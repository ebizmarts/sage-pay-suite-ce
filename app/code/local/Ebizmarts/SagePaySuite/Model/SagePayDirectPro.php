<?php

/**
 * DIRECT main model
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */
class Ebizmarts_SagePaySuite_Model_SagePayDirectPro extends Ebizmarts_SagePaySuite_Model_Api_Payment {

    protected $_code = 'sagepaydirectpro';
    protected $_formBlockType = 'sagepaysuite/form_sagePayDirectPro';
    protected $_infoBlockType = 'sagepaysuite/info_sagePayDirectPro';

    /**
     * Availability options
     */
    protected $_isGateway = true;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    protected $_canRefund = false;
    protected $_canRefundInvoicePartial = false;
    protected $_canVoid = false;
    protected $_canUseInternal = false;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = true;

    public function registerToken($payment) {
        if (true === $this->getTokenModel()->isEnabled()) {
            $result = $this->getTokenModel()->registerCard($this->getNewTokenCardArray($payment), true);
            if ($result['Status'] != 'OK') {
                Mage::throwException(Mage::helper('sagepaysuite')->__($result['StatusDetail']));
            }
            return $result;
        }
    }

    public function directRegisterTransaction(Varien_Object $payment, $amount) {
        #Process invoice
        if (!$payment->getRealCapture()) {
            return $this->captureInvoice($payment, $amount);
        }

        /**
         * Token Transaction
         */
        if (true === $this->_tokenPresent()) {

            $_info = new Varien_Object(array('payment' => $payment));
            $result = $this->getTokenModel()->tokenTransaction($_info);

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
        /**
         * Token Transaction
         */
        if ($this->_getIsAdmin() && (int) $this->_getAdminQuote()->getCustomerId() === 0) {
            //$cs = Mage::getModel('customer/customer')->setWebsiteId($this->_getAdminQuote()->getStoreId())->loadByEmail($this->_getAdminQuote()->getCustomerEmail());
            $cs = Mage::helper('sagepaysuite')->existsCustomerForEmail($this->_getAdminQuote()->getCustomerEmail(), $this->_getAdminQuote()->getStore()->getWebsite()->getId());
            if ($cs) {
                Mage::throwException($this->_SageHelper()->__('Customer already exists.'));
            }
        }
        if ($this->_getIsAdmin()) {
            $payment->setRequestVendor($this->getConfigData('vendor', $this->_getAdminQuote()->getStoreId()));
        }

        if ($this->getSageSuiteSession()->getSecure3d()) {
            $this->directCallBack3D(
                    $payment, $this->getSageSuiteSession()->getPares(), $this->getSageSuiteSession()->getEmede());
            $this->getSageSuiteSession()->setSecure3d(null);
            return $this;
        }
        $this->getSageSuiteSession()->setMd(null)
                ->setAcsurl(null)
                ->setPareq(null);

        $error = false;

        $payment->setAnetTransType(strtoupper($this->getConfigData('payment_action')));

        $payment->setAmount($amount);

        $request = $this->_buildRequest($payment);

        Mage::dispatchEvent('sagepaysuite_direct_request_post_before', array('request' => $request, 'payment' => $this));

        $result = $this->_postRequest($request);

        $dbTrn = Mage::getModel('sagepaysuite2/sagepaysuite_transaction')
            ->loadByVendorTxCode($request->getData('VendorTxCode'))
            ->setVendorTxCode($request->getData('VendorTxCode'))
            ->setCustomerContactInfo($request->getData('ContactNumber'))
            ->setCustomerCcHolderName($request->getData('CustomerName'))
            ->setVendorname($request->getData('Vendor'))
            ->setTxType($request->getData('InternalTxtype'))
            ->setTrnCurrency($request->getCurrency())
            ->setIntegration('direct')
            ->setCardType($request->getData('CardType'))
            ->setCardExpiryDate($request->getData('ExpiryDate'))
            ->setLastFourDigits(substr($request->getData('CardNumber'), -4))
            ->setToken($request->getData('Token'))
            ->setNickname($request->getData('Nickname'))
            ->setTrnCurrency($request->getData('Currency'))
            ->setMode($this->getConfigData('mode'))
            ->setTrndate($this->getDate())
            ->setStatus($result->getResponseStatus())
            ->setStatusDetail($result->getResponseStatusDetail())
            ->save();

        switch ($result->getResponseStatus()) {
            case 'FAIL':
                $error = $result->getResponseStatusDetail();
                $payment
                        ->setStatus('FAIL')
                        ->setCcTransId($result->getVPSTxId())
                        ->setLastTransId($result->getVPSTxId())
                        ->setCcApproval('FAIL')
                        ->setAddressResult($result->getAddressResult())
                        ->setPostcodeResult($result->getPostCodeResult())
                        ->setCv2Result($result->getCV2Result())
                        ->setCcCidStatus($result->getTxAuthNo())
                        ->setSecurityKey($result->getSecurityKey())
                        ->setAdditionalData($result->getResponseStatusDetail());
                break;
            case 'FAIL_NOMAIL':
                $error = $result->getResponseStatusDetail();
                break;
            case self::RESPONSE_CODE_APPROVED:
            case self::RESPONSE_CODE_REGISTERED:

                $payment->setSagePayResult($result);

                $payment
                        ->setStatus(self::RESPONSE_CODE_APPROVED)
                        ->setCcTransId($result->getVPSTxId())
                        ->setLastTransId($result->getVPSTxId())
                        ->setCcApproval(self::RESPONSE_CODE_APPROVED)
                        ->setAddressResult($result->getAddressResult())
                        ->setPostcodeResult($result->getPostCodeResult())
                        ->setCv2Result($result->getCV2Result())
                        ->setCcCidStatus($result->getTxAuthNo())
                        ->setSecurityKey($result->getSecurityKey());

                if (strtoupper($this->getConfigData('payment_action')) == self::REQUEST_TYPE_PAYMENT) {
                    $this->getSageSuiteSession()->setInvoicePayment(true);
                }

                break;
            case self::RESPONSE_CODE_3DAUTH:

                $payment->setSagePayResult($result);

                $payment->getOrder()->setIsThreedWaiting(true);

                $this->getSageSuiteSession()->setSecure3dMethod('directCallBack3D');

                $this->getSageSuiteSession()
                        ->setAcsurl($result->getData('a_cs_ur_l'))
                        ->setEmede($result->getData('m_d'))
                        ->setPareq($result->getData('p_areq'));

                $dbTrn->setMd($result->getData('m_d'))
                        ->setPareq($result->getData('p_areq'))
                        ->setAcsurl($result->getData('a_cs_ur_l'))
                        ->save();

                $this->setVndor3DTxCode($payment->getVendorTxCode());

                break;
            default:
                if ($result->getResponseStatusDetail()) {
                    $error = '';
                    if ($result->getResponseStatus() == self::RESPONSE_CODE_NOTAUTHED) {

                        $this->getSageSuiteSession()
                                ->setAcsurl(null)
                                ->setEmede(null)
                                ->setPareq(null);

                        $error = $this->_SageHelper()->__('Your credit card can not be authenticated: ');
                    } else if ($result->getResponseStatus() == self::RESPONSE_CODE_REJECTED) {
                        $this->getSageSuiteSession()
                                ->setAcsurl(null)
                                ->setEmede(null)
                                ->setPareq(null);
                        $error = $this->_SageHelper()->__('Your credit card was rejected: ');
                    }
                    $error .= $result->getResponseStatusDetail();
                } else {
                    $error = $this->_SageHelper()->__('Error in capturing the payment');
                }
                break;
        }

        if ($error !== false) {

            if (Mage::helper('adminhtml')->getCurrentUserId() !== FALSE) {
                Mage::getSingleton('adminhtml/session')->addError($error);
            }

            Mage::throwException($error);
        }

        return $this;
    }

    protected function _getPayPalCallbackUrl() {
        return Mage::getModel('core/url')->addSessionParam()->getUrl('sgps/paypalexpress/callback', array('_secure' => true));
    }

    public function getPayPalMode() {
        return Mage::getStoreConfig('payment/sagepaypaypal/mode', Mage::app()->getStore()->getId());
    }

    public function directCallBack3D(Varien_Object $payment, $PARes, $MD) {
        $error = '';

        $request = $this->_buildRequest3D($PARes, $MD);
        Sage_Log::log($request, null, '3D-Request.log');
        $result = $this->_postRequest($request, true);
        Sage_Log::log($result, null, '3D-Result.log');

        Mage::register('sageserverpost', $result);

        if ($result->getResponseStatus() == self::RESPONSE_CODE_APPROVED || $result->getResponseStatus() == 'AUTHENTICATED') {

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
                    ->setBankAuthCode($result->getData('BankAuthCode'))
                    ->setDeclineCode($result->getData('DeclineCode'))
                    ->save();

            //Saving TOKEN after 3D response.
            if ($result->getData('Token')) {
                $tokenData = array(
                    'Token'        => $result->getData('Token'),
                    'Status'       => $result->getData('Status'),
                    'Vendor'       => $_transaction->getVendorname(),
                    'CardType'     => $_transaction->getCardType(),
                    'ExpiryDate'   => $result->getData('ExpiryDate'),
                    'StatusDetail' => $result->getData('StatusDetail'),
                    'Protocol'     => 'direct',
                    'CardNumber'   => $_transaction->getLastFourDigits(),
                    'Nickname'     => $_transaction->getNickname()
                );
                Mage::getModel('sagepaysuite/sagePayToken')->persistCard($tokenData);
            }

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

    /**
     * Register DIRECT transation.
     *
     * @param array $params
     * @param bool $onlyToken
     * @param float $macOrder MAC single order
     */
    public function registerTransaction($params = null, $onlyToken = false, $macOrder = null) {

        $quoteObj = $this->_getQuote();
        $quoteObj2 = $this->getQuoteDb($quoteObj);

        if (is_null($macOrder)) {
            $amount = $this->formatAmount($quoteObj2->getGrandTotal(), $quoteObj2->getCurrencyCode());
        }
        else {

            $amount = $this->formatAmount($macOrder->getGrandTotal(), $macOrder->getCurrencyCode());

            $baseAmount = $this->formatAmount($macOrder->getBaseGrandTotal(), $macOrder->getQuoteCurrencyCode());

            $quoteObj->setMacAmount($amount);
            $quoteObj->setBaseMacAmount($baseAmount);
        }

        if (!is_null($params)) {
            $payment = $this->_getBuildPaymentObject($quoteObj2, $params);
        }
        else {
            $payment = $this->_getBuildPaymentObject($quoteObj2);
        }

        if ($onlyToken) {
            return $this->registerToken($payment);
        }

        $_rs  = $this->directRegisterTransaction($payment, $amount);
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
                ->setBankAuthCode($_res->getData('BankAuthCode'))
                ->setDeclineCode($_res->getData('DeclineCode'))
                ->save();

        return $_res;
    }

    /**
     * Validate payment method information object
     *
     * @param   Mage_Payment_Model_Info $info
     * @return  Mage_Payment_Model_Abstract
     */
    public function validate() {
        $info = $this->getInfoInstance();

        $tokenCardId = (int) $info->getSagepayTokenCcId();

        if ($tokenCardId) {
            $valid = $this->getTokenModel()->isTokenValid($tokenCardId);
            if (false === $valid) {
                Mage::throwException($this->_getHelper()->__('Token card not valid. %s', $tokenCardId));
            }

            return $this;
        }

        if (!is_null(Mage::registry('Ebizmarts_SagePaySuite_Model_Api_Payment::recoverTransaction'))) {
            return $this;
        }

        /*
         * calling parent validate function
         */

        $info = $this->getInfoInstance();
        $errorMsg = false;
        if($this->_code == "sagepaydirectpro"){
            $availableTypes = explode(',', Mage::getStoreConfig('payment/sagepaydirectpro/cctypesSagePayDirectPro'));
        }else if($this->_code == "sagepaydirectpro_moto"){
            $availableTypes = explode(',', Mage::getStoreConfig('payment/sagepaydirectpro_moto/cctypesSagePayDirectPro'));
        }

        $ccNumber = $info->getCcNumber();

        // remove credit card number delimiters such as "-" and space
        $ccNumber = preg_replace('/[\-\s]+/', '', $ccNumber);
        $info->setCcNumber($ccNumber);

        $ccType = '';

        if ($ccNumber) {

            // ccNumber is not present after 3Dcallback, in this case we supose cc is already checked

            if (!$this->_validateExpDate($info->getCcExpYear(), $info->getCcExpMonth())) {
                $errorCode = 'ccsave_expiration,ccsave_expiration_yr';
                $errorMsg = $this->_getHelper()->__('Incorrect credit card expiration date');
            }

            if (in_array($info->getCcType(), $availableTypes)) {
                if ($this->validateCcNum($ccNumber)
                        // Other credit card type number validation
                        || ($this->OtherCcType($info->getCcType()) && $this->validateCcNumOther($ccNumber))) {

                    $ccType = 'OT';
                    $ccTypeRegExpList = array(
                        'VISA' => '/^4[0-9]{12}([0-9]{3})?$/', // Visa
                        'MC' => '/^5[1-5][0-9]{14}$/', // Master Card
                        'AMEX' => '/^3[47][0-9]{13}$/'//,        // American Express
                            //'DI' => '/^6011[0-9]{12}$/'          // Discovery
                    );

                    foreach ($ccTypeRegExpList as $ccTypeMatch => $ccTypeRegExp) {
                        if (preg_match($ccTypeRegExp, $ccNumber)) {
                            $ccType = $ccTypeMatch;
                            break;
                        }
                    }

                    if (!$this->OtherCcType($info->getCcType()) && $ccType != $info->getCcType()) {
                        $errorCode = 'ccsave_cc_type,ccsave_cc_number';
                        $errorMsg = $this->_getHelper()->__("Credit card number mismatch with credit card type");
                    }
                } else {
                    $errorCode = 'ccsave_cc_number';
                    $errorMsg = $this->_getHelper()->__('Invalid Credit Card Number');
                }
            } else {
                $errorCode = 'ccsave_cc_type';
                $errorMsg = $this->_getHelper()->__('Credit card type is not allowed for this payment method');
            }
        }

        if ($errorMsg) {
            Mage::throwException($errorMsg);
        }

        return $this;

        /*
         * calling parent validate function

          return parent::validate(); */
    }

    public function OtherCcType($type) {
        return $type == 'OT' || $type == 'SOLO' || $type == 'DELTA' || $type == 'UKE' || $type == 'MAESTRO' || $type == 'SWITCH' || $type == 'LASER' || $type == 'JCB' || $type == 'DC';
    }

    protected function _buildRequest(Varien_Object $payment) {

        $order = $payment->getOrder();

        $vendorTxCode = $this->_getTrnVendorTxCode();

        $payment->setVendorTxCode($vendorTxCode);

        $_mode = ($payment->getRequestMode() ? $payment->getRequestMode() : $this->getConfigData('mode'));

        $request = Mage::getModel('sagepaysuite/sagepaysuite_request')
                ->setVPSProtocol($this->getVpsProtocolVersion($_mode))
                ->setMode($_mode)
                ->setReferrerID($this->getConfigData('referrer_id'))
                ->setTxType($payment->getAnetTransType())
                ->setInternalTxtype($payment->getAnetTransType()) # Just for storing in transactions table
                ->setVendor(($payment->getRequestVendor() ? $payment->getRequestVendor() : $this->getConfigData('vendor')))
                ->setVendorTxCode($vendorTxCode)
                ->setDescription($this->ss(($payment->getCcOwner() ? $payment->getCcOwner() : '.'), 100))
                ->setClientIPAddress($this->getClientIp());


        //basket
        $force_xml = false;
        if ($order->getPayment()->getMethodInstance()->getCode() == 'sagepaypaypal' &&
            Mage::getStoreConfig('payment/sagepaypaypal/force_basketxml_paypal') == TRUE) {
            //force XML for paypal
            $force_xml = true;
        }
        $basket = Mage::helper('sagepaysuite')->getSagePayBasket($this->_getQuote(),$force_xml);
        if(!empty($basket)) {
            if($basket[0] == "<") {
                $request->setBasketXML($basket);
            }
            else {
                $request->setBasket($basket);
            }
        }

        if ($request->getToken()) {
            $request->setData('store_token', 1);
        }

        if ($this->_getIsAdminOrder()) {
            $request->setAccountType('M');
        }

        if ($payment->getAmountOrdered()) {

            $this->_setRequestCurrencyAmount($request, $this->_getQuote());
        }
        else {
            Sage_Log::log('No amount on payment');
            Mage::throwException('No amount on payment');
        }

        if (!empty($order)) {

            //set billing address
            $billing = $order->getBillingAddress();
            if (!empty($billing)) {
                $request->setBillingAddress($this->ss($billing->getStreet(1) . ' ' . $billing->getCity() . ' ' .
                        $billing->getRegion() . ' ' . $billing->getCountry(), 100)
                )
                    ->setBillingSurname($this->ss($billing->getLastname(), 20))
                    ->setBillingFirstnames($this->ss($billing->getFirstname(), 20))
                    ->setBillingPostCode($this->sanitizePostcode($this->ss($billing->getPostcode(), 10)))
                    ->setBillingAddress1($this->ss($billing->getStreet(1), 100))
                    ->setBillingAddress2($this->ss($billing->getStreet(2), 100))
                    ->setBillingCity($this->ss($billing->getCity(), 40))
                    ->setBillingCountry($billing->getCountry())
                    ->setCustomerName($this->ss($billing->getFirstname() . ' ' . $billing->getLastname(), 100))
                    ->setContactNumber(substr($this->_cphone($billing->getTelephone()), 0, 20))
                    ->setContactFax($billing->getFax());

                //billing state
                $billing_state = $billing->getRegionCode();
                Mage::log($billing_state);
                if(!is_null($billing_state) && strlen($billing_state) > 2){
                    $billing_state = substr($billing_state,0,2);
                }
                if(!empty($billing_state)){
                    $request->setBillingState($billing_state);
                }

                $request->setCustomerEMail($this->ss($billing->getEmail(), 255));
            }

            //set shipping address
            if(!$order->getIsVirtual()){

                $shipping = $order->getShippingAddress();

                if (!empty($shipping)) {
                    $request->setDeliveryAddress($shipping->getStreet(1) . ' ' . $shipping->getCity() . ' ' .
                        $shipping->getRegion() . ' ' . $shipping->getCountry()
                    )
                        ->setDeliverySurname($this->ss($shipping->getLastname(), 20))
                        ->setDeliveryFirstnames($this->ss($shipping->getFirstname(), 20))
                        ->setDeliveryPostCode($this->sanitizePostcode($this->ss($shipping->getPostcode(), 10)))
                        ->setDeliveryAddress1($this->ss($shipping->getStreet(1), 100))
                        ->setDeliveryAddress2($this->ss($shipping->getStreet(2), 100))
                        ->setDeliveryCity($this->ss($shipping->getCity(), 40))
                        ->setDeliveryCountry($shipping->getCountry());

                    //shipping state
                    $shipping_state = $shipping->getRegionCode();
                    if(!is_null($shipping_state) && strlen($shipping_state) > 2){
                        $shipping_state = substr($shipping_state,0,2);
                    }
                    if(!empty($shipping_state)){
                        $request->setDeliveryState($shipping_state);
                    }
                }
            }
            else {
                #If the cart only has virtual products, I need to put an shipping address to Sage Pay.
                #Then the billing address will be the shipping address to
                $request->setDeliveryAddress($billing->getStreet(1) . ' ' . $billing->getCity() . ' ' .
                    $billing->getRegion() . ' ' . $billing->getCountry()
                )
                    ->setDeliverySurname($this->ss($billing->getLastname(), 20))
                    ->setDeliveryFirstnames($this->ss($billing->getFirstname(), 20))
                    ->setDeliveryPostCode($this->sanitizePostcode($this->ss($billing->getPostcode(), 10)))
                    ->setDeliveryAddress1($this->ss($billing->getStreet(1), 100))
                    ->setDeliveryAddress2($this->ss($billing->getStreet(2), 100))
                    ->setDeliveryCity($billing->getCity())
                    ->setDeliveryCountry($billing->getCountry());

                //shipping state
                $shipping_state = $billing->getRegionCode();
                if(!is_null($shipping_state) && strlen($shipping_state) > 2){
                    $shipping_state = substr($shipping_state,0,2);
                }
                if(!empty($shipping_state)){
                    $request->setDeliveryState($shipping_state);
                }
            }
        }

        if ($payment->getCcNumber()) {
            $request->setCardNumber($payment->getCcNumber())
                ->setExpiryDate(sprintf('%02d%02d', $payment->getCcExpMonth(), substr($payment->getCcExpYear(), strlen($payment->getCcExpYear()) - 2)))
                ->setCardType($payment->getCcType())
                ->setCV2($payment->getCcCid())
                ->setCardHolder($payment->getCcOwner())
                ->setNickname($payment->getCcNickname());

            if ($payment->getCcIssue()) {
                $request->setIssueNumber($payment->getCcIssue());
            }
            if ($payment->getCcStartMonth() && $payment->getCcStartYear()) {
                $request->setStartDate(sprintf('%02d%02d', $payment->getCcStartMonth(), substr($payment->getCcStartYear(), strlen($payment->getCcStartYear()) - 2)));
            }
        }

        if (Mage::getSingleton('admin/session')->isLoggedIn() || $this->isMobile()) {
            $request->setApply3DSecure('2');
        } else if ($this->_isMultishippingCheckout()) {
            $request->setApply3DSecure('2');
        }
        else {
            $request->setApply3DSecure($this->getConfigData('secure3d'));
        }

        if ($request->getAccountType() != 'M' && $this->_forceCardChecking($payment->getCcType()) === true) {
            $request->setApply3DSecure('3');
        }

        $request->setData('ApplyAVSCV2', $this->getConfigData('avscv2'));

        if ($payment->getCcGiftaid() == 1 || $payment->getCcGiftaid() == 'on') {
            $request->setData('GiftAidPayment', 1);
        }

        if (!$request->getDeliveryPostCode()) {
            $request->setDeliveryPostCode('000');
        }
        if (!$request->getBillingPostCode()) {
            $request->setBillingPostCode('000');
        }

        //Set to CreateToken if needed
        if ($this->_createToken() OR $payment->getRemembertoken()) {
            if(!$request->setCreateToken(1,$payment->getCcNumber(),$request->getExpiryDate(),$payment->getCcType())){
                $message = Mage::helper('sagepaysuite')->__('Credit card could not be saved for future use. You already have this card attached to your account or you have reached your account\'s maximum card storage capacity.');
                Mage::getSingleton('core/session')->addWarning($message);
            }
        }

        $request->setWebsite(Mage::app()->getStore()->getWebsite()->getName());

        $customerXML = $this->getCustomerXml($this->_getQuote());
        if (!is_null($customerXML)) {
            $request->setCustomerXML($customerXML);
        }

        //Skip PostCode and Address Validation for overseas orders
        if((int)Mage::getStoreConfig('payment/sagepaysuite/apply_AVSCV2') === 1){
            if($this->_SageHelper()->isOverseasOrder($billing->getCountry())){
                $request->setData('ApplyAVSCV2', 2);
            }
        }

        return $request;
    }

    /**
     * Force 3D secure checking based on card rule
     */
    protected function _forceCardChecking($ccType = null) {
        $config = $this->getConfigData('force_threed_cards');

        if (is_null($ccType) || strlen($config) === 0) {
            return false;
        }

        $config = explode(',', $config);
        if (in_array($ccType, $config)) {
            return true;
        }

        return false;
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

                    //Saving TOKEN.
                    if (!$callback3D && $result->getData('Token')) {
                        $tokenData = array(
                            'Token'        => $result->getData('Token'),
                            'Status'       => $result->getData('Status'),
                            'Vendor'       => $request->getData('Vendor'),
                            'CardType'     => $request->getData('CardType'),
                            'ExpiryDate'   => $request->getData('ExpiryDate'),
                            'StatusDetail' => $result->getData('StatusDetail'),
                            'Protocol'     => 'direct',
                            'CardNumber'   => $request->getData('CardNumber'),
                            'Nickname'     => $request->getData('Nickname')
                        );

                        Mage::getModel('sagepaysuite/sagePayToken')->persistCard($tokenData);
                    }

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

    public function getNewTokenCardArray(Varien_Object $payment) {
        $data = array();
        $data ['CardHolder'] = $payment->getCcOwner();
        $data ['CardNumber'] = $payment->getCcNumber();
        $data ['CardType']   = $payment->getCcType();
        $data ['Currency']   = $payment->getOrder()->getOrderCurrencyCode();
        $data ['CV2']        = $payment->getCcCid();
        $data ['Nickname']   = $payment->getCcNickname();
        $data ['Protocol']   = 'direct'; #For persistant storing
        $data ['ExpiryDate'] = str_pad($payment->getCcExpMonth(), 2, '0', STR_PAD_LEFT) . substr($payment->getCcExpYear(), 2);
        if ($payment->getCcSsStartMonth() && $payment->getCcSsStartYear()) {
            $data['StartDate'] = str_pad($payment->getCcSsStartMonth(), 2, '0', STR_PAD_LEFT) . substr($payment->getCcSsStartYear(), 2);
        }

        return $data;
    }

    /**
     * Capture payment
     *
     * @param   Varien_Object $orderPayment
     * @return  Mage_Payment_Model_Abstract
     */
    public function capture(Varien_Object $payment, $amount) {
        #Process invoice
        if (!$payment->getRealCapture()) {
            return $this->captureInvoice($payment, $amount);
        }

        /**
         * Token Transaction
         */
        if (true === $this->_tokenPresent()/* || $this->_getSageSuiteSession()->getLastSavedTokenccid() */) {
            $_info = new Varien_Object(array('payment' => $payment));
            $result = $this->getTokenModel()->tokenTransaction($_info);
            if ($result['Status'] != 'OK') {
                Mage::throwException(Mage::helper('sagepaysuite')->__($result['StatusDetail']));
            }

            $this->getSageSuiteSession()->setInvoicePayment(true);

            $this->setSagePayResult($result);
            return $this;
        }
        /**
         * Token Transaction
         */
        if ($this->_getIsAdmin() && (int) $this->_getAdminQuote()->getCustomerId() === 0) {
            //$cs = Mage::getModel('customer/customer')->setWebsiteId($this->_getAdminQuote()->getStoreId())->loadByEmail($this->_getAdminQuote()->getCustomerEmail());
            $cs = Mage::helper('sagepaysuite')->existsCustomerForEmail($this->_getAdminQuote()->getCustomerEmail(), $this->_getAdminQuote()->getStore()->getWebsite()->getId());
            if ($cs) {
                Mage::throwException($this->_SageHelper()->__('Customer already exists.'));
            }
        }

        /* if ($this->getSageSuiteSession()->getSecure3d()) {
          $this->capture3D(
          $payment,
          $this->getSageSuiteSession()->getPares(),
          $this->getSageSuiteSession()->getEmede());
          $this->getSageSuiteSession()->setSecure3d(null);
          return $this;
          } */
        $this->getSageSuiteSession()->setMd(null)
                ->setAcsurl(null)
                ->setPareq(null);

        $error = false;

        $payment->setAnetTransType(parent::REQUEST_TYPE_PAYMENT);

        $payment->setAmount($amount);

        $request = $this->_buildRequest($payment);
        $result = $this->_postRequest($request);
        switch ($result->getResponseStatus()) {
            case 'FAIL':
                $payment
                        ->setStatus('FAIL')
                        ->setCcTransId($result->getVPSTxId())
                        ->setLastTransId($result->getVPSTxId())
                        ->setCcApproval('FAIL')
                        ->setAddressResult($result->getAddressResult())
                        ->setPostcodeResult($result->getPostCodeResult())
                        ->setCv2Result($result->getCV2Result())
                        ->setCcCidStatus($result->getTxAuthNo())
                        ->setSecurityKey($result->getSecurityKey())
                        ->setAdditionalData($result->getResponseStatusDetail());
                break;
            case 'FAIL_NOMAIL':
                $error = $result->getResponseStatusDetail();
                break;
            case parent::RESPONSE_CODE_APPROVED:

                $payment->setSagePayResult($result);

                $payment
                        ->setStatus(parent::RESPONSE_CODE_APPROVED)
                        ->setCcTransId($result->getVPSTxId())
                        ->setLastTransId($result->getVPSTxId())
                        ->setCcApproval(parent::RESPONSE_CODE_APPROVED)
                        ->setAddressResult($result->getAddressResult())
                        ->setPostcodeResult($result->getPostCodeResult())
                        ->setCv2Result($result->getCV2Result())
                        ->setCcCidStatus($result->getTxAuthNo())
                        ->setSecurityKey($result->getSecurityKey());

                $this->getSageSuiteSession()->setInvoicePayment(true);

                break;
            case parent::RESPONSE_CODE_3DAUTH:

                $payment->setSagePayResult($result);

                $payment->getOrder()->setIsThreedWaiting(true);

                $this->getSageSuiteSession()->setSecure3dMethod('directCallBack3D');

                $this->getSageSuiteSession()
                        ->setAcsurl($result->getData('a_cs_ur_l'))
                        ->setEmede($result->getData('m_d'))
                        ->setPareq($result->getData('p_areq'));
                $this->setVndor3DTxCode($payment->getVendorTxCode());

                break;
            default:
                if ($result->getResponseStatusDetail()) {
                    $error = '';
                    if ($result->getResponseStatus() == parent::RESPONSE_CODE_NOTAUTHED) {
                        $error = $this->_SageHelper()->__('Your credit card can not be authenticated: ');
                    } else if ($result->getResponseStatus() == parent::RESPONSE_CODE_REJECTED) {
                        $error = $this->_SageHelper()->__('Your credit card was rejected: ');
                    }
                    $error .= $result->getResponseStatusDetail();
                } else {
                    $error = $this->_SageHelper()->__('Error in capturing the payment');
                }
                break;
        }

        if ($error !== false) {
            Mage::throwException($error);
        }
        return $this;
    }

    public function saveOrderAfter3dSecure($pares, $md) {

        $this->getSageSuiteSession()->setSecure3d(true);
        $this->getSageSuiteSession()->setPares($pares);
        $this->getSageSuiteSession()->setMd($md);

        $quote = Mage::getSingleton('checkout/type_onepage')->getQuote();
        $order = $this->directCallBack3D($quote->getPayment(), $pares, $md);

        $this->getSageSuiteSession()
                ->setAcsurl(null)
                ->setPareq(null)
                ->setSageOrderId(null)
                ->setSecure3d(null)
                ->setEmede(null)
                ->setPares(null)
                ->setMd(null);

        return $order;
    }

    public function sendNotificationEmail($toEmail = '', $toName = '', $params = array()) {
        $translate = Mage::getSingleton('core/translate');

        $translate->setTranslateInline(false);

        $storeId = $this->getStoreId();

        if ($this->getWebsiteId() != '0' && $storeId == '0') {
            $storeIds = Mage::app()->getWebsite($this->getWebsiteId())->getStoreIds();
            reset($storeIds);
            $storeId = current($storeIds);
        }
        $toEmail = Mage::getStoreConfig('trans_email/ident_support/email', $storeId);
        $toName = Mage::getStoreConfig('trans_email/ident_support/name', $storeId);


        $mail = Mage::getModel('core/email_template')
                ->setDesignConfig(array('area' => 'frontend', 'store' => $storeId))
                ->sendTransactional(
                Mage::getStoreConfig('payment/sagepaydirectpro/email_timeout_template'), array('name' => Mage::getStoreConfig('trans_email/ident_general/name', $storeId), 'email' => Mage::getStoreConfig('trans_email/ident_general/email', $storeId)),
//                Mage::getStoreConfig('payment/sagepaydirectpro/email_timeout_identity'),
                $toEmail, $toName, $params);

        $translate->setTranslateInline(true);

        return $mail->getSentSuccess();
    }

    /* public function getOrderPlaceRedirectUrl()
      {
      $tmp = $this->getSageSuiteSession();

      Ebizmarts_SagePaySuite_Log::w($tmp->getAcsurl().'-'.$tmp->getEmede().'-'.$tmp->getPareq());

      if ( $tmp->getAcsurl() && $tmp->getEmede() && $tmp->getPareq()) {
      #return Mage::getUrl('sagepaydirectpro/payment/redirect', array('_secure' => true));
      return Mage::getUrl('sagepaydirectpro-3dsecure', array('_secure' => true));
      } else {
      return false;
      }
      } */

    public function getPayPalTitle() {
        return Mage::getStoreConfig('payment/sagepaypaypal/title', Mage::app()->getStore()->getId());
    }

    /**
     * @return array
     */
    public function getConfigSafeFields() {
        return array('active', 'mode', 'title', 'useccv', 'threed_iframe_height', 'threed_iframe_width', 'threed_layout');
    }

}

