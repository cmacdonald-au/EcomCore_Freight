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

    const NO_EXTEND = false;

    /**
     * @var string
     */
    protected $_code = 'eccfreight';

    public static $rateResults = array();

    /**
     * @var string
     */
    protected $_default_condition_name = 'package_weight';

    protected $_conditionNames = array();

    public function __construct()
    {
        parent::__construct();
        foreach ($this->getCode('condition_name') as $k=>$v) {
            $this->_conditionNames[] = $k;
        }
    }

    public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {

        self::$rateResults = array();
        if (!$this->getConfigFlag('active')) {
            Mage::log(__METHOD__.'() Module disabled');
            return false;
        }
        Mage::log(__METHOD__.'() Starting');

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
                    $method = Mage::getModel('eccfreight/rate_result_method');

                    $method->setCarrier($this->_code);
                    $method->setCarrierTitle($this->getConfigData('title'));
                    $method->setAdjustmentRules($rate['adjustment_rules']);

                    if ($rate['carrier_code']) {
                        $methodCode = strtolower(str_replace(' ', '_', $rate['carrier_code']));
                    } else {
                        $methodCode = strtolower(str_replace(' ', '_', $rate['delivery_group']));
                    }
                    $method->setMethod($methodCode);

                    if ($this->getConfigData('name')) {
                        $method->setMethodTitle($this->getConfigData('name'));
                    } else {
                        $method->setMethodTitle($rate['delivery_group']);
                    }

                    $method->setMethodChargeCode($rate['carrier_code']);

                    if (isset($rate['min_charge']) && $rate['min_charge'] > 0 && $rate['min_charge'] > $rate['price']) {
                        $rate['price'] = $rate['min_charge'];
                    }

                    $shippingPrice = $this->getFinalPriceWithHandlingFee($rate['price'])*1.1;

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

                    $method->setCost($shippingPrice);
                    $method->setPrice($shippingPrice);
                    $method->setDeliveryType($rate['delivery_group']);
                    self::$rateResults[$methodCode] = $method;

                }
            }
        } else {
            Mage::log(__METHOD__.'() No rates available for this request');
        }

        $extensionRule = $this->getConfigData('extensionrule');
        $otherRates = $this->extend($request, $result);
        Mage::log(__METHOD__.'() Got '.count($otherRates).' rates from extend()');
        if (!empty($otherRates)) {
            foreach ($otherRates as $rate) {
                Mage::log(__METHOD__.'() Extended rate details '.json_encode($rate->toArray()));
                if ($rate->getData('price') === null) {
                    Mage::log(__METHOD__.'() Skipping rate, price is null');
                    continue;
                }
                if ($extensionRule == 'use_extend') {
                    // Techinically, if we're taking the first extension rate, then this should be up the top, _before_ we process any of the above. @TODO
                    Mage::log(__METHOD__.'() Taking rate '.$rate->getCode().' for $'.$rate->getData('price'));
                    self::$rateResults = array($rate->getMethod() => $rate);
                    break;
                } else {
                    self::$rateResults[$rate->getMethod()] = $rate;
                }
            }
        }

        $allRates = self::$rateResults;
        $finalRates = array();
        foreach ($allRates as $method) {
            // Combine all rates, consolidating based on the method title.
            // We choose the cheapest name rate by default and then change the carrier code to eccfreight to signify we've applied logic
            if (false === isset($finalRates[$method->getMethodTitle()]) || $method->getPrice() < $finalRates[$method->getMethodTitle()]->getPrice()) {
                if (isset($finalRates[$method->getMethodTitle()])) {
                    Mage::log(__METHOD__.'() Replacing existing rate `'.$method->getMethodTitle().'` ($'.$finalRates[$method->getMethodTitle()]->getPrice().') with new rate ($'.$method->getPrice().') '.json_encode($method->toArray()));
                }
                $method->setCarrier($this->_code);
                $finalRates[$method->getMethodTitle()] = $method;
            }
        }

        foreach ($finalRates as $method) {
            $result->append($method);
        }

        return $result;
    }


    /* This and it's companion should be in a helper... */
    protected function tempEnableCarrier($model)
    {
        $carrierCode = $model->getCarrierCode();
        if (Mage::app()->getStore()->getConfig('carriers/'.$carrierCode.'/active')) {
            Mage::log(__METHOD__.'() '.$carrierCode.' already enabled');
            return false;
        }

        Mage::log(__METHOD__.'() Forcing '.$carrierCode.' to active');
        Mage::app()->getStore()->setConfig('carriers/'.$carrierCode.'/active', '1');
        return true;

    }

    protected function clearTempEnableCarrier($model)
    {
        $carrierCode = $model->getCarrierCode();
        Mage::log(__METHOD__.'() Reverting '.$carrierCode.' to inactive');
        Mage::app()->getStore()->setConfig('carriers/'.$carrierCode.'/active', '0');
    }

    public function extend(Mage_Shipping_Model_Rate_Request $request, Mage_Shipping_Model_Rate_Result $result)
    {

        $methods = array();
        $otherClasses = $this->getConfigData('alsoprocess');
        if (empty($otherClasses)) {
            return;
        }
        $otherClasses = explode("\n", $otherClasses);

        foreach ($otherClasses as $class) {

            Mage::log(__METHOD__.'() trying `'.$class.'`');
            $model = Mage::getModel($class);

            $forced = $this->tempEnableCarrier($model);
            $results = $model->collectRates($request);

            if ($forced) {
                $this->clearTempEnableCarrier($model);
            }

            if ($results instanceof Mage_Shipping_Model_Rate_Result) {
                $rates = $results->getAllRates();
                foreach ($rates as $method) {
                    $methods[] = $method;
                }
            } else if ($results === false) {
                Mage::log(__METHOD__.'() Attempting to get rates from '.$model->getCarrierCode().' ('.$class.') gave us `false`');
            } else {
                Mage::log(__METHOD__.'() Got back an unsupported result `'.get_class($results).'`');
            }

        }
        return $methods;
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
        return array($this->_code => $this->getConfigData('name'));
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
            $tracking->setUrl('#tracking');
            $result->append($tracking);
        }

        return $result;
    }

    // Move to helper
    public static function isEbayRequest()
    {
        if (isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER'])) {
            if (stripos('ebay.com', $_SERVER['HTTP_REFERER']) !== false) {
                return true;
            }
        }

        $req = Mage::app()->getRequest();
        if (strtolower($req->getParam('mode')) == 'ebay') {
            return true;
        }
    }

    /**
     * Move to helper....maybe
     *
     * !!Adjustments stack!!
     * eg;
     *  Rule list: ebay:+15%;global:+10%;
     *  This will apply a 15% increase for ebay requests _and_ a 10% global lift
     * Processing order is based on entry order, so a value of lift:+10;ebay:+15% will add $10 and then 15%.
     */
    public static function applyAdjustments(Mage_Sales_Model_Quote_Address_Rate $rate)
    {
        if (empty(self::$rateResults)) {
            return;
        }

        if (false === isset(self::$rateResults[$rate->getMethod()]) || is_object(self::$rateResults[$rate->getMethod()]) === false) {
            return;
        }

        $rateAdjustments   = self::$rateResults[$rate->getMethod()]->adjustment_rules;
        $globalAdjustments = Mage::getModel('eccfreight/rate')->getConfigData('globalAdjustments');
        if (empty($rateAdjustments) && empty($globalAdjustments)) {
            return;
        }

        $currentPrice = $rate->getPrice();
        $adjustments = array_merge(explode(';', $rateAdjustments), explode(';', $globalAdjustments));

        foreach ($adjustments as $adjustmentRule) {
            $adjustment = explode(':', $adjustmentRule);
            if ($adjustment[0] == 'ebay' && self::isEbayRequest()) {
                $adjustmentValue = self::getAdjustmentAmount($adjustment[1], $currentPrice);
                Mage::log(__METHOD__.'() Adjusting price by '.$adjustmentValue.' (from: '.$currentPrice.', to: '.($currentPrice+$adjustmentValue).') due to adjustment rule `'.$adjustmentRule.'`');
                $currentPrice = $currentPrice+$adjustmentValue;
            } else if ($adjustment[0] == 'global') {
                $adjustmentValue = self::getAdjustmentAmount($adjustment[1], $currentPrice);
                Mage::log(__METHOD__.'() Adjusting price by '.$adjustmentValue.' (from: '.$currentPrice.', to: '.($currentPrice+$adjustmentValue).') due to adjustment rule `'.$adjustmentRule.'`');
                $currentPrice = $currentPrice+$adjustmentValue;
            }
        }

        $rate->setPrice(number_format($currentPrice, 2));
    }

    protected static function getAdjustmentAmount($rule, $price)
    {

        $modifier = 1; // Are we going for a surcharge (+) or a discount (-). Default is surcharge

        $sign = substr($rule, 0, 1);
        if ($sign == '-') {
            $modifier = -1;
            $rule = substr($rule, 1);
        } else if ($sign == '+') {
            $rule = substr($rule, 1);
        }

        $type = substr($rule, -1);
        if ($type == '%') {
            $rule  = substr($rule, 0, -1);
            $pctval = ($price/100)*$rule;
            return ($pctval*$modifier);
        } else {
            return ($rule*$modifier);
        }

    }
}
