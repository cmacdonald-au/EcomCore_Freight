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
 * Used for Australia Post configuration fields that need the options "never",
 * "required" and "optional"
 */
class EcomCore_Freight_Model_Shipping_Carrier_Australiapost_Source_Visibility
{
    const NEVER    = 0;
    const OPTIONAL = 1;
    const REQUIRED = 2;

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        $helper = Mage::helper('eccfreight');
        return array(
            array('value' => self::REQUIRED, 'label' => $helper->__('Required')),
            array('value' => self::OPTIONAL, 'label' => $helper->__('Optional')),
            array('value' => self::NEVER,    'label' => $helper->__('Never')),
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
            self::NEVER    => $helper->__('Never'),
            self::OPTIONAL => $helper->__('Optional'),
            self::REQUIRED => $helper->__('Required'),
        );
    }
}
