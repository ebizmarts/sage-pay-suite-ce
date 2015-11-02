<?php

class Ebizmarts_SagePaySuite_Adminhtml_SpsDashboardController extends Mage_Adminhtml_Controller_Action {

    public function indexAction() {
        $this->_title($this->__('Sales'))->_title($this->__('Sage Pay'))->_title($this->__('Dashboard'));
        $this->loadLayout()->renderLayout();
    }

    protected function _isAllowed() {
            $acl = 'sales/sagepay/dashboard';
            return Mage::getSingleton('admin/session')->isAllowed($acl);
    }

}