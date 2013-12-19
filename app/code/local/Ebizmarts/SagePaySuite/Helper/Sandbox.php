<?php

/**
 * Sandbox helper.
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */

class Ebizmarts_SagePaySuite_Helper_Sandbox extends Mage_Core_Helper_Abstract
{

	public function getTestDataJson()
    {
		return Zend_Json::encode($this->getSagePayTestData());
	}

	public function getSandBox()
	{
		return new Varien_Simplexml_Element(file_get_contents(Mage::getModuleDir('etc', 'Ebizmarts_SagePaySuite').DS.'sandbox.xml'));
	}

	public function objToArray($v)
	{
		return (array)$v;
	}

	public function getSagePayTestData()
    {

    	$sandbox = $this->getSandBox();

		$cardsArray = array_values(array_map(array($this, 'objToArray'), (array)$sandbox->testcards));

		return $cardsArray;
	}

}