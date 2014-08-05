<?php

class Ebizmarts_SagePayReporting_Block_Adminhtml_SagePayReporting extends Mage_Adminhtml_Block_Widget_Grid_Container
{
	public function __construct()
	{
		$this->_controller = 'adminhtml_sagepayreporting';
		$this->_blockGroup = 'sagepayreporting';
		$this->_headerText = Mage::helper('sagepayreporting')->__('Item Manager');
		$this->_addButtonLabel = Mage::helper('sagepayreporting')->__('Add Item');
		parent::__construct();
	}
}