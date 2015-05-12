<?php

/**
 * API model
 *
 * @category    Ebizmarts
 * @package     Ebizmarts_SagePaySuite
 */
class Ebizmarts_SagePaySuite_Model_Api extends Ebizmarts_SagePaySuite_Model_Api_Resource {

    /**
     * Initialize basic transaction model
     *
     * @param string $vpsTxId Sage Pay Unique transaction ID
     * @return Ebizmarts_SagePaySuite_Model_Sagepaysuite_Transaction
     */
    protected function _initTransaction($vpsTxId) {

        $transaction = Mage::getModel('sagepaysuite2/sagepaysuite_transaction')
        				->loadByVpsTxId($vpsTxId);

        if (!$transaction->getId()) {
            $this->_fault('not_exists');
        }

        return $transaction;
    }

    /**
     * Retrieve list of transaction.
     * Filtration could be applied
     *
     * @param null|object|array $filters
     * @return array
     */
    public function items($filters = null) {

        $transactions = array();

        $transactionCollection = Mage::getModel('sagepaysuite2/sagepaysuite_transaction')
        						 	->getCollection();

        /** @var $apiHelper Mage_Api_Helper_Data */
        $apiHelper = Mage::helper('api');
        $filters = $apiHelper->parseFilters($filters);
        try {
            foreach ($filters as $field => $value) {
                $transactionCollection->addFieldToFilter($field, $value);
            }
        } catch (Mage_Core_Exception $e) {
            $this->_fault('filters_invalid', $e->getMessage());
        }
        foreach ($transactionCollection as $trn) {
        	//ToDo: Probar si la info de paypal trns viene
            $transactions[] = $this->_getAttributes($trn, 'transaction');
        }

        return $transactions;
    }

    /**
     * Get transaction data for specific Sage Pay Unique ID
     *
     * @param string $vpsTxId Sage Pay Unique transaction ID
     * @return array
     */
    public function info($vpsTxId) {
        $transaction = $this->_initTransaction($vpsTxId);

        $result = $this->_getAttributes($transaction, 'transaction');

        return $result;
    }

}