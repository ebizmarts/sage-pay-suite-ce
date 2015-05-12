<?php

/**
 * API model
 *
 * @category    Ebizmarts
 * @package     Ebizmarts_SagePayReporting
 */
class Ebizmarts_SagePayReporting_Model_Api extends Ebizmarts_SagePaySuite_Model_Api_Resource {

    /**
     * Get transaction data for specific Sage Pay Unique ID
     *
     * @param string $vpsTxId Sage Pay Unique transaction ID
     * @return array
     */
    public function info($vpsTxId) {

        $transaction = Mage::getModel('sagepayreporting/sagepayreporting')
        				->getTransactionDetails(null, $vpsTxId);

		$result = array();

		if ($transaction->getError()) {
			$this->_fault('api_error', $transaction->getError());
		}else{
			foreach($transaction->getData() as $key => $data) {
				$result [$key]= (string)$data;
			}
		}

        return $result;
    }

    /**
     * Get transaction fraud detailed info for specific Sage Pay Unique ID
     *
     * @param string $vpsTxId Sage Pay Unique transaction ID
     * @return array
     */
    public function fraud_detail($vpsTxId) {

        $transaction = $this->info($vpsTxId);

        try {
        	$thirdmanId = $transaction['t3mid'];
            $breakdown = Mage::getModel('sagepayreporting/sagepayreporting')->getT3MDetail($thirdmanId);
            if($breakdown['ok'] !== true){
                $this->_fault('api_error', $breakdown['result']);
            }
            $breakdown = $breakdown['result'];
        }catch(Exception $e){
        	$this->_fault('api_error', $e->getMessage());
       	}

		$result = array();

		foreach($breakdown->t3mresults->rule as $rule) {
			$result[]= (string)$rule->description . " " . (string)$rule->score;
		}

        return $result;
    }
}