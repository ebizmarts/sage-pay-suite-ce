<?php

class Ebizmarts_SagePayReporting_Block_Adminhtml_SagePayReporting_Transaction_Tab_Detailmodal extends Mage_Adminhtml_Block_Widget implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
	public function __construct()
	{
		parent::__construct();
		$this->setTemplate('sagepayreporting/transaction/detail_modal.phtml');
	}

	public function getHeaderText()
	{
		return $this->__('Get Transaction Details');
	}

	public function getSaveUrl()
	{
		return $this->getUrl('*/*/*');
	}

	public function getTransactionDetail()
	{
		return Mage::registry('sagepay_detail');
	}
	/**
	 * Return Tab label
	 *
	 * @return string
	 */
	public function getTabLabel()
	{
		return $this->__('Transaction Details');
	}

	/**
	 * Return Tab title
	 *
	 * @return string
	 */
	public function getTabTitle()
	{
		return $this->__('Transaction Details');
	}

	/**
	 * Can show tab in tabs
	 *
	 * @return boolean
	 */
	public function canShowTab()
	{
		return true;
	}

	/**
	 * Tab is hidden
	 *
	 * @return boolean
	 */
	public function isHidden()
	{
		return false;
	}

}