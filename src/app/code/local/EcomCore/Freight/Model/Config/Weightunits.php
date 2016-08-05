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
class EcomCore_Freight_Model_Config_Weightunits
{
    const KILOS = 1;
    const GRAMS = 2;

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        $helper = Mage::helper('eccfreight');
        return array(
            array('value' => self::KILOS, 'label' => $helper->__('Kilograms')),
            array('value' => self::GRAMS, 'label' => $helper->__('Grams')),
        );
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        $helper = Mage::helper('eccfreight');
        return array(
            self::KILOS  => $helper->__('Kilograms'),
            self::GRAMS  => $helper->__('Grams'),
        );
    }
}