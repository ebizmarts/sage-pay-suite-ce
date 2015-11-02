<?php

/**
 * Main helper
 *
 * @category    Ebizmarts
 * @package     Ebizmarts_SagePaySuite
 * @author      Ebizmarts Team <info@ebizmarts.com>
 */
class Ebizmarts_SagePaySuite_Helper_Data extends Mage_Core_Helper_Abstract {

    protected $_states = null;
    protected $_ccCards = array();
    protected $_sageMethods = array('sagepayserver', 'sagepayserver_moto',
        'sagepaydirectpro', 'sagepaydirectpro_moto',
        'sagepaypaypal', 'sagepayform', 'sagepaynit');

    public function currenciesToOptions() {

        $options = Mage::getModel('adminhtml/system_config_source_currency')->toOptionArray(false);

        $currencies = array();

        for($i = 0; $i < count($options); $i++) {
            $currencies[$options[$i]['value']] = $options[$i]['label'] . " (" . $options[$i]['value'] . ")";
        }

        return $currencies;

    }

    /**
     * Check if basket is OKay to be sent to Sage Pay.
     *
     * @param string $basket
     * @return boolean
     */
    public function validateBasketXml($basket) {
        $valid = true;

        //Validate max length
        if(strlen($basket) > 20000) {
            $valid = false;
        }

        return $valid;
    }

    public function getSagePayBasket($quote, $force_xml) {

        $api = Mage::getModel('sagepaysuite/api_payment');

        $basket = "";

        if($force_xml == true){
            $_basket = $api->getBasketXml($quote);
            if($this->validateBasketXml($_basket)) {
                $basket = $_basket;
            }
        }else{
            if ($api->getSendBasket()) {

                $basketFormat = (string)$api
                    ->getConfigData('basket_format');
                if('sage' === $basketFormat) {
                    $basket = $api->getSageBasket($quote);
                }
                else {
                    if('xml' === $basketFormat) {
                        $_basket = $api->getBasketXml($quote);
                        if($this->validateBasketXml($_basket)) {
                            $basket = $_basket;
                        }
                    }
                }
            }
        }

        return $basket;
    }

    /**
     * Checks if a customer exists for a given email.
     *
     * @param type $email
     * @param type $websiteId
     * @return boolean
     */
    public function existsCustomerForEmail($email, $websiteId = null) {

        $exists = false;

        $customer = Mage::getModel('customer/customer');

        if(!is_null($websiteId)) {
            $customer->setWebsiteId($websiteId);
        }
        else {
            $customer->setWebsiteId(Mage::app()->getStore()->getWebsiteId());
        }

        $customer->loadByEmail($email);

        if($customer->getId()) {
            $exists = true;
        }

        return $exists;
    }

    /**
     * Return module User-Agent string
     * @return string User agent
     */
    public function getUserAgent() {
        $v = (string) Mage::getConfig()->getNode('modules/Ebizmarts_SagePaySuite/version');
        return "Ebizmarts/SagePaySuite CE(v{$v})";
    }

    public function voidTransaction($vendorTxCode, $integration) {
        $trn = Mage::getModel('sagepaysuite2/sagepaysuite_transaction')
                ->loadByVendorTxCode($vendorTxCode);

        if (($integration == 'sagepayserver') && !$trn->getTxAuthNo()) {
            $trn->setTxAuthNo(Mage::app()->getRequest()->getParam('TxAuthNo'));
        }

        Mage::getModel('sagepaysuite/api_payment')
                ->setMcode($integration)
                ->voidPayment($trn);
    }

    public function cs($str) {
        return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $str));
    }

    /**
     * Cancel a Magento order based on Sage Pay transaction object
     *
     * @param  Ebizmarts_SagePaySuite_Model_Sagepaysuite_Transaction $transaction
     * @return null|Mage_Sales_Model_Order
     */
    public function cancelTransaction(Ebizmarts_SagePaySuite_Model_Sagepaysuite_Transaction $transaction) {

        $orderId = $transaction->getOrderId();
        if (!$orderId) {
            return;
        }

        $order = Mage::getModel('sales/order')->load($orderId);

        return $order->cancel();
    }

    /**
     * Check Magento version for layout changes.
     * Only applies for Magento 1.5.x and up
     *
     * @return boolean
     */
    public function shouldAddChildLayout() {
        return (version_compare(Mage::getVersion(), '1.4.2.0') === 1);
    }

    public function isMagentoCE1800rUp() {
        return version_compare(Mage::getVersion(), '1.8.0.0', '>=');
    }

    public function isMagentoEE113OrUp() {
        return version_compare(Mage::getVersion(), '1.13.0.0', '>=');
    }

    public function mageVersionIs($version) {
        return strstr(Mage::getVersion(), $version);
    }

    public function isSagePayMethod($code) {
        return (bool) in_array($code, $this->_sageMethods);
    }

    public function F91B2E37D34E5DC4FFC59C324BDC1157C()  { return true; }

    public function getCcImage($cname) {
        return Mage::getModel('core/design_package')->getSkinUrl('sagepaysuite/images/cc/' . str_replace(array(' ', '(', ')'), '_', strtolower($cname)) . '.png');
    }

    public function getCardLabel($value, $concatImage = true) {
        if (empty($this->_ccCards)) {
            $this->_ccCards = Mage::getModel('sagepaysuite/sagepaysuite_source_creditCards')->toOption();
        }

        $label = '';
        $cardLabel = (isset($this->_ccCards[$value]) ? $this->_ccCards[$value] : '');

        if ($concatImage) {
            $label = '<img width="51" height="32" src="' . $this->getCcImage($cardLabel) . '" title="' . $cardLabel . ' logo" alt="' . $cardLabel . ' logo" />  ';
        }

        $label .= $cardLabel;

        return $label;
    }

    /**
     * Return card expiration date in a nice format: MM/YYYY
     * @return string
     */
    public function getCardNiceDate($string) {
        $newString = $string;

        if (strlen($string) == 4) {
            $date = str_split($string, 2);
            $newString = $date[0] . '/' . '20' . $date[1];
        }

        return $newString;
    }

    public function getSagePayUrlsAsArray() {
        //$cache = Mage::getSingleton('core/cache')->load('sagepayurls');
        $cache = Mage::app()->loadCache('sagepayurls');
        if (false === $cache) {
            $file = Mage::getModuleDir('etc', 'Ebizmarts_SagePaySuite') . DS . 'sagepayurls.xml';
            $element = new Varien_Simplexml_Element(file_get_contents($file));
            $data = $element->asArray();
            $data ['sagepayserver_moto'] = $data['sagepayserver'];
            $data ['sagepaydirectpro_moto'] = $data['sagepaydirectpro'];
            //Mage::getSingleton('core/cache')->save(serialize($data), 'sagepayurls');
            Mage::app()->saveCache(serialize($data), 'sagepayurls');
            return $data;
        }

        return unserialize($cache);
    }

    public function getIframeTitle() {
        return Mage::getModel('sagepaysuite/sagePayServer')->getConfigData('iframe_label');
    }

    public function isLocalhost() {
        return Mage::helper('core/http')->getServerAddr() == '127.0.0.1';
    }

    public function orphanTransactionsFlag() {
        $orphans = false;
        $coll = Mage::getModel('sagepaysuite2/sagepaysuite_transaction')->getCollection()->addFieldToSelect('id')->existOrphans()->getSize();
        if ($coll > 0) {
            $orphans = true;
        }

        /**
         * Show only transactions that are not VOIDED
         *
         * Once a PAYMENT, AUTHORISE or REPEAT transaction has been authorised, or a
         * DEFERRED transaction has been RELEASEd, it will be settled with the acquiring
         * bank early the next morning and the funds will be moved from the customer’s card
         * account, across to your merchant account.  The bank will charge you for this
         * process, the exact amount depending on the type of card and the details of your mechant agreement.
         */
        return $orphans;
    }

    public function orphanOKTransactionsFlag() {
        $okOrhpans = false;

        $trns = Mage::getModel('sagepaysuite2/sagepaysuite_transaction')->getCollection()->getOrphans();
        if ($trns->getSize()) {
            foreach ($trns as $trn) {
                #if($trn->getStatus() == 'OK'){
                if ($trn->getData('cv2result')) {
                    $okOrhpans = true;
                    break;
                }
            }
        }

        return $okOrhpans;
    }

    public function creatingAdminOrder() {
        $controllerName = Mage::app()->getRequest()->getControllerName();
        return ($controllerName == 'sales_order_create' || $controllerName == 'adminhtml_sales_order_create' || $controllerName == 'sales_order_edit' || $controllerName == 'orderspro_order_edit');
    }

    public function getIndicator() {
        return Mage::getModel('core/design_package')->getSkinUrl('sagepaysuite/images/ajax-loader.gif');
    }

    public function getSagePayConfigJson() {
        $_store = Mage::app()->getStore();
        $_url = Mage::getModel('core/url');
        $_urlAdmin = Mage::getModel('adminhtml/url');
        $_d = Mage::getDesign();

        $serverSafeFields = Mage::getModel('sagepaysuite/sagePayServer')->getConfigSafeFields();
        $directSafeFields = Mage::getModel('sagepaysuite/sagePayDirectPro')->getConfigSafeFields();
        $nitSafeFields = Mage::getModel('sagepaysuite/sagePayNit')->getConfigSafeFields();

        $conf = array();

        $conf ['global'] = array_intersect_key($_store->getConfig('payment/sagepaysuite'), array_flip(array('debug', 'max_token_card')));
        $conf ['global']['not_valid_message'] = $this->__('This Sage Pay Suite module\'s license is NOT valid.');
        $conf ['global']['onepage_progress_url'] = $_url->getUrl('checkout/onepage/progress', array('_secure' => true));
        $conf ['global']['onepage_success_url'] = $_url->getUrl('checkout/onepage/success', array('_secure' => true));
        $conf ['global']['valid'] = (int) Mage::helper('sagepaysuite')->F91B2E37D34E5DC4FFC59C324BDC1157C();
        $conf ['global']['token_enabled'] = (int) Mage::getModel('sagepaysuite/sagePayToken')->isEnabled();
        $conf ['global']['sgps_saveorder_url'] = Mage::getModel('core/url')->addSessionParam()->getUrl('sgps/payment/onepageSaveOrder', array('_secure' => true));
        $conf ['global']['sgps_nit_generate_merchant_key'] = Mage::getModel('core/url')->addSessionParam()->getUrl('sgps/nitPayment/generateMerchantKey', array('_secure' => true));
        $conf ['global']['cart_url'] = Mage::getModel('core/url')->getUrl('checkout/cart', array('_secure' => true));
        $conf ['global']['osc_loading_image'] = '<img src="' . $_d->getSkinUrl('images/opc-ajax-loader.gif') . '" />&nbsp;&nbsp;' . $this->__('Please wait, processing your order...');
        $conf ['global']['osc_save_billing_url'] = $_url->getUrl('onestepcheckout/ajax/save_billing', array('_secure' => true));
        $conf ['global']['osc_set_methods_separate_url'] = $_url->getUrl('onestepcheckout/ajax/set_methods_separate', array('_secure' => true));
        $conf ['global']['ajax_review'] = ($this->mageVersionIs('1.5') ? '1' : '2');
        $conf ['global']['html_paymentmethods_url'] = $_url->getUrl('sgps/payment/getTokenCardsHtml', array('_secure' => true));


        if ($this->creatingAdminOrder()) {
            $conf ['global']['adminhtml_save_order_url'] = Mage::helper('adminhtml')->getUrl('adminhtml/sales_order_spsCreate/save', array('_secure' => true));
        }

        if (is_array($_store->getConfig('payment/sagepayserver'))) {
            $conf ['server'] = array_intersect_key($_store->getConfig('payment/sagepayserver'), array_flip($serverSafeFields));
            $conf ['server']['sgps_registertrn_url'] = $_url->getUrl('sgps/serverPayment/registertrn', array('_secure' => true));
            $conf ['server']['sgps_admin_registertrn_url'] = $_urlAdmin->getUrl('adminhtml/spsServerPayment/registertrn', array('_secure' => true));
            $conf ['server']['osc_savebilling_url'] = $_url->getUrl('onestepcheckout/ajax/save_billing', array('_secure' => true));
            $conf ['server']['osc_setmethods_url'] = $_url->getUrl('onestepcheckout/ajax/set_methods_separate', array('_secure' => true));
            $conf ['server']['new_token_url'] = $_url->getUrl('sgps/card/serverform', array('_secure' => true));
            $conf ['server']['secured_by_image'] = $_d->getSkinUrl('sagepaysuite/images/secured-by-sage-pay.png');
        }

        $conf ['form']['url'] = $_url->getUrl('sgps/formPayment/go', array('_secure' => true));

        $conf ['direct'] = array_intersect_key($_store->getConfig('payment/sagepaydirectpro'), array_flip($directSafeFields));
        $conf ['direct']['test_data'] = Mage::helper('sagepaysuite/sandbox')->getTestDataJson();
        $conf ['direct']['sgps_registertrn_url'] = $_url->getUrl('sgps/directPayment/transaction', array('_secure' => true));
        $conf ['direct']['sgps_registerdtoken_url'] = $_url->getUrl('sgps/directPayment/registerToken', array('_secure' => true));
        $conf ['direct']['html_paymentmethods_url'] = $_url->getUrl('sgps/directPayment/getTokenCardsHtml', array('_secure' => true));
        $conf ['direct']['threed_before'] = '<h5 class="tdnote">' . $this->__('To increase the security of Internet transactions Visa and Mastercard have introduced 3D-Secure (like an online version of Chip and PIN). You have chosen to use a card that is part of the 3D-Secure scheme, so you will need to authenticate yourself with your bank in the section below.') . '</h5>';
        $conf ['direct']['threed_after'] = '<div id="direct3d-logos"><img src="' . $_d->getSkinUrl('sagepaysuite/images/mcsc_logo.gif') . '" alt="" /><img src="' . $_d->getSkinUrl('sagepaysuite/images/vbv_logo_small.gif') . '" alt="" /><img src="' . $_d->getSkinUrl('sagepaysuite/images/sage_pay_logo.gif') . '" alt="" /></div>';

        $conf ['directmoto'] = array_intersect_key($_store->getConfig('payment/sagepaydirectpro_moto'), array_flip($directSafeFields));
        $conf ['directmoto']['test_data'] = Mage::helper('sagepaysuite/sandbox')->getTestDataJson();


        //nit
        $conf ['nit'] = array_intersect_key($_store->getConfig('payment/sagepaynit'), array_flip($nitSafeFields));
        $conf ['nit']['test_data'] = Mage::helper('sagepaysuite/sandbox')->getTestDataJson();
        $conf ['nit']['sgps_registertrn_url'] = $_url->getUrl('sgps/nitPayment/transaction', array('_secure' => true));

        return Zend_Json::encode($conf);
    }

    public function undertocamel($str) {
        $a = new Zend_Filter_Word_UnderscoreToCamelCase;

        return $a->filter($str);
    }

    public function cameltounder($str) {
        $a = new Zend_Filter_Word_CamelCaseToUnderscore;

        return strtolower($a->filter($str));
    }

    public function arrayKeysToUnderscore($array) {
        $keys = array_keys($array);
        $values = array_values($array);

        $newkeys = array_map(array($this, 'cameltounder'), $keys);

        return array_combine(array_values($newkeys), array_values($values));
    }

    public function arrayKeysToCamelCase($array) {
        $keys = array_keys($array);
        $values = array_values($array);

        $newkeys = array_map(array($this, 'undertocamel'), $keys);

        return array_combine(array_values($newkeys), array_values($values));
    }

    public function getSagePaySuiteLogDir() {
        return Mage::getBaseDir('log') . DS . 'SagePaySuite';
    }

    public function getOnepage() {
        return Mage::getSingleton('checkout/type_onepage');
    }

    public function deleteQuote() {
        $quoteID = $this->getOnepage()->getQuote()->getId();

        if ($quoteID) {
            try {
                Mage::getModel('sales/quote')->load($quoteID)->setIsActive(false)
                        ->save();
            } catch (Exception $e) {
                Mage::logException($e);
            }
        }
    }

    public function isMobileApp() {
        return (bool) (Mage::app()->getRequest()->getControllerModule() == 'Mage_XmlConnect');
    }

    public function getFailedPaymentEmaillBody($incrementId, $logName) {
        return $this->__("Something went wrong while trying to save order #%s.\nPayment has not been taken and order was not saved. Please check logfile: %s", $incrementId, $logName);
    }

    public function isSuiteEnabled() {
        $server = Mage::getStoreConfigFlag('payment/sagepayserver/active');
        $serverMoto = Mage::getStoreConfigFlag('payment/sagepayserver_moto/active');
        $directMoto = Mage::getStoreConfigFlag('payment/sagepaydirectpro_moto/active');
        $direct = Mage::getStoreConfigFlag('payment/sagepaydirectpro/active');
        $form = Mage::getStoreConfigFlag('payment/sagepayform/active');
        $paypal = Mage::getStoreConfigFlag('payment/sagepaypaypal/active');
        $nit = Mage::getStoreConfigFlag('payment/sagepaynit/active');

        return ($server || $serverMoto || $directMoto || $direct || $form || $paypal || $nit);
    }

    public function logprofiler($action) {
        $suiteLogPath = Mage::getBaseDir('var') . DS . 'log' . DS . 'SagePaySuite';
        $profilerPath = $suiteLogPath . DS . 'PROFILER';

        if (!is_dir($suiteLogPath)) {
            mkdir($suiteLogPath, 0755);
        }
        if (!is_dir($profilerPath)) {
            mkdir($profilerPath, 0755);
        }

        $timers = Varien_Profiler::getTimers();

        $request = $action->getRequest();
        $prefix  = $request->getParam('vtxcode', $request->getParam('VPSTxId', null));
        $prefix  = ($prefix ? $prefix . '_' : '');

        $longest = 10;
        $rows = array();
        foreach ($timers as $name => $timer) {

            $sum = Varien_Profiler::fetch($name, 'sum');
            $count = Varien_Profiler::fetch($name, 'count');
            $realmem = Varien_Profiler::fetch($name, 'realmem');
            $emalloc = Varien_Profiler::fetch($name, 'emalloc');
            if ($sum < .0010 && $count < 10 && $emalloc < 10000) {
                continue;
            }

            $rows [] = array((string) $name, (string) number_format($sum, 4), (string) $count, (string) number_format($emalloc), (string) number_format($realmem));
            $thislong = strlen($name);
            if ($thislong > $longest) {
                $longest = $thislong;
            }
        }

        $longest = $longest == 0 ? 10 : $longest;

        //Create table
        $table = new Zend_Text_Table(array('columnWidths' => array($longest, 10, 6, 12, 12), 'decorator' => 'ascii'));

        //Memory
        $preheader = new Zend_Text_Table_Row();
        $real = memory_get_usage(true);
        $emalloc = memory_get_usage();
        $preheader->appendColumn(new Zend_Text_Table_Column('real Memory usage: ' . $real . ' ' . ceil($real / 1048576) . 'MB', 'center', 1));
        $preheader->appendColumn(new Zend_Text_Table_Column('emalloc Memory usage: ' . $emalloc . ' ' . ceil($emalloc / 1048576) . 'MB', 'center', 4));
        $table->appendRow($preheader);

        //Append Header
        $header = new Zend_Text_Table_Row();
        $header->appendColumn(new Zend_Text_Table_Column('Code Profiler', 'center'));
        $header->appendColumn(new Zend_Text_Table_Column('Time', 'center'));
        $header->appendColumn(new Zend_Text_Table_Column('Cnt', 'center'));
        $header->appendColumn(new Zend_Text_Table_Column('Emalloc', 'center'));
        $header->appendColumn(new Zend_Text_Table_Column('RealMem', 'center'));
        $table->appendRow($header);

        foreach ($rows as $row) {
            $table->appendRow($row);
        }

        //SQL profile
        $dbprofile = print_r(Varien_Profiler::getSqlProfiler(Mage::getSingleton('core/resource')->getConnection('core_write')), TRUE);
        $dbprofile = substr($dbprofile, 0, -4);
        $dbprofile = str_replace('<br>', "\n", $dbprofile);

        $preheaderlabel = new Zend_Text_Table_Row();
        $preheaderlabel->appendColumn(new Zend_Text_Table_Column('DATABASE', 'center', 5));
        $table->appendRow($preheaderlabel);
        $preheader = new Zend_Text_Table_Row();
        $preheader->appendColumn(new Zend_Text_Table_Column($dbprofile, 'left', 5));
        $table->appendRow($preheader);

        //Request
        $rqlabel = new Zend_Text_Table_Row();
        $rqlabel->appendColumn(new Zend_Text_Table_Column('REQUEST', 'center', 5));
        $table->appendRow($rqlabel);
        $inforqp = new Zend_Text_Table_Row();
        $inforqp->appendColumn(new Zend_Text_Table_Column($this->_filterRequest($request), 'left', 5));
        $table->appendRow($inforqp);

        $date = Mage::getModel('core/date')->date('Y-m-d\.H-i-s');

        $file = new SplFileObject($profilerPath . DS . $prefix . $date . '_' . $action->getFullActionName() . '.txt', 'w');
        $file->fwrite($table);
    }

    /**
     * Filter sensitive data on REQUEST
     *
     * @param Mage_Core_Controller_Request_Http $request
     * @return array
     */
    protected function _filterRequest($request) {
        $request = print_r($request, TRUE);

        $patterns = array();
        $patterns[0] = '/\[cc_number\] \=\> [0-9+](.*)/';
        $patterns[1] = '/\[cc_cid\] \=\> [0-9+](.*)/';
        $replacements = array();
        $replacements[2] = '[cc_number] => XXXXXXXXXXXXX';
        $replacements[1] = '[cc_cid] => XXX';

        return preg_replace($patterns, $replacements, $request);
    }

    public function validateQuote() {

        if((int)Mage::getStoreConfig('payment/sagepaysuite/validate_quote') === 1){
            $quote = Mage::getSingleton('sagepaysuite/api_payment')->getQuote();

            if (!$quote->isVirtual()) {
                $address = $quote->getShippingAddress();
                $addressValidation = $address->validate();
                if ($addressValidation !== true) {
                    Mage::throwException($this->__("\nPlease check shipping address information. \n%s", implode("\n", $addressValidation)));
                }
                $method = $address->getShippingMethod();
                $rate = $address->getShippingRateByCode($method);
                if (!$quote->isVirtual() && (!$method || !$rate)) {
                    Mage::throwException($this->__('Please specify shipping method.'));
                }
            }

            $addressValidation = $quote->getBillingAddress()->validate();
            if ($addressValidation !== true) {
                Mage::throwException($this->__("\nPlease check billing address information. \n%s", implode("\n", $addressValidation)));
            }

            if (!($quote->getPayment()->getMethod())) {
                Mage::throwException($this->__('Please select valid payment method.'));
            }

            //Stock check, redirect to cart if can't continue
            if (!$quote->getInventoryProcessed()) {

                $items = $this->_getProductsQty($quote->getAllItems());

                try {

                    Mage::getModel('cataloginventory/stock')->registerProductsSale($items);
                    //Set flag
                    $quote->setInventoryProcessed(true);

                    //Rollback if all is OKAY
                    Mage::getModel('cataloginventory/stock')->revertProductsSale($items);
                    // Clear flag
                    $quote->setInventoryProcessed(false);

                }catch(Exception $ex) {
                    throw new Exception('REDIRECT_CART');
                }
            }
        }
    }

    /**
     * Prepare array with information about used product qty and product stock item
     * result is:
     * array(
     *  $productId  => array(
     *      'qty'   => $qty,
     *      'item'  => $stockItems|null
     *  )
     * )
     * @param array $relatedItems
     * @return array
     */
    private function _getProductsQty($relatedItems)
    {
        $items = array();
        foreach ($relatedItems as $item) {
            $productId  = $item->getProductId();
            if (!$productId) {
                continue;
            }
            $children = $item->getChildrenItems();
            if ($children) {
                foreach ($children as $childItem) {
                    $this->_addItemToQtyArray($childItem, $items);
                }
            } else {
                $this->_addItemToQtyArray($item, $items);
            }
        }
        return $items;
    }

    /**
     * Adds stock item qty to $items (creates new entry or increments existing one)
     * $items is array with following structure:
     * array(
     *  $productId  => array(
     *      'qty'   => $qty,
     *      'item'  => $stockItems|null
     *  )
     * )
     *
     * @param Mage_Sales_Model_Quote_Item $quoteItem
     * @param array &$items
     */
    private function _addItemToQtyArray($quoteItem, &$items)
    {
        $productId = $quoteItem->getProductId();
        if (!$productId)
            return;
        if (isset($items[$productId])) {
            $items[$productId]['qty'] += $quoteItem->getTotalQty();
        } else {
            $stockItem = null;
            if ($quoteItem->getProduct()) {
                $stockItem = $quoteItem->getProduct()->getStockItem();
            }
            $items[$productId] = array(
                'item' => $stockItem,
                'qty'  => $quoteItem->getTotalQty()
            );
        }
    }

    public function moneyFormat($number) {
        return number_format($number, 2, '.', '');
    }

    public function getTxStates() {

        if (is_null($this->_states)) {
            $this->_states = Mage::getConfig()->getNode('global/tx_states')->asArray();
        }

        return $this->_states;
    }

    /**
     * Skip PostCode and Address Validation for overseas orders
     */

    public function isOverseasOrder($country) {

        return $country != 'GB' && // United Kingdom
            $country != 'GG' && //Guernsey
            $country != 'IM' && //Isle Of Man
            $country != 'JE'; //Jersey
    }

    /**
     * Make sure addresses will be saved without validation errors
     */
    public function ignoreAddressValidation($quote) {

        $ignoreAddressValidation = (int)Mage::getStoreConfig('payment/sagepaysuite/ignore_address_validation');

        if(1 === $ignoreAddressValidation) {
            $quote->getBillingAddress()->setShouldIgnoreValidation(true);
            if (!$quote->getIsVirtual()) {
                $quote->getShippingAddress()->setShouldIgnoreValidation(true);
            }
        }
    }

}
