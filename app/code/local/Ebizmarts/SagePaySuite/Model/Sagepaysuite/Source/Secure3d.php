<?php

/**
 *
 * Sagepay Payment Action Dropdown source
 *
 */
class Ebizmarts_SagePaySuite_Model_Sagepaysuite_Source_Secure3d
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 0,
                'label' => Mage::helper('sagepaysuite')->__('Default: If 3D Secure checks are possible and rules allow, perform the checks and apply the authorisation rules.')
            ),
            array(
                'value' => 1,
                'label' => Mage::helper('sagepaysuite')->__('Force 3D Secure: Force 3D Secure checks for this transaction only (if your account is 3D-enabled) and apply rules for authorisation.')
            ),
            array(
                'value' => 2,
                'label' => Mage::helper('sagepaysuite')->__('Skip 3D Secure: Do not perform 3D-Secure checks for this transaction only and always authorise.')
            ),
            array(
                'value' => 3,
                'label' => Mage::helper('sagepaysuite')->__('Always auth code: Force 3D-Secure checks for this transaction (if your account is 3D-enabled) but ALWAYS obtain an auth code, irrespective of rule base.')
            ),
        );
    }
}
