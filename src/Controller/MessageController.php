<?php

namespace App\Controller;

use App\Controller\Trait\CoachTrait;
use App\Entity\Message;
use App\Entity\Request as RequestEntity;
use App\Repository\CoachRepository;
use App\Repository\MessageRepository;
use App\Repository\RequestRepository;
use App\Repository\UserRepository;
use App\Service\FileStorageService;
use App\Service\FirebaseService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class MessageController extends AbstractController
{
    use CoachTrait;

    public function __construct(
        private readonly MessageRepository $messageRepository,
        private readonly UserRepository $userRepository,
        private readonly RequestRepository $requestRepository,
        private readonly CoachRepository $coachRepository,
        private readonly Security $security,
        private readonly EntityManagerInterface $em,
        private readonly ValidatorInterface $validator,
        private readonly FirebaseService $firebaseService,
        private readonly FileStorageService $fileStorageService,
        private readonly NotificationService $notificationService
    ) {
    }

    #[Route('/admin/messages/notifications', name: 'admin_messages_notifications', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function notifications(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['notifications' => []], 401);
        }

        $messages = $this->messageRepository->findRecentNotifications($user, 10);

        $notifications = [];
        foreach ($messages as $message) {
            $sender = $message->getSender();
            $request = $message->getRequest();
            
            // Déterminer le type de profil de l'expéditeur
            $senderProfile = 'Utilisateur';
            if ($sender instanceof \App\Entity\Coach) {
                $senderProfile = 'Coach';
            } elseif ($sender instanceof \App\Entity\ParentUser) {
                $senderProfile = 'Parent';
            } elseif ($sender instanceof \App\Entity\Student) {
                $senderProfile = 'Élève';
            } elseif ($sender instanceof \App\Entity\Specialist) {
                $senderProfile = 'Spécialiste';
            }

            $notification = [
                'id' => $message->getId(),
                'sender' => [
                    'id' => $sender->getId(),
                    'firstName' => $sender->getFirstName(),
                    'lastName' => $sender->getLastName(),
                    'profile' => $senderProfile,
                ],
                'content' => $message->getContent(),
                'isRead' => $message->isRead(),
                'createdAt' => $message->getCreatedAt()?->format('Y-m-d H:i:s'),
                'requestId' => $request?->getId(),
                'requestTitle' => $request?->getTitle(),
            ];

            // Générer l'URL de redirection
            if ($request) {
                $notification['url'] = $this->generateUrl('admin_requests_detail', ['id' => $request->getId()]);
                $notification['type'] = 'request';
            } else {
                $notification['url'] = $this->generateUrl('admin_messages_list');
                $notification['type'] = 'message';
            }

            $notifications[] = $notification;
        }

        // Compter les messages non lus
        $unreadCount = $this->messageRepository->countUnreadMessages($user);

        return new JsonResponse([
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
        ]);
    }

    #[Route('/admin/messages', name: 'admin_messages_list')]
    #[IsGranted('ROLE_COACH')]
    public function list(): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $conversations = $this->messageRepository->findConversationsWithDetails($user);
        $conversationsData = [];

        foreach ($conversations as $conv) {
            $lastMessage = $conv['lastMessage'];
            $otherUser = $conv['otherUser'];

            $conversationsData[] = [
                'conversationId' => $conv['conversationId'],
                'otherUser' => [
                    'id' => $otherUser->getId(),
                    'firstName' => $otherUser->getFirstName(),
                    'lastName' => $otherUser->getLastName(),
                    'email' => $otherUser->getEmail(),
                ],
                'lastMessage' => [
                    'content' => $lastMessage->getContent(),
                    'createdAt' => $lastMessage->getCreatedAt()?->format('Y-m-d H:i:s'),
                    'isRead' => $lastMessage->isRead(),
                    'isFromMe' => $lastMessage->getSender() === $user,
                ],
                'unreadCount' => $conv['unreadCount'],
            ];
        }

        return $this->render('tailadmin/pages/messages/list.html.twig', [
            'pageTitle' => 'Messages ',
            'pageName' => 'Messages',
            'conversations' => $conversationsData,
            'unreadCount' => $this->messageRepository->countUnreadMessages($user),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
            ],
        ]);
    }

    #[Route('/admin/messages/create', name: 'admin_messages_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Non authentifié'], 401);
        }

        // Gérer les fichiers uploadés (FormData) ou JSON
        $messageType = 'text';
        $filePath = null;
        $content = null;

        if ($request->files->has('file')) {
            // Upload de fichier (image ou audio)
            $file = $request->files->get('file');
            $receiverId = $request->request->get('receiverId');
            $content = $request->request->get('content', '');

            if (!$receiverId) {
                return new JsonResponse(['success' => false, 'message' => 'Le destinataire est requis'], 400);
            }

            // Déterminer le type de fichier
            $mimeType = $file->getMimeType();
            if (str_starts_with($mimeType, 'image/')) {
                $messageType = 'image';
                $filePath = $this->fileStorageService->uploadFile($file, 'messages/images');
            } elseif (str_starts_with($mimeType, 'audio/')) {
                $messageType = 'audio';
                $filePath = $this->fileStorageService->uploadFile($file, 'messages/audio');
            } else {
                return new JsonResponse(['success' => false, 'message' => 'Type de fichier non supporté'], 400);
            }
        } else {
            // Message texte classique (JSON)
            $data = json_decode($request->getContent(), true);
            if (!$data) {
                $data = [];
            }

            $content = $data['content'] ?? null;
            $messageType = $data['type'] ?? 'text';
            $receiverId = $data['receiverId'] ?? null;

            // Si c'est un message avec fichier base64 (photo prise depuis l'appareil)
            if (isset($data['fileData']) && isset($data['fileType'])) {
                $fileType = $data['fileType']; // 'image' ou 'audio'
                $fileExtension = $data['fileExtension'] ?? ($fileType === 'image' ? 'jpg' : 'mp3');
                
                try {
                    $filePath = $this->fileStorageService->saveBase64File(
                        $data['fileData'],
                        $fileExtension,
                        'messages/' . ($fileType === 'image' ? 'images' : 'audio')
                    );
                    $messageType = $fileType;
                } catch (\Exception $e) {
                    return new JsonResponse(['success' => false, 'message' => 'Erreur lors de l\'enregistrement du fichier: ' . $e->getMessage()], 400);
                }
            }

            if (!$receiverId) {
                return new JsonResponse(['success' => false, 'message' => 'Le destinataire est requis'], 400);
            }
        }

        // Validation : au moins un contenu ou un fichier
        if (empty(trim($content ?? '')) && !$filePath) {
            return new JsonResponse(['success' => false, 'message' => 'Le contenu du message ou un fichier est requis'], 400);
        }

        $receiver = $this->userRepository->find($receiverId);
        if (!$receiver) {
            return new JsonResponse(['success' => false, 'message' => 'Destinataire non trouvé'], 404);
        }

        // Générer ou récupérer le conversationId
        $conversationId = $this->messageRepository->findConversationBetweenUsers($user, $receiver);
        if (!$conversationId) {
            $conversationId = $this->messageRepository->generateConversationId($user, $receiver);
        }

        // Créer le message
        $message = Message::create([
            'content' => $content ? trim($content) : null,
            'type' => $messageType,
            'filePath' => $filePath,
            'conversationId' => $conversationId,
            'isRead' => false,
        ], $user, $receiver);

        // Si c'est un coach, définir aussi coach et recipient
        if ($user instanceof \App\Entity\Coach) {
            $message->setCoach($user);
            $message->setRecipient($receiver);
        }

        // Si une demande est associée
        if (isset($data['requestId']) && $data['requestId']) {
            $requestEntity = $this->requestRepository->find($data['requestId']);
            if ($requestEntity) {
                $message->setRequest($requestEntity);
            }
        }

        // Validation
        $errors = $this->validator->validate($message);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['success' => false, 'message' => implode(', ', $errorMessages)], 400);
        }

        $this->em->persist($message);
        $this->em->flush();

        // Publier le message via Firebase pour le temps réel (avec gestion d'erreur)
        try {
            // S'assurer que tous les types sont compatibles avec Firebase
            $messageData = [
                'id' => (string)$message->getId(), // Convertir en string
                'conversationId' => (string)$conversationId, // Convertir en string
                'content' => $message->getContent() ?? '', // Toujours une string
                'type' => $message->getType() ?? 'text', // Toujours une string
                'filePath' => $message->getFilePath() ? $this->fileStorageService->generateSecureUrl($message->getFilePath()) : '', // String vide au lieu de null
                'sender' => [
                    'id' => (string)$user->getId(), // Convertir en string
                    'firstName' => $user->getFirstName() ?? '',
                    'lastName' => $user->getLastName() ?? '',
                ],
                'receiverId' => (string)$receiver->getId(), // Convertir en string
                'createdAt' => $message->getCreatedAt()?->format('Y-m-d H:i:s') ?? date('Y-m-d H:i:s'),
                'isRead' => false, // Boolean OK
            ];
            
            // Nettoyer les valeurs null
            $messageData = array_filter($messageData, function($value) {
                return $value !== null;
            });

            // Publier dans Firebase Realtime Database
            $this->firebaseService->publishMessage("/conversations/{$conversationId}/messages", $messageData);

            // Publier aussi une notification pour la liste des conversations
            $this->firebaseService->publishMessage("/conversations/user/{$user->getId()}/updates", [
                'type' => 'new_message',
                'conversationId' => $conversationId,
                'unreadCount' => $this->messageRepository->countUnreadMessages($receiver),
            ]);
            $this->firebaseService->publishMessage("/conversations/user/{$receiver->getId()}/updates", [
                'type' => 'new_message',
                'conversationId' => $conversationId,
                'unreadCount' => $this->messageRepository->countUnreadMessages($receiver),
            ]);
        } catch (\Exception $e) {
            // Log l'erreur mais ne bloque pas l'envoi du message
            error_log('Erreur Firebase: ' . $e->getMessage());
        }

        // Créer une notification pour le destinataire
        try {
            $this->notificationService->notifyNewMessage($message, $receiver);
        } catch (\Exception $e) {
            // Log l'erreur mais ne bloque pas l'envoi du message
            error_log('Erreur notification nouveau message: ' . $e->getMessage());
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Message envoyé avec succès',
            'data' => [
                'id' => $message->getId(),
                'conversationId' => $conversationId,
                'content' => $message->getContent(),
                'type' => $message->getType(),
                'filePath' => $message->getFilePath() ? $this->fileStorageService->generateSecureUrl($message->getFilePath()) : null,
                'createdAt' => $message->getCreatedAt()?->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    #[Route('/admin/messages/{conversationId}', name: 'admin_messages_show')]
    #[IsGranted('ROLE_USER')]
    public function show(string $conversationId): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Vérifier que l'utilisateur fait partie de cette conversation
        $messages = $this->messageRepository->findByConversation($conversationId, $user);
        if (empty($messages)) {
            throw $this->createNotFoundException('Conversation non trouvée');
        }

        // Marquer les messages comme lus
        $this->messageRepository->markConversationAsRead($conversationId, $user);

        // Déterminer l'autre utilisateur
        $firstMessage = $messages[0];
        $otherUser = $firstMessage->getSender() === $user ? $firstMessage->getReceiver() : $firstMessage->getSender();

        $messagesData = array_map(function ($message) use ($user) {
            return [
                'id' => $message->getId(),
                'content' => $message->getContent(),
                'type' => $message->getType(),
                'filePath' => $message->getFilePath() ? $this->fileStorageService->generateSecureUrl($message->getFilePath()) : null,
                'isFromMe' => $message->getSender() === $user,
                'sender' => [
                    'id' => $message->getSender()->getId(),
                    'firstName' => $message->getSender()->getFirstName(),
                    'lastName' => $message->getSender()->getLastName(),
                ],
                'createdAt' => $message->getCreatedAt()?->format('Y-m-d H:i:s'),
                'isRead' => $message->isRead(),
            ];
        }, $messages);

        return $this->render('tailadmin/pages/messages/chat.html.twig', [
            'pageTitle' => 'Conversation ',
            'pageName' => 'Conversation',
            'conversationId' => $conversationId,
            'messages' => $messagesData,
            'otherUser' => [
                'id' => $otherUser->getId(),
                'firstName' => $otherUser->getFirstName(),
                'lastName' => $otherUser->getLastName(),
                'email' => $otherUser->getEmail(),
            ],
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                ['label' => 'Messages', 'url' => $this->generateUrl('admin_messages_list')],
            ],
        ]);
    }

    #[Route('/admin/messages/{conversationId}/mark-read', name: 'admin_messages_mark_read', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function markAsRead(string $conversationId): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Non authentifié'], 401);
        }

        $this->messageRepository->markConversationAsRead($conversationId, $user);

        return new JsonResponse([
            'success' => true,
            'message' => 'Messages marqués comme lus',
        ]);
    }

    #[Route('/admin/messages/unread-count', name: 'admin_messages_unread_count', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getUnreadCount(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'count' => 0], 401);
        }

        $count = $this->messageRepository->countUnreadMessages($user);

        return new JsonResponse([
            'success' => true,
            'count' => $count,
        ]);
    }

}

