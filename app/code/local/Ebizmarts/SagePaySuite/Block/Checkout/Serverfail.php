<?php

/**
 * Server fail block
 *
 * @category   Ebizmarts
 * @package    Ebizmarts_SagePaySuite
 * @author     Ebizmarts <info@ebizmarts.com>
 */
class Ebizmarts_SagePaySuite_Block_Checkout_Serverfail extends Mage_Core_Block_Template {

    protected function _getSess() {
        return Mage::getSingleton('sagepaysuite/session');
    }

    protected function _toHtml() {

        $message = $this->_getSess()->getFailStatus();

        if(empty($message)){
            $message = "Transaction canceled by customer.";
        }

        Mage::getSingleton('checkout/session')->addError(Mage::helper('sagepaysuite/error')->parseTransactionFailedError($message));

        $server_mode = (string)Mage::getModel('sagepaysuite/sagePayServer')->getConfigData('payment_iframe_position');
        //Redirect to cart
        if($server_mode == 'full_redirect') {

            $checkoutUrl     = $this->getUrl('checkout/cart', array('_secure'=>true));
            $fullRedirectionJsScript = "window.top.location.href = \"{$checkoutUrl}\";";
            $html = '<html><body>';
            $html.= '<script type="text/javascript">' . $fullRedirectionJsScript . '</script>';
            $html.= '</body></html>';
        }else{

            //alert message
            $alert = '';
            if ($message) {
                $alert = 'alert("' . $message . '");';
            }

            $html = '<html><body>';
            $html.= '<script type="text/javascript">' . $alert .
                'var inChElem = window.parent.$(\'sagepaysuite-server-incheckout-iframe\');

                        if(inChElem){ //Iframe below Place Order button

                            var inChElemOsc = window.parent.$(\'onestepcheckout-place-order\');
                            if(inChElemOsc){ //Iframe below Place Order button OSC
                                inChElemOsc.show();
                            }else{
                                window.parent.$(\'checkout-review-submit\').show();
                            }

                            inChElem.remove();
                        }else{
                            try{
                                window.parent.Control.Window.windows.each(
                                    function(w){
                                        if(w.container.visible()){
                                            w.close();
                                        }
                                    }
                                );
                            }catch(er){}

                                                    //@ToDo: Full redirection
                        }

                        if((typeof window.parent.restoreOscLoad) != "undefined") {
                            window.parent.restoreOscLoad();
                        }

                    </script>';
            $html.= '</body></html>';
        }


/*
        $checkoutUrl     = $this->getUrl('checkout/cart', array('_secure'=>true));
        $fullRedirectionJsScript = "window.top.location.href = \"{$checkoutUrl}\";";
        $html = '<html><body>';
        $html.= '<script type="text/javascript">' . $fullRedirectionJsScript . '</script>';
        $html.= '</body></html>';
        */

        /*
        $alert = '';
        if ($message) {
            $alert = 'alert("' . $message . '");';
        }

        $fullRedirection       = '';
        $checkoutUrl           = $this->getUrl('checkout/cart', array('_secure'=>true));
        $fullRedirectionAction = "window.location.href = \"{$checkoutUrl}\";";

        $pos = (string)Mage::getModel('sagepaysuite/sagePayServer')->getConfigData('payment_iframe_position');

        //Redirect to cart
        if($pos == 'full_redirect') {
            $fullRedirection = $fullRedirectionAction;
        }

        //For modal when in mobile
        if( empty($fullRedirection) ) {
            $fullRedirection = "var _is_mobile = (navigator.userAgent.match(/BlackBerry/i) ||
                navigator.userAgent.match(/webOS/i) ||
                                    navigator.userAgent.match(/Android/i) ||
                                    navigator.userAgent.match(/iPhone/i) ||
                                    navigator.userAgent.match(/iPod/i) ||
                                    navigator.userAgent.match(/iPad/i));
                                    if(_is_mobile){
                                    ".$fullRedirectionAction."
                                    }";
        }

        $html = '<html><body>';
        $html.= '<script type="text/javascript">' . $alert . $fullRedirection .
            'var inChElem = window.parent.$(\'sagepaysuite-server-incheckout-iframe\');

					if(inChElem){ //Iframe below Place Order button

						var inChElemOsc = window.parent.$(\'onestepcheckout-place-order\');
						if(inChElemOsc){ //Iframe below Place Order button OSC
							inChElemOsc.show();
						}else{
							window.parent.$(\'checkout-review-submit\').show();
						}

						inChElem.remove();
					}else{
						try{
							window.parent.Control.Window.windows.each(
								function(w){
									if(w.container.visible()){
										w.close();
									}
								}
							);
						}catch(er){}

                                                //@ToDo: Full redirection
					}

					if((typeof window.parent.restoreOscLoad) != "undefined") {
						window.parent.restoreOscLoad();
					}

				</script>';
        $html.= '</body></html>';
        */

        return $html;
    }

}