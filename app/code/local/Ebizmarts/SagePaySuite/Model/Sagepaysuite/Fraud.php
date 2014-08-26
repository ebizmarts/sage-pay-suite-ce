<?php

class Ebizmarts_SagePaySuite_Model_Sagepaysuite_Fraud extends Mage_Core_Model_Abstract
{
	protected function _construct()
	{
		$this->_init('sagepaysuite2/sagepaysuite_fraud');
	}

    public function loadByOrderId($orderId)
    {
        $this->load($orderId, 'order_id');
        return $this;
    }
}