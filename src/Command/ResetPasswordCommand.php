<?php

namespace App\Command;

use App\Entity\Coach;
use App\Entity\ParentUser;
use App\Entity\Student;
use App\Entity\Specialist;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:reset-password',
    description: 'Réinitialise le mot de passe d\'un utilisateur',
)]
class ResetPasswordCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email de l\'utilisateur')
            ->addArgument('password', InputArgument::OPTIONAL, 'Nouveau mot de passe (défaut: password123)', 'password123');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = $input->getArgument('email');
        $newPassword = $input->getArgument('password');

        $output->writeln("🔐 Réinitialisation du mot de passe pour : {$email}");
        $output->writeln('');

        // Chercher dans toutes les entités utilisateur
        $user = null;
        $userType = null;

        // Coach
        $coach = $this->em->getRepository(Coach::class)->findOneBy(['email' => $email]);
        if ($coach) {
            $user = $coach;
            $userType = 'Coach';
        }

        // Parent
        if (!$user) {
            $parent = $this->em->getRepository(ParentUser::class)->findOneBy(['email' => $email]);
            if ($parent) {
                $user = $parent;
                $userType = 'Parent';
            }
        }

        // Student
        if (!$user) {
            $student = $this->em->getRepository(Student::class)->findOneBy(['email' => $email]);
            if ($student) {
                $user = $student;
                $userType = 'Student';
            }
        }

        // Specialist
        if (!$user) {
            $specialist = $this->em->getRepository(Specialist::class)->findOneBy(['email' => $email]);
            if ($specialist) {
                $user = $specialist;
                $userType = 'Specialist';
            }
        }

        if (!$user) {
            $output->writeln("❌ Aucun utilisateur trouvé avec l'email : {$email}");
            return Command::FAILURE;
        }

        try {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);
            $this->em->flush();

            $output->writeln("✅ Mot de passe réinitialisé avec succès pour {$userType}");
            $output->writeln("   Email: {$email}");
            $output->writeln("   Nouveau mot de passe: {$newPassword}");
            $output->writeln('');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln("❌ Erreur lors de la réinitialisation : " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

