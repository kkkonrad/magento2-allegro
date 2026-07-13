<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Test\Unit\Model\Api;

use Macopedia\Allegro\Model\Api\Auth\Data\Token;
use PHPUnit\Framework\TestCase;

class TokenTest extends TestCase
{
    public function testTreatsTokenAsExpiredBeforeActualExpirationMargin(): void
    {
        $token = new Token();
        $token->setExpirationTime(time() + 100);

        self::assertTrue($token->isExpired());
    }

    public function testKeepsTokenValidOutsideRefreshMargin(): void
    {
        $token = new Token();
        $token->setExpirationTime(time() + 1000);

        self::assertFalse($token->isExpired());
    }
}
