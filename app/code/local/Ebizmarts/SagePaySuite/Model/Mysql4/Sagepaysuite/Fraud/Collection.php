<?php

/**
 * Fraud info collection model
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */

class Ebizmarts_SagePaySuite_Model_Mysql4_Sagepaydirectpro_Fraud_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
	protected function _construct()
	{
		$this->_init('sagepaysuite2/sagepaysuite_fraud');
	}
}