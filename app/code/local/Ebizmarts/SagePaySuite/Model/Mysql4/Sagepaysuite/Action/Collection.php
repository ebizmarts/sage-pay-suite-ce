<?php

/**
 * Action collection model, refund, void, authorise etc
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */

class Ebizmarts_SagePaySuite_Model_Mysql4_SagePaySuite_Action_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
	protected function _construct()
	{
		$this->_init('sagepaysuite2/sagepaysuite_action');
	}

	public function setPaymentsFilter()
	{
        $this->addFieldToFilter('action_code', array('repeat','release','authorise'));
        return $this;
	}

    public function setRepeatFilter()
    {
        $this->addFieldToFilter('action_code', 'repeat');
        return $this;
    }

    public function setRefundFilter()
    {
        $this->addFieldToFilter('action_code', 'refund');
        return $this;
    }

    public function setDirectRefundFilter()
    {
        $this->addFieldToFilter('action_code', 'directrefund');
        return $this;
    }

    public function setReleaseFilter()
    {
        $this->addFieldToFilter('action_code', 'release');
        return $this;
    }

    public function setAuthoriseFilter()
    {
        $this->addFieldToFilter('action_code', 'authorise');
        return $this;
    }

    public function setOrderFilter($orderId)
    {
        $this->addFieldToFilter('parent_id', $orderId);
        return $this;
    }
}