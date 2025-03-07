<?php

namespace App\Controller;

use App\Entity\Admin;
use App\Entity\Company;
use App\Entity\Users;
use App\Repository\CompanyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class CompanyController extends AbstractController
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly TagAwareCacheInterface $cache,
        private readonly Security $security,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Retrieves the list of users for a given company.
     *
     * @param CompanyRepository $companyRepository the repository for company data
     *
     * @return JsonResponse Json response containing the list of users
     *
     * @throws InvalidArgumentException If a caching error occurs
     */
    #[Route(
        '/api/companies/{companyId}/users',
        name: 'get_users_by_company',
        methods: ['GET'],
    )]
    #[OA\Get(
        description: "Cette route retourne la liste des utiisateurs d'une compagnie",
        summary: "Retourne la liste des utilisateurs d'une compagnie",
        tags: ['Company']
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des utilisateurs pour cette compagnie',
        content: new Model(type: Users::class, groups: ['user:read']),
    )]
    public function getCompanyUsers(
        int $companyId,
        CompanyRepository $companyRepository,
    ): JsonResponse {
        $currentAccount = $this->security->getToken()->getUser();

        if (!$currentAccount instanceof Company && !$currentAccount instanceof Admin) {
            return new JsonResponse(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }
        $company = $companyRepository->find($companyId);
        if (!$company) {
            return new JsonResponse(['message' => 'Company not found'], Response::HTTP_NOT_FOUND);
        }

        if ($currentAccount instanceof Company && $currentAccount->getId() !== $company->getId()) {
            return new JsonResponse(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }
        $cacheKey = 'getCompanyUsers-'.$companyId;
        try {
            $users = $this->cache->get($cacheKey, function (ItemInterface $item) use ($companyRepository, $companyId) {
                $item->tag('companiesCache');
                $company = $companyRepository->find($companyId);

                if (!$company) {
                    throw new \RuntimeException('Company not found');
                }
                $users = $company->getUsers();

                if ($users instanceof PersistentCollection && !$users->isInitialized()) {
                    $this->em->initializeObject($users);
                }

                return array_map(function ($user) use ($companyId) {
                    return [
                        'id' => $user->getId(),
                        'name' => $user->getName(),
                        'lastName' => $user->getLastName(),
                        'email' => $user->getEmail(),
                        '_links' => [
                            'user_delete' => [
                                'href' => "/api/companies/{$companyId}/users/{$user->getId()}",
                            ],
                            'user_detail' => [
                                'href' => "/api/companies/{$companyId}/users/{$user->getId()}",
                            ],
                        ],
                    ];
                }, $users->toArray());
            });

            $context = SerializationContext::create()->setGroups(['user:read']);
            $jsonContent = $this->serializer->serialize($users, 'json', $context);

            return new JsonResponse($jsonContent, Response::HTTP_OK, [], true);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * List of Companies.
     *
     * @throws InvalidArgumentException
     */
    #[Route(
        '/admin/companies',
        name: 'get_companies',
        methods: ['GET'],
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des companies',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'companyName', type: 'string', example: 'Bernhard-Brown'),
                    new OA\Property(property: 'webSite', type: 'string', example: 'http://example.com'),
                    new OA\Property(property: 'phone', type: 'string', example: '+1-901-961-8972'),
                    new OA\Property(property: 'users', type: 'array', items: new OA\Items(type: 'object')),
                    new OA\Property(property: '_links', properties: [
                        new OA\Property(property: 'company_detail', properties: [
                            new OA\Property(property: 'href', type: 'string', example: '/api/companies/1'),
                        ], type: 'object'),
                        new OA\Property(property: 'users', properties: [
                            new OA\Property(property: 'href', type: 'string', example: '/api/companies/1/users'),
                        ], type: 'object'),
                        new OA\Property(property: 'company_delete', properties: [
                            new OA\Property(property: 'href', type: 'string', example: '/api/companies/1'),
                        ], type: 'object'),
                    ], type: 'object'),
                ]
            )
        )
    )]
    #[OA\Get(
        description: 'Cette route retourne la liste des compagnies.',
        summary: 'Retourne la liste des compagnies',
        tags: ['Company']
    )]
    #[IsGranted('ROLE_ADMIN', message: 'Accès réservé aux administrateurs')]
    public function getCompanies(
        CompanyRepository $companyRepository,
    ): JsonResponse {
        $cacheKey = 'getCompanies';

        try {
            $companies = $this->cache->get($cacheKey, function (ItemInterface $item) use ($companyRepository) {
                $item->tag('companiesCache');

                return $companyRepository->findAll();
            });

            $context = SerializationContext::create()->setGroups(['company:list']);
            $jsonContent = $this->serializer->serialize($companies, 'json', $context);

            return new JsonResponse($jsonContent, Response::HTTP_OK, [], true);
        } catch (\Exception $e) {
            return new JsonResponse(['message' => 'Error while fetching companies'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Details of a company.
     *
     * @throws InvalidArgumentException
     */
    #[Route(
        '/api/companies/{companyId}',
        name: 'get_company_detail',
        methods: ['GET'],
    )]
    #[OA\Get(
        description: "Cette route retourne les informations d'une compagnie.",
        summary: "Retourne les informations d'une compagnie.",
        tags: ['Company']
    )]
    #[OA\Response(
        response: 200,
        description: 'Detail de la companie',
        content: new Model(type: Company::class, groups: ['company:read']),
    )]
    public function getCompanyDetails(
        int $companyId,
        CompanyRepository $companyRepository,
    ): JsonResponse {
        $currentAccount = $this->security->getToken()->getUser();
        if (!$currentAccount instanceof Admin && (!$currentAccount instanceof Company || $currentAccount->getId() !== $companyId)) {
            return new JsonResponse(['message' => 'Vous n\'avez pas les droits suffisants'], Response::HTTP_FORBIDDEN);
        }
        $cacheKey = 'getCompanyDetail-'.$companyId;

        try {
            $company = $this->cache->get($cacheKey, function (ItemInterface $item) use ($companyRepository, $companyId) {
                $item->tag('companiesCache');
                $company = $companyRepository->find($companyId);

                if (!$company) {
                    throw new \RuntimeException('Company not found');
                }

                return $company;
            });
            $context = SerializationContext::create()->setGroups(['company:read']);

            $jsonContent = $this->serializer->serialize($company, 'json', $context);

            return new JsonResponse($jsonContent, Response::HTTP_OK, [], true);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Create a new company.
     *
     * @throws InvalidArgumentException
     */
    #[Route(
        '/admin/companies',
        name: 'create_company',
        methods: ['POST'],
    )]
    #[OA\Post(
        description: 'Cette route crée une compagnie.',
        summary: "Création d'une compagnie.",
        tags: ['Company'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Companie créée avec succès',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/Company'
                )
            ),
        ]
    )]
    #[OA\RequestBody(
        description: 'Données requises pour créer une compagnie.',
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'email', type: 'string', example: 'exemple@email.com'),
                new OA\Property(property: 'password', type: 'string', example: 'MotDePasse'),
                new OA\Property(property: 'companyName', type: 'string', example: 'Nom de la compagnie.'),
                new OA\Property(property: 'webSite', type: 'string', example: 'https://exemple.html'),
                new OA\Property(property: 'phone', type: 'string', example: '06060606060'),
            ],
            type: 'object',
        )
    )]
    #[IsGranted('ROLE_ADMIN', message: 'Accès réservé aux administrateurs')]
    public function createCompany(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
    ): JsonResponse {
        $data = $request->getContent();
        $company = $this->serializer->deserialize($data, Company::class, 'json');

        $errors = $this->validator->validate($company);
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

        $company->setRoles(array_merge($company->getRoles()));
        $em->persist($company);
        $em->flush();
        $this->cache->invalidateTags(['companiesCache']);
        $this->cache->delete('getCompanyDetails-'.$company->getId());

        $context = SerializationContext::create()->setGroups(['company:read', 'user:write']);

        $jsonContent = $this->serializer->serialize($company, 'json', $context);

        return new JsonResponse([
            'message' => 'Company créé avec succès.',
            'company' => json_decode($jsonContent),
            'created_at' => $company->getCreatedAt()->format('Y-m-d H:i:s'),
            'company_id' => $company->getId(),
        ], Response::HTTP_CREATED);
    }

    /**
     * Deletes a company.
     *
     * @param int                    $companyId         The ID of the company to delete
     * @param CompanyRepository      $companyRepository The repository for company data
     * @param EntityManagerInterface $em                The Doctrine entity manager
     *
     * @return JsonResponse JSON response indicating success or failure
     *
     * @throws InvalidArgumentException If a caching error occurs
     */
    #[Route(
        '/admin/companies/{companyId}',
        name: 'delete_company',
        methods: ['DELETE'],
    )]
    #[OA\Delete(
        description: 'Cette route supprime une compagnie.',
        summary: "Suppression d'une compagnie.",
        tags: ['Company'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Compagnie supprimée avec succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Company successfully deleted'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Compagnie non trouvée',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Company not found'),
                    ]
                )
            ),
        ]
    )]
    #[IsGranted('ROLE_ADMIN', message: 'Accès réservé aux administrateurs')]
    public function deleteCompany(
        int $companyId,
        CompanyRepository $companyRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        $company = $companyRepository->find($companyId);
        if (!$company) {
            return new JsonResponse(['message' => 'Company not found'], Response::HTTP_NOT_FOUND);
        }
        /** @var Users[] $users List of users */
        $users = $company->getUsers();

        // If a user is only linked to this company, delete them to avoid orphaned users
        foreach ($users as $user) {
            if (1 === $user->getCompanies()->count()) {
                $em->remove($user);
            }
        }

        $em->remove($company);
        $em->flush();
        $this->cache->invalidateTags(['companiesCache']);
        $this->cache->delete('getCompanyDetails-'.$company->getId());

        return new JsonResponse(['message' => 'Company successfully deleted'], Response::HTTP_OK);
    }
}
