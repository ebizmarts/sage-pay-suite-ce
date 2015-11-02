<?php

class Ebizmarts_SagePayReporting_Block_Adminhtml_Sagepayreporting_Whitelistip_Edit extends Mage_Adminhtml_Block_Widget_Form_Container {
        
    public function __construct() {
        $this->_blockGroup = 'sagepayreporting';
        $this->_controller = 'adminhtml_sagepayreporting_whitelistip';

        parent::__construct();

        $this->_updateButton('save', 'label', Mage::helper('sagepayreporting')->__('Add'));
    }

    public function getHeaderText() {
        return Mage::helper('sagepayreporting')->__('White List IP Address');
    }

    /**
     * Get form action URL
     *
     * @return string
     */
    public function getFormActionUrl() {
        return $this->getUrl('*/sagepayreporting_whitelistip/save', array('_current' => true));
    }    
    
}