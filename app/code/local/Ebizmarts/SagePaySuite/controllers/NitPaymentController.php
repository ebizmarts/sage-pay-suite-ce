<?php

class Ebizmarts_SagePaySuite_NitPaymentController extends Mage_Core_Controller_Front_Action {

    protected function _expireAjax() {
        if (!Mage :: getSingleton('checkout/session')->getQuote()->hasItems()) {
            $this->getResponse()->setHeader('HTTP/1.1', '403 Session Expired');
            exit;
        }
    }

    public function preDispatch() {
        $storeId = $this->getRequest()->getParam('storeid');
        if ($storeId) {
            Mage :: app()->setCurrentStore((int) $storeId);
        }

        parent :: preDispatch();
        return $this;
    }

    public function saveOrderAction() {

        $this->_expireAjax();

        $resultData = array();

        try {

            Mage::helper('sagepaysuite')->validateQuote();

            $result = $this->getNitModel()->registerTransaction($this->getRequest()->getPost());
            $resultData = $result->getData();

            $response_status = $result->getResponseStatus();

            if ($response_status == Ebizmarts_SagePaySuite_Model_Api_Payment :: RESPONSE_CODE_3DAUTH) {
                $vendorTxCode = $result->getRequest()->getData('VendorTxCode');
                $resultData = array(
                    'success' => 'true',
                    'response_status' => 'threed',
                    'redirect' => Mage :: getModel('core/url'
                        )->getUrl('sgps/NitPayment/threedPost', array('_secure' => true, 'txc' => $vendorTxCode)));
            } else if ($response_status == Ebizmarts_SagePaySuite_Model_Api_Payment :: RESPONSE_CODE_APPROVED ||
                $response_status == Ebizmarts_SagePaySuite_Model_Api_Payment :: RESPONSE_CODE_REGISTERED) {

                $op = Mage :: getSingleton('checkout/type_onepage');
                $op->getQuote()->collectTotals();

                Mage::helper('sagepaysuite')->ignoreAddressValidation($op->getQuote());

                $op->saveOrder();

                $resultData = array(
                    'success' => 'true',
                    'response_status' => 'OK'
                );
                Mage::helper('sagepaysuite')->deleteQuote();
            }
        } catch (Exception $e) {
            Sage_Log :: logException($e);
            $resultData['response_status'] = 'ERROR';
            $resultData['response_status_detail'] = $e->getMessage();

            Mage::dispatchEvent('sagepay_payment_failed', array('quote' => Mage::getSingleton('checkout/type_onepage')->getQuote(), 'message' => $e->getMessage()));

        }

        return $this->getResponse()->setBody(Zend_Json :: encode($resultData));
    }

    public function generateMerchantKeyAction(){

        $resultData = array();

        try {

            $resultData = $this->getNitModel()->postForMerchantKey();

        } catch (Exception $e){
            Sage_Log :: logException($e);
            $resultData['response_status'] = 'ERROR';
            $resultData['response_status_detail'] = $e->getMessage();
        }

        return $this->getResponse()->setBody(Zend_Json :: encode($resultData));
    }

    public function threedPostAction() {
        $this->getResponse()->setBody($this->_getThreedPostHtml());
    }

    protected function _getThreedPostHtml() {
        return $this->getLayout()->createBlock('sagepaysuite/checkout_threedredirectpostnit')->toHtml();
    }

    public function callback3dAction() {

        $vendorTxCode = $this->getRequest()->getParam('v');
        $transaction = Mage::getModel('sagepaysuite2/sagepaysuite_transaction')
            ->loadByVendorTxCode($vendorTxCode);

        $emede = $transaction->getMd();
        $pares = $this->getRequest()->getPost('PaRes');

        $transaction->setPares($pares)
            ->save();

        header('Content-type: text/html; charset=utf-8');

        $image = Mage :: helper('sagepaysuite')->getIndicator();

        echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 3.2//EN"><html><head></head><body>
					<div style="background-image:url(' . $image . '); background-position: center center;background-repeat: no-repeat;height: 400px;">&nbsp;</div>';
        echo $this->__('<small>%s</small>', "Processing order, please stand by...  ");

        $error = false;
        $quote = Mage::getSingleton('checkout/type_onepage')->getQuote();

        try {

            //Check cart health on callback.
            if(1 === (int)Mage::getStoreConfig('payment/sagepaysuite/verify_cart_consistency')) {
                if(Mage::helper('sagepaysuite/checkout')->cartExpire($quote)) {

                    Sage_Log::log("Transaction " . $transaction->getVendorTxCode() . " not completed, cart was modified while customer on 3D payment pages.", Zend_Log::CRIT, 'SagePaySuite_REQUEST.log');

                    Mage::throwException($this->__('Your order could not be completed, please try again. Thanks.'));

                }
            }
            //Check cart health on callback.

            if ($pares && $emede) {
                Mage::getModel('sagepaysuite/sagePayNit')->saveOrderAfter3dSecure($pares, $emede);
                echo $this->__('<small>%s</small>', "Done. Redirecting...");
            }
            else {

                Mage::dispatchEvent('sagepay_payment_failed', array('quote' => $quote, 'message' => $this->__("3D callback error.")));

                Mage::throwException($this->__("Invalid request. PARes and MD are empty."));
            }
        } catch (Exception $e) {

            Mage::getSingleton('sagepaysuite/session')->setAcsurl(null)
                ->setPareq(null)
                ->setSageOrderId(null)
                ->setSecure3d(null)
                ->setEmede(null)
                ->setPares(null)
                ->setMd(null);

            Sage_Log::logException($e);
            Mage::dispatchEvent('sagepay_payment_failed', array('quote' => $quote, 'message' => $e->getMessage()));

            $error = true;
            $message = $e->getMessage();

            $layout = Mage::getModel('sagepaysuite/sagePayDirectPro')->getConfigData('threed_layout');
            if($layout == 'redirect') {
                Mage::getSingleton('checkout/session')->addError($message);
                echo '<script type="text/javascript">window.location.href="' . Mage::getUrl('checkout/cart') . '"</script>';
            }
            else {
                echo '<script type="text/javascript">
                    if((typeof window.parent.restoreOscLoad) != "undefined"){
                    window.parent.restoreOscLoad();
                    window.parent.notifyThreedError("' . $message . '");
                    }
                    else {
                        alert("' . $message . '");
                    }
                </script>';
            }

            echo '</body></html>';

        }

        if (!$error) {
            Mage::getSingleton('checkout/type_onepage')->getQuote()->save();

            $successUrl = Mage::getUrl('checkout/onepage/success', array('_secure' => true));

            echo '<script type="text/javascript">
					(parent.location == window.location)? window.location.href="' . $successUrl . '" : window.parent.setLocation("' . $successUrl . '");
				  </script>
				  </body></html>';
        }
    }

    public function getNitModel() {
        return Mage :: getModel('sagepaysuite/sagePayNit');
    }

    public function errorAction() {
        $this->_redirect('checkout/onepage');
    }
}