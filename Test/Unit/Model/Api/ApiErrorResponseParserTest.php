<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Test\Unit\Model\Api;

use Macopedia\Allegro\Model\Api\ApiErrorResponseParser;
use PHPUnit\Framework\TestCase;

class ApiErrorResponseParserTest extends TestCase
{
    /** @var ApiErrorResponseParser */
    private $parser;

    protected function setUp(): void
    {
        $this->parser = new ApiErrorResponseParser();
    }

    public function testParsesAndFormatsAllegroErrors(): void
    {
        $body = json_encode([
            'errors' => [
                [
                    'code' => 'VALIDATION_ERROR',
                    'path' => 'sellingMode.price.amount',
                    'message' => 'Technical message',
                    'userMessage' => 'Price must be greater than zero',
                ],
                [
                    'code' => 'MISSING_FIELD',
                    'path' => 'delivery.shippingRates.id',
                    'message' => 'Shipping rate is required',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $errors = $this->parser->parse($body);

        self::assertSame([
            [
                'code' => 'VALIDATION_ERROR',
                'path' => 'sellingMode.price.amount',
                'message' => 'Price must be greater than zero',
            ],
            [
                'code' => 'MISSING_FIELD',
                'path' => 'delivery.shippingRates.id',
                'message' => 'Shipping rate is required',
            ],
        ], $errors);
        self::assertSame(
            'Price must be greater than zero (VALIDATION_ERROR, sellingMode.price.amount) '
            . 'Shipping rate is required (MISSING_FIELD, delivery.shippingRates.id)',
            $this->parser->format($errors)
        );
    }

    public function testReturnsEmptyListForInvalidOrNonErrorResponse(): void
    {
        self::assertSame([], $this->parser->parse(''));
        self::assertSame([], $this->parser->parse('not-json'));
        self::assertSame([], $this->parser->parse('{"id":"123"}'));
    }

    public function testIgnoresMalformedEntriesAndNormalizesFallbackMessage(): void
    {
        $body = json_encode([
            'errors' => [
                'invalid',
                ['code' => ['not-scalar'], 'path' => null, 'message' => ''],
            ],
        ], JSON_THROW_ON_ERROR);

        self::assertSame([
            [
                'code' => '',
                'path' => '',
                'message' => 'Unknown Allegro API error',
            ],
        ], $this->parser->parse($body));
    }
}
