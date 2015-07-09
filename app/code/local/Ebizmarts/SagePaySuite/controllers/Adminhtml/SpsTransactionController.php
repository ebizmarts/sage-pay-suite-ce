<?php

/**
 * Orhpans transactions controller
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */
class Ebizmarts_SagePaySuite_Adminhtml_SpsTransactionController extends Mage_Adminhtml_Controller_Action {

    /**
     * Init layout, menu and breadcrumb
     *
     * @return Mage_Adminhtml_Sales_OrderController
     */
    protected function _initAction() {
        $this->loadLayout()
                ->_setActiveMenu('sales/order')
                ->_addBreadcrumb($this->__('Sales'), $this->__('Sales'));
                //->_addBreadcrumb($this->__('Sage Pay Orphan Transactions'), $this->__('Sage Pay Orphan Transactions'));
        return $this;
    }

    /**
     * Init layout, menu and breadcrumb
     *
     * @return Mage_Adminhtml_Sales_OrderController
     */
    protected function _initPaymentsAction() {
        $this->loadLayout()
                ->_setActiveMenu('sales/order')
                ->_addBreadcrumb($this->__('Sales'), $this->__('Sales'))
                ->_addBreadcrumb($this->__('Sage Pay Transactions'), $this->__('Sage Pay Transactions'));
        return $this;
    }

    protected function _getTransaction() {
        return Mage::getModel('sagepaysuite2/sagepaysuite_transaction');
    }

    /**
     * Recover transaction, creates order in Magento from a VALID transaction on Sage Pay.
     */
    public function recoverAction() {
        $paramVendor = $this->getRequest()->getParam('vendortxcode', null);

        try {

            $orderId = Mage::getModel('sagepaysuite/api_payment')->recoverTransaction($paramVendor);

            if ($orderId !== false) {

                $this->_getSession()->addSuccess(Mage::helper('sagepaysuite')->__('Transaction %s successfully recovered.', $paramVendor));
                $this->_redirect('adminhtml/sales_order/view', array(
                    'order_id' => $orderId
                ));
                return;
            } else {

                $this->_getSession()->addError(Mage::helper('sagepaysuite')->__('Transaction %s couldn\'t be recovered.', $paramVendor));
                $this->_redirectReferer();
                return;
            }
        } catch (Exception $e) {
            $this->_getSession()->addError($e->getMessage());
            $this->_redirectReferer();
            return;
        }
    }

    /**
     * Update transaction data from API on demand
     */
    public function syncAction() {

        if ($this->getRequest()->isPost()) { #Mass action

            $trnIds = $this->getRequest()->getPost('transaction_ids', array());

            foreach ($trnIds as $paramVendor) {

                $transaction = $this->_getTransaction()->load($paramVendor);

                $errors = false;

                if ($transaction->getId()) {

                    try {
                        $result = $transaction->updateFromApi();

                        if ($result->getApiError()) {
                            $errors = true;
                        }

                    } catch (Exception $e) {
                        $this->_getSession()->addError($e->getMessage());
                        $errors = true;
                    }
                } else {
                    $errors = true;
                }
            }

            if($errors == true){
                $this->_getSession()->addError('Some transactions where not found at SagePay. Either your API credentials are not correct or the transactions doesn\'t exist.');
            }else{
                $this->_getSession()->addSuccess(Mage::helper('sagepaysuite')->__('Transactions successfully updated.'));
            }

            $this->_redirectReferer();
            return;

        } else {

            $transactionId = $this->getRequest()->getParam('trn_id');
            $vendorTxCode = $this->getRequest()->getParam('vendortxcode');

            try {

                $transaction = Mage::getModel('sagepaysuite2/sagepaysuite_transaction');

                if ($vendorTxCode) {
                    $transaction->loadByVendorTxCode($vendorTxCode);
                } else {
                    $transaction->load($transactionId);
                }

                if ($transaction->getId()) {

                    $result = $transaction->updateFromApi();

                    if (!$result->getApiError()) {
                        $this->_getSession()->addSuccess(Mage::helper('sagepaysuite')->__('Transaction successfully updated.'));
                    } else {
                        $this->_getSession()->addError(Mage::helper('sagepaysuite')->__('Could not update. %s', $result->getApiError()));
                    }

                    $this->_redirectReferer();
                    return;
                } else {
                    $this->_getSession()->addError(Mage::helper('sagepaysuite')->__('Transaction not found.'));
                    $this->_redirectReferer();
                    return;
                }
            } catch (Exception $e) {
                $this->_getSession()->addError($e->getMessage());
                $this->_redirectReferer();
                return;
            }
        }
    }

    public function editAction() {

        $vendorTxCode  = $this->getRequest()->getParam('id');
        $transaction   = Mage::getModel('sagepaysuite2/sagepaysuite_transaction')->loadByVendorTxCode($vendorTxCode);

        if (!$transaction->getId()) {
            $this->_getSession()->addError($this->__('This transaction no longer exists.'));
            $this->_redirectReferer();
            return;
        }

        $this->_title($this->__('Edit Transaction'));

        // Restore previously entered form data from session
        $data = $this->_getSession()->getUserData(true);
        if (!empty($data)) {
            $transaction->setData($data);
        }

        Mage::register('sagepaysuite_transaction', $transaction);

        $this->loadLayout();
        $this->_setActiveMenu('sales');

        $this->renderLayout();

    }

    public function saveAction() {

        if($this->getRequest()->isPost()) {

            try {
                $data = $this->getRequest()->getPost('transaction');

                if(!empty($data)) {

                    $trn = Mage::getModel('sagepaysuite2/sagepaysuite_transaction')->load($data['id']);
                    if($trn->getId()) {
                        $trn
                        ->addData($data)
                        ->save();
                    }
                    $this->_getSession()->addSuccess($this->__('Transaction updated successfully.'));
                }
            }catch(Exception $ex) {
                $this->_getSession()->addSuccess($this->__('There was an error: %s.', $ex->getMessage()));
            }

            $this->_redirect('*/*/edit/', array('id' => $trn->getVendorTxCode()));
            return;

        }
        else {
            $this->_redirectReferer();
            return;
        }
    }

    public function deleteAction() {

        if ($this->getRequest()->isPost()) { #Mass action
            $trnIds = $this->getRequest()->getPost('transaction_ids', array());
            foreach ($trnIds as $paramVendor) {

                $trn = $this->_getTransaction()
                        ->load($paramVendor);
                if ($trn->getId()) {
                    try {
                        $trn->delete();
                        $this->_getSession()->addSuccess(Mage::helper('sagepaysuite')->__('Transaction %s successfully deleted.', $paramVendor));
                    } catch (Exception $e) {
                        $this->_getSession()->addError($e->getMessage());
                    }
                } else {
                    $this->_getSession()->addError(Mage::helper('sagepaysuite')->__('Invalid VendorTxCode supplied, %s', $paramVendor));
                }
            }
        } else {

            $paramVendor = $this->getRequest()->getParam('vendortxcode');
            $trn = $this->_getTransaction()
                    ->loadByVendorTxCode($paramVendor);
            if ($trn->getId()) {
                try {
                    $trn->delete();
                    $this->_getSession()->addSuccess(Mage::helper('sagepaysuite')->__('Transaction %s successfully deleted.', $paramVendor));
                } catch (Exception $e) {
                    $this->_getSession()->addError($e->getMessage());
                }
            } else {
                $this->_getSession()->addError(Mage::helper('sagepaysuite')->__('Invalid VendorTxCode supplied, %s', $paramVendor));
            }
        }

        $this->_redirectReferer();
        return;
    }

    public function voidAction() {
        $paramVendor = $this->getRequest()->getParam('vendortxcode');
        $trn = $this->_getTransaction()
                ->loadByVendorTxCode($paramVendor);
        if ($trn->getId()) {
            try {
                Mage::getModel('sagepaysuite/api_payment')->voidPayment($trn);
                $this->_getSession()->addSuccess(Mage::helper('sagepaysuite')->__('Transaction %s successfully VOIDed.', $paramVendor));
            } catch (Exception $e) {
                $this->_getSession()->addError($e->getMessage());
            }
        } else {
            $this->_getSession()->addError(Mage::helper('sagepaysuite')->__('Invalid VendorTxCode supplied, %s', $paramVendor));
        }

        $this->_redirectReferer();
        return;
    }

    public function addApiDataAction() {
        $id = $this->getRequest()->getParam('order_id');
        Mage::getModel('sagepaysuite2/sagepaysuite_transaction')->addApiDetails($id);

        $this->_redirectReferer();
        return;
    }

    /**
     * Delete TRN from sagepaysuite_transactions table
     */
    public function removetrnAction() {
        $trns = $this->getRequest()->getParam('ids');

        if (count($trns)) {

            foreach ($trns as $id) {
                $trn = Mage::getModel('sagepaysuite2/sagepaysuite_transaction')->load($id);

                if ($trn->getId()) {
                    $action = Mage::getModel('sagepaysuite2/sagepaysuite_action')->getCollection()->addFieldToFilter('parent_id', $trn->getId());

                    if ($action->getSize()) {
                        foreach ($action as $_a) {
                            $_a->delete();
                        }
                    }

                    $trn->delete();

                    $this->_getSession()->addSuccess($this->__('Transaction #%s deleted.', $id));
                } else {
                    $this->_getSession()->addError($this->__('Transaction #%s does not exist.', $id));
                }
            }
        }
        $this->_redirectReferer();
    }

    public function paymentsAction() {
        $this->_title($this->__('Sales'))->_title($this->__('Sage Pay Transactions'));

        $this->_initPaymentsAction()
                ->_addContent($this->getLayout()->createBlock('sagepaysuite/adminhtml_paymentransaction'))
                ->renderLayout();
    }

    public function paymentsGridAction() {
        $this->loadLayout();
        $this->getResponse()->setBody(
                $this->getLayout()->createBlock('sagepaysuite/adminhtml_paymentransaction_grid')->toHtml()
        );
    }

    public function orphanAction() {
        $this->_title($this->__('Sales'))->_title($this->__('Sage Pay Orphan Transactions'));

        $this->_initAction()
                ->_addContent($this->getLayout()->createBlock('sagepaysuite/adminhtml_transaction'))
                ->renderLayout();
    }

    public function gridAction() {
        $this->loadLayout();
        $this->getResponse()->setBody(
                $this->getLayout()->createBlock('sagepaysuite/adminhtml_transaction_grid')->toHtml()
        );
    }

    protected function _isAllowed() {
        switch ($this->getRequest()->getActionName()) {
            case 'save':
            case 'edit':
                $acl = 'sales/sagepay/payments/edit_transaction';
                break;
            default:
                $acl = 'sales/sagepay/payments';
        }
        return Mage::getSingleton('admin/session')->isAllowed($acl);
    }

}
