<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Model\OrderImporter;

use Macopedia\Allegro\Api\Data\CheckoutFormInterface;
use Macopedia\Allegro\Api\Data\OrderLogInterface;
use Macopedia\Allegro\Api\Data\OrderLogInterfaceFactory;
use Macopedia\Allegro\Api\OrderLogRepositoryInterface;
use Macopedia\Allegro\Model\OrderLogRepository;
use Macopedia\Allegro\Api\OrderRepositoryInterface;
use Macopedia\Allegro\Logger\Logger;
use Macopedia\Allegro\Model\OrderImporter\Creator;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Macopedia\Allegro\Model\Configuration;
use Macopedia\Allegro\Model\OrderRepository;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Lock\LockManagerInterface;
use Macopedia\Allegro\Model\OrderImportState;

class Processor
{
    /** @var Creator */
    private $creator;

    /** @var Logger */
    private $logger;

    /** @var OrderLogInterfaceFactory */
    private $orderLogFactory;

    /** @var OrderLogRepositoryInterface */
    private $orderLogRepository;

    /** @var OrderRepositoryInterface | OrderRepository */
    private $orderRepository;

    /** @var DateTime */
    private $date;

    /** @var Configuration */
    private $configuration;

    /** @var AllegroReservation */
    private $allegroReservation;

    /** @var ResourceConnection */
    private $resource;

    /** @var LockManagerInterface */
    private $lockManager;

    /** @var OrderImportState */
    private $importState;

    /**
     * Processor constructor.
     * @param Creator $creator
     * @param Logger $logger
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderLogRepositoryInterface $orderLogRepository
     * @param OrderLogInterfaceFactory $orderLogFactory
     * @param DateTime $date
     * @param Configuration $configuration
     * @param AllegroReservation $allegroReservation
     * @param ResourceConnection $resource
     * @param LockManagerInterface $lockManager
     * @param OrderImportState $importState
     */
    public function __construct(
        Creator $creator,
        Logger $logger,
        OrderRepositoryInterface $orderRepository,
        OrderLogRepositoryInterface $orderLogRepository,
        OrderLogInterfaceFactory $orderLogFactory,
        DateTime $date,
        Configuration $configuration,
        AllegroReservation $allegroReservation,
        ResourceConnection $resource,
        LockManagerInterface $lockManager,
        OrderImportState $importState
    ) {
        $this->creator = $creator;
        $this->logger = $logger;
        $this->orderRepository = $orderRepository;
        $this->orderLogRepository = $orderLogRepository;
        $this->orderLogFactory = $orderLogFactory;
        $this->date = $date;
        $this->configuration = $configuration;
        $this->allegroReservation = $allegroReservation;
        $this->resource = $resource;
        $this->lockManager = $lockManager;
        $this->importState = $importState;
    }

    /**
     * @param CheckoutFormInterface $checkoutForm
     * @throws \Exception
     */
    public function processOrder(CheckoutFormInterface $checkoutForm): void
    {
        $connection = $this->resource->getConnection();
        $checkoutFormId = $checkoutForm->getId();
        $lockName = 'macopedia_allegro_order_' . hash('sha256', $checkoutFormId);
        if (!$this->lockManager->lock($lockName, 5)) {
            throw new OrderProcessingException(
                "Order with id [{$checkoutFormId}] is already being processed"
            );
        }

        try {
            $this->importState->markProcessing($checkoutFormId);
            $connection->beginTransaction();
            $orderId = null;
            $processed = false;

            if ($checkoutForm->getStatus() === Status::ALLEGRO_READY_FOR_PROCESSING) {
                $existingOrder = $this->tryToGetOrder($checkoutFormId);
                if (!$existingOrder) {
                    $this->allegroReservation->compensateReservation($checkoutFormId);
                    $orderId = $this->tryCreateOrder($checkoutForm);
                } else {
                    $orderId = (int)$existingOrder->getEntityId();
                }
                $processed = true;
            } elseif ($checkoutForm->getStatus() === Status::ALLEGRO_CANCELLED) {
                $this->allegroReservation->compensateReservation($checkoutFormId);
                $processed = true;
            } else {
                $this->allegroReservation->placeReservation($checkoutForm);
                $this->importState->markNew($checkoutFormId);
            }

            $this->removeErrorLogIfExist($checkoutForm);
            if ($processed) {
                $this->importState->markImported($checkoutFormId, $orderId);
            }
            $connection->commit();
        } catch (\Throwable $e) {
            if ($connection->getTransactionLevel() > 0) {
                $connection->rollBack();
            }
            try {
                $this->addOrderWithErrorToTable($checkoutFormId, $e);
            } catch (\Throwable $logException) {
                $this->logger->apiFailure('Could not persist Allegro order import error', [
                    'checkout_form_id' => $checkoutFormId,
                    'exception_type' => get_class($logException),
                ]);
            }
            try {
                $this->importState->markFailure($checkoutFormId, $e);
            } catch (\Throwable $stateException) {
                $this->logger->apiFailure('Could not persist Allegro order import state', [
                    'checkout_form_id' => $checkoutFormId,
                    'exception_type' => get_class($stateException),
                ]);
            }
            throw $e;
        } finally {
            $this->lockManager->unlock($lockName);
        }
    }

    /**
     * @param $id
     * @return OrderInterface|null
     */
    protected function tryToGetOrder($id): ?OrderInterface
    {
        try {
            return $this->orderRepository->getByExternalId($id);
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }

    /**
     * @param string $checkoutFormId
     * @param \Throwable $e
     * @throws OrderProcessingException
     */
    public function addOrderWithErrorToTable(string $checkoutFormId, \Throwable $e): void
    {
        $date = $this->date->gmtDate();

        try {
            $orderLog = $this->orderLogRepository->getByCheckoutFormId($checkoutFormId);
            $orderLog->setNumberOfTries($orderLog->getNumberOfTries() + 1);
        } catch (NoSuchEntityException $noSuchEntityException) {
            $orderLog = $this->orderLogFactory->create();
            $orderLog->setDateOfFirstTry($date);
            $orderLog->setNumberOfTries(1);
        }

        $orderLog->setCheckoutFormId($checkoutFormId);
        $orderLog->setDateOfLastTry($date);

        $reason = [$this->safeErrorMessage($e->getMessage())];
        while ($e->getPrevious()) {
            $e = $e->getPrevious();
            $reason[] = $this->safeErrorMessage($e->getMessage());
        }

        $orderLog->setReason(implode("\n", $reason));

        try {
            $this->orderLogRepository->save($orderLog);
        } catch (CouldNotSaveException $e) {
            throw new OrderProcessingException(
                "Error while adding order with id [{$checkoutFormId}] to allegro_orders_with_errors table",
                1589540670,
                $e
            );
        }
    }

    /**
     * @param CheckoutFormInterface $checkoutForm
     * @throws \Exception
     */
    private function tryCreateOrder(CheckoutFormInterface $checkoutForm): ?int
    {
        $checkoutFormId = $checkoutForm->getId();
        try {
            $orderId = $this->creator->execute($checkoutForm);
            $this->logger->info("Order with id [$checkoutFormId] has been successfully created");
            return $orderId ? (int)$orderId : null;
        } catch (\Exception $e) {
            throw new OrderProcessingException(
                "Error while creating order with id [{$checkoutFormId}]",
                1589540684,
                $e
            );
        }
    }

    /**
     * @param CheckoutFormInterface $checkoutForm
     * @throws \Exception
     */
    private function removeErrorLogIfExist(CheckoutFormInterface $checkoutForm): void
    {
        $checkoutFormId = $checkoutForm->getId();
        try {
            $this->orderLogRepository->deleteByCheckoutFormId($checkoutFormId);
        } catch (CouldNotDeleteException $e) {
            throw new OrderProcessingException(
                "Error while deleting order with id [{$checkoutFormId}] from allegro_orders_with_errors table",
                1589540677,
                $e
            );
        }
    }

    /**
     * @param CheckoutFormInterface $checkoutForm
     * @return bool
     */
    public function validateCheckoutFormBoughtAtDate(CheckoutFormInterface $checkoutForm): bool
    {
        foreach ($checkoutForm->getLineItems() as $lineItem) {
            if ($lineItem->getBoughtAt() < $this->configuration->getInitializationTime()) {
                return false;
            }
        }

        return true;
    }

    private function safeErrorMessage(string $message): string
    {
        $message = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[email]', $message);
        $message = preg_replace('/\b(?:Bearer\s+)?[A-Za-z0-9+\/_=-]{32,}\b/i', '[secret]', (string)$message);
        return mb_substr(trim((string)$message), 0, 1000);
    }
}
