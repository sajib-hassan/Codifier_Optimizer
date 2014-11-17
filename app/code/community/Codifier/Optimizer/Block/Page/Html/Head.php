<?php

class Codifier_Optimizer_Block_Page_Html_Head extends Mage_Page_Block_Html_Head
{

    protected $_optimizerMergeBlacklist = array();

    public function __construct()
    {
        parent::__construct();
        $blacklist = array_merge(
            explode(',', Mage::getStoreConfig('dev/js/optimizer_merge_blacklist')),
            explode(',', Mage::getStoreConfig('dev/css/optimizer_merge_blacklist'))
        );

        foreach ($blacklist as $listItem) {
            $listItem = Mage::helper('optimizer')->normaliseUrl($listItem);
            if ($listItem) {
                $this->_optimizerMergeBlacklist[$listItem] = true;
            }
        }
    }

    /**
     * Merge static and skin files of the same format into 1 set of HEAD directives or even into 1 directive
     *
     * Will attempt to merge into 1 directive, if merging callback is provided. In this case it will generate
     * filenames, rather than render urls.
     * The merger callback is responsible for checking whether files exist, merging them and giving result URL
     *
     * @param string   $format      - HTML element format for sprintf('<element src="%s"%s />', $src, $params)
     * @param array    $staticItems - array of relative names of static items to be grabbed from js/ folder
     * @param array    $skinItems   - array of relative names of skin items to be found in skins according to design config
     * @param callback $mergeCallback
     *
     * @return string
     */
    protected function &_prepareStaticAndSkinElements(
        $format, array $staticItems, array $skinItems, $mergeCallback = null
    )
    {
        $designPackage = Mage::getDesign();
        $baseJsUrl = Mage::getBaseUrl('js');
        $items = array();
        $blacklisted = array();
        if ($mergeCallback && !is_callable($mergeCallback)) {
            $mergeCallback = null;
        }

        // get static files from the js folder, no need to lookup design package
        foreach ($staticItems as $params => $rows) {
            foreach ($rows as $name) {
                if(!$this->isMergeBlacklisted($name)){
                    $items['static'][$params][] = $mergeCallback
                        ? Mage::getBaseDir() . DS . 'js' . DS . $name
                        : $baseJsUrl . $name;
                } else {
                    $blacklisted['static'][$params][] = $baseJsUrl . $name;
                }
            }
        }

        // lookup each file basing on current theme configuration
        foreach ($skinItems as $params => $rows) {
            foreach ($rows as $name) {
                $relativeUrl = Mage_Core_Model_Store::URL_TYPE_SKIN.'/'.str_replace(Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_SKIN), '', $designPackage->getSkinUrl($name, array()));
                if(!$this->isMergeBlacklisted($relativeUrl)){
                    $items['skin'][$params][] = $mergeCallback
                        ? $designPackage->getFilename($name, array('_type' => 'skin'))
                        : $designPackage->getSkinUrl($name, array());
                } else {
                    $blacklisted['skin'][$params][] = $designPackage->getSkinUrl($name, array());
                }
            }
        }

        $html = '';
        foreach ($items as $type) {
            foreach ($type as $params => $rows) {
                // attempt to merge
                $mergedUrl = false;
                if ($mergeCallback) {
                    $mergedUrl = call_user_func($mergeCallback, $rows);
                }
                // render elements
                $params = trim($params);
                $params = $params ? ' ' . $params : '';
                if ($mergedUrl) {
                    $html .= sprintf($format, $mergedUrl, $params);
                } else {
                    foreach ($rows as $src) {
                        $html .= sprintf($format, $src, $params);
                    }
                }
            }
        }
        //add blacklisted items last
        foreach ($blacklisted as $type) {
            foreach ($type as $params => $rows) {
                // render elements
                $params = trim($params);
                $params = $params ? ' ' . $params : '';
                foreach ($rows as $src) {
                    $html .= sprintf($format, $src, $params);

                }
            }
        }
        return $html;
    }

    public function isMergeBlacklisted($input)
    {
        if (isset($this->_optimizerMergeBlacklist[ 'js' .DS. $input])
            || isset($this->_optimizerMergeBlacklist[$input])
        ) {
            return true;
        }
        return false;
    }

}
