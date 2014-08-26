<?php

class Ebizmarts_SagePayReporting_Block_Adminhtml_SagePayReporting_Transaction_Detail extends Mage_Adminhtml_Block_Widget {

    protected function _prepareLayout() {
        $this->setChild('reporting.switcher', $this->getLayout()->createBlock('sagepayreporting/adminhtml_sagepayreporting_switcher', 'reporting.switcher'));

        return parent::_prepareLayout();
    }

    public function __construct() {
        parent::__construct();
        $this->setTemplate('sagepayreporting/transaction/detail.phtml');
    }

    public function getHeaderText() {
        return Mage::helper('sagepayreporting')->__('Get Transaction Details');
    }

    public function getSaveUrl() {
        return $this->getUrl('*/*/*', array('_current'=>true));
    }

    public function getTransactionDetail() {
        return Mage::registry('sagepay_detail');
    }

}