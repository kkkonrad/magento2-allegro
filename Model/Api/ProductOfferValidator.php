<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Model\Api;

use Macopedia\Allegro\Api\Data\ProductOfferInterface;

class ProductOfferValidator
{
    /**
     * @throws ClientException
     */
    public function validate(ProductOfferInterface $offer): void
    {
        $errors = [];

        if (trim((string)$offer->getName()) === '') {
            $errors[] = 'Name is required.';
        }

        if (trim($offer->getProductId()) === '') {
            $errors[] = 'Allegro catalog product ID is required.';
        }

        $sellingMode = $offer->getSellingMode();
        $amount = $sellingMode['price']['amount'] ?? null;
        if (($sellingMode['format'] ?? '') === '' || !is_numeric($amount) || (float)$amount <= 0) {
            $errors[] = 'Selling mode with a positive price is required.';
        }

        if ($offer->getQuantity() < 0) {
            $errors[] = 'Quantity cannot be negative.';
        }

        $location = $offer->getLocation();
        foreach (['countryCode', 'city', 'postCode'] as $field) {
            if (trim((string)($location[$field] ?? '')) === '') {
                $errors[] = sprintf('Location field "%s" is required.', $field);
            }
        }

        if (trim((string)$offer->getCategory()) === '') {
            $errors[] = 'Category is required.';
        }

        $delivery = $offer->getDeliveryOptions();
        if (trim((string)($delivery['shipping_rates_id'] ?? '')) === '') {
            $errors[] = 'Delivery shipping rate is required.';
        }
        if (trim((string)($delivery['handling_time'] ?? '')) === '') {
            $errors[] = 'Delivery handling time is required.';
        }

        $payments = $offer->getPayments();
        if (trim((string)($payments['invoice'] ?? '')) === '') {
            $errors[] = 'Invoice option is required.';
        }

        if ($errors) {
            throw new ClientException(__('Product offer validation failed: %1', implode(' ', $errors)));
        }
    }
}
