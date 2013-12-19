<?php

$installer = $this;
$connection = $installer->getConnection();

$installer->startSetup();

$columns = array(
    'avscv2',
    'address_result',
    'postcode_result',
    'cv2result',
);

for ($i = 0; $i < count($columns); $i++) {
    $connection->addColumn($this->getTable('sagepaysuite_action'), $columns[$i], 'VARCHAR(255)');
}

$connection->addColumn($this->getTable('sagepaysuite_action'), 'decline_code', 'INT(11)');
$connection->addColumn($this->getTable('sagepaysuite_action'), 'bank_auth_code', 'INT(11)');

$installer->endSetup();