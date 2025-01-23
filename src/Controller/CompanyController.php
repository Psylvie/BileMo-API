<?php

namespace App\Controller;

use App\Entity\Company;
use App\Repository\CompanyRepository;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route(
        '/api/companies/{companyId}/users',
        name: 'get_users_by_company',
        methods: ['GET'],
    )]
    public function getCompanyUsers(
        int $companyId,
        CompanyRepository $companyRepository,
    ): JsonResponse {
        $cacheKey = 'getCompanyUsers-'.$companyId;
        try {
            $users = $this->cache->get($cacheKey, function (ItemInterface $item) use ($companyRepository, $companyId) {
                $item->tag('companiesCache');
                $company = $companyRepository->find($companyId);

                if (!$company) {
                    throw new \RuntimeException('Company not found');
                }

                return $company->getUsers();
            });

            $context = SerializationContext::create()->setGroups(['user:read', 'company:read']);
            $jsonContent = $this->serializer->serialize($users, 'json', $context);

            return new JsonResponse($jsonContent, Response::HTTP_OK, [], true);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route(
        '/api/companies',
        name: 'get_companies',
        methods: ['GET'],
    )]
    public function getCompanies(
        CompanyRepository $companyRepository,
    ): JsonResponse {
        $cacheKey = 'getCompanies';

        try {
            $companies = $this->cache->get($cacheKey, function (ItemInterface $item) use ($companyRepository) {
                $item->tag('companiesCache');

                return $companyRepository->findAll();
            });

            $context = SerializationContext::create()->setGroups(['company:read']);
            $jsonContent = $this->serializer->serialize($companies, 'json', $context);

            return new JsonResponse($jsonContent, Response::HTTP_OK, [], true);
        } catch (\Exception $e) {
            return new JsonResponse(['message' => 'Error while fetching companies'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(
        '/api/companies/{companyId}',
        name: 'get_company_detail',
        methods: ['GET'],
    )]
    public function getCompanyDetails(
        int $companyId,
        CompanyRepository $companyRepository,
    ): JsonResponse {
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
     * @throws InvalidArgumentException
     */
    #[Route(
        '/api/companies',
        name: 'create_company',
        methods: ['POST'],
    )]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants')]
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
        $em->persist($company);
        $em->flush();
        $this->cache->invalidateTags(['companiesCache']);

        $context = SerializationContext::create()->setGroups(['company:read']);

        $jsonContent = $this->serializer->serialize($company, 'json', $context);

        return new JsonResponse($jsonContent, Response::HTTP_CREATED, [], true);
    }

    /**
     * @throws InvalidArgumentException
     */
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
        $this->cache->invalidateTags(['companiesCache']);

        return new JsonResponse(json_encode(['message' => 'Company deleted successfully']), Response::HTTP_OK, [], true);
    }
}
