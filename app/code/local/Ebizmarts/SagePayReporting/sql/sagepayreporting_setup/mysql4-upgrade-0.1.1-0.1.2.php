<?php

$installer  = $this;
$connection = $installer->getConnection();

$installer->startSetup();

//Changing tables storage engine to avoid table locking, with InnoDB we get row locking.

$installer->run("
	ALTER TABLE `{$this->getTable('sagepayreporting_fraud')}` ENGINE='InnoDB';
");

$installer->endSetup();