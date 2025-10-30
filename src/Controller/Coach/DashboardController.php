<?php

namespace App\Controller\Coach;

use App\Repository\FamilyRepository;
use App\Repository\ObjectiveRepository;
use App\Repository\RequestRepository;
use App\Repository\StudentRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/coach')]
class DashboardController extends BaseCoachController
{
    public function __construct(
        private FamilyRepository $familyRepository,
        private ObjectiveRepository $objectiveRepository,
        private RequestRepository $requestRepository,
        private StudentRepository $studentRepository
    ) {}

    #[Route('/dashboard', name: 'coach_dashboard', methods: ['GET'])]
    public function dashboard(): JsonResponse
    {
        $coach = $this->getCoach();
        
        $data = [
            'coach' => $coach->toArray(),
            'stats' => $coach->getStats(),
            'recentActivity' => $this->getRecentActivity($coach),
            'families' => $this->getFamiliesData($coach),
            'objectives' => $this->getObjectivesData($coach),
            'requests' => $this->getRequestsData($coach)
        ];

        return $this->successResponse($data, 'Coach dashboard retrieved successfully');
    }

    private function getRecentActivity($coach): array
    {
        return [
            'lastLogin' => $coach->getUpdatedAt()?->format('Y-m-d H:i:s'),
            'recentFamilies' => $this->familyRepository->findBy(['coach' => $coach], ['createdAt' => 'DESC'], 5),
            'recentObjectives' => $this->objectiveRepository->findBy(['coach' => $coach], ['createdAt' => 'DESC'], 5)
        ];
    }

    private function getFamiliesData($coach): array
    {
        $families = $this->familyRepository->findBy(['coach' => $coach]);
        return array_map(fn($family) => $family->toArray(), $families);
    }

    private function getObjectivesData($coach): array
    {
        $objectives = $this->objectiveRepository->findBy(['coach' => $coach]);
        return array_map(fn($objective) => $objective->toArray(), $objectives);
    }

    private function getRequestsData($coach): array
    {
        $requests = $this->requestRepository->findBy(['coach' => $coach]);
        return array_map(fn($request) => $request->toArray(), $requests);
    }
}
