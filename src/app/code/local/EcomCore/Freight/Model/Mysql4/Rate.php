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

    protected $rateRequest;
    protected $configValues;
    protected $websiteId;

    protected function _construct()
    {
        $this->_init('eccfreight/rates', 'pk');
    }

    public function getRate(Mage_Shipping_Model_Rate_Request $request)
    {
        $this->rateRequest = $request;
        $this->websiteId = $request->getWebsiteId();
        $read = $this->_getReadAdapter();

        $rateHelper = Mage::helper('eccfreight/rate');
        $itemSummary = $rateHelper->summariseItems($request);

        $postcode = $request->getDestPostcode();
        $table    = $this->getMainTable();
        $storeId  = $request->getStoreId();

        $totalWeight = $request->getPackageWeight();

        Mage::log(__METHOD__.'() Country: '.$request->getDestCountryId());
        Mage::log(__METHOD__.'() Region: '.$request->getDestRegionId());
        Mage::log(__METHOD__.'() Postcode: '.$postcode);
        Mage::log(__METHOD__.'() Condition: '.var_export($request->getConditionName(), true));

        for ($j = 0; $j < 5; $j++) {

            $select = $read->select()->from($table);

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
                            $read->quoteInto(" AND dest_zip=?) ", $postcode)
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

            /** This needs to be genericised. Product mix can indicate mixed-rates apply, so some rates would be **/
            $select->where('weight_from<=?', $totalWeight);
            $select->where('weight_to>=?', $totalWeight);

            $select->where('website_id=?', $request->getWebsiteId());

            $select->order('dest_country_id DESC');
            $select->order('dest_region_id DESC');
            $select->order('dest_zip DESC');
            $select->order('weight_from DESC');

            $newdata=array();
            Mage::log($select->__toString());

            $row = $read->fetchAll($select);
            if (!empty($row) && ($j < 5)) {
                // We keep trying until we get a result, or run out of generic queries.
                // the most generic query clause in the above switch statement will be in effect when $j == 4

                foreach ($row as $data) {

                    try {

                        $price = 0;
                        if ($data['price_per_increment'] > 0 && empty($data['increment_weight'])) {
                            $data['increment_weight'] = 1;
                        }
                        if ($data['price_per_increment'] > 0 && empty($data['increment_start'])) {
                            $data['increment_start'] = 1;
                        }
                        $data['basic_price'] = $data['price'];

                        $totalWeight = 0;
                        $totalUnits  = 0;
                        foreach ($itemSummary as $className => $values) {
                            $totalWeight += $values['weight'];
                            $totalUnits  += $values['units'];
                            if (isset($values['charge'])) {
                                // Simple one - the rates have already been set for this product
                                $price += $values['charge'];
                            } else {
                                if ($data['consignment_option'] == 1) {
                                    //Consign per order (1 basic + however many increment charges)

                                    Mage::Log(__METHOD__.'() Calculating rates as a consignment');
                                    $price = (float)($data['price']);
                                    $chargeableWeight = $values['weight'];

                                    if ($data['increment_start'] > 1) {
                                        $chargeableWeight = max(0, ($chargeableWeight-$data['increment_start']));
                                        Mage::log(__METHOD__.'() Weight increments start at '.$data['increment_start'].' leaving us with '.$chargeableWeight);
                                    }

                                    $increments = 0;
                                    if ($chargeableWeight > 0) {
                                        if ($data['increment_weight'] > 0) {
                                            $increments = (ceil($chargeableWeight/$data['increment_weight']));
                                        } else if ($data['increment_weight'] === '') {
                                            $increments = ceil($chargeableWeight);
                                        }
                                    }

                                    if ($increments > 0) {
                                        $price += $increments * $data['price_per_increment'];
                                    }

                                    Mage::Log(__METHOD__.'() Consigned basic rate: '.$data['price'].' plus '.$increments.' @ '.$data['price_per_increment'].' each (~'.$data['increment_weight'].'kg, starting at '.$data['increment_start'].'kg) on a chargeable weight of '.$chargeableWeight.'kg, wi '.$values['units'].' units for a total weight of '.$values['weight']);

                                } else if ($data['consignment_option'] == 2) {
                                    //Consign per line (1 basic + however many increment charges for each item)

                                    Mage::Log(__METHOD__.'() Calculating rates per-line for '.$data['carrier_code'].'/'.$data['delivery_group']);
                                    $price = 0;
                                    foreach ($values['item_data'] as $sku => $item) {

                                        $price += (float)($data['price']);

                                        $chargeableWeight = $item['weight'];
                                        if ($data['increment_start'] > 1) {
                                            $chargeableWeight = max(0, ($chargeableWeight-$data['increment_start']));
                                        }

                                        $increments = 0;
                                        if ($chargeableWeight > 0) {
                                            $increments = ceil($chargeableWeight/$data['increment_weight']);
                                        } else if ($data['increment_weight'] === '') {
                                            $increments = ceil($chargeableWeight);
                                        }

                                        if ($increments > 0) {
                                            $price += $increments * $data['price_per_increment'];
                                        }

                                        if ($item['units'] > 1) {
                                            $additional = max(1,ceil($item['weight']/$data['increment_weight']));
                                            $additional *= $data['price_per_increment'];
                                            $price += $additional * ($item['units'] - 1);
                                        }

                                        Mage::Log(__METHOD__.'() Per-Line basic rate for item '.$sku.': '.$data['price'].' plus '.$increments.' x $'.$data['price_per_increment'].' ('.$data['increment_weight'].' increments, starting at '.$data['increment_start'].'kg over a chargeable weight of '.$chargeableWeight.'kg), wi '.$item['units'].' units. Total weight: '.$item['weight']);

                                    }

                                } else {

                                    //No consignment (basic + increment charge per unit)

                                    Mage::Log(__METHOD__.'() Calculating rates per-unit for '.$data['carrier_code'].'/'.$data['delivery_group']);
                                    $price = 0;
                                    foreach ($values['item_data'] as $item) {
                                        $unitPrice = (float)($data['price']);
                                        $increments = 0;
                                        $chargeableWeight = $item['weight'];

                                        if ($data['increment_start'] > 1) {
                                            $chargeableWeight = max(0, ($chargeableWeight-$data['increment_start']));
                                        }

                                        if ($chargeableWeight > $data['increment_weight']) {
                                            $increments = floor($chargeableWeight/$data['increment_weight']);
                                        }

                                        if ($increments > 0) {
                                            $unitPrice += $increments * $data['price_per_increment'];
                                        }

                                        $price += $unitPrice*$item['units'];

                                        Mage::Log(__METHOD__.'() Basic rate: '.$data['price'].' plus '.$increments.' x $'.$data['price_per_increment'].' on a chargeable weight of '.$chargeableWeight.' ('.$data['increment_weight'].'kg per increment after '.$data['increment_start'].'kg), per unit, for '.$item['units'].' units for a total weight of '.$item['weight']);

                                    }
                                }
                            }
                        }

                        $data['cost'] = (float)$price;
                        $data['price'] = (float)$price;
                        Mage::log(__METHOD__.'() Rate set: '.json_encode($data, true));
                        $newdata[] = $data;

                    } catch (Exception $e) {
                        Mage::log($e->getMessage());
                    }

                }
                break;

            }
        }
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

                        if (!$this->_isPositiveDecimalNumber($csvLine['price per increment'])) {
                            $exceptions[] = Mage::helper('shipping')->__('Invalid increment price "%s" on row #%s', $csvLine['price per kg'], ($k+1));
                        } else {
                            $csvLine['price per increment'] = (float)$csvLine['price per increment'];
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
                    $readCon    = $this->_getReadAdapter();
                    $connection = $this->_getWriteAdapter();

                    $condition = array(
                        $connection->quoteInto('website_id = ?', $websiteId),
                    );
                    $connection->delete($table, $condition);

                    $zoneMapping = $rateHelper->getConfigValue('zonemapping');
                    $zoneMappingRules = explode(',', $rateHelper->getConfigValue('zonemappingRules'));
                    if ($zoneMapping && count($zoneMappingRules) == 3) {
                        $tmp = $zoneMappingRules;
                        $zoneMappingRules = array();
                        foreach ($tmp as $v) {
                            $rule = explode(':', $v);
                            $zoneMappingRules[$rule[0]] = $rule[1];
                        }
                        $zoneMappingTable = Mage::getSingleton('core/resource')->getTableName($zoneMappingRules['table']);
                        $zoneMappingQuery = 'SELECT '.$zoneMappingRules['postcode'].' FROM '.$zoneMappingTable.' WHERE '.$zoneMappingRules['zone'].' = :zone';
                    }

                    Mage::log(count($data)." lines read from CSV");
                    foreach($data as $k=>$dataLine) {
                        try {
                            // convert comma-seperated postcode/postcode range
                            // string into an array
                            $postcodes = array();
                            foreach(explode(',', $dataLine['dest_zip']) as $postcodeEntry) {

                                if ($zoneMapping && preg_match('/[a-zA-Z]+/', $postcodeEntry)) {
                                    $result = $readCon->query($zoneMappingQuery, array('zone' => $postcodeEntry));
                                    while ($row = $result->fetch()) {
                                        $postcodes[] = $row[$zoneMappingRules['postcode']];
                                    }
                                    mage::log(__METHOD__.'() Got postcode list for '.$postcodeEntry.' {'.json_encode($postcodes).'}');
                                } else {
                                    $postcodeEntry = explode("-", trim($postcodeEntry));
                                    if(count($postcodeEntry) == 1) {
                                        // if the postcode entry is length 1, it's
                                        // just a single postcode
                                        $postcodes[] = $postcodeEntry[0];
                                        Mage::log("Line $k, single postcode: ".$postcodeEntry[0]);
                                    } else {
                                        // otherwise it's a range, so convert that
                                        // to a sequence of numbers
                                        $pcode1 = (int)$postcodeEntry[0];
                                        $pcode2 = (int)$postcodeEntry[1];

                                        $postcodes = array_merge($postcodes, range(min($pcode1, $pcode2), max($pcode1, $pcode2)));
                                        Mage::log("Line $k, postcode range: $pcode1-$pcode2");
                                    }
                                }
                            }

                            foreach($postcodes as $postcode) {
                                $dataLine['dest_zip'] = str_pad($postcode, 4, "0", STR_PAD_LEFT);
                                //mage::log(__METHOD__.'() inserting '.json_encode($dataLine));
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
