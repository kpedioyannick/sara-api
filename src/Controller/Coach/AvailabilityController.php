<?php

namespace App\Controller\Coach;

use App\Entity\Availability;
use App\Repository\AvailabilityRepository;
use App\Repository\SpecialistRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/coach/availability')]
class AvailabilityController extends BaseCoachController
{
    public function __construct(
        private AvailabilityRepository $availabilityRepository,
        private SpecialistRepository $specialistRepository,
        private ValidatorInterface $validator
    ) {}

    #[Route('', name: 'coach_availability_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        try {
            $coach = $this->getCoach();
            $dayOfWeek = $request->query->get('day_of_week');
            $specialistId = $request->query->get('specialist_id');
            
            if ($specialistId) {
                // Récupérer les disponibilités du spécialiste
                $specialist = $this->specialistRepository->find($specialistId);
                
                if (!$specialist) {
                    return $this->errorResponse('Specialist not found', Response::HTTP_NOT_FOUND);
                }

                $criteria = ['specialist' => $specialist];
                if ($dayOfWeek) {
                    $criteria['dayOfWeek'] = $dayOfWeek;
                }

                $availabilities = $this->availabilityRepository->findBy($criteria);
                $data = array_map(fn($availability) => $availability->toArray(), $availabilities);
                
                return $this->successResponse($data, 'Specialist availability retrieved successfully');
            } else {
                // Récupérer les disponibilités du coach
                $criteria = ['coach' => $coach];
                if ($dayOfWeek) {
                    $criteria['dayOfWeek'] = $dayOfWeek;
                }

                $availabilities = $this->availabilityRepository->findBy($criteria);
                $data = array_map(fn($availability) => $availability->toArray(), $availabilities);
                
                return $this->successResponse($data, 'Coach availability retrieved successfully');
            }

        } catch (\Exception $e) {
            return $this->errorResponse('Availability retrieval failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('', name: 'coach_availability_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            $coach = $this->getCoach();

            // Si specialist_id est fourni, créer pour le spécialiste
            if (isset($data['specialist_id']) && !empty($data['specialist_id'])) {
                $specialist = $this->specialistRepository->find($data['specialist_id']);
                
                if (!$specialist) {
                    return $this->errorResponse('Specialist not found', Response::HTTP_NOT_FOUND);
                }

                // Vérifier si c'est un tableau de slots (création en lot)
                if (isset($data['slots']) && is_array($data['slots'])) {
                    return $this->createMultipleSpecialistAvailabilities($specialist, $data['slots']);
                }

                // Création d'un seul slot pour spécialiste
                $requiredFields = ['start_time', 'end_time', 'day_of_week'];
                foreach ($requiredFields as $field) {
                    if (!isset($data[$field]) || empty($data[$field])) {
                        return $this->errorResponse("Field '{$field}' is required", Response::HTTP_BAD_REQUEST);
                    }
                }
                
                $availability = Availability::createForSpecialist($data, $specialist);
            } else {
                // Création pour le coach
                // Vérifier si c'est un tableau de slots (création en lot)
                if (isset($data['slots']) && is_array($data['slots'])) {
                    return $this->createMultipleCoachAvailabilities($coach, $data['slots']);
                }

                // Création d'un seul slot pour coach
                $requiredFields = ['start_time', 'end_time', 'day_of_week'];
                foreach ($requiredFields as $field) {
                    if (!isset($data[$field]) || empty($data[$field])) {
                        return $this->errorResponse("Field '{$field}' is required", Response::HTTP_BAD_REQUEST);
                    }
                }
                
                $availability = Availability::createForCoach($data, $coach);
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

            $message = 'Availability created successfully';
            return $this->successResponse($availability->toArray(), $message, Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->errorResponse('Availability creation failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function createMultipleCoachAvailabilities(\App\Entity\Coach $coach, array $slots): JsonResponse
    {
        try {
            // Supprimer toutes les disponibilités existantes pour ce coach
            $this->deleteExistingCoachAvailabilities($coach);
            
            $createdAvailabilities = [];
            $errors = [];

            foreach ($slots as $index => $slot) {
                $requiredFields = ['start_time', 'end_time', 'day_of_week'];
                $slotErrors = [];
                
                foreach ($requiredFields as $field) {
                    if (!isset($slot[$field]) || empty($slot[$field])) {
                        $slotErrors[] = "Slot {$index}: Field '{$field}' is required";
                    }
                }

                if (!empty($slotErrors)) {
                    $errors = array_merge($errors, $slotErrors);
                    continue;
                }

                try {
                    $availability = Availability::createForCoach($slot, $coach);
                    $validationErrors = $this->validator->validate($availability);
                    
                    if (count($validationErrors) > 0) {
                        foreach ($validationErrors as $error) {
                            $errors[] = "Slot {$index}: " . $error->getMessage();
                        }
                        continue;
                    }

                    $this->availabilityRepository->save($availability, true);
                    $createdAvailabilities[] = $availability->toArray();
                    
                } catch (\Exception $e) {
                    $errors[] = "Slot {$index}: " . $e->getMessage();
                }
            }

            if (!empty($errors)) {
                return $this->errorResponse('Some slots failed validation', Response::HTTP_BAD_REQUEST, $errors);
            }

            return $this->successResponse($createdAvailabilities, 'Coach availabilities updated successfully', Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->errorResponse('Availability update failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function createMultipleSpecialistAvailabilities(\App\Entity\Specialist $specialist, array $slots): JsonResponse
    {
        try {
            // Supprimer toutes les disponibilités existantes pour ce spécialiste
            $this->deleteExistingSpecialistAvailabilities($specialist);
            
            $createdAvailabilities = [];
            $errors = [];

            foreach ($slots as $index => $slot) {
                $requiredFields = ['start_time', 'end_time', 'day_of_week'];
                $slotErrors = [];
                
                foreach ($requiredFields as $field) {
                    if (!isset($slot[$field]) || empty($slot[$field])) {
                        $slotErrors[] = "Slot {$index}: Field '{$field}' is required";
                    }
                }

                if (!empty($slotErrors)) {
                    $errors = array_merge($errors, $slotErrors);
                    continue;
                }

                try {
                    $availability = Availability::createForSpecialist($slot, $specialist);
                    $validationErrors = $this->validator->validate($availability);
                    
                    if (count($validationErrors) > 0) {
                        foreach ($validationErrors as $error) {
                            $errors[] = "Slot {$index}: " . $error->getMessage();
                        }
                        continue;
                    }

                    $this->availabilityRepository->save($availability, true);
                    $createdAvailabilities[] = $availability->toArray();
                    
                } catch (\Exception $e) {
                    $errors[] = "Slot {$index}: " . $e->getMessage();
                }
            }

            if (!empty($errors)) {
                return $this->errorResponse('Some slots failed validation', Response::HTTP_BAD_REQUEST, $errors);
            }

            return $this->successResponse($createdAvailabilities, 'Specialist availabilities updated successfully', Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->errorResponse('Availability update failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function deleteExistingCoachAvailabilities(\App\Entity\Coach $coach): void
    {
        $criteria = ['coach' => $coach];
        $existingAvailabilities = $this->availabilityRepository->findBy($criteria);
        
        foreach ($existingAvailabilities as $availability) {
            $this->availabilityRepository->remove($availability, false);
        }
        
        $this->availabilityRepository->getEntityManager()->flush();
    }

    private function deleteExistingSpecialistAvailabilities(\App\Entity\Specialist $specialist): void
    {
        $criteria = ['specialist' => $specialist];
        $existingAvailabilities = $this->availabilityRepository->findBy($criteria);
        
        foreach ($existingAvailabilities as $availability) {
            $this->availabilityRepository->remove($availability, false);
        }
        
        $this->availabilityRepository->getEntityManager()->flush();
    }


}
