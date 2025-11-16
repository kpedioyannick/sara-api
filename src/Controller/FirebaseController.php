<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class FirebaseController extends AbstractController
{
    #[Route('/admin/firebase/config', name: 'admin_firebase_config', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getConfig(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Non authentifié'], 401);
        }

        // Retourner la configuration Firebase pour le client
        // Les valeurs par défaut sont celles du projet SARA
        return new JsonResponse([
            'success' => true,
            'config' => [
                'apiKey' => $_ENV['FIREBASE_API_KEY'] ?? 'AIzaSyAvbJ1Q-uud2-KyPZUJVGsDzvfBjRs2CQ8',
                'authDomain' => $_ENV['FIREBASE_AUTH_DOMAIN'] ?? 'sara-6c71d.firebaseapp.com',
                'databaseURL' => $_ENV['FIREBASE_DATABASE_URL'] ?? 'https://sara-6c71d-default-rtdb.europe-west1.firebasedatabase.app',
                'projectId' => $_ENV['FIREBASE_PROJECT_ID'] ?? 'sara-6c71d',
                'storageBucket' => $_ENV['FIREBASE_STORAGE_BUCKET'] ?? 'sara-6c71d.firebasestorage.app',
                'messagingSenderId' => $_ENV['FIREBASE_MESSAGING_SENDER_ID'] ?? '840962006351',
                'appId' => $_ENV['FIREBASE_APP_ID'] ?? '1:840962006351:web:d5ad1b2986100f15ec393a',
            ],
        ]);
    }
}

