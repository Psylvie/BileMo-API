<?php

namespace App\Service;

use Doctrine\ORM\EntityRepository;
use Knp\Component\Pager\PaginatorInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Service for paginating the collection of products.
 */
readonly class ProductPaginationService
{
    public function __construct(
        private PaginatorInterface $paginator,
        private TagAwareCacheInterface $cache,
    ) {
    }

    /**
     * Paginate the collection of products.
     *
     * @throws InvalidArgumentException
     */
    public function paginate(
        EntityRepository $repository,
        Request $request,
        int $defaultLimit = 10,
        int $maxLimit = 1000,
        string $cacheTag = 'default',
    ): array {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', $defaultLimit);

        if ($limit <= 0 || $limit > $maxLimit) {
            return [
                'error' => 'Invalid limit value. It should be between 1 and '.$maxLimit.'.',
                'status' => Response::HTTP_BAD_REQUEST,
            ];
        }

        if ($page <= 0) {
            return [
                'error' => 'Invalid page value. It should be greater than 0.',
                'status' => Response::HTTP_BAD_REQUEST,
            ];
        }
        $cacheKey = sprintf('products-page-%d-limit-%d', $page, $limit);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($repository, $page, $limit) {
            $item->expiresAfter(3600);
            $item->tag('products');

            $queryBuilder = $repository->createQueryBuilder('e')->getQuery();
            $pagination = $this->paginator->paginate($queryBuilder, $page, $limit);

            return [
                'items' => $pagination->getItems(),
                'totalCount' => $pagination->getTotalItemCount(),
                'currentPage' => $pagination->getCurrentPageNumber(),
                'totalPages' => ceil($pagination->getTotalItemCount() / $limit),
            ];
        });
    }

    /**
     * Invalidates the product cache globally.
     *
     * @throws InvalidArgumentException
     */
    public function invalidateProductCache(): void
    {
        $this->cache->invalidateTags(['products']);
    }
}
