<?php

/**
 * Direct token card Form
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */
class Ebizmarts_SagePaySuite_Block_Customer_Account_Card_Direct_Form extends Mage_Core_Block_Template {

    public function _construct() {
        parent::_construct();
        $this->setTemplate('sagepaysuite/customer/card/direct/form.phtml');
    }

    public function getCcAvailableTypes() {
        $types = Mage::getModel('sagepaysuite/config')->getCcTypesSagePayDirect();

        $availableTypes = Mage::getStoreConfig('payment/sagepaydirectpro/cctypesSagePayDirectPro');
        if ($availableTypes) {
            $availableTypes = explode(',', $availableTypes);

            foreach ($types as $code => $name) {
                if (!in_array($code, $availableTypes)) {
                    unset($types[$code]);
                }
            }
        }
        return $types;
    }

    /**
     * Retrieve credit card expire months
     *
     * @return array
     */
    public function getCcMonths() {
        $months = array();
        $months[0] = $this->__('Month');
        $months = array_merge($months, Mage::getModel('payment/config')->getMonths());
        return $months;
    }

    public function getStartCcYears() {
        return Mage::getBlockSingleton('payment/form_cc')->getSsStartYears();
    }

    public function getCcYears() {
        $years = Mage::getModel('sagepaysuite/config')->getYears();
        $years = array(0 => $this->helper('sagepaysuite')->__('Year')) + $years;

        return $years;
    }

}