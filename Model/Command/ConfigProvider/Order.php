<?php

namespace Ascorak\Faker\Model\Command\ConfigProvider;

use Ascorak\Faker\Console\Command\Fake;
use Ascorak\Faker\Model\Command\AbstractConfigProvider;

class Order extends AbstractConfigProvider
{
    public const IDX_NUMBER_OF_ORDERS = 'numberOfOrders';

    public const IDX_MIN_ITEMS_PER_ORDER = 'minItemsPerOrder';
    public const IDX_MAX_ITEMS_PER_ORDER = 'maxItemsPerOrder';

    public const IDX_MIN_QTY_PER_ITEM = 'minQtyPerItem';
    public const IDX_MAX_QTY_PER_ITEM = 'maxQtyPerItem';

    public const IDX_MAX_SIMPLE_PRODUCTS = 'maxSimpleProducts';
    public const IDX_MAX_CONFIGURABLE_PRODUCTS = 'maxConfigurableProducts';
    public const IDX_MAX_BUNDLE_PRODUCTS = 'maxBundleProducts';

    public const IDX_GUEST_MODE = 'guestMode'; // guest|both|customer
    public const GUEST_MODE_VALUE_GUEST = 'guest';
    public const GUEST_MODE_VALUE_BOTH = 'both';
    public const GUEST_MODE_VALUE_CUSTOMER = 'customer';

    /**
     * @inheritDoc
     */
    public function getConfig(): array
    {
        return $this->getDefaultConfig();
    }

    /**
     * @return array
     */
    private function getDefaultConfig(): array
    {
        return [
            self::IDX_NUMBER_OF_ORDERS => 10,
            self::IDX_MIN_ITEMS_PER_ORDER => 1,
            self::IDX_MAX_ITEMS_PER_ORDER => 10,
            self::IDX_MIN_QTY_PER_ITEM => 1,
            self::IDX_MAX_QTY_PER_ITEM => 10,
            self::IDX_MAX_SIMPLE_PRODUCTS => 10,
            self::IDX_MAX_CONFIGURABLE_PRODUCTS => 10,
            self::IDX_GUEST_MODE => 'both'
        ];
    }

    /**
     * @inheritDoc
     */
    public function applyConfig(Fake $command): void
    {
        // TODO: Implement applyConfig() method.
    }
}
