<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class ProductController extends AbstractController
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
        '/api/products',
        name: 'get_products',
        methods: ['GET']
    )]
    public function getProducts(
        ProductRepository $productRepository,
        PaginatorInterface $paginator,
        Request $request,
    ): JsonResponse {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);

        if ($limit <= 0 || $limit > 1000) {
            return new JsonResponse(['error' => 'Invalid limit value.'], Response::HTTP_BAD_REQUEST);
        }

        if ($page <= 0) {
            return new JsonResponse(['error' => 'Invalid page value.'], Response::HTTP_BAD_REQUEST);
        }
        $idCache = 'getProducts-'.$page.'-'.$limit;

        try {
            $productList = $this->cache->get($idCache, function (ItemInterface $item) use ($productRepository, $paginator, $page, $limit) {
                $item->tag('productsCache');
                $query = $productRepository->createQueryBuilder('p')->getQuery();
                $pagination = $paginator->paginate($query, $page, $limit);

                $item->expiresAfter(3600);

                return [
                    'products' => $pagination->getItems(),
                    'totalCount' => $pagination->getTotalItemCount(),
                ];
            });

            $context = SerializationContext::create()->setGroups(['product:read']);
            $jsonContent = $this->serializer->serialize($productList, 'json', $context);

            return new JsonResponse($jsonContent, Response::HTTP_OK, [
                'X-Total-Count' => $productList['totalCount'],
                'X-Page' => $page,
                'X-Limit' => $limit,
            ], true);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'An unexpected error occurred.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route(
        '/api/products/{id}',
        name: 'get_product_detail',
        methods: ['GET']
    )]
    public function getProductDetail(
        int $id,
        ProductRepository $productRepository,
    ): JsonResponse {
        $cacheKey = 'getProductDetail-'.$id;

        try {
            $product = $this->cache->get($cacheKey, function (ItemInterface $item) use ($productRepository, $id) {
                $item->tag('productsCache');
                $product = $productRepository->find($id);

                if (!$product) {
                    throw new NotFoundHttpException("Produit avec l'ID $id non trouvé.");
                }

                return $product;
            });

            $context = SerializationContext::create()->setGroups(['product:read']);
            $jsonContent = $this->serializer->serialize($product, 'json', $context);

            return new JsonResponse($jsonContent, Response::HTTP_OK, [], true);
        } catch (NotFoundHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route(
        '/api/products',
        name: 'create_product',
        methods: ['POST']
    )]
    public function createProduct(
        Request $request,
        EntityManagerInterface $em,
    ): JsonResponse {
        $data = $request->getContent();
        $product = $this->serializer->deserialize($data, Product::class, 'json');

        $errors = $this->getValidationErrors($product);
        if (!empty($errors)) {
            return new JsonResponse(['errors' => $errors], Response::HTTP_BAD_REQUEST);
        }

        $em->persist($product);
        $em->flush();

        $this->cache->invalidateTags(['productCache']);

        $context = SerializationContext::create()->setGroups(['product:read']);
        $jsonContent = $this->serializer->serialize($product, 'json', $context);

        return new JsonResponse($jsonContent, Response::HTTP_CREATED, [], true);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route(
        '/api/products/{id}',
        name: 'patch_product',
        methods: ['PATCH']
    )]
    public function updateProduct(
        int $id,
        Request $request,
        ProductRepository $productRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        $product = $productRepository->find($id);
        if (!$product) {
            return new JsonResponse(['message' => 'Produit non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            return new JsonResponse(['message' => 'Données JSON invalides.'], Response::HTTP_BAD_REQUEST);
        }

        if (isset($data['name'])) {
            $product->setName($data['name']);
        }
        if (isset($data['description'])) {
            $product->setDescription($data['description']);
        }
        if (isset($data['model'])) {
            $product->setModel($data['model']);
        }
        if (isset($data['brand'])) {
            $product->setBrand($data['brand']);
        }
        if (isset($data['reference'])) {
            $product->setReference($data['reference']);
        }
        if (isset($data['price'])) {
            $product->setPrice((float) $data['price']);
        }
        if (isset($data['dimension'])) {
            $product->setDimension($data['dimension']);
        }
        if (isset($data['stock'])) {
            $product->setStock((int) $data['stock']);
        }
        if (isset($data['isAvailable'])) {
            $product->setIsAvailable((bool) $data['isAvailable']);
        }
        if (isset($data['image'])) {
            $product->setImage($data['image']);
        }

        $errors = $this->getValidationErrors($product);
        if (!empty($errors)) {
            return new JsonResponse(['errors' => $errors], Response::HTTP_BAD_REQUEST);
        }

        $em->flush();

        $this->cache->invalidateTags(['productCache']);
        $this->cache->delete('getProductDetail-'.$id);

        $serializationContext = SerializationContext::create()->setGroups(['product:read']);
        $jsonContent = $this->serializer->serialize($product, 'json', $serializationContext);

        return new JsonResponse($jsonContent, Response::HTTP_OK, [], true);
    }

    /**
     * @throws InvalidArgumentException
     */
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
        $this->cache->invalidateTags(['productCache']);

        return new JsonResponse(['message' => 'Produit supprimé avec succès.'], Response::HTTP_NO_CONTENT);
    }

    private function getValidationErrors($entity): array
    {
        $errors = $this->validator->validate($entity);
        if (count($errors) > 0) {
            return array_map(fn ($error) => $error->getMessage(), iterator_to_array($errors));
        }

        return [];
    }
}
