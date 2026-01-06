<?php
namespace Ascorak\Faker\Api\Generator;

use Magento\Customer\Api\Data\AddressInterface;

interface CustomerAddressGeneratorInterface extends EntityGeneratorInterface
{
    public const string GENERATOR_CODE = 'customer_address';

    /**
     * Generate customer address
     * @return AddressInterface
     */
    public function generate(): AddressInterface;
}