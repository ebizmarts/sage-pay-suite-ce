<?php

/**
 * SagePay PayPal main model
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */

class Ebizmarts_SagePaySuite_Model_SagePayPayPal extends Ebizmarts_SagePaySuite_Model_SagePayDirectPro {

    protected $_code  = 'sagepaypaypal';

    /**
     * Availability options
     */
    protected $_isGateway               = true;
    protected $_canAuthorize            = false;
    protected $_canCapture              = false;
    protected $_canCapturePartial       = false;
    protected $_canRefund               = false;
    protected $_canRefundInvoicePartial = false;
    protected $_canVoid                 = false;
    protected $_canUseInternal          = false;
    protected $_canUseCheckout          = false;
    protected $_canUseForMultishipping  = false;


}
