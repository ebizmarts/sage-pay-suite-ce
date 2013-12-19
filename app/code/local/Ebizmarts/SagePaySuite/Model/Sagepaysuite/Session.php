<?php

class Ebizmarts_SagePaySuite_Model_Sagepaysuite_Session extends Mage_Core_Model_Abstract
{
	 /** Order instance
     *
     * @var Mage_Sales_Model_Order
     */
    protected $_order;

    /**
     * Initialize resource model
     */
    protected function _construct()
    {
        $this->_init('sagepaysuite2/sagepaysuite_session');
    }

    public function loadBySessionId($sessionId)
    {
      $col = $this->getCollection()
            ->addFieldToFilter('session_id', $sessionId)
            ->load()->setPageSize(1)->getFirstItem();

      $this->setData($col->getData());

      $this->setSessionId(Mage::getSingleton('core/session')->getSessionId());

      return $this;
    }

}