<?php
set_include_path(BP . DS . 'lib' . DS . 'minify' . PS . get_include_path());

class Codifier_Optimizer_Model_Core_Design_Package extends Mage_Core_Model_Design_Package {

    protected $_optimizerBlacklists = array();

    public function __construct() {
        if (method_exists(get_parent_class($this), '__construct')) {
            parent::__construct();
        }
        foreach (explode(',', Mage::getStoreConfig('dev/js/optimizer_minify_blacklist')) as $jsBlacklist) {
            $jsBlacklist = Mage::helper('optimizer')->normaliseUrl($jsBlacklist);
            if ($jsBlacklist) {
                $this->_optimizerBlacklists['js']['minify'][$jsBlacklist] = true;
            }
        }
        foreach (explode(',', Mage::getStoreConfig('dev/css/optimizer_minify_blacklist')) as $cssBlacklist) {
            $cssBlacklist = Mage::helper('optimizer')->normaliseUrl($cssBlacklist);
            if ($cssBlacklist) {
                $this->_optimizerBlacklists['css']['minify'][$cssBlacklist] = true;
            }
        }
        foreach (explode(',', Mage::getStoreConfig('dev/css/optimizer_minify_blacklist_secure')) as $cssBlacklist) {
            $cssBlacklist = Mage::helper('optimizer')->normaliseUrl($cssBlacklist);
            if ($cssBlacklist) {
                $this->_optimizerBlacklists['css_secure']['minify'][$cssBlacklist] = true;
            }
        }
    }

    /**
     * Merge specified JS files and return URL to the merged file on success
     * filename is md5 of files + timestamp of last modified file
     *
     * @param string $files
     *
     * @return string
     */
    public function getMergedJsUrl($files) {
        $jsBuild = Mage::getModel('optimizer/buildOptimizer')->__construct($files, BP);
        $targetFilename = md5(implode(',', $files)) . '-' . $jsBuild->getLastModified() . '.js';
        if (file_exists(Mage::getBaseDir('media') . '/js/' . $targetFilename)) {
            return Mage::getBaseUrl('media', Mage::app()->getRequest()->isSecure()) . 'js/' . $targetFilename;
        }
        $targetDir = $this->_initMergerDir('js');
        if (!$targetDir) {
            return '';
        }
        if ($this->_mergeFiles($files, $targetDir . DS . $targetFilename, false, array($this, 'beforeMergeJs'), 'js')) {
            return Mage::getBaseUrl('media', Mage::app()->getRequest()->isSecure()) . 'js/' . $targetFilename;
        }
        return '';
    }

    /**
     * Before merge JS callback function
     *
     * @param string $file
     * @param string $contents
     *
     * @return string
     */
    public function beforeMergeJs($file, $contents) {
        //append full content of blacklisted files
        $relativeFileName = str_replace(BP . DS, '', $file);
        if (isset($this->_optimizerBlacklists['js']['minify'][$relativeFileName])) {
            if (Mage::getIsDeveloperMode()) {
                return "\n/*" . $file . " (original) */\n" . $contents . "\n\n";
            }
            return "\n" . $contents;
        }

        if (preg_match('/@ sourceMappingURL=([^\s]*)/s', $contents, $matches)) {
            //create a file without source map
            $contents = str_replace(
                    $matches[0], '', $contents
            );
        }

        if (Mage::getIsDeveloperMode()) {
            return
                    "\n/*" . $file . " (minified) */\n" . Mage::getModel('optimizer/javascript')->minify($contents)
                    . "\n\n";
        }

        return "\n" . Mage::getModel('optimizer/javascript')->minify($contents);
    }

    /**
     * Merge specified css files and return URL to the merged file on success
     * filename is md5 of files + storeid + SSL flag + timestamp of last modified file
     *
     * @param $files
     *
     * @return string
     */
    public function getMergedCssUrl($files) {
        $cssBuild = Mage::getModel('optimizer/buildOptimizer')->__construct($files, BP);
        // secure or unsecure
        $isSecure = Mage::app()->getRequest()->isSecure();
        $mergerDir = $isSecure ? 'css_secure' : 'css';
        $targetDir = $this->_initMergerDir($mergerDir);
        if (!$targetDir) {
            return '';
        }

        // base hostname & port
        $storeId = Mage::app()->getStore()->getId();
        $baseMediaUrl = Mage::getBaseUrl('media', $isSecure);
        $hostname = parse_url($baseMediaUrl, PHP_URL_HOST);
        $port = parse_url($baseMediaUrl, PHP_URL_PORT);
        if (false === $port) {
            $port = $isSecure ? 443 : 80;
        }

        // merge into target file
        $targetFilename = md5(implode(',', $files) . "|{$hostname}|{$port}|{$storeId}|{$cssBuild->getLastModified()}") . '.css';

        if (file_exists(Mage::getBaseDir('media') . DS . $mergerDir .DS . $targetFilename)) {
            return $baseMediaUrl . $mergerDir . '/' . $targetFilename;
        }

        $mergeFilesResult = $this->_mergeFiles(
                $files, $targetDir . DS . $targetFilename, false, array($this, 'beforeMergeCss'), 'css'
        );
        if ($mergeFilesResult) {
            return $baseMediaUrl . $mergerDir . '/' . $targetFilename;
        }
        return '';
    }

    /**
     * Before merge css callback function
     *
     * @param string $origFile
     * @param string $contents
     *
     * @return string
     */
    public function beforeMergeCss($origFile, $contents) {
        
        $contents = parent::beforeMergeCss($origFile, $contents);
        // secure or unsecure
        $mergerDir = Mage::app()->getRequest()->isSecure() ? 'css_secure' : 'css';
        //append full content of blacklisted files
        $relativeFileName = str_replace(BP . DS, '', $origFile);
        if (isset($this->_optimizerBlacklists[$mergerDir]['minify'][$relativeFileName])) {
            if (Mage::getIsDeveloperMode()) {
                return "\n/* NON-SSL:" . $origFile . " (original) */\n" . $contents . "\n\n";
            }
            return "\n" . $contents;
        }

        $options = array(
            'preserveComments' => false,
//            'prependRelativePath' => $prependRelativePath,
            'symlinks' => array('//' => BP)
        );

        if (Mage::getIsDeveloperMode()) {
            return "\n/* NON-SSL: " . $origFile . " (minified)  */\n" . $this->_returnMergedCss($contents, $options)
                    . "\n\n";
        }
        return $this->_returnMergedCss($contents, $options);
    }

    /**
     * return minified output
     *
     * @param $contents
     * @param $options
     *
     * @return string
     */
    private function _returnMergedCss($contents, $options) {
        return "\n" . Mage::getModel('optimizer/css')->minify($contents, $options);
    }

}
