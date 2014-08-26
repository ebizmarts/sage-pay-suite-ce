<?php


class Ebizmarts_SagePaySuite_Model_Mysql4_SagePaySuite_Session extends Mage_Core_Model_Mysql4_Abstract
{
    protected function _construct()
    {
        $this->_init('sagepaysuite2/sagepaysuite_session', 'id');
    }
}