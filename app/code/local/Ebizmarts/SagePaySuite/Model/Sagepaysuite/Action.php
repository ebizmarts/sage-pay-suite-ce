<?php

class Ebizmarts_SagePaySuite_Model_Sagepaysuite_Action extends Mage_Core_Model_Abstract
{

    /**
     * Initialize resource model
     */
    protected function _construct()
    {
        $this->_init('sagepaysuite2/sagepaysuite_action');
    }

    public function loadByParent($orderId)
    {
        $this->load($orderId, 'parent_id');
        return $this;
    }

    public function getLastAuthorise($orderId)
    {
		return $this->getCollection()
    	->setOrderFilter($orderId)
                    ->setAuthoriseFilter()
    				->addOrder('action_date')
    				->load()->getFirstItem();
    }

}