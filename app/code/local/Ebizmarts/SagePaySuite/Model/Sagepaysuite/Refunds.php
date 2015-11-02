<?php

class Ebizmarts_SagePaySuite_Model_Sagepaysuite_Refunds extends Mage_Core_Model_Abstract
{
    /**
     * Initialize resource model
     */
    protected function _construct()
    {
        $this->_init('sagepaysuite/sagepaysuite_refunds');
    }


	public function addRefundLog($result, $parentId, $refunded)
	{
		$this->setParentId($parentId)
		->setStatus($result->getResponseStatus())
		->setStatusDetail($result->getResponseStatusDetail())
		->setVpsTxId($result->getVPsTxId())
		->setTxAuthNo($result->getTxAuthNo())
		->setAmountRefunded($refunded)
		->setRefundedOn(date('Y-m-d H:i:s'))
		->save();
	}

}