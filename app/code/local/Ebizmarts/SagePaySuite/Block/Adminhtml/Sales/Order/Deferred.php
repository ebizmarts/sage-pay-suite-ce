<?php

class Ebizmarts_SagePaySuite_Block_Adminhtml_Sales_Order_Deferred extends Mage_Adminhtml_Block_Widget_Grid_Container
{

    public function __construct()
    {
        $this->_blockGroup = 'sagepaysuite';
        $this->_controller = 'adminhtml_sales_order_deferred';
        $this->_headerText = Mage::helper('sagepaysuite')->__('Sage Pay Deferred Orders');

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