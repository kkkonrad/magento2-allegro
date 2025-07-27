<?php

namespace Macopedia\Allegro\Api\Data;

interface ProductInterface
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
    public function getName(): string;

    /**
     * @param string $name
     * @return $this
     */
    public function setName(string $name): self;

    /**
     * @return string
     */
    public function getCategory(): string;

    /**
     * @param string $category
     * @return $this
     */
    public function setCategory(string $category): self;

    /**
     * @return array
     */
    public function getImages(): array;

    /**
     * @param array $images
     * @return $this
     */
    public function setImages(array $images): self;

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
     * @return string
     */
    public function getDescription(): array;

    /**
     * @param string $description
     * @return $this
     */
    public function setDescription(array $description): self;
} 