<?php

/**
 * CARD frontend controller, token
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */
class Ebizmarts_SagePaySuite_CardController extends Mage_Core_Controller_Front_Action {

    protected $_eoln = Ebizmarts_SagePaySuite_Model_Api_Payment::RESPONSE_DELIM_CHAR;

    /**
     * Retrieve customer session model object
     *
     * @return Mage_Customer_Model_Session
     */
    protected function _getSession() {
        return Mage::getSingleton('customer/session');
    }

    protected function _getServerRegisterCard() {
        return Mage::getModel('sagepaysuite/sagePayToken')->registerCard();
    }

    public function serverformAction() {
        $rs = $this->_getServerRegisterCard();

        if (!isset($rs['NextURL'])) {
            echo '<html><body>';
            echo Mage::helper('sagepaysuite')->__($rs['StatusDetail']);
            echo '<script type="text/javascript">setTimeout(function(){window.parent.Control.Window.windows.each(function(w){
																						if(w.container.visible()){
																							w.close();
																						}
																	   }); try{window.parent.checkout.accordion.openSection("opc-payment")}catch(a){}}, 3000)</script></html></body>';
            return;
        }

        echo '<style type="text/css">iframe#iframeRegCard {height:518px; width:100%; border:none;}</style>';
        echo $this->getLayout()->createBlock('sagepaysuite/customer_account_card_server_form')->setNextUrl($rs['NextURL'])->toHtml();

        return;
    }

    public function registerAction() {

        /*
         * DIRECT POST
         */
        if ($this->getRequest()->isPost()) { #DIRECT POST
            $post = $this->getRequest()->getPost();

            $post['ExpiryDate'] = str_pad($post['ExpiryMonth'], 2, '0', STR_PAD_LEFT) . substr($post['ExpiryYear'], 2);

            if ($post['StartMonth'] && $post['StartYear']) {
                $post['StartDate'] = str_pad($post['StartMonth'], 2, '0', STR_PAD_LEFT) . substr($post['StartYear'], 2);
            }

            $rs = Mage::getModel('sagepaysuite/sagePayToken')->registerCard($post);

            if (empty($rs)) {
                $rs['Status'] = 'ERROR';
                $rs['StatusDetail'] = Mage::helper('sagepaysuite')->__('A server to server communication error ocurred, please try again later.');
            }

            if ($rs['Status'] == 'OK') {

                //Save token card to database
                $tokenData = array(
                    'Token'        => $rs['Token'],
                    'Status'       => $rs['Status'],
                    'Vendor'       => Mage::getModel('sagepaysuite/sagePayToken')->getConfigData('vendor'),
                    'CardType'     => $post['CardType'],
                    'ExpiryDate'   => $post['ExpiryDate'],
                    'StatusDetail' => $rs['StatusDetail'],
                    'Protocol'     => 'direct',
                    'CardNumber'   => $post['CardNumber'],
                    'Nickname'     => $post['Nickname']
                );
                $save = Mage::getModel('sagepaysuite/sagePayToken')->persistCard($tokenData);

                if(isset($save) && $save !== FALSE){
                    $rs   = array_change_key_case($rs);
                    $resp = $rs;
                    $resp ['mark'] = array(
                        'cctype'         => $save->getLabel(),
                        'id'             => $save->getId(),
                        'defaultchecked' => ($save->getIsDefault() == 1 ? ' checked="checked"' : ''),
                        'ccnumber'       => $save->getCcNumber(),
                        'exp'            => $save->getExpireDate(),
                        'ccnickname'     => $save->getNickname(),
                        'delurl'         => Mage::getUrl('sgps/card/delete', array('card' => $save->getId()))
                    );
                }else{
                    $rs['Status'] = 'ERROR';
                    $rs['StatusDetail'] = Mage::helper('sagepaysuite')->__('Credit card could not be saved for future use. You already have this card attached to your account or you have reached your account\'s maximum card storage capacity.');
                    $rs   = array_change_key_case($rs);
                    $resp = $rs;
                }

            } else {
                $rs = array_change_key_case($rs);
                $resp = $rs;
            }

            return $this->getResponse()->setBody(Zend_Json::encode($resp));
        }
        /*
         * DIRECT POST
         */

        $url = '';

        if (false === Mage::getModel('sagepaysuite/sagePayToken')->customerCanAddCard()) {

            $url = 'ERROR';
            $text = $this->__('You can\'t add more cards, please delete one if you want to add another.');
        } else {

            $int = $this->_getTokenIntegrationType();

            if ($int == 'server') {

                $rs = $this->_getServerRegisterCard();

                if ($rs['Status'] == 'OK') {
                    $config = (string) Mage::getStoreConfig('payment/sagepayserver/token_iframe_position');
                    if ($config == 'full_redirect') {
                        $text = 'full_redirect';
                        $url = $rs['NextURL'];
                    }else{
                        $text = $this->getLayout()->createBlock('sagepaysuite/customer_account_card_server_form')->setNextUrl($rs['NextURL'])->toHtml();
                    }
                } else {
                    $url = 'ERROR';
                    $text = $rs['StatusDetail'];
                }
            } else {
                $text = $this->getLayout()->createBlock('sagepaysuite/customer_account_card_direct_form')->toHtml();
            }
        }


        return $this->getResponse()->setBody(Zend_Json::encode(array('text' => $text, 'url' => $url)));
    }

    public function closeserverformAction() {
        return $this->getResponse()->setBody('<html>
                                                    <body>
                                                        <script type="text/javascript">
															if ( window.parent.$(\'multishipping-billing-form\') ){
																window.parent.$(\'multishipping-billing-form\').submit();
															}
															window.parent.Control.Window.windows.each(function(w){
																						if(w.container.visible()){
																							w.close();
																						}
																	   });
														</script>
                                                    </body>
                                                </html>');
    }

    public function closeserverabortformAction() {
        return $this->getResponse()->setBody('<html>
                                                    <body>
                                                        <script type="text/javascript">
															window.parent.Control.Window.windows.each(function(w){
																						if(w.container.visible()){
																							w.close();
																						}
																	   });
															window.parent.checkout.accordion.openSection("opc-payment");
														</script>
                                                    </body>
                                                </html>');
    }

    public function registerSuccessAction()
    {
        $config = (string) Mage::getStoreConfig('payment/sagepayserver/token_iframe_position');
        if ($config == 'full_redirect') {
            $this->_redirect('*/card/');
            return;
        } else {
            return $this->getResponse()->setBody('<html>
                                                    <body>
                                                        <!--<h2>' . $this->__('Redirecting') . '</h2>-->
														<div style="background-image:url(' . Mage::helper('sagepaysuite')->getIndicator() . '); background-position: center center;background-repeat: no-repeat;height: 400px;">&nbsp;</div>
                                                        <script type="text/javascript">
															var url = window.parent.location.href;
															if(url.match(/serverform/gi)){
																window.parent.location.href="' . Mage::getUrl('sgps/card/closeserverform') . '";
															}else{
															    var successCallback = function(){window.parent.location.reload();};
															    var token_id = "' . $this->getRequest()->getParam('token_id') . '";
															    if(token_id && token_id != ""){
															        window.parent.updateNickname(token_id,window.parent.$("sagepaytoken_cc_nickname").value,successCallback,null);
															    }else{
															        window.parent.location.reload();
															    }
															}
														</script>
                                                    </body>
                                                </html>');
        }
    }

    public function registerAbortAction()
    {
        $config = (string) Mage::getStoreConfig('payment/sagepayserver/token_iframe_position');
        if ($config == 'full_redirect') {
            $this->_redirect('*/card/');
            return;
        } else {
            return $this->getResponse()->setBody('<html>
                                                    <body>
                                                        <!--<h2>' . $this->__('Redirecting') . '</h2>-->
														<div style="background-image:url(' . Mage::helper('sagepaysuite')->getIndicator() . '); background-position: center center;background-repeat: no-repeat;height: 400px;">&nbsp;</div>
                                                        <script type="text/javascript">
															var url = window.parent.location.href;
															if(url.match(/serverform/gi)){
																window.parent.location.href="' . Mage::getUrl('sgps/card/closeserverabortform') . '";
															}else{
																window.parent.location.reload();
															}
														</script>
                                                    </body>
                                                </html>');
        }
    }

    /**
     * Handle callback.
     *
     * @return string
     */
    public function registerPostAction() {

        $post = $this->getRequest()->getPost();

        $paramCustomerId = $this->getRequest()->getParam('cid','');

        $response = '';

        if ($post['Status'] == 'OK') {

            $post['protocol'] = 'direct';
            if (array_key_exists('ExpiryDate', $post)) {
                $post['protocol'] = 'server';
            }

            //Save token card to database
            $tokenData = array(
                'Token'        => $post['Token'],
                'Status'       => $post['Status'],
                'Vendor'       => Mage::getModel('sagepaysuite/sagePayToken')->getConfigData('vendor'),
                'CardType'     => $post['CardType'],
                'ExpiryDate'   => $post['ExpiryDate'],
                'StatusDetail' => $post['StatusDetail'],
                'Protocol'     => $post['protocol'],
                'CardNumber'   => $post['Last4Digits'],
            );

            $save = Mage::getModel('sagepaysuite/sagePayToken')->persistCard($tokenData, $paramCustomerId);

            if(isset($save) && $save !== FALSE) {
                Mage::getSingleton('sagepaysuite/session')->setLastSavedTokenccid($save->getId());

                $response .= 'Status=OK' . $this->_eoln;
                $response .= 'RedirectURL=' . Mage::getUrl('sgps/card/registerSuccess') . '?SID=' . $this->getRequest()->getParam('SID', '') . $this->_eoln;
                $response .= 'StatusDetail=Card successfully registered.' . $this->_eoln;
            }else{
                $response .= 'Status=OK' . $this->_eoln;
                $response .= 'RedirectURL=' . Mage::getUrl('sgps/card/registerAbort') . '?SID=' . $this->getRequest()->getParam('SID', '') . $this->_eoln;
                $response .= 'StatusDetail='.Mage::helper('sagepaysuite')->__('Credit card could not be saved for future use. You already have this card attached to your account or you have reached your account\'s maximum card storage capacity.') . $this->_eoln;
            }

        } else if ($post['Status'] == 'ABORT') {

            $response .= 'Status=OK' . $this->_eoln;
            $response .= 'RedirectURL=' . Mage::getUrl('sgps/card/registerAbort') . '?SID=' . $this->getRequest()->getParam('SID', '') . $this->_eoln;
            $response .= 'StatusDetail=Card registering was aborted. ' . $post['StatusDetail'] . $this->_eoln;

        }


        $this->getResponse()->setHeader('Content-type', 'text/plain');
        return $this->getResponse()->setBody($response);
    }

    public function indexAction() {
        if (!$this->_getCustomerId()) {
            Mage::getSingleton('customer/session')->authenticate($this);
            return;
        }

        $this->loadLayout();
        $this->_initLayoutMessages('catalog/session');
        $this->_initLayoutMessages('customer/session');

        if ($navigationBlock = $this->getLayout()->getBlock('customer_account_navigation')) {
            $navigationBlock->setActive('sgps/card');
        }
        if ($block = $this->getLayout()->getBlock('customer.account.link.back')) {
            $block->setRefererUrl($this->_getRefererUrl());
        }

        $this->getLayout()->getBlock('head')->setTitle(Mage::helper('sagepaysuite')->__('Sage Pay - Saved Credit Cards'));
        $this->renderLayout();
    }

    /**
     * Update card nickname on local database.
     *
     * @return string(JSON)
     */
    public function updateNicknameAction(){


        $resp = array('st' => 'nok', 'text' => '');

        if (!Mage::getSingleton('customer/session')->authenticate($this)) {
            $resp ['text'] = $this->__('Please login, you session expired.');
            $this->getResponse()->setBody(Zend_Json::encode($resp));
            return;
        }

        $cardId = (int) $this->getRequest()->getParam('card');
        $cardNickname = $this->getRequest()->getParam('nickname');

        $objCard = Mage::getModel('sagepaysuite2/sagepaysuite_tokencard')->load($cardId);

        if ($objCard->getId()) {

            # Check if card is from this customer
            if ($objCard->getCustomerId() != $this->_getCustomerId()) {
                $resp ['text'] = $this->__('Invalid Card #');
                $this->getResponse()->setBody(Zend_Json::encode($resp));
                return;
            }

            $update = $objCard->setNickname($cardNickname)->save();

            if($update){
                $resp ['text'] = $this->__('Success!');
                $resp ['st']   = 'ok';
                $this->getResponse()->setBody(Zend_Json::encode($resp));
                return;
            }else{
                $resp ['text'] = $this->__('Something prevented this credit card to be saved.');
                $this->getResponse()->setBody(Zend_Json::encode($resp));
                return;
            }

        }else{
            $resp ['text'] = $this->__('The requested Card does not exist.');
            $this->getResponse()->setBody(Zend_Json::encode($resp));
            return;
        }
    }

    /**
     * Delete card from local database and Sage Pay.
     *
     * @return string
     */
    public function deleteAction() {
        $resp = array('st' => 'nok', 'text' => '');

        if (!Mage::getSingleton('customer/session')->authenticate($this)) {
            $resp ['text'] = $this->__('Please login, you session expired.');
            $this->getResponse()->setBody(Zend_Json::encode($resp));
            return;
        }

        $cardId = (int) $this->getRequest()->getParam('card');

        $objCard = Mage::getModel('sagepaysuite2/sagepaysuite_tokencard')->load($cardId);
        if ($objCard->getId()) {

            # Check if card is from this customer
            if ($objCard->getCustomerId() != $this->_getCustomerId()) {
                $resp ['text'] = $this->__('Invalid Card #');
                $this->getResponse()->setBody(Zend_Json::encode($resp));
                return;
            }

            try {
                $delete = Mage::getModel('sagepaysuite/sagePayToken')->removeCard($objCard->getToken(), $objCard->getProtocol());

                if ($delete['Status'] == 'OK' || (1 === preg_match('/^4057/i', $delete['StatusDetail']))) {

                    $objCard->delete();

                    $resp ['text'] = $this->__('Success!');
                    $resp ['st']   = 'ok';
                    $this->getResponse()->setBody(Zend_Json::encode($resp));
                    return;
                } else {
                    $resp ['text'] = $this->__('An error ocurred, %s', $delete['StatusDetail']);
                    $this->getResponse()->setBody(Zend_Json::encode($resp));
                    return;
                }
            } catch (Exception $e) {

                $resp ['text'] = $this->__($e->getMessage());
                $this->getResponse()->setBody(Zend_Json::encode($resp));
                return;
            }
        }

        $resp ['text'] = $this->__('The requested Card does not exist.');
        $this->getResponse()->setBody(Zend_Json::encode($resp));
        return;
    }

    /*
     * Set default card
     */
    public function defaultAction() {
        $resp = array('st' => 'nok', 'text' => '');

        if (!Mage::getSingleton('customer/session')->authenticate($this)) {
            $resp ['text'] = $this->__('Please login, you session expired.');
            $this->getResponse()->setBody(Zend_Json::encode($resp));
            return;
        }

        $cardId = (int) $this->getRequest()->getParam('card');

        $objCard = Mage::getModel('sagepaysuite2/sagepaysuite_tokencard')->load($cardId);
        if ($objCard->getId()) {

            # Check if card is from this customer
            if ($objCard->getCustomerId() != $this->_getCustomerId()) {
                $resp ['text'] = $this->__('Invalid Card #');
                $this->getResponse()->setBody(Zend_Json::encode($resp));
                return;
            }

            try {

                $objCard->setIsDefault(1)
                        ->save();

                $resp ['text'] = $this->__('Success!');
                $resp ['st'] = 'ok';
                $this->getResponse()->setBody(Zend_Json::encode($resp));
                return;
            } catch (Exception $e) {

                $resp ['text'] = $this->__($e->getMessage());
                $this->getResponse()->setBody(Zend_Json::encode($resp));
                return;
            }
        }

        $resp ['text'] = $this->__('The requested Card does not exist.');
        $this->getResponse()->setBody(Zend_Json::encode($resp));
        return;
    }

    protected function _getCustomerId() {
        return (int) Mage::getModel('sagepaysuite/sagePayToken')->getSessionCustomerId();
    }

    protected function _getTokenIntegrationType() {
        return (string) Mage::getStoreConfig('payment/sagepaysuite/token_integration');
    }

}
