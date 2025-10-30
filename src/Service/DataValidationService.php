<?php

namespace App\Service;

use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;

class DataValidationService
{
    public function __construct(
        private ValidatorInterface $validator
    ) {}

    public function validateEmail(string $email): array
    {
        $constraints = new Assert\Email([
            'message' => 'The email "{{ value }}" is not a valid email address.'
        ]);
        
        $errors = $this->validator->validate($email, $constraints);
        return $this->formatErrors($errors);
    }

    public function validatePassword(string $password): array
    {
        $constraints = [
            new Assert\NotBlank(['message' => 'Password cannot be blank']),
            new Assert\Length([
                'min' => 8,
                'max' => 255,
                'minMessage' => 'Password must be at least {{ limit }} characters long',
                'maxMessage' => 'Password cannot be longer than {{ limit }} characters'
            ]),
            new Assert\Regex([
                'pattern' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
                'message' => 'Password must contain at least one lowercase letter, one uppercase letter, one number, and one special character'
            ])
        ];
        
        $errors = $this->validator->validate($password, $constraints);
        return $this->formatErrors($errors);
    }

    public function sanitizeString(string $input): string
    {
        // Supprimer les caractères dangereux
        $input = trim($input);
        $input = strip_tags($input);
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        
        return $input;
    }

    public function sanitizeEmail(string $email): string
    {
        return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    }

    public function validateUserData(array $data): array
    {
        $errors = [];
        
        // Validation de l'email
        if (isset($data['email'])) {
            $emailErrors = $this->validateEmail($data['email']);
            if (!empty($emailErrors)) {
                $errors['email'] = $emailErrors;
            }
        }
        
        // Validation du mot de passe
        if (isset($data['password'])) {
            $passwordErrors = $this->validatePassword($data['password']);
            if (!empty($passwordErrors)) {
                $errors['password'] = $passwordErrors;
            }
        }
        
        // Validation des noms
        if (isset($data['firstName'])) {
            $firstName = $this->sanitizeString($data['firstName']);
            if (empty($firstName) || strlen($firstName) < 2) {
                $errors['firstName'] = ['First name must be at least 2 characters long'];
            }
        }
        
        if (isset($data['lastName'])) {
            $lastName = $this->sanitizeString($data['lastName']);
            if (empty($lastName) || strlen($lastName) < 2) {
                $errors['lastName'] = ['Last name must be at least 2 characters long'];
            }
        }
        
        return $errors;
    }

    private function formatErrors($errors): array
    {
        $formattedErrors = [];
        foreach ($errors as $error) {
            $formattedErrors[] = $error->getMessage();
        }
        return $formattedErrors;
    }
}
