<?php
/**
 *
 * Sagepay Payment Action Dropdown source
 *
 */
class Ebizmarts_SagePaySuite_Model_Sagepaysuite_Source_TokenIframePosition
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'iniframe',
                'label' => Mage::helper('sagepaysuite')->__('Below the "Add new" button')
            ),
            array(
                'value' => 'full_redirect',
                'label' => Mage::helper('sagepaysuite')->__('Redirect to Sage Pay')
            ),
        );
    }
}