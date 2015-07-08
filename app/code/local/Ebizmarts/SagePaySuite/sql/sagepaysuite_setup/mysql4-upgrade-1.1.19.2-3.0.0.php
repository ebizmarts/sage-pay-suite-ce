<?php

$installer = $this;
$connection = $installer->getConnection();

$installer->startSetup();

$connection->addColumn($this->getTable('sagepaysuite_transaction'), 'acsurl', 'varchar(255)');
$connection->addColumn($this->getTable('sagepaysuite_transaction'), 'md',      'text');
$connection->addColumn($this->getTable('sagepaysuite_transaction'), 'pareq',   'text');
$connection->addColumn($this->getTable('sagepaysuite_transaction'), 'pares',   'text');

$connection->addColumn($this->getTable('sagepaysuite_transaction'), 'red_fraud_response', 'varchar(255)');
$connection->addColumn($this->getTable('sagepaysuite_transaction'), 'surcharge_amount',  'decimal(12,4)');
$connection->addColumn($this->getTable('sagepaysuite_transaction'), 'bank_auth_code',   'int(11)');
$connection->addColumn($this->getTable('sagepaysuite_transaction'), 'decline_code',   'int(11)');

$installer->endSetup();
