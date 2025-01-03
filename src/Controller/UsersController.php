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

    #[Route(
        'api/users/{userId}',
        name: 'update_user',
        methods: ['PATCH', 'PUT']
    )]
    public function updateUser(
        int $userId,
        Request $request,
        UsersRepository $usersRepository,
        EntityManagerInterface $em,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
    ): JsonResponse {
        $user = $usersRepository->find($userId);
        if (!$user) {
            return new JsonResponse(['message' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $data = $request->getContent();
        $updatedUser = $serializer->deserialize($data, Users::class, 'json', ['object_to_populate' => $user]);

        $errors = $validator->validate($updatedUser);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }

            return new JsonResponse(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $em->flush();
        $jsonContent = $serializer->serialize($updatedUser, 'json', ['groups' => 'user:read']);

        return new JsonResponse($jsonContent, Response::HTTP_OK, [], true);
    }
}
