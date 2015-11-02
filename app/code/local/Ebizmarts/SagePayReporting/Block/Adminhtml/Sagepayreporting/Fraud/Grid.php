<?php

class Ebizmarts_SagePayReporting_Block_Adminhtml_Sagepayreporting_Fraud_Grid extends Mage_Adminhtml_Block_Widget_Grid
{

	const IMAGE_RENDERER     = 'sagepaysuite/adminhtml_widget_grid_column_renderer_image';
	const TEXT_FLAG_RENDERER = 'sagepaysuite/adminhtml_widget_grid_column_renderer_textflag';

	public function __construct()
	{
		parent::__construct();
		$this->setId('sagepayreporting_fraud');
		$this->setUseAjax(true);
		$this->setDefaultSort('created_at');
		$this->setDefaultDir('DESC');
		$this->setSaveParametersInSession(false);


	}

	/**
	 * Retrieve collection class
	 *
	 * @return string
	 */
	protected function _getCollectionClass()
	{
		return 'sales/order_grid_collection';
	}

	protected function _getCheckFlag($value, $row)
	{
		$image = '';
		switch ($value) {
			case 1:
				$image = $this->getSkinUrl('images/sagepaysuite/flag_green.png');
				break;
			case 0:
				$image = $this->getSkinUrl('images/sagepaysuite/flag_red.png');
				break;
			default:
				$image = $this->getSkinUrl('images/sagepaysuite/flag_blue.png');
			break;
		}

		return $image;
	}

	public function getFraud2($orderId)
	{
		return Mage::helper('sagepaysuite/paymentinfo')->getFraudInfo($orderId);
	}

	protected function _prepareCollection()
	{
		$collection = Mage::getResourceModel($this->_getCollectionClass());

		$collection->addFieldToSelect(array('increment_id', 'status', 'billing_name', 'grand_total', 'created_at'));

		$collection->getSelect()
		->joinLeft(
		array('pmnt' => $collection->getTable('sales/order_payment')), # sales_flat_order_payment
            'main_table.entity_id = pmnt.parent_id',
		array()
		)/*->joinLeft(
		array('sls' => $collection->getTable('sales/order')), # sales_flat_order
		'main_table.entity_id = sls.entity_id',
		array('status')
		)*/->joinLeft(
		array('fraud' => Mage :: getSingleton('core/resource')->getTableName('sagepayreporting_fraud')),
            'main_table.entity_id = fraud.order_id',
		array('thirdman_score', 'thirdman_action', 'thirdman_id')
		)->joinLeft(
		array('ste' => Mage :: getSingleton('core/resource')->getTableName('sagepaysuite_transaction')),
            'main_table.entity_id = ste.order_id',
		array('released', 'tx_type', 'authorised', 'canceled', 'aborted', 'threed_secure_status', 'cv2result', 'postcode_result', 'address_result')
		)
		->where('pmnt.method = ?', 'sagepayserver')
		->orWhere('pmnt.method = ?', 'sagepaydirectpro')
		->orWhere('pmnt.method = ?', 'sagepaydirectpro_moto')
		->orWhere('pmnt.method = ?', 'sagepayserver_moto')
		->orWhere('pmnt.method = ?', 'sagepayform');

		$this->setCollection($collection);
		parent::_prepareCollection();

		foreach($collection as $_c){

			if($_c->getTxType() == 'PAYMENT'){
				$_c->setPaymentStatus(Mage::helper('sagepaysuite')->__('OK Captured'));
			}

		}

		$this->setDefaultSort('increment_id');
		$this->setCollection($collection);
		return parent::_prepareCollection();
	}

	protected function _prepareColumns() {
		//TODO: Fix filters, neither work. This is why all are disabled.

		$this->addColumn('real_order_id', array(
            'header' => Mage::helper('sales')->__('Order #'),
            'width'  => '80px',
            'type'   => 'text',
            'filter' => false,
            'index'  => 'increment_id',
		));

		$this->addColumn('created_at', array(
            'header' => Mage::helper('sales')->__('Purchased On'),
            'index'  => 'created_at',
            'type'   => 'datetime',
            'filter' => false,
            'width'  => '100px',
		));

		/**
		 * TODO: FIX FILTER by STORE
		 */
		/*if (!Mage::app()->isSingleStoreMode()) {
		 $this->addColumn('store_id', array(
		'header'    => Mage::helper('sales')->__('Purchased From (Store)'),
		'index'     => 'store_id',
		'type'      => 'store',
		'store_view'=> true,
		'display_deleted' => true,
		));
		}*/

		$this->addColumn('cv2result', array(
            'header'    => Mage::helper('sagepaysuite')->__('Verification Value (CV2)'),
            'index'     => 'cv2result',
            'type'      => 'text',
            'align'     => 'center',
            'renderer'  => self::TEXT_FLAG_RENDERER,
            'filter'    => false,
            'sortable'  => false
		));

		$this->addColumn('postcode_result', array(
            'header' => Mage::helper('sagepaysuite')->__('Post Code'),
            'index' => 'postcode_result',
            'align'     => 'center',
            'renderer'  => self::TEXT_FLAG_RENDERER,
            'filter' => false,
            'sortable'  => false
		));

		$this->addColumn('address_result', array(
            'header' => Mage::helper('sagepaysuite')->__('Address Numerics'),
            'index' => 'address_result',
            'align'     => 'center',
            'renderer'  => self::TEXT_FLAG_RENDERER,
            'filter' => false,
            'sortable'  => false
		));

		$this->addColumn('thirdmanaction', array(
            'header' => Mage::helper('sagepaysuite')->__('The 3rdMan Action'),
            'index' => 'thirdman_action',
            'align'     => 'center',
            'renderer'  => self::TEXT_FLAG_RENDERER,
            'filter' => false,
            'sortable'  => false
		));

		$this->addColumn('thirdman', array(
            'header' => Mage::helper('sagepaysuite')->__('The 3rdMan Score'),
            'index' => 'thirdman_score',
            'align'     => 'center',
            'filter' => false,
            'sortable'  => false
		));

		/*$this->addColumn('thirdmanid', array(
		 'header' => Mage::helper('sales')->__('The 3rdMan ID'),
		'index' => 'thirdman_id',
		'align'     => 'center',
		'filter' => false,
		'sortable'  => false
		));*/

		$this->addColumn('billing_name', array(
            'header' => Mage::helper('sales')->__('Bill to Name'),
            'index' => 'billing_name',
            'filter' => false,
		));

		$this->addColumn('grand_total', array(
            'header' => Mage::helper('sales')->__('G.T. (Purchased)'),
            'index' => 'grand_total',
            'type'  => 'currency',
            'filter' => false,
            'currency' => 'order_currency_code',
		));

		$this->addColumn('status', array(
            'header' => Mage::helper('sales')->__('Status'),
            'index' => 'status',
            'type'  => 'options',
            'filter' => false,
            'width' => '70px',
            'options' => Mage::getSingleton('sales/order_config')->getStatuses(),
			'filter_condition_callback' => array($this, '_filterByStatus'),
		));

		$this->addColumn('payment_status', array(
            'header' => Mage::helper('sagepaysuite')->__('Payment Status'),
            'index' => 'payment_status',
            'filter' => false,
            'align'     => 'center',
            'sortable'  => false,
            'renderer' => self::IMAGE_RENDERER,
		));

		$this->addColumn('threed_secure_status', array(
            'header' => Mage::helper('sagepaysuite')->__('3D Secure'),
            'index' => 'threed_secure_status',
            'filter' => false,
            'align'     => 'center',
            'sortable'  => false,
		));

		$this->addColumn('action',
		array(
                'header'    => Mage::helper('sales')->__('Action'),
                'width'     => '70px',
                'type'      => 'action',
                'getter'     => 'getId',
                'actions'   => array(
		array(
                        'caption' => Mage::helper('sagepaysuite')->__('Check 3rd Man'),
                        'url'     => array('base'=>'*/*/fraudCheck'),
                        'field'   => 'order_id'
		)
		),
                'filter'    => false,
                'sortable'  => false,
                'index'     => 'stores',
                'is_system' => true,
		));

		return parent::_prepareColumns();
	}

	protected function _prepareMassaction()
	{
		$this->setMassactionIdField('entity_id');
		$this->getMassactionBlock()->setFormFieldName('order_ids');
		$this->getMassactionBlock()->setUseSelectAll(false);

		$this->getMassactionBlock()->addItem('tm_check', array(
             'label'=> Mage::helper('sagepaysuite')->__('Check 3rd Man'),
             'url'  => $this->getUrl('*/*/fraudCheck'),
		));
		return $this;
	}

	public function getRowUrl($row)
	{
		if (Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/view')) {
			return $this->getUrl('adminhtml/sales_order/view', array('order_id' => $row->getId()));
		}
		return false;
	}

	public function getGridUrl()
	{
		return $this->getUrl('*/*/grid', array('_current'=>true));
	}

	public function _filterByStatus($collection, $column)
	{
		if (!$value = $column->getFilter()->getValue()) {
			return;
		}
		$this->getCollection()->addFieldToFilter('main_table.status' , $column->getFilter()->getCondition());
	}

}
