<?php
/**
 * Server token card Form
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */
class Ebizmarts_SagePaySuite_Block_Customer_Account_Card_Server_Form extends Mage_Core_Block_Template
{
	protected $_nURL = '';

	public function setNextUrl($url)
	{
	  $this->_nURL = $url;
	  return $this;
	}

	public function getNextUrl()
	{
	  return $this->_nURL;
	}

	public function _construct()
	{
		parent::_construct();
		$this->setTemplate('sagepaysuite/customer/card/server/form.phtml');
	}

}