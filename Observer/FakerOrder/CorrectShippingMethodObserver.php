<?php

namespace Ascorak\Faker\Observer\FakerOrder;

use BricoDepot\Owebia\Helper\ShippingHelper;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote\Address\RateFactory;

class CorrectShippingMethodObserver implements ObserverInterface
{
    /**
     * @param ShippingHelper $shippingHelper
     * @param RateFactory $rateFactory
     */
    public function __construct(
        private ShippingHelper $shippingHelper,
        private RateFactory $rateFactory
    ) {}

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        $quote = $observer->getEvent()->getData('quote');
        $productsShippingMethods = $this->getShippingMethods($quote);

        if (in_array($quote->getShippingAddress()->getShippingMethod(), $productsShippingMethods)) {
            return;
        }

        $this->applyNewShippingMethod($quote, $productsShippingMethods);
    }

    /**
     * @param CartInterface $quote
     * @return array
     */
    private function getShippingMethods(CartInterface $quote): array
    {
        $shippingMethods = [];
        $itemIdsToRemove = [];

        foreach ($quote->getItemsCollection() as $itemId => $item) {
            $product = $item->getProduct();
            $productShippingMethods = $this->shippingHelper->getShippingMethodsByProduct($product);
            if (empty($productShippingMethods)) {
                $itemIdsToRemove[] = $itemId;
                continue;
            }
            if (empty($shippingMethods)) {
                $shippingMethods = $productShippingMethods;
            } else {
                $shippingMethods = array_intersect($shippingMethods, $productShippingMethods);
            }
        }

        foreach ($itemIdsToRemove as $itemKey) {
            $quote->getItemsCollection()->removeItemByKey($itemKey);
        }

        if (empty($shippingMethods)) {
            $shippingMethods = $this->stripCartAndGetMethods($quote);
        }

        return $shippingMethods;
    }

    /**
     * @param CartInterface $quote
     * @param array $shippingMethods
     * @return void
     */
    private function applyNewShippingMethod(CartInterface $quote, array $shippingMethods): void
    {
        $shippingAddress = $quote->getShippingAddress();
        $shippingMethod = $shippingMethods[array_rand($shippingMethods)];
        $shippingRate = $this->rateFactory->create(['data' =>[
            'code' => $shippingMethod,
            'price' => rand(10, 100)
        ]]);

        $shippingAddress->setCollectShippingRates(true)
            ->collectShippingRates()
            ->setShippingMethod($shippingMethod)
            ->addShippingRate($shippingRate);
    }

    /**
     * @param CartInterface $quote
     * @return array
     */
    private function stripCartAndGetMethods(CartInterface $quote): array
    {
        $itemsToRemove = [];
        $i = 0;
        foreach ($quote->getItemsCollection() as $itemId => $item) {
            if ($i === 0) {
                $product = $item->getProduct();
                if (!$product) {
                    $itemsToRemove[] = $itemId;
                    continue;
                }
                $i++;
                continue;
            }
            $itemsToRemove[] = $itemId;
        }

        foreach ($itemsToRemove as $itemKey) {
            $quote->getItemsCollection()->removeItemByKey($itemKey);
        }

        return $this->shippingHelper->getShippingMethodsByProduct($product);
    }
}