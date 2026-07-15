<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $adminEmail = 'admin@matchcv.local';
        $adminPassword = 'Admin123!';

        $existingAdmin = $manager->getRepository(User::class)->findOneBy(['email' => $adminEmail]);

        if ($existingAdmin === null) {
            $admin = new User();
            $admin
                ->setEmail($adminEmail)
                ->setRole('admin')
                ->setPassword($this->passwordHasher->hashPassword($admin, $adminPassword))
                ->setInscriptionStatus('complete')
                ->setTotpEnabled(false)
                ->setFaceIdEnabled(false);

            $manager->persist($admin);
        }

        $manager->flush();
    }
}
