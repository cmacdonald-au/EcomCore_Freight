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
 * Originally based on Magento Tablerate Shipping code and Auctionmaid Matrixrate.
 * @copyright  Copyright (c) 2008 Auction Maid (http://www.auctionmaid.com)
 * @author     Karen Baker <enquiries@auctionmaid.com>
 *
 * @category   EcomCore
 * @package    EcomCore_Freight
 * @author     Chris Norton
 * @copyright  Copyright (c) 2014 EcomCore Pty. Ltd. (http://www.ecomcore.com.au)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class EcomCore_Freight_Model_Mysql4_Rate extends Mage_Core_Model_Mysql4_Abstract
{
    protected function _construct()
    {
        $this->_init('eccfreight/rates', 'pk');
    }

    public function getRate(Mage_Shipping_Model_Rate_Request $request)
    {
        $read = $this->_getReadAdapter();

        $postcode = $request->getDestPostcode();
        $table = $this->getMainTable();
        $storeId = $request->getStoreId();

        $insuranceStep = (float)Mage::getStoreConfig('carriers/eparcel/insurance_step', $storeId);
        $insuranceCostPerStep = (float)Mage::getStoreConfig('carriers/eparcel/insurance_cost_per_step', $storeId);
        $signatureRequired = Mage::getStoreConfigFlag('carriers/eparcel/signature_required', $storeId);
        if ($signatureRequired) {
            $signatureCost = (float)Mage::getStoreConfig('carriers/eparcel/signature_cost', $storeId);
        } else {
            $signatureCost = 0;
        }

        Mage::log($request->getDestCountryId());
        Mage::log($request->getDestRegionId());
        Mage::log($postcode);
        Mage::log(var_export($request->getConditionName(), true));

        for ($j = 0; $j < 5; $j++) {

            $select = $read->select()->from($table);

            // Support for Multi Warehouse Extension.
            if ($request->getWarehouseId() > 0) {
                $select->where('stock_id = ?', $request->getWarehouseId());
            }

            switch($j) {
                case 0:
                    $select->where(
                        $read->quoteInto(" (dest_country_id=? ", $request->getDestCountryId()).
                            $read->quoteInto(" AND dest_region_id=? ", $request->getDestRegionId()).
                            $read->quoteInto(" AND dest_zip=?) ", $postcode)
                        );
                    break;
                case 1:
                    $select->where(
                       $read->quoteInto("  (dest_country_id=? ", $request->getDestCountryId()).
                            $read->quoteInto(" AND dest_region_id=? AND dest_zip='0000') ", $request->getDestRegionId())
                       );
                    break;

                case 2:
                    $select->where(
                       $read->quoteInto("  (dest_country_id=? AND dest_region_id='0' AND dest_zip='0000') ", $request->getDestCountryId())
                    );
                    break;
                case 3:
                    $select->where(
                        $read->quoteInto("  (dest_country_id=? AND dest_region_id='0' ", $request->getDestCountryId()).
                        $read->quoteInto("  AND dest_zip=?) ", $postcode)
                        );
                    break;
                case 4:
                    $select->where(
                            "  (dest_country_id='0' AND dest_region_id='0' AND dest_zip='0000')"
                );
                    break;
            }

            if (is_array($request->getConditionName())) {
                $i = 0;
                foreach ($request->getConditionName() as $conditionName) {
                    $select->where('weight_from<=?', $request->getData($conditionName));
                    $select->where('weight_to>=?', $request->getData($conditionName));

                    $i++;
                }
            } else {
                $select->where('weight_from<=?', $request->getData($request->getConditionName()));
                $select->where('weight_to>=?', $request->getData($request->getConditionName()));
            }
            $select->where('website_id=?', $request->getWebsiteId());

            $select->order('dest_country_id DESC');
            $select->order('dest_region_id DESC');
            $select->order('dest_zip DESC');
            $select->order('weight_from DESC');

            // pdo has an issue. we cannot use bind

            $newdata=array();
            Mage::log($select->__toString());
            $row = $read->fetchAll($select);
            if (!empty($row) && ($j < 5)) {
                // have found a result or found nothing and at end of list!
                foreach ($row as $data) {
                    try {
                        $price = (float)($data['price']);

                        // add per-Kg cost
                        $conditionValue = (float)($request->getData($request->getConditionName()));
                        $price += (float)($data['price_per_kg']) * $conditionValue;

                        // add signature cost
                        $price += $signatureCost;

                        // add version without insurance
                        $data['price'] = (string)$price;
                        $newdata[]=$data;

                        if (Mage::getStoreConfig('carriers/eparcel/insurance_enable', $storeId)) {
                            // add version with insurance
                            // work out how many insurance 'steps' we have
                            $steps = ceil($request->getPackageValue() / $insuranceStep);
                            Mage::log("Insurance steps: $steps");
                            // add on number of 'steps' multiplied by the
                            // insurance cost per step
                            $insuranceCost = $insuranceCostPerStep * $steps;
                            Mage::log("Insurance cost: $insuranceCost");
                            $price += $insuranceCost;

                            $data['price'] = (string)$price;
                            $data['delivery_type'] .= " with TransitCover";
                            $newdata[]=$data;
                        }
                    } catch (Exception $e) {
                        Mage::log($e->getMessage());
                    }
                }
                break;
            }
        }
        Mage::log(var_export($newdata, true));
        return $newdata;
    }

    public function uploadAndImport(Varien_Object $object)
    {
        $csvFile = $_FILES["groups"]["tmp_name"]["eccfreight"]["fields"]["import"]["value"];

        if (!empty($csvFile)) {

            $helper = Mage::helper('eccfreight/rate');
            $requiredColumnCount = count($helper->csvFieldMap);

            $csv = trim(file_get_contents($csvFile));

            $table = Mage::getSingleton('core/resource')->getTableName('eccfreight/rates');

            $websiteId = $object->getScopeId();

            if (!empty($csv)) {
                $exceptions = array();
                $csvLines = explode("\n", $csv);

                $csvHeaders = $this->_getCsvValues(array_shift($csvLines));
                if (count($csvHeaders) < $requiredColumnCount) {
                    $exceptions[0] = Mage::helper('shipping')->__('Less than ' . $requiredColumnCount . ' columns in the CSV header.');
                }

                $countryCodes = array();
                $regionCodes = array();
                foreach ($csvLines as $k => $csvLine) {
                    $csvLine = $this->_getCsvValues($csvLine, $csvHeaders);
                    $count = count($csvLine);
                    if ($count > 0 && $count < $requiredColumnCount) {
                        $exceptions[0] = Mage::helper('shipping')->__('Less than ' . $requiredColumnCount . ' columns in row ' . ($k + 1) . '.');
                    } else {
                        $countryCodes[] = $csvLine['country'];
                        $regionCodes[] = $csvLine['state'];
                    }
                }

                if (empty($exceptions)) {
                    $csvMap = array();
                    foreach ($helper->csvFieldMap as $dbKey => $csvHeader) {
                        $csvMap[strtolower($csvHeader)] = $dbKey;
                    }

                    $data = array();
                    $countryCodesToIds = array();
                    $regionCodesToIds = array();
                    $countryCodesIso2 = array();

                    $countryCollection = Mage::getResourceModel('directory/country_collection')->addCountryCodeFilter($countryCodes)->load();
                    foreach ($countryCollection->getItems() as $country) {
                        $countryCodesToIds[$country->getData('iso3_code')] = $country->getData('country_id');
                        $countryCodesToIds[$country->getData('iso2_code')] = $country->getData('country_id');
                        $countryCodesIso2[] = $country->getData('iso2_code');
                    }

                    $regionCollection = Mage::getResourceModel('directory/region_collection')
                        ->addRegionCodeFilter($regionCodes)
                        ->addCountryFilter($countryCodesIso2)
                        ->load();

                    foreach ($regionCollection->getItems() as $region) {
                        $regionCodesToIds[$region->getData('code')] = $region->getData('region_id');
                    }

                    foreach ($csvLines as $k=>$csvLine) {
                        $csvLine = $this->_getCsvValues($csvLine, $csvHeaders);

                        if (empty($countryCodesToIds) || !array_key_exists($csvLine['country'], $countryCodesToIds)) {
                            $countryId = '0';
                            if ($csvLine['country'] != '*' && $csvLine['country'] != '') {
                                $exceptions[] = Mage::helper('shipping')->__('Invalid country "%s" on row #%s', $csvLine['country'], ($k+1));
                            }
                        } else {
                            $countryId = $countryCodesToIds[$csvLine['country']];
                        }

                        if (empty($regionCodesToIds) || !array_key_exists($csvLine['state'], $regionCodesToIds)) {
                            $regionId = '0';
                            if ($csvLine['state'] != '*' && $csvLine['state'] != '') {
                                $exceptions[] = Mage::helper('shipping')->__('Invalid region/state "%s" on row #%s', $csvLine['state'], ($k+1));
                            }
                        } else {
                            $regionId = $regionCodesToIds[$csvLine['state']];
                        }

                        if ($csvLine['postcodes'] == '*' || $csvLine['postcodes'] == '') {
                            $zip = '';
                        } else {
                            $zip = $csvLine['postcodes'];
                        }

                        if (!$this->_isPositiveDecimalNumber($csvLine['weight from']) || $csvLine['weight from'] == '*' || $csvLine['weight from'] == '') {
                            $exceptions[] = Mage::helper('shipping')->__('Invalid value for weight from "%s" on row #%s', $csvLine['weight from'], ($k+1));
                        } else {
                            $csvLine['weight from'] = (float)$csvLine['weight from'];
                        }

                        if (!$this->_isPositiveDecimalNumber($csvLine['weight to']) || $csvLine['weight to'] == '*' || $csvLine['weight to'] == '') {
                            $exceptions[] = Mage::helper('shipping')->__('Invalid value for weight to "%s" on row #%s', $csvLine['weight to'], ($k+1));
                        } else {
                            $csvLine['weight to'] = (float)$csvLine['weight to'];
                        }

                        if (!$this->_isPositiveDecimalNumber($csvLine['basic price'])) {
                            $exceptions[] = Mage::helper('shipping')->__('Invalid basic price "%s" on row #%s', $csvLine['basic price'], ($k+1));
                        } else {
                            $csvLine['basic price'] = (float)$csvLine['basic price'];
                        }

                        if (!$this->_isPositiveDecimalNumber($csvLine['price per kg'])) {
                            $exceptions[] = Mage::helper('shipping')->__('Invalid kg price "%s" on row #%s', $csvLine['price per kg'], ($k+1));
                        } else {
                            $csvLine['price per kg'] = (float)$csvLine['price per kg'];
                        }

                        if (!$this->_isPositiveDecimalNumber($csvLine['price per article'])) {
                            $exceptions[] = Mage::helper('shipping')->__('Invalid article price "%s" on row #%s', $csvLine['price per article'], ($k+1));
                        } else {
                            $csvLine['price per article'] = (float)$csvLine['price per article'];
                        }

                        $dataset = array(
                            'website_id'             => $websiteId,
                            'dest_country_id'        => $countryId,
                            'dest_region_id'         => $regionId,
                            'dest_zip'               => $zip,
                        );

                        $dataDetails[] = array(
                            'country' => $csvLine['country'],
                            'region' => $csvLine['state']
                        );

                        unset($csvLine['country']);
                        unset($csvLine['state']);
                        unset($csvLine['postcodes']);

                        foreach ($csvLine as $k => $v) {
                            if (isset($csvMap[$k])) {
                                $dataset[$csvMap[$k]] = $v;
                            }
                        }

                        $data[] = $dataset;

                    }
                }

                if (empty($exceptions)) {
                    $connection = $this->_getWriteAdapter();

                    $condition = array(
                        $connection->quoteInto('website_id = ?', $websiteId),
                    );
                    $connection->delete($table, $condition);

                    Mage::log(count($data)." lines read from CSV");
                    foreach($data as $k=>$dataLine) {
                        try {
                            // convert comma-seperated postcode/postcode range
                            // string into an array
                            $postcodes = array();
                            foreach(explode(',', $dataLine['dest_zip']) as $postcodeEntry) {
                                $postcodeEntry = explode("-", trim($postcodeEntry));
                                if(count($postcodeEntry) == 1) {
                                    Mage::log("Line $k, single postcode: ".$postcodeEntry[0]);
                                    // if the postcode entry is length 1, it's
                                    // just a single postcode
                                    $postcodes[] = $postcodeEntry[0];
                                } else {
                                    // otherwise it's a range, so convert that
                                    // to a sequence of numbers
                                    $pcode1 = (int)$postcodeEntry[0];
                                    $pcode2 = (int)$postcodeEntry[1];
                                    Mage::log("Line $k, postcode range: $pcode1-$pcode2");

                                    $postcodes = array_merge($postcodes, range(min($pcode1, $pcode2), max($pcode1, $pcode2)));
                                }
                            }

                            foreach($postcodes as $postcode) {
                                $dataLine['dest_zip'] = str_pad($postcode, 4, "0", STR_PAD_LEFT);
                                mage::log(__METHOD__.'() inserting '.json_encode($dataLine));
                                $connection->insert($table, $dataLine);
                            }
                        } catch (Exception $e) {
                            Mage::log($e->getMessage());
                            $exceptions[] = $e->getMessage();
                        }
                    }
                }

                if (!empty($exceptions)) {
                    throw new Exception( "\n" . implode("\n", $exceptions) );
                }
            }
        }
    }

    /**
     * Due to bugs in fgetcsv(), this extension is using tips from php.net.
     * We could potentially swap this out for Zend's CSV parsers after testing for bugs in that.
     *
     * Note: I've updated this code the latest version in the comments on php.net (Jonathan Melnick)
     *
     * @author Jonathan Melnick
     * @author Chris Norton
     * @author Dave Walter
     * @author justin at cam dot org
     * @author Theodule
     * @author dan dot jones at lunarfish dot co dot uk
     *
     * @see http://www.php.net/manual/en/function.split.php#81490
     * @see https://bugs.php.net/bug.php?id=45356
     * @see http://stackoverflow.com/questions/12390851/fgetcsv-is-eating-the-first-letter-of-a-string-if-its-an-umlaut
     *
     * @param string $string
     * @param string $separator
     * @return array
     */
    protected function _getCsvValues($string, $headers=null)
    {
        $separator = ',';
        $elements = explode($separator, $string);
        for ($i = 0; $i < count($elements); $i++) {
            $nquotes = substr_count($elements[$i], '"');
            if ($nquotes %2 == 1) {
                for ($j = $i+1; $j < count($elements); $j++) {
                    if (substr_count($elements[$j], '"') %2 == 1) { // Look for an odd-number of quotes
                        // Put the quoted string's pieces back together again
                        array_splice($elements, $i, $j-$i+1, implode($separator, array_slice($elements, $i, $j-$i+1)));
                        break;
                    }
                }
            }
            if ($nquotes > 0) {
                // Remove first and last quotes, then merge pairs of quotes
                $qstr =& $elements[$i];
                $qstr = substr_replace($qstr, '', strpos($qstr, '"'), 1);
                $qstr = substr_replace($qstr, '', strrpos($qstr, '"'), 1);
                $qstr = str_replace('""', '"', $qstr);
            }
            $elements[$i] = trim($elements[$i]);
        }

        if (isset($headers) && is_array($headers) && count($headers) == count($elements)) {
            $headers = array_map('strtolower', $headers);
            $elements = array_combine($headers, $elements);
        }
        return $elements;
    }

    /**
     * @param string $n
     * @return int
     */
    protected function _isPositiveDecimalNumber($n)
    {
        return preg_match("/^[0-9]+(\.[0-9]*)?$/", $n);
    }
}
