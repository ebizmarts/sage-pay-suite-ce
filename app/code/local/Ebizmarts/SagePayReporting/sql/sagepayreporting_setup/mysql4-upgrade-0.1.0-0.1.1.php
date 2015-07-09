<?php

$installer = $this;
$connection = $installer->getConnection();

$installer->startSetup();

    $connection->addColumn($this->getTable('sagepayreporting_fraud'), 'created_at', 'timestamp');

$installer->endSetup();