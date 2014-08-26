<?php

class Ebizmarts_SagePaySuite_Model_Sagepaysuite_Source_ThreedLayout
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'redirect',
                'label' => Mage::helper('sagepaysuite')->__('New page')
            ),array(
                'value' => 'modal',
                'label' => Mage::helper('sagepaysuite')->__('Modal')
            ),
        );
    }
}