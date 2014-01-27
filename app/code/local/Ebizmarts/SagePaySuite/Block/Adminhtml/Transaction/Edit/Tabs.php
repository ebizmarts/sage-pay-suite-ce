<?php

class Ebizmarts_SagePaySuite_Block_Adminhtml_Transaction_Edit_Tabs extends Mage_Adminhtml_Block_Widget_Tabs {

    public function __construct() {
        parent::__construct();
        $this->setId('transaction_tabs');
        $this->setDestElementId('edit_form');
        $this->setTitle(Mage::helper('sagepaysuite')->__('Transaction Information'));
    }

    protected function _beforeToHtml() {
        $this->addTab('main_section', array(
            'label'     => Mage::helper('sagepaysuite')->__('Transaction data'),
            'title'     => Mage::helper('sagepaysuite')->__('Transaction information'),
            'content'   => $this->getLayout()->createBlock('sagepaysuite/adminhtml_transaction_edit_tab_main')->toHtml(),
            'active'    => true
        ));
        return parent::_beforeToHtml();
    }

}