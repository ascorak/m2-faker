<?php
namespace Ascorak\Faker\Model\Faker;

use Ascorak\Faker\Api\Command\ConfigProviderInterface;
use Ascorak\Faker\Api\FakerInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\CatalogInventory\Helper\Stock;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\Data\ShippingMethodInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\QuoteManagement;
use Magento\Store\Model\ResourceModel\Store\CollectionFactory as StoreCollectionFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;

/**
 * @author Alexandre Granjeon <alexandre.granjeon@gmail.com>
 */
class Order implements FakerInterface
{
    private const CACHE_COLLECTION_SIZE = 20;
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;
    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;
    /**
     * @var ProductFactory
     */
    protected $productFactory;
    /**
     * @var QuoteManagement
     */
    protected $quoteManagement;
    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;
    /**
     * @var Stock $stockFilter
     */
    protected $stockFilter;

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
     * @param ScopeConfigInterface        $scopeConfig
     * @param StoreCollectionFactory      $storeCollectionFactory
     * @param CustomerRepositoryInterface $customerRepository
     * @param StoreManagerInterface       $storeManager
     * @param QuoteFactory                $quoteFactory
     * @param ProductFactory              $productFactory
     * @param CartManagementInterface     $quoteManagement
     * @param SearchCriteriaBuilder       $searchCriteriaBuilder
     * @param ProductCollectionFactory           $productCollectionFactory
     * @param Stock                       $stockFilter
     */
    public function __construct(
        StoreCollectionFactory $storeCollectionFactory,
        StoreManagerInterface $storeManager,
        QuoteFactory $quoteFactory,
        ProductFactory $productFactory,
        CartManagementInterface $quoteManagement,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        Stock $stockFilter,
        private ScopeConfigInterface $scopeConfig,
        private ProductRepositoryInterface $productRepository,
        private ProductCollectionFactory $productCollectionFactory,
        private CustomerRepositoryInterface $customerRepository,
        private CustomerCollectionFactory $customerCollectionFactory
    ) {
        //parent::__construct($scopeConfig, $storeCollectionFactory);

        $this->storeManager             = $storeManager;
        $this->quoteFactory             = $quoteFactory;
        $this->productFactory           = $productFactory;
        $this->quoteManagement          = $quoteManagement;
        $this->searchCriteriaBuilder    = $searchCriteriaBuilder;
        $this->stockFilter              = $stockFilter;
    }

    /**
     * @param OutputInterface $output
     *
     * @return void
     */
    public function generateFakeData(ConfigProviderInterface $configProvider, SymfonyStyle $io): void
    {
        $this->initCachedData();
        $config = $configProvider->getConfig();

        $progressBar = $io->createProgressBar($config['numberOfOrders']);
        $progressBar->setFormat('<info>%message%</info> %current%/%max% [%bar%] %percent:3s%% %elapsed% %memory:6s%');
        $progressBar->start();
        $progressBar->setMessage('Orders ...');
        $progressBar->display();

        list($customerNumber, $guestNumber) = match ($config['guestMode']) {
            'guest' => ['customerNumber' => 0, 'guestNumber' => $config['numberOfOrders']],
            'customer' => ['customerNumber' => $config['numberOfOrders'], 'guestNumber' => 0],
            'both' => (function() use($config) {
                $customerNumber = rand(1, $config['numberOfOrders']);
                $guestNumber = $config['numberOfOrders'] - $customerNumber;
                return ['customerNumber' => $customerNumber, 'guestNumber' => $guestNumber];
            })(),
            default => throw new \Exception(__("Problem\n"))
        };

        for($i = 0; $i < $customerNumber; $i++) {
            $this->createCustomerOrder($config);
        }

        for($i = 0; $i < $guestNumber; $i++) {
            $this->createGuestOrder($config);
        }

        $e = 0;
        foreach ($customers as $customer) {
            $e++;
            $store = $this->storeManager->getStore($customer->getStoreId());
            if (!$store->getIsActive()) {
                continue;
            }
            $availableShippingMethods = explode(',', $this->getStoreConfig('faker/order/shipping_method', $store));
            $availablePaymentMethods  = explode(',', $this->getStoreConfig('faker/order/payment_method', $store));

            $numberOfOrders = $this->getStoreConfig('faker/order/number', $store);
            for ($i = 0; $i < $numberOfOrders; $i++) {
                $shippingMethod = $availableShippingMethods[array_rand($availableShippingMethods)];
                $paymentMethod  = $availablePaymentMethods[array_rand($availablePaymentMethods)];

                $quote = $this->quoteFactory->create();
                $quote->setStore($store);
                $quote->setCurrency();
                $quote->assignCustomer($customer);

                $shippingAddress = $customer->getAddresses();
                $numberOfItems   = rand(
                    (int)$this->getStoreConfig('faker/order/min_items_number', $store),
                    (int)$this->getStoreConfig('faker/order/max_items_number', $store)
                );

                for ($i = 0; $i < $numberOfItems; $i++) {
                    try {
                        $product = $this->productFactory->create()->load($productIds[array_rand($productIds)]);
                        $quote->addProduct(
                            $product,
                            rand(1, 3) // qty
                        );
                    } catch (\Exception $exception) {
                    }
                }
                if (count($quote->getItemsCollection()->getItems()) == 0) {
                    continue;
                }

                $quote->getBillingAddress()->importCustomerAddressData(reset($shippingAddress));
                $quote->getShippingAddress()->importCustomerAddressData(reset($shippingAddress));

                $shippingAddress = $quote->getShippingAddress();
                $shippingAddress->setCollectShippingRates(1)->collectShippingRates()->setShippingMethod(
                    $shippingMethod
                );

                $quote->setPaymentMethod($paymentMethod);
                $quote->setInventoryProcessed(false);
                try {
                    $quote->save();
                    $quote->getPayment()->importData(['method' => $paymentMethod]);
                    $quote->collectTotals()->save();
                    $this->quoteManagement->submit($quote);
                } catch (\Exception $exception) {
                }
            }
            $progressBar->advance();
        }
        $progressBar->finish();
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
            ->join(['adrs' => $collection->getTable('customer_address_entity')], 'adrs.parent_id = man_table.entity_id', [], 'inner')
            ->order('RAND()');

        $this->cachedCustomers = array_fill_keys($collection->getAllIds(), null);
        $this->initedCustomers = true;
    }

    private function getRandomCustomer(): Customer
    {
        $randKey = array_rand($this->cachedCustomers);
        if (is_null($this->cachedCustomers[$randKey])) {
            $this->cachedCustomers[$randKey] = $this->customerRepository->getById($randKey);
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
            ->addFieldToFilter('type', ['eq' => 'simple'])
            ->setCurPage(1)
            ->setPageSize(self::CACHE_COLLECTION_SIZE);
        $simpleProductCollection->getSelect()->order('RAND()');
        $this->cachedSimpleProducts = array_fill_keys($simpleProductCollection->getAllIds(), null);

        $configurableProductCollection = $this->productCollectionFactory->create();
        $configurableProductCollection->addAttributeToSelect(['entity_id'])
            ->addFieldToFilter('type', ['eq' => 'configurable'])
            ->setCurPage(1)
            ->setPageSize(self::CACHE_COLLECTION_SIZE);
        $configurableProductCollection->getSelect()->order('RAND()');
        $this->cachedConfigurableProducts = array_fill_keys($configurableProductCollection->getAllIds(), null);

        $bundleProductCollection = $this->productCollectionFactory->create();
        $bundleProductCollection->addAttributeToSelect(['entity_id'])
            ->addFieldToFilter('type', ['eq' => 'bundle'])
            ->setCurPage(1)
            ->setPageSize(self::CACHE_COLLECTION_SIZE);
        $bundleProductCollection->getSelect()->order('RAND()');
        $this->cachedBundleProducts = array_fill_keys($bundleProductCollection->getAllIds(), null);

        $this->initedProducts = true;
    }

    private function getRandomProduct(string $type): ProductInterface
    {
        $productCache = match($type) {
            'simple' => 'cachedSimpleProducts',
            'configurable' => 'cachedConfigurableProducts',
            'bundle' => 'cachedBundleProducts',
            default => throw new \Exception(__("Problem\n"))
        };

        $randKey = array_rand($this->$productCache);
        if (is_null($this->$productCache[$randKey])) {
            $this->$productCache[$randKey] = $this->productRepository->getById($randKey);
        }

        return $this->$productCache[$randKey];
    }

    private function initShippingMethod(): void
    {
        if ($this->initedShippingMethods) {
            return;
        }

        $this->cachedShippingMethods = array_keys($this->scopeConfig->getValue('carriers', ScopeInterface::SCOPE_STORE, 0));
        $this->initedShippingMethods = true;
    }

    private function getRandomShippingMethod(): string
    {
        return $this->cachedShippingMethods[rand(0, count($this->cachedShippingMethods) - 1)];
    }

    private function initPaymentMethod(): void
    {
        if ($this->initedPaymentMethods) {
            return;
        }

        $this->cachedPaymentMethods = array_keys($this->scopeConfig->getValue('payment', ScopeInterface::SCOPE_STORE, 0));
        $this->initedPaymentMethods = true;
    }

    private function getRandomPaymentMethod(): string
    {
        return $this->cachedPaymentMethods[rand(0, count($this->cachedPaymentMethods) - 1)];
    }
}
