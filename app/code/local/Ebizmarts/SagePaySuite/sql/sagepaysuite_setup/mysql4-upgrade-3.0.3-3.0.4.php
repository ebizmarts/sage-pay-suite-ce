<?php

$installer = $this;
$connection = $installer->getConnection();

$installer->startSetup();

//@ToDo: Save amount in table.

$connection->addColumn($this->getTable('sagepaysuite_transaction'), 'trn_amount', 'DECIMAL(12,4)');

$installer->endSetup();