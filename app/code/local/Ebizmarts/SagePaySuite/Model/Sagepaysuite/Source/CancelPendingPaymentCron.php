<?php
/**
 * Copyright © 2015 ebizmarts. All rights reserved.
 * See LICENSE.txt for license details.
 */

class Ebizmarts_SagePaySuite_Model_Sagepaysuite_Source_CancelPendingPaymentCron {

    public function toOptionArray() {
        return array(
            '0'  => Mage::helper('sagepaysuite')->__('Disabled'),
            '10' => Mage::helper('sagepaysuite')->__('Cancel order after 10 min'),
            '20' => Mage::helper('sagepaysuite')->__('Cancel order after 20 min'),
            '30' => Mage::helper('sagepaysuite')->__('Cancel order after 30 min')
        );
    }

}