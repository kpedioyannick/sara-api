<?php

namespace App\Controller;

use App\Entity\Admin;
use App\Entity\Coach;
use App\Entity\ParentUser;
use App\Entity\Specialist;
use App\Entity\Student;
use App\Entity\User;
use App\Repository\AdminRepository;
use App\Repository\CoachRepository;
use App\Repository\ParentUserRepository;
use App\Repository\SpecialistRepository;
use App\Repository\StudentRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Service\ShortUrlService;

class AdminUserController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly CoachRepository $coachRepository,
        private readonly ParentUserRepository $parentRepository,
        private readonly StudentRepository $studentRepository,
        private readonly SpecialistRepository $specialistRepository,
        private readonly AdminRepository $adminRepository,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
        private readonly ShortUrlService $shortUrlService
    ) {
    }

    #[Route('/admin/users', name: 'admin_users_list')]
    #[IsGranted('ROLE_ADMIN')]
    public function list(Request $request): Response
    {
        $search = $request->query->get('search', '');
        $type = $request->query->get('type', 'all');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $users = [];
        $total = 0;

        if ($type === 'all' || $type === 'coach') {
            $coaches = $this->coachRepository->createQueryBuilder('c')
                ->where('c.email LIKE :search OR c.firstName LIKE :search OR c.lastName LIKE :search')
                ->setParameter('search', '%' . $search . '%')
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();
            $users = array_merge($users, $coaches);
        }

        if ($type === 'all' || $type === 'parent') {
            $parents = $this->parentRepository->createQueryBuilder('p')
                ->where('p.email LIKE :search OR p.firstName LIKE :search OR p.lastName LIKE :search')
                ->setParameter('search', '%' . $search . '%')
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();
            $users = array_merge($users, $parents);
        }

        if ($type === 'all' || $type === 'student') {
            $students = $this->studentRepository->createQueryBuilder('s')
                ->where('s.email LIKE :search OR s.firstName LIKE :search OR s.lastName LIKE :search OR s.pseudo LIKE :search')
                ->setParameter('search', '%' . $search . '%')
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();
            $users = array_merge($users, $students);
        }

        if ($type === 'all' || $type === 'specialist') {
            $specialists = $this->specialistRepository->createQueryBuilder('sp')
                ->where('sp.email LIKE :search OR sp.firstName LIKE :search OR sp.lastName LIKE :search')
                ->setParameter('search', '%' . $search . '%')
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();
            $users = array_merge($users, $specialists);
        }

        if ($type === 'all' || $type === 'admin') {
            $admins = $this->adminRepository->createQueryBuilder('a')
                ->where('a.email LIKE :search OR a.firstName LIKE :search OR a.lastName LIKE :search')
                ->setParameter('search', '%' . $search . '%')
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();
            $users = array_merge($users, $admins);
        }

        return $this->render('tailadmin/pages/users/list.html.twig', [
            'users' => $users,
            'search' => $search,
            'type' => $type,
            'page' => $page,
        ]);
    }

    #[Route('/admin/users/{id}', name: 'admin_users_view', requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function view(int $id): Response
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            $this->addFlash('error', 'Utilisateur non trouvé.');
            return $this->redirectToRoute('admin_users_list');
        }

        return $this->render('tailadmin/pages/users/view.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/admin/users/{id}/edit', name: 'admin_users_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(int $id, Request $request): Response
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            $this->addFlash('error', 'Utilisateur non trouvé.');
            return $this->redirectToRoute('admin_users_list');
        }

        if ($request->isMethod('POST')) {
            $firstName = $request->request->get('firstName');
            $lastName = $request->request->get('lastName');
            $email = $request->request->get('email');
            $isActive = $request->request->get('isActive') === '1';

            $user->setFirstName($firstName);
            $user->setLastName($lastName);
            $user->setEmail($email);
            $user->setIsActive($isActive);
            $user->setUpdatedAt(new \DateTimeImmutable());

            $errors = $this->validator->validate($user);
            if (count($errors) > 0) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
            } else {
                $this->em->flush();
                $this->addFlash('success', 'Utilisateur mis à jour avec succès.');
                return $this->redirectToRoute('admin_users_view', ['id' => $id]);
            }
        }

        return $this->render('tailadmin/pages/users/edit.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/admin/users/{id}/change-password', name: 'admin_users_change_password', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function changePassword(int $id, Request $request): JsonResponse
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            return new JsonResponse(['error' => 'Utilisateur non trouvé.'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $newPassword = $data['password'] ?? null;

        if (!$newPassword || strlen($newPassword) < 6) {
            return new JsonResponse(['error' => 'Le mot de passe doit contenir au moins 6 caractères.'], 400);
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);
        $user->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Mot de passe modifié avec succès.']);
    }

    #[Route('/admin/users/{id}/generate-token', name: 'admin_users_generate_token', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function generateToken(int $id, Request $request): JsonResponse
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            return new JsonResponse(['error' => 'Utilisateur non trouvé.'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $validityDays = (int) ($data['validityDays'] ?? 30);

        $token = $user->generateAuthToken($validityDays);
        $this->em->flush();

        // Déterminer l'identifiant (email ou pseudo)
        $username = $user->getEmail();
        if ($user instanceof Student && $user->getPseudo()) {
            $username = $user->getPseudo();
        }

        $loginUrl = $request->getSchemeAndHttpHost() . '/login/token?username=' . urlencode($username) . '&token=' . urlencode($token);
        $shortLoginUrl = $this->shortUrlService->shorten($loginUrl);

        return new JsonResponse([
            'success' => true,
            'token' => $token,
            'loginUrl' => $shortLoginUrl,
            'expiresAt' => $user->getAuthTokenExpiresAt()->format('Y-m-d H:i:s'),
            'message' => 'Token généré avec succès.'
        ]);
    }

    #[Route('/admin/users/{id}/delete', name: 'admin_users_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            return new JsonResponse(['error' => 'Utilisateur non trouvé.'], 404);
        }

        // Ne pas permettre la suppression de l'admin actuel
        if ($user === $this->getUser()) {
            return new JsonResponse(['error' => 'Vous ne pouvez pas supprimer votre propre compte.'], 400);
        }

        $this->em->remove($user);
        $this->em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Utilisateur supprimé avec succès.']);
    }

    #[Route('/admin/users/new', name: 'admin_users_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $type = $request->request->get('type');
            $firstName = $request->request->get('firstName');
            $lastName = $request->request->get('lastName');
            $email = $request->request->get('email');
            $password = $request->request->get('password');
            $isActive = $request->request->get('isActive') === '1';

            if (!$type || !$firstName || !$lastName || !$email || !$password) {
                $this->addFlash('error', 'Tous les champs sont requis.');
                return $this->render('tailadmin/pages/users/new.html.twig', [
                    'type' => $type ?? '',
                ]);
            }

            // Créer l'utilisateur selon le type
            $user = null;
            switch ($type) {
                case 'admin':
                    $user = new Admin();
                    break;
                case 'coach':
                    $user = new Coach();
                    break;
                case 'parent':
                    $user = new ParentUser();
                    break;
                case 'student':
                    $user = new Student();
                    $pseudo = $request->request->get('pseudo');
                    if ($pseudo) {
                        $user->setPseudo($pseudo);
                    }
                    break;
                case 'specialist':
                    $user = new Specialist();
                    break;
                default:
                    $this->addFlash('error', 'Type d\'utilisateur invalide.');
                    return $this->render('tailadmin/pages/users/new.html.twig', [
                        'type' => $type,
                    ]);
            }

            $user->setFirstName($firstName);
            $user->setLastName($lastName);
            $user->setEmail($email);
            $user->setIsActive($isActive);

            $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
            $user->setPassword($hashedPassword);

            // Validation
            $errors = $this->validator->validate($user);
            if (count($errors) > 0) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
                return $this->render('tailadmin/pages/users/new.html.twig', [
                    'type' => $type,
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'email' => $email,
                ]);
            }

            $this->em->persist($user);
            $this->em->flush();

            $this->addFlash('success', 'Utilisateur créé avec succès.');
            return $this->redirectToRoute('admin_users_view', ['id' => $user->getId()]);
        }

        return $this->render('tailadmin/pages/users/new.html.twig', [
            'type' => $request->query->get('type', ''),
        ]);
    }
}

