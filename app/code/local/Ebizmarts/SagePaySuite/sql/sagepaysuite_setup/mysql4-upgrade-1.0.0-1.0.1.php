<?php

$installer = $this;
$connection = $installer->getConnection();

$installer->startSetup();

try{
	$installer->addAttribute('order_payment', 'cc_gift_aid', array());
	$installer->addAttribute('quote_payment', 'cc_gift_aid', array());
	$connection->addColumn($this->getTable('sales_flat_quote_payment'), 'cc_gift_aid', 'tinyint(1)');
}catch(Exception $ee){

}

$installer->endSetup();