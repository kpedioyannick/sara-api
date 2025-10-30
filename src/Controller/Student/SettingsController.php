<?php

namespace App\Controller\Student;

use App\Repository\StudentRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/api/student/settings')]
class SettingsController extends BaseStudentController
{
    public function __construct(
        private ValidatorInterface $validator,
        private UserPasswordHasherInterface $passwordHasher,
        private StudentRepository $studentRepository
    ) {
        parent::__construct($studentRepository);
    }

    #[Route('/profile', name: 'student_settings_profile', methods: ['GET'])]
    public function getProfile(): JsonResponse
    {
        $student = $this->getStudent();
        
        return $this->successResponse($student->toArray(), 'Profile retrieved successfully');
    }

    #[Route('/profile', name: 'student_settings_update_profile', methods: ['PUT'])]
    public function updateProfile(Request $request): JsonResponse
    {
        try {
            $student = $this->getStudent();
            
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            if (isset($data['firstName'])) {
                $student->setFirstName($data['firstName']);
            }
            if (isset($data['lastName'])) {
                $student->setLastName($data['lastName']);
            }
            if (isset($data['email'])) {
                $student->setEmail($data['email']);
            }
            if (isset($data['pseudo'])) {
                $student->setPseudo($data['pseudo']);
            }

            $errors = $this->validator->validate($student);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->errorResponse('Validation failed', Response::HTTP_BAD_REQUEST, $errorMessages);
            }

            $this->studentRepository->save($student, true);

            return $this->successResponse($student->toArray(), 'Profile updated successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Profile update failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/password', name: 'student_settings_change_password', methods: ['PUT'])]
    public function changePassword(Request $request): JsonResponse
    {
        try {
            $student = $this->getStudent();
            
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
            if (!$this->passwordHasher->isPasswordValid($student, $data['currentPassword'])) {
                return $this->errorResponse('Current password is incorrect', Response::HTTP_BAD_REQUEST);
            }

            // Vérifier que les nouveaux mots de passe correspondent
            if ($data['newPassword'] !== $data['confirmPassword']) {
                return $this->errorResponse('New passwords do not match', Response::HTTP_BAD_REQUEST);
            }

            // Hasher le nouveau mot de passe
            $hashedPassword = $this->passwordHasher->hashPassword($student, $data['newPassword']);
            $student->setPassword($hashedPassword);

            $this->studentRepository->save($student, true);

            return $this->successResponse(null, 'Password changed successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Password change failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/display', name: 'student_settings_display', methods: ['GET'])]
    public function getDisplaySettings(): JsonResponse
    {
        $student = $this->getStudent();
        
        // Pour l'instant, retourner des préférences par défaut
        $displaySettings = [
            'theme' => 'light', // light, dark
            'language' => 'fr',
            'notifications' => true,
            'sound' => true,
            'vibrations' => true
        ];

        return $this->successResponse($displaySettings, 'Display settings retrieved successfully');
    }

    #[Route('/display', name: 'student_settings_update_display', methods: ['PUT'])]
    public function updateDisplaySettings(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->errorResponse('Invalid JSON', Response::HTTP_BAD_REQUEST);
            }

            // Pour l'instant, simuler la sauvegarde des préférences d'affichage
            $displaySettings = [
                'theme' => $data['theme'] ?? 'light',
                'language' => $data['language'] ?? 'fr',
                'notifications' => $data['notifications'] ?? true,
                'sound' => $data['sound'] ?? true,
                'vibrations' => $data['vibrations'] ?? true
            ];

            return $this->successResponse($displaySettings, 'Display settings updated successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Display settings update failed: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
