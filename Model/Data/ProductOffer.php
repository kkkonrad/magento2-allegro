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
        return $this->getData('product_id') ?: '';
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
        return $this->getData('seller_id') ?: '';
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
        return $this->getData('status') ?: '';
    }

    /**
     * @param string $status
     * @return $this
     */
    public function setStatus(string $status): self
    {
        return $this->setData('status', $status);
    }

    public function getValidationErrors(): array
    {
        return $this->getData('validation_errors') ?? [];
    }

    public function setValidationErrors(array $errors): self
    {
        return $this->setData('validation_errors', $errors);
    }

    public function getValidationWarnings(): array
    {
        return $this->getData('validation_warnings') ?? [];
    }

    public function setValidationWarnings(array $warnings): self
    {
        return $this->setData('validation_warnings', $warnings);
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

    /**
     * @return string|null
     */
    public function getCategory(): ?string
    {
        return $this->getData('category');
    }

    /**
     * @param string $category
     * @return $this
     */
    public function setCategory(string $category): self
    {
        return $this->setData('category', $category);
    }

    /**
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->getData('name');
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setName(string $name): self
    {
        return $this->setData('name', $name);
    }

    /**
     * @return array
     */
    public function getSellingMode(): array
    {
        return $this->getData('selling_mode') ?? [];
    }

    /**
     * @param array $sellingMode
     * @return $this
     */
    public function setSellingMode(array $sellingMode): self
    {
        return $this->setData('selling_mode', $sellingMode);
    }

    /**
     * @return array
     */
    public function getLocation(): array
    {
        return $this->getData('location') ?? [];
    }

    /**
     * @param array $location
     * @return $this
     */
    public function setLocation(array $location): self
    {
        return $this->setData('location', $location);
    }

    /**
     * @return array
     */
    public function getImages(): array
    {
        return $this->getData('images') ?? [];
    }

    /**
     * @param array $images
     * @return $this
     */
    public function setImages(array $images): self
    {
        return $this->setData('images', $images);
    }

    /**
     * @return array
     */
    public function getDescription(): array
    {
        return $this->getData('description') ?? [];
    }

    /**
     * @param array $description
     * @return $this
     */
    public function setDescription(array $description): self
    {
        return $this->setData('description', $description);
    }

    /**
     * @return string|null
     */
    public function getExternalId(): ?string
    {
        return $this->getData('external_id');
    }

    /**
     * @param string $externalId
     * @return $this
     */
    public function setExternalId(string $externalId): self
    {
        return $this->setData('external_id', $externalId);
    }

    /**
     * @return array
     */
    public function getAfterSalesServices(): array
    {
        return $this->getData('after_sales_services') ?? [];
    }

    /**
     * @param array $afterSalesServices
     * @return $this
     */
    public function setAfterSalesServices(array $afterSalesServices): self
    {
        return $this->setData('after_sales_services', $afterSalesServices);
    }

    /**
     * @return array
     */
    public function getAttachments(): array
    {
        return $this->getData('attachments') ?? [];
    }

    /**
     * @param array $attachments
     * @return $this
     */
    public function setAttachments(array $attachments): self
    {
        return $this->setData('attachments', $attachments);
    }
}
