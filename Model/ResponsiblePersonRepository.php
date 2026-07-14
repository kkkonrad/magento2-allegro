<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Model;

use Macopedia\Allegro\Model\ResourceModel\ProductOffer;

class ResponsiblePersonRepository
{
    /** @var ProductOffer */
    private $resource;

    public function __construct(ProductOffer $resource)
    {
        $this->resource = $resource;
    }

    /**
     * @return array<int, array{id:string, name:string}>
     */
    public function getList(): array
    {
        $response = $this->resource->get('/sale/responsible-persons');
        $result = [];
        foreach ((array)($response['responsiblePersons'] ?? []) as $person) {
            if (!is_array($person)) {
                continue;
            }
            $id = trim((string)($person['id'] ?? ''));
            $name = trim((string)($person['name'] ?? ''));
            if ($id !== '' && $name !== '') {
                $result[] = ['id' => $id, 'name' => $name];
            }
        }

        usort($result, static function (array $left, array $right): int {
            return strcasecmp($left['name'], $right['name']);
        });

        return $result;
    }
}
