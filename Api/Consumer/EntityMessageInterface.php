<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Api\Consumer;

interface EntityMessageInterface
{
    /**
     * @param int $entityId
     * @return void
     */
    public function setEntityId(int $entityId);

    /**
     * @return int|null
     */
    public function getEntityId(): ?int;
}
