<?php

namespace App\Controller;

use App\Entity\Specialist;
use App\Repository\SpecialistRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class SpecialistController extends AbstractController
{
    public function __construct(
        private readonly SpecialistRepository $specialistRepository,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator
    ) {
    }

    #[Route('/admin/specialists', name: 'admin_specialists_list')]
    #[IsGranted('ROLE_COACH')]
    public function list(Request $request): Response
    {
        // Récupération des paramètres de filtrage
        $search = $request->query->get('search', '');
        $specialization = $request->query->get('specialization');
        $status = $request->query->get('status');

        // Récupération des spécialistes avec filtrage
        $specialists = $this->specialistRepository->findByWithSearch(
            $search,
            $specialization,
            $status
        );

        // Conversion en tableau pour le template
        $specialistsData = array_map(fn($specialist) => $specialist->toTemplateArray(), $specialists);

        return $this->render('tailadmin/pages/specialists/list.html.twig', [
            'pageTitle' => 'Liste des Spécialistes | TailAdmin',
            'pageName' => 'Spécialistes',
            'specialists' => $specialistsData,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
            ],
        ]);
    }

    #[Route('/admin/specialists/create', name: 'admin_specialists_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $specialist = new Specialist();
        
        if (isset($data['email'])) $specialist->setEmail($data['email']);
        if (isset($data['firstName'])) $specialist->setFirstName($data['firstName']);
        if (isset($data['lastName'])) $specialist->setLastName($data['lastName']);
        if (isset($data['specializations'])) {
            $specializations = is_array($data['specializations']) 
                ? $data['specializations'] 
                : explode(',', $data['specializations']);
            $specialist->setSpecializations($specializations);
        }
        if (isset($data['isActive'])) $specialist->setIsActive($data['isActive']);
        
        $defaultPassword = 'password123';
        $hashedPassword = $this->passwordHasher->hashPassword($specialist, $defaultPassword);
        $specialist->setPassword($hashedPassword);

        // Validation
        $errors = $this->validator->validate($specialist);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['success' => false, 'message' => implode(', ', $errorMessages)], 400);
        }

        $this->em->persist($specialist);
        $this->em->flush();

        return new JsonResponse(['success' => true, 'id' => $specialist->getId(), 'message' => 'Spécialiste créé avec succès']);
    }

    #[Route('/admin/specialists/{id}/update', name: 'admin_specialists_update', methods: ['POST'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $specialist = $this->specialistRepository->find($id);
        if (!$specialist) {
            return new JsonResponse(['success' => false, 'message' => 'Spécialiste non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (isset($data['email'])) $specialist->setEmail($data['email']);
        if (isset($data['firstName'])) $specialist->setFirstName($data['firstName']);
        if (isset($data['lastName'])) $specialist->setLastName($data['lastName']);
        if (isset($data['specializations'])) {
            $specializations = is_array($data['specializations']) 
                ? $data['specializations'] 
                : explode(',', $data['specializations']);
            $specialist->setSpecializations($specializations);
        }
        if (isset($data['isActive'])) $specialist->setIsActive($data['isActive']);
        $specialist->setUpdatedAt(new \DateTimeImmutable());

        // Validation
        $errors = $this->validator->validate($specialist);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['success' => false, 'message' => implode(', ', $errorMessages)], 400);
        }

        $this->em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Spécialiste modifié avec succès']);
    }

    #[Route('/admin/specialists/{id}/delete', name: 'admin_specialists_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $specialist = $this->specialistRepository->find($id);
        if (!$specialist) {
            return new JsonResponse(['success' => false, 'message' => 'Spécialiste non trouvé'], 404);
        }

        $this->em->remove($specialist);
        $this->em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Spécialiste supprimé avec succès']);
    }
}

