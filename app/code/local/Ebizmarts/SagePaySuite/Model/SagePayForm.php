<?php

/**
 * FORM main model
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */
class Ebizmarts_SagePaySuite_Model_SagePayForm extends Ebizmarts_SagePaySuite_Model_Api_Payment {

    protected $_code = 'sagepayform';
    protected $_formBlockType = 'sagepaysuite/form_sagePayForm';
    protected $_infoBlockType = 'sagepaysuite/info_sagePayForm';

    /**
     * Availability options
     */
    protected $_isGateway = true;
    protected $_canAuthorize = false;
    protected $_canCapture = true;
    protected $_canCapturePartial = false;
    protected $_canRefund = false;
    protected $_canRefundInvoicePartial = false;
    protected $_canVoid = false;
    protected $_canUseInternal = false;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = false;

    public function validate() {
        Mage_Payment_Model_Method_Abstract::validate();
        return $this;
    }

    public function isAvailable($quote = null) {
        return Mage_Payment_Model_Method_Abstract::isAvailable($quote);
    }

    /**
     * Return decrypted "encryption pass" from DB
     */
    public function getEncryptionPass() {
        return Mage::helper('core')->decrypt($this->getConfigData('encryption_pass'));
    }

    public function base64Decode($scrambled) {
        // Fix plus to space conversion issue
        $scrambled = str_replace(" ", "+", $scrambled);
        $output = base64_decode($scrambled);
        return $output;
    }

    public function decrypt($strIn) {
        $cryptPass = $this->getEncryptionPass();

        //** remove the first char which is @ to flag this is AES encrypted
        $strIn = substr($strIn, 1);

        //** HEX decoding
        $strIn = pack('H*', $strIn);

        return $this->removePKCS5Padding(mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $cryptPass, $strIn, MCRYPT_MODE_CBC, $cryptPass));
    }

    public function makeCrypt() {

        $cryptPass = $this->getEncryptionPass();

        if (Zend_Validate::is($cryptPass, 'NotEmpty') === false) {
            Mage::throwException('Encryption Pass is empty.');
        }

        $quoteObj = $this->_getQuote();

        //@TODO: Dont collect totals if Amasty_Promo is present
        $quoteObj->setTotalsCollectedFlag(false)->collectTotals();

        $billing = $quoteObj->getBillingAddress();
        $shipping = $quoteObj->getShippingAddress();

        $customerEmail = $this->getCustomerEmail();

        $data = array();

        $data['CustomerEMail'] = ($customerEmail == null ? $billing->getEmail() : $customerEmail);
        $data['CustomerName'] = $billing->getFirstname() . ' ' . $billing->getLastname();
        $data['VendorTxCode'] = $this->_getTrnVendorTxCode();

        if ((string) $this->getConfigData('trncurrency') == 'store') {
            $data['Amount']   = $this->formatAmount($quoteObj->getGrandTotal(), $quoteObj->getQuoteCurrencyCode());
            $data['Currency'] = $quoteObj->getQuoteCurrencyCode();
        } else if ((string) $this->getConfigData('trncurrency') == 'switcher') {
            $data['Amount']   = $this->formatAmount($quoteObj->getGrandTotal(), Mage::app()->getStore()->getCurrentCurrencyCode());
            $data['Currency'] = Mage::app()->getStore()->getCurrentCurrencyCode();
        }
        else {
            $data['Amount']   = $this->formatAmount($quoteObj->getBaseGrandTotal(), $quoteObj->getBaseCurrencyCode());
            $data['Currency'] = $quoteObj->getBaseCurrencyCode();
        }

        $data['Description'] = $this->cleanInput('product purchase', 'Text');

        $data['SuccessURL'] = Mage::getUrl('sgps/formPayment/success', array(
                    '_secure' => true,
                    '_nosid' => true,
                    'vtxc' => $data['VendorTxCode'],
                    'utm_nooverride' => 1
                ));
        $data['FailureURL'] = Mage::getUrl('sgps/formPayment/failure', array(
                    '_secure' => true,
                    '_nosid' => true,
                    'vtxc' => $data['VendorTxCode'],
                    'utm_nooverride' => 1
                ));

        $data['BillingSurname']    = $this->ss($billing->getLastname(), 20);
        $data['ReferrerID']        = $this->getConfigData('referrer_id');
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

        $basket = Mage::helper('sagepaysuite')->getSagePayBasket($this->_getQuote(),false);
        if(!empty($basket)) {
            if($basket[0] == "<") {
                $data['BasketXML'] = $basket;
            }
            else {
                $data['Basket'] = $basket;
            }
        }

        $data['AllowGiftAid'] = (int)$this->getConfigData('allow_gift_aid');
        $data['ApplyAVSCV2']  = $this->getConfigData('avscv2');

        //Skip PostCode and Address Validation for overseas orders
        if((int)Mage::getStoreConfig('payment/sagepaysuite/apply_AVSCV2') === 1){
            if($this->_SageHelper()->isOverseasOrder($billing->getCountry())){
                $data['ApplyAVSCV2'] = 2;
            }
        }

        $data['SendEmail']    = (string)$this->getConfigData('send_email');

        $vendorEmail = (string) $this->getConfigData('vendor_email');
        if ($vendorEmail) {
            $data['VendorEMail'] = $vendorEmail;
        }

        $data['Website'] = substr(Mage::app()->getStore()->getWebsite()->getName(), 0, 100);

        $eMessage = $this->getConfigData('email_message');
        if($eMessage) {
           $data['eMailMessage'] = substr($eMessage, 0, 7500);
        }

        $customerXML = $this->getCustomerXml($quoteObj);
        if (!is_null($customerXML)) {
            $data['CustomerXML'] = $customerXML;
        }

        if (empty($data['DeliveryPostCode'])) {
            $data['DeliveryPostCode'] = '000';
        }

        if (empty($data['BillingPostCode'])) {
            $data['BillingPostCode'] = '000';
        }

        $dataToSend = '';
        foreach ($data as $field => $value) {
            if ($value != '') {
                $dataToSend .= ($dataToSend == '') ? "$field=$value" : "&$field=$value";
            }
        }

        ksort($data);

        Sage_Log::log("User-Agent: " . Mage::helper('core/http')->getHttpUserAgent(false), null, 'SagePaySuite_REQUEST.log');
        Sage_Log::log(Mage::helper('sagepaysuite')->getUserAgent(), null, 'SagePaySuite_REQUEST.log');
        Sage_Log::log($data, null, 'SagePaySuite_REQUEST.log');

        Mage::getModel('sagepaysuite2/sagepaysuite_transaction')
                ->loadByVendorTxCode($data['VendorTxCode'])
                ->setVendorTxCode($data['VendorTxCode'])
                ->setVpsProtocol($this->getVpsProtocolVersion())
                ->setVendorname($this->getConfigData('vendor'))
                ->setMode($this->getConfigData('mode'))
                ->setTxType(strtoupper($this->getConfigData('payment_action')))
                ->setTrnCurrency($data['Currency'])
                ->setIntegration('form')
                ->setTrndate($this->getDate())
                ->setTrnAmount($data['Amount'])
                ->save();

        Mage::getSingleton('sagepaysuite/session')->setLastVendorTxCode($data['VendorTxCode']);

        //** add PKCS5 padding to the text to be encypted
        $pkcs5Data = $this->addPKCS5Padding($dataToSend);

        $strCrypt = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $cryptPass, $pkcs5Data, MCRYPT_MODE_CBC, $cryptPass);

        return "@" . bin2hex($strCrypt);
    }

    //** PHP's mcrypt does not have built in PKCS5 Padding, so we use this
    public function addPKCS5Padding($input) {
        $blocksize = 16;
        $padding = "";

        // Pad input to an even block size boundary
        $padlength = $blocksize - (strlen($input) % $blocksize);
        for ($i = 1; $i <= $padlength; $i++) {
            $padding .= chr($padlength);
        }

        return $input . $padding;
    }

    // Need to remove padding bytes from end of decoded string
    public function removePKCS5Padding($decrypted) {
        $padChar = ord($decrypted[strlen($decrypted) - 1]);

        return substr($decrypted, 0, -$padChar);
    }

    public function capture(Varien_Object $payment, $amount) {
        #Process invoice
        if (!$payment->getRealCapture()) {
            return $this->captureInvoice($payment, $amount);
        }
    }

}
