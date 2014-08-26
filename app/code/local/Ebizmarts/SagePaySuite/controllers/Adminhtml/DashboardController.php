<?php

class Ebizmarts_SagePaySuite_Adminhtml_DashboardController extends Mage_Adminhtml_Controller_Action {

    public function indexAction() {
        $this->_title($this->__('Sales'))->_title($this->__('Sage Pay'))->_title($this->__('Dashboard'));
        $this->loadLayout()
                ->_setActiveMenu('sagepay_dashboard')
                ->renderLayout();
    }

}