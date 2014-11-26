<?php

$installer = $this;
$connection = $installer->getConnection();

$installer->startSetup();

$connection->addColumn($this->getTable('sagepaysuite_transaction'), 'parent_trn_id', 'int');

$installer->endSetup();