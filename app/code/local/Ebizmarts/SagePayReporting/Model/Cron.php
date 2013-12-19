<?php

class Ebizmarts_SagePayReporting_Model_Cron {

	public function getThirdmanScores($cron) {

		$tblName = Mage::getSingleton('core/resource')->getTableName('sagepayreporting_fraud');
		$sagepayOrders = Mage::getResourceModel('sales/order_grid_collection');

		$sagepayOrders->addAttributeToSelect('entity_id');

		$sagepayOrders->getSelect()
		->joinLeft(array (
			'pmnt' => $sagepayOrders->getTable('sales/order_payment'
		)),
		'main_table.entity_id = pmnt.parent_id', array())
		->joinLeft(array (
			'sls' => $sagepayOrders->getTable('sales/order')
		),
		'main_table.entity_id = sls.entity_id', array())
		->where("(pmnt.method = 'sagepaydirectpro' OR pmnt.method = 'sagepayserver' OR pmnt.method = 'sagepayserver_moto' OR pmnt.method = 'sagepaydirectpro_moto' OR pmnt.method = 'sagepayform' OR pmnt.method = 'sagepaypaypal') AND (main_table.entity_id NOT IN (SELECT order_id FROM ". $tblName ."))")
		->limit(10);

		$now = strtotime("now");

		foreach ($sagepayOrders as $_order) {

			$_order = Mage::getModel('sales/order')->load($_order->getId());

                        if(is_object($_order->getSagepayInfo())) {
                            $dbtrn = Mage::getModel('sagepaysuite2/sagepaysuite_transaction')->loadByVendorTxCode($_order->getSagepayInfo()->getVendorTxCode());
                            //Get up to date transaction data from API
                            $dbtrn->updateFromApi();
                        }

		}

	}

}
