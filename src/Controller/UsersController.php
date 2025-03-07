<?php

namespace App\Controller;

use App\Entity\Admin;
use App\Entity\Company;
use App\Entity\Users;
use App\Repository\CompanyRepository;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UsersController extends AbstractController
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly Security $security,
    ) {
    }

    #[Route(
        'api/companies/{companyId}/users/{userId}',
        name: 'get_user_details_for_company',
        methods: ['GET']
    )]
    #[OA\Response(
        response: 200,
        description: "Détails d'un utilisateur d'une compagnie",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'id', type: 'integer', example: 69),
                new OA\Property(property: 'name', type: 'string', example: 'Estelle'),
                new OA\Property(property: 'lastName', type: 'string', example: "O'Conner"),
                new OA\Property(property: 'email', type: 'string', example: 'user19_0@example.com'),
                new OA\Property(property: '_links', properties: [
                    new OA\Property(property: 'user_delete', properties: [
                        new OA\Property(property: 'href', type: 'string', example: '/api/companies/20/users/69'),
                    ], type: 'object'),
                    new OA\Property(property: 'user_detail', properties: [
                        new OA\Property(property: 'href', type: 'string', example: '/api/companies/20/users/69'),
                    ], type: 'object'),
                ], type: 'object'),
            ],
            type: 'object'
        )
    )]
    #[OA\Get(
        description: "Cette route retourne le detail d'un utililsateur d'une companie",
        summary: "Retourne le detail d'un utililsateur d'une companie",
        tags: ['Users']
    )]
    public function getUserDetailsForCompany(
        int $companyId,
        int $userId,
        UsersRepository $usersRepository,
        CompanyRepository $companyRepository,
        Security $security,
    ): JsonResponse {
        $currentAccount = $security->getUser();

        if (!$currentAccount instanceof Company && !$currentAccount instanceof Admin) {
            return $this->createErrorResponse('Access denied', Response::HTTP_FORBIDDEN);
        }

        $company = $companyRepository->find($companyId);
        if (!$company) {
            return $this->createErrorResponse('Company not found', Response::HTTP_NOT_FOUND);
        }

        if ($currentAccount instanceof Company && $currentAccount->getId() !== $company->getId()) {
            return $this->createErrorResponse('Access denied', Response::HTTP_FORBIDDEN);
        }

        $user = $usersRepository->find($userId);
        if (!$user) {
            return $this->createErrorResponse('User not found', Response::HTTP_NOT_FOUND);
        }

        if (!$user->getCompanies()->contains($company)) {
            return $this->createErrorResponse('User not associated with this company', Response::HTTP_FORBIDDEN);
        }

        $context = SerializationContext::create()->setGroups(['user:read']);
        $jsonContent = $this->serializer->serialize($user, 'json', $context);

        return new JsonResponse($jsonContent, Response::HTTP_OK, [], true);
    }

    #[Route('api/companies/{companyId}/users',
        name: 'add_user_to_company',
        methods: ['POST'])]
    #[OA\Post(
        description: 'Cette route ajoute un utililsateur a une companie',
        summary: "Ajout d'un utililsateur a une companie",
        tags: ['Users']
    )]
    #[OA\Response(
        response: 201,
        description: 'Utilisateur ajouté avec succès.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'id', type: 'integer', example: 70),
                new OA\Property(property: 'name', type: 'string', example: "Nom de l'utilisateur"),
                new OA\Property(property: 'lastName', type: 'string', example: "Prénom de l'utilisateur"),
                new OA\Property(property: 'email', type: 'string', example: 'exempledetest@email.com'),
                new OA\Property(property: '_links', properties: [
                    new OA\Property(property: 'user_delete', properties: [
                        new OA\Property(property: 'href', type: 'string', example: '/api/companies/3/users/70'),
                    ], type: 'object'),
                    new OA\Property(property: 'user_detail', properties: [
                        new OA\Property(property: 'href', type: 'string', example: '/api/companies/3/users/70'),
                    ], type: 'object'),
                ], type: 'object'),
            ],
            type: 'object'
        )
    )]
    #[OA\RequestBody(
        description: 'Données requises pour ajouter un utililsateur.',
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'email', type: 'string', example: 'exemple@email.com'),
                new OA\Property(property: 'lastName', type: 'string', example: "Prénom de l'utilisateur"),
                new OA\Property(property: 'name', type: 'string', example: "Nom de l'utilisateur"),
            ],
            type: 'object',
        )
    )]
    public function addUserToCompany(
        int $companyId,
        Request $request,
        CompanyRepository $companyRepository,
        EntityManagerInterface $em,
        UsersRepository $usersRepository,
    ): JsonResponse {
        $currentAccount = $this->security->getToken()->getUser();

        if (!$currentAccount instanceof Company && !$currentAccount instanceof Admin) {
            return new JsonResponse(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $company = $companyRepository->find($companyId);
        if (!$company) {
            return $this->createErrorResponse('Company not found', Response::HTTP_NOT_FOUND);
        }

        if ($currentAccount instanceof Company && $currentAccount->getId() !== $company->getId()) {
            return new JsonResponse(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->getContent();

        try {
            $user = $this->serializer->deserialize($data, Users::class, 'json');
        } catch (\Exception $e) {
            return $this->createErrorResponse('Invalid data format', Response::HTTP_BAD_REQUEST);
        }

        $existingUser = $usersRepository->findOneBy(['email' => $user->getEmail()]);

        if ($existingUser && $existingUser->getCompanies()->contains($company)) {
            return new JsonResponse(['message' => 'User is already associated with this company'], Response::HTTP_CONFLICT);
        }

        if (!$existingUser) {
            $existingUser = new Users();
            $existingUser->setEmail($user->getEmail());
            $existingUser->setName($user->getName());
            $existingUser->setLastName($user->getLastName());
            $em->persist($existingUser);
        }

        $existingUser->addCompany($company);
        $company->addUser($existingUser);

        $errors = $this->getValidationErrors($existingUser);
        if (!empty($errors)) {
            return new JsonResponse(['errors' => $errors], Response::HTTP_BAD_REQUEST);
        }

        $em->flush();

        $context = SerializationContext::create()->setGroups(['user:read']);
        $jsonContent = $this->serializer->serialize($existingUser, 'json', $context);

        return new JsonResponse($jsonContent, Response::HTTP_CREATED, [], true);
    }

    #[Route(
        'api/company/{companyId}/users/{userId}',
        name: 'delete_user',
        methods: ['DELETE']
    )]
    #[OA\Response(
        response: 200,
        description: 'Utilisateur supprimé avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'User successfully deleted'),
            ],
            type: 'object'
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Entreprise ou utilisateur non trouvé',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Company or User not found'),
            ],
            type: 'object'
        )
    )]
    #[OA\Response(
        response: 403,
        description: "Accès refusé si l'utilisateur n'appartient pas à la compagnie",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Access denied'),
            ],
            type: 'object'
        )
    )]
    #[OA\Delete(
        description: "Cette route supprime un utililsateur d'une companie",
        summary: "Supprime un utililsateur d'une companie",
        tags: ['Users']
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
        $em->flush();
        if ($user->getCompanies()->isEmpty()) {
            $em->remove($user);
        }

        $em->flush();

        return new JsonResponse(['message' => 'User successfully deleted'], Response::HTTP_OK);
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
