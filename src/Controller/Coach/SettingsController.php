<?php

namespace App\Controller\Coach;

use App\Repository\CoachRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/coach/settings')]
class SettingsController extends BaseCoachController
{
    public function __construct(
        private CoachRepository $coachRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface $validator
    ) {}

    #[Route('', name: 'coach_settings_get', methods: ['GET'])]
    public function getSettings(): JsonResponse
    {
        $coach = $this->getCoach();
        
        $data = [
            'profile' => $coach->toArray(),
            'preferences' => [
                'notifications' => [],
                'theme' => 'light',
                'language' => 'fr'
            ]
        ];

        return $this->successResponse($data, 'Coach settings retrieved successfully');
    }

    #[Route('/profile', name: 'coach_settings_update_profile', methods: ['PUT'])]
    public function updateProfile(Request $request): JsonResponse
    {
        try {
            $coach = $this->getCoach();
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            if (isset($data['firstName'])) {
                $coach->setFirstName($data['firstName']);
            }
            if (isset($data['lastName'])) {
                $coach->setLastName($data['lastName']);
            }
            if (isset($data['email'])) {
                $coach->setEmail($data['email']);
            }
            if (isset($data['specialization'])) {
                $coach->setSpecialization($data['specialization']);
            }

            $errors = $this->validator->validate($coach);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->errorResponse('Validation failed', Response::HTTP_BAD_REQUEST, $errorMessages);
            }

            $this->coachRepository->save($coach, true);

            return $this->successResponse($coach->toArray(), 'Coach profile updated successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Profile update failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/password', name: 'coach_settings_change_password', methods: ['PUT'])]
    public function changePassword(Request $request): JsonResponse
    {
        try {
            $coach = $this->getCoach();
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            $requiredFields = ['current_password', 'new_password', 'confirm_password'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return $this->errorResponse("Field '{$field}' is required", Response::HTTP_BAD_REQUEST);
                }
            }

            // Vérifier le mot de passe actuel
            if (!$this->passwordHasher->isPasswordValid($coach, $data['current_password'])) {
                return $this->errorResponse('Current password is incorrect', Response::HTTP_BAD_REQUEST);
            }

            // Vérifier que les nouveaux mots de passe correspondent
            if ($data['new_password'] !== $data['confirm_password']) {
                return $this->errorResponse('New passwords do not match', Response::HTTP_BAD_REQUEST);
            }

            // Vérifier la force du nouveau mot de passe
            if (strlen($data['new_password']) < 8) {
                return $this->errorResponse('New password must be at least 8 characters long', Response::HTTP_BAD_REQUEST);
            }

            // Hasher le nouveau mot de passe
            $hashedPassword = $this->passwordHasher->hashPassword($coach, $data['new_password']);
            $coach->setPassword($hashedPassword);

            $this->coachRepository->save($coach, true);

            return $this->successResponse(null, 'Password changed successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Password change failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/preferences', name: 'coach_settings_update_preferences', methods: ['PUT'])]
    public function updatePreferences(Request $request): JsonResponse
    {
        try {
            $coach = $this->getCoach();
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            if (isset($data['notifications'])) {
                $coach->setNotificationPreferences($data['notifications']);
            }
            if (isset($data['theme'])) {
                $coach->setTheme($data['theme']);
            }
            if (isset($data['language'])) {
                $coach->setLanguage($data['language']);
            }

            $this->coachRepository->save($coach, true);

            return $this->successResponse($coach->toArray(), 'Preferences updated successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Preferences update failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
