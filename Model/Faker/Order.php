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
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\ScopeInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;

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
     * @param ScopeConfigInterface $scopeConfig
     * @param ProductRepositoryInterface $productRepository
     * @param ProductCollectionFactory $productCollectionFactory
     * @param CustomerRepositoryInterface $customerRepository
     * @param CustomerCollectionFactory $customerCollectionFactory
     * @param GeneratorProviderInterface $generatorProvider
     */
    public function __construct(
        private CartManagementInterface $quoteManagement,
        private QuoteFactory $quoteFactory,
        private ScopeConfigInterface $scopeConfig,
        private ProductRepositoryInterface $productRepository,
        private ProductCollectionFactory $productCollectionFactory,
        private CustomerRepositoryInterface $customerRepository,
        private CustomerCollectionFactory $customerCollectionFactory,
        private GeneratorProviderInterface $generatorProvider
    ) {}

    /**
     * @param array $config
     * @param SymfonyStyle $io
     * @return void
     * @throws NoSuchEntityException
     */
    public function generateFakeData(array $config, SymfonyStyle $io): array
    {
        $errors = [];
        $this->initCachedData();

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

        for($i = 0; $i < $customerNumber; $i++) {
            try {
                if ($i < $customerNumber) {
                    $order = $this->createOrder($config, $this->getRandomCustomer());
                } else {
                    $order = $this->createOrder($config);
                }
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }

            $progressBar->advance();
        }
        $progressBar->finish();

        return $errors;
    }

    private function createOrder(array $config, ?CustomerInterface $customer = null): OrderInterface
    {
        // create quote
        $quote = $this->quoteFactory->create();
        $quote->setStoreId(0); // admin store
        $quote->setInventoryProcessed(false);

        // add product
        $itemNumber = rand(1, $config[OrderConfigProvider::IDX_MAX_ITEMS_PER_ORDER]);
        for($i = 0; $i < $itemNumber; $i++) {
            $product = $this->getRandomProduct($this->getProductType());
            $quote->addProduct($product, rand(1, $config[OrderConfigProvider::IDX_MAX_QTY_PER_ITEM]));
        }

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
                $customerAddresses[array_rand($customerAddresses)],
                $customerAddresses[array_rand($customerAddresses)]
            );
        } else {
            // TODO: add dummy customer data to quote
        }
        $quote->setCollectShippingRates(1)->collectShippingRates();

        // add shipment and payment
        $quote->setShippingMethod($this->getRandomShippingMethod());
        $paymentMethod = $this->getRandomPaymentMethod();
        $quote->setPaymentMethod($paymentMethod);

        // submit quote
        $quote->save();
        $quote->getPayment()->importData(['method' => $paymentMethod]);
        $quote->collectTotals()->save();
        return $this->quoteManagement->submit($quote);
    }

    private function initCachedData(): void
    {
        $this->initShippingMethod();
        $this->initPaymentMethod();
        $this->initCustomers();
        $this->initProducts();
    }

    /**
     * @return CustomerInterface[]
     */
    private function initCustomers(): void
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

        $this->cachedCustomers = array_fill_keys($collection->getAllIds(), null);
        $this->initedCustomers = true;
    }

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

    private function initProducts(): void
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
        $this->cachedSimpleProducts = array_fill_keys($simpleProductCollection->getAllIds(), null);

        $configurableProductCollection = $this->productCollectionFactory->create();
        $configurableProductCollection->addAttributeToSelect(['entity_id'])
            ->addFieldToFilter('type_id', ['eq' => 'configurable'])
            ->setCurPage(1)
            ->setPageSize(self::CACHE_COLLECTION_SIZE);
        $configurableProductCollection->getSelect()->order('RAND()');
        $this->cachedConfigurableProducts = array_fill_keys($configurableProductCollection->getAllIds(), null);

        $bundleProductCollection = $this->productCollectionFactory->create();
        $bundleProductCollection->addAttributeToSelect(['entity_id'])
            ->addFieldToFilter('type_id', ['eq' => 'bundle'])
            ->setCurPage(1)
            ->setPageSize(self::CACHE_COLLECTION_SIZE);
        $bundleProductCollection->getSelect()->order('RAND()');
        $this->cachedBundleProducts = array_fill_keys($bundleProductCollection->getAllIds(), null);

        $this->initedProducts = true;
    }

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
     * @return void
     */
    private function initShippingMethod(): void
    {
        if ($this->initedShippingMethods) {
            return;
        }

        $this->cachedShippingMethods = array_keys($this->scopeConfig->getValue('carriers', ScopeInterface::SCOPE_STORE, 0));
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
     * @return void
     */
    private function initPaymentMethod(): void
    {
        if ($this->initedPaymentMethods) {
            return;
        }

        $this->cachedPaymentMethods = array_keys($this->scopeConfig->getValue('payment', ScopeInterface::SCOPE_STORE, 0));
        $this->initedPaymentMethods = true;
    }

    /**
     * @return string
     */
    private function getRandomPaymentMethod(): string
    {
        return $this->cachedPaymentMethods[rand(0, count($this->cachedPaymentMethods) - 1)];
    }

    /**
     * @return EntityGeneratorInterface
     */
    private function getAddressGenerator(): EntityGeneratorInterface
    {
        return $this->generatorProvider->getGenerator(CustomerAddressGeneratorInterface::GENERATOR_CODE);
    }
}
