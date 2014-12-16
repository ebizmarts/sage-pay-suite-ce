<?php

/**
 * DIRECT MOTO main model
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */

class Ebizmarts_SagePaySuite_Model_SagePayDirectProMoto extends Ebizmarts_SagePaySuite_Model_SagePayDirectPro
{

    protected $_code  = 'sagepaydirectpro_moto';

    /**
     * Availability options
     */
    protected $_canUseCheckout          = false;
    protected $_canUseForMultishipping  = false;
    protected $_canUseInternal          = true;

}