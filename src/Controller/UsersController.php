<?php

namespace App\Controller;

use App\Repository\CompanyRepository;
use App\Repository\UsersRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

class UsersController extends AbstractController
{
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
        SerializerInterface $serializer,
    ): JsonResponse {
        $company = $companyRepository->find($companyId);

        if (!$company) {
            return new JsonResponse(['message' => 'Company not found'], Response::HTTP_NOT_FOUND);
        }

        $user = $usersRepository->find($userId);

        if (!$user || !$user->getCompanies()->contains($company)) {
            return new JsonResponse(['message' => 'User not found or not linked to this company'], Response::HTTP_NOT_FOUND);
        }

        $jsonContent = $serializer->serialize($user, 'json', ['groups' => 'user:read']);

        return new JsonResponse($jsonContent, Response::HTTP_OK, [], true);
    }
}
