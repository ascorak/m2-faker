<?php

namespace Ascorak\Faker\Model\Faker\Generator;

use Ascorak\Faker\Api\Generator\CustomerAddressGeneratorInterface;
use Exception;
use Faker\Factory;
use Faker\Generator;
use Magento\Customer\Api\Data\AddressInterface;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Directory\Model\ResourceModel\Country\CollectionFactory as CountryCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class CustomerAddressGenerator implements CustomerAddressGeneratorInterface
{
    private Generator $faker;
    private string $locale;
    private array $cachedCountryIds = [];

    /**
     * Constructor
     * @param AddressInterfaceFactory $addressFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param CountryCollectionFactory $countryCollectionFactory
     */
    public function __construct(
        private AddressInterfaceFactory $addressFactory,
        private ScopeConfigInterface             $scopeConfig,
        private CountryCollectionFactory         $countryCollectionFactory
    ) {}

    /**
     * @inheritDoc
     */
    public function generate(): AddressInterface
    {
        $address = $this->addressFactory->create();
        $faker = $this->getFaker();
        $address->setFirstname($faker->firstName())
            ->setLastname($faker->lastName())
            ->setStreet([
                $faker->streetAddress(),
                $faker->optional(0.2)->secondaryAddress()
            ])->setCity($faker->city())
            ->setPostcode($faker->postcode())
            ->setTelephone($faker->phoneNumber())
            ->setIsActive(true)
            ->setCountryId($this->getCountryId($faker->country()));

        return $address;
    }

    /**
     * @return Generator
     */
    private function getFaker(): Generator
    {
        if (!isset($this->faker)) {
            $locale = $this->getLocale();
            $this->faker = Factory::create($locale);
        }

        return $this->faker;
    }

    /**
     * @return string
     */
    private function getLocale(): string
    {
        if (isset($this->locale)) {
            $this->locale = $this->scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORE, 0)?? 'en_US';
        }

        return $this->locale;
    }

    /**
     * @param string $countryName
     * @return int
     * @throws Exception
     */
    private function getCountryId(string $countryName): int
    {
        if (!isset($this->cachedCountryIds[$countryName])) {
            $locale = $this->getLocale();
            $collection = $this->countryCollectionFactory->create();
            foreach ($collection as $country) {
                if ($country->getName($locale) === $countryName) {
                    $this->cachedCountryIds[$countryName] = (int)$country->getId();
                    break;
                }
            }

            if (!isset($this->cachedCountryIds[$countryName])) {
                throw new Exception("Country $countryName not found");
            }
        }

        return $this->cachedCountryIds[$countryName];
    }
}