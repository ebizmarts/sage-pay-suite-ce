<?php

/**
 * Adminhtml sales orders creation process controller
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */
class Ebizmarts_SagePaySuite_Adminhtml_Sales_Order_SpsCreateController extends Mage_Adminhtml_Controller_Action
{

    /**
     * Retrieve gift message save model
     *
     * @return Mage_Adminhtml_Model_Giftmessage_Save
     */
    protected function _getGiftmessageSaveModel()
    {
        return Mage::getSingleton('adminhtml/giftmessage_save');
    }

    /**
     * Retrieve session object
     *
     * @return Mage_Adminhtml_Model_Session_Quote
     */
    protected function _getSession()
    {
        return Mage::getSingleton('adminhtml/session_quote');
    }

    /**
     * Processing request data
     *
     * @return Mage_Adminhtml_Sales_Order_CreateController
     */
    protected function _processData()
    {
        /**
         * Saving order data
         */
        if ($data = $this->getRequest()->getPost('order')) {
            $this->_getOrderCreateModel()->importPostData($data);
        }

        /**
         * init first billing address, need for virtual products
         */
        $this->_getOrderCreateModel()->getBillingAddress();

        /**
         * Flag for using billing address for shipping
         */
        if (!$this->_getOrderCreateModel()->getQuote()->isVirtual()) {
            $syncFlag = $this->getRequest()->getPost('shipping_as_billing');
            if (!is_null($syncFlag)) {
                $this->_getOrderCreateModel()->setShippingAsBilling((int)$syncFlag);
            }
        }

        /**
         * Change shipping address flag
         */
        if (!$this->_getOrderCreateModel()->getQuote()->isVirtual() && $this->getRequest()->getPost('reset_shipping')) {
            $this->_getOrderCreateModel()->resetShippingMethod(true);
        }

        /**
         * Collecting shipping rates
         */
        if (!$this->_getOrderCreateModel()->getQuote()->isVirtual() && $this->getRequest()->getPost('collect_shipping_rates')) {
            $this->_getOrderCreateModel()->collectShippingRates();
        }


        /**
         * Apply mass changes from sidebar
         */
        if ($data = $this->getRequest()->getPost('sidebar')) {
            $this->_getOrderCreateModel()->applySidebarData($data);
        }

        /**
         * Adding product to quote from shoping cart, wishlist etc.
         */
        if ($productId = (int) $this->getRequest()->getPost('add_product')) {
            $this->_getOrderCreateModel()->addProduct($productId);
        }

        /**
         * Adding products to quote from special grid and
         */
        if ($data = $this->getRequest()->getPost('add_products')) {
            $this->_getOrderCreateModel()->addProducts(Mage::helper('core')->jsonDecode($data));
        }

        /**
         * Update quote items
         */
        if ($this->getRequest()->getPost('update_items')) {
            $items = $this->getRequest()->getPost('item', array());
            $this->_getOrderCreateModel()->updateQuoteItems($items);
        }

        /**
         * Remove quote item
         */
        if ( ($itemId = (int) $this->getRequest()->getPost('remove_item'))
             && ($from = (string) $this->getRequest()->getPost('from'))) {
            $this->_getOrderCreateModel($itemId)->removeItem($itemId, $from);
        }

        /**
         * Move quote item
         */
        if ( ($itemId = (int) $this->getRequest()->getPost('move_item'))
            && ($moveTo = (string) $this->getRequest()->getPost('to')) ) {
            $this->_getOrderCreateModel()->moveQuoteItem($itemId, $moveTo);
        }

        if ($paymentData = $this->getRequest()->getPost('payment')) {
            $this->_getOrderCreateModel()->getQuote()->getPayment()->addData($paymentData);
        }

        $eventData = array(
            'order_create_model' => $this->_getOrderCreateModel(),
            'request'            => $this->getRequest()->getPost(),
        );

        Mage::dispatchEvent('adminhtml_sales_order_create_process_data', $eventData);

        $this->_getOrderCreateModel()
            ->initRuleData()
            ->saveQuote();

        if ($paymentData = $this->getRequest()->getPost('payment')) {
            $this->_getOrderCreateModel()->getQuote()->getPayment()->addData($paymentData);
        }

        /**
         * Saving of giftmessages
         */
        if ($giftmessages = $this->getRequest()->getPost('giftmessage')) {
            $this->_getGiftmessageSaveModel()->setGiftmessages($giftmessages)
                ->saveAllInQuote();
        }

        /**
         * Importing gift message allow items from specific product grid
         */
        if ($data = $this->getRequest()->getPost('add_products')) {
            $this->_getGiftmessageSaveModel()->importAllowQuoteItemsFromProducts(Mage::helper('core')->jsonDecode($data));
        }

        /**
         * Importing gift message allow items on update quote items
         */
        if ($this->getRequest()->getPost('update_items')) {
            $items = $this->getRequest()->getPost('item', array());
            $this->_getGiftmessageSaveModel()->importAllowQuoteItemsFromItems($items);
        }

        $data = $this->getRequest()->getPost('order');
        if (!empty($data['coupon']['code'])) {
            if ($this->_getQuote()->getCouponCode() !== $data['coupon']['code']) {
                $this->_getSession()->addError($this->__('"%s" coupon code is not valid.', $data['coupon']['code']));
            } else {
                $this->_getSession()->addSuccess($this->__('The coupon code has been accepted.'));
            }
        }

        return $this;
    }

    /**
     * Retrieve order create model
     *
     * @return Mage_Adminhtml_Model_Sales_Order_Create
     */
    protected function _getOrderCreateModel()
    {
        return Mage::getSingleton('adminhtml/sales_order_create');
    }

    public function getDirectModel()
    {
    	return Mage::getModel('sagepaysuite/sagePayDirectProMoto');
    }

    /**
     * Saving quote and create order
     */
    public function saveAction()
    {
    	$paymentData = $this->getRequest()->getPost('payment');

        try {
            $this->_processData();
            if ($paymentData = $this->getRequest()->getPost('payment')) {

                //Added on Magento EE 1.13.0.0
                if(Mage::helper('sagepaysuite')->isMagentoEE113OrUp()) {
                    $paymentData['checks'] = Mage_Payment_Model_Method_Abstract::CHECK_USE_INTERNAL
                        | Mage_Payment_Model_Method_Abstract::CHECK_USE_FOR_COUNTRY
                        | Mage_Payment_Model_Method_Abstract::CHECK_USE_FOR_CURRENCY
                        | Mage_Payment_Model_Method_Abstract::CHECK_ORDER_TOTAL_MIN_MAX
                        | Mage_Payment_Model_Method_Abstract::CHECK_ZERO_TOTAL;
                }

                $this->_getOrderCreateModel()->setPaymentData($paymentData);
                $this->_getOrderCreateModel()->getQuote()->getPayment()->addData($paymentData);
            }

			if($paymentData && $paymentData['method'] == 'sagepaydirectpro_moto'){
				$result = $this->getDirectModel()->registerTransaction($this->getRequest()->getPost());
			}


            $order = $this->_getOrderCreateModel()
            	->setIsValidate(true)
                ->importPostData($this->getRequest()->getPost('order'))
                ->createOrder();

            $this->_getSession()->clear();
            Mage::getSingleton('adminhtml/session')->addSuccess($this->__('The order has been created.'));
            $this->_redirect('adminhtml/sales_order/view', array('order_id' => $order->getId()));
        }
        catch (Mage_Core_Exception $e){
            $message = $e->getMessage();
            if( !empty($message) ) {
                $this->_getSession()->addError($message);
            }
            $this->_redirect('adminhtml/sales_order_create/index');
        }
        catch (Exception $e){
            $this->_getSession()->addException($e, $this->__('Order saving error: %s', $e->getMessage()));
            $this->_redirect('adminhtml/sales_order_create/index');
        }
    }

    protected function _isAllowed() {
            $acl = 'sales/order/actions/create';
            return Mage::getSingleton('admin/session')->isAllowed($acl);
    }

}
