<?php


/**
 *
 * Sagepay Payment Mode Dropdown source
 *
 */
class Ebizmarts_SagePaySuite_Model_Sagepaysuite_Source_PaymentModePaypal
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'test',
                'label' => Mage::helper('sagepaysuite')->__('Test')
            ),
            array(
                'value' => 'live',
                'label' => Mage::helper('sagepaysuite')->__('Live')
            )
        );
    }
}