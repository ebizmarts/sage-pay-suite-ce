<?php

$installer = $this;
$connection = $installer->getConnection();

$installer->startSetup();

$connection->addColumn($this->getTable('sagepaysuite_transaction'), 'payment_system_details', 'varchar(255)');
$connection->addColumn($this->getTable('sagepaysuite_transaction'), 'eci', 'varchar(255)');

$installer->endSetup();
