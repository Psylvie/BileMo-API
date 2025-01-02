<?php

namespace App\Command;

use App\Entity\Admin;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Create a new Admin in the database',
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordEncoder,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Create a new Admin')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $io->ask('Email de l\'admin');
        $name = $io->ask('Prénom de l\'admin');
        $lastName = $io->ask('Nom de l\'admin');
        $plainPassword = $io->askHidden('Mot de passe (saisie masquée)');

        $errors = $this->validateInputs($email, $plainPassword);
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $io->error($error);
            }

            return Command::FAILURE;
        }

        $existingAdmin = $this->em->getRepository(Admin::class)->findOneBy(['email' => $email]);
        if ($existingAdmin) {
            $io->error("Un admin avec l'email $email existe déjà.");

            return Command::FAILURE;
        }

        try {
            $admin = new Admin();
            $admin->setEmail($email);
            $admin->setName($name);
            $admin->setLastName($lastName);

            $hashedPassword = $this->passwordEncoder->hashPassword($admin, $plainPassword);
            $admin->setPassword($hashedPassword);

            $admin->setRoles(['ROLE_ADMIN']);

            $this->em->persist($admin);
            $this->em->flush();

            $io->success("L'admin a été créé avec succès.");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("Une erreur est survenue lors de la création de l'admin : ".$e->getMessage());

            return Command::FAILURE;
        }
    }

    private function validateInputs(string $email, string $plainPassword): array
    {
        $errors = [];

        $emailErrors = $this->validator->validate($email, new Assert\Email());
        if (count($emailErrors) > 0) {
            $errors[] = "L'email fourni n'est pas valide.";
        }

        $passwordErrors = $this->validator->validate($plainPassword, new Assert\Regex([
            'pattern' => '/^(?=.*[A-Z])(?=.*[0-9])(?=.*[\W_]).{8,}$/',
            'message' => 'Le mot de passe doit contenir au moins 8 caractères, une lettre majuscule, un chiffre et un caractère spécial.',
        ]));
        if (count($passwordErrors) > 0) {
            foreach ($passwordErrors as $passwordError) {
                $errors[] = $passwordError->getMessage();
            }
        }

        return $errors;
    }
}
