<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Service\ProductPaginationService;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class ProductController extends AbstractController
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly TagAwareCacheInterface $cache,
        private readonly ProductPaginationService $paginationService,
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
    #[OA\Get(
        description: 'Cette route retourne une liste paginée de produits.',
        summary: 'Retourne la liste des produits',
        tags: ['Products']
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des produits',
        content: new Model(type: Product::class),
    )]
    #[OA\Parameter(
        name: 'page',
        description: 'Numéro de la page à récupérer',
        in: 'query',
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'limit',
        description: "Nombre d'éléments à récupérer par page",
        in: 'query',
        schema: new OA\Schema(type: 'integer')
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY', message: 'Vous devez être authentifié pour accéder à cette ressource')]
    public function getProducts(
        ProductRepository $productRepository,
        Request $request,
    ): JsonResponse {
        $paginationResult = $this->paginationService->paginate($productRepository, $request);

        if (isset($paginationResult['error'])) {
            return new JsonResponse(['error' => $paginationResult['error']], $paginationResult['status']);
        }
        $paginatedCollection = [
            'products' => $paginationResult['items'],
            'totalCount' => $paginationResult['totalCount'],
            'currentPage' => $paginationResult['currentPage'],
            'totalPages' => $paginationResult['totalPages'],
        ];
        $jsonContent = $this->serializer->serialize($paginatedCollection, 'json');

        return new JsonResponse($jsonContent, Response::HTTP_OK, [
            'Content-Type' => 'application/json',
            'X-Total-Count' => $paginationResult['totalCount'],
            'X-Page' => $paginationResult['currentPage'],
            'X-Limit' => $request->query->getInt('limit', 10),
        ], true);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[OA\Get(
        description: 'Cette route retourne le détail du produit.',
        summary: 'Retourne le détail du produit',
        tags: ['Products'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Détail du produit',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'name', type: 'string', example: 'Produit A'),
                        new OA\Property(property: 'description', type: 'string', example: 'Description du produit A'),
                        new OA\Property(property: 'model', type: 'string', example: 'ES_8'),
                        new OA\Property(property: 'brand', type: 'string', example: 'Apple'),
                        new OA\Property(property: 'reference', type: 'string', example: '54_65_b'),
                        new OA\Property(property: 'price', type: 'number', format: 'float', example: 99.99),
                        new OA\Property(property: 'dimension', type: 'string', example: '25 x 12'),
                        new OA\Property(property: 'stock', type: 'integer', example: 10),
                        new OA\Property(property: 'isAvailable', type: 'boolean', example: true),
                        new OA\Property(property: 'image', type: 'string', example: '/uploads/products/produit-a.jpg'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Produit non trouvé',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Produit avec l\'ID 1 non trouvé.'),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    #[Route(
        '/api/products/{id}',
        name: 'get_product_detail',
        methods: ['GET']
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY', message: 'Vous devez être authentifié pour accéder à cette ressource')]
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
    #[OA\Post(
        description: 'Cette route crée un produit.',
        summary: 'Créer un produit',
        tags: ['Products'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Produit créé avec succès',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/Product'
                )
            ),
        ]
    )]
    #[OA\RequestBody(
        description: 'Données requises pour créer un produit',
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Nom du produit'),
                    new OA\Property(property: 'description', type: 'string', example: 'Description du produit'),
                    new OA\Property(property: 'price', type: 'number', example: 19.99),
                    new OA\Property(property: 'model', type: 'string', example: 'Modèle'),
                    new OA\Property(property: 'brand', type: 'string', example: 'Marque'),
                    new OA\Property(property: 'dimension', type: 'string', example: 'Dimension du produit'),
                    new OA\Property(property: 'reference', type: 'string', example: 'Référence'),
                    new OA\Property(property: 'stock', type: 'integer', example: 100),
                    new OA\Property(property: 'isAvailable', type: 'boolean', example: true),
                    new OA\Property(property: 'image', description: 'Image du produit', type: 'string', format: 'binary'),
                ],
                type: 'object'
            )
        )
    )]
    #[Route(
        '/admin/products',
        name: 'create_product',
        methods: ['POST']
    )]
    #[IsGranted('ROLE_ADMIN', message: 'Accès réservé aux administrateurs')]
    public function createProduct(
        Request $request,
        EntityManagerInterface $em,
    ): JsonResponse {
        $name = $request->request->get('name');
        $description = $request->request->get('description');
        $price = $request->request->get('price');
        $model = $request->request->get('model');
        $brand = $request->request->get('brand');
        $dimension = $request->request->get('dimension');
        $reference = $request->request->get('reference');
        $stock = $request->request->get('stock');
        $isAvailable = $request->request->get('isAvailable');

        $price = is_numeric($price) ? (float) $price : null;
        $stock = is_numeric($stock) ? (int) $stock : null;

        $product = new Product();
        $product->setName($name);
        $product->setDescription($description);
        $product->setPrice($price);
        $product->setModel($model);
        $product->setBrand($brand);
        $product->setDimension($dimension);
        $product->setReference($reference);
        $product->setStock($stock);
        $product->setIsAvailable($isAvailable);

        $imageFile = $request->files->get('image');
        if ($imageFile) {
            $imageFileName = uniqid().'.'.$imageFile->guessExtension();
            $imageFile->move($this->getParameter('images_directory'), $imageFileName);
            $product->setImage($imageFileName);
        } else {
            $product->setImage('/images/products/default.png');
        }

        $errors = $this->getValidationErrors($product);
        if (!empty($errors)) {
            return new JsonResponse([
                'errors' => $errors,
                'status' => 'bad_request',
            ], Response::HTTP_BAD_REQUEST);
        }

        $em->persist($product);
        $em->flush();

        $this->paginationService->invalidateProductCache();

        $context = SerializationContext::create()->setGroups(['product:read']);
        $jsonContent = $this->serializer->serialize($product, 'json', $context);

        return new JsonResponse([
            'message' => 'Produit créé avec succès.',
            'product' => json_decode($jsonContent),
            'created_at' => $product->getCreatedAt()->format('Y-m-d H:i:s'),
            'product_id' => $product->getId(),
        ], Response::HTTP_CREATED);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[OA\Delete(
        description: 'Cette route crée un produit.',
        summary: 'creer un produit',
        tags: ['Products']
    )]
    #[Route(
        '/admin/products/{id}',
        name: 'delete_product',
        methods: ['DELETE']
    )]
    #[IsGranted('ROLE_ADMIN', message: 'Accès réservé aux administrateurs')]
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
        $this->paginationService->invalidateProductCache();

        return new JsonResponse(['message' => 'Produit supprimé avec succès.'], Response::HTTP_OK);
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
