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
 * @category   EcomCore
 * @package    EcomCore_Freight
 * @author     Thai Phan
 * @copyright  Copyright (c) 2014 EcomCore Pty. Ltd. (http://www.ecomcore.com.au)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Helper methods for eParcel support
 *
 * @category   EcomCore
 * @package    EcomCore_Freight
 */
class EcomCore_Freight_Helper_Rate extends Mage_Core_Helper_Abstract
{

    public $csvFieldMap = array(
        'dest_country_id'      => 'Country',
        'dest_region_id'       => 'State',
        'dest_zip'             => 'Postcodes',
        'weight_from'          => 'Weight from',
        'weight_to'            => 'Weight to',
        'price'                => 'Basic Price',
        'price_per_increment'  => 'Price Per Increment',
        'increment_weight'     => 'Increment Weight',
        'increment_start'      => 'Increment Start',
        'price_per_article'    => 'Price Per Article',
        'consignment_option'   => 'Consignment Option',
        'maxkg_per_consigment' => 'Max Kg Per Consignment',
        'cap'                  => 'Capped price',
        'surcharge'            => 'Surcharge',
        'min_charge'           => 'Min Charge',
        'delivery_group'       => 'Delivery Group',
        'carrier_code'         => 'Carrier Code',
        'adjustment_rules'     => 'Adjustment Rules',
    );

    public $summarySkel = array('units' => 0, 'weight' => 0, 'cubic' => 0, 'dead' => 0, 'adjustments' => array(), 'item_data' => array());

    public function summariseItems(Mage_Shipping_Model_Rate_Request $request)
    {
        $items      = $request->getAllItems();
        $numParcels = $request->getPackageQty();
        $dropdownValues = array();

        $dataHelper = Mage::helper('eccfreight/data');

        $shippingClassRules = $dataHelper->getConfigValue('shippingclasses');
        if (!empty($shippingClassRules)) {
            $shippingClassRules = explode("\n", $shippingClassRules);
            foreach ($shippingClassRules as $k => $v) {
                $bits = explode(':', $v);
                if (isset($bits[1])) {
                    $shippingClassRules[$bits[0]] = explode(',', $bits[1]);
                    if (count($shippingClassRules[$bits[0]]) <= 1) {
                        $shippingClassRules[$bits[0]] = $bits[1];
                    }
                    unset($shippingClassRules[$k]);
                }
            }

            // Shipping classes are often dropdowns. Check...
            foreach ($shippingClassRules as $attr => $opts) {
                $model = Mage::getModel('eav/entity_attribute')->loadByCode('catalog_product', $attr);
                if ($model->frontend_input == 'select') { // Choosing a multiselect for shipping class would be insanity - we don't support that..
                    $data = Mage::getModel('catalog/resource_eav_attribute')->load($model->getData('attribute_id'))->getSource()->getAllOptions(false);
                    foreach ($data as $opt) {
                        $dropdownValues[$attr][$opt['value']] = strtolower($opt['label']);
                    }
                }
            }

        }

        $cubicAttribute     = $dataHelper->getConfigValue('cubicattribute');
        $applyfactortocubic = $dataHelper->getConfigValue('applyfactortocubic');

        $dimensionunits = $dataHelper->getConfigValue('dimensionunits');
        $dimxAttribute  = $dataHelper->getConfigValue('dimxattribute');
        $dimyAttribute  = $dataHelper->getConfigValue('dimyattribute');
        $dimzAttribute  = $dataHelper->getConfigValue('dimzattribute');

        if ($dimxAttribute) {
            $dimxAttribute = Mage::getModel('eav/entity_attribute')->load($dimxAttribute)->getAttributeCode();
        }
        if ($dimyAttribute) {
            $dimyAttribute = Mage::getModel('eav/entity_attribute')->load($dimyAttribute)->getAttributeCode();
        }
        if ($dimzAttribute) {
            $dimzAttribute = Mage::getModel('eav/entity_attribute')->load($dimzAttribute)->getAttributeCode();
        }

        if ($cubicAttribute) {
            $cubicAttribute = Mage::getModel('eav/entity_attribute')->load($cubicAttribute)->getAttributeCode();
        } else {
            $cubicAttribute = false;
        }

        $itemSummary = array(
            'standard' => $this->summarySkel
        );

        $itemNumber    = 0;
        $parcelCount   = 0;
        foreach ($items as $item) {

            $parcelCount++;
            $productId = $item->getProductId();
            $product   = $item->getProduct();

            $unitCount  = $item->getQty();
            $unitWeight = $item->getWeight();
            $unitCubic  = 0;

            if ($cubicAttribute) {

                $unitCubic  = $product->getData($cubicAttribute);
                if ($applyfactortocubic) {
                    $unitCubic = ($unitCubic/EcomCore_Freight_Model_Config_Dimensionunits::CUBIC_MULTIPLIER);
                }

            } else {

                $unitCubic = (
                    max(1,$product->getData($dimxAttribute))
                    *max(1,$product->getData($dimyAttribute))
                    *max(1,$product->getData($dimzAttribute))
                );

                if ($dimensionunits == EcomCore_Freight_Model_Config_Dimensionunits::CMS) {
                    $unitCubic = ($unitCubic/EcomCore_Freight_Model_Config_Dimensionunits::CUBIC_CMTOM);
                }

                if ($applyfactortocubic) {
                    $unitCubic *= EcomCore_Freight_Model_Config_Dimensionunits::CUBIC_MULTIPLIER;
                }
                Mage::log(__METHOD__.'() cubic measurement calculated as ('.max(1,$product->getData($dimxAttribute)).'*'.max(1,$product->getData($dimyAttribute)).'*'.max(1,$product->getData($dimzAttribute)).' '.($dimensionunits == EcomCore_Freight_Model_Config_Dimensionunits::CMS ? '/'.EcomCore_Freight_Model_Config_Dimensionunits::CUBIC_CMTOM : '').' * '.EcomCore_Freight_Model_Config_Dimensionunits::CUBIC_MULTIPLIER.')');
            }

            $unitCubic    = $unitCubic;
            $chargeWeight = max($unitCubic, $unitWeight);

            Mage::Log(__METHOD__.'() Product #'.$productId.' `'.$product->getSku().'` has cubic weight of '.$unitCubic.' dead weight of '.$unitWeight.'. Chargeable weight is '.$chargeWeight.' and we have '.$unitCount.' of them');

            $shipClass = false;
            $shippingClassAttributes = array();
            if (!empty($shippingClassRules)) {
                Mage::log(__METHOD__.'() Processing class rules '.json_encode($shippingClassRules));

                foreach ($shippingClassRules as $k => $v) {
                    if ($k == 'shipping_class') {
                        $shippingClassAttributes[] = $k;
                    } else if ($item->getData($k)) {
                        $shipClass = $v;
                        break;
                    }
                }

                if ($shipClass == false && !empty($shippingClassAttributes)) {
                    Mage::log(__METHOD__.'() possible shipping classes: '.var_export($shippingClassAttributes, true));
                    foreach ($shippingClassAttributes as $classAttribute) {
                        Mage::log(__METHOD__.'() Checking attribute '.$classAttribute);
                        $class = strtolower($product->getData($classAttribute));
                        if (isset($dropdownValues[$classAttribute])) {
                            if (isset($dropdownValues[$classAttribute][$class])) {
                                $class = $dropdownValues[$classAttribute][$class];
                            } else {
                                //skipping - not an option we care about.
                                $class = false;
                            }
                        }

                        if ($class) {
                            Mage::log(__METHOD__.'() Found - matching against '.json_encode($shippingClassRules[$classAttribute]));
                            if (empty($shippingClassRules[$classAttribute])) {
                                $shipClass = $class;
                                Mage::log(__METHOD__.'() #'.$itemNumber.' {'.$unitCount.' X '.$chargeWeight.'} shipClass override: `'.$shipClass.'` sourced from attribute `'.$classAttribute.'`');
                            } else if (is_array($shippingClassRules[$classAttribute])) {
                                if (in_array($class, $shippingClassRules[$classAttribute])) {
                                    $shipClass = $class;
                                    Mage::log(__METHOD__.'() #'.$itemNumber.' {'.$unitCount.' X '.$chargeWeight.'} shipClass override: `'.$shipClass.'` sourced from attribute `'.$classAttribute.'` value list ['.implode(',',$shippingClassRules[$classAttribute]).']');
                                } else {
                                    Mage::log(__METHOD__.'() Ignoring class `'.$class.'`. Not in allowed value list ['.implode(',',$shippingClassRules[$classAttribute]).']');
                                }
                            } else {
                                $shipClass = $shippingClassRules[$classAttribute];
                                Mage::log(__METHOD__.'() #'.$itemNumber.' {'.$unitCount.' X '.$chargeWeight.'} shipClass override: `'.$shipClass.'` sourced from config setting `'.$classAttribute.'`');
                            }
                        }
                    }
                }
            }

            if ($shipClass === false) {
                $shipClass = 'standard';
            }
            if (false == isset($itemSummary[$shipClass])) {
                $itemSummary[$shipClass] = $this->summarySkel;
            }

            $itemSummary[$shipClass]['weight'] += ($chargeWeight*$unitCount);
            $itemSummary[$shipClass]['dead']   += ($unitWeight*$unitCount);
            $itemSummary[$shipClass]['cubic']  += ($unitCubic*$unitCount);
            $itemSummary[$shipClass]['units']  += $unitCount;
            $itemSummary[$shipClass]['item_data'][$product->getSku()] = array('weight' => $chargeWeight, 'units' => $unitCount, 'dead' => $unitWeight, 'cubic' => $unitCubic);
            $itemSummary[$shipClass]['adjustments'][$product->getId()] = $this->getPromoRules($item);

            Mage::log(__METHOD__.'() Added '.$unitCount.' units ('.$item->getQty().') with a combined weight of '.($chargeWeight*$unitCount).'kg [Dead: '.$unitWeight.', Cubic: '.$unitCubic.' ea.]');

            if ($shipClass == 'free') {
                $itemSummary[$shipClass]['charge'] = 0;
            } else if ($shipClass == 'fixed') {
                $itemSummary[$shipClass]['charge'] += ($product->getShippingFlatrate()*$item->getQty());
            } else if (substr($shipClass, 0, 6) == 'capped') {
                // basic structure is that capped groupings are set with capped<class>_value
                // example rules;
                //  * items with cappedhats will be grouped together.
                //  * the value of the cap will be defined by the shiprate_capped attribute for each product.
                //  * If the product has ship_class set to 'cappedhats' and the shiprate_capped attribute has a value of 20.
                //  * all products with the same combination will be capped at that value.
                $capClass = substr($shipClass, (strlen($shipClass)-7)* -1);
                $capRate  = $product->getShiprateCapped();
                if (false === isset($itemSummary['capped'][$capClass])) {
                    $itemSummary['capped'][$capClass.'-'.$capRate] = array('units' => 0, 'charge' => $capRate);
                }
                $itemSummary['capped'][$capClass.'-'.$capRate]['units'] += $unitCount;
            }
            $itemNumber++;
        }

        return $itemSummary;

    }

    public function getPromoRules($item)
    {
        $rules = explode(",",$item->getAppliedRuleIds());
        if (empty($rules)) return;

        $adjustments = array();
        foreach ($rules as $id) {
            $rule = Mage::getModel('salesrule/rule')->load($id);
            if ($rule->apply_to_shipping != 1) {
                continue;
            }
            $adjustments[] = array('type' => $rule->getData('simple_action'), 'amount' => $rule->getData('discount_amount'));
        }
        return $adjustments;

    }



    public function getEstimate($productList, $listType, $destData)
    {
        $estimate = Mage::getModel('eccfreight/estimate');
        $estimate->setProducts($productList, $listType);
        $estimate->setDestination($destData);
        $estimate->process();

        return $estimate;
    }

    public function extractRateData($estimate)
    {

        $data = array(
            'list'     => array(),
            'cheapest' => array('price' => null, 'name' => ''),
        );

        foreach ($estimate->result as $code => $rate) {
            foreach ($rate as $option) {
                if ($option->getErrorMessage()) {
                    continue;
                }

                if ($option->getCarrier() == 'eccfreight') {
                    if (!empty(EcomCore_Freight_Model_Rate::$rateResults)) {
                        EcomCore_Freight_Model_Rate::applyAdjustments($option);
                    }
                }

                $price = $option->getPrice();
                $name  = $option->getMethodTitle();

                if ($data['cheapest']['price'] === null || $price < $data['cheapest']['price']) {
                    $data['cheapest']['price'] = $price;
                    $data['cheapest']['name']  = $name;
                }

                $data['list'][$name] = $price;
            }
        }

        return $data;
    }

    public function applyRetailTherapy($price)
    {
        $newPrice = (int)((ceil($price*2)/2)*100); //round up to the nearest $0.50 and convert to straight cents
        $newPrice = $newPrice - 5; // take 5 cents off
        $newPrice = $newPrice / 100; // Back to standard $ notation

        if ($newPrice < $price) {
            // If the original price was lower than the massaged price, add a dollar to it.
            return $this->applyRetailTherapy($price+1);
        }
        return $newPrice;

    }

}
