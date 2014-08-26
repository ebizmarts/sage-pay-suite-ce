<?php


class Ebizmarts_SagePayReporting_Model_Mysql4_Sagepayreporting_Fraud extends Mage_Core_Model_Mysql4_Abstract
{
	protected function _construct()
	{
		$this->_init('sagepayreporting/sagepayreporting_fraud', 'id');
	}
}