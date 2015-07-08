<?php

$installer = $this;
$connection = $installer->getConnection();

$installer->startSetup();

$connection->addColumn($this->getTable('sagepaysuite_transaction'), 'server_notify_arrived', 'TINYINT(1) null');

$installer->endSetup();