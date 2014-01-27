<?php

$installer  = $this;
$connection = $installer->getConnection();

$installer->startSetup();

//Changing tables storage engine to avoid table locking, with InnoDB we get row locking.

$installer->run("
	ALTER TABLE `{$this->getTable('sagepaysuite_transaction')}` ENGINE='InnoDB';
	ALTER TABLE `{$this->getTable('sagepaysuite_action')}` ENGINE='InnoDB';
	ALTER TABLE `{$this->getTable('sagepaysuite_fraud')}` ENGINE='InnoDB';
	ALTER TABLE `{$this->getTable('sagepaysuite_paypaltransaction')}` ENGINE='InnoDB';
	ALTER TABLE `{$this->getTable('sagepaysuite_debug')}` ENGINE='InnoDB';
	ALTER TABLE `{$this->getTable('sagepaysuite_session')}` ENGINE='InnoDB';
	ALTER TABLE `{$this->getTable('sagepaysuite_tokencard')}` ENGINE='InnoDB';
	ALTER TABLE `{$this->getTable('sagepaysuite_transaction_queue')}` ENGINE='InnoDB';
");

$connection->addColumn($this->getTable('sagepaysuite_transaction'), 'updated_at', 'timestamp');

$installer->endSetup();