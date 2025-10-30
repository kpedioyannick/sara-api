<?php

namespace App\Controller\Specialist;

use App\Repository\SpecialistRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/api/specialist/settings')]
class SettingsController extends BaseSpecialistController
{
    public function __construct(
        private ValidatorInterface $validator,
        private UserPasswordHasherInterface $passwordHasher,
        private SpecialistRepository $specialistRepository
    ) {
        parent::__construct($specialistRepository);
    }

    #[Route('/profile', name: 'specialist_settings_profile', methods: ['GET'])]
    public function getProfile(): JsonResponse
    {
        $specialist = $this->getSpecialist();
        
        return $this->successResponse($specialist->toArray(), 'Profile retrieved successfully');
    }

    #[Route('/profile', name: 'specialist_settings_update_profile', methods: ['PUT'])]
    public function updateProfile(Request $request): JsonResponse
    {
        try {
            $specialist = $this->getSpecialist();
            
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            if (isset($data['firstName'])) {
                $specialist->setFirstName($data['firstName']);
            }
            if (isset($data['lastName'])) {
                $specialist->setLastName($data['lastName']);
            }
            if (isset($data['email'])) {
                $specialist->setEmail($data['email']);
            }

            $errors = $this->validator->validate($specialist);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->errorResponse('Validation failed', Response::HTTP_BAD_REQUEST, $errorMessages);
            }

            $this->specialistRepository->save($specialist, true);

            return $this->successResponse($specialist->toArray(), 'Profile updated successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Profile update failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/specializations', name: 'specialist_settings_specializations', methods: ['GET'])]
    public function getSpecializations(): JsonResponse
    {
        $specialist = $this->getSpecialist();
        
        return $this->successResponse($specialist->getSpecializations(), 'Specializations retrieved successfully');
    }

    #[Route('/specializations', name: 'specialist_settings_update_specializations', methods: ['PUT'])]
    public function updateSpecializations(Request $request): JsonResponse
    {
        try {
            $specialist = $this->getSpecialist();
            
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            if (!isset($data['specializations']) || !is_array($data['specializations'])) {
                return $this->errorResponse('Specializations must be an array', Response::HTTP_BAD_REQUEST);
            }

            $specialist->setSpecializations($data['specializations']);

            $errors = $this->validator->validate($specialist);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->errorResponse('Validation failed', Response::HTTP_BAD_REQUEST, $errorMessages);
            }

            $this->specialistRepository->save($specialist, true);

            return $this->successResponse($specialist->getSpecializations(), 'Specializations updated successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Specializations update failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/password', name: 'specialist_settings_change_password', methods: ['PUT'])]
    public function changePassword(Request $request): JsonResponse
    {
        try {
            $specialist = $this->getSpecialist();
            
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
            if (!$this->passwordHasher->isPasswordValid($specialist, $data['currentPassword'])) {
                return $this->errorResponse('Current password is incorrect', Response::HTTP_BAD_REQUEST);
            }

            // Vérifier que les nouveaux mots de passe correspondent
            if ($data['newPassword'] !== $data['confirmPassword']) {
                return $this->errorResponse('New passwords do not match', Response::HTTP_BAD_REQUEST);
            }

            // Hasher le nouveau mot de passe
            $hashedPassword = $this->passwordHasher->hashPassword($specialist, $data['newPassword']);
            $specialist->setPassword($hashedPassword);

            $this->specialistRepository->save($specialist, true);

            return $this->successResponse(null, 'Password changed successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Password change failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/notifications', name: 'specialist_settings_notifications', methods: ['GET'])]
    public function getNotifications(): JsonResponse
    {
        $specialist = $this->getSpecialist();
        
        // Pour l'instant, retourner des préférences par défaut
        $notifications = [
            'email' => true,
            'push' => true,
            'sms' => false,
            'availability_changes' => true,
            'task_assignments' => true,
            'request_assignments' => true,
            'planning_updates' => true
        ];

        return $this->successResponse($notifications, 'Notification preferences retrieved successfully');
    }

    #[Route('/notifications', name: 'specialist_settings_update_notifications', methods: ['PUT'])]
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
                'availability_changes' => $data['availability_changes'] ?? true,
                'task_assignments' => $data['task_assignments'] ?? true,
                'request_assignments' => $data['request_assignments'] ?? true,
                'planning_updates' => $data['planning_updates'] ?? true
            ];

            return $this->successResponse($notifications, 'Notification preferences updated successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Notification preferences update failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
