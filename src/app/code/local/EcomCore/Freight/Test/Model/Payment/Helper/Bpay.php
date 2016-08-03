<?php


class EcomCore_Freight_Test_Model_Payment_Bpay_Helper_Bpay extends EcomCore_Freight_Model_Payment_Bpay
{

    /**
     * Proxy method to expose the protected method.
     *
     * @param integer $number
     *
     * @return integer
     */
    public function _caculateRefMod10v5($number)
    {
        return parent::_caculateRefMod10v5($number);
    }
}
