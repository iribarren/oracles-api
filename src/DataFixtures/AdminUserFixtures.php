<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Seeds the users table with a default admin account for local development.
 *
 * Run with: docker compose exec backend-php php bin/console doctrine:fixtures:load
 *
 * WARNING: Do not run in production — fixtures:load purges all tables first.
 */
class AdminUserFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {}

    public function load(ObjectManager $manager): void
    {
        $user = new User();
        $user->setEmail('admin@biblioteca.local');
        $user->setRoles(['ROLE_ADMIN']);
        $user->setPassword(
            $this->passwordHasher->hashPassword($user, 'admin123')
        );

        $manager->persist($user);
        $manager->flush();
    }
}
