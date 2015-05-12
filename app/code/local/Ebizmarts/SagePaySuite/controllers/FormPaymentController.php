<?php

/**
 * SagePay FORM controller
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */
class Ebizmarts_SagePaySuite_FormPaymentController extends Mage_Core_Controller_Front_Action {

    /**
     * @var Mage_Sales_Model_Quote
     */
    protected $_quote = false;

    public function getOnepage() {

        return Mage::getSingleton('checkout/type_onepage');
    }

    /**
     * SagePaySuite session instance getter
     *
     * @return Ebizmarts_SagePaySuite_Model_Session
     */
    private function _getSession() {

        return Mage::getSingleton('checkout/session');
    }

    /**
     * Return checkout session object
     *
     * @return Mage_Checkout_Model_Session
     */
    private function _getCheckoutSession() {

        return Mage::getSingleton('checkout/session');
    }

    /**
     * Return checkout quote object
     *
     * @return Mage_Sale_Model_Quote
     */
    private function _getQuote() {

        if (!$this->_quote) {
            $this->_quote = $this->_getCheckoutSession()->getQuote();
        }
        return $this->_quote;
    }

    private function getFormModel() {
        return Mage::getModel('sagepaysuite/sagePayForm');
    }

    protected function _getTransaction() {

        return Mage::getModel('sagepaysuite2/sagepaysuite_transaction');
    }

    /**
     * Instantiate quote and checkout
     * @throws Mage_Core_Exception
     */
    private function _initCheckout() {

        $quote = $this->_getQuote();

        if (!$quote->hasItems() || (int) $this->getFormModel()->getConfigData('active') !== 1) {
            $this->getResponse()->setHeader('HTTP/1.1', '403 Forbidden');
            Mage::throwException(Mage::helper('sagepaysuite')->__('Unable to initialize FORM Checkout.'));
        }
    }

    public function saveOrderAction() {
        try {

            Mage::helper('sagepaysuite')->validateQuote();

            $this->_initCheckout();

            $resultData = array();
            $resultData['success']  = true;
            $resultData['error']    = false;
            $resultData['redirect'] = Mage::getUrl('sgps/formPayment/go', array('_secure' => true));

        } catch (Exception $e) {
            $resultData['response_status']        = 'ERROR';
            $resultData['response_status_detail'] = $e->getMessage();

            Mage::dispatchEvent('sagepay_payment_failed', array('quote' => $this->_getQuote(), 'message' => $e->getMessage()));
        }

        return $this->getResponse()->setBody(Zend_Json::encode($resultData));
    }

    /**
     * Post to SagePay form
     */
    public function goAction() {

        $this->_initCheckout();
        $this->getResponse()->setBody($this->getLayout()->createBlock('sagepaysuite/checkout_formpost')->toHtml());
        return;
    }

    public function successAction() {

        $_r = $this->getRequest();

        Sage_Log::log($_r->getPost(), null, 'SagePaySuite_FORM_Callback.log');

        if ($_r->getParam('crypt') && $_r->getParam('vtxc')) {

            $strDecoded = $this->getFormModel()->decrypt($_r->getParam('crypt'));
            $token = Mage::helper('sagepaysuite/form')->getToken($strDecoded);

            Sage_Log::log($token, null, 'SagePaySuite_FORM_Callback.log');

            $db = Mage::helper('sagepaysuite')->arrayKeysToUnderscore($token);

            # Add data to DB transaction
            $trn = $this->_getTransaction()->loadByVendorTxCode($_r->getParam('vtxc'));

            $trn->addData($db);

            if (isset($db['post_code_result'])) {
                $trn->setPostcodeResult($db['post_code_result']);
            }
            if (isset($db['cv2_result'])) {
                $trn->setCv2result($db['cv2_result']);
            }
            if (isset($db['3_d_secure_status'])) {
                $trn->setThreedSecureStatus($db['3_d_secure_status']);
            }
            if (isset($db['last4_digits'])) {
                $trn->setLastFourDigits($db['last4_digits']);
            }
            if (isset($db['gift_aid'])) {
                $trn->setGiftAid($db['gift_aid']);
            }

            if (isset($db['fraud_response'])) {
                $trn->setRedFraudResponse($db['fraud_response']);
            }

            $trn->save();

            //Check cart health on callback.
            if(1 === (int)Mage::getStoreConfig('payment/sagepaysuite/verify_cart_consistency')) {
                if(Mage::helper('sagepaysuite/checkout')->cartExpire($this->getOnepage()->getQuote())) {

                    try {

                        Mage::helper('sagepaysuite')->voidTransaction($trn->getVendorTxCode(), 'sagepayform');

                        Sage_Log::log("Transaction " . $trn->getVendorTxCode() . " cancelled, cart was modified while customer on payment pages.", Zend_Log::CRIT, 'SagePaySuite_FORM_Callback.log');

                        Mage::getSingleton('checkout/session')->addError($this->__('Your order could not be completed, please try again. Thanks.'));

                    }catch(Exception $ex) {
                        Sage_Log::log("Transaction " . $trn->getVendorTxCode() . " could not be cancelled and order was not created, cart was modified while customer on payment pages.", Zend_Log::CRIT, 'SagePaySuite_FORM_Callback.log');
                        Mage::getSingleton('checkout/session')->addError($this->__('Your order could not be completed but we could not cancel the payment, please contact us and mention this transaction reference number: %s. Thanks.', $db['vendor_tx_code']));
                    }

                    $this->_redirect('checkout/cart');
                    return;
                }
            }
            //Check cart health on callback.

            Mage::register('sageserverpost', new Varien_Object($token));

            Mage::getSingleton('sagepaysuite/session')->setInvoicePayment(true);

            try {
                $this->getOnepage()->getQuote()->collectTotals();
                $this->getOnepage()->saveOrder();
            } catch (Exception $e) {
                $trn->setStatus('MAGE_ERROR')
                        ->setStatusDetail($e->getMessage() . $trn->getStatusDetail())
                        ->save();

                Sage_Log::logException($e);

                Mage::dispatchEvent('sagepay_payment_failed', array('quote' => $this->getOnepage()->getQuote(), 'message' => $e->getMessage()));

                $this->_getSession()->addError('<strong>'.$this->__('The payment was made with success however an error occurred, your credit card has been charged. Please contact our support team.').'</strong>');

                Mage::helper('sagepaysuite/checkout')->deleteQuote();

                $this->_redirect('checkout/cart');

                return;

            }

            Mage::helper('sagepaysuite/checkout')->deleteQuote();

            $this->_redirect('checkout/onepage/success');
            return;
        }

        $this->_redirect('/');
        return;
    }

    public function failureAction() {

        $_r = $this->getRequest();

        if ($_r->getParam('crypt') && $_r->getParam('vtxc')) {

            # Delete orphan transaction from DB
            $trn = $this->_getTransaction()->loadByVendorTxCode($_r->getParam('vtxc'));
            if ($trn->getId()) {
                $trn->delete();
            }

            $this->_getSession()->addError($this->__('Transaction has been canceled.'));
            $this->_redirect('checkout/cart');
            return;
        }

        $this->_redirect('/');
        return;
    }

}

