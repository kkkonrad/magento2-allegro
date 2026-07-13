<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Model\Offer;

final class OfferSaveRequest
{
    public int $magentoProductId;
    public string $catalogProductId;
    public string $name;
    public float $price;
    public int $quantity;
    public string $categoryId;
    public array $parameters;
    public string $shippingRateId;
    public string $handlingTime;
    public string $invoice;
    public array $location;
    public array $images;
    public ?string $description;
    public array $afterSalesServices;
    public array $responsibleProducer;
    public array $responsiblePerson;
    public array $safetyInformation;
    public array $taxSettings;

    public function __construct(
        int $magentoProductId,
        string $catalogProductId,
        string $name,
        float $price,
        int $quantity,
        string $categoryId,
        array $parameters,
        string $shippingRateId,
        string $handlingTime,
        string $invoice,
        array $location,
        array $images,
        ?string $description,
        array $afterSalesServices,
        array $responsibleProducer,
        array $responsiblePerson,
        array $safetyInformation,
        array $taxSettings
    ) {
        $this->magentoProductId = $magentoProductId;
        $this->catalogProductId = $catalogProductId;
        $this->name = $name;
        $this->price = $price;
        $this->quantity = $quantity;
        $this->categoryId = $categoryId;
        $this->parameters = $parameters;
        $this->shippingRateId = $shippingRateId;
        $this->handlingTime = $handlingTime;
        $this->invoice = $invoice;
        $this->location = $location;
        $this->images = $images;
        $this->description = $description;
        $this->afterSalesServices = $afterSalesServices;
        $this->responsibleProducer = $responsibleProducer;
        $this->responsiblePerson = $responsiblePerson;
        $this->safetyInformation = $safetyInformation;
        $this->taxSettings = $taxSettings;
    }
}
