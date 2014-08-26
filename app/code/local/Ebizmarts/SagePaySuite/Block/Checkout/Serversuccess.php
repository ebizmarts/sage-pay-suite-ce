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

            if($this->getRequest()->getParam('qide')
                and $this->getRequest()->getParam('incide')
                and $this->getRequest()->getParam('oide')) {

                Mage::getSingleton('customer/session')->loginById($this->getRequest()->getParam('cusid'));

                Mage::getSingleton('checkout/session')
                    ->setLastSuccessQuoteId($this->getRequest()->getParam('qide'))
                    ->setLastQuoteId($this->getRequest()->getParam('qide'))
                    ->setLastOrderId($this->getRequest()->getParam('oide'))
                    ->setLastRealOrderId($this->getRequest()->getParam('incide'));
            }

            //Mage::getSingleton('checkout/type_onepage')->getQuote()->save();

            $successUrl = Mage::getModel('core/url')->addSessionParam()->getUrl('checkout/onepage/success', array('_secure' => true));

            if($this->getRequest()->getParam('multishipping')) {
                Mage::getUrl('checkout/multishipping/success', array('_secure' => true));
            }

        }

        $html = '<html><body>';
        $html.= '<script type="text/javascript">(parent.location == window.location)? window.location.href="' . $successUrl . '" : window.parent.setLocation("' . $successUrl . '");</script>';
        //$html.= '<script type="text/javascript">window.parent.setLocation("' . $successUrl . '");</script>';
        $html.= '</body></html>';

        return $html;
    }

}