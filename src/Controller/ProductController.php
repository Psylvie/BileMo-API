<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

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
}
