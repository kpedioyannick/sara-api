<?php

namespace App\Controller;

use App\Entity\ContactMessage;
use App\Form\ContactMessageType;
use App\Service\ContactEmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ContactEmailService $contactEmailService
    ) {
    }

    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        // Si l'utilisateur est déjà connecté, rediriger vers le dashboard approprié
        if ($user = $this->getUser()) {
            if ($user instanceof \App\Entity\Coach) {
                return $this->redirectToRoute('admin_dashboard');
            }
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('home/index.html.twig', [
            'pageTitle' => 'SARA - Plateforme d\'accompagnement éducatif',
        ]);
    }

    #[Route('/comment-ca-fonctionne', name: 'app_how_it_works')]
    public function howItWorks(): Response
    {
        return $this->render('home/how-it-works.html.twig', [
            'pageTitle' => 'Comment ça fonctionne ? - SARA',
        ]);
    }

    #[Route('/contactez-nous', name: 'app_contact')]
    public function contact(Request $request): Response
    {
        $contactMessage = new ContactMessage();
        $form = $this->createForm(ContactMessageType::class, $contactMessage);
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($contactMessage);
            $this->em->flush();
            
            // Envoyer un email de notification à l'équipe
            try {
                $this->contactEmailService->sendContactNotification($contactMessage);
            } catch (\Exception $e) {
                error_log('Erreur envoi email notification contact: ' . $e->getMessage());
            }
            
            // Envoyer un email de confirmation à l'expéditeur
            try {
                $this->contactEmailService->sendContactConfirmation($contactMessage);
            } catch (\Exception $e) {
                error_log('Erreur envoi email confirmation contact: ' . $e->getMessage());
            }
            
            $this->addFlash('success', 'Votre message a été envoyé avec succès ! Nous vous répondrons dans les plus brefs délais.');
            
            // Réinitialiser le formulaire après envoi
            $contactMessage = new ContactMessage();
            $form = $this->createForm(ContactMessageType::class, $contactMessage);
        }
        
        return $this->render('home/contact.html.twig', [
            'pageTitle' => 'Contactez-nous - SARA',
            'form' => $form,
        ]);
    }

    #[Route('/politique-de-confidentialite', name: 'app_privacy_policy')]
    public function privacyPolicy(): Response
    {
        return $this->render('home/privacy-policy.html.twig', [
            'pageTitle' => 'Politique de confidentialité - SARA',
        ]);
    }

    #[Route('/mentions-legales', name: 'app_legal_notice')]
    public function legalNotice(): Response
    {
        return $this->render('home/legal-notice.html.twig', [
            'pageTitle' => 'Mentions légales - SARA',
        ]);
    }
}

