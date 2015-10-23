<?php

class Ebizmarts_SagePaySuite_Model_Sagepaysuite_Tokencard extends Mage_Core_Model_Abstract {

    protected function _construct() {
        $this->_init('sagepaysuite2/sagepaysuite_tokencard');
    }

    /**
     * Processing object before save data
     *
     * @return Mage_Core_Model_Abstract
     */
    protected function _beforeSave() {
        $card = $this->getCollection()
                        ->addCustomerFilter(Mage::getModel('sagepaysuite/api_payment')->getCustomerQuoteId())
                        ->addFieldToFilter('is_default', (int) 1)
                        ->load()->getFirstItem();

        if (!$card->getId()) {
            $this->setIsDefault(1);
        }

        return parent::_beforeSave();
    }

    public function getDefaultCard() {
        $card = $this->getCollection()
                        ->addCustomerFilter(Mage::getModel('sagepaysuite/api_payment')->getCustomerQuoteId())
                        ->addFieldToFilter('is_default', (int) 1)
                        ->load()->getFirstItem();

        if ($card->getId()) {
            return $card;
        }
        return new Varien_Object;
    }

    public function resetCustomerDefault() {
        $card = $this->getCollection()
                        ->addCustomerFilter(Mage::getModel('sagepaysuite/api_payment')->getCustomerQuoteId())
                        ->addFieldToFilter('is_default', (int) 1)
                        ->load()->getFirstItem();

        if ($card->getId()) {
            $card->setIsDefault(0)
                    ->save();
        }
    }

    public function setIsDefault($value) {
        if ((int) $value == 1) {
            # Reset current default card
            Mage::getModel('sagepaysuite2/sagepaysuite_tokencard')->resetCustomerDefault();
        }

        $this->setData('is_default', $value);
        return $this;
    }

    public function getLabel($withImage = true) {
        return Mage::helper('sagepaysuite')->getCardLabel($this->getCardType(), $withImage);
    }

    public function getCcNumber() {
        return '***********' . $this->getLastFour();
    }

    public function getExpireDate() {
        return Mage::helper('sagepaysuite')->getCardNiceDate($this->getExpiryDate());
    }

    public function loadByToken($token) {
        $this->load($token, 'token');
        return $this;
    }

}