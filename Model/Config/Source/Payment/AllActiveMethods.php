<?php
namespace Ascorak\Faker\Model\Config\Source\Payment;

use Magento\Framework\Option\ArrayInterface;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\Config;

/**
 * @author Alexandre Granjeon <alexandre.granjeon@gmail.com>
 */
class AllActiveMethods implements ArrayInterface
{
    /**
     * @var Data
     */
    protected $_paymentData;
    /**
     * @var Config $_paymentConfig
     */
    protected $_paymentConfig;

    /**
     * AllActiveMethods constructor
     *
     * @param Data  $paymentData
     * @param Config $paymentConfig
     */
    public function __construct(Data $paymentData, Config $paymentConfig)
    {
        $this->_paymentData   = $paymentData;
        $this->_paymentConfig = $paymentConfig;
    }

    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        $methods = [];
        $groupRelations = [];

        foreach ($this->_paymentData->getPaymentMethods() as $code => $paymentMethod) {
            if (!isset($paymentMethod['active']) || $paymentMethod['active'] != 1) {
                continue;
            }
            if (isset($paymentMethod['title'])) {
                $methods[$code] = $paymentMethod['title'];
            } else {
                $methods[$code] = $this->_paymentData->getMethodInstance($code)->getConfigData('title');
            }
            if (isset($paymentMethod['group'])) {
                $groupRelations[$code] = $paymentMethod['group'];
            }
        }
        $groups = $this->_paymentConfig->getGroups();
        foreach ($groups as $code => $title) {
            $methods[$code] = $title;
        }
        asort($methods);
        $labelValues = [];
        foreach ($methods as $code => $title) {
            $labelValues[$code] = [];
        }
        foreach ($methods as $code => $title) {
            if (isset($groups[$code])) {
                $labelValues[$code]['label'] = $title;
                if (!isset($labelValues[$code]['value'])) {
                    $labelValues[$code]['value'] = null;
                }
            } elseif (isset($groupRelations[$code])) {
                unset($labelValues[$code]);
                $labelValues[$groupRelations[$code]]['value'][$code] = ['value' => $code, 'label' => $title];
            } else {
                $labelValues[$code] = ['value' => $code, 'label' => $title];
            }
        }

        return $labelValues;
    }
}
