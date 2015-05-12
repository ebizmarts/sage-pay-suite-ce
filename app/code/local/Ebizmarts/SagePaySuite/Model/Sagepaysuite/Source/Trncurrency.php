<?php

class Ebizmarts_SagePaySuite_Model_Sagepaysuite_Source_Trncurrency
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'base',
                'label' => Mage::helper('sagepaysuite')->__('Base')
            ),
            array(
                'value' => 'store',
                'label' => Mage::helper('sagepaysuite')->__('Store')
            ),
            array(
                'value' => 'switcher',
                'label' => Mage::helper('sagepaysuite')->__('Currency Switcher')
            )
        );
    }
}