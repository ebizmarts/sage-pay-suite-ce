<?php

/**
 *
 * Sagepay Payment Action Dropdown source
 *
 */
class Ebizmarts_SagePaySuite_Model_Sagepaysuite_Source_IframePosition
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'modal',
                'label' => Mage::helper('sagepaysuite')->__('Modal, as a "Lightbox"')
            ),
            array(
                'value' => 'incheckout',
                'label' => Mage::helper('sagepaysuite')->__('Below the "Place Order" button')
            ),
            array(
                'value' => 'full_redirect',
                'label' => Mage::helper('sagepaysuite')->__('Redirect to Sage Pay')
            ),            
        );
    }
}