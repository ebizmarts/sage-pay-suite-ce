<?php

/**
 *
 * Sagepay Payment Action Dropdown source
 *
 */
class Ebizmarts_SagePaySuite_Model_Sagepaysuite_Source_PaymentAction
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => Ebizmarts_SagePaySuite_Model_Api_Payment::ACTION_AUTHORIZE_CAPTURE,
                'label' => Mage::helper('sagepaysuite')->__('Authorise and Capture')
            )
        );
    }
}
