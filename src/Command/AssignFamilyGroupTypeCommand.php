<?php

namespace App\Command;

use App\Entity\Family;
use App\Enum\FamilyType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:families:assign-group-type',
    description: 'Assign the GROUP type to all families that are missing a type',
)]
class AssignFamilyGroupTypeCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Obtenir le nom de la table depuis les métadonnées Doctrine
        $metadata = $this->entityManager->getClassMetadata(Family::class);
        $tableName = $metadata->getTableName();

        // Utiliser une requête SQL brute pour trouver les familles avec type NULL ou chaîne vide
        $connection = $this->entityManager->getConnection();
        $platform = $connection->getDatabasePlatform();
        $quotedTableName = $platform->quoteIdentifier($tableName);
        
        // Compter d'abord
        $countQuery = "
            SELECT COUNT(*) as count 
            FROM {$quotedTableName} 
            WHERE type IS NULL OR type = '' OR type NOT IN ('FAMILY', 'GROUP')
        ";
        $result = $connection->executeQuery($countQuery);
        $row = $result->fetchAssociative();
        $count = (int) $row['count'];

        if ($count === 0) {
            $io->success('All families already have a valid type.');

            return Command::SUCCESS;
        }

        $io->info(sprintf('Found %d family(ies) with invalid or missing type.', $count));

        // Mettre à jour directement en SQL pour éviter les erreurs d'enum
        $updateQuery = "
            UPDATE {$quotedTableName} 
            SET type = :groupType 
            WHERE type IS NULL OR type = '' OR type NOT IN ('FAMILY', 'GROUP')
        ";
        $connection->executeStatement($updateQuery, ['groupType' => FamilyType::GROUP->value]);

        // Rafraîchir le cache de Doctrine
        $this->entityManager->clear();

        $io->success(sprintf('%d family(ies) have been updated to type GROUP.', $count));

        return Command::SUCCESS;
    }
}

