<?php

namespace App\Service;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Database;

class FirebaseService
{
    private ?Database $database = null;
    private ?\Kreait\Firebase\Messaging $messaging = null;

    public function __construct(
        private readonly ?string $projectId = null,
        private readonly ?string $privateKey = null,
        private readonly ?string $clientEmail = null,
        private readonly ?string $databaseUrl = null
    ) {
        // Les credentials peuvent être null si Firebase n'est pas encore configuré
        if ($this->projectId && $this->privateKey && $this->clientEmail) {
            $this->initialize();
        }
    }

    private function initialize(): void
    {
        try {
            if (!$this->projectId || !$this->privateKey || !$this->clientEmail) {
                error_log('Firebase: Credentials manquantes - PROJECT_ID: ' . ($this->projectId ? 'OK' : 'MANQUANT') . ', PRIVATE_KEY: ' . ($this->privateKey ? 'OK (' . strlen($this->privateKey) . ' chars)' : 'MANQUANT') . ', CLIENT_EMAIL: ' . ($this->clientEmail ? 'OK' : 'MANQUANT'));
                return;
            }

            // Nettoyer la clé privée (enlever les \n littéraux et les remplacer par de vrais retours à la ligne)
            $privateKey = str_replace('\\n', "\n", $this->privateKey);
            
            // Vérifier que la clé commence bien par -----BEGIN
            if (strpos($privateKey, '-----BEGIN') === false) {
                error_log('Firebase: Format de clé privée invalide (ne commence pas par -----BEGIN)');
                return;
            }

            $factory = (new Factory())
                ->withServiceAccount([
                    'type' => 'service_account',
                    'project_id' => $this->projectId,
                    'private_key' => $privateKey,
                    'client_email' => $this->clientEmail,
                ]);

            if ($this->databaseUrl) {
                $this->database = $factory->withDatabaseUri($this->databaseUrl)->createDatabase();
                error_log('Firebase: Database initialisée avec succès - URL: ' . $this->databaseUrl);
            } else {
                error_log('Firebase: DATABASE_URL manquant');
            }

            $this->messaging = $factory->createMessaging();
        } catch (\Exception $e) {
            error_log('Erreur initialisation Firebase: ' . $e->getMessage() . ' - Trace: ' . $e->getTraceAsString());
        }
    }

    /**
     * Publie un message dans Firebase Realtime Database
     * @return string|null La clé du message publié, ou null en cas d'erreur
     */
    public function publishMessage(string $path, array $data): ?string
    {
        if (!$this->database) {
            error_log('Firebase Database non initialisé - Vérifiez FIREBASE_PRIVATE_KEY, FIREBASE_CLIENT_EMAIL et FIREBASE_DATABASE_URL dans .env');
            return null;
        }

        try {
            $reference = $this->database->getReference($path);
            $result = $reference->push($data);
            $key = $result->getKey();
            error_log('Firebase: Message publié avec succès dans ' . $path . ' (key: ' . $key . ')');
            error_log('Firebase: Données publiées: ' . json_encode($data, JSON_UNESCAPED_UNICODE));
            return $key; // Retourner la clé pour vérification
        } catch (\Exception $e) {
            error_log('Erreur publication Firebase: ' . $e->getMessage() . ' (path: ' . $path . ')');
            error_log('Firebase: Trace: ' . $e->getTraceAsString());
            throw $e; // Relancer l'exception pour que le contrôleur puisse la gérer
        }
    }

    /**
     * Met à jour une valeur dans Firebase Realtime Database
     */
    public function updateValue(string $path, $value): void
    {
        if (!$this->database) {
            error_log('Firebase Database non initialisé');
            return;
        }

        try {
            $reference = $this->database->getReference($path);
            $reference->set($value);
        } catch (\Exception $e) {
            error_log('Erreur mise à jour Firebase: ' . $e->getMessage());
        }
    }

    /**
     * Envoie une notification push via Firebase Cloud Messaging
     */
    public function sendPushNotification(string $token, string $title, string $body, array $data = []): void
    {
        if (!$this->messaging) {
            error_log('Firebase Messaging non initialisé');
            return;
        }

        try {
            $notification = Notification::create($title, $body);
            $message = CloudMessage::withTarget('token', $token)
                ->withNotification($notification)
                ->withData($data);

            $this->messaging->send($message);
        } catch (\Exception $e) {
            error_log('Erreur envoi notification push Firebase: ' . $e->getMessage());
        }
    }

    /**
     * Vérifie si Firebase est configuré
     */
    public function isConfigured(): bool
    {
        return $this->database !== null || $this->messaging !== null;
    }

    /**
     * Récupère l'instance de la base de données (pour les commandes de nettoyage)
     */
    public function getDatabase(): ?Database
    {
        return $this->database;
    }
}

