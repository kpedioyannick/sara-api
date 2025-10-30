<?php

namespace App\Controller;

use App\Service\FileStorageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/files')]
class FileController extends AbstractController
{
    public function __construct(
        private FileStorageService $fileStorageService
    ) {}

    #[Route('/{token}/{filename}', name: 'file_download', methods: ['GET'])]
    public function downloadFile(string $token, string $filename, Request $request): Response
    {
        try {
            // Ici on pourrait valider le token pour la sécurité
            // Pour l'instant, on accepte tous les tokens
            
            // Construire le chemin du fichier
            $filePath = 'proofs/' . $filename;
            
            // Vérifier que le fichier existe
            if (!$this->fileStorageService->fileExists($filePath)) {
                throw new \Exception('Fichier non trouvé');
            }

            // Retourner le fichier
            $fullPath = $this->fileStorageService->getFullPath($filePath);
            
            $response = new BinaryFileResponse($fullPath);
            $response->setContentDisposition('inline', $filename);
            
            return $response;

        } catch (\Exception $e) {
            return new Response('Fichier non trouvé', Response::HTTP_NOT_FOUND);
        }
    }
}
