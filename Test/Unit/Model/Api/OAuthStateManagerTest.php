<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Test\Unit\Model\Api;

use Macopedia\Allegro\Model\Api\OAuthStateManager;
use Magento\Backend\Model\Auth\Session;
use PHPUnit\Framework\TestCase;

class OAuthStateManagerTest extends TestCase
{
    public function testGeneratedStateCanBeValidatedOnlyOnce(): void
    {
        $session = $this->createSessionFake();

        $manager = new OAuthStateManager($session);
        $state = $manager->generate();

        self::assertSame(64, strlen($state));
        self::assertTrue($manager->validateAndConsume($state));
        self::assertFalse($manager->validateAndConsume($state));
    }

    public function testRejectsExpiredOrMismatchedState(): void
    {
        $session = $this->createSessionFake();
        $session->setData('macopedia_allegro_oauth_state', [
            'hash' => hash('sha256', 'expected'),
            'created_at' => time(),
        ]);

        $manager = new OAuthStateManager($session);

        self::assertFalse($manager->validateAndConsume('different'));
        $session->setData('macopedia_allegro_oauth_state', [
            'hash' => hash('sha256', 'expected'),
            'created_at' => time() - 601,
        ]);
        self::assertFalse($manager->validateAndConsume('expected'));
    }

    private function createSessionFake(): Session
    {
        return new class extends Session {
            /** @var array */
            private $values = [];

            public function __construct()
            {
            }

            public function setData($key, $value = null): void
            {
                $this->values[$key] = $value;
            }

            public function getData($key = '', $clear = false)
            {
                $value = $this->values[$key] ?? null;
                if ($clear) {
                    unset($this->values[$key]);
                }
                return $value;
            }

            public function unsetData($key = null): void
            {
                unset($this->values[$key]);
            }
        };
    }
}
