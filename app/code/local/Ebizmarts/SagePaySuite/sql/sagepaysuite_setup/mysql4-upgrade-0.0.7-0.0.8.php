<?php

$installer = $this;
$connection = $installer->getConnection();

$installer->startSetup();

$installer->run("
 ALTER TABLE `{$this->getTable('sagepaysuite_action')}` CHANGE `action_code` `action_code` enum('repeat', 'release', 'refund', 'authorise', 'abort', 'cancel', 'void') NOT NULL;
");

$installer->endSetup();