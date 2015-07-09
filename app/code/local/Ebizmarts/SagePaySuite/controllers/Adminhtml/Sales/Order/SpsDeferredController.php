<?php

/**
 * Deferred orders controller
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */
class Ebizmarts_SagePaySuite_Adminhtml_Sales_Order_SpsDeferredController extends Mage_Adminhtml_Controller_Action
{
	protected $_publicActions = array (
		'view'
	);

	protected function _initAction() {
		$this->loadLayout()->_setActiveMenu('sales/orders')->_addBreadcrumb($this->__('Sales'), $this->__('Sales'))->_addBreadcrumb($this->__('Orders'), $this->__('Deferred'));
		return $this;
	}
	public function indexAction() {
		$this->_initAction()->_addContent($this->getLayout()->createBlock('sagepaysuite/adminhtml_sales_order_deferred'))->renderLayout();
	}

	public function massReleaseAction() {
		$orderIds = $this->getRequest()->getPost('order_ids', array ());

		$countReleasedOrder = 0;
		foreach ($orderIds as $orderId) {
			$order = Mage :: getModel('sales/order')->load($orderId);
			$amountToRelease = $order->getGrandTotal();
			try {
				$result = Mage :: getModel('sagepaysuite/api_payment')->captureInvoice($order->getPayment(), $amountToRelease);

				Mage :: getModel('sagepaysuite/api_payment')->invoiceOrder($orderId);

				$countReleasedOrder++;

				$this->_getSession()->addSuccess($this->__('Order %s released OK', $order->getIncrementId()));

			} catch (Exception $e) {
				$this->_getSession()->addError($this->__('Order %s NOT released : %s', $order->getIncrementId(), $e->getMessage()));
			}

		}
		if ($countReleasedOrder > 0) {
			$this->_getSession()->addSuccess($this->__('%s order(s) successfully released', $countReleasedOrder));
		} else {
			$this->_getSession()->addError($this->__('%s order(s) successfully released', $countReleasedOrder));
		}

		$this->_redirectReferer();
		return;
	}

	public function gridAction() {
		$this->loadLayout();
		$this->getResponse()->setBody($this->getLayout()->createBlock('sagepaysuite/adminhtml_sales_order_deferred_grid')->toHtml());
	}

    protected function _isAllowed() {
            $acl = 'sales/sagepay/deferred_orders';
            return Mage::getSingleton('admin/session')->isAllowed($acl);
    }

}