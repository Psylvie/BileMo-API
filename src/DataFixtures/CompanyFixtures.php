<?php

namespace App\DataFixtures;

use App\Entity\Company;
use App\Entity\Users;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CompanyFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create();

        $commonPassword = 'password123';
        for ($i = 0; $i < 20; ++$i) {
            $company = new Company();
            $hashedPassword = $this->passwordHasher->hashPassword($company, $commonPassword);
            $company->setCompanyName($faker->company)
                ->setEmail($faker->email)
                ->setPhone($faker->phoneNumber)
                ->setWebSite($faker->url)
                ->setPassword($hashedPassword)
                ->setRoles(['ROLE_COMPANY']);

            for ($k = 0; $k < rand(1, 10); ++$k) {
                $user = new Users();
                $user->setEmail('user'.$i.'_'.$k.'@example.com')
                    ->setName($faker->firstName)
                    ->setLastName($faker->lastName);

                $user->addCompany($company);
                $user->initializeTimestampable();
                $manager->persist($user);
            }

            $company->initializeTimestampable();
            $manager->persist($company);
        }

        $manager->flush();
    }
}
