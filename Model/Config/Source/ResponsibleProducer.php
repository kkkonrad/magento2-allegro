<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Model\Config\Source;

use Macopedia\Allegro\Model\ResponsibleProducerRepository;
use Magento\Framework\Data\OptionSourceInterface;

class ResponsibleProducer implements OptionSourceInterface
{
    /** @var ResponsibleProducerRepository */
    private $repository;

    public function __construct(ResponsibleProducerRepository $repository)
    {
        $this->repository = $repository;
    }

    public function toOptionArray(): array
    {
        $options = [[
            'value' => '',
            'label' => (string)__('-- Select responsible producer --'),
        ]];

        try {
            foreach ($this->repository->getList() as $producer) {
                $options[] = [
                    'value' => $producer['id'],
                    'label' => $producer['name'],
                ];
            }
        } catch (\Throwable $exception) {
            // Keep the form usable when Allegro is temporarily unavailable.
        }

        return $options;
    }
}
