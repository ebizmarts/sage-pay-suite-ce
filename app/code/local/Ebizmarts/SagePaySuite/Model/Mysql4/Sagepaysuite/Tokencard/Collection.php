<?php

class Ebizmarts_SagePaySuite_Model_Mysql4_SagePaySuite_Tokencard_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract {

    protected function _construct() {
        $this->_init('sagepaysuite2/sagePaySuite_tokencard');
    }

    public function addCustomerFilter($customer) {

        if (is_string($customer)) {
            $this->addFieldToFilter('visitor_session_id', $customer);
        } else if ($customer instanceof Mage_Customer_Model_Customer) {
            $this->addFieldToFilter('customer_id', $customer->getId());
        } elseif (is_numeric($customer)) {
            $this->addFieldToFilter('customer_id', $customer);
        } elseif (is_array($customer)) {
            $this->addFieldToFilter('customer_id', $customer);
        }/*
          else {
          Mage::throwException(
          Mage::helper('sagepaysuite')->__('Invalid parameter for customer filter')
          );
          } */

        return $this;
    }

    /**
     * Filter collection by vendorname.
     *
     * @param Ebizmarts_SagePaySuite_Model_Mysql4_SagePaySuite_Tokencard_Collection
     */
    public function addVendorFilter($vendorname) {

      $this->getSelect()->where("`main_table`.`vendor` IS NULL OR `main_table`.`vendor` = '". $vendorname ."'");

      return $this;
    }

}