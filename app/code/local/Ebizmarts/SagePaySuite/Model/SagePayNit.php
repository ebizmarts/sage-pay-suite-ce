<?php

class Ebizmarts_SagePaySuite_Model_SagePayNit extends Ebizmarts_SagePaySuite_Model_Api_Payment {

    protected $_code = 'sagepaynit';
    protected $_formBlockType = 'sagepaysuite/form_sagePayNit';
    protected $_infoBlockType = 'sagepaysuite/info_sagePayNit';

    /**
     * Availability options
     */
    protected $_isGateway = true;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    /**
     * Can refund online?
     */
    protected $_canRefund = false;
    protected $_canRefundInvoicePartial = false;

    /**
     * Can void transactions online?
     */
    protected $_canVoid = false;
    protected $_canUseInternal = false;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = true;


    public function registerTransaction($params = null, $macOrder = null) {

        $quoteObj = $this->_getQuote();
        $quoteObj2 = $this->getQuoteDb($quoteObj);

        $session_surcharge = 0;
        if(Mage::helper('sagepaysuite')->surchargesModuleEnabled() == true){
            $session_surcharge = $this->_getSessionSurcharge();
        }

        if (is_null($macOrder)) {
            $amount = $this->formatAmount($quoteObj2->getGrandTotal()-$session_surcharge, $quoteObj2->getCurrencyCode());
        }
        else {

            $amount = $this->formatAmount($macOrder->getGrandTotal()-$session_surcharge, $macOrder->getCurrencyCode());

            $baseAmount = $this->formatAmount($macOrder->getBaseGrandTotal()-$session_surcharge, $macOrder->getQuoteCurrencyCode());

            $quoteObj->setMacAmount($amount);
            $quoteObj->setBaseMacAmount($baseAmount);
        }

        if (!is_null($params)) {
            $payment = $this->_getBuildPaymentObject($quoteObj2, $params);
        }
        else {
            $payment = $this->_getBuildPaymentObject($quoteObj2);
        }

        $_rs  = $this->nitRegisterTransaction($payment, $amount);
        $_req = $payment->getSagePayResult()->getRequest();
        $_res = $payment->getSagePayResult();

        #Last order vendortxcode
        $this->getSageSuiteSession()->setLastVendorTxCode($_req->getData('VendorTxCode'));
        if ($this->isMsOnOverview()) {
            $tx = array();
            $regTxCodes = Mage::registry('sagepaysuite_ms_txcodes');
            if ($regTxCodes) {
                $tx += $regTxCodes;
                Mage::unregister('sagepaysuite_ms_txcodes');
            }
            $tx [] = $_req->getData('VendorTxCode');
            Mage::register('sagepaysuite_ms_txcodes', $tx);
        }

        Mage::getModel('sagepaysuite2/sagepaysuite_transaction')
            ->loadByVendorTxCode($_req->getData('VendorTxCode'))
            ->setVendorTxCode($_req->getData('VendorTxCode'))
            ->setToken($_req->getData('Token'))
            ->setTrnCurrency($_req->getData('Currency'))
            ->setTrnAmount($_req->getData('Amount'))
            ->setTxType($_req->getData('Txtype'))
            ->setMode($this->getConfigData('mode'))
            ->setVendorname($_req->getData('Vendor'))
            ->setVpsProtocol($_res->getData('VPSProtocol'))
            ->setSecurityKey($_res->getData('SecurityKey'))
            ->setVpsTxId($_res->getData('VPSTxId'))
            ->setTxAuthNo($_res->getData('TxAuthNo'))
            ->setAvscv2($_res->getData('AVSCV2'))
            ->setPostcodeResult($_res->getData('PostCodeResult'))
            ->setAddressResult($_res->getData('AddressResult'))
            ->setCv2result($_res->getData('CV2Result'))
            ->setThreedSecureStatus($_res->getData('3DSecureStatus'))
            ->setCavv($_res->getData('CAVV'))
            ->setRedFraudResponse($_res->getData('FraudResponse'))
            ->setSurchargeAmount($_res->getData('Surcharge'))
            ->setBankAuthCode($_res->getData('BankAuthCode'))
            ->setDeclineCode($_res->getData('DeclineCode'))
            ->save();

        return $_res;
    }

    public function nitRegisterTransaction(Varien_Object $payment, $amount) {


        #Process invoice
        if (!$payment->getRealCapture()) {
            return $this->captureInvoice($payment, $amount);
        }

        $_info = new Varien_Object(array('payment' => $payment));

        $result = $this->nitTransaction($_info);

            if ($result['Status'] != self::RESPONSE_CODE_APPROVED
                && $result['Status'] != self::RESPONSE_CODE_3DAUTH
                && $result['Status'] != self::RESPONSE_CODE_REGISTERED) {
                Mage::throwException(Mage::helper('sagepaysuite')->__($result['StatusDetail']));
            }

            if (strtoupper($this->getConfigData('payment_action')) == self::REQUEST_TYPE_PAYMENT) {
                $this->getSageSuiteSession()->setInvoicePayment(true);
            }

            $this->setSagePayResult($result);

            if ($result['Status'] == self::RESPONSE_CODE_3DAUTH) {
                $payment->getOrder()->setIsThreedWaiting(true);

                $this->getSageSuiteSession()->setSecure3dMethod('directCallBack3D');

                Mage::getModel('sagepaysuite2/sagepaysuite_transaction')
                    ->loadByVendorTxCode($payment->getVendorTxCode())
                    ->setVendorTxCode($payment->getVendorTxCode())
                    ->setIntegration('nit')
                    ->setMd($result['MD'])
                    ->setPareq($result['PAReq'])
                    ->setAcsurl($result['ACSURL'])
                    ->save();

                $this->getSageSuiteSession()
                    ->setAcsurl($result['ACSURL'])
                    ->setEmede($result['MD'])
                    ->setPareq($result['PAReq']);
                $this->setVndor3DTxCode($payment->getVendorTxCode());
            }

            return $this;
    }

    public function validate() {
        $info = $this->getInfoInstance();

        return $this;
    }

    public function nitTransaction(Varien_Object $info) {

        $postData                   = array();
        $postData                   += $this->_getGeneralTrnData($info->getPayment(), $info->getParameters())->getData();
        $postData['VendorTxCode']   = substr($postData['vendor_tx_code'], 0, 40);
        $postData['Txtype']         = $info->getPayment()->getTransactionType();
        $postData['InternalTxtype'] = $postData['Txtype'];
        $postData['Token']          = $info->getPayment()->getNitCardIdentifier();
        $postData['ECDType']        = 1;
        $postData['Description']    = 'Purchased Goods.';
        $postData['Vendor']         = $this->getConfigData('vendor'); //@TODO: Check this for token MOTO transactions.

        //remove unused fields
        if(array_key_exists("c_v2",$postData)){
            unset($postData["c_v2"]);
        }
        if(array_key_exists("card_holder",$postData)){
            unset($postData["card_holder"]);
        }
        if(array_key_exists("card_number",$postData)){
            unset($postData["card_number"]);
        }
        if(array_key_exists("card_type",$postData)){
            unset($postData["card_type"]);
        }
        if(array_key_exists("expiry_date",$postData)){
            unset($postData["expiry_date"]);
        }

        //surcharge XML
        if(Mage::helper('sagepaysuite')->surchargesModuleEnabled() == true){
            $surchargeXML = $this->getSurchargeXml($this->_getQuote());
            if (!is_null($surchargeXML)) {
                $postData['SurchargeXML'] = $surchargeXML;
            }
        }

        $postData = Mage::helper('sagepaysuite')->arrayKeysToCamelCase($postData);
        //$postData['Apply3DSecure'] = (int) Mage::getStoreConfig("payment/sagepaydirectpro/secure3d");

        $urlPost = $this->getTokenUrl('post', 'nit');

        $rs            = $this->requestPost($urlPost, $postData);
        $rs['request'] = new Varien_Object($postData);

        $objRs = new Varien_Object($rs);
        $objRs->setResponseStatus($objRs->getData('Status'))
            ->setResponseStatusDetail($objRs->getData('StatusDetail'));

        $info->getPayment()->setSagePayResult($objRs);

        return $rs;
    }

    public function postForMerchantKey(){

        $url = $this->getUrl("api") . "merchant-session-keys";

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, "{\"vendorName\": \"" . $this->getConfigData('vendor') ."\"}");
        $sslversion = Mage::getStoreConfig('payment/sagepaysuite/curl_ssl_version');
        curl_setopt($curl, CURLOPT_SSLVERSION, $sslversion);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, 8);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        if(Mage::getStoreConfigFlag('payment/sagepaysuite/curl_proxy') == 1){
            curl_setopt($curl, CURLOPT_PROXY, Mage::getStoreConfig('payment/sagepaysuite/curl_proxy_port'));
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-type: application/json'));

        //auth
        $secret = Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/sagepaynit/password'));
        curl_setopt($curl, CURLOPT_USERPWD, Mage::getStoreConfig('payment/sagepaynit/key') . ":" . $secret);

        $response = curl_exec($curl);

        if (curl_error($curl)) {
            Mage::throwException(curl_error($curl));
        }else{
            $response_data = json_decode($response);

            if($response_data && array_key_exists("code",$response_data)){
                Mage::throwException("ERROR " . $response_data->code . " " . $response_data->description);
            }elseif($response_data && array_key_exists("merchantSessionKey",$response_data)){
                return $response_data;
            }else{
                Mage::throwException("Unable to request merchant key: Invalid response from SagePay");
            }
        }
    }

    public function saveOrderAfter3dSecure($pares, $md) {

        $this->getSageSuiteSession()->setSecure3d(true);
        $this->getSageSuiteSession()->setPares($pares);
        $this->getSageSuiteSession()->setMd($md);

        $quote = Mage::getSingleton('checkout/type_onepage')->getQuote();
        $order = $this->nitCallBack3D($quote->getPayment(), $pares, $md);

        $this->getSageSuiteSession()
            ->setAcsurl(null)
            ->setPareq(null)
            ->setSageOrderId(null)
            ->setSecure3d(null)
            ->setEmede(null)
            ->setPares(null)
            ->setMd(null)
            ->setSurcharge(null);

        return $order;
    }

    public function nitCallBack3D(Varien_Object $payment, $PARes, $MD) {
        $error = '';

        $request = $this->_buildRequest3D($PARes, $MD);
        Sage_Log::log($request, null, '3D-Request.log');
        $result = $this->_postRequest($request, true);
        Sage_Log::log($result, null, '3D-Result.log');

        if(Mage::helper('sagepaysuite')->surchargesModuleEnabled() == true){
            //save surcharge to server post for later use
            $session_surcharge_amount = Mage::getSingleton('sagepaysuite/session')->getSurcharge();
            if(!is_null($session_surcharge_amount) && $session_surcharge_amount > 0){
                $result->setData('Surcharge',$session_surcharge_amount);
            }
        }
        Mage::register('sageserverpost', $result);

        if ($result->getResponseStatus() == self::RESPONSE_CODE_APPROVED ||
            $result->getResponseStatus() == 'AUTHENTICATED' ||
            $result->getResponseStatus() == self::RESPONSE_CODE_REGISTERED) {

            if (strtoupper($this->getConfigData('payment_action')) == self::REQUEST_TYPE_PAYMENT) {
                $this->getSageSuiteSession()->setInvoicePayment(true);
            }

            $onePage = Mage::getSingleton('checkout/type_onepage');
            $quote   = $onePage->getQuote();
            $quote->collectTotals();

            Mage::helper('sagepaysuite')->ignoreAddressValidation($quote);

            $onePage->saveOrder();

            $_transaction = Mage::getModel('sagepaysuite2/sagepaysuite_transaction')
                ->loadByVendorTxCode($this->getSageSuiteSession()->getLastVendorTxCode())
                ->setVpsProtocol($result->getData('VPSProtocol'))
                ->setSecurityKey($result->getData('SecurityKey'))
                ->setStatus($result->getData('Status'))
                ->setStatusDetail($result->getData('StatusDetail'))
                ->setVpsTxId($result->getData('VPSTxId'))
                ->setTxAuthNo($result->getData('TxAuthNo'))
                ->setAvscv2($result->getData('AVSCV2'))
                ->setPostcodeResult($result->getData('PostCodeResult'))
                ->setAddressResult($result->getData('AddressResult'))
                ->setCv2result($result->getData('CV2Result'))
                ->setThreedSecureStatus($result->getData('3DSecureStatus'))
                ->setCavv($result->getData('CAVV'))
                ->setRedFraudResponse($result->getData('FraudResponse'))
                ->setSurchargeAmount($result->getData('Surcharge'))
                ->setBankAuthCode($result->getData('BankAuthCode'))
                ->setDeclineCode($result->getData('DeclineCode'))
                ->save();

            $payment->setSagePayResult($result);

            $payment->setStatus(self::STATUS_APPROVED)
                ->setCcTransId($result->getVPSTxId())
                ->setCcApproval(self::RESPONSE_CODE_APPROVED)
                ->setLastTransId($result->getVPSTxId())
                ->setAddressResult($result->getAddressResult())
                ->setPostcodeResult($result->getPostCodeResult())
                ->setCv2Result($result->getCV2Result())
                ->setSecurityKey($result->getSecurityKey())
                ->setCcCidStatus($result->getTxAuthNo())
                ->setAdditionalData($result->getResponseStatusDetail());
            $payment->save();
        }
        else {

            //Update status if 3d failed
            Mage::getModel('sagepaysuite2/sagepaysuite_transaction')
                ->loadByVendorTxCode($this->getSageSuiteSession()->getLastVendorTxCode())
                ->setStatus($result->getResponseStatus())
                ->setStatusDetail($result->getResponseStatusDetail())
                ->setVpsTxId($result->getVpsTxId())
                ->setSecurityKey($result->getSecurityKey())
                ->setPares(null)//Resetting data so we dont get "5036 : transaction not found" error for repeated calls to sagepay on 3d callback.
                ->setMd(null)//Resetting data so we dont get "5036 : transaction not found" error for repeated calls to sagepay on 3d callback.
                ->setPareq(null)
                ->save();

            if ($result->getResponseStatusDetail()) {
                if ($result->getResponseStatus() == self::RESPONSE_CODE_NOTAUTHED) {
                    $error = $this->_sageHelper()->__('Your credit card can not be authenticated: ');
                } else if ($result->getResponseStatus() == self::RESPONSE_CODE_REJECTED) {
                    $error = $this->_sageHelper()->__('Your credit card was rejected: ');
                }
                $error .= $result->getResponseStatusDetail();
            } else {
                $error = $this->_sageHelper()->__('Error in capturing the payment');
            }
        }

        if (!empty($error)) {
            Mage::throwException($error);
        }
        return $this;
    }

    protected function _buildRequest3D($PARes, $MD) {
        return $this->_getRequest()
            ->setMD($MD)
            ->setPARes($PARes);
    }

    protected function _postRequest(Varien_Object $request, $callback3D = false) {

        $result = Mage::getModel('sagepaysuite/sagepaysuite_result');

        $mode = (($request->getMode()) ? $request->getMode() : null);

        $uri = $this->getUrl('post', $callback3D, null, $mode);

        $requestData = $request->getData();

        try {
            $response = $this->requestPost($uri, $request->getData());
        } catch (Exception $e) {
            $result->setResponseCode(-1)
                ->setResponseReasonCode($e->getCode())
                ->setResponseReasonText($e->getMessage());

            Mage::throwException(
                $this->_SageHelper()->__('Gateway request error: %s', $e->getMessage())
            );
        }

        $r = $response;


        $result->setRequest($request);

        try {
            if (empty($r) OR !isset($r['Status'])) {
                $msg = $this->_SageHelper()->__('Sage Pay is not available at this time. Please try again later.');
                Sage_Log::log($msg, 1);
                $result
                    ->setResponseStatus('ERROR')
                    ->setResponseStatusDetail($msg);
                return $result;
            }

            if (isset($r['VPSTxId'])) {
                $result->setVpsTxId($r['VPSTxId']);
            }
            if (isset($r['SecurityKey'])) {
                $result->setSecurityKey($r['SecurityKey']);
            }

            switch ($r['Status']) {
                case 'FAIL':
                    $params['order'] = Mage::getSingleton('checkout/session')->getQuote()->getReservedOrderId();
                    $params['error'] = Mage::helper('sagepaysuite')->__($r['StatusDetail']);
                    //$rc = $this->sendNotificationEmail('', '', $params);

                    $result->setResponseStatus($r['Status'])
                        ->setResponseStatusDetail(Mage::helper('sagepaysuite')->__($r['StatusDetail']))
                        ->setVPSTxID(1)
                        ->setSecurityKey(1)
                        ->setTxAuthNo(1)
                        ->setAVSCV2(1)
                        ->setAddressResult(1)
                        ->setPostCodeResult(1)
                        ->setCV2Result(1)
                        ->setTrnSecuritykey(1);
                    return $result;
                    break;
                case 'FAIL_NOMAIL':
                    Mage::throwException($this->_SageHelper()->__($r['StatusDetail']));
                    break;
                case parent::RESPONSE_CODE_INVALID:
                    Mage::throwException($this->_SageHelper()->__('INVALID. %s', Mage::helper('sagepaysuite')->__($r['StatusDetail'])));
                    break;
                case parent::RESPONSE_CODE_MALFORMED:
                    Mage::throwException($this->_SageHelper()->__('MALFORMED. %s', Mage::helper('sagepaysuite')->__($r['StatusDetail'])));
                    break;
                case parent::RESPONSE_CODE_ERROR:
                    Mage::throwException($this->_SageHelper()->__('ERROR. %s', Mage::helper('sagepaysuite')->__($r['StatusDetail'])));
                    break;
                case parent::RESPONSE_CODE_REJECTED:
                    Mage::throwException($this->_SageHelper()->__('REJECTED. %s', Mage::helper('sagepaysuite')->__($r['StatusDetail'])));
                    break;
                case parent::RESPONSE_CODE_3DAUTH:
                    $result->setResponseStatus($r['Status'])
                        ->setResponseStatusDetail((isset($r['StatusDetail']) ? $r['StatusDetail'] : '')) //Fix for simulator
                        ->set3DSecureStatus($r['3DSecureStatus'])    // to store
                        ->setMD($r['MD']) // to store
                        ->setACSURL($r['ACSURL'])
                        ->setPAReq($r['PAReq']);
                    break;
                case parent::RESPONSE_CODE_PAYPAL_REDIRECT:
                    $result->setResponseStatus($r['Status'])
                        ->setResponseStatusDetail($r['StatusDetail'])
                        ->setVpsTxId($r['VPSTxId'])
                        ->setPayPalRedirectUrl($r['PayPalRedirectURL']);
                    break;
                default:

                    $result->setResponseStatus($r['Status'])
                        ->setResponseStatusDetail($r['StatusDetail'])  // to store
                        ->setVpsTxId($r['VPSTxId'])    // to store
                        ->setSecurityKey($r['SecurityKey']) // to store
                        ->setTrnSecuritykey($r['SecurityKey']);
                    if (isset($r['3DSecureStatus']))
                        $result->set3DSecureStatus($r['3DSecureStatus']);
                    if (isset($r['CAVV']))
                        $result->setCAVV($r['CAVV']);

                    if (isset($r['TxAuthNo']))
                        $result->setTxAuthNo($r['TxAuthNo']);
                    if (isset($r['AVSCV2']))
                        $result->setAvscv2($r['AVSCV2']);
                    if (isset($r['PostCodeResult']))
                        $result->setPostCodeResult($r['PostCodeResult']);
                    if (isset($r['CV2Result']))
                        $result->setCv2result($r['CV2Result']);
                    if (isset($r['AddressResult']))
                        $result->setAddressResult($r['AddressResult']);

                    $result->addData($r);

                    break;
            }
        } catch (Exception $e) {

            Sage_Log::logException($e);

            $result
                ->setResponseStatus('ERROR')
                ->setResponseStatusDetail(Mage::helper('sagepaysuite')->__($e->getMessage()));
            return $result;
        }

        return $result;
    }

    public function getConfigSafeFields() {
        return array('active', 'mode', 'title');
    }
}