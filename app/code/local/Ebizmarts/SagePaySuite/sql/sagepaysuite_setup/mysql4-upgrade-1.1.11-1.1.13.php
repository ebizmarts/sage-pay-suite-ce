<?php

$installer = $this;
$connection = $installer->getConnection();

$installer->startSetup();

$installer->run("

    -- -----------------------------------------------------
    -- Table `{$this->getTable('sagepaysuite_transaction_queue')}`
    -- -----------------------------------------------------
    CREATE TABLE IF NOT EXISTS `{$this->getTable('sagepaysuite_transaction_queue')}` (
      `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT ,
      `transaction_id` INT(11) UNSIGNED NOT NULL,
      `initial_status` VARCHAR(255) NULL,
      `initial_status_detail` VARCHAR(255) NULL,
      `processed` TINYINT(2) NULL DEFAULT 0,
      `processed_status` VARCHAR(255) NULL,
      `processed_status_detail` VARCHAR(255) NULL,
	  `processed_at` DATETIME NULL,
      `created_at` DATETIME NULL,
      PRIMARY KEY (`id`) )
    ENGINE = MYISAM DEFAULT CHARSET=utf8;

");

$installer->endSetup();
