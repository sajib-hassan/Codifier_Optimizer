<?php
class Codifier_Optimizer_Adminhtml_OptimizerController extends Mage_Adminhtml_Controller_Action
{

    protected function _initAction()
    {
        $this->setUsedModuleName('Codifier_Optimizer');
        $this->loadLayout()
            ->_setActiveMenu('system/tools')
            ->_addBreadcrumb(Mage::helper('adminhtml')->__('System'), Mage::helper('adminhtml')->__('System'))
            ->_addBreadcrumb(Mage::helper('adminhtml')->__('Tools'), Mage::helper('adminhtml')->__('Tools'))
            ->_addBreadcrumb(
            Mage::helper('optimizer')->__('Optimizer'),
            Mage::helper('optimizer')->__('Optimizer')
        );
        return $this;
    }

    public function indexAction()
    {
        if(Mage::app()->useCache('layout')) {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('optimizer')->__('Theme Optmiser can only be run when the cache is disabled.'));
            $this->_initAction()->renderLayout();
        } elseif(version_compare(Mage::getVersion(), '1.5.0.0', '<=')) {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('optimizer')->__('Theme Optmiser only runs on Magento 1.5+.'));
            $this->_redirect(Mage::getSingleton('admin/session')->getUser()->getStartupPageUrl());
        }  else {
            $storeId = $this->getRequest()->getParam('store_id');
            $apply = $this->getRequest()->getParam('apply') == 'true';
            $this->_initAction()
                ->_addContent(
                $this->getLayout()->createBlock('optimizer/adminhtml_optimiser')->setStoreToCheck($storeId)
                    ->setApply($apply)
            )
                ->renderLayout();
        }
    }
}