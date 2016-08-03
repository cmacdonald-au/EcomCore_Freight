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
 * Listing of possible options for restricting access based on customer groups
 *
 * @category   EcomCore
 * @package    EcomCore_Freight
 */
class EcomCore_Freight_Model_Config_CustomerGroupAccess
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $helper = Mage::helper('adminhtml');
        return array(
            array('value' => 0, 'label' => $helper->__('No restrictions')),
            array('value' => 1, 'label' => $helper->__('Only allow group ...')),
            array('value' => 2, 'label' => $helper->__('Allow all groups except ...')),
        );
    }
}
