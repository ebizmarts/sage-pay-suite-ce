<?php

class Ebizmarts_SagePaySuite_Model_Sagepaysuite_Repeats extends Mage_Core_Model_Abstract
{
    /**
     * Initialize resource model
     */
    protected function _construct()
    {
        $this->_init('sagepaysuite/sagepaysuite_repeats');
    }


	public function addLog($result, $parentId, $repeated, $currency)
	{
		$this->setParentId($parentId)
		->setStatus($result->getResponseStatus())
		->setStatusDetail($result->getResponseStatusDetail())
		->setVpsTxId($result->getVPsTxId())
		->setTxAuthNo($result->getTxAuthNo())
		->setAmountRepeated($repeated)
		->setCurrency($currency)
		->setRepeatedOn(date('Y-m-d H:i:s'))
		->save();
	}

}