<?php

class Ebizmarts_SagePayReporting_Block_Adminhtml_Widget_Grid_Massaction extends Mage_Adminhtml_Block_Widget_Grid_Massaction
{

	public function getJavaScript()
	{
		return "
                var {$this->getJsObjectName()} = new orphansGridMassaction('{$this->getHtmlId()}', {$this->getGridJsObjectName()}, '{$this->getSelectedJson()}', '{$this->getFormFieldNameInternal()}', '{$this->getFormFieldName()}');
		{$this->getJsObjectName()}.setItems({$this->getItemsJson()});
		{$this->getJsObjectName()}.setGridIds('{$this->getGridIdsJson()}');
                ". ($this->getUseAjax() ? "{$this->getJsObjectName()}.setUseAjax(true);" : '') . "
                ". ($this->getUseSelectAll() ? "{$this->getJsObjectName()}.setUseSelectAll(true);" : '') .
                "{$this->getJsObjectName()}.errorText = '{$this->getErrorText()}';";
	}

}
