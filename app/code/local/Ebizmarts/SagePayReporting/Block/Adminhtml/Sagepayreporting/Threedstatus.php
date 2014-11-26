<?php

class Ebizmarts_SagePayReporting_Block_Adminhtml_Sagepayreporting_Threedstatus extends Mage_Adminhtml_Block_Template {

    protected function _prepareLayout() {
        $this->setChild('reporting.switcher', $this->getLayout()->createBlock('sagepayreporting/adminhtml_sagepayreporting_switcher', 'reporting.switcher'));
        
        return parent::_prepareLayout();
    }    
    
    public function __construct() {
        parent::__construct();
        $this->setTemplate('sagepayreporting/threedstatus.phtml');
    }

    public function getAccessModel() {
        return Mage::getModel('sagepayreporting/sagepayreporting');
    }

    public function get3dStatus() {
        try {
            
            $paramStore = $this->getRequest()->getParam('store');
            if( !is_null($paramStore) ) {
                Mage::register('reporting_store_id', $paramStore);
            }
            
            $api = $this->getAccessModel();
            
            return $api->get3dSecureStatus();
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function get3dRules() {
        
        try {
            return $this->getAccessModel()->get3dSecureRules();
        } catch (Exception $e) {
            return $e->getMessage();
        }
        
    }

}