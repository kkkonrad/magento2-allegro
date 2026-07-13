<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Model\Api;

use Macopedia\Allegro\Api\Data\ProductOfferInterface;

class ProductOfferPayloadBuilder
{
    public const DEFAULT_LANGUAGE = 'pl-PL';

    /** @var ProductOfferValidator */
    private $validator;

    public function __construct(ProductOfferValidator $validator)
    {
        $this->validator = $validator;
    }

    /**
     * @throws ClientException
     */
    public function build(ProductOfferInterface $offer): array
    {
        $this->validator->validate($offer);

        $deliveryOptions = $offer->getDeliveryOptions();
        $productSetItem = [
            'product' => ['id' => $offer->getProductId()],
        ];
        if ($offer->getResponsibleProducer()) {
            $productSetItem['responsibleProducer'] = $offer->getResponsibleProducer();
        }
        if ($offer->getResponsiblePerson()) {
            $productSetItem['responsiblePerson'] = $offer->getResponsiblePerson();
        }
        if ($offer->getSafetyInformation()) {
            $productSetItem['safetyInformation'] = $offer->getSafetyInformation();
        }

        $payload = [
            'name' => trim((string)$offer->getName()),
            'productSet' => [$productSetItem],
            'sellingMode' => $offer->getSellingMode(),
            'stock' => [
                'available' => $offer->getQuantity(),
                'unit' => 'UNIT',
            ],
            'location' => $offer->getLocation(),
            'category' => ['id' => (string)$offer->getCategory()],
            'delivery' => [
                'shippingRates' => ['id' => (string)$deliveryOptions['shipping_rates_id']],
                'handlingTime' => (string)$deliveryOptions['handling_time'],
            ],
            'payments' => [
                'invoice' => (string)$offer->getPayments()['invoice'],
            ],
            'language' => self::DEFAULT_LANGUAGE,
        ];

        if (!empty($deliveryOptions['additional_info'])) {
            $payload['delivery']['additionalInfo'] = (string)$deliveryOptions['additional_info'];
        }

        $parameters = $this->normalizeParameters($offer->getParameters());
        if ($parameters) {
            $payload['parameters'] = $parameters;
        }

        $images = $this->normalizeImages($offer->getImages());
        if ($images) {
            $payload['images'] = $images;
        }

        if ($offer->getDescription()) {
            $payload['description'] = $offer->getDescription();
        }

        if ($offer->getAfterSalesServices()) {
            $payload['afterSalesServices'] = $offer->getAfterSalesServices();
        }

        if ($offer->getTaxSettings()) {
            $payload['taxSettings'] = $offer->getTaxSettings();
        }

        if ($offer->getAttachments()) {
            $payload['attachments'] = $offer->getAttachments();
        }

        if ($offer->getExternalId()) {
            $payload['external'] = ['id' => $offer->getExternalId()];
        }

        if ($offer->getStatus()) {
            $payload['publication'] = ['status' => $offer->getStatus()];
        }

        return $payload;
    }

    private function normalizeParameters(array $parameters): array
    {
        $normalized = [];

        foreach ($parameters as $key => $parameter) {
            if (is_array($parameter) && isset($parameter['id'])) {
                $item = $this->normalizeParameter((string)$parameter['id'], $parameter);
            } else {
                $item = $this->normalizeLegacyParameter((string)$key, $parameter);
            }

            if ($item !== null) {
                $normalized[] = $item;
            }
        }

        return $normalized;
    }

    /**
     * @param mixed $value
     */
    private function normalizeLegacyParameter(string $id, $value): ?array
    {
        if ($id === '') {
            return null;
        }

        $values = is_array($value) ? $value : [$value];
        $values = $this->removeEmptyValues($values);
        if (!$values) {
            return null;
        }

        return ['id' => $id, 'valuesIds' => $values];
    }

    private function normalizeParameter(string $id, array $parameter): ?array
    {
        if ($id === '') {
            return null;
        }

        $item = ['id' => $id];
        $valuesIds = $this->removeEmptyValues((array)($parameter['valuesIds'] ?? []));
        $values = $this->removeEmptyValues((array)($parameter['values'] ?? []));
        $range = $parameter['rangeValue'] ?? null;

        if ($valuesIds) {
            $item['valuesIds'] = $valuesIds;
        }
        if ($values) {
            $item['values'] = $values;
        }
        if (is_array($range)) {
            $from = $range['from'] ?? null;
            $to = $range['to'] ?? null;
            if ($from !== null && $from !== '' && $to !== null && $to !== '') {
                $item['rangeValue'] = ['from' => (string)$from, 'to' => (string)$to];
            }
        }

        return count($item) > 1 ? $item : null;
    }

    private function normalizeImages(array $images): array
    {
        $normalized = [];
        foreach ($images as $image) {
            $url = is_string($image) ? $image : (is_array($image) ? ($image['url'] ?? '') : '');
            $url = trim((string)$url);
            if ($url !== '') {
                $normalized[] = $url;
            }
        }

        return $normalized;
    }

    private function removeEmptyValues(array $values): array
    {
        return array_values(array_filter(
            $values,
            static function ($value): bool {
                return is_scalar($value) && trim((string)$value) !== '';
            }
        ));
    }
}
