<?php

$installer = $this;
$connection = $installer->getConnection();

$installer->startSetup();

$connection->addColumn($this->getTable('sagepaysuite_tokencard'), 'store_id', 'smallint(5) unsigned');


$connection->addColumn($this->getTable('sales/quote_item'), 'base_surcharge_amount', 'decimal(12,4) null');
$connection->addColumn($this->getTable('sales/quote_item'), 'surcharge_amount', 'decimal(12,4) null');

$connection->addColumn($this->getTable('sales/quote_address_item'), 'base_surcharge_amount', 'decimal(12,4) null');
$connection->addColumn($this->getTable('sales/quote_address_item'), 'surcharge_amount', 'decimal(12,4) null');

$connection->addColumn($this->getTable('sales/quote_address'), 'base_surcharge_amount', 'decimal(12,4) null');
$connection->addColumn($this->getTable('sales/quote_address'), 'surcharge_amount', 'decimal(12,4) null');

$connection->addColumn($this->getTable('sales/order_item'), 'base_surcharge_amount', 'decimal(12,4) null');
$connection->addColumn($this->getTable('sales/order_item'), 'surcharge_amount', 'decimal(12,4) null');

$connection->addColumn($this->getTable('sales/order'), 'base_surcharge_amount', 'decimal(12,4) null');
$connection->addColumn($this->getTable('sales/order'), 'surcharge_amount', 'decimal(12,4) null');

$installer->endSetup();