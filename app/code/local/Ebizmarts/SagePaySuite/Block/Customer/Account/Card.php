<?php
/**
 * Card block
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */
class Ebizmarts_SagePaySuite_Block_Customer_Account_Card extends Mage_Core_Block_Template
{
	protected $_cards = null;

	public function getCustomerCards()
	{
        if (is_null($this->_cards)) {

            $this->_cards = $this->helper('sagepaysuite/token')->loadCustomerCards();

        }

        return $this->_cards;
	}
}