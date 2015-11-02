<?php

/**
 * SERVER main model
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */
class Ebizmarts_SagePaySuite_Model_SagePayServer extends Ebizmarts_SagePaySuite_Model_Api_Payment {

    /**
     * unique internal payment method identifier
     *
     * @var string [a-z0-9_]
     */
    protected $_code = 'sagepayserver';
    protected $_formBlockType = 'sagepaysuite/form_sagePayServer';
    protected $_infoBlockType = 'sagepaysuite/info_sagePayServer';

    /**
     * Is this payment method a gateway (online auth/charge) ?
     */
    protected $_isGateway = true;

    /**
     * Can authorize online?
     */
    protected $_canAuthorize = true;

    /**
     * Can capture funds online?
     */
    protected $_canCapture = true;

    /**
     * Can capture partial amounts online?
     */
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

    /**
     * Can use this payment method in administration panel?
     */
    protected $_canUseInternal = false;

    /**
     * Can show this payment method as an option on checkout payment page?
     */
    protected $_canUseCheckout = true;

    /**
     * Is this payment method suitable for multi-shipping checkout?
     */
    protected $_canUseForMultishipping = true;

    /**
     * Can save credit card information for future processing?
     */
    protected $_canSaveCc = false;
    protected $_vendorTxC = null;
    public $errIds = array(
        'INVALID',
        'MALFORMED',
        'ERROR',
        'FAIL'
    );

    /**
     * http://pastie.org/3798730
     */
    protected $_postPayment = array();

    /**
     * Validate payment method information object
     *
     * @param   Mage_Payment_Model_Info $info
     * @return  Mage_Payment_Model_Abstract
     */
    public function validate() {
        Mage_Payment_Model_Method_Abstract::validate();
        return $this;
    }

    protected function _getSagePayServerSession() {
        return $this->getSageSuiteSession();
    }

    protected function _getAdminQuote() {
        return Mage::getSingleton('adminhtml/session_quote')->getQuote();
    }

    public function registerTransaction($params = null) {

        if(isset($params['payment'])) {
            $this->_postPayment = $params['payment'];

            //Added on Magento EE 1.13.0.0
            if($this->getCode() == 'sagepayserver_moto' && Mage::helper('sagepaysuite')->isMagentoEE113OrUp()) {
                $this->_postPayment['checks'] = Mage_Payment_Model_Method_Abstract::CHECK_USE_INTERNAL
                    | Mage_Payment_Model_Method_Abstract::CHECK_USE_FOR_COUNTRY
                    | Mage_Payment_Model_Method_Abstract::CHECK_USE_FOR_CURRENCY
                    | Mage_Payment_Model_Method_Abstract::CHECK_ORDER_TOTAL_MIN_MAX
                    | Mage_Payment_Model_Method_Abstract::CHECK_ZERO_TOTAL;
            }

        }

        if (($this->getCode() == 'sagepayserver_moto') && $this->_getIsAdmin()) {

            if(isset($this->_postPayment['sagepay_token_cc_id'])) {
                $this->getSageSuiteSession()->setLastSavedTokenccid($this->_postPayment['sagepay_token_cc_id']);
            }
            else {
                $this->getSageSuiteSession()->setLastSavedTokenccid(null);
            }

            $err = new Varien_Object;
            $err['response_status'] = 'ERROR';
            if (!$this->_getAdminQuote()->hasItems()) {

                $err['response_status_detail'] = $this->_sageHelper()->__('There are no items in quote');
            } else
            if (!$this->_getAdminQuote()->getIsVirtual() && !$this->_getAdminQuote()->getShippingAddress()->getShippingMethod()) {

                $err['response_status_detail'] = $this->_sageHelper()->__('Please, select a shipping method');
            } else
            if ((int) $this->_getAdminQuote()->getCustomerId() === 0) {

                //Check if customer exists
                $cs = Mage::helper('sagepaysuite')->existsCustomerForEmail($this->_getAdminQuote()->getCustomerEmail(), $this->_getAdminQuote()->getStore()->getWebsite()->getId());
                if ($cs) {
                    $err['response_status_detail'] = $this->_sageHelper()->__('Customer already exists.');
                }
            }

            if (isset($err['response_status_detail'])) {
                return $err;
            }
        }

        $this->_getSagePayServerSession()->setRemoteAddr($this->getClientIp());


        $quoteObj = $this->_getQuote();

        //Only collect totals for frontend orders, if you do for moto orders, it breaks for example discounts application.
        if(!$this->_getIsAdmin()) {
            //@TODO: Dont collect totals if Amasty_Promo or Qian_Bxgy is present
            //$quoteObj->setTotalsCollectedFlag(false)->collectTotals();
        }

        //$amount = $this->formatAmount($quoteObj->getGrandTotal(), $quoteObj->getQuoteCurrencyCode());
        $payment = $this->_getBuildPaymentObject($quoteObj);

        /**
         * Token Transaction
         */
        if (true === $this->_tokenPresent()) {

            $payment->setIntegra('server');

            $_info = new Varien_Object(array('payment' => $payment, 'parameters' => $params));
            $result = $this->getTokenModel()->tokenTransaction($_info);

            if ($result['Status'] != 'OK') {
                Mage::throwException(Mage::helper('sagepaysuite')->__($result['StatusDetail']));
            }

            $requestObject = $result['request'];
            $vendorTxCode  = $requestObject->getData('VendorTxCode');

            $this->getSageSuiteSession()->setLastVendorTxCode($vendorTxCode);

            if (strtoupper($requestObject->getData('Txtype')) == 'PAYMENT') {
                $this->getSageSuiteSession()->setInvoicePayment(true);
            }

            // Set values on SagePayServer session's namespace
            $this->_getSagePayServerSession()->setSecurityKey($result['SecurityKey'])->setVendorTxCode($vendorTxCode)
                    ->setVpsTxId($result['VPSTxId'])->setNextUrl($result['NextURL']);

            $trn = Mage::getModel('sagepaysuite2/sagepaysuite_transaction')
                    ->loadByVendorTxCode($vendorTxCode)
                    ->setVendorTxCode($vendorTxCode)
                    ->setToken($requestObject->getData('Token'))
                    ->setVendorname($requestObject->getData('Vendor'))
                    ->setSecurityKey($result['SecurityKey'])
                    ->setMode($this->getConfigData('mode'))
                    ->setTrnCurrency($requestObject->getData('Currency'))
                    ->setTrnAmount($requestObject->getData('Amount'))
                    ->setCustomerContactInfo($requestObject->getData('ContactNumber'))
                    ->setIntegration('server')
                    ->setTrndate($this->getDate())
                    ->save();

            $rs = new Varien_Object;
            $rs->setResponseStatus($result['Status'])->setResponseStatusDetail(Mage::helper('sagepaysuite')->__($result['StatusDetail']))->setNextUrl($result['NextURL'])->setVPSTxID($result['VPSTxId']);
            return $rs;
        }
        /**
         * Token Transaction
         */

        $request  = $this->_buildRequest($params);
        $response = $this->_postRequest($request);

        $this->getSageSuiteSession()->setLastVendorTxCode($request->getData('VendorTxCode'));

        if (strtoupper($request->getData('TxType')) == 'PAYMENT') {
            $this->getSageSuiteSession()->setInvoicePayment(true);
        }

        $token_nickname = (array_key_exists("cc_nickname",$this->_postPayment) ? $this->_postPayment['cc_nickname'] : null);

        $trn = Mage::getModel('sagepaysuite2/sagepaysuite_transaction')
                ->loadByVendorTxCode($request->getData('VendorTxCode'))
                ->setVendorTxCode($request->getData('VendorTxCode'))
                ->setVpsProtocol($response->getData('vps_protocol'))
                ->setVendorname($request->getData('Vendor'))
                ->setCustomerContactInfo($request->getData('ContactNumber'))
                ->setMode($this->getConfigData('mode'))
                ->setTxType($request->getData('TxType'))
                ->setTrnCurrency($request->getCurrency())
                ->setIntegration('server')
                ->setCardType($request->getData('CardType'))
                ->setStatus($response->getResponseStatus())
                ->setSecurityKey($response->getSecurityKey())
                ->setStatusDetail($response->getResponseStatusDetail())
                ->setVpsTxId($response->getData('v_ps_tx_id'))
                ->setTrnCurrency($request->getData('Currency'))
                ->setTrnAmount($request->getData('Amount'))
                ->setNickname($token_nickname)
                ->setTrndate($this->getDate());
        $trn->save();

        return $response;
    }

    /**
     * Check whether there are CC types set in configuration
     *
     * @return bool
     */
    public function isAvailable($quote = null) {
        return Mage_Payment_Model_Method_Abstract::isAvailable($quote);
    }

    protected function _isInvalid($status) {
        $invalid = false;

        if ($status == self::RESPONSE_CODE_INVALID || $status == self::RESPONSE_CODE_MALFORMED || $status == self::RESPONSE_CODE_ERROR || $status == self::RESPONSE_CODE_REJECTED || $status == self::RESPONSE_CODE_NOTAUTHED) {
            $invalid = true;
        }

        return $invalid;
    }

    public function getRequestUri() {
        $uri = $this->getUrl('post', false);

        return $uri;
    }

    /**
     * Check if current quote is multishipping
     */
    protected function _isMultishippingCheckout() {
        return (bool) Mage::getSingleton('checkout/session')->getQuote()->getIsMultiShipping();
    }

    public function getSidParam() {
        $coreSession = Mage::getSingleton('core/session');
        $sessionIdQueryString = $coreSession->getSessionIdQueryParam() . '=' . $coreSession->getSessionId();

        return $sessionIdQueryString;
    }

    protected function _buildRequest($adminParams = array()) {

        $quoteObj = $this->_getQuote();

        $billing  = $quoteObj->getBillingAddress();
        $shipping = $quoteObj->getShippingAddress();

        $request = new Varien_Object;

        $vendor = $this->getConfigData('vendor');

        $this->_vendorTxC = $this->_getTrnVendorTxCode();

        $confParam = (isset($adminParams['order']['send_confirmation'])) ? '&e=' . (int) $adminParams['order']['send_confirmation'] : '';

        if (isset($adminParams['order']['account']['email'])) {
            $confParam .= '&l=' . urlencode($adminParams['order']['account']['email']);
        }

        if (isset($adminParams['order']['account']['group_id'])) {
            $confParam .= '&g=' . $adminParams['order']['account']['group_id'];
        }

        // Transaction registration action
        $action = $this->getConfigData('payment_action');

        $customerEmail = $this->getCustomerEmail();

        $data                  = array();
        $data['VPSProtocol']   = $this->getVpsProtocolVersion($this->getConfigData('mode'));
        $data['TxType']        = strtoupper($action);
        $data['ReferrerID']    = $this->getConfigData('referrer_id');
        $data['CustomerEMail'] = ($customerEmail == null ? $billing->getEmail() : $customerEmail);
        $data['Vendor']        = $vendor;
        $data['VendorTxCode']  = $this->_vendorTxC;
        if ($this->_getIsAdmin()) {
            $data['User'] = Mage::getSingleton('admin/session')->getUser()->getUsername();
        }
        else {
            $data['User'] = ($customerEmail == null ? $billing->getEmail() : $customerEmail);
        }

        if ((string) $this->getConfigData('trncurrency') == 'store') {
            $data['Amount']   = $this->formatAmount($quoteObj->getGrandTotal(), $quoteObj->getQuoteCurrencyCode());
            $data['Currency'] = $quoteObj->getQuoteCurrencyCode();
        } else if ((string) $this->getConfigData('trncurrency') == 'switcher') {
            if($this->_code == "sagepayserver_moto"){
                $data['Amount']   = $this->formatAmount($quoteObj->getGrandTotal(), $adminParams['order']['currency']);
                $data['Currency'] = $adminParams['order']['currency'];
            }else{
                $data['Amount']   = $this->formatAmount($quoteObj->getGrandTotal(), Mage::app()->getStore()->getCurrentCurrencyCode());
                $data['Currency'] = Mage::app()->getStore()->getCurrentCurrencyCode();
            }
        }
        else {
            $data['Amount']   = $this->formatAmount($quoteObj->getBaseGrandTotal(), $quoteObj->getBaseCurrencyCode());
            $data['Currency'] = $quoteObj->getBaseCurrencyCode();
        }

        $data['Description']       = $this->ss($this->cleanInput($this->getConfigData('purchase_description') . ' User: ', 'Text') . $data['User'], 100);
        $data['NotificationURL']   = $this->getNotificationUrl() . $confParam;
        $data['SuccessURL']        = $this->getSuccessUrl() . $confParam;
        $data['RedirectURL']       = $this->getRedirectUrl() . $confParam;
        $data['FailureURL']        = $this->getFailureUrl() . $confParam;
        $data['BillingSurname']    = $this->ss($billing->getLastname(), 20);
        $data['BillingFirstnames'] = $this->ss($billing->getFirstname(), 20);
        $data['BillingAddress1']   = ($this->getConfigData('mode') == 'test') ? 88 : $this->ss($billing->getStreet(1), 100);
        $data['BillingAddress2']   = ($this->getConfigData('mode') == 'test') ? 88 : $this->ss($billing->getStreet(2), 100);
        $data['BillingPostCode']   = ($this->getConfigData('mode') == 'test') ? 412 : $this->sanitizePostcode($this->ss($billing->getPostcode(), 10));
        $data['BillingCity']       = $this->ss($billing->getCity(), 40);
        $data['BillingCountry']    = $billing->getCountry();
        $data['BillingPhone']      = $this->ss($this->_cphone($billing->getTelephone()), 20);

        // Set delivery information for virtual products ONLY orders
        if ($quoteObj->getIsVirtual()) {
            $data['DeliverySurname']    = $this->ss($billing->getLastname(), 20);
            $data['DeliveryFirstnames'] = $this->ss($billing->getFirstname(), 20);
            $data['DeliveryAddress1']   = $this->ss($billing->getStreet(1), 100);
            $data['DeliveryAddress2']   = $this->ss($billing->getStreet(2), 100);
            $data['DeliveryCity']       = $this->ss($billing->getCity(), 40);
            $data['DeliveryPostCode']   = $this->sanitizePostcode($this->ss($billing->getPostcode(), 10));
            $data['DeliveryCountry']    = $billing->getCountry();
            $data['DeliveryPhone']      = $this->ss($this->_cphone($billing->getTelephone()), 20);
        }
        else {
            $data['DeliveryPhone']      = $this->ss($this->_cphone($shipping->getTelephone()), 20);
            $data['DeliverySurname']    = $this->ss($shipping->getLastname(), 20);
            $data['DeliveryFirstnames'] = $this->ss($shipping->getFirstname(), 20);
            $data['DeliveryAddress1']   = $this->ss($shipping->getStreet(1), 100);
            $data['DeliveryAddress2']   = $this->ss($shipping->getStreet(2), 100);
            $data['DeliveryCity']       = $this->ss($shipping->getCity(), 40);
            $data['DeliveryPostCode']   = $this->sanitizePostcode($this->ss($shipping->getPostcode(), 10));
            $data['DeliveryCountry']    = $shipping->getCountry();
        }

        if ($data['DeliveryCountry'] == 'US') {
            if ($quoteObj->getIsVirtual()) {
                $data['DeliveryState'] = $billing->getRegionCode();
            } else {
                $data['DeliveryState'] = $shipping->getRegionCode();
            }
        }
        if ($data['BillingCountry'] == 'US') {
            $data['BillingState'] = $billing->getRegionCode();
        }

        if (empty($data['DeliveryPostCode'])) {
            $data['DeliveryPostCode'] = '000';
        }

        if (empty($data['BillingPostCode'])) {
            $data['BillingPostCode'] = '000';
        }

        $data['ContactNumber'] = substr($this->_cphone($billing->getTelephone()), 0, 20);

        $basket = Mage::helper('sagepaysuite')->getSagePayBasket($this->_getQuote(),false);
        if(!empty($basket)) {
            if($basket[0] == "<") {
                $data['BasketXML'] = $basket;
            }
            else {
                $data['Basket'] = $basket;
            }
        }

        $data['Language'] = substr(Mage::app()->getLocale()->getLocaleCode(), 0, 2);

        $data['Website'] = Mage::app()->getStore()->getWebsite()->getName();

        $data['Profile'] = (string)$this->getConfigData('template_profile');

        //Setting template style to NORMAL if customer is redirected to Sage Pay
        $tplStyle = (string)$this->getConfigData('payment_iframe_position');
        if($tplStyle == 'full_redirect') {
            $data['Profile'] = 'NORMAL';
        }

        if ($this->_getIsAdmin() !== false) {
            $data['AccountType']   = 'M';
            $data['Apply3DSecure'] = '2';
        }

        $data['AllowGiftAid'] = (int) $this->getConfigData('allow_gift_aid');
        $data['ApplyAVSCV2']  = $this->getConfigData('avscv2');

        //Skip PostCode and Address Validation for overseas orders
        if((int)Mage::getStoreConfig('payment/sagepaysuite/apply_AVSCV2') === 1){
            if($this->_SageHelper()->isOverseasOrder($billing->getCountry())){
                $data['ApplyAVSCV2'] = 2;
            }
        }

        $customerXML = $this->getCustomerXml($quoteObj);
        if (!is_null($customerXML)) {
            $data['CustomerXML'] = $customerXML;
        }

        //Set to CreateToken if needed
        if($this->_createToken() OR (isset($adminParams['payment']) && isset($adminParams['payment']['remembertoken']))) {
            $data['CreateToken'] = '1';
        }

        $request->setData($data);

        /**
         * Reward Points
         */
        if ($this->_getQuote()->getRewardInstance()) {
            Mage::getSingleton('checkout/session')->setSagePayRewInst($this->_getQuote()->getRewardInstance());
        }

        if ($this->_getQuote()->getCustomerBalanceInstance()) {
            Mage::getSingleton('checkout/session')->setSagePayCustBalanceInst($this->_getQuote()->getCustomerBalanceInstance());
        }
        /**
         * Reward Points
         */
        return $request;
    }

    public function getCustomerEmail() {
        return Mage::helper('customer')->getCustomer()->getEmail();
    }

    public function getOnepage() {
        return Mage::getSingleton('checkout/type_onepage');
    }

    /**
     * Retrieve checkout model
     *
     * @return Mage_Checkout_Model_Type_Multishipping
     */
    public function getMultishipping() {
        return Mage::getSingleton('checkout/type_multishipping');
    }

    protected function _postRequest(Varien_Object $request) {
        $result = Mage::getModel('sagepaysuite/sagepaysuite_result');

        try {

            $requestData = $request->getData();
            $response = $this->requestPost($this->getRequestUri(), $requestData);
        } catch (Exception $e) {

            Mage::logException($e);

            $result->setResponseStatus('ERROR')->setResponseStatusDetail($e->getMessage());

            Mage::dispatchEvent('sagepay_payment_failed', array('quote' => $this->getQuote(), 'message' => $e->getMessage()));

            return $result;
        }

        $r = $response;

        try {

            if (empty($r) || $r['Status'] == 'FAIL') {
                $msg = Mage::helper('sagepaysuite')->__('Sage Pay is not available at this time. Please try again later.');
                $result->setResponseStatus('ERROR')->setResponseStatusDetail($msg);
                Mage::dispatchEvent('sagepay_payment_failed', array('quote' => $this->getQuote(), 'message' => $msg));
                return $result;
            }

            if ($this->_isInvalid($r['Status'])) {
                $result->setResponseStatus($r['Status'])->setResponseStatusDetail(Mage::helper('sagepaysuite')->__($r['StatusDetail']));
                Mage::dispatchEvent('sagepay_payment_failed', array('quote' => $this->getQuote(), 'message' => $r['StatusDetail']));
            }
            else {

                $result->setResponseStatus($r['Status'])->setResponseStatusDetail(Mage::helper('sagepaysuite')->__($r['StatusDetail']))->setNextUrl($r['NextURL'])->setVPSTxID($r['VPSTxId']);
                $result->addData(Mage::helper('sagepaysuite')->arrayKeysToUnderscore($r));
                $result->setTxType($request->getTxType());

                Mage::getSingleton('checkout/session')->setSagePayServerNextUrl($r['NextURL']);

                if (!empty($this->_postPayment)) {
                    $data = $this->_postPayment;
                }
                else {
                    $data = array();
                    $data['method'] = $this->_code;
                }

                if ($this->_isMultishippingCheckout()) {
                    $this->getMultishipping()->setPaymentMethod($data);
                }
                else {

                    if($this->_getIsAdmin()) {
                        $this->_getOrderCreateModel()->setPaymentData($data);
                        $this->_getOrderCreateModel()->getQuote()->getPayment()->addData($data);
                    }
                    else {
                        $this->getOnepage()->savePayment($data);
                    }

                }

                // Set values on SagePayServer session's namespace
                $this->_getSagePayServerSession()
                        ->setSecurityKey($r['SecurityKey'])
                        ->setVendorTxCode($this->_vendorTxC)->setTrnDoneStatus($r['Status'] . '_' . strtoupper($requestData['TxType']))
                        ->setVpsTxId($r['VPSTxId'])
                        ->setTxAuthNo((isset($r['TxAuthNo']) ? $r['TxAuthNo'] : null))
                        ->setNextUrl($r['NextURL']);
            }
        } catch (Exception $e) {
            Mage::throwException($e->getMessage());
            Mage::logException($e);

            Mage::dispatchEvent('sagepay_payment_failed', array('quote' => $this->getQuote(), 'message' => $e->getMessage()));

            $result->setResponseStatus('ERROR')->setResponseStatusDetail($e->getMessage());
            return $result;
        }

        return $result;
    }

    public function capture(Varien_Object $payment, $amount) {
        #Process invoice
        if (!$payment->getRealCapture()) {
            return $this->captureInvoice($payment, $amount);
        }
    }

    protected function _getOrderCreateModel() {
        return Mage::getSingleton('adminhtml/sales_order_create');
    }

    /**
     * @return array
     */
    public function getConfigSafeFields() {
        return array(
            'active',
            'mode',
            'title',
            'iframe_label',
            'auto_advance',
            'auto_show_iframe',
            'iframe_height',
            'iframe_width',
            'token_iframe_height',
            'token_iframe_width',
            'payment_iframe_position',
        );
    }

}

