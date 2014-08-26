<?php

/**
 *
 * Sagepay Payment Action Dropdown source
 *
 */
class Ebizmarts_SagePayDirectPro_Model_Sagepaydirectpro_Source_RefundOptions
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'mn',
                'label' => Mage::helper('sagepaydirectpro')->__('Manual')
            ),
            array(
                'value' => 'ot',
                'label' => Mage::helper('sagepaydirectpro')->__('Order Total')
            ),
            array(
                'value' => 'ot-sh',
                'label' => Mage::helper('sagepaydirectpro')->__('Order Total - Shipping')
            )
        );
    }
}
