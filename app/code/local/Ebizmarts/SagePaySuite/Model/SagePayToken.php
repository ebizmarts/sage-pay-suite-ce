<?php

/**
 * TOKEN main model
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */
class Ebizmarts_SagePaySuite_Model_SagePayToken extends Ebizmarts_SagePaySuite_Model_Api_Token {

    protected $_code = 'sagepaytoken';
    protected $_formBlockType = 'sagepaysuite/form_sagePayToken';

    /**
     * Check if the current logged in customer can add a new card.
     * @return bool
     */

    public function customerCanAddCard($methodCode = null, $ccNumber = null, $ccExpireDate = null, $ccType = null) {
        $customerCards = Mage::helper('sagepaysuite/token')->loadCustomerCards()->getSize();
        $maxCards = (int) Mage::getStoreConfig('payment/sagepaysuite/max_token_card');

        if(is_null($ccNumber) && is_null($ccExpireDate) && is_null($ccType)){
            return ($customerCards < $maxCards);
        }

        if(is_string($ccNumber) && strlen($ccNumber) != 4){
            $lastFour = substr($ccNumber,-4);
        }else{
            $lastFour = $ccNumber;
        }

        if(Mage::helper('sagepaysuite/token')->loadCustomerCards($methodCode,$lastFour,$ccType,$ccExpireDate)->getSize() == 0){
            return ($customerCards < $maxCards);
        }

        return FALSE;

    }

    public function getSessionCustomerId() {

        return $this->getCustomerQuoteId();
    }

    /**
     * Save registered token in local DB
     */
    public function persistCard(array $info, $customer_id = null) {

        $sessId = $this->getSessionCustomerId();

        if(empty($sessId) && !empty($customer_id)){
            $sessId = (int)$customer_id;
        }

        //Register checkout
        if(is_string($sessId)) {
            $sessId = null;
        }

        $methodCode = null;

        if($info['Protocol'] == 'direct'){
            $methodCode = 'sagepaydirectpro';
        }else if($info['Protocol'] == 'server'){
            $methodCode = 'sagepayserver';
        }

        // As in some cases we are not able to prevent the token creation we need to delete it after creation if it is not possible to store the token.

        if(!$this->customerCanAddCard($methodCode,$info['CardNumber'],$info['ExpiryDate'],$info['CardType'])){

            $this->removeCard($info['Token']);

            $message = Mage::helper('sagepaysuite')->__('Credit card could not be saved for future use. You already have this card attached to your account or you have reached your account\'s maximum card storage capacity.');
            Mage::getSingleton('core/session')->addWarning($message);

            return FALSE;
        }

        $nickname = "";
        if(array_key_exists('Nickname', $info)){
            $nickname = $info['Nickname'];
        }

        $save = Mage::getModel('sagepaysuite2/sagepaysuite_tokencard')
                ->setToken($info['Token'])
                ->setStatus($info['Status'])
                ->setVendor($info['Vendor'])
                ->setNickname($nickname)
                ->setCardType($info['CardType'])
                ->setExpiryDate($info['ExpiryDate'])
                ->setStatusDetail($info['StatusDetail'])
                ->setProtocol($info['Protocol'])
                ->setCustomerId($sessId)
                ->setStoreId(Mage::app()->getStore()->getId())
                ->setLastFour(substr($info['CardNumber'], -4));

        /*if (is_string($sessId)) {
            $save->setVisitorSessionId($sessId);
        }*/

        $save->save();

        $this->getSageSuiteSession()->setLastSavedTokenccid($save->getId());

        return $save;
    }

    /**
     * Validate if token card is valid for checkout.
     * @return bool
     */
    public function isTokenValid($cardId) {
        $card = Mage::getModel('sagepaysuite2/sagepaysuite_tokencard')
                ->load($cardId);

        if ($card->getId()) {
            if ($card->getCustomerId() == $this->getSessionCustomerId() /*|| $card->getVisitorSessionId() == $this->getSessionCustomerId()*/) {
                return true;
            }
        }

        return false;
    }

    protected function _getNotificationUrl() {
        $adminId = Mage::registry('admin_tokenregister');

        $frontendUrl = Mage::getUrl('sgps/card/registerPost', array('_secure' => true, '_nosid' => true)) . '?' . $this->getSidParam() . '&cid=' . (is_null($this->getSessionCustomerId()) ? "" : $this->getSessionCustomerId());
        $backendUrl = Mage::getModel('adminhtml/url')->addSessionParam()->getUrl('adminhtml/spsToken/registerPost', array('cid' => $adminId, 'form_key' => Mage::getSingleton('core/session')->getFormKey()));

        return ($adminId ? $backendUrl : $frontendUrl);
    }

    public function removeCard($token, $protocol = 'direct') {
        return $this->_postRemove($token, $protocol);
    }

    /**
     * Process a card transaction using a token.
     *
     * @param  Varien_Object $info
     * @return array
     */
    public function tokenTransaction(Varien_Object $info) {

        $sessT       = $this->getSageSuiteSession()->getLastSavedTokenccid();
        $tokenLoadId = (!is_null($sessT) ? $sessT : $info->getPayment()->getSagepayTokenCcId());

        $_t = Mage::getModel('sagepaysuite2/sagepaysuite_tokencard')->load($tokenLoadId);

        $_t->setIsDefault(1)
           ->save();

        $isGuest = Mage::getSingleton('checkout/type_onepage')->getCheckoutMethod() == Mage_Checkout_Model_Type_Onepage::METHOD_GUEST;

        if ($this->_getIsAdmin()) {
            $isGuest = FALSE;
        }

        $postData                   = array();
        $postData                   += $this->_getGeneralTrnData($info->getPayment(), $info->getParameters())->getData();
        $postData['vendortxcode']   = substr($postData['vendor_tx_code'], 0, 40);
        $postData['txtype']         = $info->getPayment()->getTransactionType();
        $postData['InternalTxtype'] = $postData['txtype'];
        $postData['token']          = $_t->getToken();
        $postData['storetoken']     = ($isGuest ? '0' : '1');
        $postData['description']    = '.';
        //$postData['CV2']            = $this->getSageSuiteSession()->getTokenCvv();
        //Mage::log($info->getPayment()->getTokenCvv());
        $postData['CV2']            = $info->getPayment()->getTokenCvv();
        $postData['vendor']         = $this->getConfigData('vendor'); //@TODO: Check this for token MOTO transactions.

        if (array_key_exists('integration', $postData) && strtolower($postData['integration']) == 'server') {
            $postData['Profile'] = 'LOW';
        }

        if (isset($postData['c_v2']) && empty($postData['CV2'])) {
            $postData['CV2'] = $postData['c_v2'];
        }

        $postData = Mage::helper('sagepaysuite')->arrayKeysToCamelCase($postData);

        if (isset($postData['Storetoken'])) {
            $postData['StoreToken'] = $postData['Storetoken'];
            unset($postData['Storetoken']);
        }

        // Do not perform 3D checks on MS checkout
        if ($this->_isMultishippingCheckout()) {
            $postData['Apply3DSecure'] = 2;
        }
        else {
            $postData['Apply3DSecure'] = (int) Mage::getStoreConfig("payment/sagepaydirectpro/secure3d");
        }

        if ($this->forceCardChecking($_t->getCardType()) === true) {
            $postData['Apply3DSecure'] = 3;
        }

        $postData['ApplyAVSCV2'] = (int) $this->getConfigData('avscv2');

        $urlPost = $this->getTokenUrl('post', (isset($postData['Integration']) ? $postData['Integration'] : 'direct'));

        $rs            = $this->requestPost($urlPost, $postData);
        $rs['request'] = new Varien_Object($postData);

        $objRs = new Varien_Object($rs);
        $objRs->setResponseStatus($objRs->getData('Status'))
                ->setResponseStatusDetail($objRs->getData('StatusDetail'));

        $info->getPayment()->setSagePayResult($objRs);

        return $rs;
    }

    public function registerCard(array $data = array(), $persist = false) {

        $postData                = array();
        $postData['VPSProtocol'] = $this->getVpsProtocolVersion();
        $postData['TxType']      = 'TOKEN';
        $postData['Vendor']      = $this->getConfigData('vendor');

        if ($this->_getQuote()->hasItems()) {//Checkout
            if ((string) $this->getConfigData('trncurrency') == 'store') {
                $postData['Currency'] = $this->_getQuote()->getQuoteCurrencyCode();
            } else {
                $postData['Currency'] = $this->_getQuote()->getBaseCurrencyCode();
            }
        }
        else {//Customer account
            $postData['Currency'] = Mage::app()->getStore()->getCurrentCurrencyCode();
        }

        $postData['VendortxCode']    = $this->getNewTxCode();
        $postData['NotificationURL'] = $this->_getNotificationUrl();

        if (array_key_exists('CardType', $data)) { #DIRECT
            $urlPost = $this->getTokenUrl('register', 'direct');
            $methodCode = 'sagepaydirectpro';
            $postData += $data;
        }
        else { #SERVER
            $urlPost = $this->getTokenUrl('register', 'server');
            $methodCode = 'sagepayserver';
            $postData['Profile'] = 'LOW';
        }

        $CardNumber = null;
        $ExpiryDate = null;
        $CardType = null;
        if(!is_null($data)){
            if(array_key_exists("CardNumber",$data)){
                $CardNumber = $data['CardNumber'];
            }
            if(array_key_exists("ExpiryDate",$data)){
                $ExpiryDate = $data['ExpiryDate'];
            }
            if(array_key_exists("CardType",$data)){
                $CardType = $data['CardType'];
            }
        }

        if ($this->customerCanAddCard($methodCode, $CardNumber, $ExpiryDate, $CardType) === false) {
            return array('Status' => 'ERROR', 'StatusDetail' => Mage::helper('sagepaysuite')->__('Credit card could not be saved for future use. You already have this card attached to your account or you have reached your account\'s maximum card storage capacity.'));
        }

        $result = $this->requestPost($urlPost, $postData);

        if (true === $persist && $result['Status'] == 'OK') {
            $this->persistCard($postData+=$result);
        }

        return $result;
    }

    /**
     * Check if TOKEN is enabled on the configuration settings
     * @return bool
     */
    public function isEnabled() {
        return (bool) (Mage::getStoreConfig('payment/sagepaysuite/token_integration') != 'false');
    }

    protected function _postRemove($token, $protocol) {
        $rqData = array();
        $rqData['VPSProtocol'] = $this->getVpsProtocolVersion();
        $rqData['TxType'] = 'REMOVETOKEN';
        $rqData['Vendor'] = $this->getConfigData('vendor');
        $rqData['Token'] = $token;

        return $this->requestPost($this->getTokenUrl('removecard', $protocol), $rqData);
    }

}
