<?php


/**
 *
 * Sagepay Curl SSL version Dropdown source
 *
 */
class Ebizmarts_SagePaySuite_Model_Sagepaysuite_Source_CurlSslVersion {

    public function toOptionArray() {
        return array(
            array(
                'value' => 'CURL_SSLVERSION_DEFAULT',
                'label' => Mage::helper('sagepaysuite')->__('CURL_SSLVERSION_DEFAULT')
            ),
            array(
                'value' => 'CURL_SSLVERSION_TLSv1',
                'label' => Mage::helper('sagepaysuite')->__('CURL_SSLVERSION_TLSv1')
            ),
            array(
                'value' => 'CURL_SSLVERSION_SSLv2',
                'label' => Mage::helper('sagepaysuite')->__('CURL_SSLVERSION_SSLv2')
            ),
            array(
                'value' => 'CURL_SSLVERSION_SSLv3',
                'label' => Mage::helper('sagepaysuite')->__('CURL_SSLVERSION_SSLv3')
            ),
            array(
                'value' => 'CURL_SSLVERSION_TLSv1_0',
                'label' => Mage::helper('sagepaysuite')->__('CURL_SSLVERSION_TLSv1_0')
            ),
            array(
                'value' => 'CURL_SSLVERSION_TLSv1_1',
                'label' => Mage::helper('sagepaysuite')->__('CURL_SSLVERSION_TLSv1_1')
            ),
            array(
                'value' => 'CURL_SSLVERSION_TLSv1_2',
                'label' => Mage::helper('sagepaysuite')->__('CURL_SSLVERSION_TLSv1_2')
            )
        );
    }

}