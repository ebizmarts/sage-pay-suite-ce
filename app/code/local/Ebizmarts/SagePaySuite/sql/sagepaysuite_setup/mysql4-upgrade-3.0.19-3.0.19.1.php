<?php

$installer = $this;
$connection = $installer->getConnection();

$installer->startSetup();

$installer->run("
    ALTER TABLE `{$this->getTable('sagepaysuite_tokencard')}` CHANGE `card_type` `card_type` enum('SWITCH', 'VISA', 'MC', 'DELTA', 'SOLO', 'MAESTRO', 'UKE', 'AMEX', 'DC', 'JCB', 'LASER', 'MCDEBIT');
");

$installer->endSetup();