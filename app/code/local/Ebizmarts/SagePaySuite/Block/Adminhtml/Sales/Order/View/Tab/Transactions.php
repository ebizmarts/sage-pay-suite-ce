<?php

class Ebizmarts_SagePaysuite_Block_Adminhtml_Sales_Order_View_Tab_Transactions
    extends Mage_Adminhtml_Block_Widget_Grid
    implements Mage_Adminhtml_Block_Widget_Tab_Interface
{

    public function __construct()
    {
        parent::__construct();
        $this->setId('sagepay_transactions_grid');
        $this->setUseAjax(true);
        $this->setDefaultSort('action_date');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
    }

	protected function _getActionCollection()
	{
		$collection = Mage::getModel('sagepaysuite2/sagepaysuite_action')->getCollection();

		$order = Mage::registry('current_order');
		$orderParam = $this->getRequest()->getParam('order_id');

        if ($order) {
            $collection->addFieldToFilter('parent_id', $order->getId());
        }else if($orderParam){
        	$collection->addFieldToFilter('parent_id', $orderParam);
        }

        return $collection;
	}

    /**
     * Prepare related orders collection
     *
     * @return Mage_Adminhtml_Block_Widget_Grid
     */
    protected function _prepareCollection()
    {
        $this->setCollection($this->_getActionCollection());
        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {
		$this->addColumn('action_code', array(
            'header'=> Mage::helper('sagepaysuite')->__('Type'),
            'index' => 'action_code',
			'type'  => 'options',
			'filter' => false,
			'sortable' => false,
            'options' => Mage::getModel('sagepaysuite/sagepaysuite_source_operation')->toOption(),
        ));
		$this->addColumn('vps_tx_id', array(
            'header'=> Mage::helper('sagepaysuite')->__('VPS Tx Id'),
            'type'  => 'text',
            'filter' => false,
            'sortable' => false,
            'index' => 'vps_tx_id',
        ));
		$this->addColumn('status', array(
            'header'=> Mage::helper('sagepaysuite')->__('Status'),
            'type'  => 'text',
            'filter' => false,
            'sortable' => false,
            'index' => 'status',
        ));
		$this->addColumn('status_detail', array(
            'header'=> Mage::helper('sagepaysuite')->__('Status Detail'),
            'type'  => 'text',
            'filter' => false,
            'sortable' => false,
            'index' => 'status_detail',
        ));
		$this->addColumn('amount', array(
            'header'=> Mage::helper('sagepaysuite')->__('Amount'),
            'type'  => 'text',
            'filter' => false,
            'sortable' => false,
            'index' => 'amount',
        ));
		$this->addColumn('action_date', array(
            'header'=> Mage::helper('sagepaysuite')->__('Date'),
            'type'  => 'datetime',
            'filter' => false,
            'sortable' => false,
            'index' => 'action_date',
        ));
        return parent::_prepareColumns();
    }

    /**
     * Return Tab label
     *
     * @return string
     */
    public function getTabLabel()
    {
        return $this->__('Sage Pay Transactions');
    }

    /**
     * Return Tab title
     *
     * @return string
     */
    public function getTabTitle()
    {
        return $this->__('Sage Pay Transactions');
    }

    /**
     * Return row url for js event handlers
     *
     * @return string
     */
    public function getRowUrl($item)
    {
        return '#';
    }

    public function getGridUrl($params = array())
    {
        return $this->getAbsoluteGridUrl($params);
    }

    public function getAbsoluteGridUrl($params = array())
    {
        return null;
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
    	try{
			$regOrder = Mage::registry('current_order');
	    	if($regOrder && false === $this->helper('sagepaysuite')->isSagePayMethod($regOrder->getPayment()->getMethod())
	    		|| ($this->_getActionCollection()->getSize() == 0)){
				return true;
	    	}
    	}catch(Exception $ee){
    		return false;
    	}
        return false;
    }

}