<?php

$installer = $this;
$connection = $installer->getConnection();

$installer->startSetup();

$connection->addColumn($this->getTable('sagepaysuite_action'), 'security_key', 'varchar(200)');
$connection->addColumn($this->getTable('sagepaysuite_action'), 'vendor_tx_code', 'varchar(200)');

$installer->endSetup();