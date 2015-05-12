<?php

class Ebizmarts_SagePayReporting_Block_Adminhtml_Sagepayreporting_Switcher extends Mage_Adminhtml_Block_System_Config_Switcher {
    
    protected function _prepareLayout() {
        parent::_prepareLayout();
        
        $this->setTemplate('sagepayreporting/switcher.phtml');
        
        return Mage_Adminhtml_Block_Template::_prepareLayout();
    }
    
    public function getSelectOptions() {
        $options = $this->getStoreSelectOptions();
        
        return $options;
    }
}