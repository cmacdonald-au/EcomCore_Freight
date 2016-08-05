<?php
/**
 * EcomCore Freight Extension
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * Originally based on Magento Tablerate Shipping code and Auctionmaid Matrixrate.
 * @copyright  Copyright (c) 2008 Auction Maid (http://www.auctionmaid.com)
 * @author     Karen Baker <enquiries@auctionmaid.com>
 *
 * Subsequently based on Fontis Australia Shipping code.
 * @copyright  Copyright (c) 2014 Fontis Pty. Ltd. (http://www.fontis.com.au)
 * @author     Chris Norton
 *
 * @category   EcomCore
 * @package    EcomCore_Freight
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Custom Freight model
 *
 * @category   EcomCore
 * @package    EcomCore_Freight
 */

class EcomCore_Freight_Model_Rate
    extends Mage_Shipping_Model_Carrier_Abstract
    implements Mage_Shipping_Model_Carrier_Interface
{
    /**
     * @var string
     */
    protected $_code = 'eccfreight';

    /**
     * @var string
     */
    protected $_default_condition_name = 'package_weight';

    protected $_conditionNames = array();

    public function __construct()
    {
        Mage::log(__METHOD__.'() Starting');
        parent::__construct();
        foreach ($this->getCode('condition_name') as $k=>$v) {
            $this->_conditionNames[] = $k;
        }
    }

    public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {

        if (!$this->getConfigFlag('active')) {
            Mage::log(__METHOD__.'() Module disabled');
            return false;
        }

        if (!$request->getConditionName()) {
            $request->setConditionName($this->getConfigData('condition_name') ? $this->getConfigData('condition_name') : $this->_default_condition_name);
        }

        $result = Mage::getModel('shipping/rate_result');
        $rates = $this->getRate($request);

        if (is_array($rates)) {
            Mage::log(__METHOD__.'() Multiple options');
            foreach ($rates as $rate) {
                if (!empty($rate) && $rate['price'] >= 0) {
                    /** @var Mage_Shipping_Model_Rate_Result_Method $method */
                    $method = Mage::getModel('shipping/rate_result_method');

                    $method->setCarrier('eccfreight');
                    $method->setCarrierTitle($this->getConfigData('title'));

                    if ($this->_getChargeCode($rate)) {
                        $methodCode = strtolower(str_replace(' ', '_', $this->_getChargeCode($rate)));
                    } else {
                        $methodCode = strtolower(str_replace(' ', '_', $rate['delivery_type']));
                    }
                    $method->setMethod($methodCode);

                    if ($this->getConfigData('name')) {
                        $method->setMethodTitle($this->getConfigData('name'));
                    } else {
                        $method->setMethodTitle($rate['delivery_type']);
                    }

                    $method->setMethodChargeCode($rate['charge_code']);

                    $shippingPrice = $this->getFinalPriceWithHandlingFee($rate['price']);
                    if (isset($rate['surcharge']) && !empty($rate['surcharge'])) {
                        if (strpos($rate['surcharge'], '%') !== false) {
                            $rate['surcharge'] = str_replace('%', '', $rate['surcharge']);
                            $rate['surcharge'] = (float)substr($rate['surcharge'], 0, -1);
                            if ($rate['surcharge'] > 0) {
                                Mage::log(__METHOD__.'() Applying a surcharge of %'.$rate['surcharge'].' to '.$rate['price']);
                                $shippingPrice += ($shippingPrice / 100 * $rate['surcharge']);
                            }
                        } else {
                            $rate['surcharge'] = (float)$rate['surcharge'];
                            Mage::log(__METHOD__.'() Applying a surcharge of $'.$rate['surcharge'].' to '.$rate['price']);
                            $shippingPrice += $rate['surcharge'];
                        }
                    }

                    $method->setPrice($shippingPrice);
                    $method->setDeliveryType($rate['delivery_type']);

                    $result->append($method);
                }
            }
        } else {
            Mage::log(__METHOD__.'() No rates found for this request');
        }

        return $result;
    }

    /**
     * @param array $rate
     * @return mixed
     */
    protected function _getChargeCode($rate)
    {
        /* Is this customer is in a ~business~ group ? */
        $isBusinessCustomer = (
            Mage::getSingleton('customer/session')->isLoggedIn()
            AND
            in_array(
                Mage::getSingleton('customer/session')->getCustomerGroupId(),
                explode(
                    ',',
                    Mage::getStoreConfig('doghouse_eparcelexport/charge_codes/business_groups')
                )
            )
        );

        if ($isBusinessCustomer) {
            if (isset($rate['charge_code_business']) && $rate['charge_code_business']) {
                return $rate['charge_code_business'];
            }

            return Mage::getStoreConfig('doghouse_eparcelexport/charge_codes/default_charge_code_business');
        } else {
            if (isset($rate['charge_code_individual']) && $rate['charge_code_individual']) {
                return $rate['charge_code_individual'];
            }

            return Mage::getStoreConfig('doghouse_eparcelexport/charge_codes/default_charge_code_individual');
        }
    }

    public function getRate(Mage_Shipping_Model_Rate_Request $request)
    {
        return Mage::getResourceModel('eccfreight/rate')->getRate($request);
    }

    public function getCode($type, $code = '')
    {
        $helper = Mage::helper('shipping');
        $codes = array(
            'condition_name' => array(
                'package_weight' => $helper->__('Weight vs. Destination'),
            ),
            'condition_name_short' => array(
                'package_weight' => $helper->__('Weight (and above)'),
            ),
        );

        if (!isset($codes[$type])) {
            throw Mage::exception('Mage_Shipping', $helper->__('Invalid Table Rate code type: %s', $type));
        }

        if ('' === $code) {
            return $codes[$type];
        }

        if (!isset($codes[$type][$code])) {
            throw Mage::exception('Mage_Shipping', $helper->__('Invalid Table Rate code for type %s: %s', $type, $code));
        }

        return $codes[$type][$code];
    }

    /**
     * Get allowed shipping methods
     *
     * @return array
     */
    public function getAllowedMethods()
    {
        return array('eccfreight' => $this->getConfigData('name'));
    }

    /*
     * Tracking code
     *
     * @return bool
     */
    public function isTrackingAvailable()
    {
        return true;
    }

    public function getTrackingInfo($tracking)
    {
        $result = $this->getTracking($tracking);

        if ($result instanceof Mage_Shipping_Model_Tracking_Result) {
            if ($trackings = $result->getAllTrackings()) {
                return $trackings[0];
            }
        } elseif (is_string($result) && !empty($result)) {
            return $result;
        }

        return false;
    }

    public function getTracking($trackings)
    {
        if (!is_array($trackings)) {
            $trackings = array($trackings);
        }

        return $this->_getTracking($trackings);
    }

    protected function _getTracking($trackings)
    {
        $result = Mage::getModel('shipping/tracking_result');

        foreach ($trackings as $t) {
            $tracking = Mage::getModel('shipping/tracking_result_status');
            $tracking->setCarrier($this->_code);
            $tracking->setCarrierTitle($this->getConfigData('title'));
            $tracking->setTracking($t);
            $tracking->setUrl('http://auspost.com.au/track/');
            $result->append($tracking);
        }

        return $result;
    }

}
