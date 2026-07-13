<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Controller\Adminhtml\Catalog;

use Macopedia\Allegro\Api\CategoryRepositoryInterface;
use Macopedia\Allegro\Api\Data\CategoryInterface;
use Macopedia\Allegro\Api\Data\ParameterDefinitionInterface;
use Macopedia\Allegro\Api\Data\ProductInterface;
use Macopedia\Allegro\Api\ParameterDefinitionRepositoryInterface;
use Macopedia\Allegro\Api\ProductCatalogRepositoryInterface;
use Macopedia\Allegro\Logger\Logger;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Reflection\DataObjectProcessor;

class Data extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Macopedia_Allegro::offer_manage';

    /** @var JsonFactory */
    private $jsonFactory;

    /** @var ProductCatalogRepositoryInterface */
    private $productCatalogRepository;

    /** @var CategoryRepositoryInterface */
    private $categoryRepository;

    /** @var ParameterDefinitionRepositoryInterface */
    private $parameterDefinitionRepository;

    /** @var DataObjectProcessor */
    private $dataObjectProcessor;

    /** @var Logger */
    private $logger;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        ProductCatalogRepositoryInterface $productCatalogRepository,
        CategoryRepositoryInterface $categoryRepository,
        ParameterDefinitionRepositoryInterface $parameterDefinitionRepository,
        DataObjectProcessor $dataObjectProcessor,
        Logger $logger
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->productCatalogRepository = $productCatalogRepository;
        $this->categoryRepository = $categoryRepository;
        $this->parameterDefinitionRepository = $parameterDefinitionRepository;
        $this->dataObjectProcessor = $dataObjectProcessor;
        $this->logger = $logger;
    }

    public function execute(): Json
    {
        /** @var Json $result */
        $result = $this->jsonFactory->create();

        try {
            $operation = (string)$this->getRequest()->getParam('operation');
            if ($operation === 'search') {
                $data = $this->serializeList(
                    $this->productCatalogRepository->search(['ean' => (string)$this->getRequest()->getParam('ean')]),
                    ProductInterface::class
                );
            } elseif ($operation === 'root') {
                $data = $this->serializeList($this->categoryRepository->getRootList(), CategoryInterface::class);
            } elseif ($operation === 'children') {
                $data = $this->serializeList(
                    $this->categoryRepository->getList((string)$this->getRequest()->getParam('category_id')),
                    CategoryInterface::class
                );
            } elseif ($operation === 'parents') {
                $data = $this->serializeList(
                    $this->categoryRepository->getAllParents((string)$this->getRequest()->getParam('category_id')),
                    CategoryInterface::class
                );
            } elseif ($operation === 'parameters') {
                $definitions = $this->parameterDefinitionRepository->getListByCategoryId(
                    (int)$this->getRequest()->getParam('category_id')
                );
                $data = $this->serializeList(
                    array_values(array_filter(
                        $definitions,
                        static function (ParameterDefinitionInterface $definition): bool {
                            return !$definition->getDescribesProduct();
                        }
                    )),
                    ParameterDefinitionInterface::class
                );
            } else {
                $this->getResponse()->setHttpResponseCode(400);
                return $result->setData(['message' => (string)__('Unsupported Allegro catalog operation.')]);
            }

            return $result->setData($data);
        } catch (\Throwable $exception) {
            $this->logger->apiFailure('Allegro admin catalog request failed', [
                'operation' => (string)$this->getRequest()->getParam('operation'),
                'exception_type' => get_class($exception),
            ]);
            $this->getResponse()->setHttpResponseCode(400);
            return $result->setData([
                'message' => $exception instanceof \Magento\Framework\Exception\LocalizedException
                    ? $exception->getMessage()
                    : (string)__('Could not load data from Allegro.'),
            ]);
        }
    }

    private function serializeList(array $items, string $interface): array
    {
        $result = [];
        foreach ($items as $item) {
            if (is_object($item)) {
                $result[] = $this->dataObjectProcessor->buildOutputDataArray($item, $interface);
            }
        }
        return $result;
    }
}
