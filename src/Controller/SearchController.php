<?php

namespace App\Controller;

use App\Controller\Trait\CoachTrait;
use App\Repository\CoachRepository;
use App\Repository\ObjectiveRepository;
use App\Repository\RequestRepository;
use App\Repository\FamilyRepository;
use App\Repository\StudentRepository;
use App\Repository\SpecialistRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/search')]
#[IsGranted('ROLE_COACH')]
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
        private readonly Security $security
    ) {
    }

    #[Route('', name: 'admin_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        
        if (empty($query)) {
            return new JsonResponse(['results' => []]);
        }

        $coach = $this->getCurrentCoach($this->coachRepository, $this->security);
        
        if (!$coach) {
            return new JsonResponse(['results' => []]);
        }

        $results = [];

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

        return new JsonResponse(['results' => $results]);
    }
}

