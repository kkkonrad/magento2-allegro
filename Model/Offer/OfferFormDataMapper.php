<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Model\Offer;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;

class OfferFormDataMapper
{
    /** @var ScopeConfigInterface */
    private $scopeConfig;

    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @throws LocalizedException
     */
    public function map(array $data): OfferSaveRequest
    {
        $ean = $this->scalar($data, 'ean');
        if ($ean !== '' || empty($data['id'])) {
            $this->validateEan($ean);
        }

        $magentoProductId = filter_var($data['product'] ?? null, FILTER_VALIDATE_INT);
        if ($magentoProductId === false || $magentoProductId < 1) {
            throw new LocalizedException(__('A valid Magento product ID is required.'));
        }

        $catalogProductId = $this->scalar($data, 'product_id');
        if ($catalogProductId === '') {
            throw new LocalizedException(__('An Allegro catalog product ID is required.'));
        }

        $price = filter_var($data['price'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($price === false || $price <= 0) {
            throw new LocalizedException(__('Offer price must be greater than zero.'));
        }

        $quantity = filter_var($data['qty'] ?? null, FILTER_VALIDATE_INT);
        if ($quantity === false || $quantity < 0) {
            throw new LocalizedException(__('Offer quantity must be a non-negative integer.'));
        }

        return new OfferSaveRequest(
            $magentoProductId,
            $catalogProductId,
            $this->scalar($data, 'name'),
            (float)$price,
            (int)$quantity,
            $this->scalar($data, 'category'),
            is_array($data['parameters'] ?? null) ? $data['parameters'] : [],
            $this->scalar($data, 'delivery_shipping_rates_id'),
            $this->scalar($data, 'delivery_handling_time'),
            $this->scalar($data, 'payments_invoice'),
            $this->location(),
            is_array($data['images'] ?? null) ? $data['images'] : [],
            $this->nullableScalar($data, 'description'),
            $this->afterSalesServices($data)
        );
    }

    private function location(): array
    {
        return [
            'city' => (string)$this->scopeConfig->getValue('allegro/origin/city'),
            'countryCode' => (string)$this->scopeConfig->getValue('allegro/origin/country_id'),
            'postCode' => (string)$this->scopeConfig->getValue('allegro/origin/post_code'),
            'province' => (string)$this->scopeConfig->getValue('allegro/origin/province'),
        ];
    }

    private function afterSalesServices(array $data): array
    {
        $services = [];
        foreach ([
            'implied_warranty' => 'impliedWarranty',
            'return_policy' => 'returnPolicy',
            'warranty' => 'warranty',
        ] as $formKey => $apiKey) {
            $id = $this->scalar($data, $formKey);
            if ($id !== '') {
                $services[$apiKey] = ['id' => $id];
            }
        }

        return $services;
    }

    private function scalar(array $data, string $key): string
    {
        return isset($data[$key]) && is_scalar($data[$key]) ? trim((string)$data[$key]) : '';
    }

    private function nullableScalar(array $data, string $key): ?string
    {
        $value = $this->scalar($data, $key);
        return $value !== '' ? $value : null;
    }

    /**
     * EAN is a GTIN value: 8, 12, 13 or 14 digits with a valid check digit.
     * The product-offer endpoint receives the selected catalog product ID, but
     * validating the lookup key prevents a crafted admin request from bypassing UI validation.
     *
     * @throws LocalizedException
     */
    private function validateEan(string $ean): void
    {
        if (!preg_match('/^(?:\d{8}|\d{12}|\d{13}|\d{14})$/', $ean)) {
            throw new LocalizedException(__('EAN must contain 8, 12, 13 or 14 digits.'));
        }

        $sum = 0;
        $digits = str_split($ean);
        $lastIndex = count($digits) - 1;
        foreach (array_slice($digits, 0, -1) as $index => $digit) {
            $sum += (int)$digit * (($lastIndex - $index) % 2 === 1 ? 3 : 1);
        }
        $checkDigit = (10 - ($sum % 10)) % 10;
        if ($checkDigit !== (int)$digits[$lastIndex]) {
            throw new LocalizedException(__('EAN check digit is invalid.'));
        }
    }
}
