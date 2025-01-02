<?php

namespace App\Controller;

use App\Repository\CompanyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;


class CompanyController extends AbstractController
{
    #[Route(
        '/api/companies/{companyId}/users',
        name: 'get_users_by_company',
        methods: ['GET'],
    )]
    public function getCompanyUsers(int $companyId, CompanyRepository $companyRepository, SerializerInterface $serializer): JsonResponse
    {
        $company = $companyRepository->find($companyId);

        if (!$company) {
            return new JsonResponse(['message' => 'Company not found'], Response::HTTP_NOT_FOUND);
        }

        $users = $company->getUsers();

        $jsonContent = $serializer->serialize($users, 'json', ['groups' => 'user:read']);

        return new JsonResponse($jsonContent, Response::HTTP_OK, [], true);
    }
}
