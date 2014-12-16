<?php

$installer = $this;

$connection = $installer->getConnection();

$installer->startSetup();

$installer->run("

    -- -----------------------------------------------------
    -- Table `{$this->getTable('sagepaysuite_paypaltransaction')}`
    -- -----------------------------------------------------
    CREATE TABLE IF NOT EXISTS `{$this->getTable('sagepaysuite_paypaltransaction')}` (
      `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT ,
      `transaction_id` INT(11) UNSIGNED NOT NULL,
      `vendor_tx_code` VARCHAR(255) NOT NULL,
      `status` VARCHAR(15) NOT NULL,
      `vps_protocol` VARCHAR(4) NULL,
	  `status_detail` VARCHAR(255) NOT NULL,
      `vps_tx_id` VARCHAR(38) NULL,
      `address_status` VARCHAR(20) NULL,
      `payer_status` VARCHAR(20) NULL,
      `delivery_surname` VARCHAR(20) NULL,
      `delivery_firstnames` VARCHAR(20) NULL,
      `delivery_address` VARCHAR(100) NULL,
      `delivery_addresss` VARCHAR(100) NULL,
      `delivery_city` VARCHAR(40) NULL,
      `delivery_post_code` VARCHAR(10) NULL,
      `delivery_country` VARCHAR(2) NULL,
      `delivery_state` VARCHAR(2) NULL,
      `delivery_phone` VARCHAR(20) NULL,
      `customer_email` VARCHAR(255) NULL,
      `payer_id` VARCHAR(15) NULL,
      `trndate` datetime NULL,
      PRIMARY KEY (`id`) )
    ENGINE = MYISAM DEFAULT CHARSET=utf8;

");

$installer->endSetup();