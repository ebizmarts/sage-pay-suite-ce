<?php

/**
 * SagePaySuite PI integration library inclusion
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */
class Ebizmarts_SagePaySuite_Block_ApiLibrary extends Mage_Core_Block_Template
{

    /**
     * Return URL for API Library
     *
     * @return string
     */
    public function getAPIUrl()
    {
        $piModel = Mage::getModel('sagepaysuite/sagePayNit');
        return $piModel->getUrl("api");
    }

    /**
     * Disable url inclusion if not enabled
     *
     * @return string
     */
    protected function _toHtml()
    {
        if(false === Mage::getStoreConfigFlag('payment/sagepaynit/active')){
            return '';
        }
        return parent::_toHtml();
    }
}