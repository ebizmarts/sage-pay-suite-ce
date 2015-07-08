<?php

$installer = $this;

$connection = $installer->getConnection();

$installer->startSetup();

$connection->addColumn($this->getTable('sagepaysuite_transaction'), 'customer_contact_info', 'varchar(255)');
$connection->addColumn($this->getTable('sagepaysuite_transaction'), 'customer_cc_holder_name', 'varchar(255)');

$installer->endSetup();