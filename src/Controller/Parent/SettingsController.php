<?php

namespace App\Controller\Parent;

use App\Repository\ParentUserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/api/parent/settings')]
class SettingsController extends BaseParentController
{
    public function __construct(
        private ValidatorInterface $validator,
        private UserPasswordHasherInterface $passwordHasher,
        private ParentUserRepository $parentUserRepository
    ) {
        parent::__construct($parentUserRepository);
    }

    #[Route('/profile', name: 'parent_settings_profile', methods: ['GET'])]
    public function getProfile(): JsonResponse
    {
        $parent = $this->getParent();
        
        return $this->successResponse($parent->toArray(), 'Profile retrieved successfully');
    }

    #[Route('/profile', name: 'parent_settings_update_profile', methods: ['PUT'])]
    public function updateProfile(Request $request): JsonResponse
    {
        try {
            $parent = $this->getParent();
            
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            if (isset($data['firstName'])) {
                $parent->setFirstName($data['firstName']);
            }
            if (isset($data['lastName'])) {
                $parent->setLastName($data['lastName']);
            }
            if (isset($data['email'])) {
                $parent->setEmail($data['email']);
            }

            $errors = $this->validator->validate($parent);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->errorResponse('Validation failed', Response::HTTP_BAD_REQUEST, $errorMessages);
            }

            $this->parentUserRepository->save($parent, true);

            return $this->successResponse($parent->toArray(), 'Profile updated successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Profile update failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/password', name: 'parent_settings_change_password', methods: ['PUT'])]
    public function changePassword(Request $request): JsonResponse
    {
        try {
            $parent = $this->getParent();
            
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            $requiredFields = ['currentPassword', 'newPassword', 'confirmPassword'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return $this->errorResponse("Field '{$field}' is required", Response::HTTP_BAD_REQUEST);
                }
            }

            // Vérifier le mot de passe actuel
            if (!$this->passwordHasher->isPasswordValid($parent, $data['currentPassword'])) {
                return $this->errorResponse('Current password is incorrect', Response::HTTP_BAD_REQUEST);
            }

            // Vérifier que les nouveaux mots de passe correspondent
            if ($data['newPassword'] !== $data['confirmPassword']) {
                return $this->errorResponse('New passwords do not match', Response::HTTP_BAD_REQUEST);
            }

            // Hasher le nouveau mot de passe
            $hashedPassword = $this->passwordHasher->hashPassword($parent, $data['newPassword']);
            $parent->setPassword($hashedPassword);

            $this->parentUserRepository->save($parent, true);

            return $this->successResponse(null, 'Password changed successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Password change failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/notifications', name: 'parent_settings_notifications', methods: ['GET'])]
    public function getNotifications(): JsonResponse
    {
        $parent = $this->getParent();
        
        // Pour l'instant, retourner des préférences par défaut
        $notifications = [
            'email' => true,
            'push' => true,
            'sms' => false,
            'objectives' => true,
            'tasks' => true,
            'requests' => true,
            'planning' => true
        ];

        return $this->successResponse($notifications, 'Notification preferences retrieved successfully');
    }

    #[Route('/notifications', name: 'parent_settings_update_notifications', methods: ['PUT'])]
    public function updateNotifications(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            // Pour l'instant, simuler la sauvegarde des préférences
            $notifications = [
                'email' => $data['email'] ?? true,
                'push' => $data['push'] ?? true,
                'sms' => $data['sms'] ?? false,
                'objectives' => $data['objectives'] ?? true,
                'tasks' => $data['tasks'] ?? true,
                'requests' => $data['requests'] ?? true,
                'planning' => $data['planning'] ?? true
            ];

            return $this->successResponse($notifications, 'Notification preferences updated successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Notification preferences update failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
