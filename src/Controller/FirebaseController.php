<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class FirebaseController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {
    }

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

    #[Route('/admin/firebase/register-token', name: 'admin_firebase_register_token', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function registerToken(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['success' => false, 'message' => 'Non authentifié'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $token = $data['token'] ?? null;

        if (!$token) {
            return new JsonResponse(['success' => false, 'message' => 'Token manquant'], 400);
        }

        $user->setFcmToken($token);
        $this->em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Token enregistré']);
    }
}

