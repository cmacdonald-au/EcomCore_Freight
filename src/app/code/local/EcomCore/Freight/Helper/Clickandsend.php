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

use Auspost\Postage\Enum\ServiceCode;
use Auspost\Postage\Enum\ServiceOption;

/**
 * Class EcomCore_Freight_Helper_Clickandsend
 *
 * @category   EcomCore
 * @package    EcomCore_Freight
 */
class EcomCore_Freight_Helper_Clickandsend extends Mage_Core_Helper_Abstract
{
    const XML_PATH_CLICK_AND_SEND_ENABLED = 'ecomcore_freight/clickandsend/active';
    const XML_PATH_CLICK_AND_SEND_FILTER_SHIPPING_METHODS = 'ecomcore_freight/clickandsend/filter';
    const XML_PATH_CLICK_AND_SEND_EXPORT_ALL = 'ecomcore_freight/clickandsend/export_all';

    /**
     * @return bool
     */
    public function isClickAndSendEnabled()
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_CLICK_AND_SEND_ENABLED);
    }

    /**
     * @return bool
     */
    public function isFilterShippingMethods()
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_CLICK_AND_SEND_FILTER_SHIPPING_METHODS);
    }

    /**
     * @return bool
     */
    public function isExportAll()
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_CLICK_AND_SEND_EXPORT_ALL);
    }

    /**
     * Returns shipping method service options not supported by Click & Send.
     *
     * @return array
     */
    public function getDisallowedServiceOptions()
    {
        return array(
            ServiceOption::AUS_SERVICE_OPTION_SIGNATURE_ON_DELIVERY,
            ServiceOption::INTL_SERVICE_OPTION_EXTRA_COVER,
            ServiceOption::INTL_SERVICE_OPTION_PICKUP_METRO
        );
    }

    /**
     * Returns shipping method service codes not supported by Click & Send.
     *
     * @return array
     */
    public function getDisallowedServiceCodes()
    {
        return array(
            ServiceCode::AUS_PARCEL_COURIER,
            ServiceCode::AUS_PARCEL_COURIER_SATCHEL_MEDIUM,
            ServiceCode::INTL_SERVICE_SEA_MAIL
        );
    }
}
