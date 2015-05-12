<?php

class Ebizmarts_SagePaySuite_Helper_Error extends Mage_Core_Helper_Abstract
{

    public function parseTransactionFailedError($message)
    {
        $newMessage = $message;

        //canceled by customer
        if(strpos($message,'2013') !== FALSE){
            $newMessage = 'Transaction canceled by customer.';
        }else{
            if(is_null($newMessage) || empty($newMessage)){
                $newMessage = 'An error occurred which prevented the order from saving. Please contact administration.';
            }else{
                $newMessage = 'An error occurred which prevented the order from saving: ' . $message;
            }
        }

        return $newMessage;
    }

}

