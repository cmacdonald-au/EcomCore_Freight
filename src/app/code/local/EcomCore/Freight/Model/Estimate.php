<?php

class EcomCore_Freight_Model_Estimate
{

    protected $quote;
    protected $products = array();
    public    $result;

    public $destination = array(
        'country_id' => '',
        'region_id'  => '',
        'postcode'   => '',
        'region'     => '',
        'city'       => '',
    );

    public $existingCart = false;
    public $couponCode   = false;

    public function setProducts($skuList)
    {
        $this->products = array();
        foreach ($skuList as $sku) {
            $product = Mage::helper('catalog/product')->getProduct($sku, $this->getQuote()->getStoreId());
            if ($product->hasData()) {
                $this->products[] = $product;
            }
        }
    }

    public function setDestination($destData)
    {
        foreach ($destData as $k => $v) {
            if (array_key_exists($k, $this->destination)) {
                $this->destination[$k] = $v;
            }
        }
    }

    /**
     * Retrieve sales quote object
     *
     * @return Mage_Sales_Modelquote
     */
    public function getQuote()
    {
        if ($this->quote === null) {
            if ($this->existingCart) {
                $this->quote = Mage::getSingleton('checkout/session')->getQuote();
            } else {
                $this->quote = Mage::getModel('sales/quote');
            }
        }

        return $this->quote;
    }

    /**
    * Reset quote object
    *
    * @return EcomDev_ProductPageShipping_Model_Estimate
    */
    public function resetQuote()
    {
        $this->getQuote()->removeAllAddresses();

        if ($this->getCustomer()) {
            $this->getQuote()->setCustomer($this->getCustomer());
        }

        return $this;
    }     

    /**
     * Retrieve currently logged in customer,
     * if customer isn't logged it returns false
     *
     * @return Mage_Customer_Model_Customer|boolean
     */
    public function getCustomer()
    {
        if ($this->_customer === null) {
            $customerSession = Mage::getSingleton('customer/session');
            if ($customerSession->isLoggedIn()) {
                $this->_customer = $customerSession->getCustomer();
            } else {
                $this->_customer = false;
            }
        }
        return $this->_customer;
    }

    /**
     * Retrieve list of shipping rates
     *
     * @return EcomCore_Freight_Model_Estimate
     */
    public function process()
    {
        if ($this->existingCart) {
            $this->resetQuote();
        }

        $shippingAddress = $this->getQuote()->getShippingAddress();

        $shippingAddress->setCountryId($this->destination['country_id']);

        if (!empty($this->destination['region_id'])) {
            $shippingAddress->setRegionId($this->destination['region_id']);
        }

        if (!empty($this->destination['postcode'])) {
            $shippingAddress->setPostcode($this->destination['postcode']);
        }

        if (!empty($this->destination['region'])) {
            $shippingAddress->setRegion($this->destination['region']);
        }

        if (!empty($this->destination['city'])) {
            $shippingAddress->setCity($this->destination['city']);
        }

        $shippingAddress->setData('collect_shipping_rates',true);

        if (!empty($this->coupon_code)) {
            $this->getQuote()->setCouponCode($this->coupon_code);
        }

        foreach ($this->products as $product) {
            $addToCartInfo = (array) $product->getAddToCartInfo();
            $request = new Varien_Object($addToCartInfo); 

            if ($product->getStockItem()) {
                $minimumQty = $product->getStockItem()->getMinSaleQty();
                if($minimumQty > 0 && $request->getQty() < $minimumQty){
                    $request->setQty($minimumQty);
                }
            }

            $result = $this->getQuote()->addProduct($product, $request);

            if (is_string($result)) {
                Mage::throwException($result);
            }

            Mage::dispatchEvent('checkout_cart_product_add_after',
                                array('quote_item' => $result, 'product' => $product));
        }

        $this->getQuote()->collectTotals();
        $this->result = $shippingAddress->getGroupedAllShippingRates();
        return $this;
    }    
}