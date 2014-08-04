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

		$successUrl = $this->getRequest()->getParam('multishipping') ? Mage::getUrl('checkout/multishipping/success', array('_secure' => true)) : Mage::getUrl('checkout/onepage/success', array('_secure' => true));

		// If the order is and Admin order
		$orderId = $this->_getSess()->getDummyId();
		if(Mage::getSingleton('admin/session')->isLoggedIn() && $orderId){
			$successUrl = Mage::getModel('adminhtml/url')->getUrl('adminhtml/sales_order/view', array('order_id' => $orderId, '_secure' => true));
		}

        $html = '<html><body>';
        $html.= '<script type="text/javascript">(parent.location == window.location)? window.location.href="' . $successUrl . '" : window.parent.setLocation("' . $successUrl . '");</script>';
        //$html.= '<script type="text/javascript">window.parent.setLocation("' . $successUrl . '");</script>';
        $html.= '</body></html>';

        return $html;
    }

}