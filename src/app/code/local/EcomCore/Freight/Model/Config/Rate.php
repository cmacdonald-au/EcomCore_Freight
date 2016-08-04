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
 * Backend model for Australia Post eParcel table rates CSV import
 *
 * @category   EcomCore
 * @package    EcomCore_Freight
 */
class EcomCore_Freight_Model_Config_Rate extends Mage_Core_Model_Config_Data
{
    public function _afterSave()
    {
        Mage::getResourceModel('eccfreight/rate')->uploadAndImport($this);
    }
}
