<?php

namespace App\Controller;

use App\Entity\Company;
use App\Repository\CompanyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

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

    #[Route(
        '/api/companies',
        name: 'get_companies',
        methods: ['GET'],
    )]
    public function getCompanies(
        CompanyRepository $companyRepository,
        SerializerInterface $serializer,
    ): JsonResponse {
        $companies = $companyRepository->findAll();
        $jsonContent = $serializer->serialize($companies, 'json', ['groups' => 'company:read']);

        return new JsonResponse($jsonContent, Response::HTTP_OK, [], true);
    }

    #[Route(
        '/api/companies/{companyId}',
        name: 'get_company_detail',
        methods: ['GET'],
    )]
    public function getCompanyDetails(
        int $companyId,
        CompanyRepository $companyRepository,
        SerializerInterface $serializer,
    ): JsonResponse {
        $company = $companyRepository->find($companyId);
        if (!$company) {
            return new JsonResponse(['message' => 'Company not found'], Response::HTTP_NOT_FOUND);
        }
        $jsonContent = $serializer->serialize($company, 'json', ['groups' => 'company:read']);

        return new JsonResponse($jsonContent, Response::HTTP_OK, [], true);
    }

    #[Route(
        '/api/companies',
        name: 'create_company',
        methods: ['POST'],
    )]
    public function createCompany(
        Request $request,
        EntityManagerInterface $em,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        UserPasswordHasherInterface $passwordHasher,
    ): JsonResponse {
        $data = $request->getContent();
        $company = $serializer->deserialize($data, Company::class, 'json');

        $errors = $validator->validate($company);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }

            return new JsonResponse($errorMessages, Response::HTTP_BAD_REQUEST);
        }
        $company->setPassword(
            $passwordHasher->hashPassword($company, $company->getPassword())
        );
        $em->persist($company);
        $em->flush();

        $jsonContent = $serializer->serialize($company, 'json', ['groups' => 'company:read']);

        return new JsonResponse($jsonContent, Response::HTTP_CREATED, [], true);
    }

    #[Route(
        '/api/companies/{companyId}',
        name: 'update_company',
        methods: ['PATCH', 'PUT'],
    )]
    public function updateCompany(
        int $companyId,
        Request $request,
        CompanyRepository $companyRepository,
        EntityManagerInterface $em,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
    ): JsonResponse {
        $company = $companyRepository->find($companyId);
        if (!$company) {
            return new JsonResponse(['message' => 'Company not found'], Response::HTTP_NOT_FOUND);
        }
        $data = $request->getContent();
        $serializer->deserialize($data, Company::class, 'json', ['object_to_populate' => $company]);
        $errors = $validator->validate($company);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }

            return new JsonResponse(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $em->flush();

        $jsonContent = $serializer->serialize($company, 'json', ['groups' => 'company:read']);

        return new JsonResponse($jsonContent, Response::HTTP_OK, [], true);
    }

    #[Route(
        '/api/companies/{companyId}',
        name: 'delete_company',
        methods: ['DELETE'],
    )]
    public function deleteCompany(
        int $companyId,
        CompanyRepository $companyRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        $company = $companyRepository->find($companyId);
        if (!$company) {
            return new JsonResponse(['message' => 'Company not found'], Response::HTTP_NOT_FOUND);
        }
        $em->remove($company);
        $em->flush();

        return new JsonResponse(json_encode(['message' => 'Company deleted successfully']), Response::HTTP_OK, [], true);
    }
}
