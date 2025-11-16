<?php

namespace App\Controller;

use App\Entity\Coach;
use App\Entity\Family;
use App\Entity\ParentUser;
use App\Entity\Specialist;
use App\Entity\Student;
use App\Form\RegisterCoachType;
use App\Form\RegisterParentType;
use App\Form\RegisterSpecialistType;
use App\Form\RegisterStudentType;
use App\Repository\FamilyRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class SecurityController extends AbstractController
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
        private readonly FamilyRepository $familyRepository,
        private readonly ValidatorInterface $validator,
        private readonly TokenStorageInterface $tokenStorage
    ) {
    }

    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($user = $this->getUser()) {
            // Rediriger vers le dashboard approprié selon le rôle
            if ($user instanceof Coach) {
                return $this->redirectToRoute('admin_dashboard');
            }
            return $this->redirectToRoute('app_dashboard');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('tailadmin/pages/auth/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/login/token', name: 'app_login_token', methods: ['GET'])]
    public function loginWithToken(Request $request): Response
    {
        // Si déjà connecté, rediriger vers le dashboard
        if ($user = $this->getUser()) {
            if ($user instanceof Coach) {
                return $this->redirectToRoute('admin_dashboard');
            }
            return $this->redirectToRoute('app_dashboard');
        }

        $username = $request->query->get('username');
        $token = $request->query->get('token');

        if (!$username || !$token) {
            $this->addFlash('error', 'Username et token requis pour la connexion automatique.');
            return $this->redirectToRoute('app_login');
        }

        // Trouver l'utilisateur par email ou pseudo
        $user = $this->userRepository->findByIdentifier($username);

        if (!$user) {
            $this->addFlash('error', 'Utilisateur non trouvé.');
            return $this->redirectToRoute('app_login');
        }

        // Vérifier le token
        if (!$user->isAuthTokenValid($token)) {
            $this->addFlash('error', 'Token invalide ou expiré.');
            return $this->redirectToRoute('app_login');
        }

        // Vérifier que l'utilisateur est actif
        if (!$user->isActive()) {
            $this->addFlash('error', 'Votre compte est désactivé.');
            return $this->redirectToRoute('app_login');
        }

        // Connecter l'utilisateur automatiquement
        // Utiliser le TokenStorage pour authentifier l'utilisateur
        $authToken = new UsernamePasswordToken(
            $user,
            'main',
            $user->getRoles()
        );

        $this->tokenStorage->setToken($authToken);
        
        // Sauvegarder dans la session
        $request->getSession()->set('_security_main', serialize($authToken));

        // Rediriger vers le dashboard approprié
        if ($user instanceof Coach) {
            return $this->redirectToRoute('admin_dashboard');
        }
        return $this->redirectToRoute('app_dashboard');
    }


    #[Route('/register/parent', name: 'app_register_parent')]
    public function registerParent(Request $request): Response
    {
        // Rediriger si déjà connecté
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $parent = new ParentUser();
        $form = $this->createForm(RegisterParentType::class, $parent);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $login = $form->get('login')->getData();
            
            // Déterminer si c'est un email ou un pseudo
            $isEmail = filter_var($login, FILTER_VALIDATE_EMAIL);
            
            if ($isEmail) {
                $parent->setEmail($login);
            } else {
                // Si c'est un pseudo, utiliser comme email aussi
                $parent->setEmail($login . '@sara.education');
            }

            $plainPassword = $form->get('plainPassword')->getData();
            $hashedPassword = $this->passwordHasher->hashPassword($parent, $plainPassword);
            $parent->setPassword($hashedPassword);
            $parent->setIsActive(true);

            // Créer une famille pour le parent
            $family = new Family();
            $family->setFamilyIdentifier(uniqid('FAM_'));
            $family->setIsActive(true);
            $parent->setFamily($family);

            // Validation
            $errors = $this->validator->validate($parent);
            if (count($errors) > 0) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
                return $this->render('tailadmin/pages/auth/register_parent.html.twig', [
                    'form' => $form,
                ]);
            }

            $this->em->persist($family);
            $this->em->persist($parent);
            $this->em->flush();

            $this->addFlash('success', 'Inscription réussie ! Vous pouvez maintenant vous connecter.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('tailadmin/pages/auth/register_parent.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/register/student', name: 'app_register_student')]
    public function registerStudent(Request $request): Response
    {
        // Rediriger si déjà connecté
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $student = new Student();
        $form = $this->createForm(RegisterStudentType::class, $student);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            $hashedPassword = $this->passwordHasher->hashPassword($student, $plainPassword);
            $student->setPassword($hashedPassword);
            
            // Générer l'email à partir du pseudo
            $student->setEmail($student->getPseudo() . '@sara.education');
            $student->setIsActive(true);
            $student->setPoints(0);

            // Validation
            $errors = $this->validator->validate($student);
            if (count($errors) > 0) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
                return $this->render('tailadmin/pages/auth/register_student.html.twig', [
                    'form' => $form,
                ]);
            }

            $this->em->persist($student);
            $this->em->flush();

            $this->addFlash('success', 'Inscription réussie ! Vous pouvez maintenant vous connecter.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('tailadmin/pages/auth/register_student.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/register/specialist', name: 'app_register_specialist')]
    public function registerSpecialist(Request $request): Response
    {
        // Rediriger si déjà connecté
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $specialist = new Specialist();
        $form = $this->createForm(RegisterSpecialistType::class, $specialist);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            $hashedPassword = $this->passwordHasher->hashPassword($specialist, $plainPassword);
            $specialist->setPassword($hashedPassword);
            $specialist->setIsActive(true);

            // Récupérer les spécialisations depuis la requête
            $specializations = [];
            $requestData = $request->request->all();
            foreach (RegisterSpecialistType::getSpecializations() as $key => $label) {
                if (isset($requestData['specialization_' . $key]) && $requestData['specialization_' . $key]) {
                    $specializations[] = $key;
                }
            }
            if (empty($specializations)) {
                $this->addFlash('error', 'Veuillez sélectionner au moins une spécialisation');
                return $this->render('tailadmin/pages/auth/register_specialist.html.twig', [
                    'form' => $form,
                    'specializations' => RegisterSpecialistType::getSpecializations(),
                ]);
            }
            $specialist->setSpecializations($specializations);

            // Validation
            $errors = $this->validator->validate($specialist);
            if (count($errors) > 0) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
                return $this->render('tailadmin/pages/auth/register_specialist.html.twig', [
                    'form' => $form,
                    'specializations' => RegisterSpecialistType::getSpecializations(),
                ]);
            }

            $this->em->persist($specialist);
            $this->em->flush();

            $this->addFlash('success', 'Inscription réussie ! Vous pouvez maintenant vous connecter.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('tailadmin/pages/auth/register_specialist.html.twig', [
            'form' => $form,
            'specializations' => RegisterSpecialistType::getSpecializations(),
        ]);
    }

    #[Route('/register/coach', name: 'app_register_coach')]
    public function registerCoach(Request $request): Response
    {
        // Rediriger si déjà connecté
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $coach = new Coach();
        $form = $this->createForm(RegisterCoachType::class, $coach);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            $hashedPassword = $this->passwordHasher->hashPassword($coach, $plainPassword);
            $coach->setPassword($hashedPassword);
            $coach->setIsActive(true);
            
            // Récupérer la spécialisation depuis la requête
            $requestData = $request->request->all();
         

            // Validation
            $errors = $this->validator->validate($coach);
            if (count($errors) > 0) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
                return $this->render('tailadmin/pages/auth/register_coach.html.twig', [
                    'form' => $form,

                ]);
            }

            $this->em->persist($coach);
            $this->em->flush();

            $this->addFlash('success', 'Inscription réussie ! Vous pouvez maintenant vous connecter.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('tailadmin/pages/auth/register_coach.html.twig', [
            'form' => $form
        ]);
    }
}

