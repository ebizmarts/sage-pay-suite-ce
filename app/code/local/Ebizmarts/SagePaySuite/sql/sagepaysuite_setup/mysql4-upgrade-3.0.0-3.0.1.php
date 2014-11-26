<?php

$installer = $this;
$connection = $installer->getConnection();

$installer->startSetup();

$tables = array(
    'sagepaysuite_paypaltransaction',
    'sagepaysuite_action',
    'sagepaysuite_fraud',    
    'sagepaysuite_transaction',
    'sagepaysuite_tokencard',
    'sagepaysuite_debug',
    'sagepaysuite_session',
);

for ($i = 0; $i < count($tables); $i++) {
    $connection->addColumn($this->getTable($tables[$i]), 'created_at', 'timestamp');
}

$installer->endSetup();