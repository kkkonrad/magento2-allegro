<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Model\Offer;

use Macopedia\Allegro\Api\Data\ParameterDefinitionInterface;
use Macopedia\Allegro\Api\ParameterDefinitionRepositoryInterface;
use Macopedia\Allegro\Api\ShippingRateRepositoryInterface;
use Macopedia\Allegro\Model\ResourceModel\Sale\OfferConfiguration;
use Magento\Framework\Exception\LocalizedException;

class OfferSaveRequestValidator
{
    /** @var ParameterDefinitionRepositoryInterface */
    private $parameterDefinitionRepository;

    /** @var ShippingRateRepositoryInterface */
    private $shippingRateRepository;

    /** @var OfferConfiguration */
    private $offerConfiguration;

    public function __construct(
        ParameterDefinitionRepositoryInterface $parameterDefinitionRepository,
        ShippingRateRepositoryInterface $shippingRateRepository,
        OfferConfiguration $offerConfiguration
    ) {
        $this->parameterDefinitionRepository = $parameterDefinitionRepository;
        $this->shippingRateRepository = $shippingRateRepository;
        $this->offerConfiguration = $offerConfiguration;
    }

    /**
     * @throws LocalizedException
     */
    public function validate(OfferSaveRequest $request): void
    {
        $this->validateLocation($request->location);
        $request->parameters = $this->filterOfferParameters($request->categoryId, $request->parameters);
        $shippingRateName = $this->validateShippingRate($request->shippingRateId);
        $this->validateOneFulfillment($shippingRateName, $request);
        $this->validateTaxSettings($request->categoryId, $request->taxSettings);
        if ($request->responsibleProducer) {
            $this->validateGpsrDictionaryEntry(
                $request->responsibleProducer,
                $this->offerConfiguration->getResponsibleProducers(),
                (string)__('responsible producer')
            );
        }
        if ($request->responsiblePerson) {
            $this->validateGpsrDictionaryEntry(
                $request->responsiblePerson,
                $this->offerConfiguration->getResponsiblePersons(),
                (string)__('responsible person')
            );
        }
    }

    private function validateLocation(array $location): void
    {
        foreach (['countryCode', 'city', 'postCode'] as $field) {
            if (trim((string)($location[$field] ?? '')) === '') {
                throw new LocalizedException(__('Allegro origin field "%1" is required.', $field));
            }
        }
        if (($location['countryCode'] ?? '') === 'PL'
            && trim((string)($location['province'] ?? '')) === ''
        ) {
            throw new LocalizedException(__('Allegro origin province is required for Poland.'));
        }
    }

    private function filterOfferParameters(string $categoryId, array $parameters): array
    {
        $allowed = [];
        foreach ($this->parameterDefinitionRepository->getListByCategoryId((int)$categoryId) as $definition) {
            if ($definition instanceof ParameterDefinitionInterface && !$definition->getDescribesProduct()) {
                $allowed[(string)$definition->getId()] = $definition;
            }
        }

        $filtered = [];
        foreach ($parameters as $key => $parameter) {
            $id = is_array($parameter) && isset($parameter['id'])
                ? (string)$parameter['id']
                : (string)$key;
            if ($id === '' || !isset($allowed[$id])) {
                continue;
            }
            if (is_array($parameter) && isset($parameter['id'])) {
                $filtered[] = $parameter;
            } else {
                $filtered[] = $this->normalizeFormParameter($allowed[$id], $parameter);
            }
        }

        return $filtered;
    }

    /**
     * @param mixed $value
     */
    private function normalizeFormParameter(ParameterDefinitionInterface $definition, $value): array
    {
        $id = (string)$definition->getId();
        if ($definition->getFrontendType() === ParameterDefinitionInterface::FRONTEND_TYPE_RANGE) {
            $range = is_array($value) ? $value : [];
            return [
                'id' => $id,
                'rangeValue' => [
                    'from' => $range['minValue'] ?? $range['from'] ?? null,
                    'to' => $range['maxValue'] ?? $range['to'] ?? null,
                ],
            ];
        }

        $values = is_array($value) ? $value : [$value];
        return $definition->getFrontendType() === ParameterDefinitionInterface::FRONTEND_TYPE_VALUES
            ? ['id' => $id, 'values' => $values]
            : ['id' => $id, 'valuesIds' => $values];
    }

    private function validateShippingRate(string $shippingRateId): string
    {
        if ($shippingRateId === '') {
            throw new LocalizedException(__('A delivery price list is required.'));
        }
        foreach ($this->shippingRateRepository->getList() as $shippingRate) {
            if ((string)$shippingRate->getId() === $shippingRateId) {
                return (string)$shippingRate->getName();
            }
        }

        throw new LocalizedException(__('The selected Allegro delivery price list is no longer available.'));
    }

    private function validateOneFulfillment(string $shippingRateName, OfferSaveRequest $request): void
    {
        if (stripos($shippingRateName, 'One Fulfillment') !== 0) {
            return;
        }
        if ($request->invoice !== 'VAT') {
            throw new LocalizedException(__('One Fulfillment requires the VAT invoice option.'));
        }
        if ($request->quantity !== 0) {
            throw new LocalizedException(
                __('One Fulfillment stock is managed by Allegro. Set the offer quantity to 0.')
            );
        }
        if (!$request->taxSettings) {
            throw new LocalizedException(__('One Fulfillment requires VAT tax settings.'));
        }
    }

    private function validateTaxSettings(string $categoryId, array $taxSettings): void
    {
        if (!$taxSettings) {
            return;
        }

        $countryCode = (string)($taxSettings['rates'][0]['countryCode'] ?? '');
        $selectedRate = (string)($taxSettings['rates'][0]['rate'] ?? '');
        $selectedSubject = (string)($taxSettings['subject'] ?? '');
        $available = $this->offerConfiguration->getTaxSettings($categoryId, $countryCode);

        $subjects = array_column((array)($available['subjects'] ?? []), 'value');
        if (!in_array($selectedSubject, $subjects, true)) {
            throw new LocalizedException(__('The selected VAT subject is not supported in this Allegro category.'));
        }

        $rates = [];
        foreach ((array)($available['rates'] ?? []) as $countryRates) {
            if (($countryRates['countryCode'] ?? '') === $countryCode) {
                $rates = array_column((array)($countryRates['values'] ?? []), 'value');
                break;
            }
        }
        if (!in_array($selectedRate, $rates, true)) {
            throw new LocalizedException(
                __('VAT rate %1 is not supported for country %2 in this Allegro category.', $selectedRate, $countryCode)
            );
        }
    }

    private function validateGpsrDictionaryEntry(array $selected, array $available, string $label): void
    {
        if (!$selected) {
            return;
        }

        $field = !empty($selected['id']) ? 'id' : 'name';
        $value = trim((string)($selected[$field] ?? ''));
        foreach ($available as $item) {
            if (is_array($item) && trim((string)($item[$field] ?? '')) === $value) {
                return;
            }
        }

        throw new LocalizedException(
            __('The selected Allegro %1 does not exist in the account dictionary.', $label)
        );
    }
}
