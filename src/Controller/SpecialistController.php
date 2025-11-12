<?php

namespace App\Controller;

use App\Entity\Specialist;
use App\Repository\SpecialistRepository;
use App\Repository\StudentRepository;
use App\Service\PermissionService;
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
        private readonly ValidatorInterface $validator,
        private readonly StudentRepository $studentRepository,
        private readonly PermissionService $permissionService
    ) {
    }

    #[Route('/admin/specialists', name: 'admin_specialists_list')]
    #[IsGranted('ROLE_USER')]
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

        // Récupérer les élèves pour l'assignation (uniquement pour les coaches)
        $studentsData = [];
        $user = $this->getUser();
        if ($user && method_exists($user, 'isCoach') && $user->isCoach()) {
            $students = $this->studentRepository->findByCoach($user);
            $studentsData = array_map(fn($student) => [
                'id' => $student->getId(),
                'firstName' => $student->getFirstName(),
                'lastName' => $student->getLastName(),
                'pseudo' => $student->getPseudo(),
            ], $students);
        }

        return $this->render('tailadmin/pages/specialists/list.html.twig', [
            'pageTitle' => 'Liste des Spécialistes | TailAdmin',
            'pageName' => 'Spécialistes',
            'specialists' => $specialistsData,
            'studentsData' => $studentsData,
            'isCoach' => $user && method_exists($user, 'isCoach') && $user->isCoach(),
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
        
        // Vérifier que le mot de passe et la confirmation sont fournis et correspondent
        if (!isset($data['password']) || empty($data['password'])) {
            return new JsonResponse(['success' => false, 'message' => 'Le mot de passe est requis'], 400);
        }
        if (!isset($data['passwordConfirmation']) || $data['password'] !== $data['passwordConfirmation']) {
            return new JsonResponse(['success' => false, 'message' => 'Les mots de passe ne correspondent pas'], 400);
        }
        if (strlen($data['password']) < 6) {
            return new JsonResponse(['success' => false, 'message' => 'Le mot de passe doit contenir au moins 6 caractères'], 400);
        }
        
        $hashedPassword = $this->passwordHasher->hashPassword($specialist, $data['password']);
        $specialist->setPassword($hashedPassword);

        // Gérer l'assignation des élèves (uniquement pour les coaches)
        $user = $this->getUser();
        if ($user && method_exists($user, 'isCoach') && $user->isCoach() && isset($data['studentIds'])) {
            $studentIds = is_array($data['studentIds']) ? $data['studentIds'] : [];
            
            // Récupérer tous les élèves du coach
            $coachStudents = $this->studentRepository->findByCoach($user);
            $coachStudentIds = array_map(fn($s) => $s->getId(), $coachStudents);
            
            // Ajouter les élèves sélectionnés (vérifier qu'ils appartiennent au coach)
            foreach ($studentIds as $studentId) {
                if (in_array($studentId, $coachStudentIds)) {
                    $student = $this->studentRepository->find($studentId);
                    if ($student) {
                        $specialist->addStudent($student);
                    }
                }
            }
        }

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

        // Gérer l'assignation/révocation des élèves (uniquement pour les coaches)
        $user = $this->getUser();
        if ($user && method_exists($user, 'isCoach') && $user->isCoach() && isset($data['studentIds'])) {
            $studentIds = is_array($data['studentIds']) ? $data['studentIds'] : [];
            
            // Récupérer tous les élèves du coach
            $coachStudents = $this->studentRepository->findByCoach($user);
            $coachStudentIds = array_map(fn($s) => $s->getId(), $coachStudents);
            
            // Retirer tous les élèves actuels
            foreach ($specialist->getStudents()->toArray() as $student) {
                $specialist->removeStudent($student);
            }
            
            // Ajouter les nouveaux élèves sélectionnés (vérifier qu'ils appartiennent au coach)
            foreach ($studentIds as $studentId) {
                if (in_array($studentId, $coachStudentIds)) {
                    $student = $this->studentRepository->find($studentId);
                    if ($student) {
                        $specialist->addStudent($student);
                    }
                }
            }
        }

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

    #[Route('/admin/specialists/{id}/students', name: 'admin_specialists_get_students', methods: ['GET'])]
    #[IsGranted('ROLE_COACH')]
    public function getStudents(int $id): JsonResponse
    {
        $specialist = $this->specialistRepository->find($id);
        if (!$specialist) {
            return new JsonResponse(['success' => false, 'message' => 'Spécialiste non trouvé'], 404);
        }

        $studentIds = array_map(fn($student) => $student->getId(), $specialist->getStudents()->toArray());

        return new JsonResponse([
            'success' => true,
            'studentIds' => $studentIds,
        ]);
    }

    #[Route('/admin/specialists/{id}/change-password', name: 'admin_specialists_change_password', methods: ['POST'])]
    #[IsGranted('ROLE_COACH')]
    public function changePassword(int $id, Request $request): JsonResponse
    {
        $specialist = $this->specialistRepository->find($id);
        if (!$specialist) {
            return new JsonResponse(['success' => false, 'message' => 'Spécialiste non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true);
        
        // Vérifier que le mot de passe et la confirmation sont fournis
        if (!isset($data['password']) || empty($data['password'])) {
            return new JsonResponse(['success' => false, 'message' => 'Le mot de passe est requis'], 400);
        }
        
        if (!isset($data['passwordConfirmation']) || $data['password'] !== $data['passwordConfirmation']) {
            return new JsonResponse(['success' => false, 'message' => 'Les mots de passe ne correspondent pas'], 400);
        }
        
        if (strlen($data['password']) < 6) {
            return new JsonResponse(['success' => false, 'message' => 'Le mot de passe doit contenir au moins 6 caractères'], 400);
        }
        
        // Hasher et mettre à jour le mot de passe
        $hashedPassword = $this->passwordHasher->hashPassword($specialist, $data['password']);
        $specialist->setPassword($hashedPassword);
        $specialist->setUpdatedAt(new \DateTimeImmutable());

        $this->em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Mot de passe changé avec succès']);
    }
}

