<?php

$installer = $this;
$connection = $installer->getConnection();

$installer->startSetup();

$installer->run("ALTER TABLE `{$this->getTable('sagepaysuite_transaction')}` MODIFY `bank_auth_code` VARCHAR(100);");

$installer->endSetup();