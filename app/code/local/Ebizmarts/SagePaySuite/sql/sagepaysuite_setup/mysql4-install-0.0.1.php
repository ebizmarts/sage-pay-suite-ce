<?php

$installer = $this;

$connection = $installer->getConnection();

$installer->startSetup();

$installer->run("

	-- ------------------------------------------------------------------
	-- MC is MasterCard, UKE is Visa Electron. MAESTRO should be
	-- used for both UK and International Maestro.
	-- AMEX and DC (DINERS) can only be accepted
	-- if you have additional merchant accounts with those acquirers.
	-- ------------------------------------------------------------------
	CREATE TABLE IF NOT EXISTS `{$this->getTable('sagepaysuite_tokencard')}` (
	  `id` int(10) unsigned NOT NULL auto_increment,
      `customer_id` int(10) unsigned NOT NULL,
	  `token` varchar(38),
	  `status` varchar(15),
	  `card_type` enum('SWITCH', 'VISA', 'MC', 'DELTA', 'SOLO', 'MAESTRO', 'UKE', 'AMEX', 'DC', 'JCB', 'LASER'),
	  `last_four` varchar(4),
	  `expiry_date` varchar(4),
	  `status_detail` varchar(255),
      `vendor` varchar(255),
      `protocol` enum('server', 'direct'),
      `is_default` tinyint(1) unsigned NOT NULL default '0',
	  PRIMARY KEY  (`id`)
	) ENGINE=MYISAM DEFAULT CHARSET=utf8;



	CREATE TABLE IF NOT EXISTS `{$this->getTable('sagepaysuite_debug')}` (
	  `debug_id` int(10) unsigned NOT NULL auto_increment,
	  `request_body` text,
	  `response_body` text,
	  `request_serialized` text,
	  `result_serialized` text,
	  `request_dump` text,
	  `result_dump` text,
	  `method` enum('server', 'direct'),
	  PRIMARY KEY  (`debug_id`)
	) ENGINE=MYISAM DEFAULT CHARSET=utf8;

	-- -----------------------------------------------------
	-- Table `{$this->getTable('sagepaysuite_session')}`
	-- -----------------------------------------------------
	CREATE TABLE IF NOT EXISTS `{$this->getTable('sagepaysuite_session')}` (
	  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT ,
      `order_id` INT(11) UNSIGNED NULL,
	  `session_id` VARCHAR(255) NOT NULL,
	  `security_key` VARCHAR(255) NULL,
	  `vendor_tx_code` VARCHAR(255) NULL,
	  `trn_done_status` VARCHAR(255) NULL,
	  `vps_tx_id` VARCHAR(255) NULL,
	  `tx_auth_no` VARCHAR(255) NULL,
	  `next_url` VARCHAR(255) NULL,
	  `customer_group_id` VARCHAR(255) NULL,
	  `customer_email` VARCHAR(255) NULL,
	  `uname_moto_order` VARCHAR(255) NULL,
	  `last_request` TEXT NULL,
	  `last_response` TEXT NULL,
	  `remote_addr` VARCHAR(255) NULL,
	  `trnh_data` TEXT NULL,
	  `success_status` TEXT NULL,
	  `fail_status` TEXT NULL,
	  `dummy_id` INT(11) UNSIGNED,
	  PRIMARY KEY (`id`) )
	ENGINE = MYISAM DEFAULT CHARSET=utf8;

    -- -----------------------------------------------------
    -- Table `{$this->getTable('sagepaysuite_fraud')}`
    -- -----------------------------------------------------
    CREATE TABLE IF NOT EXISTS `{$this->getTable('sagepaysuite_fraud')}` (
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

    -- ------------------------------------------------------------
    -- Store REPEATS, REFUNDS, RELEASES
    -- ------------------------------------------------------------

    CREATE TABLE IF NOT EXISTS `{$this->getTable('sagepaysuite_action')}` (
      `id` INT(11) unsigned NOT NULL auto_increment,
      `parent_id` INT(11) unsigned NOT NULL,
      `status` VARCHAR(255),
      `status_detail` VARCHAR(255),
      `vps_tx_id` VARCHAR(255),
      `tx_auth_no` INT(11),
      `amount` DECIMAL(12,4),
      `currency` VARCHAR(255),
      `action_code` enum('repeat', 'release', 'refund', 'authorise') NOT NULL,
      `action_date` DATETIME,
      PRIMARY KEY  (`id`)
    ) ENGINE=MYISAM DEFAULT CHARSET=utf8;


    -- -----------------------------------------------------
    -- Table `{$this->getTable('sagepaysuite_transaction')}`
    -- -----------------------------------------------------
    CREATE TABLE IF NOT EXISTS `{$this->getTable('sagepaysuite_transaction')}` (
      `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT ,
      `order_id` INT(11) UNSIGNED NULL,
      `vendor_tx_code` VARCHAR(255) NOT NULL,
      `vps_protocol` VARCHAR(255) NOT NULL,
      `integration` enum('direct', 'server') NOT NULL,
      `vendorname` VARCHAR(255) NOT NULL,
      `mode` VARCHAR(255) NOT NULL,
      `trn_currency` VARCHAR(255) NOT NULL,
      `tx_type` VARCHAR(255) NOT NULL,
      `security_key` VARCHAR(255) NULL,
      `vps_tx_id` VARCHAR(255) NULL,
      `tx_auth_no` VARCHAR(255) NULL,
      `avscv2` VARCHAR(255) NULL,
      `address_result` VARCHAR(255) NULL,
      `postcode_result` VARCHAR(255) NULL,
      `cv2result` VARCHAR(255) NULL,
      `threed_secure_status` VARCHAR(255) NULL,
      `cavv` VARCHAR(255) NULL,
      `address_status` VARCHAR(255) NULL,
      `payer_status` VARCHAR(255) NULL,
      `card_type` VARCHAR(255) NULL,
      `last_four_digits` VARCHAR(255) NULL,
      `vps_signature` VARCHAR(255) NULL,
      `status` VARCHAR(255) NULL,
      `status_detail` VARCHAR(255) NULL,
      `giftaid` TINYINT(2) NULL,
      `canceled` TINYINT(2) NULL,
      `voided` TINYINT(2) NULL,
      `aborted` TINYINT(2) NULL,
      `released` TINYINT(2) NULL,
      `authorised` TINYINT(2) NULL,
      `token` varchar(38) NULL,
      `trndate` datetime NULL,
      PRIMARY KEY (`id`) )
    ENGINE = MYISAM DEFAULT CHARSET=utf8;
");


try{
	$connection->addColumn($this->getTable('sales_flat_quote_payment'), 'sagepay_token_cc_id', 'int(11)');
	$installer->addAttribute('order_payment', 'sagepay_token_cc_id', array());
	$installer->addAttribute('order_payment', 'sagepay_canceled', array());
	$installer->addAttribute('order_payment', 'sagepay_aborted', array());
	$installer->addAttribute('order_payment', 'sagepay_released', array());
	$installer->addAttribute('order_payment', 'sagepay_voided', array());
	$installer->addAttribute('quote_payment', 'sagepay_token_cc_id', array());
}catch(Exception $ee){

}

$installer->endSetup();