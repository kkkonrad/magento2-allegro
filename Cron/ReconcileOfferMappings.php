<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Cron;

use Macopedia\Allegro\Model\Configuration;
use Macopedia\Allegro\Model\OfferMappingService;

class ReconcileOfferMappings
{
    /** @var OfferMappingService */
    private $offerMappingService;

    /** @var Configuration */
    private $configuration;

    public function __construct(
        OfferMappingService $offerMappingService,
        Configuration $configuration
    ) {
        $this->offerMappingService = $offerMappingService;
        $this->configuration = $configuration;
    }

    public function execute(): void
    {
        if (!$this->configuration->isOfferMappingReconciliationCronEnabled()) {
            return;
        }

        $this->offerMappingService->reconcilePending();
    }
}
