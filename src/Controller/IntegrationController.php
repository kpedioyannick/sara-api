<?php

namespace App\Controller;

use App\Entity\Integration;
use App\Repository\IntegrationRepository;
use App\Repository\StudentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/admin/integrations')]
#[IsGranted('ROLE_COACH')]
class IntegrationController extends AbstractController
{
    public function __construct(
        private readonly IntegrationRepository $integrationRepository,
        private readonly StudentRepository $studentRepository,
        private readonly EntityManagerInterface $em,
        private readonly ValidatorInterface $validator
    ) {
    }

    #[Route('', name: 'admin_integrations_list')]
    public function list(Request $request): Response
    {
        // Initialiser les intégrations par défaut si elles n'existent pas
        $this->initializeDefaultIntegrations();

        $studentId = $request->query->get('student_id');
        
        $integrations = $this->integrationRepository->findAll();
        // Filtrer pour exclure Ecole Directe
        $integrations = array_filter($integrations, fn($integration) => $integration->getType() !== Integration::TYPE_ECOLE_DIRECTE);
        
        // Filtrer par étudiant si demandé
        if ($studentId) {
            $integrations = array_filter($integrations, fn($integration) => 
                $integration->getStudent() && $integration->getStudent()->getId() == $studentId
            );
        }
        
        $integrationsData = array_map(fn($integration) => $integration->toArray(), $integrations);
        
        // Récupérer tous les étudiants pour le filtre
        $students = $this->studentRepository->findAll();
        $studentsData = array_map(fn($s) => $s->toSimpleArray(), $students);

        return $this->render('tailadmin/pages/integrations/list.html.twig', [
            'pageTitle' => 'Intégrations | SARA',
            'pageName' => 'integrations',
            'integrations' => $integrationsData,
            'students' => $studentsData,
            'selectedStudentId' => $studentId,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Intégrations', 'url' => ''],
            ],
        ]);
    }

    private function initializeDefaultIntegrations(): void
    {
        // Vérifier si Pronote existe
        $pronote = $this->integrationRepository->findOneBy(['type' => Integration::TYPE_PRONOTE]);
        if (!$pronote) {
            $pronote = new Integration();
            $pronote->setName('Pronote');
            $pronote->setType(Integration::TYPE_PRONOTE);
            $pronote->setIsActive(false);
            $this->em->persist($pronote);
        }

        $this->em->flush();
    }

    #[Route('/create', name: 'admin_integrations_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['name']) || empty($data['type'])) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Le nom et le type sont requis'
            ], 400);
        }

        if (!in_array($data['type'], Integration::TYPES)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Type d\'intégration invalide'
            ], 400);
        }

        $integration = new Integration();
        $integration->setName($data['name']);
        $integration->setType($data['type']);
        $integration->setIsActive(true);

        $errors = $this->validator->validate($integration);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse([
                'success' => false,
                'message' => implode(', ', $errorMessages)
            ], 400);
        }

        $this->em->persist($integration);
        $this->em->flush();

        return new JsonResponse([
            'success' => true,
            'id' => $integration->getId(),
            'message' => 'Intégration créée avec succès'
        ]);
    }

    #[Route('/{id}/update', name: 'admin_integrations_update', methods: ['POST'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $integration = $this->integrationRepository->find($id);
        if (!$integration) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Intégration non trouvée'
            ], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) {
            $integration->setName($data['name']);
        }
        if (isset($data['type']) && in_array($data['type'], Integration::TYPES)) {
            $integration->setType($data['type']);
        }
        if (isset($data['isActive'])) {
            $integration->setIsActive($data['isActive']);
        }

        $integration->setUpdatedAt(new \DateTimeImmutable());

        $errors = $this->validator->validate($integration);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse([
                'success' => false,
                'message' => implode(', ', $errorMessages)
            ], 400);
        }

        $this->em->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Intégration modifiée avec succès'
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_integrations_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $integration = $this->integrationRepository->find($id);
        if (!$integration) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Intégration non trouvée'
            ], 404);
        }

        $this->em->remove($integration);
        $this->em->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Intégration supprimée avec succès'
        ]);
    }
}

