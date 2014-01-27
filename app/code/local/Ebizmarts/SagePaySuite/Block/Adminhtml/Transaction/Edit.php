<?php

class Ebizmarts_SagePaySuite_Block_Adminhtml_Transaction_Edit extends Mage_Adminhtml_Block_Widget_Form_Container {

    public function __construct() {
        $this->_objectId   = 'id';
        $this->_controller = 'adminhtml_transaction';
        $this->_blockGroup = 'sagepaysuite';

        parent::__construct();

        $this->_removeButton('delete');
    }

    public function getHeaderText() {
        return Mage::helper('sagepaysuite')->__('Edit Transaction');
    }

}