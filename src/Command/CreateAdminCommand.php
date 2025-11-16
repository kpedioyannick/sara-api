<?php

namespace App\Command;

use App\Entity\Admin;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Crée un utilisateur administrateur'
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email de l\'administrateur')
            ->addArgument('password', InputArgument::REQUIRED, 'Mot de passe de l\'administrateur')
            ->addArgument('firstName', InputArgument::REQUIRED, 'Prénom de l\'administrateur')
            ->addArgument('lastName', InputArgument::REQUIRED, 'Nom de l\'administrateur')
            ->addOption('generate-token', 't', InputOption::VALUE_NONE, 'Générer un token d\'authentification')
            ->addOption('validity-days', 'd', InputOption::VALUE_OPTIONAL, 'Nombre de jours de validité du token (défaut: 30)', 30);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $email = $input->getArgument('email');
        $password = $input->getArgument('password');
        $firstName = $input->getArgument('firstName');
        $lastName = $input->getArgument('lastName');
        $generateToken = $input->getOption('generate-token');
        $validityDays = (int) $input->getOption('validity-days');

        // Vérifier si un utilisateur avec cet email existe déjà
        $existingUser = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            $io->error(sprintf('Un utilisateur avec l\'email "%s" existe déjà.', $email));
            return Command::FAILURE;
        }

        // Créer l'admin
        $admin = new Admin();
        $admin->setEmail($email);
        $admin->setFirstName($firstName);
        $admin->setLastName($lastName);
        $admin->setIsActive(true);
        
        $hashedPassword = $this->passwordHasher->hashPassword($admin, $password);
        $admin->setPassword($hashedPassword);

        // Validation
        $errors = $this->validator->validate($admin);
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $io->error($error->getPropertyPath() . ': ' . $error->getMessage());
            }
            return Command::FAILURE;
        }

        // Générer un token si demandé
        $token = null;
        $loginUrl = null;
        if ($generateToken) {
            $token = $admin->generateAuthToken($validityDays);
            $loginUrl = sprintf(
                '/login/token?username=%s&token=%s',
                urlencode($email),
                urlencode($token)
            );
        }

        // Sauvegarder
        $this->em->persist($admin);
        $this->em->flush();

        $io->success(sprintf('Administrateur créé avec succès !'));
        $io->table(
            ['Champ', 'Valeur'],
            [
                ['ID', $admin->getId()],
                ['Email', $admin->getEmail()],
                ['Prénom', $admin->getFirstName()],
                ['Nom', $admin->getLastName()],
                ['Rôle', 'ROLE_ADMIN'],
                ['Actif', $admin->isActive() ? 'Oui' : 'Non'],
            ]
        );

        if ($generateToken && $token) {
            $io->section('Token d\'authentification généré');
            $io->info('Token: ' . $token);
            $io->info('URL de connexion: ' . $loginUrl);
            $io->note('Le token expire le: ' . $admin->getAuthTokenExpiresAt()->format('Y-m-d H:i:s'));
        }

        return Command::SUCCESS;
    }
}

