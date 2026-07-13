<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Test\Unit\Model;

use Macopedia\Allegro\Model\Api\Credentials;
use Macopedia\Allegro\Model\OfferSyncState;
use Magento\Framework\App\ResourceConnection;
use PHPUnit\Framework\TestCase;

class OfferSyncStateTest extends TestCase
{
    public function testStateHashIsStableAndIncludesEverySynchronizedValue(): void
    {
        $state = new OfferSyncState(
            $this->createMock(ResourceConnection::class),
            $this->createMock(Credentials::class)
        );

        $hash = $state->createHash('offer-1', 4, 12.5);

        self::assertSame($hash, $state->createHash('offer-1', 4, 12.50));
        self::assertNotSame($hash, $state->createHash('offer-2', 4, 12.5));
        self::assertNotSame($hash, $state->createHash('offer-1', 5, 12.5));
        self::assertNotSame($hash, $state->createHash('offer-1', 4, null));
    }
}
