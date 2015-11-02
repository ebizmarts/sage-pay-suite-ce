<?php

/**
 * Checkout events observer
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */
class Ebizmarts_SagePaySuite_Model_Observer_Checkout extends Ebizmarts_SagePaySuite_Model_Observer {

    protected function _getLastOrderId() {
        return (int) (Mage::getSingleton('checkout/type_onepage')->getCheckout()->getLastOrderId());
    }

    /**
     * Save Magemaven Order Comments
     * @param  $observer
     * @return Ebizmarts_SagePaySuite_Model_Observer_Checkout
     */
    public function saveMagemavenOrderComment($observer) {

        //Magemaven_OrderComment
        $comment = $this->getSession()->getOrderComments(true);
        if($comment) {

            $order = $observer->getEvent()->getOrder();

            if(is_object($order)) {
                $order->setCustomerComment($comment);
                $order->setCustomerNoteNotify(true);
                $order->setCustomerNote($comment);
            }
        }
        //Magemaven_OrderComment

        return $this;
    }

    /**
     * Clear SagePaySuite session when loading onepage checkout
     */
    public function controllerOnePageClear($o) {

        /**
         * Delete register and guest cards when loading checkout
         */
        try {
            $sessionCards = Mage::helper('sagepaysuite/token')->getSessionTokens();
            if ($sessionCards->getSize() > 0) {
                foreach ($sessionCards as $_c) {
                    if ($_c->getCustomerId() == 0) {

                        $delete = Mage::getModel('sagepaysuite/sagePayToken')
                                ->removeCard($_c->getToken(), $_c->getProtocol());
                        if ($delete['Status'] == 'OK') {
                            $_c->delete();
                        }
                    }
                }
            }
        } catch (Exception $ex) {
            Mage::logException($ex);
        }
        /**
         * Delete register and guest cards when loading checkout
         */

        $this->getSession()->clear();
    }

    public function controllerMultishippingClear($o) {

        if($this->getSession()->getCreateInvoicePayment(true)) {
            $orderIds = Mage::getSingleton('checkout/type_multishipping')->getOrderIds();

            if(is_array($orderIds) and !empty($orderIds)) {

                for($i=0;$i<count($orderIds);$i++) {
                    Mage::getModel('sagepaysuite/api_payment')->invoiceOrder(Mage::getModel('sales/order')->load($orderIds[$i]));
                }

            }
        }

        $this->getSession()->clear();

    }

    public function getOnepage() {

        return Mage::getSingleton('checkout/type_onepage');
    }

    public function controllerOnePageSuccess($o) {

        //check if session is there
        $sessionCheckout = $this->getOnepage()->getCheckout();
        if(!$sessionCheckout->getLastSuccessQuoteId() && !is_null(Mage::app()->getRequest()->getParam('qide'))
            && !is_null(Mage::app()->getRequest()->getParam('incide'))
            && !is_null(Mage::app()->getRequest()->getParam('oide'))) {

            $sessionCheckout
                ->setLastSuccessQuoteId(Mage::app()->getRequest()->getParam('qide'))
                ->setLastQuoteId(Mage::app()->getRequest()->getParam('qide'))
                ->setLastOrderId(Mage::app()->getRequest()->getParam('oide'))
                ->setLastRealOrderId(Mage::app()->getRequest()->getParam('incide'));

            $autoInvoice = (int)Mage::app()->getRequest()->getParam('inv');
            if($autoInvoice) {
                Mage::getSingleton('sagepaysuite/session')->setCreateInvoicePayment($autoInvoice);
            }
        }

        //Capture data from Sage Pay API
        $orderId = $this->_getLastOrderId();

        $this->_getTransactionsModel()->addApiDetails($orderId);

        /**
         * Delete session tokencards if any
         */
        $vdata = Mage::getSingleton('core/session')->getVisitorData();

        $sessionCards = Mage::getModel('sagepaysuite2/sagepaysuite_tokencard')->getCollection()
                ->addFieldToFilter('visitor_session_id', (string) $vdata['session_id']);

        if ($sessionCards->getSize() > 0) {
            foreach ($sessionCards as $_c) {
                if ($_c->getCustomerId() == 0) {
                    $_c->delete();
                }
            }
        }

        //Associate Customer ID for DIRECT transactions without 3D and REGISTER checkout
        $tokenId = $this->getSession()->getLastSavedTokenccid(true);
        if((int)$tokenId) {
            $token = Mage::getModel('sagepaysuite2/sagepaysuite_tokencard')->load($tokenId);
            if($token->getId() && ($token->getId() == $tokenId) && !$token->getCustomerId()) {

                $customerId = Mage::getModel('sales/order')->load($orderId)->getCustomerId();

                $token->setCustomerId($customerId)
                        ->save();
            }
        }

        if($this->getSession()->getCreateInvoicePayment(true)) {
            //Sage_Log::log("Checkout observer, invoicing order " . $orderId , null, 'SagePaySuite_SERVER_RESPONSE.log');
            Mage::getModel('sagepaysuite/api_payment')->invoiceOrder(Mage::getModel('sales/order')->load($orderId));
        }

        /**
         * Delete session tokencards if any
         */
        $this->getSession()->clear();
    }

    public function sendPaymentFailedEmail($observer) {
        //Check if enabled in config.
        if(0 === (int)Mage::getStoreConfig('payment/sagepaysuite/send_payment_failed_emails')) {
            return $this;
        }

        $quote   = $observer->getEvent()->getQuote();
        $message = $observer->getEvent()->getMessage();

        try {

            Mage::helper('sagepaysuite/checkout')->sendPaymentFailedEmail($quote, $message);

        } catch(Exception $ex) {
            Sage_Log::logException($ex);
        }

        return $this;
    }

    /**
     * Fix SERVER integration issue when checkout method is REGISTER.
     *
     * @param $observer
     * @return $this
     */
    public function serverRegisterRecoverSession($observer) {

//        Sage_Log::log("CHECKOUT OBS: serverRegisterRecoverSession", null, 'SagePaySuite_SERVER_RESPONSE.log');
//
//        $quote = $observer->getEvent()->getQuote();
//        $order = $observer->getEvent()->getOrder();
//
//        $isSagePayServer = ($order->getPayment()->getMethod() == 'sagepayserver');
//        $isRegister      = ($quote->getData('checkout_method') == 'register');
//
//        if($isSagePayServer) {
//
//            Sage_Log::log("CHECKOUT OBS: Registering vars", null, 'SagePaySuite_SERVER_RESPONSE.log');
//
//            Mage::register('sagepay_last_real_order_id', $order->getIncrementId(), true);
//            Mage::register('sagepay_last_order_id', $order->getId(), true);
//            Mage::register('sagepay_last_quote_id', $quote->getId(), true);
//
//            if($isRegister){
//                Mage::register('sagepay_customer_id', $quote->getData('customer_id'), true);
//
//                //sweet tooth fix
//                if(Mage::registry('rewards_createPointsTransfers_run')){
//                    Mage::unregister('rewards_createPointsTransfers_run');
//                    Mage::dispatchEvent('sales_order_save_commit_after', array('order'=>$order));
//                }
//            }
//        }
//
//        return $this;
    }
}
