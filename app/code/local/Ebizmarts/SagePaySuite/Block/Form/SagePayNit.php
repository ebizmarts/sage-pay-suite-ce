<?php

class Ebizmarts_SagePaySuite_Block_Form_SagePayNit extends Ebizmarts_SagePaySuite_Block_Form_SagePayToken
{

    protected function _prepareLayout()
    {
        return parent::_prepareLayout();
    }

    protected function _construct()
    {
        parent::_construct();

        $this->setTemplate('sagepaysuite/payment/form/sagePayNit.phtml');
    }

    public function getCcAvailableTypes()
    {
        $types = Mage::getModel('sagepaysuite/config')->getCcTypesSagePayNit();

        $availableTypes = Mage::getStoreConfig('payment/' . $this->getMethodCode() . '/cctypesSagePayNit');

        if ($availableTypes) {
            $availableTypes = explode(',', $availableTypes);

            foreach ($types as $code=>$name) {
                if (!in_array($code, $availableTypes)) {
                    unset($types[$code]);
                }
            }
        }
        return $types;
    }

    /*
    * Whether switch/solo card type available
    */
    public function hasSsCardType()
    {
        $availableTypes = explode(',', $this->getMethod()->getConfigData('cctypes'));
        $ssPresenations = array_intersect(array('SOLO', 'SWITCH', 'MAESTRO'), $availableTypes);
        if ($availableTypes && count($ssPresenations) > 0) {
            return true;
        }
        return false;
    }

    /**
     * Retrieve credit card expire years
     *
     * @return array
     */
    public function getCcStartYears()
    {
        $years = $this->getData('cc_start_years');

        if(is_null($years))
        {
            $years = $this->_getConfig()->getYearsStart();
            $years = array(0 => $this->__('Year'))+$years;
            $this->setData('cc_start_years', $years);
        }

        return $years;
    }

    /**
     * Retrieve credit card expire months
     *
     * @return array
     */
    public function getCcStartMonths()
    {
        $months = $this->getData('cc_start_months');
        if (is_null($months)) {
            $months[0] =  $this->__('Month');
            $months = array_merge($months, $this->_getConfig()->getMonths());
            $this->setData('cc_start_months', $months);
        }
        return $months;
    }

}