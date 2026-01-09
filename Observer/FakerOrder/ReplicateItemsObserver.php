<?php

namespace Ascorak\Faker\Observer\FakerOrder;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class ReplicateItemsObserver implements ObserverInterface
{
    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        $quote = $observer->getEvent()->getData('quote');
        $quote->setItems($quote->getAllItems());
    }
}