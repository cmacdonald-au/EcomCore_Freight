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

        if (empty($skuList)) {
            return false;
        }

        $skuList = explode(';', $skuList);
        if (empty($skuList)) {
            return false;
        }

        if (empty($dest)) {
            return false;
        }

        $region   = Mage::helper('eccfreight/data')->getRegion($dest, 'AU');
        $estimate = Mage::getModel('eccfreight/estimate');
        $estimate->setProducts($skuList);
        $estimate->setDestination(array('country_id'=>'AU', 'postcode'=>$dest, 'region_id'=>$region['region_id']));
        $rates = $estimate->getRates();

        $cheapestRate = array('price' => null, 'name' => '');
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
                    print $option->getMethodTitle().': '.$option->getPrice().'<br />';
                }
            }
        }

        if ($render == 1) {
            print $cheapestRate['name'].' : '.$cheapestRate['price']."<br />";
        }

    }

}