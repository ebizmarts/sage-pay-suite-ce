<?php

/**
 * JS vars
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */

class Ebizmarts_SagePaySuite_Block_JavascriptVars extends Mage_Core_Block_Template
{

    public function __construct()
    {
    	Mage::getModel('sagepaysuite/session')->clear();
        $this->assign('valid', $this->helper('sagepaysuite')->F91B2E37D34E5DC4FFC59C324BDC1157C());
    }

    public function getQuote()
    {
        return Mage::getSingleton('checkout/session')->getQuote();
    }

    public function isMS()
    {
        return (bool)Mage::getSingleton('checkout/session')->getQuote()->getIsMultiShipping();
    }

}