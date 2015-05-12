<?php

class Ebizmarts_SagePaySuite_Block_Adminhtml_Sales_Order_Creditmemo_Invoices extends Mage_Core_Block_Template
{

	protected $_pays = null;

	public function getCreditMemo()
	{
		return Mage::registry('current_creditmemo');
	}

	public function getPayments()
	{
		if(is_null($this->_pays)){

			$payments = Mage::getResourceModel('sagepaysuite2/sagepaysuite_action_collection');
	    	$payments->setOrderFilter($this->getCreditMemo()->getOrder()->getId())
	                 ->setPaymentsFilter()
	    			 ->addOrder('action_date')
	    			 ->load();
			$this->_pays = $payments;

		}

		return $this->_pays;
	}

    /**
     * Render block HTML
     *
     * @return string
     */
    protected function _toHtml()
    {
        if ($this->getPayments()->getSize() <= 1) {
            return '';
        }

        return parent::_toHtml();
    }

}