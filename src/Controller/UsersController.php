<?php

namespace App\Controller;

use App\Entity\Users;
use App\Repository\CompanyRepository;
use App\Repository\UsersRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class UsersController extends AbstractController
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly TagAwareCacheInterface $cache,
    ) {
    }

    #[Route(
        'api/companies/{companyId}/users/{userId}',
        name: 'get_user_details_for_company',
        methods: ['GET']
    )]
    public function getUserDetailsForCompany(
        int $companyId,
        int $userId,
        UsersRepository $usersRepository,
        CompanyRepository $companyRepository,
    ): JsonResponse {
        $company = $companyRepository->find($companyId);

        if (!$company) {
            return $this->createErrorResponse('Company not found', Response::HTTP_NOT_FOUND);
        }

        $user = $usersRepository->find($userId);

        if (!$user || !$user->getCompanies()->contains($company)) {
            return $this->createErrorResponse('User not found or not linked to this company', Response::HTTP_NOT_FOUND);
        }

        $context = SerializationContext::create()->setGroups(['user:read']);
        $jsonContent = $this->serializer->serialize($user, 'json', $context);

        return new JsonResponse($jsonContent, Response::HTTP_OK, [], true);
    }

    #[Route('api/companies/{companyId}/users',
        name: 'add_user_to_company',
        methods: ['POST'])]
    public function addUserToCompany(
        int $companyId,
        Request $request,
        CompanyRepository $companyRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        $company = $companyRepository->find($companyId);
        if (!$company) {
            return $this->createErrorResponse('Company not found', Response::HTTP_NOT_FOUND);
        }
        $data = $request->getContent();

        try {
            $user = $this->serializer->deserialize($data, Users::class, 'json');
        } catch (\Exception $e) {
            return $this->createErrorResponse('Invalid data format', Response::HTTP_BAD_REQUEST);
        }

        $company->addUser($user);

        $errors = $this->getValidationErrors($user);
        if (!empty($errors)) {
            return new JsonResponse(['errors' => $errors], Response::HTTP_BAD_REQUEST);
        }

        if (null === $user->getCompanies()) {
            $user->setCompanies(new ArrayCollection());
        }

        $user->addCompany($company);
        $em->persist($user);
        $em->persist($company);
        $em->flush();

        $context = SerializationContext::create()->setGroups(['user:read']);
        $jsonContent = $this->serializer->serialize($user, 'json', $context);

        return new JsonResponse($jsonContent, Response::HTTP_CREATED, [], true);
    }

    #[Route(
        'api/company/{companyId}/users/{userId}',
        name: 'delete_user',
        methods: ['DELETE']
    )]
    public function deleteUser(
        int $companyId,
        int $userId,
        CompanyRepository $companyRepository,
        UsersRepository $usersRepository,
        EntityManagerInterface $em,
        Security $security,
    ): JsonResponse {
        $company = $companyRepository->find($companyId);
        $user = $usersRepository->find($userId);

        if (!$company || !$user) {
            return $this->createErrorResponse('Company or User not found', Response::HTTP_NOT_FOUND);
        }

        if (!$company->getUsers()->contains($user) && !$security->isGranted('ROLE_ADMIN')) {
            return $this->createErrorResponse('Access denied', Response::HTTP_FORBIDDEN);
        }

        $user->removeCompany($company);
        $em->persist($user);

        $em->flush();
        // if user.companies = 0
        if ($user->getCompanies()->isEmpty()) {
            $em->remove($user);
            $em->flush();
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function getValidationErrors($entity): array
    {
        $errors = $this->validator->validate($entity);
        if (count($errors) > 0) {
            return array_map(fn ($error) => $error->getMessage(), iterator_to_array($errors));
        }

        return [];
    }

    private function createErrorResponse(string $message, int $statusCode): JsonResponse
    {
        return new JsonResponse(['error' => $message], $statusCode);
    }
}
