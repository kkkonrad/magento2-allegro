<?php

namespace Macopedia\Allegro\Api;

use Macopedia\Allegro\Api\Data\ProductOfferInterface;
use Macopedia\Allegro\Model\Api\ClientException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

interface ProductOfferRepositoryInterface
{
    /**
     * Create or update a product offer
     *
     * @param ProductOfferInterface $productOffer
     * @return string Offer ID
     * @throws ClientException
     * @throws CouldNotSaveException
     */
    public function save(ProductOfferInterface $productOffer): string;

    /**
     * Get product offer by ID
     *
     * @param string $offerId
     * @return ProductOfferInterface
     * @throws ClientException
     * @throws NoSuchEntityException
     */
    public function get(string $offerId): ProductOfferInterface;

    /**
     * Get product offers by product ID
     *
     * @param string $productId
     * @return ProductOfferInterface[]
     * @throws ClientException
     */
    public function getByProductId(string $productId): array;

    /**
     * Delete product offer
     *
     * @param string $offerId
     * @return bool
     * @throws ClientException
     */
    public function delete(string $offerId): bool;
} 