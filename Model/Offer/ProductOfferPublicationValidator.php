<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Model\Offer;

use Macopedia\Allegro\Api\Data\ProductOfferInterface;
use Magento\Framework\Exception\LocalizedException;

class ProductOfferPublicationValidator
{
    /**
     * @throws LocalizedException
     */
    public function validate(ProductOfferInterface $offer): void
    {
        if ($offer->getValidationErrors()) {
            throw new LocalizedException(
                __(
                    'The offer cannot be published because Allegro validation reported: %1',
                    $this->summarize($offer->getValidationErrors())
                )
            );
        }

        if (!in_array($offer->getStatus(), ['INACTIVE', 'ENDED'], true)) {
            throw new LocalizedException(__('Can not publish active or activating offers'));
        }
    }

    private function summarize(array $messages): string
    {
        $messages = array_values(array_filter(array_map('strval', $messages)));
        $visible = array_slice($messages, 0, 5);
        $summary = implode(' ', $visible);
        if (count($messages) > count($visible)) {
            $summary .= ' ' . (string)__('And %1 more error(s).', count($messages) - count($visible));
        }

        return $summary !== '' ? $summary : (string)__('The offer contains invalid data.');
    }
}
