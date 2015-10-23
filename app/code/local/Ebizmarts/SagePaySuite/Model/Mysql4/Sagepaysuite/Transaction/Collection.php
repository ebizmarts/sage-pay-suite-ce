<?php


class Ebizmarts_SagePaySuite_Model_Mysql4_SagePaySuite_Transaction_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
	protected function _construct()
	{
		$this->_init('sagepaysuite2/sagepaysuite_transaction');
	}

    public function getOrphans()
    {
        $this->getSelect()->where('isnull(main_table.order_id)')
        /*->where('isnull(main_table.voided)')*/;
        return $this;
    }

    public function existOrphans()
    {
        $this->getSelect()->where('isnull(main_table.order_id)')
        /*->where('isnull(main_table.voided)')*/
        ->limit(1);
        return $this;
    }

    public function getApproved()
    {
        $this->getSelect()->where('!isnull(main_table.order_id)');
        return $this;
    }

    public function getChilds($id)
    {
        $this->getSelect()->where('parent_trn_id = ?', $id);
        return $this;
    }

}
