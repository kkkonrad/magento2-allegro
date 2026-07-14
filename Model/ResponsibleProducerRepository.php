<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Model;

use Macopedia\Allegro\Model\ResourceModel\ProductOffer;

class ResponsibleProducerRepository
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
        $response = $this->resource->get('/sale/responsible-producers');
        $result = [];
        foreach ((array)($response['responsibleProducers'] ?? []) as $producer) {
            if (!is_array($producer)) {
                continue;
            }
            $id = trim((string)($producer['id'] ?? ''));
            $name = trim((string)($producer['name'] ?? ''));
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
