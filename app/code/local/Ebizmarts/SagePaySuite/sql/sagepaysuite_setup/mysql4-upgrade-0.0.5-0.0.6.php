<?php

$installer = $this;
$connection = $installer->getConnection();

$installer->startSetup();

$connection->addColumn($this->getTable('sagepaysuite_transaction'), 'quote_id', 'int(11) unsigned');
$connection->addColumn($this->getTable('sagepaysuite_transaction'), 'store_id', 'int(11) unsigned');

$installer->endSetup();