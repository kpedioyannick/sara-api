<?php

namespace App\Controller\Parent;

use App\Entity\Request;
use App\Entity\Message;
use App\Repository\RequestRepository;
use App\Repository\MessageRepository;
use App\Repository\StudentRepository;
use App\Repository\ParentUserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Mercure\Update;
use App\Service\MercurePublisher;
use Psr\Log\LoggerInterface;

#[Route('/api/parent/requests')]
class RequestController extends BaseParentController
{
    public function __construct(
        private RequestRepository $requestRepository,
        private MessageRepository $messageRepository,
        private StudentRepository $studentRepository,
        private ValidatorInterface $validator,
        private ParentUserRepository $parentUserRepository,
        private MercurePublisher $mercurePublisher,
        private LoggerInterface $logger
    ) {
        parent::__construct($parentUserRepository);
    }

    #[Route('', name: 'parent_requests_list', methods: ['GET'])]
    public function list(HttpRequest $request): JsonResponse
    {
        $parent = $this->getParent();
        $family = $parent->getFamily();
        
        if (!$family) {
            return $this->errorResponse('No family found for this parent', 404);
        }

        $status = $request->query->get('status');
        $date = $request->query->get('date');
        
        $criteria = ['parent' => $parent];
        if ($status) {
            $criteria['status'] = $status;
        }
        
        $requests = $this->requestRepository->findBy($criteria);
        
        // Filtrer par date si fournie
        if ($date) {
            $requests = array_filter($requests, function($request) use ($date) {
                return $request->getCreatedAt()->format('Y-m-d') === $date;
            });
        }
        
        $data = array_map(fn($request) => $request->toArray(), $requests);
        
        return $this->successResponse($data, 'Requests retrieved successfully');
    }

    #[Route('', name: 'parent_requests_create', methods: ['POST'])]
    public function create(HttpRequest $request): JsonResponse
    {
        try {
            $parent = $this->getParent();
            $family = $parent->getFamily();
            
            if (!$family) {
                return $this->errorResponse('No family found for this parent', 404);
            }

            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            $requiredFields = ['title', 'description'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return $this->errorResponse("Field '{$field}' is required", Response::HTTP_BAD_REQUEST);
                }
            }

            // Récupérer le coach de la famille
            $coach = $family->getCoach();
            if (!$coach) {
                return $this->errorResponse('No coach assigned to this family', Response::HTTP_BAD_REQUEST);
            }

            $requestEntity = Request::createForCoach($data, $coach, $parent, $coach);
            $requestEntity->setFamily($family);
            
            if (isset($data['student_id'])) {
                $student = $this->studentRepository->find($data['student_id']);
                if ($student && $student->getFamily() === $family) {
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

    #[Route('/{id}', name: 'parent_requests_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $parent = $this->getParent();
        
        $request = $this->requestRepository->find($id);
        
        if (!$request) {
            return $this->errorResponse('Request not found', Response::HTTP_NOT_FOUND);
        }

        // Vérifier que la demande appartient au parent
        if ($request->getParent() !== $parent) {
            return $this->errorResponse('Access denied to this request', Response::HTTP_FORBIDDEN);
        }

        return $this->successResponse($request->toArray(), 'Request retrieved successfully');
    }

    #[Route('/{id}/message', name: 'parent_requests_add_message', methods: ['POST'])]
    public function addMessage(int $id, HttpRequest $request): JsonResponse
    {
        try {
            $parent = $this->getParent();
            
            $requestEntity = $this->requestRepository->find($id);
            
            if (!$requestEntity) {
                return $this->errorResponse('Request not found', Response::HTTP_NOT_FOUND);
            }

            // Vérifier que la demande appartient au parent
            if ($requestEntity->getParent() !== $parent) {
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

            $message = Message::createForRequest($data, $parent, $recipient, $requestEntity);
            
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
