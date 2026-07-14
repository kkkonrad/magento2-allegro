<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Cron;

use Macopedia\Allegro\Model\Configuration;
use Macopedia\Allegro\Model\OfferMappingService;
use Macopedia\Allegro\Model\Operations\CronJobRunner;

class ReconcileOfferMappings
{
    /** @var OfferMappingService */
    private $offerMappingService;

    /** @var Configuration */
    private $configuration;

    /** @var CronJobRunner */
    private $jobRunner;

    public function __construct(
        OfferMappingService $offerMappingService,
        Configuration $configuration,
        CronJobRunner $jobRunner
    ) {
        $this->offerMappingService = $offerMappingService;
        $this->configuration = $configuration;
        $this->jobRunner = $jobRunner;
    }

    public function execute(): void
    {
        if (!$this->configuration->isOfferMappingReconciliationCronEnabled()) {
            return;
        }

        $this->jobRunner->run('reconcile_offer_mappings', function (): array {
            return $this->offerMappingService->reconcilePending();
        });
    }
}
