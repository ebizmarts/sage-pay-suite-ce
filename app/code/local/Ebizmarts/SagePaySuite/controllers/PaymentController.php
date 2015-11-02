<?php

/**
 * SUITE payment controller
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */
class Ebizmarts_SagePaySuite_PaymentController extends Mage_Core_Controller_Front_Action {

    protected function _expireAjax() {
        if (!Mage::getSingleton('checkout/session')->getQuote()->hasItems()) {
            $this->getResponse()->setHeader('HTTP/1.1', '403 Session Expired');
            exit;
        }
    }

    public function getSageSuiteSession() {
        return Mage::getSingleton('sagepaysuite/session');
    }

    protected function _getQuote() {
        return Mage::getSingleton('sagepaysuite/api_payment')->getQuote();
    }

    /**
     * Return all customer cards list for onepagecheckout use.
     */
    public function getTokenCardsHtmlAction() {
        $html = '';

        $_code = $this->getRequest()->getPost('payment_method', 'sagepaydirectpro');

        try {

            $html .= $this->getLayout()->createBlock('sagepaysuite/form_tokenList', 'token.cards.li')
                    ->setCanUseToken(true)
                    ->setPaymentMethodCode($_code)
                    ->toHtml();
        } catch (Exception $e) {
            Ebizmarts_SagePaySuite_Log :: we($e);
        }

        return $this->getResponse()->setBody(str_replace(array(
                            '<div id="tokencards-payment-' . $_code . '">',
                            '</div>'
                                ), array(), $html));
    }

    public function getOnepage() {
        return Mage::getSingleton('checkout/type_onepage');
    }

    private function _OSCSaveBilling() {
        $helper = Mage::helper('onestepcheckout/checkout');

        $billing_data = $this->getRequest()->getPost('billing', array());
        $shipping_data = $this->getRequest()->getPost('shipping', array());
        $customerAddressId = $this->getRequest()->getPost('billing_address_id', false);
        $shippingAddressId = $this->getRequest()->getPost('shipping_address_id', false);

        $billing_data = $helper->load_exclude_data($billing_data);
        $shipping_data = $helper->load_exclude_data($shipping_data);

        if (!Mage::helper('customer')->isLoggedIn()) {

            $emailExists = Mage::helper('sagepaysuite')->existsCustomerForEmail($billing_data['email']);

            $regWithoutPassword = (int)Mage::getStoreConfig('onestepcheckout/registration/registration_order_without_password');
            if(1 === $regWithoutPassword && $emailExists) {
                // Place order on the emails account without the password
                $customer = Mage::getModel('customer/customer')->setWebsiteId(Mage::app()->getStore()->getWebsiteId())->loadByEmail($billing_data['email']);
                Mage::getSingleton('customer/session')->setCustomerAsLoggedIn($customer);
            }
            else {

                $registration_mode = Mage::getStoreConfig('onestepcheckout/registration/registration_mode');
                if ($registration_mode == 'auto_generate_account') {
                    // Modify billing data to contain password also
                    $password = Mage::helper('onestepcheckout/checkout')->generatePassword();
                    $billing_data['customer_password'] = $password;
                    $billing_data['confirm_password'] = $password;
                    $this->getOnepage()->getQuote()->getCustomer()->setData('password', $password);
                    $this->getOnepage()->getQuote()->setData('password_hash', Mage::getModel('customer/customer')->encryptPassword($password));

                    $this->getOnepage()->getQuote()->setData('customer_email', $billing_data['email']);
                    $this->getOnepage()->getQuote()->setData('customer_firstname', $billing_data['firstname']);
                    $this->getOnepage()->getQuote()->setData('customer_lastname', $billing_data['lastname']);
                }


                if ($registration_mode == 'require_registration' || $registration_mode == 'allow_guest') {
                    if (!empty($billing_data['customer_password']) && !empty($billing_data['confirm_password']) && ($billing_data['customer_password'] == $billing_data['confirm_password'])) {
                        $password = $billing_data['customer_password'];
                        $this->getOnepage()->getQuote()->setCheckoutMethod('register');
                        $this->getOnepage()->getQuote()->getCustomer()->setData('password', $password);

                        $this->getOnepage()->getQuote()->setData('customer_email', $billing_data['email']);
                        $this->getOnepage()->getQuote()->setData('customer_firstname', $billing_data['firstname']);
                        $this->getOnepage()->getQuote()->setData('customer_lastname', $billing_data['lastname']);

                        $this->getOnepage()->getQuote()->setData('password_hash', Mage::getModel('customer/customer')->encryptPassword($password));
                    }
                }

                if (!empty($billing_data['customer_password']) && !empty($billing_data['confirm_password'])) {
                    // Trick to allow saving of
                    Mage::getSingleton('checkout/type_onepage')->saveCheckoutMethod('register');
                }

            }

        }//Create Account hook

        //Thanks Dan Norris for his input about this code.
        //if (Mage::helper('customer')->isLoggedIn() && $helper->differentShippingAvailable()) {
        if (Mage::helper('customer')->isLoggedIn()) {
            if (!empty($customerAddressId)) {
                $billingAddress = Mage::getModel('customer/address')->load($customerAddressId);
                if (is_object($billingAddress) && $billingAddress->getCustomerId() == Mage::helper('customer')->getCustomer()->getId()) {
                    $billing_data = array_merge($billing_data, $billingAddress->getData());
                }
            }
            //if (!empty($shippingAddressId)) {
            if (!empty($shippingAddressId) && $helper->differentShippingAvailable()) {
                $shippingAddress = Mage::getModel('customer/address')->load($shippingAddressId);
                if (is_object($shippingAddress) && $shippingAddress->getCustomerId() == Mage::helper('customer')->getCustomer()->getId()) {
                    $shipping_data = array_merge($shipping_data, $shippingAddress->getData());
                }
            }
        }

        if (!empty($billing_data['use_for_shipping'])) {
            $shipping_data = $billing_data;
        }

        $this->getOnepage()->getQuote()->getBillingAddress()->addData($billing_data)->implodeStreetAddress()->setCollectShippingRates(true);

        $paymentMethod = $this->getRequest()->getPost('payment_method', false);
        $selectedMethod = $this->getOnepage()->getQuote()->getPayment()->getMethod();

        $store = $this->getOnepage()->getQuote() ? $this->getOnepage()->getQuote()->getStoreId() : null;
        $methods = $helper->getActiveStoreMethods($store, $this->getOnepage()->getQuote());

        if ($paymentMethod && !empty($methods) && !in_array($paymentMethod, $methods)) {
            $paymentMethod = false;
        }

        if (!$paymentMethod && $selectedMethod && in_array($selectedMethod, $methods)) {
            $paymentMethod = $selectedMethod;
        }

        if ($this->getOnepage()->getQuote()->isVirtual()) {
            $this->getOnepage()->getQuote()->getBillingAddress()->setPaymentMethod(!empty($paymentMethod) ? $paymentMethod : null);
        } else {
            $this->getOnepage()->getQuote()->getShippingAddress()->setPaymentMethod(!empty($paymentMethod) ? $paymentMethod : null);
        }

        try {
            if ($paymentMethod) {
                $this->getOnepage()->getQuote()->getPayment()->getMethodInstance();
            }
        } catch (Exception $e) {

        }

        //Breaks totals
        //$result = $this->getOnepage()->saveBilling($billing_data, $customerAddressId);

        if ($helper->differentShippingAvailable()) {
            if (empty($billing_data['use_for_shipping'])) {
                $shipping_result = $helper->saveShipping($shipping_data, $shippingAddressId);
            } else {
                $shipping_result = $helper->saveShipping($billing_data, $customerAddressId);
            }
        }

        //save addresses
        if(Mage::helper('customer')->isLoggedIn()){
            $this->getOnepage()->getQuote()->getBillingAddress()->setSaveInAddressBook(empty($billing_data['save_in_address_book']) ? 0 : 1);
            $this->getOnepage()->getQuote()->getShippingAddress()->setSaveInAddressBook(empty($shipping_data['save_in_address_book']) ? 0 : 1);
        }

        $shipping_method = $this->getRequest()->getPost('shipping_method', false);

        if (!empty($shipping_method)) {
            $helper->saveShippingMethod($shipping_method);
        }



        //Commented, it breaks totals
        //$this->getOnepage()->getQuote()->setTotalsCollectedFlag(false)->collectTotals();

        $requestParams = $this->getRequest()->getParams();
        if (array_key_exists('onestepcheckout_comments', $requestParams)
                && !empty($requestParams['onestepcheckout_comments'])) {
            $this->getSageSuiteSession()->setOscOrderComments($requestParams['onestepcheckout_comments']);
        }

        if(Mage::getStoreConfig('onestepcheckout/feedback/enable_feedback')) {
            $feedbackValues        = unserialize(Mage::getStoreConfig('onestepcheckout/feedback/feedback_values'));
            $feedbackValue         = $this->getRequest()->getPost('onestepcheckout-feedback');
            $feedbackValueFreetext = $this->getRequest()->getPost('onestepcheckout-feedback-freetext');
            if(!empty($feedbackValue)){
                if($feedbackValue!='freetext') {
                    $feedbackValue = $feedbackValues[$feedbackValue]['value'];
                }
                else {
                    $feedbackValue = $feedbackValueFreetext;
                }

                $this->getSageSuiteSession()->setOscCustomerFeedback(Mage::helper('core')->escapeHtml($feedbackValue));
            }
        }

        //GiftMessage
        $event = new Varien_Object;
        $event->setEvent(new Varien_Object);
        $event->getEvent()->setRequest($this->getRequest());
        $event->getEvent()->setQuote($this->getOnepage()->getQuote());
        Mage::getModel('giftmessage/observer')->checkoutEventCreateGiftMessage($event);

        if (array_key_exists('subscribe_newsletter', $requestParams)
                && (int) $requestParams['subscribe_newsletter'] === 1) {
            $this->getSageSuiteSession()->setOscNewsletterEmail($this->getOnepage()->getQuote()->getBillingAddress()->getEmail());
        }

        //GiftCard
        Mage::dispatchEvent('checkout_controller_onepage_save_shipping_method', array('request' => $this->getRequest(), 'quote' => $this->getOnepage()->getQuote()));
    }

    public function _IWD_OPCSaveBilling(){

        $billing_data = $this->getRequest()->getPost('billing', array());

        if(!$this->getOnepage()->getQuote()->getBillingAddress()->getTelephone() || $this->getOnepage()->getQuote()->getBillingAddress()->getTelephone() == ""){
            $this->getOnepage()->getQuote()->getBillingAddress()->setTelephone($billing_data['telephone']);
        }

        if(!$this->getOnepage()->getQuote()->getShippingAddress()->getTelephone() || $this->getOnepage()->getQuote()->getShippingAddress()->getTelephone() == ""){
            $this->getOnepage()->getQuote()->getShippingAddress()->setTelephone($billing_data['telephone']);
        }

        if(!$this->getOnepage()->getQuote()->getShippingAddress()->getCity() || $this->getOnepage()->getQuote()->getShippingAddress()->getCity() == ""){
            $this->getOnepage()->getQuote()->getShippingAddress()->setCity($billing_data['city']);
        }

        if(!$this->getOnepage()->getQuote()->getShippingAddress()->getStreet(1) || $this->getOnepage()->getQuote()->getShippingAddress()->getStreet(1) == ""){
            $this->getOnepage()->getQuote()->getShippingAddress()->setStreet($billing_data['street'][0]. "\n" . $billing_data['street'][1]);
        }

        $this->getOnepage()->getQuote()->setData('customer_email', $billing_data['email']);
        $this->getOnepage()->getQuote()->setData('customer_firstname', $billing_data['firstname']);
        $this->getOnepage()->getQuote()->setData('customer_lastname', $billing_data['lastname']);

        if (!Mage::helper('customer')->isLoggedIn()) {
            //$emailExists = Mage::helper('sagepaysuite')->existsCustomerForEmail($billing_data['email']);

            if (!empty($billing_data['customer_password']) && !empty($billing_data['confirm_password']) && ($billing_data['customer_password'] == $billing_data['confirm_password'])) {
                $password = $billing_data['customer_password'];
                $this->getOnepage()->getQuote()->setCheckoutMethod('register');
                $this->getOnepage()->getQuote()->getCustomer()->setData('password', $password);
                $this->getOnepage()->getQuote()->setData('password_hash', Mage::getModel('customer/customer')->encryptPassword($password));
            }
        }
    }

    public function sanitize_string(&$val) {
        $val = filter_var($val, FILTER_SANITIZE_STRING);
    }

    /**
     * Create order action
     */
    public function onepageSaveOrderAction() {
        if ($this->_expireAjax()) {
            return;
        }

        $paymentData = $this->getRequest()->getPost('payment', array());
        if ($paymentData) {

            //Sanitize payment data
            array_walk($paymentData, array($this, "sanitize_string"));

            $this->getOnepage()->getQuote()->getPayment()->importData($paymentData);
        }

        $paymentMethod = $this->getOnepage()->getQuote()->getPayment()->getMethod();
        /* if(!$paymentMethod){
          $post = $this->getRequest()->getPost();
          $paymentMethod = $post['payment']['method'];
          } */

        if (!$this->getOnepage()->getQuote()->isVirtual() && !$this->getOnepage()->getQuote()->getShippingAddress()->getShippingDescription()) {
            $result['success'] = false;
            $result['response_status'] = 'ERROR';
            $result['response_status_detail'] = $this->__('Please choose a shipping method');
            $this->getResponse()->setBody(Zend_Json::encode($result));
            return;
        }

        if ((FALSE === strstr(parse_url($this->_getRefererUrl(), PHP_URL_PATH), 'onestepcheckout')) && is_null($this->getRequest()->getPost('billing'))) { // Not OSC, OSC validates T&C with JS and has it own T&C
            # Validate checkout Terms and Conditions
            $result = array();
            if ($requiredAgreements = Mage::helper('checkout')->getRequiredAgreementIds()) {
                $postedAgreements = array_keys($this->getRequest()->getPost('agreement', array()));
                if ($diff = array_diff($requiredAgreements, $postedAgreements)) {
                    $result['success'] = false;
                    $result['response_status'] = 'ERROR';
                    $result['response_status_detail'] = $this->__('Please agree to all the terms and conditions before placing the order.');
                    $this->getResponse()->setBody(Zend_Json::encode($result));
                    return;
                }
            }
            # Validate checkout Terms and Conditions

            //Fix issue #9595957091315
            if(!empty($paymentData) && !isset($paymentData['sagepay_token_cc_id'])) {
                $this->getSageSuiteSession()->setLastSavedTokenccid(null);
            }

        }
        else {

            //reset token session
            if(!empty($paymentData) && !isset($paymentData['sagepay_token_cc_id'])) {
                $this->getSageSuiteSession()->setLastSavedTokenccid(null);
            }

            /**
             * OSC
             */
            if (FALSE !== Mage::getConfig()->getNode('modules/Idev_OneStepCheckout')) {
                $this->_OSCSaveBilling();
            }
            /**
             * OSC
             */

            /**
             * IWD OPC
             */
            if (FALSE !== Mage::getConfig()->getNode('modules/IWD_OnepageCheckout')) {

                # Validate checkout Terms and Conditions
                $requiredAgreements = Mage::helper('checkout')->getRequiredAgreementIds();

                if ($requiredAgreements) {
                    $postedAgreements = array_keys($this->getRequest()->getPost('agreement', array()));
                    if ($diff = array_diff($requiredAgreements, $postedAgreements)) {
                        $result['success'] = false;
                        $result['response_status'] = 'ERROR';
                        $result['response_status_detail'] = $this->__('Please agree to all the terms and conditions before placing the order.');
                        $this->getResponse()->setBody(Zend_Json::encode($result));
                        return;
                    }
                }
                $this->_IWD_OPCSaveBilling();
            }
            /**
             * IWD OPC
             */
        }

        if ($dataM = $this->getRequest()->getPost('shipping_method', '')) {
            //$this->getOnepage()->saveShippingMethod($this->sanitize_string($dataM));
            $this->getOnepage()->saveShippingMethod($dataM);
        }

        //Magemaven_OrderComment
        $orderComment = $this->getRequest()->getPost('ordercomment');
        if (is_array($orderComment) && isset($orderComment['comment'])) {
            $comment = trim($orderComment['comment']);
            if (!empty($comment)) {
                $this->getSageSuiteSession()->setOrderComments($comment);
            }
        }
        //Magemaven_OrderComment


        if ($paymentMethod == 'sagepayserver') {
            $this->_forward('saveOrder', 'serverPayment', null, $this->getRequest()->getParams());
            return;
        } else if ($paymentMethod == 'sagepaydirectpro') {
            $this->_forward('saveOrder', 'directPayment', null, $this->getRequest()->getParams());
            return;
        } else if ($paymentMethod == 'sagepayform') {
            $this->_forward('saveOrder', 'formPayment', null, $this->getRequest()->getParams());
            return;
        } else if ($paymentMethod == 'sagepaynit') {
            $this->_forward('saveOrder', 'nitPayment', null, $this->getRequest()->getParams());
            return;
        } else {

            //As of release 1.1.18. Left for history purposes, if is not sagepay, post should not reach to this controller
            $this->_forward('saveOrder', 'onepage', 'checkout', $this->getRequest()->getParams());
            return;
        }
    }

}
