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
 * Controller handling order export requests.
 *
 * @category   EcomCore
 * @package    EcomCore_Freight
 */
class EcomCore_Freight_Adminhtml_EccfreightController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Export the eParcel table rates as a CSV file.
     */
    public function exportTableratesAction()
    {

        $helper = Mage::helper('eccfreight/rate');

        $headerLine = array_values($helper->csvFieldMap);
        $csvFields  = array_keys($helper->csvFieldMap);

        // These are processed and not sent directly.
        unset($csvFeilds['dest_country_id']);
        unset($csvFeilds['dest_region_id']);

        Mage::log(__METHOD__.'() Doing things');
        $rates = Mage::getResourceModel('eccfreight/rate_collection');
        $response = array(
            $headerLine;
        );

        foreach ($rates as $rate) {
            $countryId   = $rate->getData('dest_country_id');
            $countryCode = Mage::getModel('directory/country')->load($countryId)->getIso3Code();
            $regionId    = $rate->getData('dest_region_id');
            $regionCode  = Mage::getModel('directory/region')->load($regionId)->getCode();

            $line = array(
                $countryCode,
                $regionCode
            );

            foreach ($csvFields as $field) {
                $line[] = $rate->getData($field);
            }

            $response[] = $line;
        }

        $csv = new Varien_File_Csv();
        $temp = tmpfile();

        foreach ($response as $responseRow) {
            $csv->fputcsv($temp, $responseRow);
        }

        rewind($temp);

        $contents = stream_get_contents($temp);
        $this->_prepareDownloadResponse('eccfreightrates-'.date('Ymd.Hi', $_SERVER['REQUEST_TIME']).'.csv', $contents);

        fclose($temp);
    }

    /**
     * @return bool
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton("admin/session")->isAllowed("sales/order");
    }
}
