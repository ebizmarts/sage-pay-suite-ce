<?php

$installer = $this;
$connection = $installer->getConnection();

$installer->startSetup();

$connection->addColumn($this->getTable('sales_flat_quote_payment'), 'repeat_code', 'varchar(255)');
$connection->addColumn($this->getTable('sales_flat_order_payment'), 'repeat_code', 'varchar(255)');

$installer->endSetup();