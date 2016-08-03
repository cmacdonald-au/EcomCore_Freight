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
class EcomCore_Freight_Adminhtml_RateController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Export the eParcel table rates as a CSV file.
     */
    public function exportTableratesAction()
    {
        $rates = Mage::getResourceModel('eccfreight/shipping_carrier_eparcel_collection');
        $response = array(
            array(
                'Country',
                'State',
                'Postcodes',
                'Weight from',
                'Weight to',
                'Basic Price',
                'Price Per Kg',
                'Price Per Article',
                'Consignment Allowed',
                'Max Kg Per Consignment',
                'Capped price',
                'Surcharge',
                'Delivery Type',
                'Charge Code Individual',
                'Charge Code Business',
                'Adjustment Rules'
            )
        );

        foreach ($rates as $rate) {
            $countryId   = $rate->getData('dest_country_id');
            $countryCode = Mage::getModel('directory/country')->load($countryId)->getIso3Code();
            $regionId    = $rate->getData('dest_region_id');
            $regionCode  = Mage::getModel('directory/region')->load($regionId)->getCode();

            $response[] = array(
                $countryCode,
                $regionCode,
                $rate->getData('dest_zip'),
                $rate->getData('weight_from'),
                $rate->getData('weight_to'),
                $rate->getData('price'),
                $rate->getData('price_per_kg'),
                $rate->getData('price_per_article'),
                $rate->getData('consignable'),
                $rate->getData('consignment_allowed'),
                $rate->getData('maxkg_per_consigment'),
                $rate->getData('cap'),
                $rate->getData('surcharge'),
                $rate->getData('delivery_type'),
                $rate->getData('charge_code')
                $rate->getData('adjustment_rules'),
            );
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
