<?php

class Ebizmarts_SagePaySuite_Model_Sagepaysuite_Source_Sendemail
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 1,
                'label' => Mage::helper('sagepaysuite')->__('Send customer and vendor e-mails if addresses are provided (DEFAULT)')
            ),
            array(
                'value' => 2,
                'label' => Mage::helper('sagepaysuite')->__('Send vendor e-mail but NOT the customer e-mail')
            ),
            array(
                'value' => 0,
                'label' => Mage::helper('sagepaysuite')->__('Do not send either customer or vendor e-mails')
            ),
        );
    }
}

