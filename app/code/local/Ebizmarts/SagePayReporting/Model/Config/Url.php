<?php

class Ebizmarts_SagePayReporting_Model_Config_Url extends Varien_Object
{
	const URL_LIVE  = 1;
	const URL_TEST  = 2;

	static public function toOptionArray()
	{
		return array(
		self::URL_LIVE  => Mage::helper('sagepayreporting')->__('Live'),
		self::URL_TEST  => Mage::helper('sagepayreporting')->__('Test')
		);
	}
}