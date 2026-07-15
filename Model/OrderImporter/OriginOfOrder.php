<?php


namespace Macopedia\Allegro\Model\OrderImporter;

use Magento\Sales\Model\Order;

/**
 * Class checks if order is from Allegro
 */
class OriginOfOrder
{
    const ALLEGRO = 'Allegro';

    /**
     * @param Order $order
     * @return bool
     */
    public function isOrderFromAllegro(Order $order)
    {
        if (stripos((string) $order->getOrderFrom(), self::ALLEGRO) !== false) {
            return true;
        }

        $extensionAttributes = $order->getExtensionAttributes();
        if ($extensionAttributes
            && stripos((string) $extensionAttributes->getOrderFrom(), self::ALLEGRO) !== false
        ) {
            return true;
        }

        return false;
    }
}
