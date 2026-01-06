<?php
namespace Ascorak\Faker\Api\Generator;

use Magento\Customer\Api\Data\AddressInterface;

interface CustomerAddressGeneratorInterface
{
    public function generate(): AddressInterface;
}