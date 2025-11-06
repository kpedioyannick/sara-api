<?php

namespace App\Controller;

use App\Controller\Trait\CoachTrait;
use App\Entity\Family;
use App\Entity\ParentUser;
use App\Entity\Student;
use App\Form\FamilyType;
use App\Form\ParentUserType;
use App\Form\StudentType;
use App\Repository\CoachRepository;
use App\Repository\FamilyRepository;
use App\Repository\ParentUserRepository;
use App\Repository\StudentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class FamilyController extends AbstractController
{
    use CoachTrait;

    public function __construct(
        private readonly FamilyRepository $familyRepository,
        private readonly CoachRepository $coachRepository,
        private readonly Security $security,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ParentUserRepository $parentRepository,
        private readonly StudentRepository $studentRepository,
        private readonly ValidatorInterface $validator
    ) {
    }

    #[Route('/admin/families', name: 'admin_families_list')]
    #[IsGranted('ROLE_COACH')]
    public function list(Request $request): Response
    {
        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        
        if (!$coach) {
            throw $this->createNotFoundException('Aucun coach trouvé');
        }

        // Récupération des paramètres de filtrage
        $search = $request->query->get('search', '');
        $status = $request->query->get('status');

        // Récupération des familles avec filtrage
        $families = $this->familyRepository->findByCoachWithSearch(
            $coach,
            $search,
            $status
        );

        // Conversion en tableau pour le template
        $familiesData = array_map(fn($family) => $family->toTemplateArray($coach), $families);

        return $this->render('tailadmin/pages/families/list.html.twig', [
            'pageTitle' => 'Liste des Familles | TailAdmin',
            'pageName' => 'Familles',
            'families' => $familiesData,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
            ],
        ]);
    }

    #[Route('/admin/families/create', name: 'admin_families_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        if (!$coach) {
            return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $family = new Family(); // L'identifiant est généré automatiquement dans le constructeur
        $family->setCoach($coach);

        // Validation famille
        $errors = $this->validator->validate($family);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['success' => false, 'message' => implode(', ', $errorMessages)], 400);
        }

        $this->em->persist($family);
        $this->em->flush();

        // Créer le parent si les données sont fournies
        if (isset($data['parent']) && is_array($data['parent'])) {
            $parentData = $data['parent'];
            
            // Vérifier si un parent avec cet email existe déjà
            $existingParent = $this->parentRepository->findOneBy(['email' => $parentData['email'] ?? '']);
            if ($existingParent) {
                return new JsonResponse(['success' => false, 'message' => 'Un parent avec cet email existe déjà'], 400);
            }

            $parent = new ParentUser();
            $parent->setFamily($family);
            
            if (isset($parentData['email'])) {
                $parent->setEmail($parentData['email']);
            }
            if (isset($parentData['firstName'])) {
                $parent->setFirstName($parentData['firstName']);
            }
            if (isset($parentData['lastName'])) {
                $parent->setLastName($parentData['lastName']);
            }
            if (isset($parentData['phone'])) {
                $parent->setPhone($parentData['phone']);
            }
            
            // Mot de passe par défaut
            $defaultPassword = 'password123';
            $hashedPassword = $this->passwordHasher->hashPassword($parent, $defaultPassword);
            $parent->setPassword($hashedPassword);
            $parent->setIsActive(true);

            // Validation parent
            $errors = $this->validator->validate($parent);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return new JsonResponse(['success' => false, 'message' => implode(', ', $errorMessages)], 400);
            }

            $this->em->persist($parent);
            $this->em->flush();
        }

        return new JsonResponse(['success' => true, 'id' => $family->getId(), 'message' => 'Famille créée avec succès']);
    }

    #[Route('/admin/families/{id}/update', name: 'admin_families_update', methods: ['POST'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        if (!$coach) {
            return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
        }

        $family = $this->familyRepository->find($id);
        if (!$family || $family->getCoach() !== $coach) {
            return new JsonResponse(['success' => false, 'message' => 'Famille non trouvée'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (isset($data['familyIdentifier'])) {
            $family->setFamilyIdentifier($data['familyIdentifier']);
        }
        if (isset($data['isActive'])) {
            $family->setIsActive($data['isActive']);
        }
        $family->setUpdatedAt(new \DateTimeImmutable());

        // Validation
        $errors = $this->validator->validate($family);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['success' => false, 'message' => implode(', ', $errorMessages)], 400);
        }

        $this->em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Famille modifiée avec succès']);
    }

    #[Route('/admin/families/{id}/delete', name: 'admin_families_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        if (!$coach) {
            return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
        }

        $family = $this->familyRepository->find($id);
        if (!$family || $family->getCoach() !== $coach) {
            return new JsonResponse(['success' => false, 'message' => 'Famille non trouvée'], 404);
        }

        $this->em->remove($family);
        $this->em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/admin/families/{familyId}/parent/update', name: 'admin_families_parent_update', methods: ['POST'])]
    public function updateParent(int $familyId, Request $request): JsonResponse
    {
        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        if (!$coach) {
            return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
        }

        $family = $this->familyRepository->find($familyId);
        if (!$family || $family->getCoach() !== $coach) {
            return new JsonResponse(['success' => false, 'message' => 'Famille non trouvée'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $parent = $family->getParent();
        
        if (!$parent) {
            $parent = new ParentUser();
            $parent->setFamily($family);
            $defaultPassword = 'password123';
            $hashedPassword = $this->passwordHasher->hashPassword($parent, $defaultPassword);
            $parent->setPassword($hashedPassword);
            $parent->setIsActive(true);
        }

        if (isset($data['email'])) $parent->setEmail($data['email']);
        if (isset($data['firstName'])) $parent->setFirstName($data['firstName']);
        if (isset($data['lastName'])) $parent->setLastName($data['lastName']);
        if (isset($data['phone'])) $parent->setPhone($data['phone']);
        if (!isset($data['isActive'])) {
            $parent->setIsActive(true);
        } elseif (isset($data['isActive'])) {
            $parent->setIsActive($data['isActive']);
        }
        $parent->setUpdatedAt(new \DateTimeImmutable());

        // Validation
        $errors = $this->validator->validate($parent);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['success' => false, 'message' => implode(', ', $errorMessages)], 400);
        }

        $this->em->persist($parent);
        $this->em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Parent modifié avec succès']);
    }

    #[Route('/admin/families/{familyId}/students/create', name: 'admin_families_students_create', methods: ['POST'])]
    public function createStudent(int $familyId, Request $request): JsonResponse
    {
        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        if (!$coach) {
            return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
        }

        $family = $this->familyRepository->find($familyId);
        if (!$family || $family->getCoach() !== $coach) {
            return new JsonResponse(['success' => false, 'message' => 'Famille non trouvée'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $student = new Student();
        $student->setFamily($family);
        
        if (isset($data['email'])) $student->setEmail($data['email']);
        if (isset($data['firstName'])) $student->setFirstName($data['firstName']);
        if (isset($data['lastName'])) $student->setLastName($data['lastName']);
        if (isset($data['pseudo'])) $student->setPseudo($data['pseudo']);
        if (isset($data['class'])) $student->setClass($data['class']);
        if (isset($data['schoolName'])) $student->setSchoolName($data['schoolName']);
        if (isset($data['points'])) $student->setPoints($data['points']);
        
        $defaultPassword = 'password123';
        $hashedPassword = $this->passwordHasher->hashPassword($student, $defaultPassword);
        $student->setPassword($hashedPassword);

        // Validation
        $errors = $this->validator->validate($student);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['success' => false, 'message' => implode(', ', $errorMessages)], 400);
        }

        $this->em->persist($student);
        $this->em->flush();

        return new JsonResponse(['success' => true, 'id' => $student->getId(), 'message' => 'Élève créé avec succès']);
    }

    #[Route('/admin/families/{familyId}/students/{studentId}/update', name: 'admin_families_students_update', methods: ['POST'])]
    public function updateStudent(int $familyId, int $studentId, Request $request): JsonResponse
    {
        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        if (!$coach) {
            return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
        }

        $family = $this->familyRepository->find($familyId);
        if (!$family || $family->getCoach() !== $coach) {
            return new JsonResponse(['success' => false, 'message' => 'Famille non trouvée'], 404);
        }

        $student = $this->studentRepository->find($studentId);
        if (!$student || $student->getFamily() !== $family) {
            return new JsonResponse(['success' => false, 'message' => 'Élève non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (isset($data['email'])) $student->setEmail($data['email']);
        if (isset($data['firstName'])) $student->setFirstName($data['firstName']);
        if (isset($data['lastName'])) $student->setLastName($data['lastName']);
        if (isset($data['pseudo'])) $student->setPseudo($data['pseudo']);
        if (isset($data['class'])) $student->setClass($data['class']);
        if (isset($data['schoolName'])) $student->setSchoolName($data['schoolName']);
        if (isset($data['points'])) $student->setPoints($data['points']);
        $student->setUpdatedAt(new \DateTimeImmutable());

        // Validation
        $errors = $this->validator->validate($student);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['success' => false, 'message' => implode(', ', $errorMessages)], 400);
        }

        $this->em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Élève modifié avec succès']);
    }

    #[Route('/admin/families/{familyId}/students/{studentId}/delete', name: 'admin_families_students_delete', methods: ['DELETE'])]
    public function deleteStudent(int $familyId, int $studentId): JsonResponse
    {
        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        if (!$coach) {
            return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
        }

        $family = $this->familyRepository->find($familyId);
        if (!$family || $family->getCoach() !== $coach) {
            return new JsonResponse(['success' => false, 'message' => 'Famille non trouvée'], 404);
        }

        $student = $this->studentRepository->find($studentId);
        if (!$student || $student->getFamily() !== $family) {
            return new JsonResponse(['success' => false, 'message' => 'Élève non trouvé'], 404);
        }

        $this->em->remove($student);
        $this->em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Élève supprimé avec succès']);
    }
}
