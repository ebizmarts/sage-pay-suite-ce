<?php

class Ebizmarts_SagePaySuite_Model_Sagepaysuite_Source_BasketFormat {

    public function toOptionArray() {
        return array(
            'xml'  => Mage::helper('sagepaysuite')->__('Basket XML'),
            'sage' => Mage::helper('sagepaysuite')->__('Basket (Sage 50 compatible)'),
        );
    }

}