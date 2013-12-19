<?php


class Ebizmarts_SagePayReporting_Block_Adminhtml_Sagepayreporting_Transaction_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{

	protected function _beforeToHtml()
	{
		$this->addTab('transaction_detail', array(
            'label'     => $this->__('Transaction Details'),
            'content'   => $this->getLayout()->createBlock('sagepayreporting/adminhtml_sagepayreporting_transaction_tab_detailmodal')->toHtml(),
            'active'    => true
		));
		$this->addTab('transaction_related', array(
            'label'     => $this->__('Related Transactions'),
            'content'   => $this->getLayout()->createBlock('sagepayreporting/adminhtml_sagepayreporting_transaction_tab_relatedtransactions')->toHtml(),
            'active'    => false
		));

		return parent::_beforeToHtml();
	}

}