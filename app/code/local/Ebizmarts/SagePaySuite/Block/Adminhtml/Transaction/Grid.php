<?php

class Ebizmarts_SagePaySuite_Block_Adminhtml_Transaction_Grid extends Mage_Adminhtml_Block_Widget_Grid
{

	protected $_massactionBlockName = 'sagepayreporting/adminhtml_widget_grid_massaction';

    public function __construct()
    {
        parent::__construct();
        $this->setId('orphan_transactions_grid');
        $this->setUseAjax(true);
        $this->setDefaultSort('id');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(false);
    }

    protected function _prepareCollection()
    {
        $collection = Mage::getModel('sagepaysuite2/sagepaysuite_transaction')->getCollection()->getOrphans();
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {

        $this->addColumn('id', array(
            'header'=> Mage::helper('sagepaysuite')->__('ID'),
            'width' => '80px',
            'index' => 'id',
            'type' => 'number'
        ));

        $this->addColumn('vendor_tx_code', array(
            'header'=> Mage::helper('sagepaysuite')->__('Vendor Tx Code'),
            'width' => '80px',
            'index' => 'vendor_tx_code'
        ));

        $this->addColumn('vendorname', array(
            'header'=> Mage::helper('sagepaysuite')->__('Vendor Name'),
            'width' => '80px',
            'index' => 'vendorname'
        ));

        $this->addColumn('mode', array(
            'header'=> Mage::helper('sagepaysuite')->__('Mode'),
            'width' => '80px',
            'index' => 'mode'
        ));

        $this->addColumn('vps_tx_id', array(
            'header'=> Mage::helper('sagepaysuite')->__('Vps Tx Id'),
            'width' => '140px',
            'index' => 'vps_tx_id'
        ));

        $this->addColumn('status', array(
            'header'=> Mage::helper('sagepaysuite')->__('Transaction Status'),
            'width' => '80px',
            'index' => 'status',
            'renderer'   => 'sagepaysuite/adminhtml_sales_order_grid_renderer_state',
        ));

        $this->addColumn('status_detail', array(
            'header'=> Mage::helper('sagepaysuite')->__('Transaction Status Detail'),
            'width' => '80px',
            'index' => 'status_detail'
        ));

        $this->addColumn('tx_state_id', array(
            'header'=> Mage::helper('sagepaysuite')->__('System Status'),
            'width' => '80px',
            'index' => 'tx_state_id',
            'renderer'   => 'sagepaysuite/adminhtml_widget_grid_column_renderer_txState',
        ));

        $this->addColumn('customer_cc_holder_name', array(
            'header'=> Mage::helper('sagepaysuite')->__('Card Holder Name'),
            'width' => '80px',
            'index' => 'customer_cc_holder_name',
        ));

        $this->addColumn('customer_contact_info', array(
            'header'=> Mage::helper('sagepaysuite')->__('Customer Contact Info'),
            'width' => '80px',
            'index' => 'customer_contact_info',
        ));

        $this->addColumn('card_type', array(
            'header'=> Mage::helper('sagepaysuite')->__('Card Type'),
            'width' => '80px',
            'type'  => 'options',
            'index' => 'card_type',
            'options' => Mage::getModel('sagepaysuite/sagepaysuite_source_creditCards')->toOption()
        ));

        $this->addColumn('last_four_digits', array(
            'header'=> Mage::helper('sagepaysuite')->__('Last 4 Digits'),
            'width' => '80px',
            'index' => 'last_four_digits'
        ));

        $this->addColumn('integration', array(
            'header'=> Mage::helper('sagepaysuite')->__('Integration'),
            'width' => '80px',
            'index' => 'integration'
        ));

        $this->addColumn('action',
            array(
                'header'    => Mage::helper('sagepaysuite')->__('Action'),
                'width'     => '80px',
                'type'      => 'action',
                'getter'     => 'getVendorTxCode',
                'actions'   => array(
                    /*array(
                        'caption' => Mage::helper('sagepaysuite')->__('View Detail'),
                        'url'     => array('base'=>'sagepayreporting/adminhtml_sagepayreporting/transactionDetailModal'),
                        'field'   => 'vendortxcode',
                        'popup'   => '1',
                        'modal'   => '1',
                    ),*/
                    array(
                        'caption' => Mage::helper('sagepaysuite')->__('Edit'),
                        'url'     => array('base'=>'sgpsSecure/adminhtml_transaction/edit'),
                        'field'   => 'id',
                    ),
                    array(
                        'caption' => Mage::helper('sagepaysuite')->__('Recover [beta]'),
                        'url'     => array('base'=>'sgpsSecure/adminhtml_transaction/recover'),
                        'field'   => 'vendortxcode',
                        'confirm' => Mage::helper('sagepaysuite')->__("Are you sure?\nA new order will be created and payment will be associated."),
                    ),
                    array(
                        'caption' => Mage::helper('sagepaysuite')->__('VOID'),
                        'url'     => array('base'=>'sgpsSecure/adminhtml_transaction/void'),
                        'field'   => 'vendortxcode',
                        'confirm' => Mage::helper('sagepaysuite')->__('Are you sure?')
                    ),
                    array(
                        'caption' => Mage::helper('sagepaysuite')->__('Delete'),
                        'url'     => array('base'=>'sgpsSecure/adminhtml_transaction/delete'),
                        'field'   => 'vendortxcode',
                        'confirm' => Mage::helper('sagepaysuite')->__("Are you sure?\nThis operation will just delete the record from your local database.")
                    ),
                    array(
                        'caption' => Mage::helper('sagepaysuite')->__('Sync from API'),
                        'url'     => array('base'=>'sgpsSecure/adminhtml_transaction/sync'),
                        'field'   => 'vendortxcode'
                    )
                ),
                'filter'    => false,
                'sortable'  => false,
                'is_system' => true,
        ));

        return parent::_prepareColumns();
    }

    protected function _prepareMassaction()
    {
        $this->setMassactionIdField('vendortxcode');
        $this->getMassactionBlock()->setFormFieldName('transaction_ids');
        $this->getMassactionBlock()->setUseSelectAll(false);

        $this->getMassactionBlock()->addItem('dlete', array(
             'label'=> Mage::helper('sagepaysuite')->__('Delete'),
             'url'  => $this->getUrl('sgpsSecure/adminhtml_transaction/delete'),
        ));
        return $this;
    }

    public function getRowUrl($row)
    {
        //return false;
        return $this->getUrl('sagepayreporting/adminhtml_sagepayreporting/transactionDetailModal', array('vendortxcode' => $row->getVendorTxCode()));
    }

    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', array('_current'=>true));
    }
}
