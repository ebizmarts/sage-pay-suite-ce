<?php

/**
 * API model access for SagePay
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */
class Ebizmarts_SagePaySuite_Model_Api_Payment extends Mage_Payment_Model_Method_Cc {

    protected $_code = '';
    protected $_canManageRecurringProfiles = false;
    protected $_quote = null;
    protected $_canEdit = TRUE;

    const BASKET_SEP                           = ':';
    const BASKET_SEP_ESCAPE                    = '-';
    const RESPONSE_DELIM_CHAR                  = "\r\n";
    const REQUEST_BASKET_ITEM_DELIMITER        = ':';
    const RESPONSE_CODE_APPROVED               = 'OK';
    const RESPONSE_CODE_REGISTERED             = 'REGISTERED';
    const RESPONSE_CODE_DECLINED               = 'OK';
    const RESPONSE_CODE_ABORTED                = 'OK';
    const RESPONSE_CODE_AUTHENTICATED          = 'OK';
    const RESPONSE_CODE_REJECTED               = 'REJECTED';
    const RESPONSE_CODE_INVALID                = 'INVALID';
    const RESPONSE_CODE_ERROR                  = 'ERROR';
    const RESPONSE_CODE_NOTAUTHED              = 'NOTAUTHED';
    const RESPONSE_CODE_3DAUTH                 = '3DAUTH';
    const RESPONSE_CODE_MALFORMED              = 'MALFORMED';
    const REQUEST_TYPE_PAYMENT                 = 'PAYMENT';
    const REQUEST_TYPE_VOID                    = 'VOID';
    const XML_CREATE_INVOICE                   = 'payment/sagepaydirectpro/create_invoice';
    const REQUEST_METHOD_CC                    = 'CC';
    const REQUEST_METHOD_ECHECK                = 'ECHECK';
    const ACTION_AUTHORIZE_CAPTURE             = 'payment';

    protected $ACSURL = NULL;
    protected $PAReq = NULL;
    protected $MD = NULL;

    private $_sharedConf = array(
                                 'sync_mode',
                                 'email_on_invoice',
                                 'trncurrency',
                                 'referrer_id',
                                 'vendor',
                                 'timeout_message',
                                 'connection_timeout',
                                 'send_basket',
                                 'sagefifty_basket',
                                 'basket_format',
                                 'curl_verifypeer',
                                 'layout_rewrites_active',
                                 'layout_rewrites',
                                 'ignore_address_validation',
                                 'send_payment_failed_emails',
    );

    /**
     * Flag to set if request can be retried.
     *
     * @var boolean
     */
    private $_canRetry = true;

    /**
     * BasketXML related error codes.
     *
     * @var type
     */
    private $_basketErrors = array(3021, 3195, 3177);

    /**
     * Can be edit order (renew order)
     *
     * @return bool
     */
    public function canEdit() {
        return $this->_canEdit;
    }

    protected function _getCoreUrl() {
        return Mage::getModel('core/url');
    }

    public function getTransactionDetails($orderId) {
        return Mage::getModel('sagepaysuite2/sagepaysuite_transaction')->loadByParent($orderId);
    }

    public function getNewTxCode() {
        return substr(time(), 0, 39);
    }

    public function getDate($format = 'Y-m-d H:i:s') {
        return Mage::getModel('core/date')->date($format);
    }

    public function getVpsProtocolVersion($mode = "live") {

        $protocol = '3.00';

        if("simulator" === strtolower($mode)) {
            $protocol = '2.23';
        }

        return $protocol;
    }

    public function getCustomerQuoteId() {
        $id = null;

        if (Mage::getSingleton('adminhtml/session_quote')->getQuoteId()) { #Admin
            $id = Mage::getSingleton('adminhtml/session_quote')->getCustomerId();
        }
        else if (Mage::getSingleton('customer/session')->getCustomerId()) { #Logged in frontend
            $id = Mage::getSingleton('customer/session')->getCustomerId();
        }
        else { #Guest/Register
            $vdata = Mage::getSingleton('core/session')->getVisitorData();
            return (string) $vdata['session_id'];
        }

        return (int) $id;
    }

    public function getCustomerLoggedEmail() {
        $s = Mage::getSingleton('customer/session');
        if ($s->getCustomerId()) {
            return $s->getCustomer()->getEmail();
        }
        return null;
    }

    public function setMcode($code) {
        $this->_code = $code;
        return $this;
    }

    /**
     * Retrieve information from payment configuration
     *
     * @param   string $field
     * @return  mixed
     */
    public function getConfigData($field, $storeId = null) {
        if (null === $storeId) {
            $storeId = $this->getStore();
        }

        if (!in_array($field, $this->_sharedConf)) {
            $_code = $this->getCode();
        } else {
            if (($field == 'vendor') && (strpos($this->getCode(), 'moto') !== FALSE)) {
                $_code = $this->getCode();
            } else {
                $_code = 'sagepaysuite';
            }
        }

        if (($_code != 'sagepaysuite') && (strpos($this->getCode(), 'moto') !== FALSE)) {
            $_code = $this->getCode();
            if (Mage::getSingleton('adminhtml/session_quote')->getStoreId()) {
                $storeId = Mage::getSingleton('adminhtml/session_quote')->getStoreId();
            }
        }

        $path = 'payment/' . $_code . '/' . $field;

        $value = Mage::getStoreConfig($path, $storeId);

        if ($field == 'timeout_message') {
            $store = Mage::app()->getStore($storeId);
            $value = $this->_sageHelper()->__(str_replace(array('{store_name}', '{admin_email}'), array($store->getName(), Mage::getStoreConfig('trans_email/ident_general/email', $storeId)), Mage::getStoreConfig('payment/sagepaysuite/timeout_message', $storeId)));
        }

        $confValue = new stdClass;
        $confValue->value = $value;

        Mage::dispatchEvent('sagepaysuite_get_configvalue_' . $field, array('confobject' => $confValue, 'path' => $path));

        //euro payment pending status
        if($path == "payment/sagepayserver/order_status" && $this->getSageSuiteSession()->getEuroPaymentIsPending() === true){
            $confValue->value = "pending";
        }

        return $confValue->value;
    }

    public function getUrl($key, $tdcall = false, $code = null, $mode = null) {
        if ($tdcall) {
            $key = $key.='3d';
        }

        $_code = (is_null($code) ? $this->getCode() : $code);
        $_mode = (is_null($mode) ? $this->getConfigData('mode') : $mode);

        $urls = Mage::helper('sagepaysuite')->getSagePayUrlsAsArray();

        return $urls[$_code][$_mode][$key];
    }

    public function getTokenUrl($key, $integration) {

        $confKey = "";
        switch($integration){
            case 'direct':
                $confKey = "sagepaydirectpro";
                break;
            case 'server':
                $confKey = "sagepayserver";
                break;
            case 'nit':
                $confKey = "sagepaynit";
                break;
            default:
                $confKey = "sagepaydirectpro";
                break;
        }
        $urls = Mage::helper('sagepaysuite')->getSagePayUrlsAsArray();
        return $urls['sagepaytoken'][Mage::getStoreConfig('payment/' . $confKey . '/mode', Mage::app()->getStore()->getId())][$integration . $key];
    }

    public function getSidParam() {
        $coreSession = Mage::getSingleton('core/session');
        $sessionIdQueryString = $coreSession->getSessionIdQueryParam() . '=' . $coreSession->getSessionId();

        return $sessionIdQueryString;
    }

    public function getTokenModel() {
        return Mage::getModel('sagepaysuite/sagePayToken');
    }

    public static function log($data, $level = null, $file = null) {
        Sage_Log::log($data, $level, $file);
    }

    protected function _tokenPresent() {
        try {
            $present = (bool) ((int) $this->getInfoInstance()->getSagepayTokenCcId() !== 0);
        } catch (Exception $e) {

            if ((int) $this->getSageSuiteSession()->getLastSavedTokenccid() !== 0) {
                $present = true;
            } else {
                $present = false;
            }
        }

        return $present;
    }

    protected function _createToken() {
        try {
            $create = (bool) ((int) $this->getInfoInstance()->getRemembertoken() !== 0);
        } catch (Exception $e) {

            if((int)$this->getSageSuiteSession()->getRemembertoken(true) === 1) {
                $create = true;
            }
            else {
                $create = false;
            }

        }

        return $create;
    }

    protected function _setRequestCurrencyAmount($request, $quote) {

        if ($quote->getMacAmount()) {
            $quote = clone $quote;
            $quote->setGrandTotal($quote->getMacAmount());
            $quote->setBaseGrandTotal($quote->getBaseMacAmount());
        }

        $quote2 = $this->getQuoteDb($quote);

        //Fix problem on STORE config for MOTO orders
        $trnCurrency = (string)$this->getConfigData('trncurrency', $quote2->getStoreId());

        if ($trnCurrency == 'store') {
            $request->setAmount($this->formatAmount($quote2->getGrandTotal(), $quote2->getQuoteCurrencyCode()));
            $request->setCurrency($quote2->getQuoteCurrencyCode());
        }
        else if ($trnCurrency == 'switcher') {

            if($this->_getIsAdmin()) {
                $currencyCode = $quote2->getQuoteCurrencyCode();
            }
            else {
                $currencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();
            }

            $request->setAmount($this->formatAmount($quote2->getGrandTotal(), $currencyCode));
            $request->setCurrency($currencyCode);
        }
        else {
            $request->setAmount($this->formatAmount($quote2->getBaseGrandTotal(), $quote2->getBaseCurrencyCode()));
            $request->setCurrency($quote2->getBaseCurrencyCode());
        }

    }

    public function assignData($data) {

        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }

        $info = $this->getInfoInstance();

        if (!$data->getSagepayTokenCcId() && $this->getSageSuiteSession()->getLastSavedTokenccid()) {
            $data->setSagepayTokenCcId($this->getSageSuiteSession()->getLastSavedTokenccid());
        } else {

            if ($data->getSagepayTokenCcId()) {
                //This check is because OSC set_methods_separate posts data and its not complete sometimes
                //Attention: Server with OSC will still have this problem since cv2 is asked on iframe
                if (($data->getMethod() == 'sagepayserver' || $data->getMethod() == 'sagepayserver_moto')
                        || $data->getTokenCvv()) {
                    $this->getSageSuiteSession()->setLastSavedTokenccid($data->getSagepayTokenCcId());
                }
            }

        }

        //$this->getSageSuiteSession()->setTokenCvv($data->getTokenCvv());

        if ($this->isMobile()) {
            $cct = Mage::getSingleton('sagepaysuite/config')->getTranslateCc();
            if (in_array($data->getCcType(), $cct)) {
                $cctF = array_flip($cct);
                $data->setCcType($cctF[$data->getCcType()]);
            }
        }

        //Direct GiftAidPayment flag
        $dgift = (!is_null($data->getCcGiftaid()) ? 1 : NULL);

        //Remember token
        $info->setRemembertoken((!is_null($data->getRemembertoken()) ? 1 : 0));

        $info->setCcType($data->getCcType())
                ->setCcOwner($data->getCcOwner())
                ->setCcLast4(substr($data->getCcNumber(), -4))
                ->setCcNumber($data->getCcNumber())
                ->setCcCid($data->getCcCid())
                ->setSagepayTokenCcId($data->getSagepayTokenCcId())
                ->setCcExpMonth($data->getCcExpMonth())
                ->setCcExpYear($data->getCcExpYear())
                ->setCcIssue($data->getCcIssue())
                ->setSaveTokenCc($data->getSavecc())
                ->setTokenCvv($data->getTokenCvv())
                ->setCcStartMonth($data->getCcStartMonth())
                ->setCcStartYear($data->getCcStartYear())
                ->setCcNickname($data->getCcNickname())
                ->setCcGiftaid($dgift);
        return $this;
    }

    protected function _getQuote() {

        $opQuote = Mage::getSingleton('checkout/type_onepage')->getQuote();
        $adminQuote = Mage::getSingleton('adminhtml/session_quote')->getQuote();

        $rqQuoteId = Mage::app()->getRequest()->getParam('qid');

        if ($adminQuote->hasItems() === false && (int) $rqQuoteId) {
//            Mage::getSingleton('checkout/type_onepage')->setQuote(
//                Mage::getModel('sales/quote')->loadActive($rqQuoteId)
//            );
            $opQuote->setQuote(Mage::getModel('sales/quote')->loadActive($rqQuoteId));
        }

        return ($adminQuote->hasItems() === true) ? $adminQuote : $opQuote;
    }

    public function getQuote() {
        return $this->_getQuote();
    }

    public function getQuoteDb($sessionQuote) {

        return $sessionQuote;

        /*
        @TODO: work on this further, it causes 0.00 Amount sometimes.
        $resource = $sessionQuote->getResource();
        $dbQuote = new Mage_Sales_Model_Quote;
        $resource->loadActive($dbQuote, $sessionQuote->getId());

        //For MOTO
        if( !((int)$dbQuote->getId()) ) {
            $resource->loadByIdWithoutStore($dbQuote, $sessionQuote->getId());
        }

        if( !((int)$dbQuote->getId()) ) {
            $dbQuote = $sessionQuote;
        }

        return $dbQuote;*/
    }

    /**
     * Check if current quote is multishipping
     */
    protected function _isMultishippingCheckout() {
        return (bool) Mage::getSingleton('checkout/session')->getQuote()->getIsMultiShipping();
    }

    public function cleanInput($strRawText, $strType) {
        if ($strType == "Number") {
            $strClean = "0123456789.";
            $bolHighOrder = false;
        } else
        if ($strType == "VendorTxCode") {
            $strClean = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_.";
            $bolHighOrder = false;
        } else {
            $strClean = " ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789.,'/{}@():?-_&�$=%~<>*+\"";
            $bolHighOrder = true;
        }

        $strCleanedText = "";
        $iCharPos = 0;

        do {
            // Only include valid characters
            $chrThisChar = substr($strRawText, $iCharPos, 1);

            if (strspn($chrThisChar, $strClean, 0, strlen($strClean)) > 0) {
                $strCleanedText = $strCleanedText . $chrThisChar;
            } else
            if ($bolHighOrder == true) {
                // Fix to allow accented characters and most high order bit chars which are harmless
                if (bin2hex($chrThisChar) >= 191) {
                    $strCleanedText = $strCleanedText . $chrThisChar;
                }
            }

            $iCharPos = $iCharPos + 1;
        } while ($iCharPos < strlen($strRawText));

        $cleanInput = ltrim($strCleanedText);
        return $cleanInput;
    }

    protected function _cleanString($text) {
        $pattern = '|[^a-zA-Z0-9\-\._]+|';
        $text = preg_replace($pattern, '', $text);

        return $text;
    }

    protected function _cphone($phone) {
        return preg_replace('/[^a-zA-Z0-9\s]/', '', $phone);
    }

    /**
     * Validate postcode based on Sage Pay rules.
     *
     * @param string $text
     *
     * @return string
     */
    public function sanitizePostcode($text) {
        return preg_replace("/[^a-zA-Z0-9-\s]/", "", $text);
        //return $text;
    }

    public function cleanString($text) {
        return $this->_cleanString($text);
    }

    protected function _getAdminSession() {
        return Mage::getSingleton('adminhtml/session');
    }

    /**
     * Check if admin is logged in
     * @return bool
     */
    protected function _getIsAdmin() {
        return (bool) (Mage::getSingleton('admin/session')->isLoggedIn());
    }

    /**
     * Check if current transaction is from the Backend
     * @return bool
     */
    protected function _getIsAdminOrder() {
        return (bool) (Mage::getSingleton('admin/session')->isLoggedIn() &&
                Mage::getSingleton('adminhtml/session_quote')->getQuoteId());
    }

    /**
     * Return commno data for *all* transactions.
     * @return array Data
     */
    public function _getGeneralTrnData(Varien_Object $payment, $adminParams = array()) {
        $order    = $payment->getOrder();
        $quoteObj = $this->_getQuote();

        $vendorTxCode = $this->_getTrnVendorTxCode();

//        if ($payment->getCcNumber()) {
//            $vendorTxCode .= $this->_cleanString(substr($payment->getCcOwner(), 0, 10));
//        }
        $payment->setVendorTxCode($vendorTxCode);

        $request = new Varien_Object;
        $request->setVPSProtocol((string) $this->getVpsProtocolVersion($this->getConfigData('mode')))
                ->setReferrerID($this->getConfigData('referrer_id'))
                ->setVendor($this->getConfigData('vendor'))
                ->setVendorTxCode($vendorTxCode);

        $request->setClientIPAddress($this->getClientIp());

        if ($payment->getIntegra()) { //Server

            if(is_array($adminParams) && !empty($adminParams)) {
                $confParam = (isset($adminParams['order']['send_confirmation'])) ? '&e=' . (int) $adminParams['order']['send_confirmation'] : '';

                if (isset($adminParams['order']['account']['email'])) {
                    $confParam .= '&l=' . urlencode($adminParams['order']['account']['email']);
                }

                if (isset($adminParams['order']['account']['group_id'])) {
                    $confParam .= '&g=' . $adminParams['order']['account']['group_id'];
                }
            }
            else {
                $confParam = '';
            }

            $this->getSageSuiteSession()->setLastVendorTxCode($vendorTxCode);
            $request->setIntegration($payment->getIntegra());
            $request->setData('notification_URL', $this->getNotificationUrl() . '&vtxc=' . $vendorTxCode . $confParam);
            $request->setData('success_URL', $this->getSuccessUrl());
            $request->setData('redirect_URL', $this->getRedirectUrl());
            $request->setData('failure_URL', $this->getFailureUrl());
        }

        if ($this->_getIsAdminOrder()) {
            $request->setAccountType('M');
        }

        if ($payment->getAmountOrdered()) {
            $this->_setRequestCurrencyAmount($request, $quoteObj);
        }

        if (!empty($order)) {

            $billing = $order->getBillingAddress();
            if (!empty($billing)) {
                $request->setBillingAddress($billing->getStreet(1) . ' ' . $billing->getCity() . ' ' .
                                $billing->getRegion() . ' ' . $billing->getCountry()
                        )
                        ->setBillingSurname($this->ss($billing->getLastname(), 20))
                        ->setBillingFirstnames($this->ss($billing->getFirstname(), 20))
                        ->setBillingPostCode($this->sanitizePostcode($this->ss($billing->getPostcode(), 10)))
                        ->setBillingAddress1($this->ss($billing->getStreet(1), 100))
                        ->setBillingAddress2($this->ss($billing->getStreet(2), 100))
                        ->setBillingCity($this->ss($billing->getCity(), 40))
                        ->setBillingCountry($billing->getCountry())
                        ->setContactNumber(substr($this->_cphone($billing->getTelephone()), 0, 20));

                if ($billing->getCountry() == 'US') {
                    $request->setBillingState($billing->getRegionCode());
                }

                $request->setCustomerEMail($billing->getEmail());
            }

            if (!$request->getDescription()) {
                $request->setDescription('.');
            }

            $shipping = $order->getShippingAddress();

            if(!$quoteObj->isVirtual()) {
                $request->setDeliveryAddress($shipping->getStreet(1) . ' ' . $shipping->getCity() . ' ' .
                                $shipping->getRegion() . ' ' . $shipping->getCountry()
                        )
                        ->setDeliverySurname($this->ss($shipping->getLastname(), 20))
                        ->setDeliveryFirstnames($this->ss($shipping->getFirstname(), 20))
                        ->setDeliveryPostCode($this->sanitizePostcode($this->ss($shipping->getPostcode(), 10)))
                        ->setDeliveryAddress1($this->ss($shipping->getStreet(1), 100))
                        ->setDeliveryAddress2($this->ss($shipping->getStreet(2), 100))
                        ->setDeliveryCity($this->ss($shipping->getCity(), 40))
                        ->setDeliveryCountry($shipping->getCountry())
                        ->setDeliveryPhone($this->ss(urlencode($this->_cphone($shipping->getTelephone())), 20));

                if ($shipping->getCountry() == 'US') {
                    $request->setDeliveryState($shipping->getRegionCode());
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
                        ->setDeliveryCity($this->ss($billing->getCity(), 40))
                        ->setDeliveryCountry($billing->getCountry())
                        ->setDeliveryPhone($this->ss(urlencode($this->_cphone($billing->getTelephone())), 20));

                if ($billing->getCountry() == 'US') {
                    $request->setDeliveryState($billing->getRegionCode());
                }
            }
        }
        if ($payment->getCcNumber()) {
            $request->setCardNumber($payment->getCcNumber())
                    ->setExpiryDate(sprintf('%02d%02d', $payment->getCcExpMonth(), substr($payment->getCcExpYear(), strlen($payment->getCcExpYear()) - 2)))
                    ->setCardType($payment->getCcType())
                    ->setCV2($payment->getCcCid())
                    ->setCardHolder($payment->getCcOwner());

            if ($payment->getCcIssue()) {
                $request->setIssueNumber($payment->getCcIssue());
            }
            if ($payment->getCcStartMonth() && $payment->getCcStartYear()) {
                $request->setStartDate(sprintf('%02d%02d', $payment->getCcStartMonth(), substr($payment->getCcStartYear(), strlen($payment->getCcStartYear()) - 2)));
            }
        }

        $basket = Mage::helper('sagepaysuite')->getSagePayBasket($quoteObj,false);
        if(!empty($basket)) {
            if($basket[0] == "<") {
                $request->setBasketXML($basket);
            }
            else {
                $request->setBasket($basket);
            }
        }

        if (!$request->getDeliveryPostCode()) {
            $request->setDeliveryPostCode('000');
        }
        if (!$request->getBillingPostCode()) {
            $request->setBillingPostCode('000');
        }

        return $request;
    }

    /**
     * Invoice existing order
     *
     * @param int $id Order id
     * @param string $captureMode Mode capture, OFFLINE-ONLINE-NOTCAPTURE
     */
    public function invoiceOrder($id = null, $captureMode = Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE, $silent = true) {

        if (is_object($id)) {
            $order = $id;
        }
        else {
            $order = Mage::getModel('sales/order')->load($id);
        }

        try {
            if (!$order->canInvoice()) {
                $emessage = $this->_getCoreHelper()->__('Cannot create an invoice.');
                if (!$silent) {
                    Mage::throwException($emessage);
                }
                Sage_Log::log($emessage);
                return false;
            }

            $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();

            if (!$invoice->getTotalQty()) {
                $emessage = $this->_getCoreHelper()->__('Cannot create an invoice without products.');
                if (!$silent) {
                    Mage::throwException($emessage);
                }
                Sage_Log::log($emessage);
                return false;
            }

            Mage::unregister('current_invoice');

            Mage::register('current_invoice', $invoice);

            $invoice->setRequestedCaptureCase($captureMode);

            # New in 1.4.2.0, if there is not such value, only REFUND OFFLINE shows up
            # @see Mage_Sales_Model_Order_Payment::registerCaptureNotification
            //$invoice->setTransactionId($order->getSagepayInfo()->getId());
            $invoice->setTransactionId(time());

            $invoice->register();

            //Send email
            $sendemail = (bool) $this->getConfigData('email_on_invoice');
            $invoice->setEmailSent($sendemail);

            //If using Magemaven_OrderComment, change this to TRUE, otherwise
            //comment is not visible on email.
            //$invoice->getOrder()->setCustomerNoteNotify($sendemail);

            $transactionSave = Mage::getModel('core/resource_transaction')
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder());

            $transactionSave->save();

            if ($sendemail) {
                try {
                    $invoice->sendEmail(TRUE, '');
                } catch (Exception $em) {
                    Mage::logException($em);
                }
            }

            return true;
        } catch (Mage_Core_Exception $e) {
            if (!$silent) {
                Mage::throwException($e->getMessage());
            }
            Sage_Log::logException($e);
            return false;
        }
    }

    public function getClientIp() {
        $remote_ip = Mage::helper('core/http')->getRemoteAddr();
        //check if more than one IP:
        $all_ips = explode(", ", $remote_ip);
        if(count($all_ips)>1){
            $remote_ip = $all_ips[count($all_ips)-1];
        }

        //Workaround for SagePay not supporting IPv6
        if(filter_var($remote_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $remote_ip = "0.0.0.0";
        }

        return $remote_ip;
    }

    /**
     * Get product customize options
     *
     * @return array || false
     */
    protected function _getProductOptions($item) {
        $options = array();

        //This HELPER does not exist on all Magento versions
        $helperClass = Mage::getConfig()->getHelperClassName('catalog/product_configuration');
        if (FALSE === class_exists($helperClass, FALSE)) {
            return $options;
        }

        $helper = Mage::helper('catalog/product_configuration');
        if ($item->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_SIMPLE) {
            $options = $helper->getCustomOptions($item);
        } elseif ($item->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
            $options = $helper->getConfigurableOptions($item);
        }

        return $options;
    }

    protected function _getCoreHelper() {
        return Mage::helper('core');
    }

    protected function _sageHelper() {
        return Mage::helper('sagepaysuite');
    }

    /**
     * Return Multishipping Checkout ACTIVE Step.
     */
    public function getMsActiveStep() {
        return Mage::getSingleton('checkout/type_multishipping_state')->getActiveStep();
    }

    public function isMsOnOverview() {
        return ($this->_getQuote()->getIsMultiShipping() && $this->getMsActiveStep() == 'multishipping_overview');
    }

    protected function _getReservedOid() {

        if ($this->isMsOnOverview() && ($this->_getQuote()->getPayment()->getMethod() == 'sagepayserver')) {
            return null;
        }

        $orderId = $this->getSageSuiteSession()->getReservedOrderId();

        if (!$orderId) {

            // we need to check here if the orderId has already been used by other payment method
            if (!$this->_getQuote()->getReservedOrderId() || $this->_orderIdAlreadyUsed($this->_getQuote()->getReservedOrderId())) {
                $this->_getQuote()->unsReservedOrderId();
                $this->_getQuote()->reserveOrderId()->save();
                // Commenting ->save() if save is performed and Amasty_Promo is installed an exception is thrown.
                //$this->_getQuote()->reserveOrderId();
            }

            $orderId = $this->_getQuote()->getReservedOrderId();
            $this->getSageSuiteSession()->setReservedOrderId($orderId);
        }

        if ($this->isMsOnOverview()) {
            $this->getSageSuiteSession()->setReservedOrderId(null);
        }
        return $orderId;
    }

    protected function _orderIdAlreadyUsed($orderId) {
        // just in case there is no orderId provided
        if (!$orderId)
            return false;

        // let's check now if it has been used for another order
        $potentialExistingOrder = Mage::getModel("sales/order")->loadByIncrementId($orderId);

        // if there is an order we should have it loaded by now
        if (!$potentialExistingOrder->getId()) {
            return false;
        }

        return true;
    }

    protected function _getTrnVendorTxCode() {
        //@ToDo: If Amasty_Promo is present, _getReserverOid() creates this error
        /*
        An error occurred with Sage Pay:
        SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update a child row:
         * a foreign key constraint fails (`shop_rush_test`.`sales_flat_quote_item_option`, CONSTRAINT `FK_5F20E478CA64B6891EA8A9D6C2735739`
         * FOREIGN KEY (`item_id`) REFERENCES `sales_flat_quote_item` (`item_id`) ON DELETE CASCADE ON UP)
         */
        $rsOid = str_replace("/", "-", $this->_getReservedOid());
        $prefix = Mage::getStoreConfig('payment/sagepaysuite/vendor_tx_prefix', Mage::app()->getStore()->getId());
        $prefix = preg_replace("/[^a-zA-Z0-9]+/", "", $prefix);
        $prefix = substr($prefix, 0, 5);

        return $prefix . (($rsOid) ? substr($rsOid . '-' . date('Y-m-d-H-i-s'), 0, 40) : substr(date('Y-m-d-H-i-s-') . time(), 0, 40));
    }

    protected function _getRqParams() {
        return Mage::app()->getRequest()->getParams();
    }

    protected function _getBuildPaymentObject($quoteObj, $params = array('payment' => array())) {
        $payment = new Varien_Object;
        if (isset($params['payment']) && !empty($params['payment'])) {
            $payment->addData($params['payment']);
        }

        if (Mage::helper('sagepaysuite')->creatingAdminOrder()) {
            $payment->addData($quoteObj->getPayment()->toArray());
        }

        //nit payment
        if(array_key_exists('nit_card_identifier',$params)){
            $payment->setNitCardIdentifier($params['nit_card_identifier']);
        }

        $payment->setTransactionType(strtoupper($this->getConfigData('payment_action')));
        $payment->setAmountOrdered($this->formatAmount($quoteObj->getGrandTotal(), $quoteObj->getQuoteCurrencyCode()));
        $payment->setRealCapture(true); //To difference invoice from capture
        $payment->setOrder( (clone $quoteObj) );
        $payment->setAnetTransType(strtoupper($this->getConfigData('payment_action')));
        $payment->getOrder()->setOrderCurrencyCode($quoteObj->getQuoteCurrencyCode());
        $payment->getOrder()->setBillingAddress($quoteObj->getBillingAddress());

        if($quoteObj->isVirtual()) {
            $payment->getOrder()->setShippingAddress($quoteObj->getBillingAddress());
        }
        else {
            $payment->getOrder()->setShippingAddress($quoteObj->getShippingAddress());
        }

        return $payment;
    }

    public function getConfigCurrencyCode($quoteObj) {
        $code = null;

        $currencyCode = (string) $this->getConfigData('trncurrency', $quoteObj->getStoreId());

        if ($currencyCode == 'store') {
            $code = $quoteObj->getQuoteCurrencyCode();
        } else if ($currencyCode == 'switcher') {
            $code = Mage::app()->getStore()->getCurrentCurrencyCode();
        }
        else {
            $code = $quoteObj->getBaseCurrencyCode();
        }

        return $code;
    }

    public function saveAction($orderId, $request, $result) {
        $model = Mage::getModel('sagepaysuite2/sagepaysuite_action')->setParentId($orderId);

        $model->setStatus($result['Status'])
                ->setStatusDetail($result['StatusDetail'])
                ->setActionCode(strtolower($request['TxType']))
                ->setActionDate($this->getDate());

        //Add additional transaction data to action
        if(isset($result['AVSCV2'])) {
            $model->setAvscv2($result['AVSCV2']);
        }
        if(isset($result['AddressResult'])) {
            $model->setAddressResult($result['AddressResult']);
        }
        if(isset($result['PostCodeResult'])) {
            $model->setPostcodeResult($result['PostCodeResult']);
        }
        if(isset($result['CV2Result'])) {
            $model->setCv2result($result['CV2Result']);
        }
        if(isset($result['DeclineCode'])) {
            $model->setDeclineCode($result['DeclineCode']);
        }
        if(isset($result['BankAuthCode'])) {
            $model->setBankAuthCode($result['BankAuthCode']);
        }

        return $model->save();
    }

    /**
     * Returns real payment method code.
     * @param string $dbName Name on db, direct/server
     * @return string Real module code
     */
    protected function _getIntegrationCode($dbName) {
        switch ($dbName) {
            case 'direct':
                return 'sagepaydirectpro';
                break;
            case 'server':
                return 'sagepayserver';
                break;
            case 'form':
                return 'sagepayform';
                break;
            case 'nit':
                return 'sagepaynit';
                break;
            default:
                return '';
                break;
        }
    }

    /**
     * Cancel payment (VOID)
     * @param   Varien_Object $invoicePayment
     * @return  Ebizmarts_SagePaySuite_Model_Api_Payment
     */
    public function cancelOrder(Varien_Object $payment) {
        $order = $payment->getOrder();
        $trn = $this->getTransactionDetails($order->getId());

        if (!$trn->getId()) {

            $msg = $this->_getCoreHelper()->__('Sagepay local transaction does not exist, order id -> %s', $order->getId());
            $this->_getAdminSession()->addError($msg);

            self::log($msg);
            Mage::logException(new Exception($msg));
            return $this;
        }

        if($trn->getEuroPaymentsStatus() === null || $trn->getEuroPaymentsStatus() == "OK"){
            //if it's not an euro payment I try to cancel the sagepay transaction

                $this->voidPayment($trn);

        }else{
            $trn->setAborted(1)->save();

            //set order status
            $order->setStatus("canceled")->save();

            //send canceled email
            $comment = "<b>Your payment failed due to error " . $trn->getStatusDetail() . "</b>";
            $order->sendOrderUpdateEmail(true, $comment);
        }

        return $this;
    }

    public function abortPayment($trn) {

        /**
         * SecurityKey from the "Admin & Access API"
         */
        if (!$trn->getSecurityKey() && strtoupper($trn->getIntegration()) == 'FORM') {
            $this->_addSecurityKey($trn);
        }

        $data = array();
        $data['VPSProtocol']  = $trn->getVpsProtocol();
        $data['TxType']       = self::REQUEST_TYPE_ABORT;
        $data['ReferrerID']   = $this->getConfigData('referrer_id');
        $data['Vendor']       = $trn->getVendorname();
        $data['VendorTxCode'] = $trn->getVendorTxCode();
        $data['VPSTxId']      = $trn->getVpsTxId();
        $data['SecurityKey']  = $trn->getSecurityKey();
        $data['TxAuthNo']     = $trn->getTxAuthNo();

        try {
            $result = $this->requestPost($this->getUrl('abort', false, $this->_getIntegrationCode($trn->getIntegration()), $trn->getMode()), $data);
        } catch (Exception $e) {
            Sage_Log::logException($e);
            Mage::throwException($this->_getHelper()->__('Transaction could not be aborted at SagePay. You may want to delete it from the local database and check the transaction at the SagePay admin panel.'));
        }

        if ($result['Status'] != 'OK') {

            $statusDetail = $result['StatusDetail'];

            Sage_Log::log($statusDetail);

            //For expired DEFERRED transactions
            if(1 === preg_match('/^4039/i', $statusDetail) ||
               1 === preg_match('/^4028/i', $statusDetail)) {
                $this->_getAdminSession()->addError("Order canceled but an error occurred at SagePay: " . $statusDetail);
            }
            else {
                Mage::throwException(Mage::helper('sagepaysuite')->__($statusDetail));
            }

        }
        else {
            $this->saveAction($trn->getOrderId(), $data, $result);
            $trn->setAborted(1)->save();
        }

    }

    public function voidPayment($trn) {

        /**
         * SecurityKey from the "Admin & Access API"
         */
        if (!$trn->getSecurityKey() && strtoupper($trn->getIntegration()) == 'FORM') {
            $this->_addSecurityKey($trn);
        }

        $data = array();
        $data['VPSProtocol'] = $trn->getVpsProtocol();
        $data['TxType'] = self::REQUEST_TYPE_VOID;
        $data['ReferrerID'] = $this->getConfigData('referrer_id');
        $data['Vendor'] = $trn->getVendorname();
        $data['VendorTxCode'] = $trn->getVendorTxCode();
        $data['VPSTxId'] = $trn->getVpsTxId();
        $data['SecurityKey'] = $trn->getSecurityKey();
        $data['TxAuthNo'] = $trn->getTxAuthNo();

        try {
            $result = $this->requestPost($this->getUrl('void', false, $this->_getIntegrationCode($trn->getIntegration()), $trn->getMode()), $data);
        } catch (Exception $e) {
            Mage::throwException($this->_getHelper()->__('Transaction could not be voided at SagePay. You may want to delete it from the local database and check the transaction at the SagePay admin panel.'));
        }

        if ($result['Status'] != 'OK') {

            foreach($data as $key => $value)
            {
                if(empty($value)) {
                    Mage::throwException("Unable to VOID, required data is missing for the transaction.");
                }
            }

            Sage_Log::log($result['StatusDetail']);
            Mage::throwException(Mage::helper('sagepaysuite')->__($result['StatusDetail']));
        }

        $this->saveAction($trn->getOrderId(), $data, $result);

        $trn->setVoided(1)->save();
    }

    protected function _getAdminQuote() {
        return Mage::getSingleton('adminhtml/session_quote')->getQuote();
    }

    public function loadQuote($quoteId, $storeId) {
        return Mage::getModel('sales/quote')->setStoreId($storeId)->load($quoteId);
    }

    public function getNotificationUrl() {
        if ($this->_getIsAdmin()) {

            return Mage::getSingleton('adminhtml/url')
                ->getUrl('adminhtml/spsServerPayment/notifyAdminOrder', array('_secure' => true,
                                                                                      '_nosid' => true,
                                                                                      'form_key' => Mage::getSingleton('core/session')->getFormKey(),
                                                                                      '_nosecret' => true)) . '?' . $this->getSidParam();
        } else {
            $params = array('_secure' => true);

            return $this->_getCoreUrl()->addSessionParam()->getUrl('sgps/ServerPayment/notify', array_merge($params, $this->_getServerUrlParams()));
        }
    }

    protected function _getServerUrlParams() {
        $params = array();

        if ($this->_isMultishippingCheckout() === true) {
            $params ['multishipping'] = 1;
        }

        $params ['storeid'] = Mage::app()->getStore()->getId();
        //$params ['qid'] = (int) Mage::app()->getRequest()->getParam('qid');
        $params ['qid'] = (int) Mage::getSingleton('checkout/session')->getQuoteId();

        return $params;
    }

    public function getSuccessUrl() {
        if ($this->_getIsAdmin()) {
            return Mage :: getModel('adminhtml/url')->getUrl('adminhtml/spsServerPayment/success', array(
                '_secure' => true,
                '_nosid' => true
            )) . '?' . $this->getSidParam();
        } else {
            $params = array('_secure' => true);
            return $this->_getCoreUrl()->addSessionParam()->getUrl('sgps/ServerPayment/success', array_merge($params, $this->_getServerUrlParams()));
        }
    }

    public function getRedirectUrl() {
        if ($this->_getIsAdmin()) {
            return Mage :: getModel('adminhtml/url')->getUrl('adminhtml/spsServerPayment/redirect', array(
                '_secure' => true,
                '_nosid' => true
            )) . '?' . $this->getSidParam();
        } else {
            $params = array('_secure' => true);
            return $this->_getCoreUrl()->addSessionParam()->getUrl('sgps/payment/redirect', array_merge($params, $this->_getServerUrlParams()));
        }
    }

    public function getFailureUrl() {
        if ($this->_getIsAdmin()) {
            return Mage :: getModel('adminhtml/url')->getUrl('adminhtml/spsServerPayment/failure', array(
                '_secure' => true,
                #'form_key' => Mage::getSingleton('core/session')->getFormKey(),
                '_nosid' => true
            )) . '?' . $this->getSidParam();
        } else {
            $params = array('_secure' => true);
            return $this->_getCoreUrl()->addSessionParam()->getUrl('sgps/ServerPayment/failure', array_merge($params, $this->_getServerUrlParams()));
        }
    }

    /**
     * Recover a transaction, creates an order in Magento and adds payment data
     * from an approved transaction that has no order.
     *
     * @param type $vendorTxCode
     * @return type
     */
    public function recoverTransaction($vendorTxCode) {

        //@TODO: Fix this for configurable products.

        $trn = Mage::getModel('sagepaysuite2/sagepaysuite_transaction')
                ->loadByVendorTxCode($vendorTxCode);

        if (is_null($trn->getId())) {
            Mage::throwException($this->_sageHelper()->__('Transaction "%s" not found.', $vendorTxCode));
        }

        if (!is_null($trn->getOrderId())) {
            Mage::throwException($this->_sageHelper()->__('Transaction "%s" is already associated to an order, no need to recover it.', $vendorTxCode));
        }

        $quote = $this->loadQuote($trn->getQuoteId(), $trn->getStoreId());
        if (!$quote->getId()) {
            Mage::throwException($this->_sageHelper()->__('Quote could not be loaded for "%s".', $vendorTxCode));
        }

        Mage::register('Ebizmarts_SagePaySuite_Model_Api_Payment::recoverTransaction', $vendorTxCode);

        $o = Mage::getModel('sagepaysuite/createOrder', $quote)->create();

        return $o;

    }

    public function showPost() {
        $this->_code = 'direct';
        $showPostUrl = 'https://test.sagepay.com/showpost/showpost.asp';

        $data = array();
        $data ['SuiteModuleVersion'] = (string) Mage::getConfig()->getNode('modules/Ebizmarts_SagePaySuite/version');
        $data ['Vendor'] = uniqid();

        $this->requestPost($showPostUrl, $data, true);

        return $data['Vendor'];
    }

    /**
     * Send a post request with cURL
     * @param string $url URL to POST to
     * @param array $data Data to POST
     * @return array|string $result Result of POST
     */
    public function requestPost($url, $data, $returnRaw = false) {

        //$storeId = $this->getStoreId();
        $aux = $data;

        if (isset($aux['CardNumber'])) {
            $aux['CardNumber'] = substr_replace($aux['CardNumber'], "XXXXXXXXXXXXX", 0, strlen($aux['CardNumber']) - 3);
        }
        if (isset($aux['CV2'])) {
            $aux['CV2'] = "XXX";
        }

        ksort($aux);

        $rd = '';
        foreach ($data as $_key => $_val) {
            if ($_key == 'billing_address1')
                $_key = 'BillingAddress1';
            $rd .= $_key . '=' . urlencode(mb_convert_encoding($_val, 'ISO-8859-1', 'UTF-8')) . '&';
        }

        $userAgent = $this->_sageHelper()->getUserAgent();

        self::log($url, null, 'SagePaySuite_REQUEST.log');
        self::log("User-Agent: " . Mage::helper('core/http')->getHttpUserAgent(false), null, 'SagePaySuite_REQUEST.log');
        self::log($userAgent, null, 'SagePaySuite_REQUEST.log');
        self::log($aux, null, 'SagePaySuite_REQUEST.log');

        $_timeout = (int) $this->getConfigData('connection_timeout');
        $timeout = ($_timeout > 0 ? $_timeout : 90);

        $output = array();

        $curlSession = curl_init();

        $sslversion = Mage::getStoreConfig('payment/sagepaysuite/curl_ssl_version');
        curl_setopt($curlSession, CURLOPT_SSLVERSION, $sslversion);
        curl_setopt($curlSession, CURLOPT_USERAGENT, $userAgent);
        curl_setopt($curlSession, CURLOPT_URL, $url);
        curl_setopt($curlSession, CURLOPT_HEADER, 0);
        curl_setopt($curlSession, CURLOPT_POST, 1);
        curl_setopt($curlSession, CURLOPT_POSTFIELDS, $rd);
        curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curlSession, CURLOPT_TIMEOUT, $timeout);

        if(Mage::getStoreConfigFlag('payment/sagepaysuite/curl_proxy') == 1){
            curl_setopt($curlSession, CURLOPT_PROXY, Mage::getStoreConfig('payment/sagepaysuite/curl_proxy_port'));
        }

        $verifyPeerConfig = (int)$this->getConfigData('curl_verifypeer');
        $verifyPeer       = 1 === $verifyPeerConfig ? true : false;
        curl_setopt($curlSession, CURLOPT_SSL_VERIFYPEER, $verifyPeer);

        curl_setopt($curlSession, CURLOPT_SSL_VERIFYHOST, 2);

        $rawresponse = curl_exec($curlSession);

        if (true === $returnRaw) {
            return $rawresponse;
        }

        self::log($rawresponse, null, 'SagePaySuite_RawResponse.log');
        self::log(curl_getinfo($curlSession, CURLINFO_HTTP_CODE), Zend_Log::ALERT, 'SagePaySuite_REQUEST.log');
        self::log(curl_getinfo($curlSession, CURLINFO_EFFECTIVE_URL), Zend_Log::ALERT, 'SagePaySuite_REQUEST.log');

        //Split response into name=value pairs
        $response = explode(chr(10), $rawresponse);

        // Check that a connection was made
        if (curl_error($curlSession)) {

            self::log(curl_error($curlSession), Zend_Log::ALERT, 'SagePaySuite_REQUEST.log');
            self::log(curl_error($curlSession), Zend_Log::ALERT, 'Connection_Errors.log');

            $output['Status'] = 'FAIL';
            $output['StatusDetail'] = htmlentities(curl_error($curlSession)) . '. ' . $this->getConfigData('timeout_message');

            return $output;
        }

        curl_close($curlSession);

        // Tokenise the response
        for ($i = 0; $i < count($response); $i++) {
            // Find position of first "=" character
            $splitAt = strpos($response[$i], "=");
            // Create an associative (hash) array with key/value pairs ('trim' strips excess whitespace)
            $arVal = (string) trim(substr($response[$i], ($splitAt + 1)));
            if (!empty($arVal)) {
                $output[trim(substr($response[$i], 0, $splitAt))] = $arVal;
            }
        }

        //Resend same request if fails because of basket related errors.
        if( $this->_canRetry){
            if(isset($output['StatusDetail']) && (isset($output['Status']) && ($output['Status'] == 'INVALID')) ) {

                for ($i = 0; $i < count($this->_basketErrors); $i++) {
                    if (1 === preg_match('/^' . $this->_basketErrors[$i] . '/i', $output['StatusDetail'])) {

                        if (isset($data['BasketXML'])) {
                            unset($data['BasketXML']);

                            self::log($output, null, 'SagePaySuite_REQUEST.log');
                            self::log("Basket ERROR, retrying without BasketXML in POST ...", null, 'SagePaySuite_REQUEST.log');

                            return $this->requestPost($url, $data, false);
                        }

                    }
                }
            }else{
                
            }
        }

        self::log($output, null, 'SagePaySuite_REQUEST.log');

        return $output;
    }

    public function getSendBasket() {
        return ((int) $this->getConfigData('send_basket') === 1 ? true : false);
    }

    protected function _getRequest() {
        return Mage::getModel('sagepaysuite/sagepaysuite_request');
    }

    public function getSageSuiteSession() {
        return Mage::getSingleton('sagepaysuite/session');
    }

    protected function _isInViewOrder() {
        $r = Mage::getModel('core/url')->getRequest();
        return (bool) ($r->getActionName() == 'view' && $r->getControllerName() == 'sales_order');
    }

    public function getTitle() {
        $mode = $this->getConfigData('mode');
        if ($mode == 'live' || $this->_isInViewOrder() === true || $this->getCode() == 'sagepaypaypal') {
            return parent::getTitle();
        }

        return parent::getTitle() . ' - ' . Mage::helper('sagepaysuite')->__('%s mode', strtoupper($mode));
    }

    public function isServer() {
        return (bool) ($this->getCode() == 'sagepayserver');
    }

    public function isDirect() {
        return (bool) ($this->getCode() == 'sagepaydirectpro');
    }

    public function isMobile() {
        return Mage::helper('sagepaysuite')->isMobileApp();
    }

    /**
     * Trim $string to certaing $length
     */
    public function ss($string, $length) {
        return substr($string, 0, $length);
    }

    protected function _addSecurityKey($trn) {
        $trnDetails = Mage::getModel('sagepayreporting/sagepayreporting')->getTransactionDetails($trn->getVendorTxCode(), null);
        if ($trnDetails->getErrorcode() != '0000') {
            Mage::throwException($trnDetails->getError());
        }
        $formSecKey = (string) $trnDetails->getSecuritykey();
        $trn->setSecurityKey($formSecKey)
                ->save();
    }

    /**
     * Format amount based on currency
     *
     * @param float $amount
     * @param string $currency
     * @return float|int
     */
    public function formatAmount($amount, $currency) {
        $_amount = 0.00;

        //JPY, which only accepts whole number amounts
        if ($currency == 'JPY') {
            $_amount = round($amount, 0, PHP_ROUND_HALF_EVEN);
        }
        else {
            $_amount = number_format(Mage::app()->getStore()->roundPrice($amount), 2, '.', '');
        }

        return $_amount;
    }

    /**
     * Sage50 compatible Basket.
     *
     * @param  Mage_Sales_Model_Quote $quote
     * @return string Basket as string.
     */
    /*
    public function getSageBasket($quote) {

        $basket = '';

        $fakeColon = "%$%!this^must&be±unique!%$%";

        //$orderCurrencyCode = $this->getConfigCurrencyCode($quote);
        //$_currency         = Mage::getModel('directory/currency')->load($orderCurrencyCode);

        $useBaseMoney = true;

        $trnCurrency = (string)$this->getConfigData('trncurrency', $quote->getStoreId());
        if ($trnCurrency == 'store' or $trnCurrency == 'switcher') {
            $useBaseMoney = false;
        }

        $itemsCollection = $quote->getItemsCollection();
        if ($itemsCollection->getSize() > 0) {

            $numberOfdetailLines = $itemsCollection->getSize() + 1;
            $todelete = 0;

            foreach ($itemsCollection as $item) {
                if ($item->getParentItem()) { # Configurable products
                    $numberOfdetailLines--;
                }
            }

            $basket .= $numberOfdetailLines . $fakeColon;

            foreach ($itemsCollection as $item) {

                //Avoid duplicates SKUs on basket
                if (strpos($basket, ($this->_cleanString($item->getSku()) . '|')) !== FALSE) {
                    continue;
                }
                if ($item->getParentItem()) {
                    continue;
                }

                $tax = ($item->getBaseTaxBeforeDiscount() ? $item->getBaseTaxBeforeDiscount() : ($item->getBaseTaxAmount() ? $item->getBaseTaxAmount() : 0));

                $calculationPrice = 0.00;
                if($useBaseMoney) {
                    $calculationPrice = $item->getBaseCalculationPrice();
                }
                else {
                    $calculationPrice = $item->getCalculationPrice();
                }

                //Options
                $options = $this->_getProductOptions($item);
                $_options = '';
                if (count($options) > 0) {
                    foreach ($options as $opt) {
                        $_options .= $opt['label'] . '-' . $opt['value'] . '.';
                    }
                    $_options = '_' . substr($_options, 0, -1) . '_';
                }

                //[SKU]|Name
                $line = str_replace($fakeColon, '-', '[' . $this->_cleanString($item->getSku()) . ']|' . $item->getName()) . $this->_cleanString($_options) . $fakeColon;

                //Quantity
                $line .= ( $item->getQty() * 1) . $fakeColon;

                //if ($this->getConfigData('sagefifty_basket')) {
                $taxAmount = number_format(($item->getTaxAmount() / ($item->getQty() * 1)), 2);

                //Item value
                $line .= $calculationPrice . $fakeColon;

                //Item tax
                $line .= number_format($taxAmount, 2) . $fakeColon;

                //Item total
                $line .= number_format($calculationPrice + $taxAmount, 2) . $fakeColon;

                if($useBaseMoney) {
                    $rowTotal = $item->getBaseRowTotal();
                }
                else {
                    $rowTotal = $item->getRowTotal();
                }

                //Line total
                $line .= (($rowTotal + $tax) - $item->getDiscountAmount()) . $fakeColon;

                //add item to string if not too large
                if (strlen($basket . $line) < 7498) {
                    $basket .= $line;
                }
                else {
                    $todelete++;
                }
            }
        }

        //Delivery data
        $shippingAddress = $quote->getShippingAddress();

        if($useBaseMoney) {
            $deliveryValue  = $shippingAddress->getBaseShippingAmount();
            $deliveryTax    = $shippingAddress->getBaseShippingTaxAmount();
            $deliveryAmount = $shippingAddress->getBaseShippingInclTax();
        }
        else {
            $deliveryValue  = $shippingAddress->getShippingAmount();
            $deliveryTax    = $shippingAddress->getShippingTaxAmount();
            $deliveryAmount = $shippingAddress->getShippingInclTax();
        }

        $deliveryName = $shippingAddress->getShippingDescription() ? $shippingAddress->getShippingDescription() : 'Delivery';
        $delivery = $deliveryName . $fakeColon . '1' . $fakeColon . $deliveryValue . $fakeColon
                    . $deliveryTax . $fakeColon . $deliveryAmount . $fakeColon . $deliveryAmount;

        //add delivery to string if not too large
        if (strlen($basket . $delivery) < 7498) {
            $basket .= $delivery;
        }
        else {
            $todelete++;
        }

        $numberOfLines = substr($basket, 0, strpos($basket, $fakeColon));

        if ($todelete > 0) {
            $num    = $numberOfLines - $todelete;
            $basket = str_replace($numberOfLines, $num, $basket);
        }


        // Verify that items count is correct

        $items = explode($fakeColon, $basket);
        //Remove line number from basket
        array_shift($items);
        //Split into rows
        $rows = count(array_chunk($items, 6));
        if ($rows != $numberOfLines) {
            $basket[0] = $rows;
        }

        //Verify that items count is correct


        $basket = str_replace(self::BASKET_SEP,self::BASKET_SEP_ESCAPE,$basket);
        $basket = str_replace($fakeColon,self::BASKET_SEP,$basket);

        //verify that last char is not the separator
        $lastCharacters = substr($basket, strlen(self::BASKET_SEP) * -1);
        if($lastCharacters == self::BASKET_SEP){
            //remove last separator
            $basket = substr($basket, 0, strlen(self::BASKET_SEP) * -1);
        }

        return $basket;
    }
    */

    public function getSageBasket($quote) {

        $basketArray = array();
        $useBaseMoney = true;

        $trnCurrency = (string)$this->getConfigData('trncurrency', $quote->getStoreId());
        if ($trnCurrency == 'store' or $trnCurrency == 'switcher') {
            $useBaseMoney = false;
        }

        $itemsCollection = $quote->getItemsCollection();
        if ($itemsCollection->getSize() > 0) {

            foreach ($itemsCollection as $item) {

                //Avoid duplicates SKUs on basket
                if ($this->_isSkuDuplicatedInSageBasket($basketArray,$this->_cleanString($item->getSku())) == true) {
                    continue;
                }
                //Avoid configurables
                if ($item->getParentItem()) {
                    continue;
                }

                $newItem = array("item"=>"",
                    "qty"=>0,
                    "item_value"=>0,
                    "item_tax"=>0,
                    "item_total"=>0,
                    "line_total"=>0,);

                $itemQty = $item->getQty() * 1;


                if($useBaseMoney){

                    $itemDiscount = $item->getBaseDiscountAmount() / $itemQty;

                    $taxAmount = number_format($item->getBaseTaxAmount() / $itemQty, 3);

                    $itemValue = $item->getBasePriceInclTax() - $taxAmount - $itemDiscount;

                }else{

                    $itemDiscount = $item->getDiscountAmount() / $itemQty;

                    $taxAmount = number_format($item->getTaxAmount() / $itemQty, 3);

                    $itemValue = $item->getPriceInclTax() - $taxAmount - $itemDiscount;

                }

                $itemTotal = $itemValue + $taxAmount;

                //Options
                $options = $this->_getProductOptions($item);
                $_options = '';
                if (count($options) > 0) {
                    foreach ($options as $opt) {
                        $_options .= $opt['label'] . '-' . $opt['value'] . '.';
                    }
                    $_options = '_' . substr($_options, 0, -1) . '_';
                }

                //[SKU] Name
                $newItem["item"] = str_replace(self::BASKET_SEP, self::BASKET_SEP_ESCAPE, '[' . $this->_cleanString($item->getSku()) . '] ' . $this->_cleanString($item->getName()) . $this->_cleanString($_options));

                //Quantity
                $newItem["qty"] = $itemQty;

                //Item value
                $newItem["item_value"] = $itemValue;

                //Item tax
                $newItem["item_tax"] = $taxAmount;

                //Item total
                $newItem["item_total"] = $itemTotal;

                //Line total
                $newItem["line_total"] = $itemTotal * $itemQty;

                //add item to array
                $basketArray[] = $newItem;
            }
        }

        //Delivery data
        $shippingAddress = $quote->getShippingAddress();

        if($useBaseMoney) {
            $deliveryValue  = $shippingAddress->getBaseShippingAmount();
            $deliveryTax    = $shippingAddress->getBaseShippingTaxAmount();
            $deliveryAmount = $shippingAddress->getBaseShippingInclTax();
        }
        else {
            $deliveryValue  = $shippingAddress->getShippingAmount();
            $deliveryTax    = $shippingAddress->getShippingTaxAmount();
            $deliveryAmount = $shippingAddress->getShippingInclTax();
        }

        $deliveryName = $shippingAddress->getShippingDescription() ? $shippingAddress->getShippingDescription() : 'Delivery';

        //delivery item
        $deliveryItem = array("item"=>str_replace(self::BASKET_SEP, self::BASKET_SEP_ESCAPE, $this->_cleanString($deliveryName)),
            "qty"=>1,
            "item_value"=>$deliveryValue,
            "item_tax"=>$deliveryTax,
            "item_total"=>$deliveryAmount,
            "line_total"=>$deliveryAmount,);
        $basketArray[] = $deliveryItem;

        //create basket string
        $basketString = '';
        $rowCount = 0;
        for($i = 0;$i<count($basketArray);$i++){
            $line = self::BASKET_SEP . $basketArray[$i]['item'] .
                self::BASKET_SEP . $basketArray[$i]['qty'] .
                self::BASKET_SEP . $basketArray[$i]['item_value'] .
                self::BASKET_SEP . $basketArray[$i]['item_tax'] .
                self::BASKET_SEP . $basketArray[$i]['item_total'] .
                self::BASKET_SEP . $basketArray[$i]['line_total'];

            if (strlen($basketString . $line) < 7498) {
                $basketString .= $line;
                $rowCount ++;
            }else{
                break;
            }
        }

        //add total rows
        $basketString = $rowCount . $basketString;

        return $basketString;
    }

    private function _isSkuDuplicatedInSageBasket($basketArray,$itemSku){
        for($i = 0;$i<count($basketArray);$i++){
            if(strpos($basketArray[$i]['item'], $itemSku) !== FALSE){
                return true;
                break;
            }
        }
        return false;
    }

    /**
     * The basket can be passed as an xml document with extra information that
     * can be used for more accurate fraud screening through ReD.
     *
     * @param $quote
     * @return string
     */
    public function getBasketXml($quote) {

                /* @TODO
                 *                 //Options
                $options = $this->_getProductOptions($item);
                $_options = '';
                if (count($options) > 0) {
                    foreach ($options as $opt) {
                        $_options .= $opt['label'] . '-' . $opt['value'] . '.';
                    }
                    $_options = '_' . substr($_options, 0, -1) . '_';
                }*/

        $basket = new Ebizmarts_Simplexml_Element('<basket />');

        if($this->_getIsAdmin()) {

            $uname = trim(Mage::getSingleton('admin/session')->getUser()->getUsername());

            $validAgent = preg_match_all("/[a-zA-Z0-9\s]+/", $uname, $matchesUname);
            if($validAgent !== 1) {
                $uname = implode("", $matchesUname[0]);
            }

            //<agentId>
            $basket->addChildCData('agentId', substr($uname, 0, 16));
        }

        $discount = null;

        $shippingAdd = $quote->getShippingAddress();
        $billingAdd  = $quote->getBillingAddress();

        $itemsCollection   = $quote->getItemsCollection();

        foreach ($itemsCollection as $item) {

            if ($item->getParentItem()) {
                continue;
            }

            $node = $basket->addChild('item', '');

            $itemDesc = trim( substr($item->getName(), 0, 100) );
            $validDescription = preg_match_all("/.*/", $itemDesc, $matchesDescription);
            if($validDescription === 1) {
                //<description>
                $node->addChildCData('description', $this->_convertStringToSafeXMLChar($itemDesc));
            }
            else {
                //<description>
                $node->addChildCData('description', $this->_convertStringToSafeXMLChar(substr(implode("", $matchesDescription[0]), 0, 100)));
            }

            $validSku = preg_match_all("/[\p{L}0-9\s\-]+/", $item->getSku(), $matchesSku);
            if($validSku === 1) {
                //<productSku>
                $node->addChildCData('productSku', substr($item->getSku(), 0, 12));
            }

            //<productCode>
            $node->addChild('productCode', $item->getId());

            //<quantity>
            $node->addChild('quantity', $item->getQty());

            /* Item price data
                unitGrossAmount = unitNetAmount + unitTaxAmount
                totalGrossAmount = unitGrossAmount * quantity
                Amount = Sum of totalGrossAmount + deliveryGrossAmount - Sum of fixed (discounts)
            */

                $weeTaxApplied = $item->getWeeeTaxAppliedAmount();

                $itemQty = ($item->getQty()*1);

                $unitTaxAmount = number_format( ($item->getTaxAmount()/$itemQty), 2, '.', '');

                //$unitNetAmount = number_format(($item->getPrice()+$weeTaxApplied)-($item->getDiscountAmount()/$itemQty), 2, '.', '');
                $unitNetAmount = number_format(($item->getPrice()+$weeTaxApplied), 2, '.', '');

                if($item->getDiscountAmount()) {
                    $discount += $item->getDiscountAmount();
                }

                $unitGrossAmount = number_format($unitNetAmount + $unitTaxAmount, 2, '.', '');

                $totalGrossAmount = number_format($unitGrossAmount * $itemQty, 2, '.', '');

                //<unitNetAmount>
                $node->addChild('unitNetAmount', $unitNetAmount);
                //<unitTaxAmount>
                $node->addChild('unitTaxAmount', $unitTaxAmount);
                //<unitGrossAmount>
                $node->addChild('unitGrossAmount', $unitGrossAmount);
                //<totalGrossAmount>
                $node->addChild('totalGrossAmount', $totalGrossAmount);
            /* Item price data */

            //<recipientFName>
            $recipientFName = $this->_convertStringToSafeXMLChar(substr(trim($shippingAdd->getFirstname()), 0, 20));
            $recipientFName = preg_replace('/[0-9]+/', '', $recipientFName);
            if(!empty($recipientFName)){
                $node->addChildCData('recipientFName', $recipientFName);
            }


            //<recipientLName>
            $recipientLName = $this->_convertStringToSafeXMLChar(substr(trim($shippingAdd->getLastname()), 0, 20));
            $recipientLName = preg_replace('/[0-9]+/', '', $recipientLName);
            if(!empty($recipientLName)){
                $node->addChildCData('recipientLName', $recipientLName);
            }

            //<recipientMName>
            if($shippingAdd->getMiddlename()){
                $recipientMName = $this->_convertStringToSafeXMLChar(substr(trim($shippingAdd->getMiddlename()), 0, 1));
                $recipientMName = preg_replace('/[0-9]+/', '', $recipientMName);
                if(!empty($recipientMName)) {
                    $node->addChildCData('recipientMName', $recipientMName);
                }
            }

            //<recipientSal>
            if($shippingAdd->getPrefix()) {
                $recipientSal = $this->_convertStringToSafeXMLChar(substr(trim($shippingAdd->getPrefix()), 0, 4));
                if(!empty($recipientSal)) {
                    $node->addChildCData('recipientSal', $recipientSal);
                }
            }

            //<recipientEmail>

            if($shippingAdd->getEmail()) {
                $recipientEmail = $this->_convertStringToSafeXMLChar(substr(trim($shippingAdd->getEmail()), 0, 45));
                if(!empty($recipientEmail)) {
                    $node->addChildCData('recipientEmail', $recipientEmail);
                }
            }

            //<recipientPhone>
            $recipientPhone = $this->_convertStringToSafeXMLChar(substr(trim($shippingAdd->getTelephone()), 0, 20));
            if(!empty($recipientPhone)) {
                $node->addChildCData('recipientPhone', $recipientPhone);
            }

            //<recipientAdd1>
            $address1 = $this->_convertStringToSafeXMLChar(substr(trim($shippingAdd->getStreet(1)), 0, 100));
            if(!empty($address1)) {
                $node->addChildCData('recipientAdd1', $address1);
            }

            //<recipientAdd2>
            if($shippingAdd->getStreet(2)) {
                $recipientAdd2 = $this->_convertStringToSafeXMLChar(substr(trim($shippingAdd->getStreet(2)), 0, 100));
                if(!empty($recipientAdd2)) {
                    $node->addChildCData('recipientAdd2', $recipientAdd2);
                }
            }

            //<recipientCity>
            $recipientCity = $this->_convertStringToSafeXMLChar(substr(trim($shippingAdd->getCity()), 0, 40));
            if(!empty($recipientCity)) {
                $node->addChildCData('recipientCity', $recipientCity);
            }

            //<recipientState>
            if($shippingAdd->getCountry() == 'US') {
                if ($quote->getIsVirtual()) {
                    $node->addChild('recipientState', $this->_convertStringToSafeXMLChar(substr(trim($billingAdd->getRegionCode()), 0, 2)));
                }
                else {
                    $node->addChild('recipientState', $this->_convertStringToSafeXMLChar(substr(trim($shippingAdd->getRegionCode()), 0, 2)));
                }
            }

            //<recipientCountry>
            $node->addChild('recipientCountry', $this->_convertStringToSafeXMLChar(substr(trim($shippingAdd->getCountry()), 0, 2)));

            //<recipientPostCode>
            $_postCode = '000';
            if($shippingAdd->getPostcode()) {
                $_postCode = $shippingAdd->getPostcode();
            }
            $node->addChildCData('recipientPostCode', $this->_convertStringToSafeXMLChar($this->sanitizePostcode(substr(trim($_postCode), 0, 9))));

        }

        //Sum up shipping totals when using SERVER with MAC
        if($this->_isMultishippingCheckout() && ($quote->getPayment()->getMethod() == 'sagepayserver') ) {

            $shippingInclTax = $shippingTaxAmount = 0.00;

            $addresses = $quote->getAllAddresses();
            foreach($addresses as $address) {
                $shippingInclTax   += $address->getShippingInclTax();
                $shippingTaxAmount += $address->getShippingTaxAmount();
            }

        }
        else {
            $shippingInclTax   = $shippingAdd->getShippingInclTax();
            $shippingTaxAmount = $shippingAdd->getShippingTaxAmount();
        }

        //if(0 != round($shippingAdd->getShippingInclTax())) {

        //<deliveryNetAmount>
        $basket->addChild('deliveryNetAmount', number_format($shippingAdd->getShippingAmount(), 2, '.', ''));

        //<deliveryTaxAmount>
        $basket->addChild('deliveryTaxAmount', number_format($shippingTaxAmount, 2, '.', ''));

        //<deliveryGrossAmount>
        $basket->addChild('deliveryGrossAmount', number_format($shippingInclTax, 2, '.', ''));

        //}

        //<shippingFaxNo>
        $validFax = preg_match_all("/[a-zA-Z0-9\-\s\(\)\+]+/", trim($shippingAdd->getFax()), $matchesFax);
        if($validFax === 1) {
            $basket->addChildCData('shippingFaxNo', substr(trim($shippingAdd->getFax()), 0, 20));
        }

        //Discounts
        if(!is_null($discount) && $discount > 0.00) {
            $nodeDiscounts = $basket->addChild('discounts', '');
            $_discount = $nodeDiscounts->addChild('discount', '');
            $_discount->addChild('fixed', number_format($discount, 2, '.', ''));
        }

        $xmlBasket = str_replace("\n", "", trim($basket->asXml()));

        return $xmlBasket;
    }

    private function _convertStringToSafeXMLChar($string){

        $safe_regex = '/([a-zA-Z\s\d\+\'\"\/\\\&\:\,\.\-\{\}\@])/';
        $safe_string = "";

        for($i = 0;$i<strlen($string);$i++){
            if(preg_match($safe_regex,substr($string,$i,1)) != FALSE){
                $safe_string .= substr($string,$i,1);
            }else{
                $safe_string .= '-';
            }
        }

        return $safe_string;
    }

    /**
     * Return customer data in xml format.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return string Xml data
     */
    public function getCustomerXml($quote) {

        $_xml = null;
        $checkoutMethod = Mage::getSingleton('checkout/type_onepage')->getCheckoutMethod();

        if ($checkoutMethod) {

            $customer = new Varien_Object;
            switch ($checkoutMethod) {
                case 'register':
                case 'guest':
                    $customer->setMiddlename($quote->getBillingAddress()->getMiddlename());
                    $customer->setPreviousCustomer('0');
                    break;
                case 'customer':
                    //Load customer by Id
                    $customer = $quote->getCustomer();
                    $customer->setPreviousCustomer('1');
                    break;
                default:
                    $customer = $quote->getCustomer();
                    $customer->setPreviousCustomer('0');
                    break;
            }

            $customer->setWorkPhone($quote->getBillingAddress()->getFax());
            $customer->setMobilePhone($quote->getBillingAddress()->getTelephone());

            $xml = new Ebizmarts_Simplexml_Element('<customer />');

            if ($customer->getMiddlename()) {
                $xml->addChild('customerMiddleInitial', substr($customer->getMiddlename(), 0, 1));
            }

            if ($customer->getDob()) {
                $_dob = substr($customer->getDob(), 0, strpos($customer->getDob(), ' '));
                if($_dob != "0000-00-00"){
                    $xml->addChildCData('customerBirth', $_dob); //YYYY-MM-DD
                }
            }

            if ($customer->getWorkPhone()) {
                $xml->addChildCData('customerWorkPhone', substr(str_pad($customer->getWorkPhone(), 11, '0', STR_PAD_RIGHT), 0, 19));
            }

            if ($customer->getMobilePhone()) {
                $xml->addChildCData('customerMobilePhone', substr(str_pad($customer->getMobilePhone(), 11, '0', STR_PAD_RIGHT), 0, 19));
            }

            $xml->addChild('previousCust', $customer->getPreviousCustomer());

            if($customer->getId()) {
                $xml->addChild('customerId', $customer->getId());
            }

            //$xml->addChild('timeOnFile', 10);

            $_xml = str_replace("\n", "", trim($xml->asXml()));
        }

        return $_xml;
    }
    /**
    * Check that two floats are equal
    *
    * @see http://www.php.net/manual/en/language.types.float.php
    * @param float $amount1
    * @param float $amount2
    * @return bool
    */
    public function floatsEqual($amount1, $amount2, $precision = 0.0001) {
        $equal = false;

        if(abs($amount1-$amount2) < $precision) {
            $equal = true;
        }

        return $equal;
    }

    /**
     * Force 3D secure checking based on card rule
     */
    public function forceCardChecking($ccType = null)
    {
        $config = Mage::getStoreConfig("payment/sagepaydirectpro/force_threed_cards");

        if (is_null($ccType) || strlen($config) === 0) {
            return false;
        }

        $config = explode(',', $config);
        if (in_array($ccType, $config)) {
            return true;
        }

        return false;
    }
    public function recurringOthers($oldOrder, $newOrder)
    {
        $rc = New Varien_Object();
        $orderId = $oldOrder->getId();
        $newOrder->setIsRecurring(1);
        $trn    = Mage::getModel('sagepaysuite2/sagepaysuite_transaction')
            ->loadByParent($orderId);
        $amount = $newOrder->getPayment()->getAmountOrdered();

        try {

            $paymentApi = Mage::getModel('sagepaysuite/api_payment');

            $auth = new Varien_Object;

            $paymentApi->setMcode($paymentApi->realIntegrationCode($trn->getIntegration()));
            $repeat = $paymentApi->repeat($trn, $amount);
            if($repeat['Status'] == 'OK') {
                $repeatTransaction = clone $trn;
                $repeatTransaction->setId(null)
                    ->setOrderId($newOrder->getId())
                    ->setReleased(null)
                    ->setStatus($repeat['Status'])
                    ->setStatusDetail($repeat['StatusDetail'])
                    ->setVpsTxId($repeat['VPSTxId'])
                    ->setTxAuthNo($repeat['TxAuthNo'])
                    ->setSecurityKey($repeat['SecurityKey'])
                    ->setIntegration($trn->getIntegration())
                    ->setVendorTxCode($repeat['_requestvendor_'])
                    ->setVpsProtocol($trn->getVpsProtocol())
                    ->setVendorname($trn->getVendorname())
                    ->setMode($trn->getMode())
                    ->setTxType(strtoupper($repeat['_requesttxtype_']))
                    ->setTrnCurrency($trn->getTrnCurrency())
                    ->setTrndate($this->getDate())
                    ->save();
                $auth = Mage::getModel('sagepaysuite2/sagepaysuite_action')
                    ->load($repeat['_requestvendor_'], 'vendor_tx_code');

                $newOrder->getPayment()->setLastTransId($repeat['VPSTxId']);
            }
            else {
                $rc->setPaymentDetails("ERROR: Could not repeat payment.");
                $rc->setPaymentOK(false);
            }

            if($auth->getId()) {
                //$rc->setPaymentDetails($auth->getStatusDetail());
                $rc->setPaymentOK(true);
            }
            else {
                $rc->setPaymentDetails("ERROR: Could not load authorisation.");
                $rc->setPaymentOK(false);
            }
        }
        catch(Exception $e) {
            $rc->setPaymentDetails($e->getMessage());
            $rc->setPaymentOK(false);
            Mage::logException($e);
        }
        return $rc;
    }
    public function recurringFirst()
    {
        return $this;

    }

}
