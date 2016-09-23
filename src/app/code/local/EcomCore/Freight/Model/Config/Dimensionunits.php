<?php
/**
 * Fontis Australia Extension
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   Fontis
 * @package    Fontis_Australia
 * @author     Thai Phan
 * @copyright  Copyright (c) 2014 Fontis Pty. Ltd. (http://www.fontis.com.au)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Used for Australia Post configuration fields that need the options "never",
 * "required" and "optional"
 */
class EcomCore_Freight_Model_Config_Dimensionunits
{
    const METERS = 1;
    const CMS    = 2;
    const CUBIC_MULTIPLIER = 250;
    const CUBIC_CMTOM = 1000000;

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        $helper = Mage::helper('eccfreight');
        return array(
            array('value' => self::METERS, 'label' => $helper->__('Meters')),
            array('value' => self::CMS, 'label' => $helper->__('Centimeters')),
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
            self::METERS => $helper->__('Meters'),
            self::CMS    => $helper->__('Centimeters'),
        );
    }
}
