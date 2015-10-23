<?php
/**
 * Fraud controller
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */
class Ebizmarts_SagePaySuite_Adminhtml_Sales_Order_SpsFraudController extends Mage_Adminhtml_Controller_Action
{

    protected function _initAction()
    {
        $this->loadLayout()
            ->_setActiveMenu('sales/order')
            ->_addBreadcrumb($this->__('Sales'), $this->__('Sales'))
            ->_addBreadcrumb($this->__('Sage Pay Fraud Information'), $this->__('Sage Pay Fraud Information'));
        return $this;
    }

    public function indexAction()
    {
        $this->_title($this->__('Sales'))->_title($this->__('Sage Pay'))->_title($this->__('Fraud Information'));

        $this->_initAction()
            ->_addContent($this->getLayout()->createBlock('sagepaysuite/adminhtml_sales_order_fraud'))
            ->renderLayout();
    }

    public function gridAction()
    {
        $this->loadLayout();
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('sagepaysuite/adminhtml_sales_order_fraud_grid')->toHtml()
        );
    }

    public function fraudCheckAction()
    {

         if($this->getRequest()->isPost()){ #Mass action

            $orderIds = $this->getRequest()->getPost('order_ids', array());
            foreach ($orderIds as $orderId) {

                $_order = Mage::getModel('sales/order')->load($orderId);
                $rs = $this->getFraud()->getTransactionDetails($_order->getVendorTxCode());

                if($rs[0] != '<'){
                    $this->_getSession()->addError($this->__('An error occurred: %s %s', $_order->getVendorTxCode(), $rs));
                    continue;
                }

                $xml = new Varien_Simplexml_Element($rs);

                if((string)$xml->errorcode != '0000'){
                    $this->_getSession()->addError((string)$xml->error.' '.$_order->getVendorTxCode());
                }else{
                    try{
                        $this->getFraud()->updateThirdMan($orderId, $xml);
                        $this->_getSession()->addSuccess($this->__('Updated: Order Id #%s', $_order->getIncrementId()));
                    }catch(Exception $e){
                        Ebizmarts_SagePaySuite_Log::we($e);
                        $this->_getSession()->addError($_order->getVendorTxCode().' '.$e->getMessage);
                    }
                }

            }

         }else{
            $orderId = $this->getRequest()->getParam('order_id');

            $_order = Mage::getModel('sales/order')->load($orderId);
            $rs = $this->getFraud()->getTransactionDetails($_order->getVendorTxCode());

            if($rs[0] != '<'){
                $this->_getSession()->addError($this->__('An error occurred: %s', $rs));
                $this->_redirectReferer();
                return;
            }else{

                $xml = new Varien_Simplexml_Element($rs);

                if((string)$xml->errorcode != '0000'){
                    $this->_getSession()->addError((string)$xml->error.' '.$_order->getVendorTxCode());
                }else{
                    try{
                        $this->getFraud()->updateThirdMan($orderId, $xml);
                        $this->_getSession()->addSuccess($this->__('Updated: Order Id #%s', $_order->getIncrementId()));
                    }catch(Exception $e){
                        Ebizmarts_SagePaySuite_Log::we($e);
                        $this->_getSession()->addError($_order->getVendorTxCode().' '.$e->getMessage());
                    }
                }
            }

         }

         $this->_redirectReferer();
         return;
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