<?php

/**
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */
class Ebizmarts_SagePaySuite_Model_Api_Resource extends Mage_Api_Model_Resource_Abstract {

    /**
     * Default ignored attribute codes per entity type
     *
     * @var array
     */
    protected $_ignoredAttributeCodes = array(
        'global'    =>  array('entity_id', 'attribute_set_id', 'entity_type_id'),
        'transaction' => array('surcharge_amount', 'quote_id', 'store_id',
                               'giftaid', 'canceled', 'voided', 'aborted', 'released',
                               'authorised')        

    );

    /**
     * Attributes map array per entity type
     *
     * @var google
     */
    protected $_attributesMap = array(
        'global'    => array()
    );

	/**
     * Update attributes for entity
     *
     * @param array $data
     * @param Mage_Core_Model_Abstract $object
     * @param array $attributes
     * @return Mage_Sales_Model_Api_Resource
     */
    protected function _updateAttributes($data, $object, $type,  array $attributes = null)
    {

        foreach ($data as $attribute=>$value) {
            if ($this->_isAllowedAttribute($attribute, $type, $attributes)) {
                $object->setData($attribute, $value);
            }
        }

        return $this;
    }

    /**
     * Retrieve entity attributes values
     *
     * @param Mage_Core_Model_Abstract $object
     * @param array $attributes
     * @return Mage_Sales_Model_Api_Resource
     */
    protected function _getAttributes($object, $type, array $attributes = null)
    {
        $result = array();

        if (!is_object($object)) {
            return $result;
        }

        foreach ($object->getData() as $attribute=>$value) {
            if ($this->_isAllowedAttribute($attribute, $type, $attributes)) {
                $result[$attribute] = $value;
            }
        }

        if (isset($this->_attributesMap['global'])) {
            foreach ($this->_attributesMap['global'] as $alias=>$attributeCode) {
                $result[$alias] = $object->getData($attributeCode);
            }
        }

        if (isset($this->_attributesMap[$type])) {
            foreach ($this->_attributesMap[$type] as $alias=>$attributeCode) {
                $result[$alias] = $object->getData($attributeCode);
            }
        }

        return $result;
    }

    /**
     * Check is attribute allowed to usage
     *
     * @param Mage_Eav_Model_Entity_Attribute_Abstract $attribute
     * @param string $entityType
     * @param array $attributes
     * @return boolean
     */
    protected function _isAllowedAttribute($attributeCode, $type, array $attributes = null)
    {
        if (!empty($attributes)
            && !(in_array($attributeCode, $attributes))) {
            return false;
        }

        if (in_array($attributeCode, $this->_ignoredAttributeCodes['global'])) {
            return false;
        }

        if (isset($this->_ignoredAttributeCodes[$type])
            && in_array($attributeCode, $this->_ignoredAttributeCodes[$type])) {
            return false;
        }

        return true;
    }

}