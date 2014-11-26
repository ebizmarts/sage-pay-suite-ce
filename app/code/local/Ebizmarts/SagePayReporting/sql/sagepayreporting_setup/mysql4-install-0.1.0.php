<?php

$installer = $this;

$installer->startSetup();

$installer->run("

    -- -----------------------------------------------------
    -- Table `{$this->getTable('sagepayreporting_fraud')}`
    -- -----------------------------------------------------
    CREATE  TABLE IF NOT EXISTS `{$this->getTable('sagepayreporting_fraud')}` (
      `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
      `order_id` INT(11) UNSIGNED NOT NULL,
      `vendor_tx_code` VARCHAR(255) NOT NULL,
      `cv2result` VARCHAR(255) NOT NULL,
      `addressresult` VARCHAR(255) NOT NULL,
      `postcoderesult` VARCHAR(255) NOT NULL,
      `thirdman_score` VARCHAR(255) NULL,
      `thirdman_action` VARCHAR(255) NULL,
      `thirdman_id` VARCHAR(255) NULL,
      `tresd` VARCHAR(255) NULL,
      `vps_tx_id` VARCHAR(255) NULL,
      PRIMARY KEY (`id`) )
    ENGINE = MYISAM DEFAULT CHARSET=utf8;

");

$installer->run("
	ALTER TABLE `{$this->getTable('sagepayreporting_fraud')}` ADD INDEX `IDX_SAGEPAYREPORTING_FRAUD_ORDER_ID` (`order_id`);
");

$installer->endSetup();