<?php

class Ebizmarts_SagePayReporting_Helper_Error extends Mage_Core_Helper_Abstract
{

    public function parseError($message, $param1)
    {
        $newMessage = $message;
        //Mage::log($message);

        //invalid api details errors
        if(strpos($message,'A valid &lt;vendor&gt; value is required') !== FALSE){
            $newMessage = 'Unable to connect with SagePay API: Incorrect vendor: "' . $param1 . '"  (This is configured in: Sage Pay Suite [Backend - Reporting & Third Man API Integration])';
        }elseif(strpos($message,'A valid &lt;username&gt; value is required') !== FALSE){
            $newMessage = 'Unable to connect with SagePay API: Incorrect API username (This is configured in: Sage Pay Suite [Backend - Reporting & Third Man API Integration])';
        }elseif(strpos($message,'The specified &lt;vendor&gt; and &lt;user&gt; combination is not valid') !== FALSE){
            $newMessage = 'Unable to connect with SagePay API: Incorrect API user/password or the API user might be locked out (This is configured in: Sage Pay Suite [Backend - Reporting & Third Man API Integration])';
        }elseif(strpos($message,'Unable to find a valid IP for this transaction') !== FALSE){
            $newMessage = 'Transaction not found in SagePay for vendor "' . $param1 . '"';
        }

        return $newMessage;
    }

}

