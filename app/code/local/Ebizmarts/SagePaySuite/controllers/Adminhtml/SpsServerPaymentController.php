<?php

/**
 * SERVER payment controller
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */
class Ebizmarts_SagePaySuite_Adminhtml_SpsServerPaymentController extends Mage_Adminhtml_Controller_Action {
    /*
     * Define end of line character used to correctly format response to Sage Pay Server
     * @access public
     */

    public $eoln = "\r\n";

    /**
     * Public actions defined so no KEY param is requested when posting back from Sage Pay
     */
    protected $_publicActions = array('notifyAdminOrder', 'notify');

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
        return Mage :: getModel('sagepaysuite/sagePayServerMoto');
    }

    public function saveOrderAction() {
        $this->_expireAjax();

        $resultData = array();

        try {

            $result = $this->getServerModel()->registerTransaction($this->getRequest()->getPost());
            $resultData = $result->getData();

            if ($result->getResponseStatus() == Ebizmarts_SagePaySuite_Model_Api_Payment :: RESPONSE_CODE_APPROVED) {
                $redirectUrl = $result->getNextUrl();
                $resultData['success'] = true;
                $resultData['error'] = false;
            }
        } catch (Exception $e) {
            $resultData['response_status'] = 'ERROR';
            $resultData['response_status_detail'] = $e->getMessage();
        }

        if (isset($redirectUrl)) {
            $resultData['redirect'] = $redirectUrl;
        }

        return $this->getResponse()->setBody(Zend_Json :: encode($resultData));
    }

    public function getSPSModel() {
        return Mage :: getModel('sagepaysuite/sagePayServerMoto');
    }

    protected function _expireAjax() {
        if (!Mage :: getSingleton('checkout/session')->getQuote()->hasItems()) {
            $this->getResponse()->setHeader('HTTP/1.1', '403 Session Expired')->setHeader('Login-Required', 'true')->sendResponse();
            die();
        }
    }

    public function registertrnAction() {

        $post = $this->getRequest()->getPost();

        if (array_key_exists('account', $post['order'])) {
            if (array_key_exists('group_id', $post['order']['account'])) {
                $this->_getSagePayServerSession()->setGroupId($post['order']['account']['group_id']);
            }
            if (array_key_exists('email', $post['order']['account'])) {
                $this->_getSagePayServerSession()->setEmail(urlencode($post['order']['account']['email']));
            }
        }

        try {
            
            /**
             * Saving of giftmessages
             */
            $giftmessages = $this->getRequest()->getPost('giftmessage');
            if ($giftmessages) {
                Mage::getSingleton('adminhtml/giftmessage_save')
                     ->setGiftmessages($giftmessages)
                     ->saveAllInQuote();
            }            
            
            $result = $this->getSPSModel()->registerTransaction($post);
            $resultData = $result->getData();
        } catch (Exception $e) {
            $resultData = array();
            $resultData['response_status']        = 'ERROR';
            $resultData['response_status_detail'] = $e->getMessage();
        }

        $response = json_encode($resultData);
        return $this->getResponse()->setBody($response);
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

    /**
     * Check whetever this is an Admin transaction or not
     */
    public function ia() {
        return (Mage :: getSingleton('admin/session')->isLoggedIn() && Mage :: getSingleton('adminhtml/session_quote')->getQuote()->hasItems());
    }

    protected function _getSuccessRedirectUrl() {

        $url = Mage :: getModel('adminhtml/url')->getUrl('adminhtml/spsServerPayment/success', array(
            '_secure' => true,
                ));

        return $url;
    }

    protected function _getFailedRedirectUrl() {

        $url = Mage :: getModel('adminhtml/url')->getUrl('adminhtml/spsServerPayment/failed', array(
            '_secure' => true,
                ));

        return $url;
    }

    protected function _trn() {
        return Mage :: getModel('sagepaysuite2/sagepaysuite_transaction')->loadByVendorTxCode($this->getRequest()->getParam('VendorTxCode'));
    }

    private function _returnOk() {
        header('Content-type: text/plain');
        $strResponse = 'Status=OK' . $this->eoln;
        $strResponse .= 'StatusDetail=Transaction completed successfully' . $this->eoln;
        $strResponse .= 'RedirectURL=' . $this->_getSuccessRedirectUrl() . '?SID=' . $this->getRequest()->getParam('SID', '') . $this->eoln;

        Sage_Log::log("[MOTO] " . $strResponse, null, 'SagePaySuite_SERVER_RESPONSE.log');

        echo $strResponse;
        exit;
    }

    private function _returnInvalid($message = 'Unable to find the transaction in our database.') {


        header('Content-type: text/plain');
        $response = 'Status=INVALID' . $this->eoln;
        $response .= 'RedirectURL=' . $this->_getFailedRedirectUrl() . '?SID=' . $this->getRequest()->getParam('SID', '') . $this->eoln;
        $response .= 'StatusDetail=' . $message . $this->eoln;

        Sage_Log::log($message);
        Sage_Log::log($this->getRequest()->getPost());
        Sage_log::log($this->_getSagePayServerSession()->getData());

        Sage_Log::log("[MOTO] " . $response, null, 'SagePaySuite_SERVER_RESPONSE.log');

        echo $response;
        exit;
    }

    protected function _getHRStatus($strStatus, $strStatusDetail) {
        if ($strStatus == 'OK')
            $strDBStatus = 'AUTHORISED - The transaction was successfully authorised with the bank.';
        elseif ($strStatus == 'NOTAUTHED')
            $strDBStatus = 'DECLINED - The transaction was not authorised by the bank.';
        elseif ($strStatus == 'ABORT')
            $strDBStatus = 'ABORTED - The customer clicked Cancel on the payment pages, or the transaction was timed out due to customer inactivity.';
        elseif ($strStatus == 'REJECTED')
            $strDBStatus = 'REJECTED - The transaction was failed by your 3D-Secure or AVS/CV2 rule-bases.';
        elseif ($strStatus == 'AUTHENTICATED')
            $strDBStatus = 'AUTHENTICATED - The transaction was successfully 3D-Secure Authenticated and can now be Authorised.';
        elseif ($strStatus == 'REGISTERED')
            $strDBStatus = 'REGISTERED - The transaction was could not be 3D-Secure Authenticated, but has been registered to be Authorised.';
        elseif ($strStatus == 'ERROR')
            $strDBStatus = 'ERROR - There was an error during the payment process.  The error details are: ' . $strStatusDetail;
        else
            $strDBStatus = 'UNKNOWN - An unknown status was returned from Sage Pay.  The Status was: ' . $strStatus . ', with StatusDetail:' . $strStatusDetail;

        return $strDBStatus;
    }

    protected function _getCheckFile() {
        return Mage :: getBaseDir('var') . '/tmp/' . $this->getRequest()->getParam('VendorTxCode');
    }

    public function notifyAction() {

        Sage_Log::log($_POST, null, 'SagePaySuite_POST_Requests.log');

        //try {

        if (!file_exists(Mage :: getBaseDir('var') . '/tmp')) {
            mkdir(Mage :: getBaseDir('var') . '/tmp');
        }

        $dbtrn = $this->_trn();
        if ($dbtrn->getId() && file_exists($this->_getCheckFile())) {
            $this->_returnOk();
        }

        $request = $this->getRequest();

        $sagePayServerSession = $this->_getSagePayServerSession();

        $strVendorName = $this->getSPSModel()->getConfigData('vendor');

        $strStatus = $request->getParam('Status', '');
        $strVendorTxCode = $request->getParam('VendorTxCode', '');
        $strVPSTxId = $request->getParam('VPSTxId', '');

        $strSecurityKey = '';
        if ($sagePayServerSession->getVendorTxCode() == $strVendorTxCode && $sagePayServerSession->getVpsTxId() == $strVPSTxId) {
            $strSecurityKey = $sagePayServerSession->getSecurityKey();
            $sagePayServerSession->setVpsTxId($strVPSTxId);
        }
        $response = '';
        if (strlen($strSecurityKey) == 0) {
            $this->_returnInvalid('Security Key invalid');
        }
        else {

            // Mark
            if ($request->getParam('VendorTxCode')) {
                fopen($this->_getCheckFile(), 'w');
            }

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
            $validSignature = (((int) $this->getSPSModel()->getConfigData('validate_md5') == 1) && ($this->getSPSModel()->getConfigData('mode') == 'live')) ? ($strMySignature !== $strVPSSignature) : false;

            if ($validSignature) {

                Sage_Log::log("Cannot match the MD5 Hash", null, 'SagePaySuite_POST_Requests.log');
                Sage_Log::log("My Message: $strMessage", null, 'SagePaySuite_POST_Requests.log');
                Sage_Log::log("My Signature: $strMySignature", null, 'SagePaySuite_POST_Requests.log');
                Sage_Log::log("VPS Signature: $strVPSSignature", null, 'SagePaySuite_POST_Requests.log');
                Sage_Log::log("TRN from DB:", null, 'SagePaySuite_POST_Requests.log');
                Sage_Log::log($dbtrn->toArray(), null, 'SagePaySuite_POST_Requests.log');

                $this->_returnInvalid('Cannot match the MD5 Hash. Order might be tampered with. ' . $strStatusDetail);
            } else {

                $strDBStatus = $this->_getHRStatus($strStatus, $strStatusDetail);

                if ($strStatus == 'OK' || $strStatus == 'AUTHENTICATED' || $strStatus == 'REGISTERED') {

                    try {
                        $sagePayServerSession->setTrnhData($this->_setAdditioanlPaymentInfo($strDBStatus));

                        $sOrder = $this->_sAdminOrder();
                        if (is_string($sOrder)) {
                            $sagePayServerSession->setFailStatus($sOrder);
                            /** The status indicates a failure of one state or another, so send the customer to orderFailed instead * */
                            $strRedirectPage = $this->_getFailedRedirectUrl();

                            $this->_returnInvalid('Couldnot save order');
                        } else {

                            $orderId = Mage::registry('last_order_id');

                            $dbtrn->addData(Mage::helper('sagepaysuite')->arrayKeysToUnderscore($_POST))
                                    ->setPostcodeResult($this->getRequest()->getPost('PostCodeResult'))
                                    ->setThreedSecureStatus($this->getRequest()->getPost('3DSecureStatus'))
                                    ->setLastFourDigits($this->getRequest()->getPost('Last4Digits'))
                                    ->setOrderId($orderId)->save();

                            $sagePayServerSession->setSuccessStatus($strDBStatus);

                            //if ($this->ia()) {
                            $sagePayServerSession->setDummyId($sOrder->getId());

                            if ($request->getParam('e')) {
                                $sOrder->sendNewOrderEmail();
                            }
                            //}
                        }

                        Mage :: getSingleton('checkout/session')->setSagePayRewInst(null)->setSagePayCustBalanceInst(null);

                        $this->_returnOk();
                    } catch (Exception $e) {
                        Mage :: logException($e);
                        Mage :: log($e->getMessage());
                    }
                } else {
                    $sagePayServerSession->setFailStatus($strDBStatus);
                    /** The status indicates a failure of one state or another, so send the customer to orderFailed instead * */
                    $this->_returnInvalid($strDBStatus);
                }
            }
        } //}} SecurityKey check
    }

    public function notifyAdminOrderAction() {
        Mage :: getModel('adminhtml/url')->turnOffSecretKey();
        return $this->notifyAction();
    }

    protected function _invoiceOrder($id) {
        $order = Mage :: getModel('sales/order');
        $order->loadByIncrementId($id);
        if (!$order->canInvoice()) {
            //when order cannot create invoice, need to have some logic to take care
            $order->addStatusToHistory($order->getStatus(), // keep order status/state
                    Mage :: helper('sagepaysuite')->__('Error in creating an invoice', true));
        } else {

            $osar = $this->getSPSModel()->getConfigData('order_status_after_release');
            $status = Mage_Sales_Model_Order :: STATE_PROCESSING;

            if (!empty($osar)) {
                $status = $osar;
            }
            $invoice = $order->prepareInvoice();
            $invoice->register()->capture();
            Mage :: getModel('core/resource_transaction')->addObject($invoice)->addObject($invoice->getOrder())->save();
            $order->setState($status, $status, Mage :: helper('sagepaysuite')->__('Invoice #%s created', $invoice->getIncrementId()));
        }
        return $order->save();
    }

    public function redirectAction() {
        $this->getResponse()->setBody($this->getLayout()->createBlock('sagepaysuite/checkout_servercallback')->toHtml());
    }

    public function failedAction() {
        $this->getResponse()->setBody($this->getLayout()->createBlock('sagepaysuite/checkout_serverfailmoto')->toHtml());
    }

    public function failureAction() {
        $this->getResponse()->setBody($this->getLayout()->createBlock('sagepaysuite/checkout_serverfailmoto')->toHtml());
    }

    public function successAction() {
        $this->getResponse()->setBody($this->getLayout()->createBlock('sagepaysuite/checkout_serversuccess')->toHtml());
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

    /**
     * Check if current quote is multishipping
     */
    protected function _isMultishippingCheckout() {
        return (bool) Mage :: getSingleton('checkout/session')->getQuote()->getIsMultiShipping();
    }

    protected function _getOrderCreateModel() {
        return Mage :: getSingleton('adminhtml/sales_order_create');
    }

    private function _sAdminOrder() {
        try {

            /* Commented, fixes problems with discounts.
             * $this->_getOrderCreateModel()
                    ->getQuote()->collectTotals()
                    ->save();*/

            $email = $this->getRequest()->getParam('l');
            $groupId = $this->getRequest()->getParam('g');

            $order = $this->_getOrderCreateModel()
                          ->importPostData(array(
                            'account' => array('group_id' => $groupId, 'email' => $email)
                          ))
                          ->setIsValidate(true)
                          ->createOrder();

            Mage::register('last_order_id', $order->getId());

            return $order;
        } catch (Exception $e) {
            Mage :: log($e->getMessage());
            Mage :: logException($e);
            return $e->getMessage();
        }
    }

    protected function _getMsState() {
        return Mage :: getSingleton('checkout/type_multishipping_state');
    }

    /*
     * Return selected payment method code
     */

    public function gtspmcAction() {
        $this->getResponse()->setHeader('content-type', 'text/plain', true);
        return $this->getResponse()->setBody($this->getOnepage()->getQuote()->getPayment()->getMethod());
    }

    private function _deleteQuote() {

        if ($this->getOnepage()->getQuote()->hasItems()) {
            try {
                $this->getOnepage()->getQuote()->setIsActive(false)
                        ->save();
            } catch (Exception $e) {
                Mage::logException($e);
            }
        }
    }

    protected function _isAllowed() {
            $acl = 'sales/order/actions/create';
            return Mage::getSingleton('admin/session')->isAllowed($acl);
    }

}