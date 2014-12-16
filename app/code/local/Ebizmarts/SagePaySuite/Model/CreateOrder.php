<?php

/**
 * Creat Order dummy model
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */

class Ebizmarts_SagePaySuite_Model_CreateOrder
{
	protected $_source_quote = null;

	public function __construct(Mage_Sales_Model_Quote $quote)
	{
		$this->_source_quote = $quote;
	}

    public function getSession()
    {
        return Mage::getSingleton('sagepaysuite/session');
    }

	public function create()
	{
		$sq = $this->_source_quote;

		$quote = Mage::getModel('sales/quote')->setStoreId($sq->getStoreId());

		if ($sq->getCustomerId()) {
			// for customer orders:
			$customer = Mage::getModel('customer/customer')->setWebsiteId(1)->load($sq->getCustomerId());
			$quote->assignCustomer($customer);
		} else {
			// for guesr orders only:
			$quote->setCustomerEmail($sq->getCustomerEmail());
		}

		// add product(s)

        foreach ($sq->getItemsCollection() as $orderItem) {

            /* @var $orderItem Mage_Sales_Model_Order_Item */
            if (!$orderItem->getParentItem()) {

                $qty = $orderItem->getQty();

                if ($qty > 0) {
			        $product = Mage::getModel('catalog/product')
			            ->setStoreId($sq->getStoreId())
			            ->load($orderItem->getProductId());

			        if ($product->getId()) {

			            $product->setSkipCheckRequiredOption(true);

                        $buyRequest = $orderItem->getBuyRequest();
                        if (is_numeric($qty)) {
                            $buyRequest->setQty($qty);
                        }

			            $item = $quote->addProduct($product, $buyRequest);
			            if (is_string($item)) {
			                Mage::throwException($item);
			            }
			            $item->setQty($qty);
			            if ($additionalOptions = $orderItem->getProductOptionByCode('additional_options')) {
			                $item->addOption(new Varien_Object(
			                    array(
			                        'product' => $item->getProduct(),
			                        'code' => 'additional_options',
			                        'value' => serialize($additionalOptions)
			                    )
			                ));
			            }

			        }

                }
            }
        }

		$billingAddress = $quote->getBillingAddress()->addData($sq->getBillingAddress()->toArray());
		$shippingAddress = $quote->getShippingAddress()->addData($sq->getShippingAddress()->toArray());

		$shippingMethod = (!is_null($sq->getShippingAddress()->getShippingMethod()) ? $sq->getShippingAddress()->getShippingMethod() : 'flatrate_flatrate');

		$shippingAddress->setCollectShippingRates(true)->collectShippingRates()
        ->setShippingMethod($shippingMethod)
        ->setPaymentMethod($sq->getPayment()->getMethod());

		$shippingAddress->setShippingMethod($shippingMethod);

		$quote->getPayment()->importData(array (
			'method' => $sq->getPayment()->getMethod()
		));

		$quote->collectTotals()->save();

		$service = Mage::getModel('sales/service_quote', $quote);
		$service->submitAll();
		$order = $service->getOrder();

		if($order->getId()){
			return $order->getId();
		}else{
			return false;
		}
	}

}