<?php

namespace Macopedia\Allegro\Model;

use Macopedia\Allegro\Logger\Logger;
use Macopedia\Allegro\Model\ResourceModel\Order\CheckoutForm;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Model\Order;

class AllegroOrderStatus
{
    const STATUSES_MAPPING_CONFIG_KEY = 'allegro/order/mapping';

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var CheckoutForm
     */
    protected $checkoutForm;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param CheckoutForm $checkoutForm
     * @param Logger $logger
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        CheckoutForm $checkoutForm,
        Logger $logger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->checkoutForm = $checkoutForm;
        $this->logger = $logger;
    }

    /**
     * @param Order $order
     * @return void
     */
    public function updateOrderStatus(Order $order)
    {
        $targetStatus = $this->getTargetStatus($order);
        $checkoutFormId = $order->getExternalId() ?: $order->getExtensionAttributes()->getExternalId();
        if ($targetStatus === null || !$checkoutFormId) {
            return false;
        }

        $this->checkoutForm->changeOrderStatus($checkoutFormId, $targetStatus);
        $this->logger->info('Allegro order fulfillment status has been updated', [
            'order_id' => (int)$order->getId(),
        ]);
        return true;
    }

    public function getTargetStatus(Order $order): ?string
    {
        $rawMapping = $this->scopeConfig->getValue(self::STATUSES_MAPPING_CONFIG_KEY);
        $decoded = is_string($rawMapping) ? json_decode($rawMapping, true) : null;
        if (!is_array($decoded)) {
            return null;
        }

        $statusesMapping = array_column($decoded, 'allegro_code', 'magento_code');
        $status = $statusesMapping[(string)$order->getStatus()] ?? null;
        return is_scalar($status) && (string)$status !== '' ? (string)$status : null;
    }
}
