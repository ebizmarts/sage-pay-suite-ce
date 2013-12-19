<?php

$installer = $this;
$connection = $installer->getConnection();

$installer->startSetup();

$connection->addColumn($this->getTable('sagepaysuite_transaction'), 'tx_state_id', 'int(11)');

$installer->endSetup();