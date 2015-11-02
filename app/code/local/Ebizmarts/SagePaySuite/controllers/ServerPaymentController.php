<?php

/**
 * SERVER payment controller
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */
class Ebizmarts_SagePaySuite_ServerPaymentController extends Mage_Core_Controller_Front_Action {
    /*
     * Define end of line character used to correctly format response to Sage Pay Server
     * @access public
     */

    public $eoln = "\r\n";

    public function preDispatch() {
        $storeId = $this->getRequest()->getParam('storeid');
        if ($storeId) {
            Mage::app()->setCurrentStore((int) $storeId);
        }

        //Thanks to our partner agency de-facto.com for the feedback on this one.
        parent::preDispatch();
        Mage::getModel('sagepaysuite/api_payment')->getQuote();

        return $this;
    }

    /**
     * Load and order by its incremental ID attribute
     *
     * @access protected
     * @param $orderId integer The ID of the order
     * @return Order Object
     */
    protected function _loadOrderById($orderId) {
        return Mage :: getModel('sales/order')->loadByAttribute('entity_id', (int) $orderId);
    }

    public function getServerModel() {
        return Mage :: getModel('sagepaysuite/sagePayServer');
    }

    public function saveOrderAction() {
        $this->_expireAjax();

        $resultData = array();

        try {

            Mage::helper('sagepaysuite')->validateQuote();

            $result = $this->getServerModel()->registerTransaction($this->getRequest()->getPost());
            $resultData = $result->getData();

            if ($result->getResponseStatus() == Ebizmarts_SagePaySuite_Model_Api_Payment :: RESPONSE_CODE_APPROVED) {
                $redirectUrl = $result->getNextUrl();
                $resultData['success'] = true;
                $resultData['error'] = false;
            }
        } catch (Exception $e) {
            $resultData['response_status']        = 'ERROR';
            $resultData['response_status_detail'] = $e->getMessage();
            Mage::dispatchEvent('sagepay_payment_failed', array('quote' => Mage::getSingleton('sagepaysuite/api_payment')->getQuote(), 'message' => $e->getMessage()));
        }

        if (isset($redirectUrl)) {
            $resultData['redirect'] = $redirectUrl;
        }

        return $this->getResponse()->setBody(Zend_Json::encode($resultData));
    }

    public function getSPSModel() {
        return Mage :: getModel('sagepaysuite/sagePayServer');
    }

    protected function _expireAjax() {
        if (!Mage :: getSingleton('checkout/session')->getQuote()->hasItems()) {
            $this->getResponse()->setHeader('HTTP/1.1', '403 Session Expired')->setHeader('Login-Required', 'true')->sendResponse();
            die();
        }
    }

    protected function _setAdditioanlPaymentInfo($status) {
        $requestParams = $this->getRequest()->getParams();
        unset($requestParams['SID']);
        unset($requestParams['VPSProtocol']);
        unset($requestParams['TxType']);
        unset($requestParams['VPSSignature']);

        $requestParams['CustomStatusCode'] = $this->_getSagePayServerSession()->getTrnDoneStatus();
        $info = serialize($requestParams);

        $this->_getSagePayServerSession()->setTrnDoneStatus(null);

        return $info;
    }

    protected function _getAbortRedirectUrl() {
        $url = Mage :: getUrl('sgps/ServerPayment/abortredirect', array(
                    '_secure' => true,
                    '_current' => true,
                    '_store'   => $this->getRequest()->getParam('storeid', Mage::app()->getStore()->getId()),
                    'storeid'  => $this->getRequest()->getParam('storeid', Mage::app()->getStore()->getId()),
                ));

        return $url;
    }

    protected function _getSuccessRedirectUrl($params = array()) {

        $myParams = array_merge(array(
            '_secure'  => true,
            '_current' => true,
            '_store'   => $this->getRequest()->getParam('storeid', Mage::app()->getStore()->getId()),
            'storeid'  => $this->getRequest()->getParam('storeid', Mage::app()->getStore()->getId()),
        ), $params);

        $url = Mage :: getUrl('sgps/ServerPayment/success', $myParams);

        return $url;
    }

    protected function _getFailedRedirectUrl() {
        $url = Mage :: getUrl('sgps/ServerPayment/failure', array(
                    '_secure' => true,
                    '_current' => true,
                    '_store'   => $this->getRequest()->getParam('storeid', Mage::app()->getStore()->getId()),
                    'storeid'  => $this->getRequest()->getParam('storeid', Mage::app()->getStore()->getId()),
                ));

        return $url;
    }

    protected function _trn() {
        return Mage::getModel('sagepaysuite2/sagepaysuite_transaction')->loadByVendorTxCode($this->getRequest()->getParam('VendorTxCode'));
    }

    private function _returnOkAbort() {
        $strResponse = 'Status=OK' . $this->eoln;
        $strResponse .= 'StatusDetail=Transaction ABORTED successfully' . $this->eoln;
        $strResponse .= 'RedirectURL=' . $this->_getAbortRedirectUrl() . $this->eoln;

        $this->getResponse()->setHeader('Content-type', 'text/plain');
        $this->getResponse()->setBody($strResponse);

        Sage_Log::log($strResponse, null, 'SagePaySuite_SERVER_RESPONSE.log');

        return;
    }

    private function _returnOk($params = array()) {
        $strResponse = 'Status=OK' . $this->eoln;
        $strResponse .= 'StatusDetail=Transaction completed successfully' . $this->eoln;
        $strResponse .= 'RedirectURL=' . $this->_getSuccessRedirectUrl($params) . $this->eoln;

        $this->getResponse()->setHeader('Content-type', 'text/plain');
        $this->getResponse()->setBody($strResponse);

        Sage_Log::log($strResponse, null, 'SagePaySuite_SERVER_RESPONSE.log');

        return;
    }

    private function _returnInvalid($message = 'Unable to find the transaction in our database.') {
        $response = 'Status=INVALID' . $this->eoln;
        $response .= 'RedirectURL=' . $this->_getFailedRedirectUrl() . $this->eoln;
        $response .= 'StatusDetail=' . $message . $this->eoln;

        if (!$this->_getSagePayServerSession()->getFailStatus()) {
            $this->_getSagePayServerSession()->setFailStatus($message);
        }

        Sage_Log::log($message);
        Sage_Log::log($this->getRequest()->getPost());
        Sage_log::log($this->_getSagePayServerSession()->getData());

        Mage::dispatchEvent('sagepay_payment_failed', array('quote' => $this->getOnepage()->getQuote(), 'message' => $message));

        $this->getResponse()->setHeader('Content-type', 'text/plain');
        $this->getResponse()->setBody($response);

        Sage_Log::log($response, null, 'SagePaySuite_SERVER_RESPONSE.log');

        return;
    }

    protected function _getHRStatus($strStatus, $strStatusDetail) {
        if ($strStatus == 'OK')
            $strDBStatus = 'AUTHORISED - The transaction was successfully authorised with the bank.';
        elseif ($strStatus == 'NOTAUTHED'){
            if($strStatusDetail == "1003 : The transaction timed out."){
                $strDBStatus = 'ERROR - The transaction timed out.';
            }else{
                $strDBStatus = 'DECLINED - The transaction was not authorised by the bank.';
            }
        }
        elseif ($strStatus == 'ABORT')
            $strDBStatus = 'ABORTED - The customer clicked Cancel on the payment pages, or the transaction was timed out due to customer inactivity.';
        elseif ($strStatus == 'REJECTED')
            $strDBStatus = 'REJECTED - The transaction was failed by your 3D-Secure or AVS/CV2 rule-bases.';
        elseif ($strStatus == 'AUTHENTICATED')
            $strDBStatus = 'AUTHENTICATED - The transaction was successfully 3D-Secure Authenticated and can now be Authorised.';
        elseif ($strStatus == 'REGISTERED')
            $strDBStatus = 'REGISTERED - The transaction could not be 3D-Secure Authenticated, but has been registered to be Authorised.';
        elseif ($strStatus == 'ERROR')
            $strDBStatus = 'ERROR - There was an error during the payment process.  The error details are: ' . $strStatusDetail;
        elseif ($strStatus == 'PENDING')
            $strDBStatus = 'PENDING - Transaction pending';
        else
            $strDBStatus = 'UNKNOWN - An unknown status was returned from Sage Pay.  The Status was: ' . $strStatus . ', with StatusDetail:' . $strStatusDetail;

        return $strDBStatus;
    }

    /**
     * Create Token in database after receiving token confirmation from API.
     *
     * @param type $_transaction
     */
    protected function _saveToken($_transaction) {

        $result = $this->getRequest();

        //Saving TOKEN.
        if ($result->getPost('Token')) {
            $tokenData = array(
                'Token' => $result->getPost('Token'),
                'Status' => $result->getPost('Status'),
                'Vendor' => $_transaction->getVendorname(),
                'CardType' => $result->getPost('CardType'),
                'ExpiryDate' => $result->getPost('ExpiryDate'),
                'StatusDetail' => $result->getPost('StatusDetail'),
                'Nickname'      => $_transaction->getNickname(),
                'Protocol' => 'server',
                'CardNumber' => '00000000' . $result->getPost('Last4Digits')
            );
            Mage::getModel('sagepaysuite/sagePayToken')->persistCard($tokenData);
        }
    }

    public function notifyAction() {

        Sage_Log::log($_POST, null, 'SagePaySuite_POST_Requests.log');

        $request = $this->getRequest();
        $dbtrn   = $this->_trn();
        $quote = null;

//        if($dbtrn->getId() && $dbtrn->getData("server_notify_arrived") == true){
//            Sage_Log::log("Notified: Retry sleeping.", null, 'SagePaySuite_SERVER_RESPONSE.log');
//            $this->_returnOk();
//            return;
//        }

        //set euro payments status
        $euro_payment_status = ((string)$request->getPost("Status") == "PENDING" ? "PENDING" : (string)$dbtrn->getEuroPaymentsStatus());
        $returning_from_euro_payment = false;
        //if it was already 'PENDING' I update it
        if((string)$dbtrn->getStatus() == "PENDING"){
            $euro_payment_status = $request->getPost("Status");
            $returning_from_euro_payment = true;
        }
        //reset session flag
        $this->_getSagePayServerSession()->setEuroPaymentIsPending(false);

        $dbtrn->addData(Mage::helper('sagepaysuite')->arrayKeysToUnderscore($_POST))
                ->setPostcodeResult($this->getRequest()->getPost('PostCodeResult'))
                ->setData('cv2result', $this->getRequest()->getPost('CV2Result'))
                ->setThreedSecureStatus($this->getRequest()->getPost('3DSecureStatus'))
                ->setLastFourDigits($this->getRequest()->getPost('Last4Digits'))
                ->setRedFraudResponse($this->getRequest()->getPost('FraudResponse'))
                ->setBankAuthCode($this->getRequest()->getPost('BankAuthCode'))
                ->setDeclineCode($this->getRequest()->getPost('DeclineCode'))
                ->setEuroPaymentsStatus($euro_payment_status)
                //->setData("server_notify_arrived",true)
                ->save();

        //Sage_Log::log("Notified: Transaction updated.", null, 'SagePaySuite_SERVER_RESPONSE.log');

        //Handle ABORT
        $sageStatus = $request->getParam('Status');
        if ($sageStatus == 'ABORT') {

            Sage_Log::log("Notified: Aborted.", null, 'SagePaySuite_SERVER_RESPONSE.log');

            $this->_getSagePayServerSession()->setFailStatus($request->getParam('StatusDetail'));
            $dbtrn->setStatus($sageStatus)
                    ->setStatusDetail($request->getParam('StatusDetail'))
                    ->save();
            $this->_returnOkAbort();
            return;
        }

        if ($dbtrn->getId() && $dbtrn->getOrderId()) {

            //Sage_Log::log("Notified: Order already exists: " . $dbtrn->getOrderId(), null, 'SagePaySuite_SERVER_RESPONSE.log');

            if($returning_from_euro_payment == true){ //EURO Payment PENDING doing 2nd POST

                if($euro_payment_status == "OK"){
                    //invoice it
                    $order = Mage::getModel('sales/order')->load($dbtrn->getOrderId());
                    $invoiced = Mage::getModel('sagepaysuite/api_payment')->invoiceOrder($order);
                    if($invoiced == true){
                        $dbtrn->setStatusDetail($request->getParam('StatusDetail'))->save();
                        $order->setStatus("processing")->save();
                    }

                }else{
                    //transaction was failed, cancel it
                    Mage::helper('sagepaysuite')->cancelTransaction($dbtrn);
                    Sage_Log::log("Transaction " . $dbtrn->getVendorTxCode() . " cancelled due to error " . $request->getParam('StatusDetail'), '', '');
                }
                $this->_returnOk();
                return;
            }
            else if($euro_payment_status !== null){
                //general retry

                //Mage::getSingleton('core/session')->addError("Operation timed-out. Please contact us to make sure your order has been correctly taken.");

                //do nothing for now
                $this->_returnOk();
                return;
            }
            else{ //OLD RETRY (it's not suppose to enter here)

                //Sage_Log::log("Notified: Retry OK.", null, 'SagePaySuite_SERVER_RESPONSE.log');

                $dbtrn->setStatusDetail("Sage Pay Retry. " . $dbtrn->getStatusDetail())->save();
                $this->_returnOk();
                return;
            }
        }

        //Check cart health on callback.
        if(1 === (int)Mage::getStoreConfig('payment/sagepaysuite/verify_cart_consistency')) {
            if(Mage::helper('sagepaysuite/checkout')->cartExpire($this->getOnepage()->getQuote())) {

                try {

                    Mage::helper('sagepaysuite')->voidTransaction($dbtrn->getVendorTxCode(), 'sagepayserver');

                    Sage_Log::log("Transaction " . $dbtrn->getVendorTxCode() . " cancelled, cart was modified while customer on payment pages.", Zend_Log::CRIT, 'SagePaySuite_POST_Requests.log');

                }catch(Exception $ex) {
                    Sage_Log::log("Transaction " . $dbtrn->getVendorTxCode() . " could not be cancelled and order was not created, cart was modified while customer on payment pages.", Zend_Log::CRIT, 'SagePaySuite_POST_Requests.log');
                }

                $this->_returnInvalid('Your order could not be completed, please try again. Thanks.');
                return;

            }
        }
        //Check cart health on callback.

        $sagePayServerSession = $this->_getSagePayServerSession();

        $strVendorName = strtolower($this->getSPSModel()->getConfigData('vendor'));
        $strStatus       = $request->getParam('Status', '');
        $strVendorTxCode = $request->getParam('VendorTxCode', '');
        $strVPSTxId      = $request->getParam('VPSTxId', '');
        $strSecurityKey = '';
        if ($dbtrn->getVendorTxCode() == $strVendorTxCode && $dbtrn->getVpsTxId() == $strVPSTxId) {
            $strSecurityKey = $dbtrn->getSecurityKey();
            $sagePayServerSession->setVpsTxId($strVPSTxId);
        }
        $response = '';


        if (strlen($strSecurityKey) == 0) { //check security key invalid

            Sage_Log::log("Security Key invalid", null, 'SagePaySuite_POST_Requests.log');
            Sage_Log::log("TRN from DB:", null, 'SagePaySuite_POST_Requests.log');
            Sage_Log::log($dbtrn->toArray(), null, 'SagePaySuite_POST_Requests.log');

            $dbtrn->setStatus('MAGE_ERROR')
                    ->setStatusDetail("Security Key invalid. " . $dbtrn->getStatusDetail())
                    ->save();

            $this->_returnInvalid('Security Key invalid');
        }
        else {

            $strStatusDetail = $strTxAuthNo = $strAVSCV2 = $strAddressResult = $strPostCodeResult = $strCV2Result = $strGiftAid = $str3DSecureStatus = $strCAVV = $strAddressStatus = $strPayerStatus = $strCardType = $strPayerStatus = $strLast4Digits = $strMySignature = '';

            $strVPSSignature = $request->getParam('VPSSignature', '');
            $strStatusDetail = $request->getParam('StatusDetail', '');

            if (strlen($request->getParam('TxAuthNo', '')) > 0) {
                $strTxAuthNo = $request->getParam('TxAuthNo', '');

                $sagePayServerSession->setTxAuthNo($strTxAuthNo);
            }

            $strAVSCV2 = $request->getParam('AVSCV2', '');
            $strAddressResult = $request->getParam('AddressResult', '');
            $strPostCodeResult = $request->getParam('PostCodeResult', '');
            $strCV2Result = $request->getParam('CV2Result', '');
            $strGiftAid = $request->getParam('GiftAid', '');
            $str3DSecureStatus = $request->getParam('3DSecureStatus', '');
            $strCAVV = $request->getParam('CAVV', '');
            $strAddressStatus = $request->getParam('AddressStatus', '');
            $strPayerStatus = $request->getParam('PayerStatus', '');
            $strCardType = $request->getParam('CardType', '');
            $strLast4Digits = $request->getParam('Last4Digits', '');
            $strDeclineCode = $request->getParam('DeclineCode', '');
            $strExpiryDate = $request->getParam('ExpiryDate', '');
            $strFraudResponse = $request->getParam('FraudResponse', '');
            $strBankAuthCode = $request->getParam('BankAuthCode', '');

            $strMessage = $strVPSTxId . $strVendorTxCode . $strStatus . $strTxAuthNo . $strVendorName . $strAVSCV2 . $strSecurityKey
                          . $strAddressResult . $strPostCodeResult . $strCV2Result . $strGiftAid . $str3DSecureStatus . $strCAVV
                          . $strAddressStatus . $strPayerStatus . $strCardType . $strLast4Digits . $strDeclineCode
                          . $strExpiryDate . $strFraudResponse . $strBankAuthCode;

            $strMySignature = strtoupper(md5($strMessage));

            $response = '';

            /** We can now compare our MD5 Hash signature with that from Sage Pay Server * */
            $validSignature = ($strMySignature !== $strVPSSignature);

            if ($validSignature) { //check signature

                Sage_Log::log("Cannot match the MD5 Hash", null, 'SagePaySuite_POST_Requests.log');
                Sage_Log::log("My Message: $strMessage", null, 'SagePaySuite_POST_Requests.log');
                Sage_Log::log("My Signature: $strMySignature", null, 'SagePaySuite_POST_Requests.log');
                Sage_Log::log("VPS Signature: $strVPSSignature", null, 'SagePaySuite_POST_Requests.log');
                Sage_Log::log("TRN from DB:", null, 'SagePaySuite_POST_Requests.log');
                Sage_Log::log($dbtrn->toArray(), null, 'SagePaySuite_POST_Requests.log');

                $dbtrn->setStatus('MAGE_ERROR')
                        ->setStatusDetail("Cannot match the MD5 Hash. " . $dbtrn->getStatusDetail())
                        ->save();

                $this->_returnInvalid('Cannot match the MD5 Hash. Order might be tampered with. ' . $strStatusDetail);
                return;

            }
            else {

                $strDBStatus = $this->_getHRStatus($strStatus, $strStatusDetail);

                if ($strStatus == 'OK' || $strStatus == 'AUTHENTICATED' || $strStatus == 'REGISTERED') {

                    //Sage_Log::log("Notified: Transaction OK.", null, 'SagePaySuite_SERVER_RESPONSE.log');

                    try {

                        $sagePayServerSession->setTrnhData($this->_setAdditioanlPaymentInfo($strDBStatus));

                        $checkout_session = Mage::getSingleton('checkout/session');
                        if ($checkout_session->getSagePayRewInst()) {
                            $this->getOnepage()->getQuote()
                                    ->setUseRewardPoints(1)
                                    ->setRewardInstance($checkout_session->getSagePayRewInst());
                        }

                        if ($checkout_session->getSagePayCustBalanceInst()) {
                            $this->getOnepage()->getQuote()
                                    ->setUseCustomerBalance(1)
                                    ->setCustomerBalanceInstance($checkout_session->getSagePayCustBalanceInst());
                        }

                        if ((string) $request->getParam('Status') == 'OK' && (string) $request->getParam('TxType') == 'PAYMENT') {
                            $this->_getSagePayServerSession()->setInvoicePayment(true);
                            Mage::register('sagepay_create_invoice', 1, true);//For Magento 1.9+ when customer is Checkout=Register
                        }

                        $sageserverpost = new Varien_Object($_POST);
                        Mage::register('sageserverpost', $sageserverpost);

                        //1.9.1 ssl fix
                        $customer_id = null;
                        $quote = $this->getOnepage()->getQuote();
                        if($quote->getId() == null){
                            $rqQuoteId = Mage::app()->getRequest()->getParam('qid');
                            $quote = Mage::getModel('sales/quote')->loadActive($rqQuoteId);
                            $this->getOnepage()->setQuote($quote);
                            Mage::app()->getStore()->setCurrentCurrencyCode($quote->getQuoteCurrencyCode()); //Thanks to Ross Kinsman for his input on this.
                            //Sage_Log::log("Notified: Checkout quote reloaded with id: " . $rqQuoteId, null, 'SagePaySuite_SERVER_RESPONSE.log');
                            $customer_id = $this->getOnepage()->getQuote()->getData('customer_id');
                            if(!is_null($customer_id)){
                                $customer = Mage::getModel('customer/customer')->load($customer_id);
                                if(!is_null($customer)){
                                    Mage::getSingleton('customer/session')->setCustomerAsLoggedIn($customer);
                                    //Sage_Log::log("Notified: Customer logged-in", null, 'SagePaySuite_SERVER_RESPONSE.log');
                                }
                            }

                        }
                        //sweet tooth fix
                        if(class_exists('rewards/session', FALSE)){
                            Mage::getSingleton('rewards/session')->getQuote()->setData('checkout_method',$this->getOnepage()->getQuote()->getData('checkout_method'));
                        }

                        //Saving TOKEN.
                        $this->_saveToken($dbtrn);

                        //saving order
                        $sOrder = $this->_saveMagentoOrder();

                        if ($sOrder !== true) {

                            //Sage_Log::log("Notified: Order NOT saved.", null, 'SagePaySuite_SERVER_RESPONSE.log');

                            $sagePayServerSession->setFailStatus('An error occurred: ' . $sOrder);

                            /** The status indicates a failure of one state or another, so send the customer to orderFailed instead * */
                            $strRedirectPage = $this->_getFailedRedirectUrl();

                            //Mage::helper('sagepaysuite')->cancelTransaction($dbtrn);

                            $dbtrn->setStatus('MAGE_ERROR')
                                    ->setStatusDetail('Could not save order: ' . $sOrder . $dbtrn->getStatusDetail())
                                    ->save();

                            $this->_returnInvalid('Could not save order: ' . $sOrder);
                            return;

                        }
                        else {

                            //Sage_Log::log("Notified: Order saved.", null, 'SagePaySuite_SERVER_RESPONSE.log');

                            $orderId = Mage::registry('last_order_id');
                            $msOrderIds = $this->_getMsOrderIds();
                            if ($orderId || $msOrderIds) {

                                if (false !== $msOrderIds) {
                                    $aidis = array_keys($msOrderIds);
                                    $orderId = $aidis[0];
                                    #Mage::register('ms_parent_trn_id', $dbtrn->getId());
                                    $dbtrn->setOrderId($aidis[0])->save();

                                    unset($aidis[0]);
                                    $trns = Mage::getModel('sagepaysuite2/sagepaysuite_transaction')
                                                    ->getCollection()
                                                    ->getChilds($dbtrn->getId())
                                                    ->load()->toArray();
                                    foreach ($aidis as $_order) {
                                        foreach ($trns['items'] as $ka => $_t) {
                                            Mage::getModel('sagepaysuite2/sagepaysuite_transaction')
                                                    ->load($_t['id'])->setOrderId($_order)->save();
                                            unset($trns['items'][$ka]);
                                            break;
                                        }
                                    }
                                }
                            }

                            $dbtrn->setOrderId($orderId)->save();
                            $sagePayServerSession->setSuccessStatus($strDBStatus);
                        }

                        $order = Mage::getModel('sales/order')->load($orderId);
                        $isRegister      = ($quote->getData('checkout_method') == 'register');

                        if($isRegister){
                            //sweet tooth fix
                            if(Mage::registry('rewards_createPointsTransfers_run')){
                                Mage::unregister('rewards_createPointsTransfers_run');
                                Mage::dispatchEvent('sales_order_save_commit_after', array('order'=>$order));
                            }
                        }

                        //save server session data on db as it gets lost sometimes
                        $server_session = array();
                        $messages = Mage::getSingleton('core/session')->getMessages();
                        $successes = $messages->getItemsByType("success");
                        $errors = $messages->getItemsByType("error");
                        if(!is_null($successes) && count($successes) > 0){
                            $server_session["core_messages"] = array();
                            $server_session["core_messages"]["success"] = array();
                            foreach($successes as $msg){
                                $server_session["core_messages"]["success"][] = $msg->getCode();
                            }
                        }
                        if(!is_null($errors) && count($errors) > 0){
                            if(!array_key_exists("core_messages",$server_session)){
                                $server_session["core_messages"] = array();
                            }
                            $server_session["core_messages"]["error"] = array();
                            foreach($errors as $msg){
                                $server_session["core_messages"]["error"][] = $msg->getCode();
                            }
                        }
                        if(count(array_keys($server_session)) > 0){
                            try {
                                $server_session_json = json_encode($server_session);
                                $dbtrn->setData("server_session",$server_session_json)->save();
                            }catch (Exception $e){
                                //unable to save server session data for later :/
                            }
                        }

                        Mage::getSingleton('checkout/session')->setSagePayRewInst(null)->setSagePayCustBalanceInst(null);
                        if($quote->getId()) {

                            //Sage_Log::log("Notified: Return OK with quote.", null, 'SagePaySuite_SERVER_RESPONSE.log');

                            $get_data_to_send = array('inv' => (int)Mage::registry('sagepay_create_invoice'),
                                'cusid' => is_null($customer_id) ? $quote->getData('customer_id') : $customer_id,
                                'qide' => $quote->getId(),
                                'incide' => $order->getIncrementId(),
                                'oide' => $order->getId());

                            if(!empty($msOrderIds)){
                                $id_strings = "";
                                foreach ($msOrderIds as $key => $value) {
                                    if($id_strings != ''){
                                        $id_strings .= ",";
                                    }
                                    $id_strings .= $key . ":" . $value;
                                }
                                $get_data_to_send["msorderids"] = $id_strings;
                            }

                            $this->_returnOk($get_data_to_send);

                        }
                        else {

                            //Sage_Log::log("Notified: Return OK.", null, 'SagePaySuite_SERVER_RESPONSE.log');

                            $this->_returnOk();
                        }

                        return;
                    } catch (Exception $e) {

                        //Sage_Log::log("Notified: Catched Error.", null, 'SagePaySuite_SERVER_RESPONSE.log');

                        $dbtrn->setStatus('MAGE_ERROR')
                                ->setStatusDetail($e->getMessage() . $dbtrn->getStatusDetail())
                                ->save();
                        Mage::logException($e);
                        Mage::log($e->getMessage());

                        Mage::dispatchEvent('sagepay_payment_failed', array('quote' => $this->getOnepage()->getQuote(), 'message' => $e->getMessage()));

                        $this->_returnOkAbort();
                    }
                }
                else if($strStatus == 'PENDING'){ //handle EURO payments

                    //set flag
                    $this->_getSagePayServerSession()->setEuroPaymentIsPending(true);

                    try {

                        $sagePayServerSession->setTrnhData($this->_setAdditioanlPaymentInfo($strDBStatus));

                        $checkout_session = Mage::getSingleton('checkout/session');
                        if ($checkout_session->getSagePayRewInst()) {
                            $this->getOnepage()->getQuote()
                                ->setUseRewardPoints(1)
                                ->setRewardInstance($checkout_session->getSagePayRewInst());
                        }

                        if ($checkout_session->getSagePayCustBalanceInst()) {
                            $this->getOnepage()->getQuote()
                                ->setUseCustomerBalance(1)
                                ->setCustomerBalanceInstance($checkout_session->getSagePayCustBalanceInst());
                        }

                        //no invoice since it's PENDING
                        $this->_getSagePayServerSession()->setInvoicePayment(false);
                        Mage::register('sagepay_create_invoice', 0, true);

                        $sageserverpost = new Varien_Object($_POST);
                        Mage::register('sageserverpost', $sageserverpost);

                        //1.9.1 ssl fix
                        $customer_id = null;
                        if($this->getOnepage()->getQuote()->getId() == null){
                            $rqQuoteId = Mage::app()->getRequest()->getParam('qid');
                            $quote = Mage::getModel('sales/quote')->loadActive($rqQuoteId);
                            $this->getOnepage()->setQuote($quote);
                            Mage::app()->getStore()->setCurrentCurrencyCode($quote->getQuoteCurrencyCode()); //Thanks to Ross Kinsman for his input on this.
                            $customer_id = $this->getOnepage()->getQuote()->getData('customer_id');
                            if(!is_null($customer_id)){
                                $customer = Mage::getModel('customer/customer')->load($customer_id);
                                if(!is_null($customer)){
                                    Mage::getSingleton('customer/session')->setCustomerAsLoggedIn($customer);
                                }
                            }
                        }

                        //Saving TOKEN.
                        $this->_saveToken($dbtrn);

                        //save order
                        $sOrder = $this->_saveMagentoOrder();

                        if ($sOrder !== true) {

                            $sagePayServerSession->setFailStatus('An error occurred: ' . $sOrder);

                            /** The status indicates a failure of one state or another, so send the customer to orderFailed instead * */
                            $strRedirectPage = $this->_getFailedRedirectUrl();

                            //Mage::helper('sagepaysuite')->cancelTransaction($dbtrn);

                            $dbtrn->setStatus('MAGE_ERROR')
                                ->setStatusDetail('Could not save order: ' . $sOrder . $dbtrn->getStatusDetail())
                                ->save();

                            $this->_returnInvalid('Could not save order: ' . $sOrder);
                            return;

                        }
                        else {

                            $orderId = Mage::registry('last_order_id');
                            $msOrderIds = $this->_getMsOrderIds();
                            if ($orderId || $msOrderIds) {

                                if (false !== $msOrderIds) {
                                    $aidis = array_keys($msOrderIds);
                                    $orderId = $aidis[0];
                                    #Mage::register('ms_parent_trn_id', $dbtrn->getId());
                                    $dbtrn->setOrderId($aidis[0])->save();

                                    unset($aidis[0]);
                                    $trns = Mage::getModel('sagepaysuite2/sagepaysuite_transaction')
                                        ->getCollection()
                                        ->getChilds($dbtrn->getId())
                                        ->load()->toArray();
                                    foreach ($aidis as $_order) {
                                        foreach ($trns['items'] as $ka => $_t) {
                                            Mage::getModel('sagepaysuite2/sagepaysuite_transaction')
                                                ->load($_t['id'])->setOrderId($_order)->save();
                                            unset($trns['items'][$ka]);
                                            break;
                                        }
                                    }
                                }
                            }

                            $dbtrn
                                ->setOrderId($orderId)
                                ->save();

                            //set PENDING status
                            //$order = Mage::getModel('sales/order')->load($orderId);
                            //$order->setStatus("pending")->save();

                            $sagePayServerSession->setSuccessStatus($strDBStatus);
                        }

                        Mage::getSingleton('checkout/session')->setSagePayRewInst(null)->setSagePayCustBalanceInst(null);

                        if(Mage::registry('sagepay_last_quote_id')) {
                            $this->_returnOk(array('inv' => (int)Mage::registry('sagepay_create_invoice'),
                                'cusid' => is_null($customer_id) ? Mage::registry('sagepay_customer_id') : $customer_id,
                                'qide' => Mage::registry('sagepay_last_quote_id'),
                                'incide' => Mage::registry('sagepay_last_real_order_id'),
                                'oide' => Mage::registry('sagepay_last_order_id')));
                        }
                        else {
                            $this->_returnOk();
                        }

                        return;
                    } catch (Exception $e) {

                        $dbtrn->setStatus('MAGE_ERROR')
                            ->setStatusDetail($e->getMessage() . $dbtrn->getStatusDetail())
                            ->save();
                        Mage::logException($e);
                        Mage::log($e->getMessage());

                        Mage::dispatchEvent('sagepay_payment_failed', array('quote' => $this->getOnepage()->getQuote(), 'message' => $e->getMessage()));
                    }

                }
                else {

                    //Mage::helper('sagepaysuite')->cancelTransaction($this->_trn());

                    $dbtrn->setStatus('MAGE_ERROR')
                            ->setStatusDetail($strDBStatus . $dbtrn->getStatusDetail())
                            ->save();

                    $sagePayServerSession->setFailStatus($strDBStatus);
                    /** The status indicates a failure of one state or another, so send the customer to orderFailed instead * */
                    $this->_returnInvalid($strDBStatus);
                    return;
                }
            }
        }
    }

    public function redirectAction() {
        $this->getResponse()->setBody($this->getLayout()->createBlock('sagepaysuite/checkout_servercallback')->toHtml());
    }

    public function failedAction() {
        $this->getResponse()->setBody($this->getLayout()->createBlock('sagepaysuite/checkout_serverfail')->toHtml());
    }

    public function failureAction() {
        $this->getResponse()->setBody($this->getLayout()->createBlock('sagepaysuite/checkout_serverfail')->toHtml());
    }

    public function successAction() {
        Mage::helper('sagepaysuite')->deleteQuote();
        $this->getResponse()->setBody($this->getLayout()->createBlock('sagepaysuite/checkout_serversuccess')->setMultiShipping($this->_isMultishippingCheckout())->toHtml());
    }

    public function abortredirectAction() {
        $this->_getSagePayServerSession()->setLastSavedTokenccid(null);
        $this->getResponse()->setBody($this->getLayout()->createBlock('sagepaysuite/checkout_serverfail')->toHtml());
    }

    protected function _getSagePayServerSession() {
        return Mage::getSingleton('sagepaysuite/session');
    }

    public function getOnepage() {
        return Mage :: getSingleton('checkout/type_onepage');
    }

    /**
     * Retrieve checkout model
     *
     * @return Mage_Checkout_Model_Type_Multishipping
     */
    public function getMultishipping() {
        return Mage :: getSingleton('checkout/type_multishipping');
    }

    protected function _getMsOrderIds() {
        $ids = Mage::getSingleton('core/session')->getOrderIds();
        if ($ids && is_array($ids)) {
            return $ids;
        }
        return false;
    }

    /**
     * Check if current quote is multishipping
     */
    protected function _isMultishippingCheckout() {
        return (bool) Mage :: getSingleton('checkout/session')->getQuote()->getIsMultiShipping();
    }

    protected function _getMsState() {
        return Mage :: getSingleton('checkout/type_multishipping_state');
    }

    private function _saveMagentoOrder() {
        try {

            if ($this->_isMultishippingCheckout()) {

                $this->getOnepage()->getQuote()->collectTotals();

                $this->getMultishipping()->createOrders();
                $this->_getMsState()->setActiveStep(
                        Mage_Checkout_Model_Type_Multishipping_State::STEP_SUCCESS
                );
                $this->_getMsState()->setCompleteStep(
                        Mage_Checkout_Model_Type_Multishipping_State::STEP_OVERVIEW
                );
                $this->getMultishipping()->getCheckoutSession()->clear();
                $this->getMultishipping()->getCheckoutSession()->setDisplaySuccess(true);

                return true;
            }
            else {

                $this->getOnepage()->getQuote()->collectTotals();

                Mage::helper('sagepaysuite')->ignoreAddressValidation($this->getOnepage()->getQuote());

                $this->getOnepage()->saveOrder();

                Mage::register('last_order_id', Mage::getSingleton('checkout/session')->getLastOrderId());

                Mage::helper('sagepaysuite')->deleteQuote();

                return true;
            }
        } catch (Exception $e) {

            //Mage::helper('sagepaysuite')->cancelTransaction($this->_trn());

            Mage::log($e->getMessage());
            Mage::logException($e);

            Mage::dispatchEvent('sagepay_payment_failed', array('quote' => $this->getOnepage()->getQuote(), 'message' => $e->getMessage()));

            $this->getOnepage()->getQuote()->save();

            $message = "MAGENTO ERROR: " . $e->getMessage() . ". ";
            $trn = $this->_trn();
            $trn
            ->setStatus('MAGE_ERROR')
            ->setStatusDetail($message . $trn->getStatusDetail())
            ->save();

            return $e->getMessage();
        }
    }

}
