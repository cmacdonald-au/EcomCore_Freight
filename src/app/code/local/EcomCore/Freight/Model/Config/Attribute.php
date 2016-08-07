<?php

class EcomCore_Freight_Model_Config_Attribute
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
        if (is_null($this->_attributes)) {
            $attrCollection = Mage::getResourceModel('catalog/product_attribute_collection')
                ->addVisibleFilter()
                ->setFrontendInputTypeFilter('text')
                ->setOrder('frontend_label', Varien_Data_Collection::SORT_ORDER_ASC);

            $this->_attributes = array(
                array(
                    'value' => '',
                    'label' => Mage::helper('eccfreight')->__('-- Please Select --'),
                )
            );
            foreach ($attrCollection as $attribute) {
                $this->_attributes[] = array(
                    'label' => $attribute->getFrontendLabel(),
                    'value' => $attribute->getId(),
                );
            }

        }
        return $this->_attributes;
    }
}