<?php

class EcomCore_Freight_Model_Estimate
{

    const PLISTTYPE_ID  = 0;
    const PLISTTYPE_SKU = 1;

    protected $quote;
    protected $products = array();
    protected $customer;
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

    public function setProducts($productList, $listType=self::PLISTTYPE_SKU)
    {
        $this->products = array();
        foreach ($productList as $ident => $qty) {
            if ($listType == self::PLISTTYPE_SKU) {
                Mage::log(__METHOD__.'() Looking up product with sku `'.$ident.'`');
                $product = Mage::helper('catalog/product')->getProduct($ident, $this->getQuote()->getStoreId(), 'sku');
            } else if ($listType == self::PLISTTYPE_ID) {
                Mage::log(__METHOD__.'() Loading product with id `'.$ident.'`');
                $product = Mage::helper('catalog/product')->getProduct($ident, $this->getQuote()->getStoreId(), 'id');
            } else {
                Mage::log(__METHOD__.'() Loading product with identifier `'.$ident.'`');
                $product = Mage::helper('catalog/product')->getProduct($ident, $this->getQuote()->getStoreId());
            }
            if ($product->hasData()) {
                Mage::log(__METHOD__.'() Success..');
                $this->products[] = array('qty' => $qty, 'product' => $product);
            } else {
                Mage::log(__METHOD__.'() Failed');
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
            $quote = Mage::getSingleton('checkout/type_onepage')->getQuote();
            if ($quote->hasData()) {
                $this->existingCart = true;
                $this->quote = $quote;
            } else {
                $this->quote = $quote;
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
        if ($this->customer === null) {
            $customerSession = Mage::getSingleton('customer/session');
            if ($customerSession->isLoggedIn()) {
                $this->customer = $customerSession->getCustomer();
            } else {
                $this->customer = false;
            }
        }
        return $this->customer;
    }

    /**
     * Retrieve list of shipping rates
     *
     * @return EcomCore_Freight_Model_Estimate
     */
    public function process()
    {
        mage::log(__METHOD__.'() init');
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

        foreach ($this->products as $p) {
            $request = new Varien_Object(array('qty' => $p['qty']));
            $product = $p['product'];

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