<?php

namespace App\MessageHandler;

use App\Entity\Path\Path;
use App\Message\GeneratePathMessage;
use App\Repository\PathRepository;
use App\Service\Path\PathGenerationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class GeneratePathMessageHandler
{
    public function __construct(
        private readonly PathRepository $pathRepository,
        private readonly PathGenerationService $pathGenerationService,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(GeneratePathMessage $message): void
    {
        $path = $this->pathRepository->find($message->getPathId());
        
        if (!$path) {
            $this->logger->error("Path not found for ID: {$message->getPathId()}");
            return;
        }

        try {
            // Mettre à jour le statut à "generating" si nécessaire
            if ($path->getStatus() === Path::STATUS_DRAFT) {
                // Le statut sera mis à jour par PathGenerationService
            }

            // Générer le contenu H5P
            $this->pathGenerationService->generatePathContent(
                $path,
                $message->getModules(),
                $message->getChapterPrompts(),
                $message->getSubChapterPrompts()
            );

            // Le statut sera mis à "generated" par PathGenerationService
            $this->logger->info("Path {$path->getId()} generated successfully");

        } catch (\Exception $e) {
            $this->logger->error("Error generating path {$path->getId()}: " . $e->getMessage());
            
            // Mettre à jour le statut à "draft" avec une note d'erreur
            $path->setStatus(Path::STATUS_DRAFT);
            $this->em->flush();
            
            throw $e;
        }
    }
}

