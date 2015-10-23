<?php

class Ebizmarts_SagePaySuite_Block_Adminhtml_Tokencard_Grid extends Mage_Adminhtml_Block_Widget_Grid {

    public function __construct() {
        parent::__construct();
        $this->setId('token_grid');
        $this->setUseAjax(true);
        $this->setDefaultSort('id');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
    }

    protected function _prepareCollection() {
        $collection = Mage::getModel('sagepaysuite2/sagepaysuite_tokencard')->getCollection();
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    protected function _prepareColumns() {

        $this->addColumn('id', array(
            'header' => Mage::helper('sagepaysuite')->__('Card #'),
            'width' => '80px',
            'type' => 'number',
            'index' => 'id'
        ));

        $this->addColumn('customer_id', array(
            'header' => Mage::helper('sagepaysuite')->__('Customer #'),
            'index' => 'customer_id',
            'type' => 'text',
            'width' => '100px',
            'renderer' => 'sagepaysuite/adminhtml_sales_order_grid_renderer_customerId'
        ));

        $this->addColumn('token', array(
            'header' => Mage::helper('sagepaysuite')->__('Token'),
            'index' => 'token',
            'type' => 'text',
            'width' => '100px'
        ));

        $ccCards = Mage::getModel('sagepaysuite/sagepaysuite_source_creditCards')->toOption();
        $this->addColumn('card_type', array(
            'header' => Mage::helper('sagepaysuite')->__('Card Type'),
            'index' => 'card_type',
            'type' => 'options',
            'width' => '100px',
            'options' => $ccCards
        ));

        $this->addColumn('last_four', array(
            'header' => Mage::helper('sagepaysuite')->__('Last 4 Digits'),
            'index' => 'last_four',
            'type' => 'text',
            'width' => '100px'
        ));

        $this->addColumn('expiry_date', array(
            'header' => Mage::helper('sagepaysuite')->__('Expiry Date'),
            'index' => 'expiry_date',
            'type' => 'text',
            'width' => '100px',
            'renderer' => 'sagepaysuite/adminhtml_widget_grid_column_renderer_expiry'
        ));

        $ccCards = Mage::getModel('sagepaysuite/sagepaysuite_source_protocol')->toOption();
        $this->addColumn('protocol', array(
            'header' => Mage::helper('sagepaysuite')->__('Integration'),
            'index' => 'protocol',
            'type' => 'options',
            'width' => '100px',
            'options' => $ccCards
        ));

        $this->addColumn('visitor_session_id', array(
            'header' => Mage::helper('sagepaysuite')->__('Visitor Session ID'),
            'index' => 'visitor_session_id',
            'width' => '60px'
        ));

        $this->addColumn('action', array(
            'header' => Mage::helper('sagepaysuite')->__('Action'),
            'width' => '50px',
            'type' => 'action',
            'align' => 'center',
            'getter' => 'getId',
            'actions' => array(
                array(
                    'caption' => Mage::helper('sagepaysuite')->__('Delete'),
                    'url' => array('base' => 'adminhtml/spsToken/deleteCard'),
                    'field' => 'id',
                    'confirm' => Mage::helper('sagepaysuite')->__('Are you sure?')
                )
            ),
            'filter' => false,
            'sortable' => false,
            'is_system' => true,
        ));

        return parent::_prepareColumns();
    }

    public function getRowUrl($row) {
        return false;
    }

    public function getGridUrl() {
        return $this->getUrl('adminhtml/spsToken/grid', array('_current' => true));
    }

    protected function _prepareMassaction() {
        $this->setMassactionIdField('id');
        $this->getMassactionBlock()->setFormFieldName('cards');
        $this->getMassactionBlock()->setUseSelectAll(false);

        $this->getMassactionBlock()->addItem('delete_tokens', array(
            'label' => Mage::helper('sagepaysuite')->__('Delete'),
            'url' => $this->getUrl('adminhtml/spsToken/massDelete'),
            'confirm' => Mage::helper('sagepaysuite')->__('Are you sure?')
        ));

        return $this;
    }

}