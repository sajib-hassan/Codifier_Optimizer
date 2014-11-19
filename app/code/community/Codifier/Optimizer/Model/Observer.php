<?php

class Codifier_Optimizer_Model_Observer {

    /**
     * Move all js to footer.
     * 
     * Uses the event 'http_response_send_before'.
     * 
     * @param Varien_Event_Observer $observer
     * @return Codifier_Optimizer_Model_Observer
     */
    
    public function moveAllJsToFooter($observer) {
        $response = $observer->getResponse();
        $shouldMoveAllJsToFooter = Mage::getStoreConfigFlag('dev/js/move_all_js_to_footer');

        if (!$shouldMoveAllJsToFooter || Mage::app()->getRequest()->isAjax() || Mage::helper('tgc_checkout')->isPaymentBridgeContext()) {
            return $this;
        }

        $html = $response->getBody(false);
        $text = '';

        $jsPattern = '#<script.*</script>#isU';
        $conditionalJsPattern = '#<\!--\[if[^\>]*>\s*<script.*</script>\s*<\!\[endif\]-->#isU';
        $conditionalCSSJsPattern = '#(<\!--\[if[^\>]*>\s*)<link.*/>(\s*<script.*</script>)(\s*<\!\[endif\]-->)#iU';

        // First deal with conditionals
        $matches = array();
        $success = preg_match_all($conditionalJsPattern, $html, $matches);
        if ($success) {
            $text = implode("\n", $matches[0]);
            $html = preg_replace($conditionalJsPattern, '', $html);
        }

        // First deal with conditionals which has both CSS and JS
        $matches = array();
        $success = preg_match_all($conditionalCSSJsPattern, $html, $matches);
        if ($success) {
            list($_matches, $_start, $_jsFiles, $_end) = $matches;
            foreach ($_jsFiles as $key => $_jsFile) {
                $html = preg_replace('#' . $_jsFile . '#iU', '', $html);
                $text .= $_start[$key] . $_jsFile . $_end[$key] . "\n";
            }
        }

        // Then the rest of the javascript
        $matches = array();
        $success = preg_match_all($jsPattern, $html, $matches);
        if ($success) {
            $text .= "\n" . implode("\n", $matches[0]);
            $html = preg_replace($jsPattern, '', $html);
        }
        $response->setBody(str_replace('</body>', $text . '</body>', $html));
    }

}
