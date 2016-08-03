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
 * @author     Thai Phan
 * @copyright  Copyright (c) 2014 EcomCore Pty. Ltd. (http://www.ecomcore.com.au)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Australia Post allowed shipping methods
 *
 * @category   EcomCore
 * @package    EcomCore_Freight
 */
class EcomCore_Freight_Model_Shipping_Carrier_Australiapost_Source_Method
{
    public function toOptionArray()
    {
        /** @var EcomCore_Freight_Model_Shipping_Carrier_Australiapost $eccfreightpost */
        $eccfreightpost = Mage::getSingleton('eccfreight/shipping_carrier_eccfreightpost');
        $options = array();
        foreach ($eccfreightpost->getCode('services') as $key => $value) {
            $options[] = array('value' => $key, 'label' => $value);
        }
        return $options;
    }
}
