<?php
/**
 * Card block
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */
class Ebizmarts_SagePaySuite_Block_Customer_Account_Card extends Mage_Core_Block_Template {

	protected $_cards = null;

	public function getCustomerCards() {
        if (is_null($this->_cards)) {

        	$config = (string) Mage::getStoreConfig('payment/sagepaysuite/token_integration', Mage::app()->getStore()->getId());

        	$method = null;

        	if($config == 'server') {
        		$method = 'sagepayserver';
        	}
        	else {
        		if($config == 'direct') {
					$method = 'sagepaydirectpro';
        		}
        	}

            $this->_cards = $this->helper('sagepaysuite/token')->loadCustomerCards($method);

        }

        return $this->_cards;
	}

}