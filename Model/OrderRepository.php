<?php

namespace Macopedia\Allegro\Model;

use Macopedia\Allegro\Api\OrderRepositoryInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Payment\Api\Data\PaymentAdditionalInfoInterfaceFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterfaceFactory as SearchResultFactory;
use Magento\Sales\Model\ResourceModel\Metadata;
use Magento\Tax\Api\OrderTaxManagementInterface;
use Magento\Sales\Api\Data\OrderExtensionFactory;
use Macopedia\Allegro\Model\ResourceModel\Order as ResourceModel;

class OrderRepository extends \Magento\Sales\Model\OrderRepository implements OrderRepositoryInterface
{
    /** @var ResourceModel */
    private $resourceModel;

    /** @var OrderExtensionFactory */
    private $orderExtensionFactory;

    /** @var OrderTaxManagementInterface */
    private $orderTaxManagement;

    /**
     * OrderRepository constructor.
     * @param Metadata $metadata
     * @param SearchResultFactory $searchResultFactory
     * @param ResourceModel $resourceModel
     * @param CollectionProcessorInterface|null $collectionProcessor
     * @param OrderTaxManagementInterface|null $orderTaxManagement
     * @param PaymentAdditionalInfoInterfaceFactory|null $paymentAdditionalInfoFactory
     * @param JsonSerializer|null $serializer
     * @param OrderExtensionFactory|null $orderExtensionFactory
     */

    

    public function __construct(
        Metadata $metadata,
        SearchResultFactory $searchResultFactory,
        ResourceModel $resourceModel,
        CollectionProcessorInterface $collectionProcessor = null,
        OrderTaxManagementInterface $orderTaxManagement = null,
        PaymentAdditionalInfoInterfaceFactory $paymentAdditionalInfoFactory = null,
        JsonSerializer $serializer = null,
        OrderExtensionFactory $orderExtensionFactory = null
    ) {
        $this->resourceModel = $resourceModel;
        $this->orderExtensionFactory = $orderExtensionFactory;
        $this->orderTaxManagement = $orderTaxManagement;

        parent::__construct(
            $metadata,
            $searchResultFactory,
            $collectionProcessor,
            $orderTaxManagement,
            $paymentAdditionalInfoFactory,
            $serializer
        );
    }

    /**
     * @param string $externalId
     * @return OrderInterface
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\InputException
     */
    public function getByExternalId(string $externalId): OrderInterface
    {
        $orderId = $this->resourceModel->getIdByAllegroCheckoutFormId($externalId);
        if (!$orderId) {
            throw new NoSuchEntityException(
                __("The order that was requested doesn't exist. Verify the product and try again.")
            );
        }
        return $this->get($orderId);
    }
}
