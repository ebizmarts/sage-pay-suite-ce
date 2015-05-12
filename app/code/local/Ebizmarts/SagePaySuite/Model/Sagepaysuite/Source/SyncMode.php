<?php

class Ebizmarts_SagePaySuite_Model_Sagepaysuite_Source_SyncMode {

    public function toOptionArray() {
        return array(
            'sync' => Mage::helper('sagepaysuite')->__('Synchronous'),
            'async' => Mage::helper('sagepaysuite')->__('Asynchronous (cron)'),
        );
    }

}