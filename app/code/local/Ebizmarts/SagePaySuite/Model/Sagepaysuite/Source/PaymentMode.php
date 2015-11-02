<?php


/**
 *
 * Sagepay Payment Mode Dropdown source
 *
 */
class Ebizmarts_SagePaySuite_Model_Sagepaysuite_Source_PaymentMode {

    public function toOptionArray() {
        return array(
            /*
            array(
                'value' => 'simulator',
                'label' => Mage::helper('sagepaysuite')->__('Simulator')
            ),
            */
            array(
                'value' => 'test',
                'label' => Mage::helper('sagepaysuite')->__('Test')
            ),
            array(
                'value' => 'live',
                'label' => Mage::helper('sagepaysuite')->__('Live')
            )
        );
    }

    public function toOptions() {
        $options = $this->toOptionArray();

        $modes = array();

        for($i = 0; $i < count($options); $i++) {
            $modes[$options[$i]['value']] = $options[$i]['label'];
        }

        return $modes;
    }
}