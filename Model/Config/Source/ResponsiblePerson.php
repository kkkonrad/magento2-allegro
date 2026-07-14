<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Model\Config\Source;

use Macopedia\Allegro\Model\ResponsiblePersonRepository;
use Magento\Framework\Data\OptionSourceInterface;

class ResponsiblePerson implements OptionSourceInterface
{
    /** @var ResponsiblePersonRepository */
    private $repository;

    public function __construct(ResponsiblePersonRepository $repository)
    {
        $this->repository = $repository;
    }

    public function toOptionArray(): array
    {
        $options = [[
            'value' => '',
            'label' => (string)__('-- Not applicable --'),
        ]];

        try {
            foreach ($this->repository->getList() as $person) {
                $options[] = [
                    'value' => $person['id'],
                    'label' => $person['name'],
                ];
            }
        } catch (\Throwable $exception) {
            // Keep the form usable when Allegro is temporarily unavailable.
        }

        return $options;
    }
}
