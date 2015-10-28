;(function () {
// application code
})();

notifyThreedError = function (msg) {
    Control.Window.windows.each(function (w) {
        if (w.container.visible()) {
            w.close();
        }
    });
    if ((typeof checkout.accordion == 'object')) {
        checkout.accordion.openSection('opc-payment');
    }
    alert(msg);
}

getOneStepCheckoutId = function () {
    var possibleIds = [
        {form: "onestepcheckout-form", trigger: "onestepcheckout-place-order"},
        {form: "onepagecheckout_orderform", trigger: ".button.btn-checkout"}
    ];


    for (var i = 0; i < possibleIds.length; i++) {

        var ele = document.getElementById(possibleIds[i].form);

        if ((typeof ele) != "undefined" && ele != null) {
            window._sagepayonepageFormId = possibleIds[i].form;

            var trigger = $(possibleIds[i].trigger);
            if ((typeof trigger) == "undefined" || trigger == null) {
                trigger = $$(possibleIds[i].trigger).first();
            }

            window._sagepayonepageTriggerId = trigger;
        }
    }

}

setOscLoad = function () {

    //IWD_OnePage
    //if(IWD && typeof IWD.OPC != "undefined") {
    //checkout.setLoadWaiting(true);
    //}

    //http://stackoverflow.com/questions/19209647/magento-pass-billing-and-shipping-info-to-sagepay-module-from-iwd-cart

    //var oscButton = $('onestepcheckout-place-order');
    //if(oscButton) {
    //    //transport.element().removeClassName('grey').addClassName('orange');
    //    $$('div.onestepcheckout-place-order-loading').invoke('hide');
    //}

}

restoreOscLoad = function () {

    window._sagepayprocessingorder = false;

    var loading_osc = $$('.onestepcheckout-place-order-loading');
    if (loading_osc.length) {
        $('onestepcheckout-place-order').removeClassName('grey').addClassName('orange');
        $('onestepcheckout-place-order').removeAttribute('disabled');
        loading_osc.invoke('hide');
        already_placing_order = false;
    }
    else {
        //IWD_OnePage
        //if(IWD && typeof IWD.OPC != "undefined") {
        //    checkout.setLoadWaiting(false);
        //}
    }

}

if (typeof EbizmartsSagePaySuite == 'undefined') {
    var EbizmartsSagePaySuite = {};
}
EbizmartsSagePaySuite.Checkout = Class.create();
EbizmartsSagePaySuite.Checkout.prototype = {

    initialize: function (config) {
        this.config = config;
        this.servercode = 'sagepayserver';
        this.directcode = 'sagepaydirectpro';
        this.paypalcode = 'sagepaypaypal';
        this.formcode = 'sagepayform';
        this.nitcode = 'sagepaynit';
        this.code = '';
        this.oldUrl = '';
        this._mobile = sagePayIsMobile();
        this.customckout = null;
        this.old_review_message = null;

        if (this._mobile) {
            Position.prepare();
        }

        if (this.getConfig('review')) {
            this.oldUrl = this.getConfig('review').saveUrl;
            this.getConfig('review').saveUrl = SuiteConfig.getConfig('global', 'sgps_saveorder_url');
            this.getConfig('review').onSave = this.reviewSave.bindAsEventListener(this);
        } else if (this.getConfig('osc')) {

//            if("BUTTON" == window._sagepayonepageTriggerId.tagName) {
//                var oncl = window._sagepayonepageTriggerId.readAttribute("onclick");
//
//                if(null == oncl.match(/void/gi)) {
//                    window._sagepayonepageTriggerId.writeAttribute("onclick", "$('"+ window._sagepayonepageFormId +"').submit();");
//                    //if("submit" == window._sagepayonepageTriggerId.readAttribute("type")) {
//                        //window._sagepayonepageTriggerId.writeAttribute("onclick", "return false;");
//                    //    window._sagepayonepageTriggerId.writeAttribute("type", "button");
//                    //}
//
//                }
//            }

            //IWD_OnePage
            /*
             if(IWD && "undefined" != (typeof IWD.OPC)) {
             //checkout.saveUrl = SuiteConfig.getConfig('global', 'sgps_saveorder_url');
             //checkout._save = checkout.save.clone();
             //checkout.save    = this.reviewSave.bindAsEventListener(this);

             window._sagepayonepageTriggerId.writeAttribute("onclick", "$('" + window._sagepayonepageFormId + "').submit(); return false;");

             if($(window._sagepayonepageFormId).readAttribute("method") != "post") {
             $(window._sagepayonepageFormId).writeAttribute("method", "post");
             }
             }
             */

            Event.stopObserving($(window._sagepayonepageFormId));
            $(window._sagepayonepageFormId)._submit = document.createElement("form").submit;
            $(window._sagepayonepageFormId).submit = function () {
                this.reviewSave();
            }.bind(this);

        } else if (this.getConfig('msform')) {
            this.getConfig('msform').observe('submit', function (evmsc) {
                Event.stop(evmsc);
                this.reviewSave(evmsc);
            }.bind(this));
        }

        var blFormMAC = $('multishipping-billing-form');
        if (blFormMAC) {
            blFormMAC.observe('submit', function (_event_) {
                Event.stop(_event_);
                this.setPaymentMethod();
            }.bind(this));
        }

        var paymentSubmit = this.getPaymentSubmit();
        if (paymentSubmit) {
            if (this.getCurrentCheckoutStep() == 'opc-review') {//Magento 1.5.x+
                this.setPaymentMethod(true);
            } else {
                paymentSubmit.observe('click', this.setPaymentMethod.bindAsEventListener(this));
            }
        }
    },
    evalTransport: function (transport) {
        try {
            response = eval('(' + transport.responseText + ')')
        } catch (e) {
            response = {}
        }
        return response;
    },
    getConfig: function (instance) {
        return (this.config[instance] != 'undefined' ? this.config[instance] : false);
    },
    getCurrentCheckoutStep: function () {
        return this.getConfig('checkout').accordion.currentSection;
    },
    getPaymentSubmit: function () {
        var elements = $$("#opc-payment [onclick]");
        for (var i = 0; i < elements.length; i++) {
            // IE problems with readAttribute/getAttribute returning invalid results
            var attrubutes = [elements[i].readAttribute('onclick'), elements[i].getAttribute('onclick')];
            for (var j = 0; j < attrubutes.length; j++) {
                if (Object.isString(attrubutes[j]) && -1 !== attrubutes[j].search(/payment\.save/)) {
                    return elements[i];
                }
            }
        }
        return false;
    },
    getShippingMethodSubmit: function () {
        var elements = $$("#opc-shipping_method [onclick]");
        for (var i = 0; i < elements.length; i++) {
            var attrubutes = [elements[i].readAttribute('onclick'), elements[i].getAttribute('onclick')];
            for (var j = 0; j < attrubutes.length; j++) {
                if (Object.isString(attrubutes[j]) && -1 !== attrubutes[j].search(/shippingMethod\.save/)) {
                    return elements[i];
                }
            }
        }
        return false;
    },
    getPaymentMethod: function () {

        var form = null;

        //Fix this when using IE patch

        if ($('multishipping-billing-form')) {
            form = $('multishipping-billing-form');
        } else if (this.getConfig('osc')) {
            form = this.getConfig('oscFrm');
        } else if ((typeof this.getConfig('payment')) != 'undefined') {
            form = $(this.getConfig('payment').form);
        }

        if (form === null) {
            return this.code;
        }

        var checkedPayment = null

        form.getInputs('radio', 'payment[method]').each(function (el) {
            if (el.checked) {
                checkedPayment = el.value;
                throw $break;
            }
        });

        if (checkedPayment != null) {
            return checkedPayment;
        }

        return this.code;
    },
    isFormPaymentMethod: function () {
        return (this.getPaymentMethod() === this.formcode);
    },
    isServerPaymentMethod: function () {
        return (this.getPaymentMethod() === this.servercode || ($('suite_ms_payment_method') && $('suite_ms_payment_method').getValue() == this.servercode));
    },
    isDirectPaymentMethod: function () {
        return (this.getPaymentMethod() === this.directcode);
    },
    isNitPaymentMethod: function () {
        return (this.getPaymentMethod() === this.nitcode);
    },
    isSagePay: function () {
        var isSagePayMethod = false;
        if ((this.getPaymentMethod() === this.formcode) || (this.getPaymentMethod() === this.directcode) ||
            (this.getPaymentMethod() === this.servercode) || (this.getPaymentMethod() === this.paypalcode) ||
            (this.getPaymentMethod() === this.nitcode)) {
            isSagePayMethod = true;
        }

        return isSagePayMethod;
    },
    growlError: function (msg) {
        alert(msg);
        return;
        try {
            var ng = new k.Growler({
                location: "tc"
            });
            ng.error(msg, {
                life: 10
            });
        } catch (grwlerror) {
            alert(msg);
        }
    },
    growlWarn: function (msg) {
        alert(msg);
        return;
        try {
            var ng = new k.Growler({
                location: "tc"
            });
            ng.warn(msg, {
                life: 10
            });
        } catch (grwlerror) {
            alert(msg);
        }
    },
    isDirectTokenTransaction: function () {
        var tokenRadios = $$('div#payment_form_sagepaydirectpro ul.tokensage li.tokencard-radio input');
        if (tokenRadios.length) {
            if (tokenRadios[0].disabled === false) {
                return true;
            }
        }
        return false;
    },
    isServerTokenTransaction: function () {
        var tokenRadios = $$('div#payment_form_sagepayserver ul.tokensage li.tokencard-radio input');
        if (tokenRadios.length) {
            if (tokenRadios[0].disabled === false) {
                return true;
            }
        }
        return false;
    },
    getServerSecuredImage: function () {
        return new Element('img', {
            'src': SuiteConfig.getConfig('server', 'secured_by_image'),
            'style': 'margin-bottom:5px'
        });
    },
    setShippingMethod: function () {
        try {
            if ($('sagepaydirectpro_cc_type')) {
                $('sagepaydirectpro_cc_type').selectedIndex = 0;
            }
        } catch (ser) {
            alert(ser);
        }
    },
    setPaymentMethod: function (modcompat) {

        if (this.getConfig('review')) {
            if (!this.isSagePay()) {
                this.getConfig('review').saveUrl = this.oldUrl;
            }
            else {
                this.getConfig('review').saveUrl = SuiteConfig.getConfig('global', 'sgps_saveorder_url');
            }
        }

        // Remove Server InCheckout iFrame if exists
        if ($('sagepaysuite-server-incheckout-iframe')) {
            $('checkout-review-submit').show();
            $('sagepaysuite-server-incheckout-iframe').remove();
        }

        //nit response
        if(this.isSagePay() && this.isNitPaymentMethod()){
            try {
                this.nitGenerateMerchantKey();
            } catch (err){
                console.log(err);
            }
        }

    },
    getTokensHtml: function () {

        new Ajax.Updater(('tokencards-payment-' + this.getPaymentMethod()), SuiteConfig.getConfig('global', 'html_paymentmethods_url'), {
            parameters: {
                payment_method: this.getPaymentMethod()
            },
            onComplete: function () {
                if ($$('a.addnew').length > 1) {
                    $$('a.addnew').each(function (el) {
                        if (!el.visible()) {
                            el.remove();
                        }
                    })
                }
                toggleNewCard(2);

                if ($(window._sagepayonepageFormId) && this.isServerPaymentMethod()) {
                    toggleNewCard(1);

                    var tokens = $$('div#payment_form_sagepayserver ul li.tokencard-radio input');
                    if (tokens.length) {
                        tokens.each(function (radiob) {
                            radiob.disabled = true;
                            radiob.removeAttribute('checked');
                        });
                        tokens.first().writeAttribute('checked', 'checked');
                        tokens.first().disabled = false;
                        $(window._sagepayonepageFormId).submit();
                    } else {
                        this.resetOscLoading();
                    }

                }
            }.bind(this)
        });

    },
    resetOscLoading: function () {
        restoreOscLoad();
    },
    resetMACLoading: function () {
        if (this.getConfig('msform')) {
            submitted = false;
            var step = 'review';
            Element.hide(step + '-please-wait');
            $(step + '-buttons-container').setStyle({opacity: 1});
            $(step + '-buttons-container').descendants().each(function (s) {
                s.disabled = false;
            });
        }
    },
    reviewSave: function (transport) {

        if ((typeof transport) == 'undefined') {
            var transport = {};
        }

        if (typeof window._sagepayprocessingorder == "undefined") {
            window._sagepayprocessingorder = false;
        }

        //OSC\\
        if ((typeof transport.responseText) == 'undefined' && $(window._sagepayonepageFormId)) {

            var gotNITToken = document.getElementById("nit_card_identifier") != null && document.getElementById("nit_card_identifier").value != "";

            if(!gotNITToken){
                if (true === window._sagepayprocessingorder) {
                    return;
                }

                if (!(new Validation(window._sagepayonepageFormId).validate())) {
                    return;
                }
            }

            if (!this.isSagePay()) {
                $(window._sagepayonepageFormId)._submit();
                return;
            }

            window._sagepayprocessingorder = true;

            if (this.isFormPaymentMethod()) {
                new Ajax.Request(SuiteConfig.getConfig('global', 'sgps_saveorder_url'), {
                    method: "post",
                    parameters: Form.serialize($(window._sagepayonepageFormId)),
                    onSuccess: function (f) {
                        var d = f.responseText.evalJSON();
                        if (d.response_status == 'ERROR') {
                            alert(d.response_status_detail);
                            window._sagepayprocessingorder = false;
                            this.resetOscLoading();
                            return;
                        }

                        window._sagepayprocessingorder = false;
                        setLocation(SuiteConfig.getConfig('form', 'url'));
                    },
                    onLoading: function (r) {
                        setOscLoad();
                    }
                });
                return;
            }

            var slPayM = this.getPaymentMethod();

            if(slPayM == this.servercode || slPayM == this.directcode){
                new Ajax.Request(SuiteConfig.getConfig('global', 'sgps_saveorder_url'),{
                    method:"post",
                    parameters: Form.serialize($(window._sagepayonepageFormId)),
                    onSuccess: function (f) {
                        this.reviewSave(f);
                        restoreOscLoad();
                    }.bind(this),
                    onLoading: function (r) {
                        setOscLoad();
                    }
                });
                return;
            }else if (slPayM == this.nitcode) {

                try {

                    if(gotNITToken){

                        //if token was generated continue as direct
                        new Ajax.Request(SuiteConfig.getConfig('global', 'sgps_saveorder_url'), {
                            method: "post",
                            parameters: Form.serialize($(window._sagepayonepageFormId)),
                            onSuccess: function (f) {
                                this.reviewSave(f);
                                restoreOscLoad();
                            }.bind(this),
                            onLoading: function (r) {
                                setOscLoad();
                            }
                        });
                        return;
                    }else{
                        this.nitGenerateMerchantKeyOSC();
                        return;
                    }

                } catch (err){
                    console.log(err);
                }
                return;
            }
            else {

                $(window._sagepayonepageFormId)._submit();
                window._sagepayprocessingorder = false;
                return;
            }

            window._sagepayprocessingorder = false;
            return;
            //OSC\\
        }
        else if ((typeof transport.responseText) == 'undefined' && this.getConfig('msform')) {

            var ps = $H(this.getConfig('msform').serialize(true));

            ps.set('payment[method]', 'sagepayserver');

            if ($('sagepay_server_token_cc_id')) {
                ps.set('payment[sagepay_token_cc_id]', $('sagepay_server_token_cc_id').getValue());
            }

            //CreateToken
            if ($('token-remembertoken')) {
                ps.set('payment[remembertoken]', $('token-remembertoken').getValue());
            }

            new Ajax.Request(SuiteConfig.getConfig('global', 'sgps_saveorder_url'), {
                method: "post",
                parameters: ps,
                onSuccess: function (f) {
                    this.reviewSave(f);
                }.bind(this)
            });
            return;

        }
        else {
            try {
                var response = this.evalTransport(transport);
            } catch (notv) {
                suiteLogError(notv);
            }
        }

        //an error occurred
        if ((typeof response.response_status != 'undefined') && response.response_status != 'OK' && response.response_status != 'threed' && response.response_status != 'paypal_redirect') {

            this.resetOscLoading();

            //reset nit
            if(document.getElementById("nit_card_identifier")){
                document.getElementById("nit_card_identifier").value = "";
            }

            if ("REDIRECT_CART" == response.response_status_detail.toString()) {
                setLocation(SuiteConfig.getConfig('global', 'cart_url'));
                return;
            }

            this.resetMACLoading();

            this.growlWarn(Translator.translate("An error occurred") + ":\n" + response.response_status_detail.toString());
            return;
        }

        //paypal response
        if (response.response_status == 'paypal_redirect') {
            setLocation(response.redirect);
            return;
        }

        //OSC success
        if (this.getConfig('osc') && response.success && response.response_status == 'OK' && (typeof response.next_url == 'undefined')) {
            setLocation(SuiteConfig.getConfig('global', 'onepage_success_url'));
            return;
        }

        if (!response.redirect || !response.success) {
            this.getConfig('review').nextStep(transport);
            return;
        }

        if (this.isServerPaymentMethod()) {

            if (this._mobile) {
                setLocation(response.redirect);
                return;
            }

            $('sagepayserver-dummy-link').writeAttribute('href', response.redirect);

            var rbButtons = $('review-buttons-container');

            var lcont = new Element('div', {
                className: 'lcontainer'
            });
            var height_container = parseInt(SuiteConfig.getConfig('server', 'iframe_height'));
            if (Prototype.Browser.IE) {
                height_container = height_container - 65;
            }

            var wtype = SuiteConfig.getConfig('server', 'payment_iframe_position').toString();
            if (wtype == 'modal') {

                var wm = new Control.Modal('sagepayserver-dummy-link', {
                    className: 'modal-sagepaysuite',
                    iframe: true,
                    closeOnClick: false,
                    insertRemoteContentAt: lcont,
                    height: parseInt(SuiteConfig.getConfig('server', 'iframe_height')) + 20,
                    width: SuiteConfig.getConfig('server', 'iframe_width'),
                    fade: true,
                    afterOpen: function () {
                        if (rbButtons) {
                            rbButtons.addClassName('disabled');
                        }
                    },
                    afterClose: function () {
                        if (rbButtons) {
                            rbButtons.removeClassName('disabled');
                        }
                    }
                });
                wm.container.insert(lcont);
                wm.container.down().setStyle({
                    'height': height_container.toString() + 'px'
                });
                wm.container.down().insert(this.getServerSecuredImage());
                wm.open();
                //scroll to top
                scroll(0, 0);

            } else if (wtype == 'incheckout') {

                var iframeId = 'sagepaysuite-server-incheckout-iframe';
                var paymentIframe = new Element('iframe', {
                    'src': response.redirect,
                    'id': iframeId
                });

                if (this.getConfig('osc')) {
                    var placeBtn = $('onestepcheckout-place-order');

                    placeBtn.hide();

                    $(window._sagepayonepageFormId).insert({
                        after: paymentIframe
                    });
                    $(iframeId).scrollTo();

                } else {

                    if ((typeof $('checkout-review-submit')) == 'undefined') {
                        var btnsHtml = $$('div.content.button-set').first();
                    } else {
                        var btnsHtml = $('checkout-review-submit');
                    }

                    btnsHtml.hide();
                    btnsHtml.insert({
                        after: paymentIframe
                    });

                }

            } else if (wtype == 'full_redirect') {
                setLocation(response.redirect);
                return;
            }

        }
        else if (this.isDirectPaymentMethod() && (typeof response.response_status != 'undefined') && response.response_status == 'threed') {

            var threedLayout = SuiteConfig.getConfig('direct', 'threed_layout');

            if (threedLayout == 'redirect' || this._mobile) {
                setLocation(response.redirect);
                return;
            }
            else {

                $('sagepaydirectpro-dummy-link').writeAttribute('href', response.redirect);

                var lcontdtd = new Element('div', {
                    className: 'lcontainer'
                });

                var dtd = new Control.Modal('sagepaydirectpro-dummy-link', {
                    className: 'modal-sagepaysuite sagepaymodal',
                    closeOnClick: false,
                    insertRemoteContentAt: lcontdtd,
                    iframe: true,
                    height: SuiteConfig.getConfig('direct', 'threed_iframe_height'),
                    width: SuiteConfig.getConfig('direct', 'threed_iframe_width'),
                    fade: true,
                    afterOpen: function () {

                        //do nothing if IE version < 8
                        if (true === Prototype.Browser.IE) {
                            var ie_version = parseFloat(navigator.appVersion.split("MSIE")[1]);
                            if (ie_version < 8) {
                                return;
                            }
                        }

                        try {
                            var daiv = this.container;

                            if ($$('.sagepaymodal').length > 1) {
                                $$('.sagepaymodal').each(function (elem) {
                                    if (elem.visible()) {
                                        daiv = elem;
                                        throw $break;
                                    }
                                });
                            }

                            if (!this._mobile) {
                                daiv.down().down('iframe').insert({
                                    before: new Element('div', {
                                        'id': 'sage-pay-direct-ddada',
                                        'style': 'background:#FFF'
                                    }).update(
                                            SuiteConfig.getConfig('direct', 'threed_after').toString() + SuiteConfig.getConfig('direct', 'threed_before').toString())
                                });
                            }

                        } catch (er) {
                        }

                        if (false === Prototype.Browser.IE) {
                            if (!this._mobile) {
                                daiv.down().down('iframe').setStyle({
                                    'height': (parseInt(daiv.down().getHeight()) - 60) + 'px'
                                });
                                daiv.setStyle({
                                    'height': (parseInt(daiv.down().getHeight()) + 57) + 'px'
                                });
                            }
                        }
                        else {
                            daiv.down().down('iframe').setStyle({
                                'height': (parseInt(daiv.down().getHeight()) + 116) + 'px'
                            });
                        }

                    },
                    afterClose: function () {
                        if ($('sage-pay-direct-ddada')) {
                            $('sage-pay-direct-ddada').remove();
                        }
                        $('sagepaydirectpro-dummy-link').writeAttribute('href', '');
                    }
                });

                if (this._mobile) {
                    var offset_left = (Position.deltaX + Math.floor((document.viewport.getDimensions().width - parseInt(dtd.container.getDimensions().width)) / 2));
                    var ye = (dtd.container.getDimensions().width <= document.viewport.getDimensions().width) ? ((offset_left != null && offset_left > 0) ? offset_left : 0) : 0;
                    dtd.options.position = [ye, 0];
                }

                dtd.container.insert(lcontdtd);
                dtd.open();
            }

        }
        else if (this.isDirectPaymentMethod()) {
            new Ajax.Request(SuiteConfig.getConfig('direct', 'sgps_registertrn_url'), {
                onSuccess: function (f) {

                    try {

                        var d = f.responseText.evalJSON();

                        if (d.response_status == "INVALID" || d.response_status == "MALFORMED" || d.response_status == "ERROR" || d.response_status == "FAIL") {
                            this.getConfig('checkout').accordion.openSection('opc-payment');
                            this.growlWarn(Translator.translate("An error occurred with Sage Pay Direct") + ":\n" + d.response_status_detail.toString());
                        } else if (d.response_status == 'threed') {
                            $('sagepaydirectpro-dummy-link').writeAttribute('href', d.url);
                        }

                    } catch (alfaEr) {
                        this.growlError(f.responseText.toString());
                    }

                }.bind(this)
            });
        }
        else if (this.isNitPaymentMethod() && (typeof response.response_status != 'undefined') && response.response_status == 'threed') {

            var threedLayout = SuiteConfig.getConfig('direct', 'threed_layout');

            if (threedLayout == 'redirect' || this._mobile) {
                setLocation(response.redirect);
                return;
            }
            else {

                $('sagepaydirectpro-dummy-link').writeAttribute('href', response.redirect);

                var lcontdtd = new Element('div', {
                    className: 'lcontainer'
                });

                var dtd = new Control.Modal('sagepaydirectpro-dummy-link', {
                    className: 'modal-sagepaysuite sagepaymodal',
                    closeOnClick: false,
                    insertRemoteContentAt: lcontdtd,
                    iframe: true,
                    height: SuiteConfig.getConfig('direct', 'threed_iframe_height'),
                    width: SuiteConfig.getConfig('direct', 'threed_iframe_width'),
                    fade: true,
                    afterOpen: function () {

                        //do nothing if IE version < 8
                        if (true === Prototype.Browser.IE) {
                            var ie_version = parseFloat(navigator.appVersion.split("MSIE")[1]);
                            if (ie_version < 8) {
                                return;
                            }
                        }

                        try {
                            var daiv = this.container;

                            if ($$('.sagepaymodal').length > 1) {
                                $$('.sagepaymodal').each(function (elem) {
                                    if (elem.visible()) {
                                        daiv = elem;
                                        throw $break;
                                    }
                                });
                            }

                            if (!this._mobile) {
                                daiv.down().down('iframe').insert({
                                    before: new Element('div', {
                                        'id': 'sage-pay-direct-ddada',
                                        'style': 'background:#FFF'
                                    }).update(
                                            SuiteConfig.getConfig('direct', 'threed_after').toString() + SuiteConfig.getConfig('direct', 'threed_before').toString())
                                });
                            }

                        } catch (er) {
                        }

                        if (false === Prototype.Browser.IE) {
                            if (!this._mobile) {
                                daiv.down().down('iframe').setStyle({
                                    'height': (parseInt(daiv.down().getHeight()) - 60) + 'px'
                                });
                                daiv.setStyle({
                                    'height': (parseInt(daiv.down().getHeight()) + 57) + 'px'
                                });
                            }
                        }
                        else {
                            daiv.down().down('iframe').setStyle({
                                'height': (parseInt(daiv.down().getHeight()) + 116) + 'px'
                            });
                        }

                    },
                    afterClose: function () {
                        if ($('sage-pay-direct-ddada')) {
                            $('sage-pay-direct-ddada').remove();
                        }
                        $('sagepaydirectpro-dummy-link').writeAttribute('href', '');
                    }
                });

                if (this._mobile) {
                    var offset_left = (Position.deltaX + Math.floor((document.viewport.getDimensions().width - parseInt(dtd.container.getDimensions().width)) / 2));
                    var ye = (dtd.container.getDimensions().width <= document.viewport.getDimensions().width) ? ((offset_left != null && offset_left > 0) ? offset_left : 0) : 0;
                    dtd.options.position = [ye, 0];
                }

                dtd.container.insert(lcontdtd);
                dtd.open();
            }

        }
        else if (this.isNitPaymentMethod()) {

            new Ajax.Request(SuiteConfig.getConfig('nit', 'sgps_registertrn_url'), {
                onSuccess: function (f) {

                    try {

                        var d = f.responseText.evalJSON();

                        if (d.response_status == "INVALID" || d.response_status == "MALFORMED" || d.response_status == "ERROR" || d.response_status == "FAIL") {
                            this.getConfig('checkout').accordion.openSection('opc-payment');
                            this.growlWarn(Translator.translate("An error occurred with Sage Pay PI") + ":\n" + d.response_status_detail.toString());
                        } else if (d.response_status == 'threed') {
                            $('sagepaydirectpro-dummy-link').writeAttribute('href', d.url);
                        }

                    } catch (alfaEr) {
                        this.growlError(f.responseText.toString());
                    }

                }.bind(this)
            });
        }
        else {
            this.getConfig('review').nextStep(transport);
            return;
        }

    },
    nitGenerateCardToken: function(merchant_session_key, isOSC, suite){

        if(merchant_session_key){

            //create token form
            var token_form = document.getElementById('sagepay_nit_token_form');

            if(!token_form){

                token_form = document.createElement("form");
                token_form.setAttribute('id',"sagepay_nit_token_form");
                token_form.setAttribute('method',"post");
                token_form.setAttribute('action',"/payment");
                token_form.setAttribute('style',"display:none;");
                document.getElementsByTagName('body')[0].appendChild(token_form);

                var input_merchant_key = document.createElement("input");
                input_merchant_key.setAttribute('type',"hidden");
                input_merchant_key.setAttribute('data-sagepay',"merchantSessionKey");
                //input_merchant_key.setAttribute('id',"merchant-session-key");
                token_form.appendChild(input_merchant_key);
                input_merchant_key.setAttribute('value',merchant_session_key);

                var input_cc_owner = document.createElement("input");
                input_cc_owner.setAttribute('type',"text");
                input_cc_owner.setAttribute('data-sagepay',"cardholderName");
                token_form.appendChild(input_cc_owner);
                input_cc_owner.setAttribute('value',document.getElementById("sagepaynit_cc_owner").value);

                var input_cc_number = document.createElement("input");
                input_cc_number.setAttribute('type',"text");
                input_cc_number.setAttribute('data-sagepay',"cardNumber");
                token_form.appendChild(input_cc_number);
                input_cc_number.setAttribute('value',document.getElementById("sagepaynit_cc_number").value);

                var input_cc_exp = document.createElement("input");
                input_cc_exp.setAttribute('type',"text");
                input_cc_exp.setAttribute('data-sagepay',"expiryDate");
                token_form.appendChild(input_cc_exp);
                var expiration = document.getElementById("sagepaynit_expiration").value
                expiration = expiration.length == 1 ? "0" + expiration : expiration;
                expiration += document.getElementById("sagepaynit_expiration_yr").value.substring(2,4);
                input_cc_exp.setAttribute('value',expiration);

                var input_cc_cvc = document.createElement("input");
                input_cc_cvc.setAttribute('type',"text");
                input_cc_cvc.setAttribute('data-sagepay',"securityCode");
                token_form.appendChild(input_cc_cvc);
                input_cc_cvc.setAttribute('value',document.getElementById("sagepaynit_cc_cid").value);

            }else{

                token_form.elements[0].setAttribute('value',merchant_session_key);
                token_form.elements[1].setAttribute('value',document.getElementById("sagepaynit_cc_owner").value);
                token_form.elements[2].setAttribute('value',document.getElementById("sagepaynit_cc_number").value);
                var expiration = document.getElementById("sagepaynit_expiration").value
                expiration = expiration.length == 1 ? "0" + expiration : expiration;
                expiration += document.getElementById("sagepaynit_expiration_yr").value.substring(2,4);
                token_form.elements[3].setAttribute('value',expiration);
                token_form.elements[4].setAttribute('value',document.getElementById("sagepaynit_cc_cid").value);
            }

            var old_message_html = document.getElementById('sagepay-nit-aux-placeholder').innerHTML;

            try {
                //console.log(token_form);

                //request token
                Sagepay.tokeniseCardDetails(token_form, function(status, response){
                    //console.log(status, response);

                    if (status === 201) {
                        if(isOSC){
                            document.getElementsByClassName("onestepcheckout-place-order-loading")[0].innerHTML = old_message_html;
                        }else{
                            checkout.setLoadWaiting(false);
                            document.getElementById("review-please-wait").innerHTML = old_message_html;
                        }

                        //update payment form
                        if(isOSC){
                            var payment_form = document.getElementById('onestepcheckout-form');
                        }else{
                            var payment_form = document.getElementById('co-payment-form');
                        }

                        var input_card_identifier = document.getElementById("nit_card_identifier");
                        if(!input_card_identifier){
                            input_card_identifier = document.createElement("input");
                            input_card_identifier.setAttribute('type',"hidden");
                            input_card_identifier.setAttribute('name',"nit_card_identifier");
                            input_card_identifier.setAttribute('id',"nit_card_identifier");
                            payment_form.appendChild(input_card_identifier);
                            input_card_identifier.value = response.cardIdentifier;
                        }else{
                            input_card_identifier.value = response.cardIdentifier;
                        }

                        //remove cc details from form
                        //document.getElementById("sagepaynit_cc_owner").value = "";
                        document.getElementById("sagepaynit_cc_number").value = "";
                        document.getElementById("sagepaynit_expiration").value = "";
                        document.getElementById("sagepaynit_expiration_yr").value = "";
                        document.getElementById("sagepaynit_cc_cid").value = "";

                        if(isOSC){
                            suite.reviewSave();
                        }

                    } else {
                        console.log(response);
                        alert("Unable to initialize SagePay payment method, please refresh the page and try again.");
                        checkout.setLoadWaiting(false);
                        document.getElementById("review-please-wait").innerHTML = old_message_html;
                    }
                });
            }catch(err){
                console.log(err);
                alert("Unable to initialize SagePay payment method, please refresh the page and try again.");
                if(isOSC){
                    document.getElementsByClassName("onestepcheckout-place-order-loading")[0].innerHTML = old_message_html;
                }else{
                    checkout.setLoadWaiting(false);
                    document.getElementById("review-please-wait").innerHTML = old_message_html;
                }
            }
        }
    },
    nitGenerateMerchantKey: function(){

        //alter review step loading message
        var aux = document.getElementById('sagepay-nit-aux-placeholder');
        if(!aux){
            var aux = document.createElement("span");
            aux.setAttribute("id","sagepay-nit-aux-placeholder");
            document.getElementsByTagName('body')[0].appendChild(aux);
        }
        var old_review_message = document.getElementById("review-please-wait").innerHTML;
        var loader_gif = document.getElementById("review-please-wait").getElementsByTagName("img");
        aux.innerHTML = "";
        aux.appendChild(loader_gif[0]);
        document.getElementById("review-please-wait").innerHTML = aux.innerHTML + " Initializing payment gateway...";
        checkout.setLoadWaiting('review');
        aux.innerHTML = old_review_message;

        var suite = this;

        //get merchant generated key
        new Ajax.Request(SuiteConfig.getConfig('global', 'sgps_nit_generate_merchant_key'), {
            method: "get",
            onSuccess: function (data) {

                try{
                    if(data.status == 200){

                        var response = data.responseText.evalJSON();

                        if(response.merchantSessionKey){
                            //console.log(response.merchantSessionKey);
                            suite.nitGenerateCardToken(response.merchantSessionKey, false, suite);
                        }else{
                            console.log(response);
                            checkout.setLoadWaiting(false);
                            document.getElementById("review-please-wait").innerHTML = old_review_message;
                            alert("Unable to initialize SagePay payment method:, please refresh the page and try again.");
                        }

                    }else{
                        console.log(data);
                        checkout.setLoadWaiting(false);
                        document.getElementById("review-please-wait").innerHTML = old_review_message;
                        alert("Unable to initialize SagePay payment method, please refresh the page and try again.");
                    }
                } catch (err){
                    console.log(err);
                    checkout.setLoadWaiting(false);
                    document.getElementById("review-please-wait").innerHTML = old_review_message;
                    alert("Unable to initialize SagePay payment method, please refresh the page and try again.");
                }
            }
        });
    },
    nitGenerateMerchantKeyOSC: function(){

        //alter review step loading message
        var aux = document.getElementById('sagepay-nit-aux-placeholder');
        if(!aux){
            var aux = document.createElement("span");
            aux.setAttribute("id","sagepay-nit-aux-placeholder");
            document.getElementsByTagName('body')[0].appendChild(aux);
        }
        var old_review_message = document.getElementsByClassName("onestepcheckout-place-order-loading")[0].innerHTML;
        aux.innerHTML = "";
        document.getElementsByClassName("onestepcheckout-place-order-loading")[0].innerHTML = aux.innerHTML + " Initializing payment gateway...";
        //checkout.setLoadWaiting('review');
        aux.innerHTML = old_review_message;

        //remove precious attempts
        previousIdentifier = document.getElementById("nit_card_identifier");
        if(previousIdentifier){
            var payment_form = document.getElementById('onestepcheckout-form');
            payment_form.removeChild(previousIdentifier);
        }

        var suite = this;

        //get merchant generated key
        new Ajax.Request(SuiteConfig.getConfig('global', 'sgps_nit_generate_merchant_key'), {
            method: "get",
            onSuccess: function (data) {

                try{
                    if(data.status == 200){

                        var response = data.responseText.evalJSON();

                        if(response.merchantSessionKey){
                            //console.log(response.merchantSessionKey);
                            suite.nitGenerateCardToken(response.merchantSessionKey, true, suite);
                        }else{
                            console.log(response);
                            //checkout.setLoadWaiting(false);
                            document.getElementsByClassName("onestepcheckout-place-order-loading")[0].innerHTML = old_review_message;
                            alert("Unable to initialize SagePay payment method:, please refresh the page and try again.");
                        }

                    }else{
                        console.log(data);
                        document.getElementsByClassName("onestepcheckout-place-order-loading")[0].innerHTML = old_review_message;
                        alert("Unable to initialize SagePay payment method, please refresh the page and try again.");
                    }
                } catch (err){
                    console.log(err);
                    //checkout.setLoadWaiting(false);
                    document.getElementsByClassName("onestepcheckout-place-order-loading")[0].innerHTML = old_review_message;
                    alert("Unable to initialize SagePay payment method, please refresh the page and try again.");
                }
            }
        });
    }
}

try {
    Event.observe(window, "load", function () {
        //document.observe('dom:loaded', function(){
        getOneStepCheckoutId();

        $(document.body).insert(new Element('a', {
            'id': 'sagepayserver-dummy-link',
            'href': '#',
            'style': 'display:none'
        }).update('&nbsp;'));
        $(document.body).insert(new Element('a', {
            'id': 'sagepaydirectpro-dummy-link',
            'href': '#',
            'style': 'display:none'
        }).update('&nbsp;'));

        var msCont = $('suite_ms_payment_method');

        if (!msCont && (SuiteConfig.getConfig('global', 'ajax_review') == '2') && ((typeof window.review) != 'undefined')) {
            var SageServer = new EbizmartsSagePaySuite.Checkout(
                {
                    'checkout': window.checkout,
                    'review': window.review,
                    'payment': window.payment,
                    'billing': window.billing,
                    'accordion': window.accordion
                }
            );
            //}else if(!msCont && ($$('div.shopping-cart-totals').length != 1) && $(window._sagepayonepageFormId)){
        } else if (!msCont && $(window._sagepayonepageFormId)) {
            var SageServer = new EbizmartsSagePaySuite.Checkout(
                {
                    'osc': window._sagepayonepageTriggerId,
                    'oscFrm': $(window._sagepayonepageFormId)
                }
            );

        } else if (msCont && (msCont.getValue() == 'sagepayserver')) {
            var SageServer = new EbizmartsSagePaySuite.Checkout(
                {
                    'msform': $$('div.multiple-checkout')[0].down(2)
                }
            );
        }

        if (parseInt(SuiteConfig.getConfig('global', 'valid')) === 0) {
            if (SuiteConfig.getConfig('direct', 'mode') == "live" || SuiteConfig.getConfig('server', 'mode') == "live") {
                new PeriodicalExecuter(function () {
                    alert(SuiteConfig.getConfig('global', 'not_valid_message'));
                }, 10);
            } else {
                var invalidG = new k.Growler({
                    location: "bl"
                }).error('<strong>' + SuiteConfig.getConfig('global', 'not_valid_message') + '</strong>', {
                        life: 14400
                    });
            }
        }
    })
}
catch (er) {
    suiteLogError(er);
}

addValidationClass = function (obj) {
    if (obj.hasClassName('validation-passed')) {
        obj.removeClassName('validation-passed');
    }
    obj.addClassName('validate-issue-number');
}
paypalClean = function (reverse) {
    var ccTypeContainer = $('sagepaydirectpro_cc_type');
    var sf = 'div#payment_form_sagepaydirectpro';
    var sfls = $$(sf + ' input, ' + sf + ' select, ' + sf + ' radio, ' + sf + ' checkbox');

    if (reverse) {
        //sfls.invoke('enable');
        //Just hide items wose parent is visible, these prevents enabling hiden token card elements
        sfls.each(function (item) {
            if (item.up().visible() === true) {
                item.enable();
            }
        });

        sfls.invoke('show');

        $$(sf + ' label, ' + sf + ' a[class!="addnew"]').invoke('show');

        //ccTypeContainer.show();
        //ccTypeContainer.disabled = false;
        ccTypeContainer.addClassName('validate-ccsgpdp-type-select');
    } else {
        sfls.invoke('disable');
        sfls.invoke('hide');

        $$(sf + ' label, ' + sf + ' a').invoke('hide');

        ccTypeContainer.show();
        ccTypeContainer.disabled = false;
        ccTypeContainer.removeClassName('validate-ccsgpdp-type-select');
    }

}
changecsvclass = function (obj) {
    var ccTypeContainer = $('sagepaydirectpro_cc_type');
    var ccCVNContainer = $('sagepaydirectpro_cc_cid');

    fillSagePayTestData();

    if (ccTypeContainer.value == 'PAYPAL') {//PayPal MARK integration
        paypalClean(false);
    } else {
        paypalClean(true);
    }

    if (ccTypeContainer) {
        if (ccTypeContainer.value == 'LASER' && ccCVNContainer.hasClassName('required-entry')) {
            if (ccCVNContainer) {
                ccCVNContainer.removeClassName('required-entry');
            }
        }
        if (ccTypeContainer.value != 'LASER' && !ccCVNContainer.hasClassName('required-entry')) {
            if (ccCVNContainer) {
                ccCVNContainer.addClassName('required-entry');
            }
        }
    }
}
changecsvclass_nit = function (obj) {
    var ccTypeContainer = $('sagepaynit_cc_type');
    var ccCVNContainer = $('sagepaynit_cc_cid');

    fillSagePayTestDataNit();

    if (ccTypeContainer) {
        if (ccTypeContainer.value == 'LASER' && ccCVNContainer.hasClassName('required-entry')) {
            if (ccCVNContainer) {
                ccCVNContainer.removeClassName('required-entry');
            }
        }
        if (ccTypeContainer.value != 'LASER' && !ccCVNContainer.hasClassName('required-entry')) {
            if (ccCVNContainer) {
                ccCVNContainer.addClassName('required-entry');
            }
        }
    }
}

Validation.addAllThese([
    ['validate-ccsgpdp-number', 'Please enter a valid credit card number.', function (v, elm) {
        // remove non-numerics
        try {
            var ccTypeContainer = $(elm.id.substr(0, elm.id.indexOf('_cc_number')) + '_cc_type');
            if (ccTypeContainer && typeof Validation.creditCartTypes.get(ccTypeContainer.value) != 'undefined'
                && Validation.creditCartTypes.get(ccTypeContainer.value)[2] == false) {
                if (!Validation.get('IsEmpty').test(v) && Validation.get('validate-digits').test(v)) {
                    return true;
                } else {
                    return false;
                }
            }

            if (ccTypeContainer.value == 'OT' || ccTypeContainer.value == 'UKE' || ccTypeContainer.value == 'DELTA' || ccTypeContainer.value == 'MAESTRO' || ccTypeContainer.value == 'SOLO' || ccTypeContainer.value == 'SWITCH' || ccTypeContainer.value == 'LASER' || ccTypeContainer.value == 'JCB' || ccTypeContainer.value == 'DC') {
                return true;
            }

            return validateCreditCard(v);
        } catch (_error) {
            return true;
        }
    }],
    ['validate-ccsgpdp-cvn', 'Please enter a valid credit card verification number.', function (v, elm) {
        try {
            var ccTypeContainer = $(elm.id.substr(0, elm.id.indexOf('_cc_cid')) + '_cc_type');
            var ccCVNContainer = $(elm.id.substr(0, elm.id.indexOf('_cc_cid')) + '_cc_cid');
            if (ccTypeContainer) {
                if (ccTypeContainer.value == 'LASER' && ccCVNContainer.hasClassName('required-entry')) {
                    if (ccCVNContainer) {
                        ccCVNContainer.removeClassName('required-entry');
                    }
                }
                if (ccTypeContainer.value != 'LASER' && !ccCVNContainer.hasClassName('required-entry')) {
                    if (ccCVNContainer) {
                        ccCVNContainer.addClassName('required-entry');
                    }
                }
            }
            else {
                return true;
            }
            if (!ccTypeContainer && ccTypeContainer.value != 'LASER') {
                return true;
            }
            var ccType = ccTypeContainer.value;

            switch (ccType) {
                case 'VISA' :
                case 'MC' :
                    re = new RegExp('^[0-9]{3}$');
                    break;
                //case 'AMEX' :
                //    re = new RegExp('^[0-9]{4}$');
                //    break;
                case 'MAESTRO':
                case 'SOLO':
                case 'SWITCH':
                    re = new RegExp('^([0-9]{1}|^[0-9]{2}|^[0-9]{3})?$');
                    break;
                default:
                    re = new RegExp('^([0-9]{3}|[0-9]{4})?$');
                    break;
            }

            if (v.match(re) || ccType == 'LASER') {
                return true;
            }

            return false;
        } catch (_error) {
            return true;
        }
    }],
    ['validate-ccsgpdp-type', 'Credit card number doesn\'t match credit card type', function (v, elm) {
        try {
            // remove credit card number delimiters such as "-" and space
            elm.value = removeDelimiters(elm.value);
            v = removeDelimiters(v);

            var ccTypeContainer = $(elm.id.substr(0, elm.id.indexOf('_cc_number')) + '_cc_type');
            if (!ccTypeContainer) {
                return true;
            }
            var ccType = ccTypeContainer.value;

            // Other card type or switch or solo card
            if (ccType == 'MCDEBIT' || ccType == 'OT' || ccType == 'UKE' || ccType == 'DELTA' || ccType == 'MAESTRO' || ccType == 'SOLO' || ccType == 'SWITCH' || ccType == 'LASER' || ccType == 'JCB' || ccType == 'DC') {
                return true;
            }
            // Credit card type detecting regexp
            var ccTypeRegExp = {
                'VISA': new RegExp('^4[0-9]{12}([0-9]{3})?$'),
                'MC': new RegExp('^5[1-5][0-9]{14}$'),
                //'MCDEBIT': new RegExp('(?:516730|516979|517000|517049|535110|535309|535420|535819|537210|537609|557347|557496|557498|557547)'),
                'AMEX': new RegExp('^3[47][0-9]{13}$')
            };

            // Matched credit card type
            var ccMatchedType = '';
            $H(ccTypeRegExp).each(function (pair) {
                if (v.match(pair.value)) {
                    ccMatchedType = pair.key;
                    throw $break;
                }
            });

            if (ccMatchedType != ccType) {
                return false;
            }

            return true;
        } catch (_error) {
            return true;
        }
    }],
    ['validate-ccsgpdp-type-select', 'Card type doesn\'t match credit card number', function (v, elm) {
        try {
            var ccNumberContainer = $(elm.id.substr(0, elm.id.indexOf('_cc_type')) + '_cc_number');
            return Validation.get('validate-ccsgpdp-type').test(ccNumberContainer.value, ccNumberContainer);
        } catch (_error) {
            return true;
        }
    }],
    ['validate-issue-number', 'Issue Number must have at least two characters', function (v, elm) {
        try {
            if (v.length > 0 && !(v.match(new RegExp('^([0-9]{1}|[0-9]{2})$')))) {
                return false;
            }

            return true;
        } catch (_error) {
            return true;
        }
    }]
]);

