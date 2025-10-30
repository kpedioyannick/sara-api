<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class FileStorageService
{
    private string $uploadsDirectory;
    private SluggerInterface $slugger;

    public function __construct(string $uploadsDirectory, SluggerInterface $slugger)
    {
        $this->uploadsDirectory = $uploadsDirectory;
        $this->slugger = $slugger;
    }

    /**
     * Upload un fichier et retourne le chemin relatif
     */
    public function uploadFile(UploadedFile $file, string $subDirectory = 'proofs'): string
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $fileName = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        try {
            $file->move($this->uploadsDirectory . '/' . $subDirectory, $fileName);
        } catch (FileException $e) {
            throw new \Exception('Erreur lors de l\'upload du fichier: ' . $e->getMessage());
        }

        return $subDirectory . '/' . $fileName;
    }

    /**
     * Sauvegarde un fichier base64
     */
    public function saveBase64File(string $base64Data, string $extension, string $subDirectory = 'proofs'): string
    {
        // Décoder le base64
        $fileData = base64_decode($base64Data);
        if ($fileData === false) {
            throw new \Exception('Données base64 invalides');
        }

        // Générer un nom de fichier unique
        $fileName = uniqid() . '_' . time() . '.' . $extension;
        $filePath = $this->uploadsDirectory . '/' . $subDirectory . '/' . $fileName;

        // Créer le dossier s'il n'existe pas
        $directory = dirname($filePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Sauvegarder le fichier
        if (file_put_contents($filePath, $fileData) === false) {
            throw new \Exception('Erreur lors de la sauvegarde du fichier');
        }

        return $subDirectory . '/' . $fileName;
    }

    /**
     * Génère une URL d'accès sécurisée pour un fichier
     */
    public function generateSecureUrl(string $filePath): string
    {
        // Pour la sécurité, on peut ajouter un token ou un hash
        $token = hash('sha256', $filePath . time());
        return '/api/files/' . $token . '/' . basename($filePath);
    }

    /**
     * Valide le type de fichier selon le type de preuve
     */
    public function validateFileType(string $mimeType, string $proofType): bool
    {
        $allowedTypes = [
            'image' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            'audio' => ['audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp4'],
            'video' => ['video/mp4', 'video/avi', 'video/mov', 'video/webm'],
            'document' => ['application/pdf', 'text/plain', 'application/msword'],
            'text' => ['text/plain', 'text/html']
        ];

        return in_array($mimeType, $allowedTypes[$proofType] ?? []);
    }

    /**
     * Valide la taille du fichier
     */
    public function validateFileSize(int $fileSize, string $proofType): bool
    {
        $maxSizes = [
            'image' => 5 * 1024 * 1024, // 5MB
            'audio' => 20 * 1024 * 1024, // 20MB
            'video' => 100 * 1024 * 1024, // 100MB
            'document' => 10 * 1024 * 1024, // 10MB
            'text' => 1 * 1024 * 1024 // 1MB
        ];

        return $fileSize <= ($maxSizes[$proofType] ?? 1024 * 1024);
    }

    /**
     * Supprime un fichier
     */
    public function deleteFile(string $filePath): bool
    {
        $fullPath = $this->uploadsDirectory . '/' . $filePath;
        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }
        return true;
    }

    /**
     * Lit le contenu d'un fichier
     */
    public function getFileContent(string $filePath): string
    {
        $fullPath = $this->uploadsDirectory . '/' . $filePath;
        if (!file_exists($fullPath)) {
            throw new \Exception('Fichier non trouvé');
        }
        return file_get_contents($fullPath);
    }

    /**
     * Vérifie si un fichier existe
     */
    public function fileExists(string $filePath): bool
    {
        $fullPath = $this->uploadsDirectory . '/' . $filePath;
        return file_exists($fullPath);
    }

    /**
     * Retourne le chemin complet d'un fichier
     */
    public function getFullPath(string $filePath): string
    {
        return $this->uploadsDirectory . '/' . $filePath;
    }
}
