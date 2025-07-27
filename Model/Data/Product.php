<?php

namespace Macopedia\Allegro\Model\Data;

use Macopedia\Allegro\Api\Data\ProductInterface;
use Magento\Framework\DataObject;

class Product extends DataObject implements ProductInterface
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
    public function getName(): string
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
     * @return string
     */
    public function getCategory(): string
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
    public function getDescription(): array
    {
        return $this->getData('description');
    }

    /**
     * @param array $description
     * @return $this
     */
    public function setDescription( $description): self
    {
        return $this->setData('description', $description);
    }
} 