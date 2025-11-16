<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-auth-token',
    description: 'Génère un token d\'authentification pour un utilisateur'
)]
class GenerateAuthTokenCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'Email ou pseudo de l\'utilisateur')
            ->addArgument('validity-days', InputArgument::OPTIONAL, 'Nombre de jours de validité du token (défaut: 30)', 30);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $username = $input->getArgument('username');
        $validityDays = (int) $input->getArgument('validity-days');

        $user = $this->userRepository->findByIdentifier($username);

        if (!$user) {
            $io->error(sprintf('Utilisateur "%s" non trouvé.', $username));
            return Command::FAILURE;
        }

        $token = $user->generateAuthToken($validityDays);
        $this->em->flush();

        $io->success(sprintf('Token généré pour %s (%s)', $user->getEmail(), $user->getFirstName() . ' ' . $user->getLastName()));
        $io->info('Token: ' . $token);
        $io->info('URL de connexion: /login/token?username=' . urlencode($username) . '&token=' . urlencode($token));
        $io->note('Le token expire le: ' . $user->getAuthTokenExpiresAt()->format('Y-m-d H:i:s'));

        return Command::SUCCESS;
    }
}

