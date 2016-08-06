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

    public function indexAction() {

        $skuList = $this->getRequest()->getParam('p');
        $dest    = $this->getRequest()->getParam('d');
        $render  = $this->getRequest()->getParam('r');
        $output  = $this->getRequest()->getParam('o');
        $target  = $this->getRequest()->getParam('t');

        if ($output == 'js') {
        	$render = 1;
        	if (!empty($target)) {
        		$target = preg_replace('/[^0-9a-zA-Z\._\-]/', '', $target);
        	} else {
        		$target = 'shippingCost';
        	}
        }

        if (empty($skuList)) {
            return false;
        }

        if (empty($dest)) {
            return false;
        }

        $skuList = json_decode($skuList, true);
        if ($skuList === false || empty($skuList)) {
        	$skuList = array($this->getRequest()->getParam('p'));
        }
        if (empty($skuList)) {
        	return false;
        }

        $destData = json_decode($dest);
        if ($destData === false || empty($destData)) {
        	return false;
        }
        if (is_int($destData)) {
        	//single vals = postcodes
	        $region   = Mage::helper('eccfreight/data')->getRegion($destData, 'AU');
        	$destData = array('country_id'=>'AU', 'postcode'=>$destData, 'region_id'=>$region['region_id']);
        } else if (is_object($destData)) {
        	$destData = (array)$destData;
        }

        if (isset($destData['region']) && false == isset($destData['region_id'])) {
        	$region   = Mage::helper('eccfreight/data')->getRegion($destData['postcode'], $destData['country_id']);
        	$destData['region_id'] = $region['region_id'];
        }

        $estimate = Mage::getModel('eccfreight/estimate');
        $estimate->setProducts($skuList);
        $estimate->setDestination($destData);
        $rates = $estimate->getRates();

        if ($render == 1) {
	        $cheapestRate = array('price' => null, 'name' => '');
        } else {
        	$rateList     = array();
        }

        foreach ($rates as $code => $rate) {
            foreach ($rate as $option) {
                if ($option->getErrorMessage()) {
                    continue;
                }

                if ($render == 1) {
                    if ($cheapestRate['price'] === null || $option->getPrice() < $cheapestRate['price']) {
                        $cheapestRate['price'] = $option->getPrice();
                        $cheapestRate['name']  = $option->getMethodTitle();
                    }
                } else {
                    $rateList[$option->getMethodTitle()] = $option->getPrice();
                }
            }
        }

        if ($render == 1) {
        	if ($output == 'js') {
        		$outputData = $cheapestRate['price'];
        		if ($outputData == 0) {
        			$outputData = 'FREE';
        		}
	            print 'document.getElementById("'.$target.'").innerText = "$'.$outputData.'";';
        	} else {
	            print $cheapestRate['name'].': '.$cheapestRate['price']."<br />";
	        }
        } else {
        	print json_encode($rateList);
        }

    }

}