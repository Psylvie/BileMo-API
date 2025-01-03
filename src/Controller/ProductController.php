<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ProductController extends AbstractController
{
    #[Route(
        '/api/products',
        name: 'get_products',
        methods: ['GET']
    )]
    public function getProducts(
        ProductRepository $productRepository,
        SerializerInterface $serializer,
        PaginatorInterface $paginator,
        Request $request,
    ): JsonResponse {
        $query = $productRepository->createQueryBuilder('p')->getQuery();

        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);

        // if limit = 0 or if is too big all the products
        if (0 === $limit || $limit > 1000) {
            $products = $productRepository->findAll();
            $jsonContent = $serializer->serialize($products, 'json', ['groups' => 'product:read']);

            return new JsonResponse($jsonContent, Response::HTTP_OK, [], true);
        }

        // Pagination
        $pagination = $paginator->paginate(
            $query,
            $page,
            $limit
        );

        $jsonContent = $serializer->serialize($pagination->getItems(), 'json', ['groups' => 'product:read']);

        return new JsonResponse($jsonContent, Response::HTTP_OK, [
            'X-Total-Count' => $pagination->getTotalItemCount(),
            'X-Page' => $page,
        ], true);
    }

    #[Route(
        '/api/products/{id}',
        name: 'get_product_detail',
        methods: ['GET']
    )]
    public function getProductDetail(
        int $id,
        ProductRepository $productRepository,
        SerializerInterface $serializer,
    ): JsonResponse {
        $product = $productRepository->find($id);

        if (!$product) {
            throw new NotFoundHttpException("Produit avec l'ID $id non trouvé.");
        }

        $jsonContent = $serializer->serialize($product, 'json', ['groups' => 'product:read']);

        return new JsonResponse($jsonContent, Response::HTTP_OK, [], true);
    }

    #[Route(
        '/api/products',
        name: 'create_product',
        methods: ['POST']
    )]
    public function createProduct(
        Request $request,
        SerializerInterface $serializer,
        ProductRepository $productRepository,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
    ): JsonResponse {
        $data = $request->getContent();
        $product = $serializer->deserialize($data, Product::class, 'json');

        $errors = $validator->validate($product);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }

            return new JsonResponse(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }
        $em->persist($product);
        $em->flush();

        $jsonContent = $serializer->serialize($product, 'json', ['groups' => 'product:read']);

        return new JsonResponse($jsonContent, Response::HTTP_CREATED, [], true);
    }

    #[Route(
        '/api/products/{id}',
        name: 'patch_product',
        methods: ['PATCH']
    )]
    public function updateProduct(
        int $id,
        Request $request,
        ProductRepository $productRepository,
        SerializerInterface $serializer,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
    ): JsonResponse {
        $product = $productRepository->find($id);
        if (!$product) {
            return new JsonResponse(['message' => 'Produit non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        $data = $request->getContent();
        $serializer->deserialize($data, Product::class, 'json', [AbstractNormalizer::OBJECT_TO_POPULATE => $product]);

        $errors = $validator->validate($product);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }

            return new JsonResponse(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $em->flush();

        $jsonContent = $serializer->serialize($product, 'json', ['groups' => 'product:read']);

        return new JsonResponse($jsonContent, Response::HTTP_OK, [], true);
    }

    #[Route(
        '/api/products/{id}',
        name: 'delete_product',
        methods: ['DELETE']
    )]
    public function deleteProduct(
        int $id,
        ProductRepository $productRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        $product = $productRepository->find($id);
        if (!$product) {
            return new JsonResponse(['message' => 'Produit non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        $em->remove($product);
        $em->flush();

        return new JsonResponse(['message' => 'Produit supprimé avec succès.'], Response::HTTP_NO_CONTENT);
    }
}
