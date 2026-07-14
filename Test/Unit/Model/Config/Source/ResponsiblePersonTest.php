<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Test\Unit\Model\Config\Source;

use Macopedia\Allegro\Model\Config\Source\ResponsiblePerson;
use Macopedia\Allegro\Model\ResponsiblePersonRepository;
use PHPUnit\Framework\TestCase;

class ResponsiblePersonTest extends TestCase
{
    public function testBuildsSelectOptionsFromAllegroPersons(): void
    {
        $repository = $this->createMock(ResponsiblePersonRepository::class);
        $repository->method('getList')->willReturn([
            ['id' => 'person-id', 'name' => 'Responsible person'],
        ]);

        $options = (new ResponsiblePerson($repository))->toOptionArray();

        self::assertSame('', $options[0]['value']);
        self::assertSame('person-id', $options[1]['value']);
        self::assertSame('Responsible person', $options[1]['label']);
    }

    public function testKeepsNotApplicableOptionWhenApiFails(): void
    {
        $repository = $this->createMock(ResponsiblePersonRepository::class);
        $repository->method('getList')->willThrowException(new \RuntimeException('API unavailable'));

        $options = (new ResponsiblePerson($repository))->toOptionArray();

        self::assertCount(1, $options);
        self::assertSame('', $options[0]['value']);
    }
}
