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
 * Listing of customer groups available in the shop.
 *
 * @category   EcomCore
 * @package    EcomCore_Freight
 */
class EcomCore_Freight_Model_Config_CustomerGroups
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $storeId = Mage::app()->getStore()->getId();
        $collection = Mage::getModel('customer/group')->setStoreId($storeId)->getCollection();

        $options = array();

        foreach ($collection as $group) {
            $options[] = array(
                'value' => $group->getCustomerGroupId(),
                'label' => $group->getCustomerGroupCode(),
            );
        }

        return $options;
    }
}
