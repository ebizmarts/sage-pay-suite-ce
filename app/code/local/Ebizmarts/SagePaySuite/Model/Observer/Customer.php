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

        //remove old tokens
        $customer_tokens = Mage::getModel('sagepaysuite2/sagepaysuite_tokencard')
            ->getCollection()
            ->addFieldToFilter("customer_id",$customer->getId());

        foreach ($customer_tokens as $token){

            $expiry_date = $token->getExpiryDate();

            if(!empty($expiry_date) && strlen($expiry_date) == 4){
                $expiry_month = substr($expiry_date,0,2);
                $expiry_year = substr($expiry_date,2);
                $current_month = date("m");
                $current_year = date("y");

                $delete = false;

                if((int)$expiry_year < (int)$current_year){
                    $delete = true;
                }elseif((int)$expiry_year == (int)$current_year){
                    if((int)$expiry_month <= (int)$current_month){
                        $delete = true;
                    }
                }

                if($delete == true){
                    //delete token
                    $token->delete();
                }
            }
        }
    }
}