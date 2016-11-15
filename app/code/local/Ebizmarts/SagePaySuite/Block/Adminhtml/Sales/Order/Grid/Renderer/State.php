<?php

class Ebizmarts_SagePaysuite_Block_Adminhtml_Sales_Order_Grid_Renderer_State extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{

    /**
     * Renders grid column
     *
     * @param   Varien_Object $row
     * @return  string
     */
    public function render(Varien_Object $row)
    {
        $result = parent::render($row);

        $transaction = Mage::getModel('sagepaysuite2/sagepaysuite_transaction')
            ->loadByParent($row->getId());

        if ($transaction->getId()) {

            if (((string)Mage::getStoreConfig('payment/sagepaysuite/sync_mode')) === 'sync') {

                //check date, if order is newer than 24 hours check status otherwise just show
                $datetime1 = new DateTime($row->getCreatedAt());
                $datetime2 = new DateTime(Mage::getModel('core/date')->gmtDate(null, "-1 day"));

                if ($datetime1 > $datetime2) {
                    $transaction->updateFromApi();
                }
            }

            $result = $transaction->getStatus();

            if ((int)$transaction->getTxStateId()) {
                $states = $this->helper('sagepaysuite')->getTxStates();
                $result = '<img src="' . $this->_icon($transaction->getTxStateId()) . '" title="Transaction state: ' . $states["stateid_{$transaction->getTxStateId()}"] . '" />';
            }

            //Fraud
            $fraud = Mage::getModel('sagepayreporting/sagepayreporting_fraud')->loadByOrderId($row->getId());
            if (!is_null($fraud->getThirdmanScore())) {
                $title = $this->__("Fraud: %s. Score is: %s", $fraud->getThirdmanAction(), $fraud->getThirdmanScore());
                $result .= '&nbsp;&nbsp;<img src="' . $this->_fraudIcon($fraud->getThirdmanScore()) . '" title="' . $title . '" />';
            }

            //ReD
            $red = (string)$transaction->getRedFraudResponse();
            if (!empty($red)) {
                $redTitle = $this->__("ReD Status: %s.", $red);
                $result .= '&nbsp;&nbsp;<img src="' . $this->_redFraudIcon($red) . '" title="' . $redTitle . '" />';
            }

        }

        return $result;
    }

    protected function _redFraudIcon($status)
    {
        switch (strtoupper($status)) {
            case 'ACCEPT':
                return $this->_shield("check");
                break;
            case 'DENY':
                return $this->_shield("cross");
                break;
            case 'CHALLENGE':
                return $this->_shield("zebra");
                break;
            case 'NOTCHECKED':
                return $this->_shield("outline");
                break;
        }
    }

    protected function _fraudIcon($score)
    {

        if ($score < 30) {
            $type = "check";
        } else if ($score >= 30 && $score <= 49) {
            $type = "zebra";
        } else {
            $type = "cross";
        }

        return $this->_shield($type);
    }

    protected function _icon($txStateId)
    {

        $type = "outline";

        $crossArray = array(1, 8, 9, 10, 11, 12, 13, 17, 18, 19, 20, 22, 23, 27);
        if (in_array($txStateId, $crossArray)) {
            $type = "cross";
        }


        $checkArray = array(14, 15, 16, 26);
        if (in_array($txStateId, $checkArray)) {
            $type = "check";
        }


        $outlineArray = array(2, 3, 4, 5, 6, 7, 24, 25, 21);
        if (in_array($txStateId, $outlineArray)) {
            $type = "outline";
        }


        return $this->_shield($type);
    }

    protected function _shield($type)
    {
        return $this->getSkinUrl("sagepaysuite/images/flags/icon-shield-{$type}.png");
    }

}
