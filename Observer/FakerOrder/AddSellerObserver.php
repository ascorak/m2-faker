<?php

namespace Ascorak\Faker\Observer\FakerOrder;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Smile\Seller\Api\Data\SellerInterface;
use Smile\Seller\Model\ResourceModel\Seller\CollectionFactory as SellerCollectionFactory;

class AddSellerObserver implements ObserverInterface
{
    private const MAX_SELLER_CACHED = 20;

    /** @var array $cachedSellers */
    private array $cachedSellers = [];

    /** @var bool $initedSellers */
    private bool $initedSellers = false;

    /**
     * FakerOrderQuoteCreated constructor.
     * @param SellerCollectionFactory $sellerCollectionFactory
     */
    public function __construct(
        private SellerCollectionFactory $sellerCollectionFactory
    ) {}

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        $quote = $observer->getEvent()->getData('quote');

        $quote->setCustomerSellerId($this->getRandomSeller()->getId());
        return $this;
    }

    /**
     * @return SellerInterface
     * @throws NoSuchEntityException
     */
    private function getRandomSeller(): SellerInterface
    {
        $this->initSellers();
        $randKey = array_rand($this->cachedSellers);

        return $this->cachedSellers[$randKey];
    }

    /**
     * @return void
     */
    private function initSellers(): void
    {
        if ($this->initedSellers) {
            return;
        }

        $sellerCollection = $this->sellerCollectionFactory->create()
            ->addFieldToSelect('entity_id')
            ->setPageSize(self::MAX_SELLER_CACHED)
            ->setCurPage(1);
        $sellerCollection->getSelect()->order('RAND()');

        foreach ($sellerCollection as $seller) {
            $this->cachedSellers[$seller->getId()] = $seller;
        }
        $this->initedSellers = true;
    }
}