<?php

class Ebizmarts_SagePaySuite_Helper_Paymentinfo extends Mage_Core_Helper_Abstract
{
    public function getFraudInfo($orderId)
    {
    	return Mage::getModel('sagepayreporting/sagepayreporting_fraud')->loadByOrderId($orderId);
    }
}