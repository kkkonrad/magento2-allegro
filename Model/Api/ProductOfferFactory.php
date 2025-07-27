<?php

namespace Macopedia\Allegro\Model\Api;

use Macopedia\Allegro\Api\Data\ProductOfferInterface;
use Magento\Framework\ObjectManagerInterface;

class ProductOfferFactory
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * @return ProductOfferInterface
     */
    public function create(): ProductOfferInterface
    {
        return $this->objectManager->create(ProductOfferInterface::class);
    }
} 