<?php

namespace App\Controller\Coach;

use App\Entity\Planning;
use App\Repository\PlanningRepository;
use App\Repository\StudentRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/coach/plannings')]
class PlanningController extends BaseCoachController
{
    public function __construct(
        private PlanningRepository $planningRepository,
        private StudentRepository $studentRepository,
        private ValidatorInterface $validator
    ) {}

    #[Route('/student-planning', name: 'coach_plannings_student_planning', methods: ['POST'])]
    public function studentPlanning(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            // L'ID de l'élève est obligatoire
            if (!isset($data['student_id']) || empty($data['student_id'])) {
                return $this->errorResponse('Field \'student_id\' is required', Response::HTTP_BAD_REQUEST);
            }

            $coach = $this->getCoach();
            $studentId = $data['student_id'];
            $type = $data['type'] ?? null;
            $status = $data['status'] ?? null;
            $startDate = $data['start_date'] ?? null;
            $endDate = $data['end_date'] ?? null;

            $criteria = [];
            
            // Vérifier que l'étudiant appartient au coach
            $student = $this->studentRepository->find($studentId);
            if (!$student || $student->getFamily()->getCoach() !== $coach) {
                return $this->errorResponse('Student not found or access denied', Response::HTTP_NOT_FOUND);
            }
            $criteria['student'] = $student;

            if ($type) {
                $criteria['type'] = $type;
            }
            if ($status) {
                $criteria['status'] = $status;
            }

            $plannings = $this->planningRepository->findBy($criteria);

            // Filtrer par date si spécifié
            if ($startDate || $endDate) {
                $plannings = array_filter($plannings, function($planning) use ($startDate, $endDate) {
                    $planningDate = $planning->getStartDate();
                    if (!$planningDate) return false;

                    if ($startDate && $planningDate < new \DateTimeImmutable($startDate)) {
                        return false;
                    }
                    if ($endDate && $planningDate > new \DateTimeImmutable($endDate)) {
                        return false;
                    }
                    return true;
                });
            }

            // Trier par date de début
            usort($plannings, function($a, $b) {
                return $a->getStartDate() <=> $b->getStartDate();
            });

            $data = array_map(fn($planning) => $planning->toArray(), $plannings);

            return $this->successResponse($data, 'Plannings retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Planning retrieval failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('', name: 'coach_plannings_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            $requiredFields = ['title', 'student_id', 'start_date', 'end_date', 'type'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return $this->errorResponse("Field '{$field}' is required", Response::HTTP_BAD_REQUEST);
                }
            }

            $student = $this->studentRepository->find($data['student_id']);
            if (!$student) {
                return $this->errorResponse('Student not found', Response::HTTP_NOT_FOUND);
            }

            // Vérifier que l'étudiant appartient au coach
            $coach = $this->getCoach();
            if ($student->getFamily()->getCoach() !== $coach) {
                return $this->errorResponse('Access denied to this student', Response::HTTP_FORBIDDEN);
            }

            // Valider le type
            if (!in_array($data['type'], Planning::TYPES)) {
                return $this->errorResponse('Invalid planning type', Response::HTTP_BAD_REQUEST);
            }

            // Valider le statut si fourni
            if (isset($data['status']) && !in_array($data['status'], Planning::STATUSES)) {
                return $this->errorResponse('Invalid planning status', Response::HTTP_BAD_REQUEST);
            }

            // Valider la récurrence si fournie
            if (isset($data['recurrence']) && !empty($data['recurrence'])) {
                $validRecurrences = ['daily', 'weekly', 'monthly', 'yearly'];
                if (!in_array($data['recurrence'], $validRecurrences)) {
                    return $this->errorResponse('Invalid recurrence type. Must be: ' . implode(', ', $validRecurrences), Response::HTTP_BAD_REQUEST);
                }
            }

            $planning = Planning::createForCoach($data, $student);

            // Gérer les champs de récurrence
            if (isset($data['recurrence']) && !empty($data['recurrence'])) {
                $planning->setRecurrence($data['recurrence']);
                $planning->setRecurrenceInterval($data['recurrence_interval'] ?? 1);
                
                if (isset($data['recurrence_end'])) {
                    $planning->setRecurrenceEnd(new \DateTimeImmutable($data['recurrence_end']));
                }
                
                if (isset($data['max_occurrences'])) {
                    $planning->setMaxOccurrences($data['max_occurrences']);
                }
            }

            $errors = $this->validator->validate($planning);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->errorResponse('Validation failed', Response::HTTP_BAD_REQUEST, $errorMessages);
            }

            $this->planningRepository->save($planning, true);

            return $this->successResponse($planning->toArray(), 'Planning created successfully', Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->errorResponse('Planning creation failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'coach_plannings_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $planning = $this->planningRepository->find($id);
        
        if (!$planning) {
            return $this->errorResponse('Planning not found', Response::HTTP_NOT_FOUND);
        }

        // Vérifier que le planning appartient à un étudiant du coach
        $coach = $this->getCoach();
        if ($planning->getStudent()->getFamily()->getCoach() !== $coach) {
            return $this->errorResponse('Access denied to this planning', Response::HTTP_FORBIDDEN);
        }

        return $this->successResponse($planning->toArray(), 'Planning retrieved successfully');
    }

    #[Route('/{id}', name: 'coach_plannings_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        try {
            $planning = $this->planningRepository->find($id);
            
            if (!$planning) {
                return $this->errorResponse('Planning not found', Response::HTTP_NOT_FOUND);
            }

            // Vérifier que le planning appartient à un étudiant du coach
            $coach = $this->getCoach();
            if ($planning->getStudent()->getFamily()->getCoach() !== $coach) {
                return $this->errorResponse('Access denied to this planning', Response::HTTP_FORBIDDEN);
            }

            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            if (isset($data['title'])) {
                $planning->setTitle($data['title']);
            }
            if (isset($data['description'])) {
                $planning->setDescription($data['description']);
            }
            if (isset($data['start_date'])) {
                $planning->setStartDate(new \DateTimeImmutable($data['start_date']));
            }
            if (isset($data['end_date'])) {
                $planning->setEndDate(new \DateTimeImmutable($data['end_date']));
            }
            if (isset($data['type'])) {
                if (!in_array($data['type'], Planning::TYPES)) {
                    return $this->errorResponse('Invalid planning type', Response::HTTP_BAD_REQUEST);
                }
                $planning->setType($data['type']);
            }
            if (isset($data['status'])) {
                if (!in_array($data['status'], Planning::STATUSES)) {
                    return $this->errorResponse('Invalid planning status', Response::HTTP_BAD_REQUEST);
                }
                $planning->setStatus($data['status']);
            }

            $planning->setUpdatedAt(new \DateTimeImmutable());

            $errors = $this->validator->validate($planning);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->errorResponse('Validation failed', Response::HTTP_BAD_REQUEST, $errorMessages);
            }

            $this->planningRepository->save($planning, true);

            return $this->successResponse($planning->toArray(), 'Planning updated successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Planning update failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'coach_plannings_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $planning = $this->planningRepository->find($id);
        
        if (!$planning) {
            return $this->errorResponse('Planning not found', Response::HTTP_NOT_FOUND);
        }

        // Vérifier que le planning appartient à un étudiant du coach
        $coach = $this->getCoach();
        if ($planning->getStudent()->getFamily()->getCoach() !== $coach) {
            return $this->errorResponse('Access denied to this planning', Response::HTTP_FORBIDDEN);
        }

        $this->planningRepository->remove($planning, true);

        return $this->successResponse(null, 'Planning deleted successfully');
    }

    #[Route('/types', name: 'coach_plannings_types', methods: ['GET'], priority: 10)]
    public function getTypes(): JsonResponse
    {
        return $this->successResponse(Planning::TYPES, 'Planning types retrieved successfully');
    }

    #[Route('/statuses', name: 'coach_plannings_statuses', methods: ['GET'], priority: 10)]
    public function getStatuses(): JsonResponse
    {
        return $this->successResponse(Planning::STATUSES, 'Planning statuses retrieved successfully');
    }




    #[Route('/calendar', name: 'coach_plannings_calendar', methods: ['POST'])]
    public function getCalendar(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            // L'ID de l'élève est obligatoire
            if (!isset($data['student_id']) || empty($data['student_id'])) {
                return $this->errorResponse('Field \'student_id\' is required', Response::HTTP_BAD_REQUEST);
            }

            $coach = $this->getCoach();
            $startDate = $data['start_date'] ?? null;
            $endDate = $data['end_date'] ?? null;
            $studentId = $data['student_id'];

            if (!$startDate || !$endDate) {
                return $this->errorResponse('start_date and end_date are required', Response::HTTP_BAD_REQUEST);
            }

            $criteria = [];
            
            // Vérifier que l'étudiant appartient au coach
            $student = $this->studentRepository->find($studentId);
            if (!$student || $student->getFamily()->getCoach() !== $coach) {
                return $this->errorResponse('Student not found or access denied', Response::HTTP_NOT_FOUND);
            }
            $criteria['student'] = $student;

            $plannings = $this->planningRepository->findBy($criteria);

            // Filtrer par période
            $start = new \DateTimeImmutable($startDate);
            $end = new \DateTimeImmutable($endDate);
            
            $filteredPlannings = array_filter($plannings, function($planning) use ($start, $end) {
                $planningDate = $planning->getStartDate();
                return $planningDate && $planningDate >= $start && $planningDate <= $end;
            });

            $data = array_map(fn($planning) => $planning->toArray(), $filteredPlannings);

            return $this->successResponse($data, 'Calendar plannings retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Calendar retrieval failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}