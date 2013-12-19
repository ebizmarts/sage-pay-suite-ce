<?php

$installer = $this;
$connection = $installer->getConnection();

$installer->startSetup();

$installer->run("
 ALTER TABLE `{$this->getTable('sagepaysuite_transaction')}` CHANGE `integration` `integration` enum('direct', 'server', 'form') NOT NULL;
");

$installer->endSetup();