<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Model\Consumer;

use Macopedia\Allegro\Api\Consumer\EntityMessageInterface;

class EntityMessage implements EntityMessageInterface
{
    /** @var int|null */
    private $entityId;

    public function setEntityId(int $entityId)
    {
        $this->entityId = $entityId;
        return $this;
    }

    public function getEntityId(): ?int
    {
        return $this->entityId;
    }
}
