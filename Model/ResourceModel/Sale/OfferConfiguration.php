<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Model\ResourceModel\Sale;

use Macopedia\Allegro\Model\ResourceModel\AbstractResource;

class OfferConfiguration extends AbstractResource
{
    public function getTaxSettings(string $categoryId, string $countryCode): array
    {
        return $this->requestGet('/sale/tax-settings?' . http_build_query([
            'category.id' => $categoryId,
            'countryCode' => $countryCode,
        ], '', '&', PHP_QUERY_RFC3986));
    }

    public function getResponsibleProducers(): array
    {
        $response = $this->requestGet('/sale/responsible-producers');
        return is_array($response['responsibleProducers'] ?? null)
            ? $response['responsibleProducers']
            : [];
    }

    public function getResponsiblePersons(): array
    {
        $response = $this->requestGet('/sale/responsible-persons');
        return is_array($response['responsiblePersons'] ?? null)
            ? $response['responsiblePersons']
            : [];
    }
}
