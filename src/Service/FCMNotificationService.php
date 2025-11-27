<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service simplifié pour envoyer des notifications push via Firebase Cloud Messaging
 */
class FCMNotificationService
{
    public function __construct(
        private readonly FirebaseService $firebaseService,
        private readonly EntityManagerInterface $em
    ) {
    }

    /**
     * Envoie une notification push à un utilisateur
     */
    public function sendNotification(User $user, string $title, string $body, array $data = []): bool
    {
        $token = $user->getFcmToken();
        
        if (!$token) {
            error_log("FCM: Aucun token pour l'utilisateur {$user->getId()} ({$user->getEmail()})");
            return false;
        }

        try {
            $this->firebaseService->sendPushNotification($token, $title, $body, $data);
            error_log("FCM: Notification envoyée à {$user->getEmail()} - {$title}");
            return true;
        } catch (\Exception $e) {
            error_log("FCM: Erreur envoi notification à {$user->getEmail()}: " . $e->getMessage());
            
            // Si le token est invalide, le supprimer
            if (str_contains($e->getMessage(), 'invalid') || str_contains($e->getMessage(), 'not found')) {
                $user->setFcmToken(null);
                $this->em->flush();
                error_log("FCM: Token invalide supprimé pour {$user->getEmail()}");
            }
            
            return false;
        }
    }

    /**
     * Envoie une notification à plusieurs utilisateurs
     */
    public function sendNotificationToUsers(array $users, string $title, string $body, array $data = []): int
    {
        $successCount = 0;
        
        foreach ($users as $user) {
            if ($this->sendNotification($user, $title, $body, $data)) {
                $successCount++;
            }
        }
        
        return $successCount;
    }
}

