<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Test\Unit\Model\Api;

use Macopedia\Allegro\Api\Data\TokenInterface;
use Macopedia\Allegro\Model\Api\Auth\Data\TokenSerializer;
use Macopedia\Allegro\Model\Api\Credentials;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\FlagManager;
use PHPUnit\Framework\TestCase;

class CredentialsTest extends TestCase
{
    public function testStoresSandboxTokenUnderEnvironmentSpecificEncryptedFlag(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnMap([
            [Credentials::SANDBOX_CONFIG_KEY, 'default', null, '1'],
        ]);
        $serializer = $this->createMock(TokenSerializer::class);
        $token = $this->createMock(TokenInterface::class);
        $serializer->expects(self::once())->method('encode')->with($token)->willReturn('encrypted-token');

        $flagManager = $this->createMock(FlagManager::class);
        $flagManager->expects(self::once())
            ->method('saveFlag')
            ->with('allegro_credentials_token_data_sandbox', 'encrypted-token');

        $credentials = new Credentials($scopeConfig, $serializer, $flagManager);
        $credentials->saveToken($token);
    }

    public function testReadsProductionTokenFromSeparateFlag(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnMap([
            [Credentials::SANDBOX_CONFIG_KEY, 'default', null, '0'],
        ]);
        $token = $this->createMock(TokenInterface::class);
        $serializer = $this->createMock(TokenSerializer::class);
        $serializer->expects(self::once())->method('decode')->with('encrypted-token')->willReturn($token);

        $flagManager = $this->createMock(FlagManager::class);
        $flagManager->expects(self::once())
            ->method('getFlagData')
            ->with('allegro_credentials_token_data_production')
            ->willReturn('encrypted-token');

        $credentials = new Credentials($scopeConfig, $serializer, $flagManager);

        self::assertSame($token, $credentials->getToken());
    }
}
