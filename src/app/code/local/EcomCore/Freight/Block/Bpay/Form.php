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

class EcomCore_Freight_Block_Bpay_Form extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('ecomcore/freight/payment/bpay/form.phtml');
    }
}
