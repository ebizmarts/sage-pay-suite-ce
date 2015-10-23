<?php

class Ebizmarts_SagePaySuite_Model_Sagepaysuite_Source_AccountType
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'E',
                'label' => Mage::helper('sagepaysuite')->__('E-Commerce')
            ),
            array(
                'value' => 'M',
                'label' => Mage::helper('sagepaysuite')->__('Telephone/Mail Order')
            )
        );
    }
}