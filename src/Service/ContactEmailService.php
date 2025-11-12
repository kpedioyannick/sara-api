<?php

namespace App\Service;

use App\Entity\ContactMessage;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class ContactEmailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $contactEmail,
        private readonly string $appName = 'SARA'
    ) {
    }

    /**
     * Envoie un email de notification lorsqu'un message de contact est reçu
     */
    public function sendContactNotification(ContactMessage $contactMessage): void
    {
        try {
            $email = (new TemplatedEmail())
                ->from(new Address('noreply@sara.fr', $this->appName))
                ->to(new Address($this->contactEmail, 'SARA Contact'))
                ->replyTo(new Address($contactMessage->getEmail(), $contactMessage->getFirstName() . ' ' . $contactMessage->getLastName()))
                ->subject('Nouveau message de contact : ' . $contactMessage->getSubject())
                ->htmlTemplate('emails/contact_notification.html.twig')
                ->context([
                    'contactMessage' => $contactMessage,
                    'appName' => $this->appName,
                ]);

            $this->mailer->send($email);
        } catch (\Exception $e) {
            // Log l'erreur mais ne bloque pas la sauvegarde du message
            error_log('Erreur envoi email contact: ' . $e->getMessage());
        }
    }

    /**
     * Envoie un email de confirmation à la personne qui a envoyé le message
     */
    public function sendContactConfirmation(ContactMessage $contactMessage): void
    {
        if (!$contactMessage->getEmail()) {
            return;
        }

        try {
            $email = (new TemplatedEmail())
                ->from(new Address('noreply@sara.fr', $this->appName))
                ->to(new Address($contactMessage->getEmail(), $contactMessage->getFirstName() . ' ' . $contactMessage->getLastName()))
                ->subject('Confirmation de réception de votre message - ' . $this->appName)
                ->htmlTemplate('emails/contact_confirmation.html.twig')
                ->context([
                    'contactMessage' => $contactMessage,
                    'appName' => $this->appName,
                ]);

            $this->mailer->send($email);
        } catch (\Exception $e) {
            // Log l'erreur mais ne bloque pas la sauvegarde du message
            error_log('Erreur envoi email confirmation contact: ' . $e->getMessage());
        }
    }
}

