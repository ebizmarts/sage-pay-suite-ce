<?php

class Ebizmarts_SagePayReporting_Block_Adminhtml_Sagepayreporting_Avscvstatus extends Mage_Adminhtml_Block_Template {

    public function __construct() {
        parent::__construct();
        $this->setTemplate('sagepayreporting/avscvstatus.phtml');
    }

    protected function _prepareLayout() {
        $this->setChild('reporting.switcher', $this->getLayout()->createBlock('sagepayreporting/adminhtml_sagepayreporting_switcher', 'reporting.switcher'));
        
        return parent::_prepareLayout();
    }    
    
    public function getAccessModel() {
        return Mage::getModel('sagepayreporting/sagepayreporting');
    }

    public function getAvsCv2Status() {
        try {
            $paramStore = $this->getRequest()->getParam('store');
            if (!is_null($paramStore)) {
                Mage::register('reporting_store_id', $paramStore);
            }
            return $this->getAccessModel()->getAVSCV2Status();
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function getAvsCv2Rules() {
        try {
            return $this->getAccessModel()->getAvsCv2Rules();
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

}