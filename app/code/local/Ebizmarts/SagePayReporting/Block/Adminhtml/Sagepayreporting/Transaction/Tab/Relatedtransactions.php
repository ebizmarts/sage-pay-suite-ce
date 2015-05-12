<?php

class Ebizmarts_SagePayReporting_Block_Adminhtml_Sagepayreporting_Transaction_Tab_Relatedtransactions extends Mage_Adminhtml_Block_Widget_Grid
implements Mage_Adminhtml_Block_Widget_Tab_Interface
{

	public function __construct()
	{
		parent::__construct();
		$this->setId('sagepay_related_transactions_grid');
		$this->setUseAjax(true);
		$this->setSaveParametersInSession(true);
		$this->setPagerVisibility(false);
		$this->setFilterVisibility(false);
	}

	/**
	 * Prepare related orders collection
	 *
	 * @return Mage_Adminhtml_Block_Widget_Grid
	 */
	protected function _prepareCollection()
	{
		$collection = Mage::getModel('sagepayreporting/sagepayreporting_collection', Mage::registry('sagepay_related_transactions'));
		$this->setCollection($collection);
		return Mage_Adminhtml_Block_Widget_Grid::_prepareCollection();
	}

	protected function _prepareColumns()
	{
		$_helper = Mage::helper('sales');

		foreach(Mage::helper('sagepayreporting')->getDetailTransactionColumns() as $_colIndex => $_colName){
			$this->addColumn($_colIndex, array(
	            'header'=> $_helper->__($_colName),
            	'type'  => 'text',
            	'index' => $_colIndex,
            	'filter' => false,
            	'sortable' => false,
			));
		}

		return parent::_prepareColumns();
	}

	/**
	 * Return Tab label
	 *
	 * @return string
	 */
	public function getTabLabel()
	{
		return $this->__('Related Transactions');
	}

	/**
	 * Return Tab title
	 *
	 * @return string
	 */
	public function getTabTitle()
	{
		return $this->__('Related Transactions');
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
		return true;
	}

}