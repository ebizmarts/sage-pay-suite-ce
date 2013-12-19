<?php

/**
 * Orphans trns grid
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */
class Ebizmarts_SagePaySuite_Block_Adminhtml_Transaction extends Mage_Adminhtml_Block_Widget_Grid_Container
{

    public function __construct()
    {
    	$this->_blockGroup = 'sagepaysuite';
        $this->_controller = 'adminhtml_transaction';
        $this->_headerText = Mage::helper('sagepaysuite')->__('Sage Pay Orphan Transactions');

        parent::__construct();

        $this->_removeButton('add');

    }


    public function getHeaderCssClass()
    {
        return 'icon-head head-adminhtml-sagepayorphantrns';
    }

}
