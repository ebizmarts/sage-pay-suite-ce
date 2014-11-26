<?php

/**
 * Token list block
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */

class Ebizmarts_SagePaySuite_Block_Form_TokenList extends Mage_Core_Block_Template {

    protected function _construct() {
        parent::_construct();
        $this->setTemplate('sagepaysuite/payment/form/sagepayTokenList.phtml');
    }

    /**
     * Render block HTML
     *
     * @return string
     */
    protected function _toHtml() {
        if (!$this->getCanUseToken()) {
            return '';
        }
        return parent::_toHtml();
    }

    public function getAvailableTokenCards($methodCode = null) {
        $allCards = $this->helper('sagepaysuite/token')->loadCustomerCards($methodCode);

        return $allCards;
    }

}