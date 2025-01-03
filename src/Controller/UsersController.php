<?php

namespace App\Controller;

use App\Entity\Users;
use App\Repository\CompanyRepository;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

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

    #[Route('api/companies/{companyId}/users',
        name: 'add_user_to_company',
        methods: ['POST'])]
    public function addUserToCompany(
        int $companyId,
        Request $request,
        CompanyRepository $companyRepository,
        EntityManagerInterface $em,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
    ): JsonResponse {
        $company = $companyRepository->find($companyId);
        if (!$company) {
            throw $this->createNotFoundException('Company not found');
        }

        $data = $request->getContent();
        try {
            $user = $serializer->deserialize($data, Users::class, 'json');
        } catch (\Exception $e) {
            return new JsonResponse(
                ['error' => 'Invalid data format'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            return new JsonResponse(
                ['errors' => $errors],
                Response::HTTP_BAD_REQUEST
            );
        }

        $user->addCompany($company);

        $em->persist($user);
        $em->flush();

        return $this->json($user, Response::HTTP_CREATED, [], ['groups' => 'user:read']);
    }
}
