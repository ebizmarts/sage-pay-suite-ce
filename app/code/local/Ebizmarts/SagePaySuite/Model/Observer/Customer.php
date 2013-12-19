<?php

/**
 * Customer events observer model
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */

class Ebizmarts_SagePaySuite_Model_Observer_Customer
{
    /**
     * Set cards for customer on CHECKOUT REGISTER
     */
    public function addRegisterCheckoutTokenCards($e)
    {
        $customer = $e->getEvent()->getCustomer();

        $sessionCards = Mage::helper('sagepaysuite/token')->getSessionTokens();

        if($sessionCards->getSize() > 0){
            foreach($sessionCards as $_c){
                if($_c->getCustomerId() == 0){
                    $_c->setCustomerId($customer->getId())
                            ->setVisitorSessionId(null)
                        ->save();
                }
            }
        }
    }
}