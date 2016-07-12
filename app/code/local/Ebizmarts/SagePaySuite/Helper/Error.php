<?php

class Ebizmarts_SagePaySuite_Helper_Error extends Mage_Core_Helper_Abstract
{

    public function parseTransactionFailedError($message)
    {
        $newMessage = $message;
        //canceled by customer
        if(strpos($message,'2013') !== FALSE){
            $newMessage = Mage::helper('sagepaysuite')->__('Transaction cancelled by customer.');
        }else{
            if(is_null($newMessage) || empty($newMessage)){
                $newMessage = Mage::helper('sagepaysuite')->__('An error occurred which prevented the order from saving. Please contact administration.');
            }else{
                $newMessage = Mage::helper('sagepaysuite')->__('An error occurred which prevented the order from saving: %s', $message);
            }
        }
        return $newMessage;
    }

}

