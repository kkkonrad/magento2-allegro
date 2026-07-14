<?php

declare(strict_types = 1);

namespace Macopedia\Allegro\Cron;

use Macopedia\Allegro\Logger\Logger;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Macopedia\Allegro\Model\OffersMapping;
use Macopedia\Allegro\Model\Operations\CronJobRunner;

class CleanOffersMapping
{
    const OFFERS_MAPPING_CRON_CONFIG_KEY = 'allegro/order/offers_mapping_cron_enabled';

    /** @var Logger */
    protected $logger;

    /** @var ScopeConfigInterface */
    protected $scopeConfig;

    /** @var OffersMapping */
    protected $offersMapping;

    /** @var CronJobRunner */
    private $jobRunner;

    /**
     * @param Logger $logger
     * @param ScopeConfigInterface $scopeConfig
     * @param OffersMapping $offersMapping
     */
    public function __construct(
        Logger $logger,
        ScopeConfigInterface $scopeConfig,
        OffersMapping $offersMapping,
        CronJobRunner $jobRunner
    ) {
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->offersMapping = $offersMapping;
        $this->jobRunner = $jobRunner;
    }

    public function execute()
    {
        if ($this->scopeConfig->getValue(self::OFFERS_MAPPING_CRON_CONFIG_KEY)) {
            $this->logger->info('Cronjob clean offers mapping is executed.');
            try {
                $result = $this->jobRunner->run('clean_offer_mappings', function (): array {
                    return $this->offersMapping->clean();
                });
                if ($result === null) {
                    return;
                }
                $this->logger->info(sprintf(
                    'Allegro offer mapping cleanup checked %d, removed %d, failed %d.',
                    $result['checked'],
                    $result['removed'],
                    $result['failed']
                ));
            } catch (\Exception $e) {
                $this->logger->apiFailure('Could not clean old Allegro offer mappings', [
                    'exception_type' => get_class($e),
                ]);
                throw $e;
            }
        }
    }
}
