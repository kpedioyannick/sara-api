<?php

namespace App\Controller;

use App\Controller\Trait\CoachTrait;
use App\Entity\Coach;
use App\Entity\ParentUser;
use App\Entity\Student;
use App\Entity\Specialist;
use App\Repository\CoachRepository;
use App\Repository\ObjectiveRepository;
use App\Repository\RequestRepository;
use App\Repository\FamilyRepository;
use App\Repository\StudentRepository;
use App\Repository\SpecialistRepository;
use App\Service\PermissionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/search')]
#[IsGranted('ROLE_USER')]
class SearchController extends AbstractController
{
    use CoachTrait;

    public function __construct(
        private readonly ObjectiveRepository $objectiveRepository,
        private readonly RequestRepository $requestRepository,
        private readonly FamilyRepository $familyRepository,
        private readonly StudentRepository $studentRepository,
        private readonly SpecialistRepository $specialistRepository,
        private readonly CoachRepository $coachRepository,
        private readonly Security $security,
        private readonly PermissionService $permissionService
    ) {
    }

    #[Route('', name: 'admin_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        
        if (empty($query)) {
            return new JsonResponse(['results' => []]);
        }

        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['results' => []]);
        }

        $results = [];

        // Recherche selon le type d'utilisateur
        if ($user instanceof Coach) {
            $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
            if (!$coach) {
                return new JsonResponse(['results' => []]);
            }

            // Recherche dans les objectifs
            $objectives = $this->objectiveRepository->findByCoachWithSearch($coach, $query);
            foreach ($objectives as $objective) {
                $results[] = [
                    'type' => 'objective',
                    'id' => $objective->getId(),
                    'title' => $objective->getTitle(),
                    'url' => $this->generateUrl('admin_objectives_detail', ['id' => $objective->getId()]),
                    'description' => $objective->getDescription(),
                ];
            }

            // Recherche dans les demandes
            $requests = $this->requestRepository->findByCoachWithSearch($coach, $query);
            foreach ($requests as $requestItem) {
                $results[] = [
                    'type' => 'request',
                    'id' => $requestItem->getId(),
                    'title' => $requestItem->getTitle(),
                    'url' => $this->generateUrl('admin_requests_detail', ['id' => $requestItem->getId()]),
                    'description' => $requestItem->getDescription(),
                ];
            }

            // Recherche dans les familles
            $families = $this->familyRepository->findByCoachWithSearch($coach, $query);
            foreach ($families as $family) {
                $results[] = [
                    'type' => 'family',
                    'id' => $family->getId(),
                    'title' => $family->getFamilyIdentifier(),
                    'url' => $this->generateUrl('admin_families_list'),
                    'description' => 'Famille',
                ];
            }

            // Recherche dans les élèves
            $students = $this->studentRepository->findByCoachWithSearch($coach, $query);
            foreach ($students as $student) {
                $results[] = [
                    'type' => 'student',
                    'id' => $student->getId(),
                    'title' => $student->getFirstName() . ' ' . $student->getLastName(),
                    'url' => $this->generateUrl('admin_families_list'),
                    'description' => 'Élève - ' . ($student->getClass() ?? 'N/A'),
                ];
            }

            // Recherche dans les spécialistes
            $specialists = $this->specialistRepository->findByWithSearch($query);
            foreach ($specialists as $specialist) {
                $results[] = [
                    'type' => 'specialist',
                    'id' => $specialist->getId(),
                    'title' => $specialist->getFirstName() . ' ' . $specialist->getLastName(),
                    'url' => $this->generateUrl('admin_specialists_list'),
                    'description' => 'Spécialiste',
                ];
            }
        } elseif ($user instanceof ParentUser) {
            // Pour les parents : recherche dans leurs enfants et leurs demandes
            $family = $user->getFamily();
            if ($family) {
                // Recherche dans les élèves de la famille
                foreach ($family->getStudents() as $student) {
                    $fullName = $student->getFirstName() . ' ' . $student->getLastName();
                    if (stripos($fullName, $query) !== false || 
                        stripos($student->getPseudo() ?? '', $query) !== false ||
                        stripos($student->getClass() ?? '', $query) !== false) {
                        $results[] = [
                            'type' => 'student',
                            'id' => $student->getId(),
                            'title' => $fullName,
                            'url' => $this->generateUrl('admin_families_list'),
                            'description' => 'Élève - ' . ($student->getClass() ?? 'N/A'),
                        ];
                    }
                }

                // Recherche dans les demandes accessibles
                $accessibleRequests = $this->permissionService->getAccessibleRequests($user);
                foreach ($accessibleRequests as $requestItem) {
                    if (stripos($requestItem->getTitle() ?? '', $query) !== false ||
                        stripos($requestItem->getDescription() ?? '', $query) !== false) {
                        $results[] = [
                            'type' => 'request',
                            'id' => $requestItem->getId(),
                            'title' => $requestItem->getTitle(),
                            'url' => $this->generateUrl('admin_requests_detail', ['id' => $requestItem->getId()]),
                            'description' => $requestItem->getDescription(),
                        ];
                    }
                }

                // Recherche dans les objectifs accessibles
                $accessibleObjectives = $this->permissionService->getAccessibleObjectives($user);
                foreach ($accessibleObjectives as $objective) {
                    if (stripos($objective->getTitle() ?? '', $query) !== false ||
                        stripos($objective->getDescription() ?? '', $query) !== false) {
                        $results[] = [
                            'type' => 'objective',
                            'id' => $objective->getId(),
                            'title' => $objective->getTitle(),
                            'url' => $this->generateUrl('admin_objectives_detail', ['id' => $objective->getId()]),
                            'description' => $objective->getDescription(),
                        ];
                    }
                }
            }
        } elseif ($user instanceof Student) {
            // Pour les élèves : recherche dans leurs propres données
            $fullName = $user->getFirstName() . ' ' . $user->getLastName();
            if (stripos($fullName, $query) !== false || 
                stripos($user->getPseudo() ?? '', $query) !== false) {
                $results[] = [
                    'type' => 'student',
                    'id' => $user->getId(),
                    'title' => $fullName,
                    'url' => $this->generateUrl('admin_families_list'),
                    'description' => 'Élève - ' . ($user->getClass() ?? 'N/A'),
                ];
            }

            // Recherche dans les demandes accessibles
            $accessibleRequests = $this->permissionService->getAccessibleRequests($user);
            foreach ($accessibleRequests as $requestItem) {
                if (stripos($requestItem->getTitle() ?? '', $query) !== false ||
                    stripos($requestItem->getDescription() ?? '', $query) !== false) {
                    $results[] = [
                        'type' => 'request',
                        'id' => $requestItem->getId(),
                        'title' => $requestItem->getTitle(),
                        'url' => $this->generateUrl('admin_requests_detail', ['id' => $requestItem->getId()]),
                        'description' => $requestItem->getDescription(),
                    ];
                }
            }

            // Recherche dans les objectifs accessibles
            $accessibleObjectives = $this->permissionService->getAccessibleObjectives($user);
            foreach ($accessibleObjectives as $objective) {
                if (stripos($objective->getTitle() ?? '', $query) !== false ||
                    stripos($objective->getDescription() ?? '', $query) !== false) {
                    $results[] = [
                        'type' => 'objective',
                        'id' => $objective->getId(),
                        'title' => $objective->getTitle(),
                        'url' => $this->generateUrl('admin_objectives_detail', ['id' => $objective->getId()]),
                        'description' => $objective->getDescription(),
                    ];
                }
            }
        } elseif ($user instanceof Specialist) {
            // Pour les spécialistes : recherche dans leurs élèves assignés et leurs demandes
            $assignedStudents = $user->getStudents();
            foreach ($assignedStudents as $student) {
                $fullName = $student->getFirstName() . ' ' . $student->getLastName();
                if (stripos($fullName, $query) !== false || 
                    stripos($student->getPseudo() ?? '', $query) !== false) {
                    $results[] = [
                        'type' => 'student',
                        'id' => $student->getId(),
                        'title' => $fullName,
                        'url' => $this->generateUrl('admin_families_list'),
                        'description' => 'Élève - ' . ($student->getClass() ?? 'N/A'),
                    ];
                }
            }

            // Recherche dans les demandes accessibles
            $accessibleRequests = $this->permissionService->getAccessibleRequests($user);
            foreach ($accessibleRequests as $requestItem) {
                if (stripos($requestItem->getTitle() ?? '', $query) !== false ||
                    stripos($requestItem->getDescription() ?? '', $query) !== false) {
                    $results[] = [
                        'type' => 'request',
                        'id' => $requestItem->getId(),
                        'title' => $requestItem->getTitle(),
                        'url' => $this->generateUrl('admin_requests_detail', ['id' => $requestItem->getId()]),
                        'description' => $requestItem->getDescription(),
                    ];
                }
            }
        }

        return new JsonResponse(['results' => $results]);
    }
}

