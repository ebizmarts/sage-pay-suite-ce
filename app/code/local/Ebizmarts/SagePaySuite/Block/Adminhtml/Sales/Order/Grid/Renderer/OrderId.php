<?php

class Ebizmarts_SagePaysuite_Block_Adminhtml_Sales_Order_Grid_Renderer_OrderId extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract {
    /**
     * Renders grid column
     *
     * @param   Varien_Object $row
     * @return  string
     */
    public function render(Varien_Object $row) {
        $result = parent::render($row);

        $order = Mage::getModel('sales/order')->load($row->getOrderId());

        if($order->getId()) {
            $href   = Mage::helper('adminhtml')->getUrl('adminhtml/sales_order/view', array('order_id' => $order->getId()));
            $result = '<a href="' . $href . '" target="_blank">' . $order->getIncrementId() . '</a>';
        }

        return $result;
    }

}