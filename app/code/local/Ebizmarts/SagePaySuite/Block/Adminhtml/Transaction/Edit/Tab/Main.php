<?php

class Ebizmarts_SagePaySuite_Block_Adminhtml_Transaction_Edit_Tab_Main extends Mage_Adminhtml_Block_Widget_Form {

    public $_formFields = array(
        'vendor_tx_code' => array(
            'label'    => 'Vendor TX Code',
            'note'     => '',
            'required' => true,
        ),
        'vps_tx_id' => array(
            'label'    => 'Sage Pay unique ID',
            'note'     => '',
            'required' => true,
        ),
        'status' => array(
            'label'    => 'Status',
            'note'     => '',
            'required' => true,
        ),
        'status_detail' => array(
            'label'    => 'Status Detail',
            'note'     => '',
            'required' => true,
        ),
        'vps_protocol' => array(
            'label'    => 'VPS Protocol',
            'note'     => '',
            'required' => true,
        ),
        'integration' => array(
            'label'    => 'Integration',
            'note'     => 'DIRECT, SERVER, FORM',
            'required' => true,
        ),
        'vendorname' => array(
            'label'    => 'Vendor',
            'note'     => '',
            'required' => true,
        ),
        'mode' => array(
            'label'    => 'Mode',
            'note'     => '',
            'required' => true,
        ),
        'trn_currency' => array(
            'label'    => 'Currency',
            'note'     => '',
            'required' => true,
        ),
        'tx_type' => array(
            'label'    => 'TX Type',
            'note'     => 'PAYMENT, DEFERRED, AUTHENTICATE...',
            'required' => true,
        ),
        'security_key' => array(
            'label'    => 'Security Key',
            'note'     => '',
            'required' => true,
        ),
        'tx_auth_no' => array(
            'label'    => 'TX Auth. Number',
            'note'     => '',
            'required' => true,
        ),
        'card_type' => array(
            'label'    => 'Card Type',
            'note'     => '',
            'required' => true,
        ),
        'last_four_digits' => array(
            'label'    => 'Last four digits',
            'note'     => '',
            'required' => true,
        ),
        'order_id' => array(
            'label'    => 'Order ID',
            'note'     => 'Associated order id for this transaction.',
            'required' => false,
        ),
        'customer_contact_info' => array(
            'label'    => 'Customer contact information',
            'note'     => '',
            'required' => false,
        ),
    );

    public $_formSecurityFields = array(
        'avscv2' => array(
            'label'    => 'AVS CV2',
            'note'     => '',
            'required' => false,
        ),
        'address_result' => array(
            'label'    => 'Address result',
            'note'     => '',
            'required' => false,
        ),
        'postcode_result' => array(
            'label'    => 'Postcode result',
            'note'     => '',
            'required' => false,
        ),
        'cv2result' => array(
            'label'    => 'CV2 result',
            'note'     => '',
            'required' => false,
        ),
        'threed_secure_status' => array(
            'label'    => '3D secure status',
            'note'     => '',
            'required' => false,
        ),
        'cavv' => array(
            'label'    => 'CAVV',
            'note'     => 'Unique signature for a validated 3D-Secure transaction',
            'required' => false,
        ),
        'vps_signature' => array(
            'label'    => 'VPS Signature',
            'note'     => '',
            'required' => false,
        ),
        'bank_auth_code' => array(
            'label'    => 'Bank auth. code',
            'note'     => '',
            'required' => false,
        ),
        'decline_code' => array(
            'label'    => 'Decline code',
            'note'     => '',
            'required' => false,
        ),
        'address_status' => array(
            'label'    => 'Address status',
            'note'     => 'PayPal transactions only.',
            'required' => false,
        ),
        'payer_status' => array(
            'label'    => 'Payer status',
            'note'     => 'PayPal transactions only.',
            'required' => false,
        ),
    );

    protected function _prepareForm() {
        $model = Mage::registry('sagepaysuite_transaction');

        $form = new Varien_Data_Form();

        $form->setHtmlIdPrefix('transaction_');

        $fieldset = $form->addFieldset('base_fieldset', array('legend' => Mage::helper('sagepaysuite')->__('General')));
        foreach($this->_formFields as $id => $data) {

            $fieldset->addField($id, 'text', array(
                'name'     => 'transaction[' . $id . ']',
                'label'    => Mage::helper('sagepaysuite')->__($data['label']),
                'id'       => $id,
                'title'    => Mage::helper('sagepaysuite')->__($data['label']),
                'required' => $data['required'],
                'note'     => $data['note'],
            ));

        }

        $fieldsetSec = $form->addFieldset('security_fieldset', array('legend' => Mage::helper('sagepaysuite')->__('Security')));
        foreach($this->_formSecurityFields as $id => $data) {

            $fieldsetSec->addField($id, 'text', array(
                'name'     => 'transaction[' . $id . ']',
                'label'    => Mage::helper('sagepaysuite')->__($data['label']),
                'id'       => $id,
                'title'    => Mage::helper('sagepaysuite')->__($data['label']),
                'required' => $data['required'],
                'note'     => $data['note'],
            ));

        }

        $fieldset->addField('id', 'hidden', array(
            'name'     => 'transaction[id]',
        ));

        $data = $model->getData();

        $form->setValues($data);

        $this->setForm($form);

        return parent::_prepareForm();
    }

}