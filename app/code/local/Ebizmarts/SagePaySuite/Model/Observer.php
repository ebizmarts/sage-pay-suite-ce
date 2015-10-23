<?php


/**
 * Main event observer model
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */

class Ebizmarts_SagePaySuite_Model_Observer
{

	protected $_sage_info = null;
	protected $_dbSess = null;

    /**
     * Round up and cast specified amount to float or string
     *
     * @param string|float $amount
     * @param bool $asFloat
     * @return string|float
     */
    protected function _formatAmount($amount, $asFloat = false)
    {
        $amount = sprintf('%.2F', $amount); // "f" depends on locale, "F" doesn't
        return $asFloat ? (float)$amount : $amount;
    }

    protected function _getIsAdmin()
    {
        return Mage::getSingleton('admin/session')->isLoggedIn();
    }

    protected function _getTransactionsModel()
    {
    	return Mage::getModel('sagepaysuite2/sagepaysuite_transaction');
    }

    public function getSession()
    {
        return Mage::getSingleton('sagepaysuite/session');
    }

	public function getSageSession()
    {
		if (is_null($this->_dbSess)) {
			$this->_dbSess = Mage::getModel('sagepaysuite2/sagepaysuite_session')->loadBySessionId(Mage::getSingleton('core/session')->getSessionId());
		}

		return $this->_dbSess;
	}

	/**
	 * Mage::dispatchEvent('checkout_type_onepage_save_order_after',
	 * array('order'=>$order, 'quote'=>$this->getQuote()));
	 */
	public function onePageAfterSaveOrder($o)
    {

	}

	/**
	 * Mage::dispatchEvent(
	              'checkout_type_multishipping_create_orders_single',
	              array('order'=>$order, 'address'=>$address)
	          );
	 */
	public function multiShippingCreateOrderSingle($o)
    {

	}

	/**
	 * Mage::dispatchEvent('sales_order_payment_cancel', array('payment' => $this));
	 */
	public function orderCancel($o)
    {
		$payment = $o->getEvent()->getPayment();

		if ((string) $payment->getMethodInstance()->getCode() == 'sagepaydirectpro') {
			$this->getMethodInstance()->setStore($payment->getOrder()->getStoreId())->cancelorrefund($payment);
		}
	}

	/**
     * @see Mage_Sales_Model_Order_Payment
	 * Mage::dispatchEvent('sales_order_payment_capture', array('payment' => $this, 'invoice' => $invoice));
	 */
	public function paymentCapture($o)
    {

		$payment = $o->getEvent()->getPayment();
		$invoice = $o->getEvent()->getInvoice();

		$sagePayInfo = $this->_getSagePayInfo($this->getOrder()->getId());
		$adminUsername = (($this->_getIsAdmin()) ? Mage :: getSingleton('admin/session')->getUser()->getUsername() : 'auto');
		$isReleased = $sagePayInfo->getReleased();
		$isAuthorised = $sagePayInfo->getAuthorised() == null ? 0 : $sagePayInfo->getAuthorised();
		$status = $payment->getOrder()->getPayment()->getCcApproval();

		if (!$isAuthorised && $status == 'OK_AUTHENTICATED') {
			$payment->getMethodInstance()->setStore($payment->getOrder()->getStoreId())->authorise($payment, sprintf('%.2f', $invoice->getBaseGrandTotal()));
			$invoice->setState(Mage_Sales_Model_Order_Invoice :: STATE_PAID);
			$orderState = Mage_Sales_Model_Order :: STATE_PROCESSING;
			$payment->getOrder()->setState($orderState);
			$payment->getOrder()->addStatusToHistory($orderState, $this->getOrder()->getCustomerNote(), $this->getOrder()->getCustomerNoteNotify());
		} else
			if (!$isReleased && $status == 'OK_DEFERRED') {
				$payment->getMethodInstance()->setStore($payment->getOrder()->getStoreId())->release($payment, sprintf('%.2f', $invoice->getBaseGrandTotal()));
				$invoice->setState(Mage_Sales_Model_Order_Invoice :: STATE_PAID);
				$orderState = Mage_Sales_Model_Order :: STATE_PROCESSING;
				$payment->getOrder()->setState($orderState);
				$payment->getOrder()->addStatusToHistory($orderState, $payment->getOrder()->getCustomerNote(), $payment->getOrder()->getCustomerNoteNotify());

			}

		return $o;

	}

	protected function _canProfile($controllerAction)
	{
		$sagepay = (bool)('Ebizmarts_SagePaySuite' == $controllerAction->getRequest()->getControllerModule() || 'Ebizmarts_SagePaySuite_Adminhtml' == $controllerAction->getRequest()->getControllerModule());
		$config  = (bool)Mage::getStoreConfig('payment/sagepaysuite/profile_request', Mage::app()->getStore());

		return ($sagepay && $config);
	}

	public function profilePre($o)
	{
		$ca = $o->getEvent()->getControllerAction();
		if( $this->_canProfile($ca) === FALSE){
			return $o;
		}

		Mage::getSingleton('core/resource')->getConnection('core_write')->getProfiler()->setEnabled(TRUE);
		Varien_Profiler::enable();
	}

	public function profilePost($o)
	{
		$ca = $o->getEvent()->getControllerAction();
		if( $this->_canProfile($ca) === FALSE){
			return $o;
		}

		Mage::helper('sagepaysuite')->logprofiler($ca);
	}

	public function layoutUpdate($o)
	{
		if(!Mage::helper('sagepaysuite')->isSuiteEnabled()){
			return $o;
		}

		if(FALSE === Mage::helper('sagepaysuite')->shouldAddChildLayout()){
			return $o;
		}

		$updates = $o->getEvent()->getUpdates();
		$updates->addChild('sagepaysuite_checkout_review')
				->file = 'sagepaysuite_checkout_review.xml';
	}

    public function layoutRewrite($o) {
        if(!Mage::helper('sagepaysuite')->isSuiteEnabled()){
            return $o;
        }

        $confPath = 'payment/sagepaysuite/';

        $config  = (int)Mage::getStoreConfig($confPath . 'layout_rewrites_active', Mage::app()->getStore());

        if($config !== 1) {
            return $o;
        }

        $applyOn = explode(',', Mage::getStoreConfig($confPath . 'layout_rewrites_updates_applyon', Mage::app()->getStore()));

        $actionName = Mage::app()->getFrontController()->getAction()->getFullActionName();

        if(is_array($applyOn) && in_array($actionName, $applyOn)) {
            $layoutUpdate = Mage::getStoreConfig($confPath . 'layout_rewrites_updates', Mage::app()->getStore());
            Mage::app()->getLayout()->getUpdate()->addUpdate($layoutUpdate);
        }

        return $o;

    }

}
