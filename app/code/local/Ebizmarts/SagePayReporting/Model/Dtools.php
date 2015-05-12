<?php

class Ebizmarts_SagePayReporting_Model_Dtools
{

	public function pad($n)
	{
		return str_pad($n, 3, '0', STR_PAD_LEFT);
	}

	public function getIpAddress()
	{
		try{
			/*$xml = file_get_contents('http://ip-address.domaintools.com/myip.xml');
			 $xml = new Varien_Simplexml_Element($xml);

			$ip = (string)$xml->ip_address;*/
			$ip = explode('.', file_get_contents('https://ebizmarts.com/magento/ipcheck.php'));
			$ip = array_map(array($this, 'pad'), $ip);

			return implode('.', $ip);

		}catch(Exception $e){
			Ebizmarts_SagePaySuite_Log::we($e);
			return '';
		}
	}
}