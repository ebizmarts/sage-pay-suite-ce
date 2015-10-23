<?php

class Ebizmarts_SagePaySuite_Block_Adminhtml_Paymentransaction_Grid extends Mage_Adminhtml_Block_Widget_Grid {

    public function __construct() {
        parent::__construct();
        $this->setId('payment_transactions_grid');
        $this->setUseAjax(true);
        $this->setDefaultSort('id');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(false);
    }

    protected function _prepareCollection() {
        $collection = Mage::getModel('sagepaysuite2/sagepaysuite_transaction')->getCollection()->getApproved();
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    protected function _prepareColumns() {
        if (FALSE === $this->_shortgrid()) {
            $this->_fullGridColumns();
        } else {
            $this->_smallGridColumns();
        }

        return parent::_prepareColumns();
    }

    protected function _prepareMassaction() {
        if (TRUE === $this->_shortgrid()) {
            return $this;
        }

        $this->setMassactionIdField('id');
        $this->getMassactionBlock()->setFormFieldName('ids');
        $this->getMassactionBlock()->setUseSelectAll(false);

        $this->getMassactionBlock()->addItem('dlete', array(
            'label' => Mage::helper('sagepaysuite')->__('Delete'),
            'url' => $this->getUrl('adminhtml/spsTransaction/removetrn'),
            'confirm' => Mage::helper('sagepaysuite')->__('Are you sure?')
        ));
        return $this;
    }

    public function getRowUrl($row) {

        if(FALSE === $this->_shortgrid() && Mage::getSingleton('admin/session')->isAllowed('sales/sagepay/payments/edit_transaction')) {
            return $this->getUrl('*/*/edit', array('id' => $row->getVendorTxCode()));
        }

        return false;
    }

    public function getGridUrl() {
        if (FALSE === $this->_shortgrid()) {
            return $this->getUrl('adminhtml/spsTransaction/paymentsGrid', array('_current' => true));
        } else {
            return $this->getUrl('adminhtml/spsRepeatpayment/grid', array('_current' => true));
        }
    }

    protected function _smallGridColumns() {
        $this->setDefaultSort('order_id');

        $this->addColumn('order_id', array(
            'header' => Mage::helper('sagepaysuite')->__('Order ID'),
            'width' => '80px',
            'index' => 'order_id',
            'type' => 'text',
            'renderer' => 'sagepaysuite/adminhtml_sales_order_grid_renderer_orderId'
        ));

        $this->addColumn('customer_cc_holder_name', array(
            'header' => Mage::helper('sagepaysuite')->__('Card Holder Name'),
            'width' => '80px',
            'index' => 'customer_cc_holder_name',
        ));

        $ccCards = Mage::getModel('sagepaysuite/sagepaysuite_source_creditCards')->toOption();
        $this->addColumn('card_type', array(
            'header' => Mage::helper('sagepaysuite')->__('Card Type'),
            'width' => '80px',
            'type' => 'options',
            'index' => 'card_type',
            'options' => $ccCards
        ));

        $this->addColumn('last_four_digits', array(
            'header' => Mage::helper('sagepaysuite')->__('Last 4 Digits'),
            'width' => '80px',
            'index' => 'last_four_digits'
        ));

        $this->addColumn('vps_tx_id', array(
            'header' => Mage::helper('sagepaysuite')->__('Vps Tx Id'),
            'width' => '140px',
            'index' => 'vps_tx_id'
        ));

        $this->addColumn('status', array(
            'header' => Mage::helper('sagepaysuite')->__('Transaction Status'),
            'width' => '80px',
            'index' => 'status'
        ));
    }

    protected function _fullGridColumns() {
        $this->addColumn('id', array(
            'header' => Mage::helper('sagepaysuite')->__('ID'),
            'width' => '80px',
            'index' => 'id',
            'type' => 'number',
        ));

        $this->addColumn('order_id', array(
            'header' => Mage::helper('sagepaysuite')->__('Order ID'),
            'width' => '80px',
            'index' => 'order_id',
            'type' => 'text',
            'renderer' => 'sagepaysuite/adminhtml_sales_order_grid_renderer_orderId'
        ));

        $this->addColumn('vendor_tx_code', array(
            'header' => Mage::helper('sagepaysuite')->__('Vendor Tx Code'),
            'width' => '80px',
            'index' => 'vendor_tx_code'
        ));

        $this->addColumn('vendorname', array(
            'header' => Mage::helper('sagepaysuite')->__('Vendor Name'),
            'width' => '80px',
            'index' => 'vendorname'
        ));

        $this->addColumn('mode', array(
            'header' => Mage::helper('sagepaysuite')->__('Mode'),
            'width' => '80px',
            'index' => 'mode'
        ));

        $this->addColumn('vps_tx_id', array(
            'header' => Mage::helper('sagepaysuite')->__('Vps Tx Id'),
            'width' => '140px',
            'index' => 'vps_tx_id'
        ));

        $this->addColumn('status', array(
            'header' => Mage::helper('sagepaysuite')->__('Transaction Status'),
            'width' => '80px',
            'index' => 'status'
        ));

        $this->addColumn('customer_cc_holder_name', array(
            'header' => Mage::helper('sagepaysuite')->__('Card Holder Name'),
            'width' => '80px',
            'index' => 'customer_cc_holder_name',
        ));

        $this->addColumn('customer_contact_info', array(
            'header' => Mage::helper('sagepaysuite')->__('Customer Contact Info'),
            'width' => '80px',
            'index' => 'customer_contact_info',
        ));

        $ccCards = Mage::getModel('sagepaysuite/sagepaysuite_source_creditCards')->toOption();
        $this->addColumn('card_type', array(
            'header' => Mage::helper('sagepaysuite')->__('Card Type'),
            'width' => '80px',
            'type' => 'options',
            'index' => 'card_type',
            'options' => $ccCards
        ));

        $this->addColumn('last_four_digits', array(
            'header' => Mage::helper('sagepaysuite')->__('Last 4 Digits'),
            'width' => '80px',
            'index' => 'last_four_digits'
        ));

        $this->addColumn('action', array(
            'header' => Mage::helper('sagepaysuite')->__('Action'),
            'width' => '80px',
            'type' => 'action',
            'getter' => 'getOrderId',
            'actions' => array(
                array(
                    'caption' => Mage::helper('sagepaysuite')->__('View Order'),
                    'url' => array('base' => 'adminhtml/sales_order/view'),
                    'field' => 'order_id',
                    'popup' => '1',
                ),
                array(
                    'caption' => Mage::helper('sagepaysuite')->__('Add API data'),
                    'url' => array('base' => 'adminhtml/spsTransaction/addApiData'),
                    'field' => 'order_id',
                )
            ),
            'filter' => false,
            'sortable' => false,
            'is_system' => true,
        ));
    }

    protected function _shortgrid() {
        return (bool) ($this->getRequest()->getControllerName() == 'spsRepeatpayment');
    }

}
