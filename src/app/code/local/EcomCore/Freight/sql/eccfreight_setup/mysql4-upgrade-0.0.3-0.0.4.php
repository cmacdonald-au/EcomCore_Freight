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
 * @author     Jonathan Melnick
 * @copyright  Copyright (c) 2014 EcomCore Pty. Ltd. (http://www.ecomcore.com.au)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

set_time_limit(0);

Mage::log(__FILE__.' Running installer');

$installer = $this;
$installer->startSetup();
$installer->getConnection()
    ->addColumn($this->getTable('eccfreight_rates'),
    'increment_start',
    array(
        'type' => Varien_Db_Ddl_Table::TYPE_DECIMAL,
        'nullable' => true,
        'default' => null,
        'after' => 'increment_weight',
        'precision' => '12',
        'scale' => '2',
        'comment' => 'increment start weight'
    )
);

$installer->endSetup();
