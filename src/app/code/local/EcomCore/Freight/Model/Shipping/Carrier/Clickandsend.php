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

class EcomCore_Freight_Model_Shipping_Carrier_Clickandsend
{
    const ENCLOSURE = '"';
    const DELIMITER = ',';

    protected $items = array();

    public function addItem(Mage_Sales_Model_Order $order)
    {
        /** @var EcomCore_Freight_Helper_Australiapost $helper */
        $helper = Mage::helper('eccfreight/eccfreightpost');

        /** @var Mage_Sales_Model_Order_Address $shippingAddress */
        $shippingAddress = $order->getShippingAddress();

        $item = array(
            // TODO: Add feature to export address books to and from Click & Send.
            'addressCode' => '',
            'deliveryCompanyName' => $shippingAddress->getCompany(),
            'deliveryName' => $shippingAddress->getName(),
            'deliveryTelephone' => $shippingAddress->getTelephone(),
            'deliveryEmail' => $shippingAddress->getEmail(),
            'deliveryAddressLine1' => $shippingAddress->getStreet1(),
            'deliveryAddressLine2' => $shippingAddress->getStreet2(),
            'deliveryAddressLine3' => $shippingAddress->getStreet3(),
            'deliveryCity' => $shippingAddress->getCity(),
            'deliveryState' => $shippingAddress->getRegionCode(),
            'deliveryPostcode' => $shippingAddress->getPostcode(),
            'deliveryCountryCode' => $shippingAddress->getCountry(),
            'serviceCode' => $this->getServiceCode($order),
            'articleType' => $this->getArticleType($order),
            'length' => $helper->getAttribute($order, 'length'),
            'width' => $helper->getAttribute($order, 'width'),
            'height' => $helper->getAttribute($order, 'height'),
            'declaredWeight' => sprintf('%0.3f', $order->getWeight()),

            // Extra Cover doesn't work with Click & Send
            'extraCover' => '',

            'insuranceValue' => '',
            'descriptionOfGoods' => '',
            'categoryOfItems' => Mage::getStoreConfig('ecomcore_freight/clickandsend/category_of_items'),

            // This number is sometimes needed when you're exporting a package to a foreign country.
            'exportDeclarationNumber' => '',

            'categoryOfItemsExplanation' => Mage::getStoreConfig('ecomcore_freight/clickandsend/category_of_items_explanation'),
            'articleLodgerName' => Mage::getStoreConfig('ecomcore_freight/clickandsend/from_name'),
            'nonDeliveryInstructions' => Mage::getStoreConfig('ecomcore_freight/clickandsend/nondelivery_instructions'),
            'returnAddress' => Mage::getStoreConfig('ecomcore_freight/clickandsend/return_address'),
            'fromName' => Mage::getStoreConfig('ecomcore_freight/clickandsend/from_name'),
            'fromCompanyName' => Mage::getStoreConfig('ecomcore_freight/clickandsend/from_company_name'),
            'fromPhone' => Mage::getStoreConfig('ecomcore_freight/clickandsend/from_phone'),
            'fromFax' => Mage::getStoreConfig('ecomcore_freight/clickandsend/from_fax'),
            'fromEmail' => Mage::getStoreConfig('ecomcore_freight/clickandsend/from_email'),
            'fromAbn' => Mage::getStoreConfig('ecomcore_freight/clickandsend/from_abn'),
            'fromAddressLine1' => Mage::getStoreConfig('ecomcore_freight/clickandsend/from_address_line_1'),
            'fromAddressLine2' => Mage::getStoreConfig('ecomcore_freight/clickandsend/from_address_line_2'),
            'fromAddressLine3' => Mage::getStoreConfig('ecomcore_freight/clickandsend/from_address_line_3'),
            'fromCity' => Mage::getStoreConfig('ecomcore_freight/clickandsend/from_city'),
            'fromState' => Mage::getStoreConfig('ecomcore_freight/clickandsend/from_state'),
            'fromPostcode' => Mage::getStoreConfig('ecomcore_freight/clickandsend/from_postcode'),
            'fromCountry' => Mage::getStoreConfig('ecomcore_freight/clickandsend/from_country'),

            // A value that can used for reconciliations with shipments, e.g. order number,
            // invoice number, recipient name, etc.
            'yourReference' => $order->getIncrementId(),

            'deliveryInstructions' => '',
            'additionalServices' => '',
            'boxOrIrregularShapedItem' => '',
            'sendersCustomReference' => '',
            'importersReferenceNumber' => '',
            'hasCommercialValue' => ''
        );

        // The Click & Send CSV specification only allows up to four items to be listed.
        $maxItems = 4;

        // Initialise the items
        for ($i = 0; $i < $maxItems; $i++) {
            $item['itemCode' . $i] = '';
            $item['itemDescription' . $i] = '';
            $item['itemHsTariffNumber' . $i] = '';
            $item['itemCountryOfOrigin' . $i] = '';
            $item['itemQuantity' . $i] = '';
            $item['itemUnitPrice' . $i] = '';
            $item['itemUnitWeight' . $i] = '';
        }

        $allSimpleItems = $helper->getAllSimpleItems($order);
        for ($i = 0; $i < $maxItems; $i++) {
            if (isset($allSimpleItems[$i])) {
                $simpleItem = $allSimpleItems[$i];
                $item['itemCode' . $i] = $simpleItem->getId();
                $item['itemDescription' . $i] = $this->cleanItemDescription($simpleItem->getName());
                $item['itemHsTariffNumber' . $i] = '';
                $item['itemCountryOfOrigin' . $i] = $simpleItem->getData('country_of_manufacture');
                $item['itemQuantity' . $i] = (int)$simpleItem->getQtyOrdered();
                $item['itemUnitPrice' . $i] = sprintf('%0.2f', $simpleItem->getPrice());
                $item['itemUnitWeight' . $i] = sprintf('%0.3f', $simpleItem->getWeight());
            }
        }

        // Include ATL options column to satisfy new specification
        $item['atlOptions'] = '';

        $this->items[] = $item;
    }

    /**
     * @param string $itemDescription
     * @return string
     */
    private function cleanItemDescription($itemDescription)
    {
        return preg_replace("/[^ \w]+/", "", $itemDescription);
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @return array
     */
    private function getShippingConfiguration(Mage_Sales_Model_Order $order)
    {
        return explode('_', $order->getShippingMethod());
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @return int|null
     */
    private function getServiceCode(Mage_Sales_Model_Order $order)
    {
        $serviceCode = array(
            'INTL' => array(
                'ECI' => 1,
                'EPI' => 2,
                'RPI' => 3,
                'AIR' => 4
            ),
            'AUS' => array(
                'REGULAR' => 6,
                'EXPRESS' => 8
            )
        );

        $shippingMethod = $this->getShippingConfiguration($order);
        $destCountry = $shippingMethod[1];
        $service = $shippingMethod[3];
        if (isset($serviceCode[$destCountry][$service])) {
            return $serviceCode[$destCountry][$service];
        } else if (Mage::helper('eccfreight/clickandsend')->isExportAll()) {
            return null;
        }

        $message = "Order #" . $order->getIncrementId() . " can't be imported into Click & Send";
        throw new EcomCore_Freight_Model_Shipping_Carrier_Clickandsend_Export_Exception($message);
    }

    /**
     * Article type can be documents (1), merchandise (2) or own packaging (7)
     *
     * @param Mage_Sales_Model_Order $order
     * @return int
     */
    private function getArticleType(Mage_Sales_Model_Order $order)
    {
        if ($order->getShippingAddress()->getCountry() == EcomCore_Freight_Helper_Data::AUSTRALIA_COUNTRY_CODE) {
            return 7;
        } else {
            $shippingMethod = $this->getShippingConfiguration($order);
            if (isset($shippingMethod[4]) && $shippingMethod[4] == 'D') {
                return 1;
            }
            return 2;
        }
    }

    /**
     * @param string $filePath
     * @return bool
     */
    public function makeCsv($filePath)
    {
        $handle = fopen($filePath, 'w');
        if ($handle == false) {
            return false;
        }
        foreach ($this->items as $item) {
            fputcsv($handle, array_values($item), self::DELIMITER, self::ENCLOSURE);
        }
        fclose($handle);
        return true;
    }

    /**
     * Event observer. Triggered before an adminhtml widget template is
     * rendered. We use this to add our action to bulk actions in the sales
     * order grid instead of overriding the class.
     *
     * @param $observer
     */
    public function addExportToBulkAction($observer)
    {
        $block = $observer->getBlock();
        if (
            $block instanceof Mage_Adminhtml_Block_Sales_Order_Grid &&
            Mage::helper('eccfreight/clickandsend')->isClickAndSendEnabled()
        ) {
            $block->getMassactionBlock()->addItem('clickandsendexport', array(
                'label' => $block->__('Export to CSV (Click & Send)'),
                'url'   => $block->getUrl('*/eccfreight_clickandsend/export')
            ));
        }
    }
}
