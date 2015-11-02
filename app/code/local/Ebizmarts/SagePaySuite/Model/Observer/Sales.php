<?php

/**
 * Sales event observer
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */
class Ebizmarts_SagePaySuite_Model_Observer_Sales extends Ebizmarts_SagePaySuite_Model_Observer {


    /**
     * Sets order STATE based on status.
     *
     * @param type $o
     */
    public function orderState($o) {

        $order = $o->getEvent()->getOrder();

        if(!is_object($order->getPayment())) {
            return $o;
        }

        $_c = $order->getPayment()->getMethod();
        if (Mage::helper('sagepaysuite')->isSagePayMethod($_c) === false) {
            return $o;
        }

        $methodInstance = $order->getPayment()->getMethodInstance();
        $methodInstance->setStore($order->getStoreId());
        $action = $methodInstance->getConfigPaymentAction();

        $state = Mage_Sales_Model_Order::STATE_NEW;

        if ($action == Ebizmarts_SagePaySuite_Model_Api_Payment::ACTION_AUTHORIZE_CAPTURE
                or $action == Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE) {
            $state = Mage_Sales_Model_Order::STATE_PROCESSING;
        }

        $order->setState($state);

        /* Set order status based on ReD response.
         * $sagedata = $this->_getTransactionsModel()->loadByParent($order->getId());
        $ReD      = $sagedata->getRedFraudResponse();
        if(strtoupper($ReD) == 'DENY') {
            $order->setStatus('security_check');
        }*/

    }

    public function loadAfter($o) {
        $order = $o->getEvent()->getOrder();

        $trn = $this->_getTransactionsModel()
                ->loadByParent($order->getId());

        if ($trn->getId()) {
            $order->setData('sagepay_info', $trn);
        }
    }

    public function saveAfter($o) {
        $order = $o->getEvent()->getOrder();

        $isSage = Mage::helper('sagepaysuite')->isSagePayMethod($order->getPayment()->getMethod());

        if (!$order->getId() || $isSage === false || $order->getIsRecurring()) {
            return $o;
        }

        $dbtrn = $this->_getTransactionsModel()->loadByParent($order->getId());

        if ($dbtrn->getId()) {
            return $o;
        }

        if ((int) Mage::getStoreConfig('payment/sagepaysuite/order_error_save', Mage::app()->getStore()->getId()) === 1) {
            Mage::throwException(Mage::getStoreConfig('payment/sagepaysuite/order_error_save_message', Mage::app()->getStore()->getId()));
        }

        $rqVendorTxCode = Mage::app()->getRequest()->getParam('vtxc');
        $sessionVendor = ($rqVendorTxCode) ? $rqVendorTxCode : $this->getSession()->getLastVendorTxCode();

        if($sessionVendor == null){
            $sessionVendor = Mage::app()->getRequest()->getParam('VendorTxCode');
        }

        /**
         * Multishipping vendors
         */
        $multiShippingTxCodes = Mage::registry('sagepaysuite_ms_txcodes');
        if ($multiShippingTxCodes) {

            Mage::unregister('sagepaysuite_ms_txcodes');

            $sessionVendor = current($multiShippingTxCodes);

            array_shift($multiShippingTxCodes);
            reset($multiShippingTxCodes);

            Mage::register('sagepaysuite_ms_txcodes', $multiShippingTxCodes);
        }
        /**
         * Multishipping vendors
         */



        $reg = Mage::registry('Ebizmarts_SagePaySuite_Model_Api_Payment::recoverTransaction');
        if (!is_null($reg)) {
            $sessionVendor = $reg;
        }

        if (is_null($sessionVendor)) {


            if (!$dbtrn->getId()) {

                #For empty payments or old orders (standalone payment methods).
                if ((Mage::app()->getRequest()->getControllerModule() == 'Mage_Api') || Mage::registry('current_shipment') || Mage::registry('sales_order') || Mage::registry('current_creditmemo') || Mage::registry('current_invoice') || ($order->getPayment()->getMethod() == 'sagepayrepeat')) {
                    return $o;
                }

                $logfileName = $order->getIncrementId() . '-' . time() . '_Payment_Failed.log';

                $request_data = $_REQUEST;
                if (isset($request_data['payment'])) {
                    $request_data['payment']['cc_number'] = 'XXXXXXXXXXXXX';
                    $request_data['payment']['cc_cid'] = 'XXX';
                }

                Sage_Log::log($order->getIncrementId(), null, $logfileName);
                Sage_Log::log(Mage::helper('core/http')->getHttpUserAgent(false), null, $logfileName);
                Sage_Log::log(print_r($request_data, true), null, $logfileName);
                Sage_Log::log('--------------------', null, $logfileName);

                Mage::throwException('Payment has failed, please reload checkout page and try again. Your card has not been charged.');
            }

            return $o;
        }

        $this->_handleOscCallbacks($order);

        $tran = $this->_getTransactionsModel()
                ->loadByVendorTxCode($sessionVendor)
                ->setOrderId($order->getId());

        if ($tran->getId()) {

            if ($tran->getToken()) {
                $token = Mage::getModel('sagepaysuite2/sagepaysuite_tokencard')->loadByToken($tran->getToken());
                if ($token->getId()) {
                    $tran->setCardType($token->getCardType())
                            ->setLastFourDigits($token->getLastFour());
                }
            }

            $tran->save();

            Mage::dispatchEvent('sagepaysuite_transaction_new', array('order' => $order, 'transaction' => $tran));
        }

        // Ip address for SERVER method
        if ($this->getSession()->getRemoteAddr()) {
            $order->setRemoteIp($this->getSession()->getRemoteAddr());
        }

        # Invoice automatically PAYMENT transactions
        if ($this->getSession()->getInvoicePayment() || (!is_null($reg) && $tran->getTxType() == 'PAYMENT')) {
            $preventInvoice = ((int)Mage::getStoreConfig('payment/sagepaysuite/prevent_invoicing') === 1);
            Mage::getSingleton('sagepaysuite/session')->setCreateInvoicePayment(!$preventInvoice);
        }
    }

    public function saveBefore($o) {
        $order = $o->getEvent()->getOrder();

        $payment = $order->getPayment();

        $isSage = Mage::helper('sagepaysuite')->isSagePayMethod($payment->getMethod());

        /**
         * Check if charged in Sage Pay and order Total amounts match.
         */
        if($isSage && ((int)Mage::getStoreConfig('payment/sagepaysuite/check_amounts') === 1)) {
            $dbtrn = $this->_getTransactionsModel()->loadByParent($order->getId());
            $amountsMatch = Mage::getModel('sagepaysuite/api_payment')
                            ->floatsEqual($order->getGrandTotal(), $dbtrn->getTrnAmount(), 0.01);

            if($dbtrn->getId() and (false === $amountsMatch)) {
                Mage::throwException("Amounts do not match!\n" . $order->getGrandTotal() . "\n" . $dbtrn->getTrnAmount());
            }
        }

        /**
         * Add OSC comments to ORDER
         */
        if ($this->getSession()->getOscOrderComments()) {
            $order->setOnestepcheckoutCustomercomment($this->getSession()->getOscOrderComments());
        }

        $feedbackValue = $this->getSession()->getOscCustomerFeedback();
        if($feedbackValue) {
            $order->setOnestepcheckoutCustomerfeedback($feedbackValue);
        }

        if ($payment->getMethod() != 'sagepaydirectpro' && $payment->getMethod() != 'sagepayserver') {
            return $o;
        }

        if ($payment->getStatus() == 'FAIL' && $payment->getMethod() == 'sagepaydirectpro') {
            $order->setStatus(Mage::getStoreConfig('payment/sagepaysuite/fail_order_status'));
        }

        if ($payment->getMethod() != 'sagepaydirectpro') {
            return $o;
        }

        if ($payment->getStatus() == 'FAIL') {
            $order->setStatus(Mage::getStoreConfig('payment/sagepaydirectpro/fail_order_status'));
        }
    }

    public function invoiceAdminOrder($observer) {
        $order = $observer->getEvent()->getOrder();

        $payment = $order->getPayment();

        $isSage = Mage::helper('sagepaysuite')->isSagePayMethod($payment->getMethod());

        if($isSage) {
            if($this->getSession()->getCreateInvoicePayment(true)) {
                Mage::getModel('sagepaysuite/api_payment')->invoiceOrder($order->getId());
            }
        }
    }

    public function updateButton($observer) {
        $block = $observer->getEvent()->getBlock();

        if (get_class($block) == 'Mage_Adminhtml_Block_Widget_Button') {
            $ctrlername = $block->getRequest()->getControllerName();
            if (($ctrlername == 'sales_order_create' || $ctrlername == 'sales_order_edit')
                    && ($block->getData('onclick') == 'order.submit()')) {
                $block->setData('onclick', 'SageSuiteCreateOrder.orderSave();');
            }
        }

        return $observer;
    }

    public function quoteToOrder(Varien_Event_Observer $observer) {
        $sessionOrderId = $this->getSession()->getReservedOrderId();

        $orderExists = Mage::getModel('sales/order')->loadByIncrementId($sessionOrderId);

        if (!$sessionOrderId || ($orderExists->getId())) {
            return $observer;
        }

        $order = $observer->getEvent()->getOrder();
        $quote = $observer->getEvent()->getQuote();

        $order->setIncrementId($sessionOrderId);
        $quote->setReservedOrderId($sessionOrderId)->save();
    }

    /**
     * Handle OneStepCheckout callbacks
     */
    protected function _handleOscCallbacks($order) {
        $newsletterEmail = $this->getSession()->getOscNewsletterEmail();
        if ($newsletterEmail) {
            $this->_oscSuscribeNewsletter($newsletterEmail);
        }

    }

    protected function _oscSuscribeNewsletter($customerEmail) {
        try {
            $model = Mage::getModel('newsletter/subscriber');
            $result = $model->loadByEmail($customerEmail);

            if ($result->getId() === NULL) {
                // Not subscribed, OK to subscribe
                Mage::getModel('newsletter/subscriber')->subscribe($customerEmail);
            }
        } catch (Exception $e) {
            Sage_Log::logException($e);
        }
    }

    public function addColumnToSalesOrderGrid($observer) {

        $block = $observer->getEvent()->getBlock();
        //if (get_class($block) == 'Mage_Adminhtml_Block_Sales_Order_Grid') {
        if($block instanceof Mage_Adminhtml_Block_Sales_Order_Grid) { //Thanks Paul Ketelle for your feedback on this

            $block->addColumnAfter('sagepay_transaction_state', array(
                'header' => Mage::helper('sagepaysuite')->__('Sage Pay'),
                'index' => 'sagepay_transaction_state',
                'align' => 'center',
                'filter' => false,
                'renderer' => 'sagepaysuite/adminhtml_sales_order_grid_renderer_state',
                'sortable' => false,
                    )
                    , 'real_order_id');
        }

        return $observer;
    }

    public function addButtonToOrderView($observer) {
        $block = $observer->getEvent()->getBlock();

        //if (get_class($block) == 'Mage_Adminhtml_Block_Sales_Order_View') {
        if ($block instanceof Mage_Adminhtml_Block_Sales_Order_View) {

            $sagePayData = $block->getOrder()->getSagepayInfo();
            if ($sagePayData) {

                //Sage Pay - Sync from Api
                $block->addButton('update_from_api', array(
                    'label' => Mage::helper('sagepaysuite')->__('Sage Pay - Sync from Api'),
                    'onclick' => 'setLocation(\'' . $block->getUrl('adminhtml/spsTransaction/sync', array('_secure' => true, 'trn_id' => $sagePayData->getId())) . '\')',
                    'class' => 'go'
                ));
            }

        }
    }

}
