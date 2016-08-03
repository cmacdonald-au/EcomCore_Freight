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
 * Helper functions for address validation
 *
 * @category   EcomCore
 * @package    EcomCore_Freight
 */
class EcomCore_Freight_Helper_Address extends Mage_Core_Helper_Abstract
{
    const XML_PATH_ADDRESS_VALIDATION_ENABLED = 'ecomcore_freight/address_validation/enabled';
    const XML_PATH_ADDRESS_VALIDATION_BACKEND = 'ecomcore_freight/address_validation/backend';
    const XML_PATH_DELIVERY_CHOICES_DEVELOPER_MODE = 'ecomcore_freight/address_validation/delivery_choices_developer_mode';

    /**
     * Checks whether checkout address validation is enabled.
     *
     * @return bool
     */
    public function isAddressValidationEnabled()
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_ADDRESS_VALIDATION_ENABLED);
    }

    /**
     * Gets the selected backend for address validation, e.g. Australia Post
     * Delivery Choices
     *
     * @return string
     */
    public function getAddressValidationBackend()
    {
        return Mage::getStoreConfig(self::XML_PATH_ADDRESS_VALIDATION_BACKEND);
    }

    /**
     * Checks whether developer mode is enabled for Delivery Choices.
     *
     * @return bool
     */
    public function isDeliveryChoicesDeveloperMode()
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_DELIVERY_CHOICES_DEVELOPER_MODE);
    }
}
