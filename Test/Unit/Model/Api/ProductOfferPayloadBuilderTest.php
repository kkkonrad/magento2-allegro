<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Test\Unit\Model\Api;

use Macopedia\Allegro\Model\Api\ClientException;
use Macopedia\Allegro\Model\Api\ProductOfferPayloadBuilder;
use Macopedia\Allegro\Model\Api\ProductOfferValidator;
use Macopedia\Allegro\Model\Data\ProductOffer;
use PHPUnit\Framework\TestCase;

class ProductOfferPayloadBuilderTest extends TestCase
{
    /** @var ProductOfferPayloadBuilder */
    private $builder;

    protected function setUp(): void
    {
        $this->builder = new ProductOfferPayloadBuilder(new ProductOfferValidator());
    }

    public function testBuildsCompleteProductOfferPayloadFromNormalizedData(): void
    {
        $offer = $this->validOffer();
        $offer->setParameters([
            ['id' => '217', 'valuesIds' => ['', '217_2048', null]],
            ['id' => '224017', 'values' => ['SM-A025']],
            ['id' => 'range', 'rangeValue' => ['from' => '1', 'to' => '10']],
            ['id' => 'empty', 'values' => ['', null]],
        ]);
        $offer->setImages([
            ['url' => 'https://example.test/one.jpg', 'ignored' => 'value'],
            'https://example.test/two.jpg',
            ['url' => ''],
        ]);
        $offer->setDescription([
            'sections' => [[
                'items' => [['type' => 'TEXT', 'content' => '<p>Description</p>']],
            ]],
        ]);
        $offer->setAfterSalesServices([
            'returnPolicy' => ['id' => 'return-id'],
        ]);
        $offer->setExternalId('magento-product-10');

        $payload = $this->builder->build($offer);

        self::assertSame('pl-PL', $payload['language']);
        self::assertSame('catalog-product-id', $payload['productSet'][0]['product']['id']);
        self::assertSame(0, $payload['stock']['available']);
        self::assertSame('shipping-rate-id', $payload['delivery']['shippingRates']['id']);
        self::assertSame('PT24H', $payload['delivery']['handlingTime']);
        self::assertSame('VAT', $payload['payments']['invoice']);
        self::assertSame([
            ['id' => '217', 'valuesIds' => ['217_2048']],
            ['id' => '224017', 'values' => ['SM-A025']],
            ['id' => 'range', 'rangeValue' => ['from' => '1', 'to' => '10']],
        ], $payload['parameters']);
        self::assertSame([
            ['url' => 'https://example.test/one.jpg'],
            ['url' => 'https://example.test/two.jpg'],
        ], $payload['images']);
        self::assertSame('return-id', $payload['afterSalesServices']['returnPolicy']['id']);
        self::assertSame('magento-product-10', $payload['external']['id']);
        self::assertSame('INACTIVE', $payload['publication']['status']);
    }

    public function testNormalizesLegacyParameterMap(): void
    {
        $offer = $this->validOffer();
        $offer->setParameters([
            '217' => ['217_2048', ''],
            '219' => '219_64',
        ]);

        $payload = $this->builder->build($offer);

        self::assertSame([
            ['id' => '217', 'valuesIds' => ['217_2048']],
            ['id' => '219', 'valuesIds' => ['219_64']],
        ], $payload['parameters']);
    }

    public function testRejectsIncompleteOfferBeforeApiCall(): void
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Name is required');

        $this->builder->build(new ProductOffer());
    }

    private function validOffer(): ProductOffer
    {
        $offer = new ProductOffer();
        $offer->setName('Test offer');
        $offer->setProductId('catalog-product-id');
        $offer->setSellingMode([
            'format' => 'BUY_NOW',
            'price' => ['amount' => '10.00', 'currency' => 'PLN'],
        ]);
        $offer->setQuantity(0);
        $offer->setLocation([
            'countryCode' => 'PL',
            'city' => 'Poznań',
            'postCode' => '60-001',
            'province' => 'WIELKOPOLSKIE',
        ]);
        $offer->setCategory('123');
        $offer->setDeliveryOptions([
            'shipping_rates_id' => 'shipping-rate-id',
            'handling_time' => 'PT24H',
        ]);
        $offer->setPayments(['invoice' => 'VAT']);
        $offer->setStatus('INACTIVE');

        return $offer;
    }
}
