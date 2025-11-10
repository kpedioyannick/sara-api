<?php

namespace App\Controller;

use App\Entity\Integration;
use App\Entity\Student;
use App\Repository\IntegrationRepository;
use App\Repository\StudentRepository;
use App\Service\PronoteSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/integrations/pronote')]
#[IsGranted('ROLE_COACH')]
class PronoteController extends AbstractController
{
    public function __construct(
        private readonly IntegrationRepository $integrationRepository,
        private readonly StudentRepository $studentRepository,
        private readonly EntityManagerInterface $em,
        private readonly PronoteSyncService $pronoteSyncService
    ) {
    }

    #[Route('/connect', name: 'admin_pronote_connect', methods: ['GET', 'POST'])]
    public function connect(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleConnect($request);
        }

        $students = $this->studentRepository->findAll();
        $studentsData = array_map(fn($s) => $s->toSimpleArray(), $students);

        return $this->render('tailadmin/pages/integrations/pronote/connect.html.twig', [
            'pageTitle' => 'Connexion PRONOTE | SARA',
            'pageName' => 'integrations',
            'students' => $studentsData,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Intégrations', 'url' => $this->generateUrl('admin_integrations_list')],
                ['label' => 'Connexion PRONOTE', 'url' => ''],
            ],
        ]);
    }

    private function handleConnect(Request $request): Response
    {
        $qrCodeJson = $request->request->get('qr_code_json');
        $pin = $request->request->get('pin');
        $studentId = $request->request->get('student_id');

        if (!$qrCodeJson || !$pin || !$studentId) {
            $this->addFlash('error', 'Tous les champs sont requis.');
            return $this->redirectToRoute('admin_pronote_connect');
        }

        $student = $this->studentRepository->find($studentId);
        if (!$student) {
            $this->addFlash('error', 'Étudiant non trouvé.');
            return $this->redirectToRoute('admin_pronote_connect');
        }

        // Vérifier si une intégration PRONOTE existe déjà pour cet étudiant
        $existingIntegration = $this->integrationRepository->findOneBy([
            'type' => Integration::TYPE_PRONOTE,
            'student' => $student
        ]);

        // Parser le JSON du QR code
        $qrData = json_decode($qrCodeJson, true);
        if (!$qrData || !isset($qrData['url']) || !isset($qrData['login']) || !isset($qrData['jeton'])) {
            $this->addFlash('error', 'Format du QR code invalide.');
            return $this->redirectToRoute('admin_pronote_connect');
        }

        // Appeler le script Node.js (Pawnote.js)
        // Créer un fichier temporaire pour passer le JSON (évite les problèmes d'échappement)
        $tempFile = sys_get_temp_dir() . '/pronote_qr_' . uniqid() . '.json';
        file_put_contents($tempFile, json_encode($qrData));
        
        try {
            $process = new Process([
                'npm',
                'run',
                'sync',
                '--',
                $tempFile,
                $pin
            ], __DIR__ . '/../../scripts/pawnote');

            $process->run();
        } finally {
            // Nettoyer le fichier temporaire
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }

        // Parser la réponse JSON du script Node.js
        // npm peut ajouter des messages sur stdout, il faut extraire uniquement le JSON
        $output = trim($process->getOutput());
        $errorOutput = $process->getErrorOutput();
        
        // Log pour debug
        error_log("PRONOTE Connect - Process successful: " . ($process->isSuccessful() ? 'yes' : 'no'));
        error_log("PRONOTE Connect - Exit code: " . $process->getExitCode());
        error_log("PRONOTE Connect - Output length: " . strlen($output));
        error_log("PRONOTE Connect - Error output length: " . strlen($errorOutput));
        if (!empty($output)) {
            error_log("PRONOTE Connect - Output (first 500 chars): " . substr($output, 0, 500));
        }
        if (!empty($errorOutput)) {
            error_log("PRONOTE Connect - Error output (debug): " . substr($errorOutput, 0, 500));
        }
        
        // Extraire le JSON de la sortie (npm peut ajouter des messages avant)
        // Le JSON commence par { et se termine par }
        $result = null;
        if (!empty($output)) {
            // Chercher la première ligne qui commence par { (le JSON)
            $lines = explode("\n", $output);
            $jsonLine = null;
            foreach ($lines as $line) {
                $line = trim($line);
                if (strpos($line, '{') === 0) {
                    $jsonLine = $line;
                    break;
                }
            }
            
            // Si on n'a pas trouvé de ligne commençant par {, essayer de parser toute la sortie
            if ($jsonLine === null) {
                $jsonLine = $output;
            }
            
            // Essayer de trouver le JSON complet (peut être sur plusieurs lignes)
            // Chercher le premier { et le dernier }
            $firstBrace = strpos($jsonLine, '{');
            if ($firstBrace !== false) {
                // Chercher le dernier } après le premier {
                $lastBrace = strrpos($jsonLine, '}');
                if ($lastBrace !== false && $lastBrace > $firstBrace) {
                    $jsonLine = substr($jsonLine, $firstBrace, $lastBrace - $firstBrace + 1);
                } else {
                    // Si on n'a pas trouvé de }, essayer de parser quand même
                    $jsonLine = substr($jsonLine, $firstBrace);
                }
            }
            
            $result = json_decode($jsonLine, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("PRONOTE Connect - JSON decode error: " . json_last_error_msg());
                error_log("PRONOTE Connect - Raw output: " . substr($output, 0, 500));
                error_log("PRONOTE Connect - Extracted JSON line: " . substr($jsonLine, 0, 500));
            }
        }
        
        if (!$process->isSuccessful()) {
            // Si le processus a échoué, essayer de récupérer l'erreur depuis le JSON
            if ($result && isset($result['error'])) {
                $errorMsg = $result['error'];
            } else {
                // Extraire uniquement les lignes d'erreur (qui commencent par ❌) du stderr
                $errorLines = array_filter(explode("\n", $errorOutput), function($line) {
                    return strpos(trim($line), '❌') === 0 || strpos(trim($line), 'Erreur') === 0;
                });
                $errorMsg = !empty($errorLines) ? implode("\n", $errorLines) : 'Erreur inconnue (code: ' . $process->getExitCode() . ')';
            }
            error_log("PRONOTE Connect - Process failed: " . $errorMsg);
            $this->addFlash('error', 'Erreur lors de la connexion PRONOTE: ' . $errorMsg);
            return $this->redirectToRoute('admin_pronote_connect');
        }

        if (!$result) {
            // Extraire uniquement les lignes d'erreur du stderr
            $errorLines = array_filter(explode("\n", $errorOutput), function($line) {
                return strpos(trim($line), '❌') === 0 || strpos(trim($line), 'Erreur') === 0;
            });
            $errorMsg = !empty($errorLines) ? implode("\n", $errorLines) : (!empty($output) ? substr($output, 0, 200) : 'Impossible de parser la réponse du script');
            error_log("PRONOTE Connect - Failed to parse JSON: " . $errorMsg);
            error_log("PRONOTE Connect - Raw output: " . substr($output, 0, 500));
            $this->addFlash('error', 'Échec de la connexion: ' . $errorMsg);
            return $this->redirectToRoute('admin_pronote_connect');
        }

        if (!isset($result['success'])) {
            $errorMsg = $result['error'] ?? 'Réponse invalide du script';
            error_log("PRONOTE Connect - No success field in result: " . json_encode($result));
            $this->addFlash('error', 'Échec de la connexion: ' . $errorMsg);
            return $this->redirectToRoute('admin_pronote_connect');
        }

        if (!$result['success']) {
            $error = $result['error'] ?? $result['message'] ?? 'Erreur inconnue';
            error_log("PRONOTE Connect - Script returned success=false: " . $error);
            $this->addFlash('error', 'Échec de la connexion: ' . $error);
            return $this->redirectToRoute('admin_pronote_connect');
        }

        // Créer ou mettre à jour l'intégration
        if ($existingIntegration) {
            $integration = $existingIntegration;
        } else {
            $integration = new Integration();
            $integration->setName('Pronote - ' . $student->getFirstName() . ' ' . $student->getLastName());
            $integration->setType(Integration::TYPE_PRONOTE);
        }

        $integration->setStudent($student);
        // Le script qrcode-sync.mts retourne les credentials dans result['credentials']
        $integration->setCredentials($result['credentials']);
        $integration->setIsActive(true);
        $integration->setUpdatedAt(new \DateTimeImmutable());

        // Stocker les infos utilisateur dans metadata
        $metadata = [
            'user_info' => $result['user_info'] ?? null,
            'connected_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'sync_data' => [
                'homework' => $result['data']['homework'] ?? 0,
                'lessons' => $result['data']['lessons'] ?? 0,
                'absences' => $result['data']['absences'] ?? 0,
            ]
        ];
        $integration->setMetadata($metadata);

        $this->em->persist($integration);
        $this->em->flush();

        // Le script qrcode-sync.mts fait déjà la synchronisation complète
        // Les données sont déjà dans result['data']
        $homeworkCount = $result['data']['homework'] ?? 0;
        $lessonsCount = $result['data']['lessons'] ?? 0;
        
        // Synchroniser les données dans la base (devoirs et cours)
        try {
            // Les données sont déjà récupérées, on les synchronise dans la base
            $syncResults = $this->pronoteSyncService->syncIntegrationData($integration, $result['data']);
            $this->addFlash('success', 'Connexion PRONOTE réussie ! Synchronisation effectuée : ' . 
                $syncResults['homework'] . ' devoirs, ' . 
                $syncResults['lessons'] . ' cours synchronisés.');
        } catch (\Exception $e) {
            // Si la synchronisation échoue, on affiche quand même le succès de la connexion
            $this->addFlash('success', 'Connexion PRONOTE réussie ! ' . $homeworkCount . ' devoirs et ' . $lessonsCount . ' cours récupérés.');
            $this->addFlash('warning', 'Erreur lors de la synchronisation en base : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_integrations_list', ['student_id' => $studentId]);
    }


    #[Route('/{id}/detail', name: 'admin_integration_detail')]
    public function detail(int $id): Response
    {
        $integration = $this->integrationRepository->find($id);
        if (!$integration || $integration->getType() !== Integration::TYPE_PRONOTE) {
            throw $this->createNotFoundException('Intégration non trouvée');
        }

        return $this->render('tailadmin/pages/integrations/pronote/detail.html.twig', [
            'pageTitle' => 'Détail Intégration PRONOTE | SARA',
            'pageName' => 'integrations',
            'integration' => $integration,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Intégrations', 'url' => $this->generateUrl('admin_integrations_list')],
                ['label' => 'Détail PRONOTE', 'url' => ''],
            ],
        ]);
    }

    #[Route('/{id}/sync', name: 'admin_pronote_sync', methods: ['POST'])]
    public function sync(int $id): JsonResponse
    {
        $integration = $this->integrationRepository->find($id);
        if (!$integration || $integration->getType() !== Integration::TYPE_PRONOTE) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Intégration non trouvée'
            ], 404);
        }

        try {
            $results = $this->pronoteSyncService->syncIntegration($integration);
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Synchronisation réussie',
                'results' => $results
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de la synchronisation: ' . $e->getMessage()
            ], 500);
        }
    }
}

