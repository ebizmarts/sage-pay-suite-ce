<?php

class Ebizmarts_SagePayReporting_Block_Adminhtml_Sagepayreporting_Whitelistip_Edit_Form extends Mage_Adminhtml_Block_Widget_Form {

    protected function _prepareForm() {
        
        $form = new Varien_Data_Form(array('id' => 'edit_form', 'action' => $this->getData('action'), 'method' => 'post'));

        $fieldset = $form->addFieldset('base_fieldset', array('legend' => Mage::helper('sagepayreporting')->__('IP Information')));

        $data = array();
        $data['ip_address'] = Mage::getSingleton('sagepayreporting/dtools')->getIpAddress();
        $data['ip_mask'] = '255.255.255.255';
        $data['vendor'] = Mage::getStoreConfig('sagepayreporting/account/vendor');
        $data['ip_note'] = Mage::helper('sagepayreporting')->__('IP address added on %s from API by %s', Mage::getModel('core/date')->date('l jS \of F Y'), Mage::getSingleton('admin/session')->getUser()->getUsername());

        $fieldset->addField('ip_address', 'text', array(
            'name' => 'ip_address',
            'label' => Mage::helper('sagepayreporting')->__('IP Address'),
            'id' => 'ip_address',
            'class' => 'required-entry',
            'required' => true,
                )
        );
        $fieldset->addField('ip_mask', 'text', array(
            'name' => 'ip_mask',
            'label' => Mage::helper('sagepayreporting')->__('IP Mask'),
            'id' => 'ip_mask',
            'class' => 'required-entry',
            'required' => true,
                )
        );
        $fieldset->addField('ip_note', 'text', array(
            'name' => 'ip_note',
            'label' => Mage::helper('sagepayreporting')->__('IP Note'),
            'id' => 'ip_note',
            'class' => 'required-entry',
            'required' => true,
                )
        );
        $fieldset->addField('vendor', 'text', array(
            'name' => 'vendor',
            'label' => Mage::helper('sagepayreporting')->__('Vendor'),
            'id' => 'vendor',
            'class' => 'required-entry',
            'required' => true,
                )
        );
        $fieldset->addField('mode', 'multiselect', array(
            'name' => 'mode',
            'label' => Mage::helper('sagepayreporting')->__('Mode'),
            'id' => 'mode',
            'class' => 'required-entry',
            'required' => true,
            'values' => array(
                array('value' => '2', 'label' => Mage::helper('sagepayreporting')->__('Test')),
                array('value' => '1', 'label' => Mage::helper('sagepayreporting')->__('Live')),
            )
                )
        );

        $fieldset->addField('api_username', 'text', array(
            'name' => 'api_username',
            'label' => Mage::helper('sagepayreporting')->__('API Username'),
            'id' => 'api_username',
            'note' => Mage::helper('sagepayreporting')->__('If not provided, Reporting configuration value will be used')
                )
        );
        $fieldset->addField('api_password', 'password', array(
            'name' => 'api_password',
            'label' => Mage::helper('sagepayreporting')->__('API Password'),
            'id' => 'api_password',
            'note' => Mage::helper('sagepayreporting')->__('If not provided, Reporting configuration value will be used')
                )
        );

        $form->setValues($data);

        $form->setUseContainer(true);
        $this->setForm($form);

        $this->setChild('form_after', Mage::getModel('core/layout')->createBlock('sagepayreporting/adminhtml_sagepayreporting_whitelistip_whites', 'whiteips'));

        return parent::_prepareForm();
    }

}
