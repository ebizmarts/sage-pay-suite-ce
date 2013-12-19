<?php

/**
 * SagePaySuite online logo with additional options
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */
class Ebizmarts_SagePaySuite_Block_Logo extends Mage_Core_Block_Template
{
    /**
     * Return URL for Paypal Landing page
     *
     * @return string
     */
    public function getSagePayUrl()
    {
        #return 'https://support.sagepay.com/apply/default.aspx?partnerID=01bf51f9-0dcd-49dd-a07a-3b1f918c77d7';
        return 'https://www.sagepay.com/';
    }

    /**
     * Disable block output if logo turned off
     *
     * @return string
     */
    protected function _toHtml()
    {
    	if(false === Mage::getStoreConfigFlag('payment/sagepaysuite/cms_index_logo')){
    		return '';
    	}
        $this->setLogoImageUrl($this->getSkinUrl('sagepaysuite/images/secured-by-sage-pay.png'));
        $this->setPartnerLogoImageUrl($this->getSkinUrl('sagepaysuite/images/sagelogo-partner.jpg'));
        return parent::_toHtml();
    }
}