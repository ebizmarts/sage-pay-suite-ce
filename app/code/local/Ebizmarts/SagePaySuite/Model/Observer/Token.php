<?php
/**
 * Token event observer
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */
class Ebizmarts_SagePaySuite_Model_Observer_Token extends Ebizmarts_SagePaySuite_Model_Observer
{

    /**
     * Register new token card for customer before new transaction
     * @see Ebizmarts_SagePaySuite_Model_SagePayDirectPro::_postRequest
     */
    public function registerOnPayment($o)
    {
    	$request = $o->getEvent()->getRequest();

        $customerSession = Mage::helper('customer')->getCustomer();

        $customerId = (int)$customerSession->getId();

        Ebizmarts_SagePaySuite_Log::w($customerId, null, 'Token.log');

        if(!$customerId){
        	return $o;
        }

        $_data = $request->getData();
        $_pdata = array(
                        'Vendor'=>'', 'Currency'=>'', 'CardHolder'=>'', 'CardType'=>'', 'CardNumber'=>'',
                        'StartYear'=>'', 'StartMonth'=>'', 'ExpiryMonth'=>'', 'ExpiryYear'=>'', 'CV2'=>'',
                        'IssueNumber'=>'', 'ExpiryDate'=>'', 'StartDate'=>'',
                       );

        $rs = Mage::getModel('sagepaysuite/sagePayToken')->registerCard(array_intersect_key($_data, $_pdata));

        if(empty($rs)){
            return $o;
        }

        Ebizmarts_SagePaySuite_Log::w($_data, null, 'SagePayToken.log');
        Ebizmarts_SagePaySuite_Log::w($rs, null, 'SagePayToken.log');

        if($rs['Status'] == 'OK'){
         $save = Mage::getModel('sagepaysuite2/sagepaysuite_tokencard')
            ->setToken($rs['Token'])
            ->setStatus($rs['Status'])
            ->setCardType($_data['CardType'])
            ->setExpiryDate($_data['ExpiryDate'])
            ->setStatusDetail($rs['StatusDetail'])
            ->setProtocol('direct')
            ->setCustomerId($customerId)
            ->setLastFour(substr($_data['CardNumber'], -4))
            ->save();
        }else{
            Ebizmarts_SagePaySuite_Log::w($rs, null, 'SagePayToken_Errors.log');

            #$customerSession->addError(Mage::helper('sagepaysuite')->__('Could not save credit card token: %s', $rs['StatusDetail']));

        }
    }

}