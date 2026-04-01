<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\RegistrationRequest;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/auth')]
class AuthController extends AbstractController
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly ValidatorInterface $validator,
        private readonly UserRepository $userRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/register', name: 'api_auth_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $dto = new RegistrationRequest();
        $dto->email = trim((string) ($data['email'] ?? ''));
        $dto->password = (string) ($data['password'] ?? '');
        $dto->passwordConfirmation = (string) ($data['passwordConfirmation'] ?? '');

        $violations = $this->validator->validate($dto);

        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()][] = $violation->getMessage();
            }
            return $this->json(['error' => ['message' => 'Validation failed.', 'details' => $errors]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($dto->password !== $dto->passwordConfirmation) {
            return $this->json([
                'error' => [
                    'message' => 'Validation failed.',
                    'details' => ['passwordConfirmation' => ['Passwords do not match.']],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $existingUser = $this->userRepository->findOneBy(['email' => $dto->email]);

        if ($existingUser !== null) {
            // Avoid email enumeration: log and return the same success-like response.
            $this->logger->info('Registration attempted with already-registered email.', ['email' => $dto->email]);
            return $this->json(['message' => 'Registration successful.'], Response::HTTP_CREATED);
        }

        $user = new User();
        $user->setEmail($dto->email);
        $user->setRoles(['ROLE_PLAYER']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $dto->password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $token = $this->jwtManager->create($user);

        return $this->json(['token' => $token], Response::HTTP_CREATED);
    }

    #[Route('/me', name: 'api_auth_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'displayName' => $user->getDisplayName(),
            'roles' => $user->getRoles(),
        ]);
    }
}
