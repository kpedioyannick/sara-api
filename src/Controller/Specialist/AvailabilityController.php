<?php

namespace App\Controller\Specialist;

use App\Entity\Availability;
use App\Repository\AvailabilityRepository;
use App\Repository\SpecialistRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/specialist/availability')]
class AvailabilityController extends BaseSpecialistController
{
    public function __construct(
        private AvailabilityRepository $availabilityRepository,
        private ValidatorInterface $validator,
        private SpecialistRepository $specialistRepository
    ) {
        parent::__construct($specialistRepository);
    }

    #[Route('', name: 'specialist_availability_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $specialist = $this->getSpecialist();
        
        $date = $request->query->get('date');
        $dayOfWeek = $request->query->get('day_of_week');
        
        $criteria = ['specialist' => $specialist];
        if ($date) {
            $criteria['date'] = new \DateTimeImmutable($date);
        }
        if ($dayOfWeek) {
            $criteria['dayOfWeek'] = $dayOfWeek;
        }
        
        $availabilities = $this->availabilityRepository->findBy($criteria);
        $availabilitiesData = array_map(fn($availability) => $availability->toArray(), $availabilities);
        
        return $this->successResponse($availabilitiesData, 'Availabilities retrieved successfully');
    }

    #[Route('', name: 'specialist_availability_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $specialist = $this->getSpecialist();
            
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            $requiredFields = ['start_time', 'end_time', 'date'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return $this->errorResponse("Field '{$field}' is required", Response::HTTP_BAD_REQUEST);
                }
            }

            $availability = Availability::createForCoach($data, $specialist);

            $errors = $this->validator->validate($availability);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->errorResponse('Validation failed', Response::HTTP_BAD_REQUEST, $errorMessages);
            }

            $this->availabilityRepository->save($availability, true);

            return $this->successResponse($availability->toArray(), 'Availability created successfully', Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->errorResponse('Availability creation failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'specialist_availability_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $specialist = $this->getSpecialist();
        
        $availability = $this->availabilityRepository->find($id);
        
        if (!$availability) {
            return $this->errorResponse('Availability not found', Response::HTTP_NOT_FOUND);
        }

        // Vérifier que la disponibilité appartient au spécialiste
        if ($availability->getSpecialist() !== $specialist) {
            return $this->errorResponse('Access denied to this availability', Response::HTTP_FORBIDDEN);
        }

        return $this->successResponse($availability->toArray(), 'Availability retrieved successfully');
    }

    #[Route('/{id}', name: 'specialist_availability_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        try {
            $specialist = $this->getSpecialist();
            
            $availability = $this->availabilityRepository->find($id);
            
            if (!$availability) {
                return $this->errorResponse('Availability not found', Response::HTTP_NOT_FOUND);
            }

            // Vérifier que la disponibilité appartient au spécialiste
            if ($availability->getSpecialist() !== $specialist) {
                return $this->errorResponse('Access denied to this availability', Response::HTTP_FORBIDDEN);
            }

            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            if (isset($data['start_time'])) {
                $availability->setStartTime(new \DateTimeImmutable($data['start_time']));
            }
            if (isset($data['end_time'])) {
                $availability->setEndTime(new \DateTimeImmutable($data['end_time']));
            }
            if (isset($data['date'])) {
                $availability->setDate(new \DateTimeImmutable($data['date']));
            }
            if (isset($data['day_of_week'])) {
                $availability->setDayOfWeek($data['day_of_week']);
            }
            if (isset($data['is_available'])) {
                $availability->setIsAvailable($data['is_available']);
            }
            if (isset($data['notes'])) {
                $availability->setNotes($data['notes']);
            }

            $errors = $this->validator->validate($availability);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->errorResponse('Validation failed', Response::HTTP_BAD_REQUEST, $errorMessages);
            }

            $this->availabilityRepository->save($availability, true);

            return $this->successResponse($availability->toArray(), 'Availability updated successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Availability update failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'specialist_availability_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $specialist = $this->getSpecialist();
        
        $availability = $this->availabilityRepository->find($id);
        
        if (!$availability) {
            return $this->errorResponse('Availability not found', Response::HTTP_NOT_FOUND);
        }

        // Vérifier que la disponibilité appartient au spécialiste
        if ($availability->getSpecialist() !== $specialist) {
            return $this->errorResponse('Access denied to this availability', Response::HTTP_FORBIDDEN);
        }

        $this->availabilityRepository->remove($availability, true);

        return $this->successResponse(null, 'Availability deleted successfully');
    }

    #[Route('/planning', name: 'specialist_availability_planning', methods: ['GET'])]
    public function getPlanning(): JsonResponse
    {
        $specialist = $this->getSpecialist();
        
        $availabilities = $this->availabilityRepository->findBy(['specialist' => $specialist]);
        $availabilitiesData = array_map(fn($availability) => $availability->toArray(), $availabilities);
        
        return $this->successResponse($availabilitiesData, 'Availability planning retrieved successfully');
    }
}
