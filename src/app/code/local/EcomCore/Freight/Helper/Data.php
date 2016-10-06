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
 * Data helper
 *
 * @category   EcomCore
 * @package    EcomCore_Freight
 */
class EcomCore_Freight_Helper_Data extends Mage_Core_Helper_Abstract
{

    const MAX_QUERY_LEN = 100;

    const MAX_AUTOCOMPLETE_RESULTS_DEFAULT = 20;

    const AUSTRALIA_COUNTRY_CODE = 'AU';

    protected $queryText;
    public $configValues;

    /**
     * Gets the query text for city lookups in the postcode database.
     *
     * @return string
     */
    public function getQueryText()
    {
        if (is_null($this->queryText)) {
            if ($this->_getRequest()->getParam('billing')) {
                $tmp = $this->_getRequest()->getParam('billing');
                $this->queryText = $tmp['city'];
            } elseif ($this->_getRequest()->getParam('shipping')) {
                $tmp = $this->_getRequest()->getParam('shipping');
                $this->queryText = $tmp['city'];
            } else {
                $this->queryText = $this->_getRequest()->getParam('city');
            }
            $this->queryText = trim($this->queryText);
            if (Mage::helper('core/string')->strlen($this->queryText) > self::MAX_QUERY_LEN) {
                $this->queryText = Mage::helper('core/string')->substr($this->queryText, 0, self::MAX_QUERY_LEN);
            }
        }
        return $this->queryText;
    }

    /**
     * @return string
     */
    public function getQueryCountry()
    {
        return $this->_getRequest()->getParam('country');
    }

    /**
     * @return int
     */
    public function getPostcodeAutocompleteMaxResults()
    {
        $max = Mage::getStoreConfig("ecomcore_freight/postcode_autocomplete/max_results");
        if (!is_numeric($max)) {
            return self::MAX_AUTOCOMPLETE_RESULTS_DEFAULT;
        }
        $max = (int) $max;
        if ($max > 0) {
            return $max;
        } else {
            return self::MAX_AUTOCOMPLETE_RESULTS_DEFAULT;
        }
    }

    public function getRegion($postcode, $country)
    {
        $res  = Mage::getSingleton('core/resource');
        $conn = $res->getConnection('eccfreight_read');
        return $conn->fetchRow('SELECT au.*, dcr.* FROM ' . $res->getTableName('eccfreight_postcode') . ' AS au
             INNER JOIN ' . $res->getTableName('directory_country_region') . ' AS dcr ON au.region_code = dcr.code
             WHERE postcode = :postcode LIMIT 1', array('postcode' => $postcode));
    }

    /**
     * @return array
     */
    public function getPostcodeAutocompleteResults()
    {
        $country = $this->getQueryCountry();
        if ($country != "AU") {
            return array();
        }

        $res = Mage::getSingleton('core/resource');
        /* @var $conn Varien_Db_Adapter_Pdo_Mysql */
        $conn = $res->getConnection('eccfreight_read');
        return $conn->fetchAll(
            'SELECT au.*, dcr.region_id FROM ' . $res->getTableName('eccfreight_postcode') . ' AS au
             INNER JOIN ' . $res->getTableName('directory_country_region') . ' AS dcr ON au.region_code = dcr.code
             WHERE city LIKE :city ORDER BY city, region_code, postcode
             LIMIT ' . $this->getPostcodeAutocompleteMaxResults(),
            array('city' => '%' . $this->getQueryText() . '%')
        );
    }

    public function getConfigValue($key)
    {
        if (empty($this->configValues)) {
            $this->configValues = Mage::getStoreConfig('carriers/eccfreight', $this->websiteId);
        }
        if (isset($this->configValues[$key])) {
            return $this->configValues[$key];
        }

        return false;
    }


}
