<?php


/**
 *
 * Sagepay Payment Mode Dropdown source
 *
 */
class Ebizmarts_SagePaySuite_Model_Sagepaysuite_Source_AvsCv2
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 0,
                'label' => Mage::helper('sagepaysuite')->__('If AVS/CV2 enabled then check them. If rules apply, use rules.')
            ),
            array(
                'value' => 1,
                'label' => Mage::helper('sagepaysuite')->__('Force AVS/CV2 checks even if not enabled for the account. If rules apply, use rules.')
            ),
            array(
                'value' => 2,
                'label' => Mage::helper('sagepaysuite')->__('Force NO AVS/CV2 checks even if enabled on account.')
            ),
            array(
                'value' => 3,
                'label' => Mage::helper('sagepaysuite')->__('Force AVS/CV2 checks even if not enabled for the account but DON\'T apply any rules.')
            )
        );
    }
}