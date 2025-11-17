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
use App\Repository\PlanningRepository;
use App\Repository\SpecialistRepository;
use App\Repository\StudentRepository;
use App\Service\PermissionService;
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
        private readonly SpecialistRepository $specialistRepository,
        private readonly PlanningRepository $planningRepository,
        private readonly ValidatorInterface $validator,
        private readonly PermissionService $permissionService
    ) {
    }

    #[Route('/admin/families', name: 'admin_families_list')]
    #[IsGranted('ROLE_USER')]
    public function list(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté');
        }

        // Vérifier l'accès au menu Famille
        if (!$this->permissionService->canAccessFamilyMenu($user)) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette page');
        }

        // Récupération des paramètres de filtrage
        $search = $request->query->get('search', '');
        $status = $request->query->get('status');
        $profileType = $request->query->get('profileType'); // 'parent', 'specialist', 'student', ou null
        $selectedIds = $request->query->get('selectedIds', ''); // IDs séparés par des virgules

        // Récupération des familles selon le rôle
        $families = [];
        $coach = null;
        if ($user->isCoach()) {
            // Si l'utilisateur est un coach, utiliser directement l'utilisateur connecté
            $coach = $user instanceof \App\Entity\Coach ? $user : $this->getCurrentCoach($this->coachRepository, $this->security);
            if (!$coach) {
                throw $this->createAccessDeniedException('Vous devez être un coach pour accéder à cette page');
            }
            $families = $this->familyRepository->findByCoachWithSearch(
                $coach,
                $search,
                $status
            );
            
            // Appliquer les filtres par profil si spécifiés
            if ($profileType && $selectedIds) {
                $ids = array_filter(array_map('intval', explode(',', $selectedIds)));
                if (!empty($ids)) {
                    $filteredFamilies = [];
                    foreach ($families as $family) {
                        $shouldInclude = false;
                        
                        if ($profileType === 'parent') {
                            // Filtrer par parent
                            if ($family->getParent() && in_array($family->getParent()->getId(), $ids)) {
                                $shouldInclude = true;
                            }
                        } elseif ($profileType === 'specialist') {
                            // Filtrer par spécialiste (si au moins un élève a ce spécialiste)
                            foreach ($family->getStudents() as $student) {
                                foreach ($student->getSpecialists() as $specialist) {
                                    if (in_array($specialist->getId(), $ids)) {
                                        $shouldInclude = true;
                                        break 2;
                                    }
                                }
                            }
                        } elseif ($profileType === 'student') {
                            // Filtrer par élève
                            foreach ($family->getStudents() as $student) {
                                if (in_array($student->getId(), $ids)) {
                                    $shouldInclude = true;
                                    break;
                                }
                            }
                        }
                        
                        if ($shouldInclude) {
                            $filteredFamilies[] = $family;
                        }
                    }
                    $families = $filteredFamilies;
                }
            }
        } elseif ($user->isParent()) {
            // Les parents voient uniquement leur propre famille
            $family = $user->getFamily();
            if ($family) {
                $families = [$family];
            }
        }

        // Trier les familles par date de création décroissante (les plus récentes en premier)
        usort($families, function($a, $b) {
            $dateA = $a->getCreatedAt();
            $dateB = $b->getCreatedAt();
            if ($dateA == $dateB) {
                return 0;
            }
            return ($dateA > $dateB) ? -1 : 1;
        });

        // Conversion en tableau pour le template
        $familiesData = array_map(function($family) use ($coach) {
            $familyData = $family->toTemplateArray($coach);
            
            // Ajouter le nombre de plannings pour chaque étudiant
            if (isset($familyData['students'])) {
                foreach ($familyData['students'] as &$student) {
                    $studentEntity = $this->studentRepository->find($student['id']);
                    if ($studentEntity) {
                        $student['planningsCount'] = $this->planningRepository->createQueryBuilder('p')
                            ->select('COUNT(p.id)')
                            ->where('p.user = :user')
                            ->setParameter('user', $studentEntity)
                            ->getQuery()
                            ->getSingleScalarResult();
                    } else {
                        $student['planningsCount'] = 0;
                    }
                }
                unset($student);
            }
            
            return $familyData;
        }, $families);

        // Récupérer tous les spécialistes pour la sélection
        $specialists = $this->specialistRepository->findAll();
        $specialistsData = array_map(fn($s) => [
            'id' => $s->getId(),
            'firstName' => $s->getFirstName(),
            'lastName' => $s->getLastName(),
        ], $specialists);

        // Récupérer les parents et élèves pour les filtres (uniquement pour les coaches)
        $parentsData = [];
        $studentsData = [];
        if ($user->isCoach() && $coach) {
            $allFamilies = $this->familyRepository->findByCoachWithSearch($coach);
            foreach ($allFamilies as $family) {
                $parent = $family->getParent();
                if ($parent) {
                    $parentsData[] = [
                        'id' => $parent->getId(),
                        'firstName' => $parent->getFirstName(),
                        'lastName' => $parent->getLastName(),
                        'email' => $parent->getEmail(),
                    ];
                }
                foreach ($family->getStudents() as $student) {
                    $studentsData[] = [
                        'id' => $student->getId(),
                        'firstName' => $student->getFirstName(),
                        'lastName' => $student->getLastName(),
                        'pseudo' => $student->getPseudo(),
                        'class' => $student->getClass(),
                    ];
                }
            }
        }

        return $this->render('tailadmin/pages/families/list.html.twig', [
            'pageTitle' => 'Groupe',
            'pageName' => 'families',
            'families' => $familiesData,
            'specialists' => $specialistsData,
            'parents' => $parentsData,
            'students' => $studentsData,
            'profileType' => $profileType,
            'selectedIds' => $selectedIds,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
            ],
        ]);
    }

    #[Route('/admin/families/create', name: 'admin_families_create', methods: ['POST'])]
    #[IsGranted('ROLE_COACH')]
    public function create(Request $request): JsonResponse
    {
        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        if (!$coach) {
            return new JsonResponse(['success' => false, 'message' => 'Coach non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true);
        
        // Vérifier que les données du parent sont fournies et valides
        if (!isset($data['parent']) || !is_array($data['parent'])) {
            return new JsonResponse(['success' => false, 'message' => 'Les données du parent sont requises'], 400);
        }
        
        $parentData = $data['parent'];
        
        // Vérifier les champs obligatoires
        if (empty($parentData['email']) || empty($parentData['firstName']) || empty($parentData['lastName'])) {
            return new JsonResponse(['success' => false, 'message' => 'Tous les champs du parent sont obligatoires'], 400);
        }
        
        // Vérifier si un parent avec cet email existe déjà
        $existingParent = $this->parentRepository->findOneBy(['email' => $parentData['email']]);
        if ($existingParent) {
            return new JsonResponse(['success' => false, 'message' => 'Un parent avec cet email existe déjà'], 400);
        }

        // Créer le parent d'abord pour valider avant de créer la famille
        $parent = new ParentUser();
        
        $parent->setEmail($parentData['email']);
        $parent->setFirstName($parentData['firstName']);
        $parent->setLastName($parentData['lastName']);
        
        // Mot de passe par défaut
        $defaultPassword = 'password123';
        $hashedPassword = $this->passwordHasher->hashPassword($parent, $defaultPassword);
        $parent->setPassword($hashedPassword);
        $parent->setIsActive(true);

        // Validation parent AVANT de créer la famille
        $errors = $this->validator->validate($parent);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['success' => false, 'message' => implode(', ', $errorMessages)], 400);
        }

        // Créer la famille seulement si le parent est valide
        $family = new Family();
        $family->setCoach($coach);
        
        // Définir le type de famille (FAMILY ou GROUP)
        if (isset($data['type'])) {
            try {
                $familyType = \App\Enum\FamilyType::from($data['type']);
                $family->setType($familyType);
            } catch (\ValueError $e) {
                return new JsonResponse(['success' => false, 'message' => 'Type de famille invalide'], 400);
            }
        }

        // Si c'est un groupe, ajouter le lieu et les spécialistes
        if ($family->getType() === \App\Enum\FamilyType::GROUP) {
            if (isset($data['location'])) {
                $family->setLocation($data['location']);
            }
            
            // Gérer les spécialistes
            if (isset($data['specialistIds']) && is_array($data['specialistIds'])) {
                $family->getSpecialists()->clear();
                $specialists = $this->specialistRepository->findBy(['id' => $data['specialistIds']]);
                foreach ($specialists as $specialist) {
                    $family->addSpecialist($specialist);
                }
            }
        }

        // Validation famille
        $errors = $this->validator->validate($family);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['success' => false, 'message' => implode(', ', $errorMessages)], 400);
        }

        // Lier le parent à la famille
        $parent->setFamily($family);

        // Persister et sauvegarder en une seule transaction
        $this->em->persist($family);
        $this->em->persist($parent);
        $this->em->flush();

        return new JsonResponse(['success' => true, 'id' => $family->getId(), 'message' => 'Famille créée avec succès']);
    }

    #[Route('/admin/families/{id}/update', name: 'admin_families_update', methods: ['POST'])]
    #[IsGranted('ROLE_COACH')]
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
        
        // Si c'est un groupe, mettre à jour le lieu et les spécialistes
        if ($family->getType() === \App\Enum\FamilyType::GROUP) {
            if (isset($data['location'])) {
                $family->setLocation($data['location']);
            }
            
            // Gérer les spécialistes
            if (isset($data['specialistIds']) && is_array($data['specialistIds'])) {
                $family->getSpecialists()->clear();
                $specialists = $this->specialistRepository->findBy(['id' => $data['specialistIds']]);
                foreach ($specialists as $specialist) {
                    $family->addSpecialist($specialist);
                }
            }
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
    #[IsGranted('ROLE_COACH')]
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
    #[IsGranted('ROLE_COACH')]
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
        if (!isset($data['isActive'])) {
            $parent->setIsActive(true);
        } elseif (isset($data['isActive'])) {
            $parent->setIsActive($data['isActive']);
        }
        
        // Gérer le changement de mot de passe si fourni
        if (isset($data['newPassword']) && !empty(trim($data['newPassword']))) {
            $hashedPassword = $this->passwordHasher->hashPassword($parent, $data['newPassword']);
            $parent->setPassword($hashedPassword);
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
    #[IsGranted('ROLE_USER')]
    public function createStudent(int $familyId, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Vous devez être connecté'], 403);
        }

        $family = $this->familyRepository->find($familyId);
        if (!$family) {
            return new JsonResponse(['success' => false, 'message' => 'Famille non trouvée'], 404);
        }

        // Vérifier les permissions
        if ($user->isCoach()) {
            $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
            if (!$coach || $family->getCoach() !== $coach) {
                return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas accès à cette famille'], 403);
            }
        } elseif ($user->isParent()) {
            // Les parents peuvent créer des enfants dans leur famille
            if ($user->getFamily() !== $family) {
                return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas accès à cette famille'], 403);
            }
        } else {
            return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas le droit de créer des enfants'], 403);
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
        if (isset($data['needTags']) && is_array($data['needTags'])) {
            $student->setNeedTags($data['needTags']);
        }
        
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
    #[IsGranted('ROLE_USER')]
    public function updateStudent(int $familyId, int $studentId, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Vous devez être connecté'], 403);
        }

        $family = $this->familyRepository->find($familyId);
        if (!$family) {
            return new JsonResponse(['success' => false, 'message' => 'Famille non trouvée'], 404);
        }

        $student = $this->studentRepository->find($studentId);
        if (!$student) {
            return new JsonResponse(['success' => false, 'message' => 'Élève non trouvé'], 404);
        }

        // Vérifier les permissions de modification
        if (!$this->permissionService->canModifyStudent($user, $student)) {
            return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas le droit de modifier cet élève'], 403);
        }

        // Vérifier que l'élève appartient bien à la famille
        if ($student->getFamily() !== $family) {
            return new JsonResponse(['success' => false, 'message' => 'L\'élève n\'appartient pas à cette famille'], 403);
        }

        $data = json_decode($request->getContent(), true);
        if (isset($data['email'])) $student->setEmail($data['email']);
        if (isset($data['firstName'])) $student->setFirstName($data['firstName']);
        if (isset($data['lastName'])) $student->setLastName($data['lastName']);
        if (isset($data['pseudo'])) $student->setPseudo($data['pseudo']);
        if (isset($data['class'])) $student->setClass($data['class']);
        if (isset($data['schoolName'])) $student->setSchoolName($data['schoolName']);
        if (isset($data['points'])) $student->setPoints($data['points']);
        if (isset($data['needTags']) && is_array($data['needTags'])) {
            $student->setNeedTags($data['needTags']);
        }
        if (isset($data['isActive'])) {
            $student->setIsActive((bool)$data['isActive']);
        }
        
        // Gérer le changement de mot de passe si fourni
        if (isset($data['newPassword']) && !empty(trim($data['newPassword']))) {
            $hashedPassword = $this->passwordHasher->hashPassword($student, $data['newPassword']);
            $student->setPassword($hashedPassword);
        }
        
        // Gérer l'assignation des spécialistes - uniquement pour les coaches
        if ($user->isCoach() && isset($data['specialistIds']) && is_array($data['specialistIds'])) {
            // Retirer tous les spécialistes actuels
            foreach ($student->getSpecialists()->toArray() as $specialist) {
                $student->removeSpecialist($specialist);
            }
            
            // Ajouter les nouveaux spécialistes
            foreach ($data['specialistIds'] as $specialistId) {
                $specialist = $this->specialistRepository->find($specialistId);
                if ($specialist) {
                    $student->addSpecialist($specialist);
                }
            }
        }
        
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
    #[IsGranted('ROLE_USER')]
    public function deleteStudent(int $familyId, int $studentId): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Vous devez être connecté'], 403);
        }

        $family = $this->familyRepository->find($familyId);
        if (!$family) {
            return new JsonResponse(['success' => false, 'message' => 'Famille non trouvée'], 404);
        }

        $student = $this->studentRepository->find($studentId);
        if (!$student) {
            return new JsonResponse(['success' => false, 'message' => 'Élève non trouvé'], 404);
        }

        // Vérifier les permissions de modification
        if (!$this->permissionService->canModifyStudent($user, $student)) {
            return new JsonResponse(['success' => false, 'message' => 'Vous n\'avez pas le droit de supprimer cet élève'], 403);
        }

        // Vérifier que l'élève appartient bien à la famille
        if ($student->getFamily() !== $family) {
            return new JsonResponse(['success' => false, 'message' => 'L\'élève n\'appartient pas à cette famille'], 403);
        }

        $this->em->remove($student);
        $this->em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Élève supprimé avec succès']);
    }

    #[Route('/admin/families/{familyId}/students/{studentId}/rightsheet', name: 'admin_families_students_rightsheet', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getStudentRightSheet(int $familyId, int $studentId): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté');
        }

        $family = $this->familyRepository->find($familyId);
        if (!$family) {
            throw $this->createNotFoundException('Famille non trouvée');
        }

        $student = $this->studentRepository->find($studentId);
        if (!$student) {
            throw $this->createNotFoundException('Élève non trouvé');
        }

        // Vérifier que l'élève appartient à la famille
        if ($student->getFamily()->getId() !== $family->getId()) {
            throw $this->createAccessDeniedException('Cet élève n\'appartient pas à cette famille');
        }

        // Vérifier les permissions
        if ($user->isCoach()) {
            $coach = $user instanceof \App\Entity\Coach ? $user : $this->getCurrentCoach($this->coachRepository, $this->security);
            if (!$coach || $family->getCoach()->getId() !== $coach->getId()) {
                throw $this->createAccessDeniedException('Vous n\'avez pas la permission de voir cet élève');
            }
        } elseif ($user->isParent()) {
            if ($family->getParent()->getId() !== $user->getId()) {
                throw $this->createAccessDeniedException('Vous n\'avez pas la permission de voir cet élève');
            }
        } else {
            throw $this->createAccessDeniedException('Vous n\'avez pas la permission de voir cet élève');
        }

        // Compter les objectifs, demandes et plannings
        $objectivesCount = $student->getObjectives()->count();
        $requestsCount = $student->getRequests()->count();
        $planningsCount = $this->planningRepository->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.user = :user')
            ->setParameter('user', $student)
            ->getQuery()
            ->getSingleScalarResult();

        // Récupérer tous les spécialistes pour la sélection (si coach)
        $specialists = [];
        if ($user->isCoach()) {
            $specialists = $this->specialistRepository->findAll();
        }

        return $this->render('tailadmin/pages/families/student_rightsheet.html.twig', [
            'student' => $student,
            'family' => $family,
            'objectivesCount' => $objectivesCount,
            'requestsCount' => $requestsCount,
            'planningsCount' => $planningsCount,
            'specialists' => $specialists,
        ]);
    }

    #[Route('/admin/students/need-tags', name: 'admin_students_need_tags', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getNeedTags(): JsonResponse
    {
        // Tags prédéfinis
        $predefinedTags = [
            'Comportement',
            'Problème scolaire',
            'Gestion émotionnelle',
            'Concentration',
            'Motivation',
            'Organisation',
            'Autonomie',
            'Confiance en soi',
            'Relations sociales',
            'Communication',
        ];
        
        // Récupérer tous les étudiants avec leurs tags
        $students = $this->studentRepository->findAll();
        
        // Extraire tous les tags uniques depuis la base de données
        $allTags = [];
        foreach ($students as $student) {
            $tags = $student->getNeedTags();
            if ($tags && is_array($tags)) {
                foreach ($tags as $tag) {
                    if (!empty(trim($tag)) && !in_array($tag, $allTags, true)) {
                        $allTags[] = trim($tag);
                    }
                }
            }
        }
        
        // Fusionner les tags prédéfinis avec ceux de la base de données
        // (en évitant les doublons)
        $allTags = array_unique(array_merge($predefinedTags, $allTags));
        
        // Trier par ordre alphabétique
        sort($allTags);
        
        return new JsonResponse([
            'success' => true,
            'tags' => array_values($allTags) // array_values pour réindexer
        ]);
    }
}
