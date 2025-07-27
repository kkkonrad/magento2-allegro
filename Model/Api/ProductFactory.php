<?php

namespace Macopedia\Allegro\Model\Api;

use Macopedia\Allegro\Api\Data\ProductInterface;
use Magento\Framework\ObjectManagerInterface;

class ProductFactory
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
     * @return ProductInterface
     */
    public function create(): ProductInterface
    {
        return $this->objectManager->create(ProductInterface::class);
    }
} 