<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Test\Unit\Model\Config\Source;

use Macopedia\Allegro\Model\Config\Source\ResponsibleProducer;
use Macopedia\Allegro\Model\ResponsibleProducerRepository;
use PHPUnit\Framework\TestCase;

class ResponsibleProducerTest extends TestCase
{
    public function testBuildsSelectOptionsFromAllegroProducers(): void
    {
        $repository = $this->createMock(ResponsibleProducerRepository::class);
        $repository->method('getList')->willReturn([
            ['id' => 'producer-id', 'name' => 'Producer name'],
        ]);

        $options = (new ResponsibleProducer($repository))->toOptionArray();

        self::assertSame('', $options[0]['value']);
        self::assertSame('producer-id', $options[1]['value']);
        self::assertSame('Producer name', $options[1]['label']);
    }

    public function testKeepsEmptyOptionWhenApiFails(): void
    {
        $repository = $this->createMock(ResponsibleProducerRepository::class);
        $repository->method('getList')->willThrowException(new \RuntimeException('API unavailable'));

        $options = (new ResponsibleProducer($repository))->toOptionArray();

        self::assertCount(1, $options);
        self::assertSame('', $options[0]['value']);
    }
}
