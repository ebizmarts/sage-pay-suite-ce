<?php

/**
 * Abstract info block
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */
class Ebizmarts_SagePaySuite_Block_Info_Suite extends Mage_Payment_Block_Info_Cc {

    public function getSpecificInformation() {
        $tokenId = $this->isTokenInfo();
        if (!$tokenId) {
            return parent::getSpecificInformation();
        }

        return $this->helper('sagepaysuite/token')->getDataAsArray($tokenId);
    }

    protected function _construct() {
        parent::_construct();

        if (Mage::getSingleton('core/translate')->getTranslateInline() === false && Mage::app()->getStore()->isAdmin()) { //For Emails
            $this->setTemplate('sagepaysuite/payment/info/base-basic.phtml');
        } else {
            $this->setTemplate('sagepaysuite/payment/info/base.phtml');
        }
    }

    public function getOnMemo() {
        $r = $this->getRequest();
        return (bool) (!is_null(Mage::registry('current_creditmemo')) || ($r->getControllerName() == 'sales_order_creditmemo' && $r->getActionName() == 'view'));
    }

    public function getOnInvoice() {
        $r = $this->getRequest();
        return (bool) (!is_null(Mage::registry('current_invoice')) || ($r->getControllerName() == 'sales_order_invoice' && $r->getActionName() == 'view'));
    }

    public function getTokenCard() {
        $token = $this->isTokenInfo();
        if ($token) {
            if (is_object($this->getInfo()->getOrder()) && is_object($this->getInfo()->getOrder()->getSagepayInfo()) && $this->getInfo()->getOrder()->getSagepayInfo()->getToken()) {
                $t = Mage::getModel('sagepaysuite2/sagepaysuite_tokencard')->loadByToken($this->getInfo()->getOrder()->getSagepayInfo()->getToken());
            } else {
                $t = Mage::getModel('sagepaysuite2/sagepaysuite_tokencard')->load($this->getInfo()->getSagepayTokenCcId());
            }

            return $t;
        }

        return new Varien_Object;
    }

    public function isTokenInfo() {
        if (is_object($this->getInfo()->getOrder()) && is_object($this->getInfo()->getOrder()->getSagepayInfo())) {
            return (bool) (strlen($this->getInfo()->getOrder()->getSagepayInfo()->getToken()) > 0);
        } else if ($this->getInfo()->getSagepayTokenCcId()) {
            return true;
        }
        return false;
    }

    public function getCcTypeName($type = null, $textOnly = false) {
        $types = Mage::getSingleton('sagepaysuite/config')->getCcTypesSagePayDirect(true);
        $ccType = ($type === null) ? $this->getInfo()->getCcType() : $type;
        if (isset($types[$ccType])) {

            if (true === $textOnly) {
                return $types[$ccType];
            }

            $name = '<img width="51" height="32" alt="' . $types[$ccType] . '" title="' . $types[$ccType] . '" src="' . $this->helper('sagepaysuite')->getCcImage($types[$ccType]) . '"/>';

            return $name;
        }
        return (empty($ccType)) ? Mage::helper('payment')->__('N/A') : $ccType;
    }

    public function getBasicRealTitle() {
        $title = $this->getMethod()->getTitle();

        return $title;
    }

    protected function _getDetailUrl($vpsTxId) {
        return $this->helper('adminhtml')->getUrl('adminhtml/sagepayreporting/transactionDetailModal/', array('vpstxid' => $vpsTxId));
    }

    public function getStoppedLabel($trn) {
        $txt = '-';

        if ($trn->getVoided()) {
            $txt = $this->__('Transaction is VOIDED.');
        }

        return $txt;
    }

    public function detailLink($vpsTxId) {
        $title = $this->__('Click here to view Transaction detail.');
        return sprintf('<a title="%s" id="%s" class="trn-detail-modal" href="%s">%s</a>', $title, str_replace(array('{', '}'), '', $vpsTxId), $this->_getDetailUrl($vpsTxId), $vpsTxId);
    }

    public function getParentOrderLink($sagepay) {
        $lnk = '';

        if ($sagepay->getParentTrnId()) {
            $parent = Mage::getModel('sagepaysuite2/sagepaysuite_transaction')->load($sagepay->getParentTrnId());
            if ($parent->getOrderId()) {
                $order = Mage::getModel('sales/order')->load($parent->getOrderId());

                $lnk .= '<a href="' . $this->getUrl('*/sales_order/view', array('order_id' => $order->getId())) . '">#' . $order->getIncrementId() . '</a>';
            }
        }

        return $lnk;
    }

    public function cs($str) {
        return Mage::helper('sagepaysuite')->cs($str);
    }

    public function toPdf() {
        $this->setTemplate('sagepaysuite/payment/info/pdf/base-basic.phtml');
        return $this->toHtml();
    }

    public function getThirdmanBreakdown($thirdmanId) {
        try {
            $breakdown = Mage::getModel('sagepayreporting/sagepayreporting')->getT3MDetail($thirdmanId);
            if($breakdown['ok'] === true){
                $breakdown = $breakdown['result'];
            }else{
                $breakdown = null;
            }
        } catch (Exception $e) {
            $breakdown = null;
            Mage::logException($e);
        }
        return $breakdown;
    }

}
