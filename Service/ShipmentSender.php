<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Service;

use Macopedia\Allegro\Model\ResourceModel\Order\CheckoutForm;
use Magento\Sales\Api\Data\ShipmentInterface;

class ShipmentSender
{
    private const CARRIERS = [
        'ups' => 'UPS', 'inpost' => 'INPOST', 'dhl' => 'DHL', 'gls' => 'GLS',
        'ruch' => 'RUCH', 'poczta_polska' => 'POCZTA_POLSKA', 'dpd' => 'DPD',
        'pocztex' => 'POCZTEX', 'fedex' => 'FEDEX', 'tnt_express' => 'TNT_EXPRESS',
        'db_schenker' => 'DB_SCHENKER', 'raben' => 'RABEN', 'geis' => 'GEIS', 'dts' => 'DTS',
    ];

    /** @var CheckoutForm */
    private $checkoutForm;

    public function __construct(CheckoutForm $checkoutForm)
    {
        $this->checkoutForm = $checkoutForm;
    }

    public function state(ShipmentInterface $shipment): array
    {
        $lineItems = [];
        foreach ($shipment->getItems() as $item) {
            $lineItemId = $item->getOrderItem()->getData('allegro_line_item_id');
            if ($lineItemId) {
                $lineItems[] = (string)$lineItemId;
            }
        }
        sort($lineItems);

        $tracks = [];
        foreach ($shipment->getTracks() as $track) {
            if ($track->getTrackNumber()) {
                $tracks[] = [(string)$track->getCarrierCode(), (string)$track->getTrackNumber()];
            }
        }
        sort($tracks);

        return ['line_items' => $lineItems, 'tracks' => $tracks];
    }

    public function send(ShipmentInterface $shipment): int
    {
        $order = $shipment->getOrder();
        $checkoutFormId = $order->getExternalId() ?: $order->getExtensionAttributes()->getExternalId();
        if (!$checkoutFormId) {
            return 0;
        }

        $baseData = ['lineItems' => []];
        foreach ($shipment->getItems() as $item) {
            $lineItemId = $item->getOrderItem()->getData('allegro_line_item_id');
            if ($lineItemId) {
                $baseData['lineItems'][] = ['id' => (string)$lineItemId];
            }
        }

        $sent = 0;
        foreach ($shipment->getTracks() as $track) {
            if (!$track->getTrackNumber()) {
                continue;
            }
            $data = $baseData;
            $carrierCode = (string)$track->getCarrierCode();
            $data['carrierId'] = self::CARRIERS[$carrierCode] ?? 'OTHER';
            if ($data['carrierId'] === 'OTHER') {
                $data['carrierName'] = $carrierCode;
            }
            $data['waybill'] = (string)$track->getTrackNumber();
            $this->checkoutForm->shipment((string)$checkoutFormId, $data);
            $sent++;
        }

        return $sent;
    }
}
