<?php

class Ebizmarts_SagePayReporting_Adminhtml_Sagepayreporting_FraudController extends Mage_Adminhtml_Controller_Action
{

	protected function _initAction()
	{
		$this->loadLayout()
		->_setActiveMenu('sales')
		->_addBreadcrumb($this->__('Sage Pay Reporting'), $this->__('Sage Pay Reporting'))
		->_addBreadcrumb($this->__('Sage Pay Fraud Information'), $this->__('Sage Pay Fraud Information'));
		return $this;
	}

	public function indexAction()
	{
		$this->_title(Mage::helper('sagepaysuite')->__('Sage Pay Reporting'))->_title(Mage::helper('sagepaysuite')->__('Fraud Information'));

		$this->_initAction()
		->_addContent($this->getLayout()->createBlock('sagepayreporting/adminhtml_sagepayreporting_fraud'))
		->renderLayout();
	}

	public function gridAction()
	{
		$this->loadLayout();
		$this->getResponse()->setBody(
		$this->getLayout()->createBlock('sagepayreporting/adminhtml_sagepayreporting_fraud_grid')->toHtml()
		);
	}

	public function invoiceAction()
	{
		$orderIds = array();

		if($this->getRequest()->isPost()){
			$orderIds = $this->getRequest()->getPost('order_ids', array());
		}else{
			$orderIds []= $this->getRequest()->getParam('order_id');
		}

		if(count($orderIds)){
			#Mass action

			foreach ($orderIds as $orderId) {

			try{
				$rs = $this->getPersistentFraud()->invoice($orderId);
				$this->_getSession()->addSuccess($this->__('Invoiced: Order Id #%s', $orderId));
			}catch(Exception $e){
				Sage_Log::logException($e);
				$this->_getSession()->addError($this->__('Cannot invoice order #%s. Reason: "%s"', $orderId, $e->getMessage()));
			}

		}

		}

		$this->_redirectReferer();
		return;
	}

	public function fraudCheckAction()
	{

		if($this->getRequest()->isPost()){
			#Mass action

			$orderIds = $this->getRequest()->getPost('order_ids', array());
		foreach ($orderIds as $orderId) {

			$_order = Mage::getModel('sales/order')->load($orderId);

			Mage::register('reporting_store_id', $_order->getStoreId());

			$rs = $this->getFraud()->getTransactionDetails($_order->getSagepayInfo()->getVendorTxCode());

			if($rs->getError()){
				Mage::unregister('reporting_store_id');
				$this->_getSession()->addError($this->__('An error occurred: %s %s', $_order->getVendorTxCode(), $rs));
				continue;
			}

			if($rs->getError()){
				$this->_getSession()->addError((string)$xml->error.' '.$_order->getVendorTxCode());
			}else{
				try{
					$this->getPersistentFraud()->updateThirdMan($orderId, $rs);
					$this->_getSession()->addSuccess($this->__('Updated: Order Id #%s', $_order->getIncrementId()));
				}catch(Exception $e){
					Ebizmarts_SagePaySuite_Log::we($e);
					$this->_getSession()->addError($_order->getVendorTxCode().' '.$e->getMessage);
				}
			}
			Mage::unregister('reporting_store_id');
		}

		}else{
			$orderId = $this->getRequest()->getParam('order_id');

			$_order = Mage::getModel('sales/order')->load($orderId);

			Mage::register('reporting_store_id', $_order->getStoreId());

            if(is_object($_order->getSagepayInfo())) {
			    $rs = $this->getFraud()->getTransactionDetails($_order->getSagepayInfo()->getVendorTxCode());
            }
            else {
                $this->_getSession()->addError($this->__('Transaction not found.'));
                $this->_redirectReferer();
                return;
            }

			if($rs->getError()){
				$this->_getSession()->addError($this->__('An error occurred: %s', htmlentities($rs->getError())));
				$this->_redirectReferer();
				return;
			}else{
				try{
					$this->getPersistentFraud()->updateThirdMan($orderId, $rs);
					$this->_getSession()->addSuccess($this->__('Updated: Order Id #%s', $_order->getIncrementId()));
				}catch(Exception $e){
					Ebizmarts_SagePaySuite_Log::we($e);
					$this->_getSession()->addError($_order->getVendorTxCode().' '.htmlentities($e->getMessage()));
				}
			}

		}

		$this->_redirectReferer();
		return;
	}

	public function getPersistentFraud()
	{
		return Mage::getModel('sagepayreporting/fraud');
	}

	public function getFraud()
	{
		return Mage::getModel('sagepayreporting/sagepayreporting');
	}

    protected function _isAllowed() {
        $acl = 'sales/sagepay/sagepayreporting/fraud_info_orders';
        return Mage::getSingleton('admin/session')->isAllowed($acl);
    }

}