<?php

$installer = $this;
$connection = $installer->getConnection();

$installer->startSetup();

$connection->addColumn($this->getTable('sagepaysuite_transaction'), 'card_expiry_date', 'varchar(10)');

$installer->endSetup();