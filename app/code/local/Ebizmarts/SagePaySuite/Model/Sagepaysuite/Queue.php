<?php

class Ebizmarts_SagePaySuite_Model_Sagepaysuite_Queue extends Mage_Core_Model_Abstract {

    /**
     * Initialize resource model
     */
    protected function _construct() {
        $this->_init('sagepaysuite2/sagepaysuite_queue');
    }

    public function push(Ebizmarts_SagePaySuite_Model_Sagepaysuite_Transaction $trn) {
        $this->setTransactionId($trn->getId())
        	 ->setInitialStatus($trn->getStatus())
        	 ->setInitialStatusDetail($trn->getStatusDetail())
        	 ->setProcessed(0)
        	 ->setProcessedStatus("idle")
        	 ->setProcessedStatusDetail("Queued")
        	 ->setCreatedAt(Mage::getModel('core/date')->gmtDate())
        	 ->save();

        return $this;
    }

}
