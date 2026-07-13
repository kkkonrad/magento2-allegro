<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Test\Unit\Service;

use Macopedia\Allegro\Api\Consumer\MessageInterface;
use Macopedia\Allegro\Api\Consumer\MessageInterfaceFactory;
use Macopedia\Allegro\Logger\Logger;
use Macopedia\Allegro\Model\Configuration;
use Macopedia\Allegro\Service\MessageQtyChange;
use Magento\Framework\MessageQueue\DefaultValueProvider;
use Magento\Framework\MessageQueue\PublisherInterface;
use PHPUnit\Framework\TestCase;

class MessageQtyChangeTest extends TestCase
{
    /**
     * @dataProvider connectionProvider
     */
    public function testUsesConfiguredQueueConnection(string $connection, string $topic): void
    {
        $publisher = $this->createMock(PublisherInterface::class);
        $message = $this->createMock(MessageInterface::class);
        $message->expects(self::once())->method('setProductId')->with(42);
        $factory = $this->createMock(MessageInterfaceFactory::class);
        $factory->method('create')->willReturn($message);
        $configuration = $this->createMock(Configuration::class);
        $configuration->method('isStockSynchronizationEnabled')->willReturn(true);
        $defaultValueProvider = $this->createMock(DefaultValueProvider::class);
        $defaultValueProvider->method('getConnection')->willReturn($connection);
        $publisher->expects(self::once())->method('publish')->with($topic, $message);

        (new MessageQtyChange(
            $publisher,
            $factory,
            $this->createMock(Logger::class),
            $defaultValueProvider,
            $configuration
        ))->execute(42);
    }

    public static function connectionProvider(): array
    {
        return [
            'database queue' => ['db', MessageQtyChange::DB_TOPIC_NAME],
            'RabbitMQ' => ['amqp', MessageQtyChange::TOPIC_NAME],
        ];
    }
}
