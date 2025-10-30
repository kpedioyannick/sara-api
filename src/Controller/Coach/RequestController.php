<?php

namespace App\Controller\Coach;

use App\Entity\Request as RequestEntity;
use App\Entity\Message;
use App\Repository\RequestRepository;
use App\Repository\UserRepository;
use App\Repository\FamilyRepository;
use App\Repository\MessageRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Mercure\Update;
use App\Service\MercurePublisher;
use Psr\Log\LoggerInterface;

#[Route('/api/coach/requests')]
class RequestController extends BaseCoachController
{
    public function __construct(
        private RequestRepository $requestRepository,
        private UserRepository $userRepository,
        private FamilyRepository $familyRepository,
        private MessageRepository $messageRepository,
        private ValidatorInterface $validator,
        private MercurePublisher $mercurePublisher,
        private LoggerInterface $logger
    ) {}

    #[Route('', name: 'coach_requests_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $coach = $this->getCoach();
        $familyId = $request->query->get('family_id');
        $status = $request->query->get('status');
        $specialistId = $request->query->get('specialist_id');
        $studentId = $request->query->get('student_id');

        $criteria = [];
        if ($familyId) {
            $family = $this->familyRepository->find($familyId);
            if ($family && $family->getCoach() === $coach) {
                $criteria['family'] = $family;
            }
        }
        if ($status) {
            $criteria['status'] = $status;
        }
        if ($specialistId) {
            $criteria['specialist'] = $this->userRepository->find($specialistId);
        }
        if ($studentId) {
            $criteria['student'] = $this->userRepository->find($studentId);
        }

        $requests = $this->requestRepository->findBy($criteria);
        
        $data = [];
        foreach ($requests as $request) {
            $data[] = $request->toArray();
        }

        return $this->successResponse($data, 'Coach requests retrieved successfully');
    }

    #[Route('', name: 'coach_requests_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            $requiredFields = ['title', 'description', 'creator_id', 'recipient_id'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return $this->errorResponse("Field '{$field}' is required", Response::HTTP_BAD_REQUEST);
                }
            }

            $creator = $this->userRepository->find($data['creator_id']);
            $recipient = $this->userRepository->find($data['recipient_id']);
            
            if (!$creator || !$recipient) {
                return $this->errorResponse('Creator or recipient not found', Response::HTTP_NOT_FOUND);
            }

            $requestEntity = RequestEntity::createForCoach($data, $this->getCoach(), $creator, $recipient);
            
            if (isset($data['family_id'])) {
                $family = $this->familyRepository->find($data['family_id']);
                if ($family && $family->getCoach() === $this->getCoach()) {
                    $requestEntity->setFamily($family);
                }
            }

            if (isset($data['student_id'])) {
                $student = $this->userRepository->find($data['student_id']);
                if ($student) {
                    $requestEntity->setStudent($student);
                }
            }

            $errors = $this->validator->validate($requestEntity);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->errorResponse('Validation failed', Response::HTTP_BAD_REQUEST, $errorMessages);
            }

            $this->requestRepository->save($requestEntity, true);

            // Publier la notification de création de demande
            $this->publishRequestUpdate($requestEntity, 'created');

            return $this->successResponse($requestEntity->toArray(), 'Request created successfully', Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->errorResponse('Request creation failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'coach_requests_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $requestEntity = $this->requestRepository->find($id);
        
        if (!$requestEntity) {
            return $this->errorResponse('Request not found', Response::HTTP_NOT_FOUND);
        }

        // Vérifier que la demande appartient au coach
        $coach = $this->getCoach();
        if ($requestEntity->getCoach() !== $coach) {
            return $this->errorResponse('Access denied to this request', Response::HTTP_FORBIDDEN);
        }

        return $this->successResponse($requestEntity->toArray(), 'Request retrieved successfully');
    }

    #[Route('/{id}', name: 'coach_requests_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        try {
            $requestEntity = $this->requestRepository->find($id);
            
            if (!$requestEntity) {
                return $this->errorResponse('Request not found', Response::HTTP_NOT_FOUND);
            }

            // Vérifier que la demande appartient au coach
            $coach = $this->getCoach();
            if ($requestEntity->getCoach() !== $coach) {
                return $this->errorResponse('Access denied to this request', Response::HTTP_FORBIDDEN);
            }

            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            if (isset($data['title'])) {
                $requestEntity->setTitle($data['title']);
            }
            if (isset($data['description'])) {
                $requestEntity->setDescription($data['description']);
            }
            if (isset($data['status'])) {
                $requestEntity->setStatus($data['status']);
            }
            if (isset($data['priority'])) {
                $requestEntity->setPriority($data['priority']);
            }
            if (isset($data['assigned_to'])) {
                $assignedUser = $this->userRepository->find($data['assigned_to']);
                if ($assignedUser) {
                    $requestEntity->setAssignedTo($assignedUser);
                }
            }

            $errors = $this->validator->validate($requestEntity);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->errorResponse('Validation failed', Response::HTTP_BAD_REQUEST, $errorMessages);
            }

            $this->requestRepository->save($requestEntity, true);

            // Publier la notification de mise à jour de demande
            $this->publishRequestUpdate($requestEntity, 'updated');

            return $this->successResponse($requestEntity->toArray(), 'Request updated successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Request update failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'coach_requests_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $requestEntity = $this->requestRepository->find($id);
        
        if (!$requestEntity) {
            return $this->errorResponse('Request not found', Response::HTTP_NOT_FOUND);
        }

        // Vérifier que la demande appartient au coach
        $coach = $this->getCoach();
        if ($requestEntity->getCoach() !== $coach) {
            return $this->errorResponse('Access denied to this request', Response::HTTP_FORBIDDEN);
        }

        $this->requestRepository->remove($requestEntity, true);

        return $this->successResponse(null, 'Request deleted successfully');
    }

    #[Route('/{id}/assign', name: 'coach_requests_assign', methods: ['POST'])]
    public function assign(int $id, Request $request): JsonResponse
    {
        try {
            $requestEntity = $this->requestRepository->find($id);
            
            if (!$requestEntity) {
                return $this->errorResponse('Request not found', Response::HTTP_NOT_FOUND);
            }

            // Vérifier que la demande appartient au coach
            $coach = $this->getCoach();
            if ($requestEntity->getCoach() !== $coach) {
                return $this->errorResponse('Access denied to this request', Response::HTTP_FORBIDDEN);
            }

            $data = json_decode($request->getContent(), true);
            
            if (!$data || !isset($data['assigned_to'])) {
                return $this->errorResponse('assigned_to field is required', Response::HTTP_BAD_REQUEST);
            }

            $assignedUser = $this->userRepository->find($data['assigned_to']);
            if (!$assignedUser) {
                return $this->errorResponse('Assigned user not found', Response::HTTP_NOT_FOUND);
            }

            $requestEntity->setAssignedTo($assignedUser);
            $requestEntity->setStatus('assigned');

            $this->requestRepository->save($requestEntity, true);

            // Publier la notification d'assignation de demande
            $this->publishRequestUpdate($requestEntity, 'assigned');

            return $this->successResponse($requestEntity->toArray(), 'Request assigned successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Request assignment failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/respond', name: 'coach_requests_respond', methods: ['POST'])]
    public function respond(int $id, Request $request): JsonResponse
    {
        try {
            $requestEntity = $this->requestRepository->find($id);
            
            if (!$requestEntity) {
                return $this->errorResponse('Request not found', Response::HTTP_NOT_FOUND);
            }

            // Vérifier que la demande appartient au coach
            $coach = $this->getCoach();
            if ($requestEntity->getCoach() !== $coach) {
                return $this->errorResponse('Access denied to this request', Response::HTTP_FORBIDDEN);
            }

            $data = json_decode($request->getContent(), true);
            
            if (!$data || !isset($data['response'])) {
                return $this->errorResponse('response field is required', Response::HTTP_BAD_REQUEST);
            }

            $requestEntity->setResponse($data['response']);
            $requestEntity->setStatus('responded');

            $this->requestRepository->save($requestEntity, true);

            // Publier la notification de réponse à la demande
            $this->publishRequestUpdate($requestEntity, 'responded');

            return $this->successResponse($requestEntity->toArray(), 'Request response added successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Request response failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Publie une notification de mise à jour de demande via Mercure
     */
    private function publishRequestUpdate(RequestEntity $request, string $action): void
    {
        try {
            $this->logger->info('Mercure publish attempt for request ID: ' . $request->getId() . ' action: ' . $action);
            
            $notificationData = [
                'type' => 'request_update',
                'action' => $action,
                'request' => $request->toArray(),
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
            ];

            $update = new Update(
                'requests',
                json_encode($notificationData),
                true
            );
            
            $this->mercurePublisher->__invoke($update);
            $this->logger->info('Mercure publish successful for request ID: ' . $request->getId());
        } catch (\Exception $e) {
            // Log l'erreur mais ne pas faire échouer l'opération
            $this->logger->error('Mercure publish failed for request: ' . $e->getMessage());
            $this->logger->error('Mercure error details: ' . $e->getTraceAsString());
            if ($e->getPrevious()) {
                $this->logger->error('Mercure previous error: ' . $e->getPrevious()->getMessage());
            }
        }
    }
}
