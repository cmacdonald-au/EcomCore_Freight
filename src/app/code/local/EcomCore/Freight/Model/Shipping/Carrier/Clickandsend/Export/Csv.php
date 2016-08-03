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

class EcomCore_Freight_Model_Shipping_Carrier_Clickandsend_Export_Csv extends EcomCore_Freight_Model_Shipping_Carrier_Common_Export_Csv_Abstract
{
    public function exportOrders($orders)
    {
        $clickandsend = Mage::getModel('eccfreight/shipping_carrier_clickandsend');

        foreach ($orders as $order) {
            /*/** @var Mage_Sales_Model_Order $order */
            $order = Mage::getModel('sales/order')->load($order);

            if (!$order->getShippingCarrier() instanceof EcomCore_Freight_Model_Shipping_Carrier_Australiapost) {
                throw new EcomCore_Freight_Model_Shipping_Carrier_Clickandsend_Export_Exception(
                    "Order #" . $order->getIncrementId() . " doesn't use Australia Post as its carrier!"
                );
            }

            $clickandsend->addItem($order);
        }

        // Save file
        $fileName = 'order_export_' . date('Ymd_His') . '_clickandsend.csv';
        $filePath = Mage::getBaseDir('export') . '/' . $fileName;

        if ($clickandsend->makeCsv($filePath)) {
            return $filePath;
        }

        throw new EcomCore_Freight_Model_Shipping_Carrier_Clickandsend_Export_Exception(
            'Unable to build a CSV file!'
        );
    }
}
