<?php

$installer = $this;
$connection = $installer->getConnection();

$installer->startSetup();

$installer->run("
	ALTER TABLE `{$this->getTable('sagepaysuite_transaction')}` ADD INDEX `IDX_SAGEPAYSUITE_TRANSACTION_ORDER_ID` (`order_id`);
	ALTER TABLE `{$this->getTable('sagepaysuite_transaction')}` ADD INDEX `IDX_SAGEPAYSUITE_TRANSACTION_VENDOR_TX_CODE` (`vendor_tx_code`);
");

$installer->endSetup();