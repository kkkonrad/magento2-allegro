<?php

namespace Macopedia\Allegro\Model\Data;

use Macopedia\Allegro\Api\Data\ProductOfferInterface;
use Magento\Framework\DataObject;

class ProductOffer extends DataObject implements ProductOfferInterface
{
    /**
     * @return string|null
     */
    public function getId(): ?string
    {
        return $this->getData('id');
    }

    /**
     * @param string $id
     * @return $this
     */
    public function setId(string $id): self
    {
        return $this->setData('id', $id);
    }

    /**
     * @return string
     */
    public function getProductId(): string
    {
        return $this->getData('product_id');
    }

    /**
     * @param string $productId
     * @return $this
     */
    public function setProductId(string $productId): self
    {
        return $this->setData('product_id', $productId);
    }

    /**
     * @return string
     */
    public function getSellerId(): string
    {
        return $this->getData('seller_id');
    }

    /**
     * @param string $sellerId
     * @return $this
     */
    public function setSellerId(string $sellerId): self
    {
        return $this->setData('seller_id', $sellerId);
    }

    /**
     * @return float
     */
    public function getPrice(): float
    {
        return (float)$this->getData('price');
    }

    /**
     * @param float $price
     * @return $this
     */
    public function setPrice(float $price): self
    {
        return $this->setData('price', $price);
    }

    /**
     * @return int
     */
    public function getQuantity(): int
    {
        return (int)$this->getData('quantity');
    }

    /**
     * @param int $quantity
     * @return $this
     */
    public function setQuantity(int $quantity): self
    {
        return $this->setData('quantity', $quantity);
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->getData('status');
    }

    /**
     * @param string $status
     * @return $this
     */
    public function setStatus(string $status): self
    {
        return $this->setData('status', $status);
    }

    /**
     * @return array
     */
    public function getParameters(): array
    {
        return $this->getData('parameters') ?? [];
    }

    /**
     * @param array $parameters
     * @return $this
     */
    public function setParameters(array $parameters): self
    {
        return $this->setData('parameters', $parameters);
    }

    /**
     * @return array
     */
    public function getDeliveryOptions(): array
    {
        return $this->getData('delivery_options') ?? [];
    }

    /**
     * @param array $deliveryOptions
     * @return $this
     */
    public function setDeliveryOptions(array $deliveryOptions): self
    {
        return $this->setData('delivery_options', $deliveryOptions);
    }

    /**
     * @return array
     */
    public function getPayments(): array
    {
        return $this->getData('payments') ?? [];
    }

    /**
     * @param array $payments
     * @return $this
     */
    public function setPayments(array $payments): self
    {
        return $this->setData('payments', $payments);
    }
} 