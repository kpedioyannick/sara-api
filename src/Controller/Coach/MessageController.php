<?php

namespace App\Controller\Coach;

use App\Entity\Message;
use App\Entity\Request as RequestEntity;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use App\Repository\RequestRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Psr\Log\LoggerInterface;
use App\Service\MercurePublisher;

#[Route('/api/coach/messages')]
class MessageController extends BaseCoachController
{
    public function __construct(
        private MessageRepository $messageRepository,
        private UserRepository $userRepository,
        private RequestRepository $requestRepository,
        private ValidatorInterface $validator,
        private HubInterface $hub,
        private LoggerInterface $logger,
        private MercurePublisher $mercurePublisher
    ) {}

    #[Route('', name: 'coach_messages_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        try {
            $coach = $this->getCoach();
            $conversationId = $request->query->get('conversation_id');
            $limit = (int) $request->query->get('limit', 50);
            $offset = (int) $request->query->get('offset', 0);

            $criteria = ['coach' => $coach];
            if ($conversationId) {
                $criteria['conversationId'] = $conversationId;
            }

            $messages = $this->messageRepository->findBy(
                $criteria,
                ['createdAt' => 'DESC'],
                $limit,
                $offset
            );

            $data = array_map(fn($message) => $message->toArray(), $messages);

            return $this->successResponse($data, 'Messages retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Message retrieval failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('', name: 'coach_messages_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            $requiredFields = ['recipient_id', 'content', 'conversation_id'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return $this->errorResponse("Field '{$field}' is required", Response::HTTP_BAD_REQUEST);
                }
            }

            $recipient = $this->userRepository->find($data['recipient_id']);
            if (!$recipient) {
                return $this->errorResponse('Recipient not found', Response::HTTP_NOT_FOUND);
            }

            $message = Message::createForCoach($data, $this->getCoach(), $recipient);

            $errors = $this->validator->validate($message);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->errorResponse('Validation failed', Response::HTTP_BAD_REQUEST, $errorMessages);
            }

            $this->messageRepository->save($message, true);

            // TODO: Publier le message en temps réel via Mercure
            $this->publishMessageUpdate($message);

            return $this->successResponse($message->toArray(), 'Message sent successfully', Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->errorResponse('Message creation failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'coach_messages_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $message = $this->messageRepository->find($id);
        
        if (!$message) {
            return $this->errorResponse('Message not found', Response::HTTP_NOT_FOUND);
        }

        // Vérifier que le message appartient au coach
        $coach = $this->getCoach();
        if ($message->getCoach() !== $coach) {
            return $this->errorResponse('Access denied to this message', Response::HTTP_FORBIDDEN);
        }

        return $this->successResponse($message->toArray(), 'Message retrieved successfully');
    }

    #[Route('/{id}', name: 'coach_messages_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        try {
            $message = $this->messageRepository->find($id);
            
            if (!$message) {
                return $this->errorResponse('Message not found', Response::HTTP_NOT_FOUND);
            }

            // Vérifier que le message appartient au coach
            $coach = $this->getCoach();
            if ($message->getCoach() !== $coach) {
                return $this->errorResponse('Access denied to this message', Response::HTTP_FORBIDDEN);
            }

            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            if (isset($data['content'])) {
                $message->setContent($data['content']);
            }
            if (isset($data['isRead'])) {
                $message->setIsRead($data['isRead']);
            }

            $this->messageRepository->save($message, true);

            // TODO: Publier la mise à jour en temps réel
            $this->publishMessageUpdate($message);

            return $this->successResponse($message->toArray(), 'Message updated successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Message update failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'coach_messages_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $message = $this->messageRepository->find($id);
        
        if (!$message) {
            return $this->errorResponse('Message not found', Response::HTTP_NOT_FOUND);
        }

        // Vérifier que le message appartient au coach
        $coach = $this->getCoach();
        if ($message->getCoach() !== $coach) {
            return $this->errorResponse('Access denied to this message', Response::HTTP_FORBIDDEN);
        }

        $this->messageRepository->remove($message, true);

        return $this->successResponse(null, 'Message deleted successfully');
    }

    #[Route('/conversations', name: 'coach_messages_conversations', methods: ['GET'])]
    public function getConversations(): JsonResponse
    {
        try {
            $coach = $this->getCoach();
            $conversations = $this->messageRepository->findConversationsByCoach($coach);

            return $this->successResponse($conversations, 'Conversations retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Conversation retrieval failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/mark-read', name: 'coach_messages_mark_read', methods: ['POST'])]
    public function markAsRead(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $conversationId = $data['conversation_id'] ?? null;
            $messageIds = $data['message_ids'] ?? [];

            $coach = $this->getCoach();

            if ($conversationId) {
                // Marquer tous les messages de la conversation comme lus
                $messages = $this->messageRepository->findBy([
                    'conversationId' => $conversationId,
                    'coach' => $coach,
                    'isRead' => false
                ]);
            } elseif (!empty($messageIds)) {
                // Marquer des messages spécifiques comme lus
                $messages = $this->messageRepository->findBy([
                    'id' => $messageIds,
                    'coach' => $coach
                ]);
            } else {
                return $this->errorResponse('conversation_id or message_ids is required', Response::HTTP_BAD_REQUEST);
            }

            foreach ($messages as $message) {
                $message->setIsRead(true);
                $this->messageRepository->save($message, false);
            }

            $this->messageRepository->getEntityManager()->flush();

            return $this->successResponse(['marked_count' => count($messages)], 'Messages marked as read');

        } catch (\Exception $e) {
            return $this->errorResponse('Mark as read failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function publishMessageUpdate(Message $message): void
    {
        try {
            $this->logger->info('Mercure publish attempt for message ID: ' . $message->getId());
            
            $update = new Update(
                'messages',
                json_encode($message->toArray()),
                true
            );
            
            $this->mercurePublisher->__invoke($update);
            $this->logger->info('Mercure publish successful for message ID: ' . $message->getId());
        } catch (\Exception $e) {
            // Log l'erreur mais ne pas faire échouer la création du message
            $this->logger->error('Mercure publish failed: ' . $e->getMessage());
            $this->logger->error('Mercure error details: ' . $e->getTraceAsString());
            if ($e->getPrevious()) {
                $this->logger->error('Mercure previous error: ' . $e->getPrevious()->getMessage());
            }
        }
    }

    #[Route('/request/{requestId}', name: 'coach_messages_by_request', methods: ['GET'])]
    public function listByRequest(int $requestId): JsonResponse
    {
        try {
            $coach = $this->getCoach();
            
            // Vérifier que la demande appartient au coach
            $requestEntity = $this->requestRepository->find($requestId);
            if (!$requestEntity || $requestEntity->getCoach() !== $coach) {
                return $this->errorResponse('Request not found or access denied', Response::HTTP_NOT_FOUND);
            }

            $messages = $this->messageRepository->findBy(
                ['request' => $requestEntity],
                ['createdAt' => 'ASC']
            );

            $data = array_map(fn($message) => $message->toArray(), $messages);

            return $this->successResponse($data, 'Request messages retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Message retrieval failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/request/{requestId}', name: 'coach_messages_create_for_request', methods: ['POST'])]
    public function createForRequest(int $requestId, Request $request): JsonResponse
    {
        try {
            $coach = $this->getCoach();
            
            // Vérifier que la demande appartient au coach
            $requestEntity = $this->requestRepository->find($requestId);
            if (!$requestEntity || $requestEntity->getCoach() !== $coach) {
                return $this->errorResponse('Request not found or access denied', Response::HTTP_NOT_FOUND);
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

            // Créer le message avec la demande
            $message = Message::createForRequest($data, $coach, $recipient, $requestEntity);
            $message->setCoach($coach);
            $message->setRecipient($recipient);

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

            return $this->successResponse($message->toArray(), 'Request message sent successfully', Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->errorResponse('Request message creation failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Publie une notification de message de demande via Mercure
     */
    private function publishRequestMessageUpdate(Message $message, RequestEntity $request): void
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
