<?php

namespace App\Controller\Specialist;

use App\Entity\Request;
use App\Entity\Message;
use App\Repository\RequestRepository;
use App\Repository\MessageRepository;
use App\Repository\SpecialistRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Mercure\Update;
use App\Service\MercurePublisher;
use Psr\Log\LoggerInterface;

#[Route('/api/specialist/requests')]
class RequestController extends BaseSpecialistController
{
    public function __construct(
        private RequestRepository $requestRepository,
        private MessageRepository $messageRepository,
        private ValidatorInterface $validator,
        private SpecialistRepository $specialistRepository,
        private MercurePublisher $mercurePublisher,
        private LoggerInterface $logger
    ) {
        parent::__construct($specialistRepository);
    }

    #[Route('', name: 'specialist_requests_list', methods: ['GET'])]
    public function list(HttpRequest $request): JsonResponse
    {
        $specialist = $this->getSpecialist();
        
        $familyId = $request->query->get('family_id');
        $studentId = $request->query->get('student_id');
        $status = $request->query->get('status');
        $date = $request->query->get('date');
        
        $criteria = ['specialist' => $specialist];
        if ($status) {
            $criteria['status'] = $status;
        }
        
        $requests = $this->requestRepository->findBy($criteria);
        
        // Filtrer par famille si fournie
        if ($familyId) {
            $requests = array_filter($requests, function($request) use ($familyId) {
                return $request->getFamily() && $request->getFamily()->getId() == $familyId;
            });
        }
        
        // Filtrer par étudiant si fourni
        if ($studentId) {
            $requests = array_filter($requests, function($request) use ($studentId) {
                return $request->getStudent() && $request->getStudent()->getId() == $studentId;
            });
        }
        
        // Filtrer par date si fournie
        if ($date) {
            $requests = array_filter($requests, function($request) use ($date) {
                return $request->getCreatedAt()->format('Y-m-d') === $date;
            });
        }
        
        $data = array_map(fn($request) => $request->toArray(), $requests);
        
        return $this->successResponse($data, 'Requests retrieved successfully');
    }

    #[Route('/{id}', name: 'specialist_requests_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $specialist = $this->getSpecialist();
        
        $request = $this->requestRepository->find($id);
        
        if (!$request) {
            return $this->errorResponse('Request not found', Response::HTTP_NOT_FOUND);
        }

        // Vérifier que la demande est assignée au spécialiste
        if ($request->getSpecialist() !== $specialist) {
            return $this->errorResponse('Access denied to this request', Response::HTTP_FORBIDDEN);
        }

        return $this->successResponse($request->toArray(), 'Request retrieved successfully');
    }

    #[Route('/{id}/message', name: 'specialist_requests_add_message', methods: ['POST'])]
    public function addMessage(int $id, HttpRequest $request): JsonResponse
    {
        try {
            $specialist = $this->getSpecialist();
            
            $requestEntity = $this->requestRepository->find($id);
            
            if (!$requestEntity) {
                return $this->errorResponse('Request not found', Response::HTTP_NOT_FOUND);
            }

            // Vérifier que la demande est assignée au spécialiste
            if ($requestEntity->getSpecialist() !== $specialist) {
                return $this->errorResponse('Access denied to this request', Response::HTTP_FORBIDDEN);
            }

            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            $requiredFields = ['content', 'recipient_id'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return $this->errorResponse("Field '{$field}' is required", Response::HTTP_BAD_REQUEST);
                }
            }

            $recipient = $this->userRepository->find($data['recipient_id']);
            if (!$recipient) {
                return $this->errorResponse('Recipient not found', Response::HTTP_NOT_FOUND);
            }

            $message = Message::createForRequest($data, $specialist, $recipient, $requestEntity);
            
            $errors = $this->validator->validate($message);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->errorResponse('Validation failed', Response::HTTP_BAD_REQUEST, $errorMessages);
            }

            $this->messageRepository->save($message, true);

            // Publier la notification de message de demande
            $this->publishRequestMessageUpdate($message, $requestEntity);

            return $this->successResponse($message->toArray(), 'Message added successfully', Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->errorResponse('Message creation failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/status', name: 'specialist_requests_update_status', methods: ['PUT'])]
    public function updateStatus(int $id, HttpRequest $request): JsonResponse
    {
        try {
            $specialist = $this->getSpecialist();
            
            $requestEntity = $this->requestRepository->find($id);
            
            if (!$requestEntity) {
                return $this->errorResponse('Request not found', Response::HTTP_NOT_FOUND);
            }

            // Vérifier que la demande est assignée au spécialiste
            if ($requestEntity->getSpecialist() !== $specialist) {
                return $this->errorResponse('Access denied to this request', Response::HTTP_FORBIDDEN);
            }

            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            if (!isset($data['status'])) {
                return $this->errorResponse('Status is required', Response::HTTP_BAD_REQUEST);
            }

            $requestEntity->setStatus($data['status']);

            $errors = $this->validator->validate($requestEntity);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->errorResponse('Validation failed', Response::HTTP_BAD_REQUEST, $errorMessages);
            }

            $this->requestRepository->save($requestEntity, true);

            // Publier la notification de changement de statut
            $this->publishRequestUpdate($requestEntity, 'status_updated');

            return $this->successResponse($requestEntity->toArray(), 'Request status updated successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Request status update failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Publie une notification de mise à jour de demande via Mercure
     */
    private function publishRequestUpdate(Request $request, string $action): void
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

    /**
     * Publie une notification de message de demande via Mercure
     */
    private function publishRequestMessageUpdate(Message $message, Request $request): void
    {
        try {
            $this->logger->info('Mercure publish attempt for request message ID: ' . $message->getId());
            
            $notificationData = [
                'type' => 'request_message',
                'action' => 'message_added',
                'message' => $message->toArray(),
                'request' => $request->toArray(),
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
            ];

            $update = new Update(
                'requests',
                json_encode($notificationData),
                true
            );
            
            $this->mercurePublisher->__invoke($update);
            $this->logger->info('Mercure publish successful for request message ID: ' . $message->getId());
        } catch (\Exception $e) {
            // Log l'erreur mais ne pas faire échouer l'opération
            $this->logger->error('Mercure publish failed for request message: ' . $e->getMessage());
            $this->logger->error('Mercure error details: ' . $e->getTraceAsString());
            if ($e->getPrevious()) {
                $this->logger->error('Mercure previous error: ' . $e->getPrevious()->getMessage());
            }
        }
    }
}
