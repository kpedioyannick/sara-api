<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\RouterInterface;

class EmailNotificationService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly RouterInterface $router,
        private readonly string $appName = 'SARA',
        private readonly ?string $baseUrl = null,
        private readonly ?string $ccEmail = null
    ) {
    }

    /**
     * Envoie un email pour une notification
     */
    public function sendNotificationEmail(Notification $notification): void
    {
        $recipient = $notification->getRecipient();
        
        // Vérifier que l'utilisateur a une adresse email valide
        if (!$recipient || !$recipient->getEmail()) {
            error_log('EmailNotificationService: Pas d\'email pour le destinataire (ID: ' . ($recipient?->getId() ?? 'null') . ')');
            return;
        }

        try {
            error_log('EmailNotificationService: Tentative d\'envoi d\'email à ' . $recipient->getEmail() . ' (' . $recipient->getFirstName() . ' ' . $recipient->getLastName() . ', ID: ' . $recipient->getId() . ') pour notification: ' . $notification->getTitle() . ' (Type: ' . $notification->getType() . ')');
            
            // Générer l'URL absolue si une URL relative est fournie
            $url = $notification->getUrl();
            if ($url && !str_starts_with($url, 'http')) {
                $url = $this->baseUrl ? rtrim($this->baseUrl, '/') . $url : $this->router->generate('admin_dashboard', [], RouterInterface::ABSOLUTE_URL);
            }

            $email = (new TemplatedEmail())
                ->from(new Address('noreply@sara.fr', $this->appName))
                ->to(new Address($recipient->getEmail(), $recipient->getFirstName() . ' ' . $recipient->getLastName()))
                ->subject($notification->getTitle())
                ->htmlTemplate('emails/notification.html.twig')
                ->context([
                    'notification' => $notification,
                    'recipient' => $recipient,
                    'appName' => $this->appName,
                    'url' => $url,
                ]);
            
            // Ajouter l'adresse en copie si configurée
            if ($this->ccEmail) {
                $email->cc(new Address($this->ccEmail, 'SARA - Copie'));
            }

            $this->mailer->send($email);
            $ccInfo = $this->ccEmail ? ' (CC: ' . $this->ccEmail . ')' : '';
            error_log('EmailNotificationService: ✅ Email envoyé avec succès à ' . $recipient->getEmail() . ' (' . $recipient->getFirstName() . ' ' . $recipient->getLastName() . ') - Sujet: ' . $notification->getTitle() . $ccInfo);
        } catch (\Exception $e) {
            // Log l'erreur mais ne bloque pas la création de la notification
            error_log('Erreur envoi email notification: ' . $e->getMessage() . ' - Trace: ' . $e->getTraceAsString());
        }
    }

    /**
     * Envoie un email pour une notification avec des données personnalisées
     */
    public function sendCustomNotificationEmail(
        User $recipient,
        string $subject,
        string $template,
        array $context = []
    ): void {
        if (!$recipient || !$recipient->getEmail()) {
            return;
        }

        try {
            $email = (new TemplatedEmail())
                ->from(new Address('noreply@sara.fr', $this->appName))
                ->to(new Address($recipient->getEmail(), $recipient->getFirstName() . ' ' . $recipient->getLastName()))
                ->subject($subject)
                ->htmlTemplate($template)
                ->context(array_merge([
                    'recipient' => $recipient,
                    'appName' => $this->appName,
                ], $context));
            
            // Ajouter l'adresse en copie si configurée
            if ($this->ccEmail) {
                $email->cc(new Address($this->ccEmail, 'SARA - Copie'));
            }

            $this->mailer->send($email);
        } catch (\Exception $e) {
            error_log('Erreur envoi email personnalisé: ' . $e->getMessage());
        }
    }
}

