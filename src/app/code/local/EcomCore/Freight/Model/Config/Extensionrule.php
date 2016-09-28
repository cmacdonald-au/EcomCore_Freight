<?php

class EcomCore_Freight_Model_Config_Extensionrule
{
    /**
     * Attributes array
     *
     * @var null|array
     */
    protected $_attributes = null;


    /**
     * Retrieve attributes as array
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array('value' => 'cheapest_first', 'label' => Mage::helper('adminhtml')->__('Use cheapest overall rate')),
            array('value' => 'use_extend', 'label' => Mage::helper('adminhtml')->__('Take first result from an extension class')),
        );
   }
}