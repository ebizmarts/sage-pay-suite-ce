<?php

/**
 * Server success block
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */
class Ebizmarts_SagePaySuite_Block_Checkout_Serversuccess extends Mage_Core_Block_Template
{

    protected function _getSess()
    {
        return Mage::getSingleton('sagepaysuite/api_payment')->getSageSuiteSession();
    }

    protected function _toHtml()
    {

        // If the order is and Admin order
        $orderId = $this->_getSess()->getDummyId();
        if(Mage::getSingleton('admin/session')->isLoggedIn() && $orderId){
            $successUrl = Mage::getModel('adminhtml/url')->getUrl('adminhtml/sales_order/view', array('order_id' => $orderId, '_secure' => true));
        }
        else {

            if(!is_null($this->getRequest()->getParam('qide'))
                && !is_null($this->getRequest()->getParam('incide'))
                && !is_null($this->getRequest()->getParam('oide'))) {

                //relogin user if just registered
                $quote = Mage::getModel('sales/quote')->load($this->getRequest()->getParam('qide'));
                $isRegister = ($quote->getData('checkout_method') == 'register');
                $quote_customer_id = $quote->getData('customer_id');
                $transaction = Mage::getModel('sagepaysuite2/sagepaysuite_transaction')
                    ->loadByParent($this->getRequest()->getParam('oide'));
                if($isRegister && $quote_customer_id == $this->getRequest()->getParam('cusid')){
                    //check transaction flag
                    if($transaction->getIntegration()=="server" && $transaction->getData("server_success_arrived") == false){
                        $transaction->setData("server_success_arrived",true)->save();
                        Mage::getSingleton('customer/session')->loginById($this->getRequest()->getParam('cusid'));
                    }
                }

                //saved core messages
                try {
                    if(!is_null($transaction->getData("server_session"))){
                        $server_session = json_decode($transaction->getData("server_session"));
                        if(!is_null($server_session) && array_key_exists("core_messages",$server_session)){
                            $messages = $server_session->core_messages;
                            if(array_key_exists("success",$messages)){
                                foreach($messages->success as $message){
                                    Mage::getSingleton('core/session')->addSuccess($message);
                                }
                            }
                            if(array_key_exists("error",$messages)){
                                foreach($messages->error as $message){
                                    Mage::getSingleton('core/session')->addError($message);
                                }
                            }
                        }
                    }
                }catch (Exception $e){
                    //unable to retrive core messages from db :/
                }

                Mage::getSingleton('checkout/session')
                    ->setLastSuccessQuoteId($this->getRequest()->getParam('qide'))
                    ->setLastQuoteId($this->getRequest()->getParam('qide'))
                    ->setLastOrderId($this->getRequest()->getParam('oide'))
                    ->setLastRealOrderId($this->getRequest()->getParam('incide'));

            }

            $autoInvoice = (int)$this->getRequest()->getParam('inv');
            if($autoInvoice) {
                Mage::getSingleton('sagepaysuite/session')->setCreateInvoicePayment($autoInvoice);
            }

            $successUrl = Mage::getModel('core/url')->getUrl('checkout/onepage/success', array('_secure' => true));

            if($this->getRequest()->getParam('multishipping')) {
                $successUrl = Mage::getUrl('checkout/multishipping/success', array('_secure' => true));
            }
        }

        $html = '<html><body>';
        $html.= '<script type="text/javascript">(parent.location == window.location)? window.location.href="' . $successUrl . '" : window.parent.setLocation("' . $successUrl . '");</script>';
        //$html.= '<script type="text/javascript">window.parent.setLocation("' . $successUrl . '");</script>';
        $html.= '</body></html>';

        return $html;
    }

}