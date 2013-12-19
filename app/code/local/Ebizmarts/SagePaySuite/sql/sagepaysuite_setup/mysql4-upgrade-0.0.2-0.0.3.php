<?php

$installer = $this;

$connection = $installer->getConnection();

$installer->startSetup();

$connection->addColumn($this->getTable('sagepaysuite_tokencard'), 'visitor_session_id', 'varchar(255)');

$installer->endSetup();