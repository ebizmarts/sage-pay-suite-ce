<?php

class Ebizmarts_SagePayReporting_Model_Sagepayreporting_Fraud extends Mage_Core_Model_Abstract
{
	protected function _construct()
	{
		$this->_init('sagepayreporting/sagepayreporting_fraud');
	}

	public function loadByOrderId($orderId)
	{
		$this->load($orderId, 'order_id');
		return $this;
	}
}