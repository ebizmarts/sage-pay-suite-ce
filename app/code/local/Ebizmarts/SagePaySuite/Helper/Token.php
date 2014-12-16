<?php

/**
 * TOKEN main helper.
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */
class Ebizmarts_SagePaySuite_Helper_Token extends Mage_Core_Helper_Abstract {

    protected $_tokenCards = null;

    public function getDataAsArray($cardId) {
        $card = Mage::getModel('sagepaysuite2/sagepaysuite_tokencard')->load($cardId);

        $_rest = array();

        if ($card->getId()) {
            $_rest['Card Type'] = $card->getLabel(false);
            $_rest['Card Number'] = $card->getCcNumber();
        }

        return $_rest;
    }

    public function getDefaultToken() {
        return Mage::getModel('sagepaysuite2/sagepaysuite_tokencard')->getDefaultCard();
    }

    public function loadCustomerCards($methodCode = null, $ccLastFour = null, $ccType = null, $ccExpireDate = null) {

        $this->_tokenCards = new Varien_Object;

        if (!$this->_tokenCards->getSize()) {

            $_id = Mage::getModel('sagepaysuite/api_payment')->getCustomerQuoteId();

            if(is_numeric($_id)) {
                if($_id === 0) {
                    return $this->_tokenCards;
                }
            }
            $this->_tokenCards = Mage::getModel('sagepaysuite2/sagepaysuite_tokencard')->getCollection()
                    ->setOrder('id', 'DESC')
                    ->addCustomerFilter($_id);

            if(!is_null($methodCode)) {
                $method = new Varien_Object;

                switch ($methodCode) {
                    case 'sagepayserver':
                        $method = Mage::getModel('sagepaysuite/sagePayServer');
                        break;
                    case 'sagepayserver_moto':
                        $method = Mage::getModel('sagepaysuite/sagePayServerMoto');
                        break;
                    case 'sagepaydirectpro':
                        $method = Mage::getModel('sagepaysuite/sagePayDirectPro');
                        break;
                    case 'sagepaydirectpro_moto':
                        $method = Mage::getModel('sagepaysuite/sagePayDirectProMoto');
                        break;
                    default:
                        break;
                }

                $this->_tokenCards->addVendorFilter($method->getConfigData('vendor'));
            }

            if($ccLastFour){
                $this->_tokenCards->addFieldToFilter('last_four', $ccLastFour);
            }

            if($ccType){
                $this->_tokenCards->addFieldToFilter('card_type', $ccType);
            }

            if($ccExpireDate){
                $this->_tokenCards->addFieldToFilter('expiry_date', $ccExpireDate);
            }

            $this->_tokenCards->load();
        }

        return $this->_tokenCards;
    }

    public function getSessionTokens() {
        $vdata = Mage::getSingleton('core/session')->getVisitorData();

        $sessionCards = Mage::getModel('sagepaysuite2/sagepaysuite_tokencard')
                ->getCollection()
                ->addFieldToFilter('visitor_session_id', (string) $vdata['session_id']);

        return $sessionCards;
    }

}