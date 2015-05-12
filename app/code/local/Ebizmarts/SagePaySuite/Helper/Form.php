<?php

/**
 * FORM helper
 *
 * @category    Ebizmarts
 * @package     Ebizmarts_SagePaySuite
 * @author      Ebizmarts Team <info@ebizmarts.com>
 */

class Ebizmarts_SagePaySuite_Helper_Form extends Mage_Core_Helper_Abstract
{

	public function getToken($string)
	{
		$strV = explode('&', $string);
		$output = array();
		foreach($strV as $key => $value)
		{
			$val1 = explode('=',$value);
			$output[$val1[0]]=$val1[1];
		}
		return $output;
	}

}