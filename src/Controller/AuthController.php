<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\FamilyRepository;
use App\Service\RateLimiterService;
use App\Service\DataValidationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

#[Route('/api/auth')]
class AuthController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private FamilyRepository $familyRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
        private TokenStorageInterface $tokenStorage,
        private JWTTokenManagerInterface $jwtManager,
        private RateLimiterService $rateLimiterService,
        private DataValidationService $dataValidationService
    ) {}

    #[Route('/register', name: 'auth_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return new JsonResponse(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
            }

            // Validation et sanitisation des données
            $validationErrors = $this->dataValidationService->validateUserData($data);
            if (!empty($validationErrors)) {
                return new JsonResponse(['error' => 'Validation failed', 'details' => $validationErrors], Response::HTTP_BAD_REQUEST);
            }

            // Sanitisation des données
            $data['email'] = $this->dataValidationService->sanitizeEmail($data['email']);
            $data['firstName'] = $this->dataValidationService->sanitizeString($data['firstName']);
            $data['lastName'] = $this->dataValidationService->sanitizeString($data['lastName']);

            // Validation des données requises
            $requiredFields = ['email', 'password', 'firstName', 'lastName', 'userType'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return new JsonResponse(['error' => "Field '{$field}' is required"], Response::HTTP_BAD_REQUEST);
                }
            }

            // Vérifier si l'utilisateur existe déjà
            if ($this->userRepository->findOneBy(['email' => $data['email']])) {
                return new JsonResponse(['error' => 'User already exists'], Response::HTTP_CONFLICT);
            }

            // Créer l'utilisateur selon le type
            $user = $this->createUserByType($data);
            
            if (!$user) {
                return new JsonResponse(['error' => 'Invalid user type'], Response::HTTP_BAD_REQUEST);
            }

            // Hasher le mot de passe
            $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
            $user->setPassword($hashedPassword);

            // Valider l'entité
            $errors = $this->validator->validate($user);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return new JsonResponse(['error' => $errorMessages], Response::HTTP_BAD_REQUEST);
            }

            // Sauvegarder l'utilisateur
            $this->userRepository->save($user, true);

            // Générer le token JWT
            $token = $this->jwtManager->create($user);

            return new JsonResponse([
                'success' => true,
                'message' => 'User created successfully',
                'data' => [
                    'user' => [
                        'id' => $user->getId(),
                        'email' => $user->getEmail(),
                        'firstName' => $user->getFirstName(),
                        'lastName' => $user->getLastName(),
                        'userType' => $user->getUserType(),
                        'isActive' => $user->isActive(),
                        'createdAt' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
                        'roles' => $user->getRoles()
                    ],
                    'token' => $token
                ]
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Registration failed: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/login', name: 'auth_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        try {
            // Vérifier le rate limiting pour l'authentification
            // $this->rateLimiterService->checkAuthRateLimit($request);
            
            $data = json_decode($request->getContent(), true);
            
            if (!$data || !isset($data['email']) || !isset($data['password'])) {
                return new JsonResponse(['error' => 'Email and password are required'], Response::HTTP_BAD_REQUEST);
            }

            // Trouver l'utilisateur
            $user = $this->userRepository->findOneBy(['email' => $data['email']]);
            
            if (!$user) {
                return new JsonResponse(['error' => 'Invalid credentials'], Response::HTTP_UNAUTHORIZED);
            }

            // Vérifier le mot de passe
            if (!$this->passwordHasher->isPasswordValid($user, $data['password'])) {
                return new JsonResponse(['error' => 'Invalid credentials'], Response::HTTP_UNAUTHORIZED);
            }

            // Vérifier si l'utilisateur est actif
            if (!$user->isActive()) {
                return new JsonResponse(['error' => 'Account is disabled'], Response::HTTP_UNAUTHORIZED);
            }

            // Générer le token JWT
            $token = $this->jwtManager->create($user);

            return new JsonResponse([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => [
                        'id' => $user->getId(),
                        'email' => $user->getEmail(),
                        'firstName' => $user->getFirstName(),
                        'lastName' => $user->getLastName(),
                        'userType' => $user->getUserType(),
                        'isActive' => $user->isActive(),
                        'createdAt' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
                        'roles' => $user->getRoles()
                    ],
                    'token' => $token
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Login failed: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/logout', name: 'auth_logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        $this->tokenStorage->setToken(null);
        
        return new JsonResponse(['message' => 'Logout successful']);
    }

    #[Route('/me', name: 'auth_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user) {
            return new JsonResponse(['error' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse([
            'user' => $user->toArray()
        ]);
    }

    private function createUserByType(array $data): ?User
    {
        $userType = $data['userType'];
        
        switch ($userType) {
            case 'coach':
                $user = new \App\Entity\Coach();
                if (isset($data['specialization'])) {
                    $user->setSpecialization($data['specialization']);
                }
                break;
                
            case 'parent':
                $user = new \App\Entity\ParentUser();
                $family = new \App\Entity\Family();
                $family->setCoach(null);
                $familyIdentifier = 'FAM_' . strtoupper($data['lastName']) . '_' . strtoupper(substr($data['firstName'], 0, 1));
                $family->setFamilyIdentifier($familyIdentifier);
                $user->setFamily($family);
                break;
                
            case 'student':
                $user = new \App\Entity\Student();
                if (isset($data['pseudo'])) {
                    $user->setPseudo($data['pseudo']);
                }
                if (isset($data['class'])) {
                    $user->setClass($data['class']);
                }
                
                if (isset($data['family_id'])) {
                    $family = $this->familyRepository->find($data['family_id']);
                    if (!$family) {
                        throw new \Exception('Family not found with ID: ' . $data['family_id']);
                    }
                    $user->setFamily($family);
                } else {
                    throw new \Exception('Student registration requires family_id to link to existing family.');
                }
                break;
                
            case 'specialist':
                $user = new \App\Entity\Specialist();
                if (isset($data['specializations'])) {
                    $user->setSpecializations($data['specializations']);
                }
                break;
                
            default:
                return null;
        }

        $user->setEmail($data['email']);
        $user->setFirstName($data['firstName']);
        $user->setLastName($data['lastName']);
        
        return $user;
    }
}
