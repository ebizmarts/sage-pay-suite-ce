<?php

$installer = $this;

$installer->startSetup();

$installer->run("
	ALTER TABLE `{$this->getTable('sagepaysuite_transaction')}` CHANGE `integration` `integration` ENUM('direct','server','form','nit') CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;
");

$installer->endSetup();