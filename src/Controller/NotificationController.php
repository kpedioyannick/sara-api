<?php

namespace App\Controller;

use App\Repository\NotificationRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/notifications')]
#[IsGranted('ROLE_USER')]
class NotificationController extends AbstractController
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository,
        private readonly NotificationService $notificationService,
        private readonly EntityManagerInterface $em
    ) {
    }

    #[Route('', name: 'admin_notifications_list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté');
        }

        $page = $request->query->getInt('page', 1);
        $limit = 20;

        $notifications = $this->notificationRepository->findByUser($user, $page, $limit);
        $unreadCount = $this->notificationRepository->countUnread($user);

        $notificationsData = array_map(fn($notification) => $notification->toArray(), $notifications);

        return $this->render('tailadmin/pages/notifications/list.html.twig', [
            'notifications' => $notificationsData,
            'unreadCount' => $unreadCount,
            'page' => $page,
        ]);
    }

    #[Route('/api/unread-count', name: 'api_notifications_unread_count', methods: ['GET'])]
    public function unreadCount(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['count' => 0], 401);
        }

        $count = $this->notificationRepository->countUnread($user);

        return new JsonResponse(['count' => $count]);
    }

    #[Route('/api/list', name: 'api_notifications_list', methods: ['GET'])]
    public function apiList(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['notifications' => []], 401);
        }

        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 20);

        $notifications = $this->notificationRepository->findByUser($user, $page, $limit);
        $notificationsData = array_map(fn($notification) => $notification->toArray(), $notifications);

        return new JsonResponse([
            'notifications' => $notificationsData,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    #[Route('/api/mark-read/{id}', name: 'api_notifications_mark_read', methods: ['POST'])]
    public function markAsRead(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false], 401);
        }

        $notification = $this->notificationRepository->find($id);
        if (!$notification || $notification->getRecipient()->getId() !== $user->getId()) {
            return new JsonResponse(['success' => false], 404);
        }

        $this->notificationService->markAsRead($notification);

        return new JsonResponse(['success' => true]);
    }

    #[Route('/api/mark-all-read', name: 'api_notifications_mark_all_read', methods: ['POST'])]
    public function markAllAsRead(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false], 401);
        }

        $this->notificationService->markAllAsRead($user);

        return new JsonResponse(['success' => true]);
    }

    #[Route('/api/delete/{id}', name: 'api_notifications_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false], 401);
        }

        $notification = $this->notificationRepository->find($id);
        if (!$notification || $notification->getRecipient()->getId() !== $user->getId()) {
            return new JsonResponse(['success' => false], 404);
        }

        $this->notificationService->delete($notification);

        return new JsonResponse(['success' => true]);
    }
}

