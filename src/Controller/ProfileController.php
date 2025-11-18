<?php

namespace App\Controller;

use App\Entity\Coach;
use App\Entity\ParentUser;
use App\Entity\Specialist;
use App\Entity\Student;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ProfileController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator
    ) {
    }

    #[Route('/admin/profile', name: 'admin_profile')]
    #[IsGranted('ROLE_USER')]
    public function profile(): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Préparer les données selon le type d'utilisateur
        $userData = [];
        $userType = $user->getUserType();

        if ($user instanceof Coach) {
            $userData = [
                'id' => $user->getId(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'email' => $user->getEmail(),
                'specialization' => $user->getSpecialization(),
                'isActive' => $user->isActive(),
                'createdAt' => $user->getCreatedAt(),
                'updatedAt' => $user->getUpdatedAt(),
            ];
        } elseif ($user instanceof ParentUser) {
            $userData = [
                'id' => $user->getId(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'email' => $user->getEmail(),
                'isActive' => $user->isActive(),
                'createdAt' => $user->getCreatedAt(),
                'updatedAt' => $user->getUpdatedAt(),
                'family' => $user->getFamily() ? [
                    'id' => $user->getFamily()->getId(),
                    'familyIdentifier' => $user->getFamily()->getFamilyIdentifier(),
                    'isActive' => $user->getFamily()->isActive(),
                ] : null,
            ];
        } elseif ($user instanceof Student) {
            $userData = [
                'id' => $user->getId(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'email' => $user->getEmail(),
                'pseudo' => $user->getPseudo(),
                'class' => $user->getClass(),
                'points' => $user->getPoints(),
                'isActive' => $user->isActive(),
                'createdAt' => $user->getCreatedAt(),
                'updatedAt' => $user->getUpdatedAt(),
                'family' => $user->getFamily() ? [
                    'id' => $user->getFamily()->getId(),
                    'familyIdentifier' => $user->getFamily()->getFamilyIdentifier(),
                    'isActive' => $user->getFamily()->isActive(),
                ] : null,
            ];
        } elseif ($user instanceof Specialist) {
            $userData = [
                'id' => $user->getId(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'email' => $user->getEmail(),
                'specializations' => $user->getSpecializations(),
                'isActive' => $user->isActive(),
                'createdAt' => $user->getCreatedAt(),
                'updatedAt' => $user->getUpdatedAt(),
            ];
        }

        return $this->render('tailadmin/pages/profile/profile.html.twig', [
            'pageTitle' => 'Mon Profil',
            'pageName' => 'profile',
            'breadcrumbs' => [
                ['label' => 'Accueil', 'url' => $this->generateUrl('admin_dashboard')],
            ],
            'user' => $user,
            'userData' => $userData,
            'userType' => $userType,
        ]);
    }

    #[Route('/admin/profile/update', name: 'admin_profile_update', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Utilisateur non trouvé'], 401);
        }

        $data = json_decode($request->getContent(), true);

        try {
            // Mettre à jour les informations selon le type d'utilisateur
            if (isset($data['firstName'])) {
                $user->setFirstName($data['firstName']);
            }
            if (isset($data['lastName'])) {
                $user->setLastName($data['lastName']);
            }

            // Champs spécifiques selon le type
            if ($user instanceof Student) {
                if (isset($data['pseudo'])) {
                    $user->setPseudo($data['pseudo']);
                }
                if (isset($data['class'])) {
                    $user->setClass($data['class']);
                }
            } elseif ($user instanceof Coach) {
                if (isset($data['specialization'])) {
                    $user->setSpecialization($data['specialization']);
                }
            } elseif ($user instanceof Specialist) {
                if (isset($data['specializations']) && is_array($data['specializations'])) {
                    $user->setSpecializations($data['specializations']);
                }
            }

            // Validation
            $errors = $this->validator->validate($user);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return new JsonResponse(['success' => false, 'message' => implode(', ', $errorMessages)], 400);
            }

            $user->setUpdatedAt(new \DateTimeImmutable());
            $this->em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Profil mis à jour avec succès'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/admin/profile/change-password', name: 'admin_profile_change_password', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function changePassword(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Utilisateur non trouvé'], 401);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['currentPassword']) || !isset($data['newPassword']) || !isset($data['confirmPassword'])) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Tous les champs sont requis'
            ], 400);
        }

        // Vérifier le mot de passe actuel
        if (!$this->passwordHasher->isPasswordValid($user, $data['currentPassword'])) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Mot de passe actuel incorrect'
            ], 400);
        }

        // Vérifier que les nouveaux mots de passe correspondent
        if ($data['newPassword'] !== $data['confirmPassword']) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Les mots de passe ne correspondent pas'
            ], 400);
        }

        // Vérifier la longueur du nouveau mot de passe
        if (strlen($data['newPassword']) < 6) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Le mot de passe doit contenir au moins 6 caractères'
            ], 400);
        }

        try {
            // Hasher et définir le nouveau mot de passe
            $hashedPassword = $this->passwordHasher->hashPassword($user, $data['newPassword']);
            $user->setPassword($hashedPassword);
            $user->setUpdatedAt(new \DateTimeImmutable());

            $this->em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Mot de passe modifié avec succès'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de la modification du mot de passe: ' . $e->getMessage()
            ], 500);
        }
    }
}

