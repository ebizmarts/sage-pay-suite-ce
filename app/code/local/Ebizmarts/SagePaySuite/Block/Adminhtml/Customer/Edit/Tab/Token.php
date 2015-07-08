<?php

class Ebizmarts_SagePaySuite_Block_Adminhtml_Customer_Edit_Tab_Token
    extends Ebizmarts_SagePaySuite_Block_Adminhtml_Tokencard_Grid
    implements Mage_Adminhtml_Block_Widget_Tab_Interface
{

    /**
     * Columns, that should be removed from grid
     *
     * @var array
     */
    protected $_columnsToRemove = array('visitor_session_id', 'protocol', 'customer_id');

    public function __construct()
    {
        parent::__construct();
        $this->setFilterVisibility(FALSE);
        $this->setSaveParametersInSession(FALSE);
    }

    /**
     * Defines after which tab, this tab should be rendered
     *
     * @return string
     */
    public function getAfter()
    {
        return 'orders';
    }

    /**
     * Return Tab label
     *
     * @return string
     */
    public function getTabLabel()
    {
        return $this->__('Sage Pay Saved Cards');
    }

    /**
     * Return Tab title
     *
     * @return string
     */
    public function getTabTitle()
    {
        return $this->__('Sage Pay Token Cards');
    }

    /**
     * Can show tab in tabs
     *
     * @return boolean
     */
    public function canShowTab()
    {
        $customer = Mage::registry('current_customer');
        return (bool)$customer->getId();
    }

    /**
     * Tab is hidden
     *
     * @return boolean
     */
    public function isHidden()
    {
        $hidden = false;

        $config = (string) Mage::getStoreConfig('payment/sagepaysuite/token_integration');
        if($config == 'false') {
            $hidden = true;
        }

        return $hidden;
    }

	protected function _getNewTokenUrl()
	{
		return $this->getUrl('adminhtml/spsToken/new', array('customer_id' => Mage::registry('current_customer')->getId()));
	}

	/**
	 * GRID methods
	 */

    protected function _prepareLayout()
    {
    	parent::_prepareLayout();

        $this->setChild('new_token_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setData(array(
                    'label'     => Mage::helper('adminhtml')->__('Add Token'),
                    'onclick' => "new Control.Modal('" . $this->_getNewTokenUrl() . "',{
								    overlayOpacity: 0.75,
									iframe: true,
									height:600,
									width:600,
								    className: 'modal-sagepaysuite',
								    fade: true
								}).open();",
                    'class'   => 'add'
                ))
        );

        return Mage_Adminhtml_Block_Widget::_prepareLayout();
    }

    public function getMainButtonsHtml()
    {
        $html = parent::getMainButtonsHtml();
        $html .= $this->getChildHtml('new_token_button');
        return $html;
    }

    protected function _prepareCollection()
    {
        $collection = Mage::getModel('sagepaysuite2/sagepaysuite_tokencard')
        				->getCollection()
        				->addCustomerFilter(Mage::registry('current_customer'));
        $this->setCollection($collection);
        return Mage_Adminhtml_Block_Widget_Grid::_prepareCollection();
    }

    protected function _prepareColumns()
    {
        $result = parent::_prepareColumns();

        foreach ($this->_columns as $key => $value) {
            if (in_array($key, $this->_columnsToRemove)) {
                unset($this->_columns[$key]);
            }else{
            	$this->_columns[$key]->setData('sortable', FALSE);
            }
        }
        return $result;
    }

    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', array('_current'=>true));
    }
}