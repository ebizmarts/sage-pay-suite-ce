<?php

/**
 * Payment events observer
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */
class Ebizmarts_SagePaySuite_Model_Observer_Payment extends Ebizmarts_SagePaySuite_Model_Observer {

    public function addInfo($o) {
        $payment = $o->getEvent()->getPayment();

        if (Mage::helper('sagepaysuite')->isSagePayMethod($payment->getMethod()) === false) {
            return $o;
        }

        $_order = $payment->getOrder();

        if ($_order) {
            $payment->setVendorTxCode($_order->getVendorTxCode())
                    ->setAddressResult($_order->getAddressResult())
                    ->setPostcodeResult($_order->getPostcodeResult())
                    ->setCv2result($_order->getCv2result())
                    ->setAvscv2($_order->getAvscv2())
                    ->setCcTransId($_order->getVpsTxId());

            if ($_order->getThreedSecureStatus()) {
                $payment->setAdditionalData($_order->getStatusDetail());
            }
        }
    }

    /**
     * @see Mage_Sales_Model_Order_Payment::cancel
     * Mage::dispatchEvent('sales_order_payment_cancel', array('payment' => $this));
     */
    public function cancel($o) {
        $payment = $o->getEvent()->getPayment();

        $_c = $payment->getMethodInstance()->getCode();
        if (Mage::helper('sagepaysuite')->isSagePayMethod($_c) === false) {
            return $o;
        }

        Mage::getModel('sagepaysuite/api_payment')->setMcode($_c)->cancelOrder($payment);
    }

    public function multiShipping($observer) {
        $order = $observer->getEvent()->getOrder();
        $pmethod = (string) $order->getPayment()->getMethod();

        $_request = Mage::app()->getRequest();
        $_post = $_request->getPost();
        $_paymentPost = $_request->getPost('payment');

        $tokenEnabled = Mage::getModel('sagepaysuite/sagePayToken')->isEnabled();

        if ($pmethod == 'sagepayserver') {

            if (is_null(Mage::registry('first_server_ms_trn'))) {

                $trn = Mage::getModel('sagepaysuite2/sagepaysuite_transaction')
                        ->loadByVendorTxCode($_request->getPost('VendorTxCode'));
                Mage::register('first_server_ms_trn', $trn->getId());

                $this->getSession()->setReservedOrderId(null);
            } else {

                $trn = Mage::getModel('sagepaysuite2/sagepaysuite_transaction')
                        ->setParentTrnId(Mage::registry('first_server_ms_trn'))
                        ->setIntegration('server')
                        ->save();
            }

            return $observer;
        }

        if ($pmethod != 'sagepaydirectpro') {
            return $observer;
        }

        if (true === $tokenEnabled) {

            if (isset($_paymentPost['token_cvv']) && isset($_paymentPost['sagepay_token_cc_id'])) { //Use Token
                $this->getSession()
                        ->setLastSavedTokenccid($_paymentPost['sagepay_token_cc_id'])
                        ->setTokenCvv($_paymentPost['token_cvv']);

                $this->_directMultiShippingTrn($_post, $order);
                return $observer;
            } else {
                //Register token for use below
                // Subsequent orders with same token (do not register a new one everytime)
                if (Mage::registry('ms_token_reguse')) {
                    $this->_directMultiShippingTrn($_post, $order);
                    return $observer;
                }

                $resultToken = Mage::getModel('sagepaysuite/sagePayDirectPro')->registerTransaction($_post, true);

                $tokenDb = Mage::getModel('sagepaysuite2/sagepaysuite_tokencard')->loadByToken($resultToken['Token']);
                if ($tokenDb->getId()) {

                    Mage::register('ms_token_reguse', $tokenDb);

                    $this->getSession()
                            ->setLastSavedTokenccid($tokenDb->getId())
                            ->setTokenCvv($_paymentPost['cc_cid']);

                    $this->_directMultiShippingTrn($_post, $order);
                }
            }

            return $observer;
        }

        $this->_directMultiShippingTrn($_post, $order);
    }

    protected function _directMultiShippingTrn($_post, $order) {
        $result = Mage::getModel('sagepaysuite/sagePayDirectPro')->registerTransaction($_post, FALSE, $order);

        if ($result->getResponseStatus() != Ebizmarts_SagePaySuite_Model_Api_Payment::RESPONSE_CODE_APPROVED &&
                $result->getResponseStatus() != Ebizmarts_SagePaySuite_Model_Api_Payment::RESPONSE_CODE_REGISTERED) {

            Mage::throwException($result->getResponseStatusDetail());
        }
    }

}
