<?php

class Ebizmarts_SagePayReporting_Model_Cron {

	/**
	 * Retrieve fraud score (3rd man) for transactions that do not have score.
	 * @param  $cron Cron object
	 * @return Ebizmarts_SagePayReporting_Model_Cron
	 */
	public function getThirdmanScores($cron) {

		$fraudTblName = Mage::getSingleton('core/resource')->getTableName('sagepayreporting_fraud');
		$transactions = Mage::getResourceModel('sagepaysuite2/sagepaysuite_transaction_collection');
		$transactions->addFieldToSelect(array('order_id', 'vendor_tx_code', 'vps_tx_id', 'tx_type'));

		$transactions
		->getSelect()
        ->where("`main_table`.`order_id` IS NOT NULL AND `main_table`.`trndate` IS NOT NULL AND `main_table`.`trndate` > (CURDATE() - INTERVAL 2 DAY) AND (`main_table`.`order_id` NOT IN (SELECT `order_id` FROM ". $fraudTblName ."))")
		->order("main_table.created_at DESC")
		->limit(30);

		foreach($transactions as $_trn) {

			$update = $_trn->updateFromApi();

		}

		
	}

}
