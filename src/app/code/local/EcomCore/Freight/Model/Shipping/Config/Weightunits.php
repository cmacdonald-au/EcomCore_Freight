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
 * @author     Chris Norton
 * @copyright  Copyright (c) 2014 EcomCore Pty. Ltd. (http://www.ecomcore.com.au)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Australia Post shipping weight unit selector and conversion values.
 * All conversion rates are to convert into grams, which is the required unit
 * of weight used by the Australia Post DRC.
 *
 * @category   EcomCore
 * @package    EcomCore_Freight
 */
class EcomCore_Freight_Model_Shipping_Config_Weightunits
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $helper = Mage::helper('adminhtml');
        return array(
            array(
                'value' => 1000,
                'label' => $helper->__('Kilograms (kg)')
            ),
            array(
                'value' => 1,
                'label' => $helper->__('Grams (g)')
            ),
            array(
                'value' => 453.59,
                'label' => $helper->__('Pounds (lb)')
            ),
            array(
                'value' => 28.35,
                'label' => $helper->__('Ounces (oz)')
            ),
        );
    }
}
