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
class EcomCore_Freight_IndexController extends Mage_Core_Controller_Front_Action
{

    public function indexAction()
    {
        $idList  = $this->getRequest()->getParam('i'); // (string)<id> or (json){<id>:[qty];<id>:[qty][;<id>:qty...]} qty will default to 1
        $skuList = $this->getRequest()->getParam('p'); // (string)<sku> or (json){<sku>:[qty];<sku>:[qty][;<sku>:qty...]} qty will default to 1 if unspecified
        $dest    = $this->getRequest()->getParam('d'); // (int)<postcode> or (json){postcode:<postcode>;country_id:<country_id>;...}
        $render  = $this->getRequest()->getParam('r'); // optional (int)1 render 1 or all
        $output  = $this->getRequest()->getParam('o'); // optional (string)"js" render javascript block
        $target  = $this->getRequest()->getParam('t'); // optional (string) element id for javascript output to set innerText

        $overrides = $this->getRequest()->getParam('overrides');
        if ($overrides == 'false') {
            Mage::app()->getStore()->setConfig('carriers/eccfreight/alsoprocess', '');
        }

        if ($output == 'js') {
        	$render = 1;
        	if (!empty($target)) {
        		$target = preg_replace('/[^0-9a-zA-Z\._\-]/', '', $target);
        	} else {
        		$target = 'shippingCost';
        	}
        }

        if (empty($skuList) && empty($idList)) {
            return false;
        }

        if (empty($dest)) {
            return false;
        }

        if (!empty($skuList)) {
            $productList = json_decode($skuList, true);
            if ($productList === null) {
                $productList = $this->getRequest()->getParam('p');
            }
            $listType = EcomCore_Freight_Model_Estimate::PLISTTYPE_SKU;
        } else if (!empty($idList)) {
            $productList = json_decode($idList, true);
            if ($productList === null) {
                $productList = $this->getRequest()->getParam('i');
            }
            $listType = EcomCore_Freight_Model_Estimate::PLISTTYPE_ID;
        }

        if (is_array($productList) == false && (is_string($productList) || is_int($productList))) {
        	$qty = max($this->getRequest()->getParam('q'), 1);
        	$productList = array($productList => $qty);
        }
        Mage::log(__METHOD__.'() Processing for product list '.json_encode($productList));

        $destData = json_decode($dest);
        if ($destData === false || empty($destData)) {
            Mage::log(__METHOD__.'() No destination');
        	return false;
        }

        if (is_int($destData)) {
            Mage::log(__METHOD__.'() Single destination: '.$destData);
        	//single val = postcode
	        $region   = Mage::helper('eccfreight/data')->getRegion($destData, 'AU');
        	$destData = array('country_id'=>'AU', 'postcode'=>$destData, 'region_id'=>$region['region_id']);
        } else if (is_object($destData)) {
        	$destData = (array)$destData;
        }

        if (isset($destData['region']) && false == isset($destData['region_id'])) {
            Mage::log(__METHOD__.'() Looking up region for {'.$destData['postcode'].'}, {'.$destData['country_id'].'}');
        	$region = Mage::helper('eccfreight/data')->getRegion($destData['postcode'], $destData['country_id']);
        	$destData['region_id'] = $region['region_id'];
        }

        Mage::log(__METHOD__.'() Getting shipping estimates for '.json_encode($productList).' ['.$listType.']');

        $estimate = Mage::helper('eccfreight/rate')->getEstimate($productList, $listType, $destData);

        Mage::log(__METHOD__.'() Processed. Got '.count($estimate->result).' carrier');

        $rates = Mage::helper('eccfreight/rate')->extractRateData($estimate);

        Mage::log(__METHOD__.'() Final rate count: '.count($rates));

        if ($render == 1) {
        	if ($output == 'js') {
        		$outputData = $rates['cheapest']['price'];
        		if ($outputData == 0) {
        			$outputData = 'FREE';
        		}
	            print 'document.getElementById("'.$target.'").innerText = "$'.$outputData.'";';
        	} else {
	            print $rates['cheapest']['name'].': '.$rates['cheapest']['price']."<br />";
	        }
        } else {
        	print json_encode($rates['list']);
        }

    }

}