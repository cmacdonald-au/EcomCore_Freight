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

}
