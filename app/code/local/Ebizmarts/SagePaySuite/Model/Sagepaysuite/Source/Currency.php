<?php

class Ebizmarts_SagePaySuite_Model_Sagepaysuite_Source_Currency
{
    protected $_options;

    public function toOptionArray($isMultiselect)
    {
        if (!$this->_options) {
        	$opts = Mage::app()->getLocale()->getOptionCurrencies();

        	array_unshift($opts, array('value'=>'', 'label'=>''));

            $this->_options = $opts;
        }
        $options = $this->_options;
        return $options;
    }
}