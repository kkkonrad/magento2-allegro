<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Model\Offer;

use Macopedia\Allegro\Api\Data\ImageInterface;
use Macopedia\Allegro\Api\Data\ImageInterfaceFactory;
use Macopedia\Allegro\Api\ImageRepositoryInterface;
use Macopedia\Allegro\Api\ProductOfferRepositoryInterface;
use Macopedia\Allegro\Logger\Logger;
use Macopedia\Allegro\Model\Api\Credentials;
use Macopedia\Allegro\Model\Api\ProductOfferFactory;
use Macopedia\Allegro\Model\OfferMappingService;
use Magento\Framework\Exception\LocalizedException;

class ProductOfferSaveService
{
    /** @var OfferFormDataMapper */
    private $mapper;

    /** @var ProductOfferFactory */
    private $productOfferFactory;

    /** @var ImageInterfaceFactory */
    private $imageFactory;

    /** @var ImageRepositoryInterface */
    private $imageRepository;

    /** @var ProductOfferRepositoryInterface */
    private $productOfferRepository;

    /** @var OfferMappingService */
    private $offerMappingService;

    /** @var Credentials */
    private $credentials;

    /** @var Logger */
    private $logger;

    public function __construct(
        OfferFormDataMapper $mapper,
        ProductOfferFactory $productOfferFactory,
        ImageInterfaceFactory $imageFactory,
        ImageRepositoryInterface $imageRepository,
        ProductOfferRepositoryInterface $productOfferRepository,
        OfferMappingService $offerMappingService,
        Credentials $credentials,
        Logger $logger
    ) {
        $this->mapper = $mapper;
        $this->productOfferFactory = $productOfferFactory;
        $this->imageFactory = $imageFactory;
        $this->imageRepository = $imageRepository;
        $this->productOfferRepository = $productOfferRepository;
        $this->offerMappingService = $offerMappingService;
        $this->credentials = $credentials;
        $this->logger = $logger;
    }

    /**
     * @return array{
     *     offer_id:string,
     *     mapping_saved:bool,
     *     validation_checked:bool,
     *     validation_errors:string[],
     *     validation_warnings:string[]
     * }
     */
    public function execute(array $formData): array
    {
        $request = $this->mapper->map($formData);
        $offer = $this->productOfferFactory->create();
        $isUpdate = isset($formData['id'])
            && is_scalar($formData['id'])
            && trim((string)$formData['id']) !== '';
        if ($isUpdate) {
            $offer->setId(trim((string)$formData['id']));
        }
        $offer->setName($request->name)
            ->setProductId($request->catalogProductId)
            ->setSellerId($this->credentials->getClientId())
            ->setPrice($request->price)
            ->setQuantity($request->quantity)
            ->setCategory($request->categoryId)
            ->setParameters($request->parameters)
            ->setSellingMode([
                'format' => 'BUY_NOW',
                'price' => ['amount' => (string)$request->price, 'currency' => 'PLN'],
            ])
            ->setLocation($request->location)
            ->setDeliveryOptions([
                'shipping_rates_id' => $request->shippingRateId,
                'handling_time' => $request->handlingTime,
            ])
            ->setPayments(['invoice' => $request->invoice])
            ->setImages($this->uploadImages($request->images))
            ->setAfterSalesServices($request->afterSalesServices)
            ->setExternalId('magento-product-' . $request->magentoProductId);

        if (!$isUpdate) {
            $offer->setStatus('INACTIVE');
        }

        if ($request->description !== null) {
            $offer->setDescription([
                'sections' => [[
                    'items' => [['type' => 'TEXT', 'content' => $request->description]],
                ]],
            ]);
        }

        $offerId = $this->productOfferRepository->save($offer);
        $mappingSaved = $this->offerMappingService->saveMapping(
            $request->magentoProductId,
            $offerId,
            $request->catalogProductId
        );

        $validationChecked = false;
        $validationErrors = [];
        $validationWarnings = [];
        try {
            $savedOffer = $this->productOfferRepository->get($offerId);
            $validationChecked = true;
            $validationErrors = $savedOffer->getValidationErrors();
            $validationWarnings = $savedOffer->getValidationWarnings();
        } catch (LocalizedException $exception) {
            // The Allegro write and local mapping already succeeded. A temporary
            // validation read failure must not encourage the operator to retry
            // creation and produce a duplicate offer.
            $this->logger->apiFailure('Could not verify saved Allegro offer validation', [
                'offer_id' => $offerId,
                'exception_type' => get_class($exception),
            ]);
        }

        return [
            'offer_id' => $offerId,
            'mapping_saved' => $mappingSaved,
            'validation_checked' => $validationChecked,
            'validation_errors' => $validationErrors,
            'validation_warnings' => $validationWarnings,
        ];
    }

    private function uploadImages(array $imagesData): array
    {
        $this->validateImages($imagesData);
        $images = [];
        foreach ($imagesData as $imageData) {
            if (!is_array($imageData)) {
                continue;
            }

            if (empty($imageData['path']) && !empty($imageData['url'])) {
                $url = trim((string)$imageData['url']);
                if (filter_var($url, FILTER_VALIDATE_URL)) {
                    $images[] = ['url' => $url];
                }
                continue;
            }

            /** @var ImageInterface $image */
            $image = $this->imageFactory->create();
            $image->setRawData($imageData);
            $image->setStatus(ImageInterface::STATUS_LOCAL);
            $this->imageRepository->save($image);
            if ($image->getUrl()) {
                $images[] = ['url' => $image->getUrl()];
            }
        }

        return $images;
    }

    /**
     * @throws LocalizedException
     */
    private function validateImages(array $imagesData): void
    {
        foreach ($imagesData as $imageData) {
            if (!is_array($imageData)) {
                throw new LocalizedException(__('Each offer image must be a valid image record.'));
            }

            if (isset($imageData['url']) && !is_scalar($imageData['url'])) {
                throw new LocalizedException(__('Offer image URL must be a string.'));
            }

            if (!empty($imageData['url'])) {
                $url = trim((string)$imageData['url']);
                if (!filter_var($url, FILTER_VALIDATE_URL)
                    || !in_array((string)parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)
                ) {
                    throw new LocalizedException(__('Offer image URL must use HTTP or HTTPS.'));
                }
                continue;
            }

            $path = $imageData['path'] ?? $imageData['file'] ?? null;
            if (!is_scalar($path) || trim((string)$path) === '') {
                throw new LocalizedException(__('Offer image file is missing.'));
            }
        }
    }
}
