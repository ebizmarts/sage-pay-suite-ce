<?php

$installer  = $this;
$connection = $installer->getConnection();

$installer->startSetup();

$installer->run("
    ALTER TABLE `{$this->getTable('sagepaysuite_tokencard')}` CHANGE `card_type` `card_type` enum('SWITCH', 'VISA', 'MC', 'DELTA', 'SOLO', 'MAESTRO', 'UKE', 'AMEX', 'DC', 'JCB', 'LASER', 'MCDEBIT');
");

$connection->addColumn($this->getTable('sagepaysuite_tokencard'), 'nickname', 'varchar(30) null');
$connection->addColumn($this->getTable('sagepaysuite_transaction'), 'nickname', 'varchar(30) null');

$connection->addColumn($this->getTable('sagepaysuite_transaction'), 'euro_payments_status', 'varchar(50) null');

$connection->addColumn($this->getTable('sagepaysuite_transaction'), 'server_success_arrived', 'TINYINT(1) null');
$connection->addColumn($this->getTable('sagepaysuite_transaction'), 'server_session', 'TEXT null');

$installer->run("
	ALTER TABLE `{$this->getTable('sagepaysuite_transaction')}` ADD INDEX `IDX_SAGEPAYSUITE_TRANSACTION_CREATED_AT` (`created_at`);
");

$installer->endSetup();