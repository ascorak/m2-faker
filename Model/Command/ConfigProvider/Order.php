<?php

namespace Ascorak\Faker\Model\Command\ConfigProvider;

use Ascorak\Faker\Console\Command\Fake;
use Ascorak\Faker\Model\Command\AbstractConfigProvider;

class Order extends AbstractConfigProvider
{
    private const NUMBER_OF_ORDERS = 10;

    private const MIN_ITEMS_PER_ORDER = 1;
    private const MAX_ITEMS_PER_ORDER = 10;

    private const MIN_QTY_PER_ITEM = 1;
    private const MAX_QTY_PER_ITEM = 10;

    private const MAX_SIMPLE_PRODUCTS = 10;
    private const MAX_CONFIGURABLE_PRODUCTS = 10;
    private const MAX_BUNDLE_PRODUCTS = 10;

    private const GUEST_MODE = 'both'; // guest|both|customer

    /**
     * @inheritDoc
     */
    public function getConfig(): array
    {
        return [
            'numberOfOrders' => self::NUMBER_OF_ORDERS,
            'minItemsPerOrder' => self::MIN_ITEMS_PER_ORDER,
            'maxItemsPerOrder' => self::MAX_ITEMS_PER_ORDER,
            'minQtyPerItem' => self::MIN_QTY_PER_ITEM,
            'maxQtyPerItem' => self::MAX_QTY_PER_ITEM,
            'maxSimpleProducts' => self::MAX_SIMPLE_PRODUCTS,
            'maxConfigurableProducts' => self::MAX_CONFIGURABLE_PRODUCTS,
            'maxBundleProducts' => self::MAX_BUNDLE_PRODUCTS,
            'guestMode' => self::GUEST_MODE
        ];
    }

    public function applyConfig(Fake $command): void
    {
        // TODO: Implement applyConfig() method.
    }
}
