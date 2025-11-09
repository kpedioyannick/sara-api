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

        // Ajouter le PIN aux données
        $qrData['pin'] = $pin;

        // Appeler le script Python
        $scriptPath = __DIR__ . '/../../scripts/pronote_qrcode_login.py';
        $process = new Process([
            'python3',
            $scriptPath,
            json_encode($qrData)
        ]);

        $process->run();

        if (!$process->isSuccessful()) {
            $errorOutput = $process->getErrorOutput();
            $this->addFlash('error', 'Erreur lors de la connexion PRONOTE: ' . $errorOutput);
            return $this->redirectToRoute('admin_pronote_connect');
        }

        // Parser la réponse JSON du script
        $output = $process->getOutput();
        $result = json_decode($output, true);

        if (!$result || !$result['success']) {
            $error = $result['error'] ?? 'Erreur inconnue';
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
        $integration->setCredentials($result['credentials']);
        $integration->setIsActive(true);
        $integration->setUpdatedAt(new \DateTimeImmutable());

        // Stocker les infos utilisateur dans metadata
        $metadata = [
            'user_info' => $result['user_info'] ?? null,
            'connected_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];
        $integration->setMetadata($metadata);

        $this->em->persist($integration);
        $this->em->flush();

        $this->addFlash('success', 'Connexion PRONOTE réussie !');
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

