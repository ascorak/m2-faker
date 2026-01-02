<?php

namespace Ascorak\Faker\Model\Faker\ConfigProvider;

use Ascorak\Faker\Model\Faker\AbstractConfigProvider;

class Order extends AbstractConfigProvider
{
    private const NUMBER_OF_ORDERS = 10;

    private const MIN_ITEMS_PER_ORDER = 1;
    private const MAX_ITEMS_PER_ORDER = 10;

    private const MIN_QTY_PER_ITEM = 1;
    private const MAX_QTY_PER_ITEM = 10;

    private const MAX_SIMPLE_PRODUCTS = 10;
    private const MAX_BUNDLE_PRODUCTS = 10;

    /**
     * @inheritDoc
     */
    public function getConfig(string $code): array
    {
        // TODO: Implement getConfig() method.
    }
}