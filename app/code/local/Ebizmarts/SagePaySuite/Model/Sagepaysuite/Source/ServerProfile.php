<?php

class Ebizmarts_SagePaySuite_Model_Sagepaysuite_Source_ServerProfile
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'LOW',
                'label' => Mage::helper('sagepaysuite')->__('Low (recommended)')
            ),
            array(
                'value' => 'NORMAL',
                'label' => Mage::helper('sagepaysuite')->__('Normal')
            )
        );
    }
}