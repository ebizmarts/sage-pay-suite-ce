<?php

/**
 * Token payment form
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */
class Ebizmarts_SagePaySuite_Block_Form_SagePayToken extends Mage_Payment_Block_Form_Cc {

    public function getTokenCards($methodCode = null) {
        $allCards = $this->helper('sagepaysuite/token')->loadCustomerCards($methodCode);

        return $allCards;
    }

    public function canUseToken() {
        $ret = Mage::getModel('sagepaysuite/sagePayToken')->isEnabled();

        if(!$this->helper('sagepaysuite')->creatingAdminOrder()) {
            $ret = $ret && (Mage::getModel('checkout/type_onepage')->getCheckoutMethod() != 'guest');
        }

        return $ret;
    }

}