<?php

class Ebizmarts_SagePayReporting_Block_Adminhtml_Sagepayreporting_Fraud extends Mage_Adminhtml_Block_Widget_Grid_Container
{

	public function __construct()
	{
		$this->_blockGroup = 'sagepayreporting';
		$this->_controller = 'adminhtml_sagepayreporting_fraud';
		$this->_headerText = Mage::helper('sagepayreporting')->__('Sage Pay Fraud Information');

		parent::__construct();

		$this->_removeButton('add');
	}

	protected function _prepareLayout()
	{
		if(!$this->getRequest()->isXmlHttpRequest()){
			$this->getLayout()->getBlock('head')
			->addItem('skin_css', 'sagepaysuite/css/sagePaySuite.css');
		}
		return parent::_prepareLayout();
	}

}