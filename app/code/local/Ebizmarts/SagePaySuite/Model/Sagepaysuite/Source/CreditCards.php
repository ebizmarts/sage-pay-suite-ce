<?php

class Ebizmarts_SagePaySuite_Model_Sagepaysuite_Source_CreditCards extends Varien_Object
{
    public function toOptionArray()
    {
        $options =  array();

		if($this->getPath() == 'payment/sagepaydirectpro/force_threed_cards'){
			$options[] = array(
        	   'value' => '',
        	   'label' => '',
        	);
		}

        foreach (Mage::getSingleton('sagepaysuite/config')->getCcTypesSagePayDirect() as $code => $name) {
        	$options[] = array(
        	   'value' => $code,
        	   'label' => $name
        	);
        }

        return $options;
    }

    public function toOption()
    {
        $options =  array();

        foreach (Mage::getSingleton('sagepaysuite/config')->getCcTypesSagePayDirect() as $code => $name) {
        	$options[$code] = $name;
        }

        return $options;
    }
}