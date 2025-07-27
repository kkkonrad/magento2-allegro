<?php

namespace Macopedia\Allegro\Api;

use Macopedia\Allegro\Api\Data\ProductInterface;
use Macopedia\Allegro\Model\Api\ClientException;

interface ProductCatalogRepositoryInterface
{
    /**
     * Search for products in Allegro catalog
     *
     * @param string $phrase Search phrase
     * @param array $parameters Additional search parameters
     * @return ProductInterface[]
     * @throws ClientException
     */
    public function search(array $parameters = []): array;

    /**
     * Get product by ID from Allegro catalog
     *
     * @param string $productId
     * @return ProductInterface
     * @throws ClientException
     */
    public function get(string $productId): ProductInterface;

    /**
     * Create new product in Allegro catalog
     *
     * @param ProductInterface $product
     * @return string Product ID
     * @throws ClientException
     */
    public function create(ProductInterface $product): string;
} 