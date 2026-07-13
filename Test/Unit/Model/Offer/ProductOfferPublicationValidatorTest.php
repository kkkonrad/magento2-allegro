<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Test\Unit\Model\Offer;

use Macopedia\Allegro\Model\Data\ProductOffer;
use Macopedia\Allegro\Model\Offer\ProductOfferPublicationValidator;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class ProductOfferPublicationValidatorTest extends TestCase
{
    public function testRejectsInactiveOfferWithAllegroValidationErrors(): void
    {
        $offer = (new ProductOffer())
            ->setStatus('INACTIVE')
            ->setValidationErrors(['[parameters] Uzupełnij markę.']);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('[parameters] Uzupełnij markę.');

        (new ProductOfferPublicationValidator())->validate($offer);
    }

    public function testAcceptsValidInactiveOffer(): void
    {
        $offer = (new ProductOffer())->setStatus('INACTIVE');

        (new ProductOfferPublicationValidator())->validate($offer);

        self::assertTrue(true);
    }
}
