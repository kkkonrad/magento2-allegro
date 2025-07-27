<?php

namespace Macopedia\Allegro\Api\Data;

interface ProductOfferInterface
{
    /**
     * @return string|null
     */
    public function getId(): ?string;

    /**
     * @param string $id
     * @return $this
     */
    public function setId(string $id): self;

    /**
     * @return string
     */
    public function getProductId(): string;

    /**
     * @param string $productId
     * @return $this
     */
    public function setProductId(string $productId): self;

    /**
     * @return string
     */
    public function getSellerId(): string;

    /**
     * @param string $sellerId
     * @return $this
     */
    public function setSellerId(string $sellerId): self;

    /**
     * @return float
     */
    public function getPrice(): float;

    /**
     * @param float $price
     * @return $this
     */
    public function setPrice(float $price): self;

    /**
     * @return int
     */
    public function getQuantity(): int;

    /**
     * @param int $quantity
     * @return $this
     */
    public function setQuantity(int $quantity): self;

    /**
     * @return string
     */
    public function getStatus(): string;

    /**
     * @param string $status
     * @return $this
     */
    public function setStatus(string $status): self;

    /**
     * @return array
     */
    public function getParameters(): array;

    /**
     * @param array $parameters
     * @return $this
     */
    public function setParameters(array $parameters): self;

    /**
     * @return array
     */
    public function getDeliveryOptions(): array;

    /**
     * @param array $deliveryOptions
     * @return $this
     */
    public function setDeliveryOptions(array $deliveryOptions): self;

    /**
     * @return array
     */
    public function getPayments(): array;

    /**
     * @param array $payments
     * @return $this
     */
    public function setPayments(array $payments): self;
} 