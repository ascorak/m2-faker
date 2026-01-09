<?php
namespace Ascorak\Faker\Model\Faker;

use Ascorak\Faker\Api\FakerInterface;
use Ascorak\Faker\Api\Generator\CustomerAddressGeneratorInterface;
use Ascorak\Faker\Api\Generator\EntityGeneratorInterface;
use Ascorak\Faker\Api\Generator\GeneratorProviderInterface;
use Ascorak\Faker\Model\Command\ConfigProvider\Order as OrderConfigProvider;
use Exception;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Customer\Api\Data\AddressInterface as CustomerAddressInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\Data\AddressInterface as QuoteAddressInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\Quote\ItemFactory as QuoteItemFactory;
use Magento\Quote\Model\Quote\AddressFactory as QuoteAddressFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\ScopeInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Magento\Quote\Model\Quote\Address\RateFactory;

/**
 * @author Grare Olivier <grare.o@gmail.com>
 */
class Order implements FakerInterface
{
    private const CACHE_COLLECTION_SIZE = 20;

    private array $cachedCustomers = [];
    private bool $initedCustomers = false;

    private array $cachedSimpleProducts = [];
    private array $cachedConfigurableProducts = [];
    private array $cachedBundleProducts = [];
    private bool $initedProducts = false;

    private array $cachedShippingMethods = [];
    private bool $initedShippingMethods = false;

    private array $cachedPaymentMethods = [];
    private bool $initedPaymentMethods = false;

    /**
     * Order constructor.
     *
     * @param CartManagementInterface $quoteManagement
     * @param QuoteFactory $quoteFactory
     * @param QuoteItemFactory $quoteItemFactory
     * @param QuoteAddressFactory $quoteAddressFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param EventManagerInterface $eventManager
     * @param ProductRepositoryInterface $productRepository
     * @param ProductCollectionFactory $productCollectionFactory
     * @param CustomerRepositoryInterface $customerRepository
     * @param CustomerCollectionFactory $customerCollectionFactory
     * @param GeneratorProviderInterface $generatorProvider
     */
    public function __construct(
        private CartManagementInterface $quoteManagement,
        private QuoteFactory $quoteFactory,
        private QuoteItemFactory $quoteItemFactory,
        private QuoteAddressFactory $quoteAddressFactory,
        private ScopeConfigInterface $scopeConfig,
        private EventManagerInterface $eventManager,
        private ProductRepositoryInterface $productRepository,
        private ProductCollectionFactory $productCollectionFactory,
        private CustomerRepositoryInterface $customerRepository,
        private CustomerCollectionFactory $customerCollectionFactory,
        private GeneratorProviderInterface $generatorProvider,
        private RateFactory $rateFactory
    ) {}

    /**
     * @param array $config
     * @param SymfonyStyle $io
     * @return array
     * @throws LocalizedException
     */
    public function generateFakeData(array $config, SymfonyStyle $io): array
    {
        $errors = [];
        $successes = [];
        $this->initCachedData($config);

        $progressBar = $io->createProgressBar($config[OrderConfigProvider::IDX_NUMBER_OF_ORDERS]);
        $progressBar->setFormat('<info>%message%</info> %current%/%max% [%bar%] %percent:3s%% %elapsed% %memory:6s%');
        $progressBar->start();
        $progressBar->setMessage('Orders ...');
        $progressBar->display();

        $customerNumber = match ($config[OrderConfigProvider::IDX_GUEST_MODE]) {
            OrderConfigProvider::GUEST_MODE_VALUE_GUEST => 0,
            OrderConfigProvider::GUEST_MODE_VALUE_CUSTOMER => $config[OrderConfigProvider::IDX_NUMBER_OF_ORDERS],
            OrderConfigProvider::GUEST_MODE_VALUE_BOTH => rand(1, $config[OrderConfigProvider::IDX_NUMBER_OF_ORDERS]),
            default => throw new Exception(__("Problem\n"))
        };

        for($i = 0; $i < $config[OrderConfigProvider::IDX_NUMBER_OF_ORDERS]; $i++) {
            try {
                if ($i < $customerNumber) {
                    $order = $this->createOrder($config, $this->getRandomCustomer());
                } else {
                    $order = $this->createOrder($config);
                }
                $successes[] = __('Created Order #%1.', $order->getIncrementId());
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }

            $progressBar->advance();
        }
        $progressBar->finish();

        return ['errors' => $errors, 'successes' => $successes];
    }

    /**
     * @param array $config
     * @param CustomerInterface|null $customer
     * @return OrderInterface
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function createOrder(array $config, ?CustomerInterface $customer = null): OrderInterface
    {
        // create quote
        $quote = $this->quoteFactory->create();
        $quote->setStoreId(0) // admin store
            ->setIsActive(true)
            ->setInventoryProcessed(true);
        $this->eventManager->dispatch('faker_order_quote_created', ['quote' => $quote]);

        // add product
        $itemNumber = rand(1, $config[OrderConfigProvider::IDX_MAX_ITEMS_PER_ORDER]);
        for($i = 0; $i < $itemNumber; $i++) {
            $quote->addItem($this->createQuoteItem($config));
        }
        $this->eventManager->dispatch('faker_order_quote_add_products_after', ['quote' => $quote]);

        // add customer data
        if ($customer) {
            $customerAddresses = $customer->getAddresses();
            if (empty($customerAddresses)) {
                $addressGenerator = $this->getAddressGenerator();
                $customerAddress = $addressGenerator->generate();
                $customerAddresses[] = $customerAddress;
                $customer->setAddresses([$customerAddress]);
            }
            $quote->assignCustomerWithAddressChange(
                $customer,
                $this->convertToQuoteAddress($customerAddresses[array_rand($customerAddresses)]),
                $this->convertToQuoteAddress($customerAddresses[array_rand($customerAddresses)])
            );
        } else {
            // TODO: add dummy customer data to quote
        }
        $this->eventManager->dispatch('faker_order_quote_add_customer_after', ['quote' => $quote]);

        // add shipment and payment
        $this->applyShippingMethod($quote);
        $paymentMethod = $this->getRandomPaymentMethod();
        $quote->getPayment()->setMethod($paymentMethod);

        // submit quote
        $quote->collectTotals();
        $quote->save();
        $this->eventManager->dispatch('faker_order_quote_submit_before', ['quote' => $quote]);
        $return = $this->quoteManagement->submit($quote);
        return $return;
    }

    /**
     * @param array $config
     * @return CartItemInterface
     * @throws NoSuchEntityException
     */
    private function createQuoteItem(array $config): CartItemInterface
    {
        $product = $this->getRandomProduct($this->getProductType());
        $quoteItem = $this->quoteItemFactory->create();
        $quoteItem->setProduct($product)
            ->setQty(rand(1, $config[OrderConfigProvider::IDX_MAX_ITEMS_PER_ORDER]))
            ->setIsInStock(true);
        // TODO: add product options for configurable and bundle products

        return $quoteItem;
    }

    /**
     * @param CustomerAddressInterface $address
     * @return QuoteAddressInterface
     */
    private function convertToQuoteAddress(CustomerAddressInterface $address): QuoteAddressInterface
    {
        $quoteAddress = $this->quoteAddressFactory->create();
        return $quoteAddress->importCustomerAddressData($address);
    }

    /**
     * @param CartInterface $quote
     * @return void
     */
    private function applyShippingMethod(CartInterface $quote): void
    {
        $shippingMethod = $this->getRandomShippingMethod();
        $shippingAddress = $quote->getShippingAddress();
        $shippingRate = $this->rateFactory->create(['data' =>[
            'code' => $shippingMethod,
            'price' => rand(10, 100)
        ]]);

        $shippingAddress->setCollectShippingRates(true)
            ->collectShippingRates()
            ->setShippingMethod($shippingMethod)
            ->addShippingRate($shippingRate);
        $this->eventManager->dispatch('faker_order_quote_apply_shipping_method_after', ['quote' => $quote]);
    }

    /**
     * @param array $config
     * @return void
     * @throws LocalizedException
     */
    private function initCachedData(array $config): void
    {
        $this->initShippingMethod($config);
        $this->initPaymentMethod($config);
        $this->initCustomers($config);
        $this->initProducts($config);
    }

    /**
     * @param array $config
     * @return void
     * @throws LocalizedException
     */
    private function initCustomers(array $config): void
    {
        if ($this->initedCustomers) {
            return;
        }
        $collection = $this->customerCollectionFactory->create();
        $collection->addAttributeToSelect(['entity_id'])
            ->setCurPage(1)
            ->setPageSize(self::CACHE_COLLECTION_SIZE);
        $collection->getSelect()
            ->joinInner(['adrs' => $collection->getTable('customer_address_entity')], 'adrs.parent_id = e.entity_id', [])
            ->order('RAND()');
        $this->eventManager->dispatch('faker_order_customer_collection_before_load', ['collection' => $collection]);

        $this->cachedCustomers = array_fill_keys($collection->getAllIds(), null);
        $this->initedCustomers = true;
    }

    /**
     * @return CustomerInterface|null
     * @throws LocalizedException
     */
    private function getRandomCustomer(): ?CustomerInterface
    {
        $randKey = array_rand($this->cachedCustomers);
        if (is_null($this->cachedCustomers[$randKey])) {
            try {
                $this->cachedCustomers[$randKey] = $this->customerRepository->getById($randKey);
            } catch (NoSuchEntityException $e) {
                return null;
            }
        }

        return $this->cachedCustomers[$randKey];
    }

    /**
     * @param array $config
     * @return void
     */
    private function initProducts(array $config): void
    {
        if ($this->initedProducts) {
            return;
        }
        $simpleProductCollection = $this->productCollectionFactory->create();
        $simpleProductCollection->addAttributeToSelect(['entity_id'])
            ->addFieldToFilter('type_id', ['eq' => 'simple'])
            ->setCurPage(1)
            ->setPageSize(self::CACHE_COLLECTION_SIZE);
        $simpleProductCollection->getSelect()->order('RAND()');
        $this->eventManager->dispatch('faker_order_simple_product_collection_before_load', ['collection' => $simpleProductCollection]);
        $this->cachedSimpleProducts = array_fill_keys($simpleProductCollection->getAllIds(), null);

        $configurableProductCollection = $this->productCollectionFactory->create();
        $configurableProductCollection->addAttributeToSelect(['entity_id'])
            ->addFieldToFilter('type_id', ['eq' => 'configurable'])
            ->setCurPage(1)
            ->setPageSize(self::CACHE_COLLECTION_SIZE);
        $configurableProductCollection->getSelect()->order('RAND()');
        $this->eventManager->dispatch('faker_order_configurable_product_collection_before_load', ['collection' => $configurableProductCollection]);
        $this->cachedConfigurableProducts = array_fill_keys($configurableProductCollection->getAllIds(), null);

        $bundleProductCollection = $this->productCollectionFactory->create();
        $bundleProductCollection->addAttributeToSelect(['entity_id'])
            ->addFieldToFilter('type_id', ['eq' => 'bundle'])
            ->setCurPage(1)
            ->setPageSize(self::CACHE_COLLECTION_SIZE);
        $bundleProductCollection->getSelect()->order('RAND()');
        $this->eventManager->dispatch('faker_order_bundle_product_collection_before_load', ['collection' => $bundleProductCollection]);
        $this->cachedBundleProducts = array_fill_keys($bundleProductCollection->getAllIds(), null);

        $this->initedProducts = true;
    }

    /**
     * @return string
     */
    private function getProductType(): string
    {
        // TODO: make it radom to get simple|configurable|bundle
        return 'simple';
    }

    /**
     * @param string $type
     * @return ProductInterface
     * @throws NoSuchEntityException
     * @throws Exception
     */
    private function getRandomProduct(string $type): ProductInterface
    {
        $productCache = match($type) {
            'simple' => 'cachedSimpleProducts',
            'configurable' => 'cachedConfigurableProducts',
            'bundle' => 'cachedBundleProducts',
            default => throw new Exception(__("Problem\n"))
        };

        $randKey = array_rand($this->$productCache);
        if (is_null($this->$productCache[$randKey])) {
            $this->$productCache[$randKey] = $this->productRepository->getById($randKey);
        }

        return $this->$productCache[$randKey];
    }

    /**
     * @param array $config
     * @return void
     */
    private function initShippingMethod(array $config): void
    {
        if ($this->initedShippingMethods) {
            return;
        }

        $carriers = $this->scopeConfig->getValue('carriers', ScopeInterface::SCOPE_STORE, 0);
        foreach ($carriers as $shippingMethodCode => $carrier) {
            if (!isset($carrier['active']) || !$carrier['active']) {
                continue;
            }
            $this->cachedShippingMethods[] = $shippingMethodCode;
        }

        $this->initedShippingMethods = true;
    }

    /**
     * @return string
     */
    private function getRandomShippingMethod(): string
    {
        return $this->cachedShippingMethods[rand(0, count($this->cachedShippingMethods) - 1)];
    }

    /**
     * @param array $config
     * @return void
     */
    private function initPaymentMethod(array $config): void
    {
        if ($this->initedPaymentMethods) {
            return;
        }

        $paymentMethods = array_keys($this->scopeConfig->getValue('payment', ScopeInterface::SCOPE_STORE, 0));
        foreach ($paymentMethods as $paymentMethod) {
            if ($this->scopeConfig->getValue(
                sprintf('payment/%s/active', $paymentMethod),
                ScopeInterface::SCOPE_STORE,
                0
            )) {
                $this->cachedPaymentMethods[] = $paymentMethod;
            }
        }
        $this->initedPaymentMethods = true;
    }

    /**
     * @return string
     */
    private function getRandomPaymentMethod(): string
    {
        //return $this->cachedPaymentMethods[rand(0, count($this->cachedPaymentMethods) - 1)];
        return 'checkmo';
    }

    /**
     * @return EntityGeneratorInterface
     */
    private function getAddressGenerator(): EntityGeneratorInterface
    {
        return $this->generatorProvider->getGenerator(CustomerAddressGeneratorInterface::GENERATOR_CODE);
    }
}
