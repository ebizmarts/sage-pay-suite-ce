<?php

class Ebizmarts_SagePaySuite_Model_Address extends Varien_Object
{

    /**
     * Public wrapper for __toArray
     *
     * @param array $arrAttributes
     * @return array
     */
    public function toArray(array $arrAttributes = array())
    {
    	$this->setStreet1($this->getStreet(1));
    	$this->setStreet2($this->getStreet(2));

		$this->setStreet($this->getStreet(1) . "\n" . $this->getStreet(2));
/*
		if( $this->getRegionCode() ){
			$this->setRegionCode($this->getRegionCode());
		}*/

        return parent::toArray($arrAttributes);
    }

	public function getRegionCode()
	{
		$regionId = $this->getData('region_id');
		if (!is_null($regionId) && is_numeric($regionId)) {
			$regionCodeNew = Mage::getModel('directory/region')
							 ->load((int)$regionId)
							 ->getCode();

			if (!is_null($regionCodeNew)) {
				return $regionCodeNew;
			}
		}

		return $this->getData('region_id');
	}

	public function getCountry()
	{
		return $this->getData('country_id');
	}

	/**
	 * get address street
	 *
	 * @param   int $line address line index
	 * @return  string
	 */
	public function getStreet($line = 0)
	{
		$street = $this->getData('street');
		if (-1 === $line) {
			return $street;
		} else {
			$arr = is_array($street) ? $street : explode("\n", $street);
			if (0 === $line || $line === null) {
				return $arr;
			}
			elseif (isset ($arr[$line -1])) {
				return $arr[$line -1];
			} else {
				return '';
			}
		}
	}

}