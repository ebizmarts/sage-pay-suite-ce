suiteLogError = function(erText){
	if(!window.console){
		return;
	}
	console.error(erText);
}

if(typeof EbizmartsSagePaySuite == 'undefined') {
    var EbizmartsSagePaySuite = {};
}
EbizmartsSagePaySuite.Conf = Class.create();
EbizmartsSagePaySuite.Conf.prototype = {
	initialize: function(config){
		this.config = config;
	},
	getConfig: function(type, field){
		try{
			return this.config[type][field];
		}catch(error){
			return null;
		}
	},
	setConfig: function(type, field, value){
		if((typeof this.config[type][field]) == 'undefined'){
			this.config[type][field] = value;
		}
	}
}

var sagePayIsMobile = function() {
	return (navigator.userAgent.match(/BlackBerry/i) ||
		navigator.userAgent.match(/webOS/i) ||
		navigator.userAgent.match(/Android/i) ||
		navigator.userAgent.match(/iPhone/i) ||
		navigator.userAgent.match(/iPod/i) ||
		navigator.userAgent.match(/iPad/i));
}

fillSagePayTestData = function(){

	var shortCode = 'direct';
	var methodCode = 'sagepaydirectpro';

	if((typeof FORM_KEY) != 'undefined'){
		shortCode = 'directmoto';
		methodCode = 'sagepaydirectpro_moto';
	}

    if(SuiteConfig.getConfig(shortCode,'mode') != 'test' && SuiteConfig.getConfig(shortCode,'mode') != 'simulator'){
        return;
    }

    SuiteConfig.getConfig(shortCode,'test_data').evalJSON().each(function(cc, ci){

        if(cc.code == $(methodCode + '_cc_type').getValue()){
            $(methodCode + '_cc_owner').value = (rstring() + ' ' + rstring());
            $(methodCode + '_expiration').setValue(Math.floor(Math.random()*12)+1);

            var d = new Date();
            $(methodCode + '_expiration_yr').setValue((parseInt(d.getFullYear())+(Math.floor(Math.random()*10)+1)));

        	$(methodCode + '_cc_number').value = cc.ccn;
            $(methodCode + '_cc_cid').value = cc.cvv;
            if((typeof cc.isn != 'undefined')){
            	$(methodCode + '_cc_issue').value = cc.isn;
            }else{
            	$(methodCode + '_cc_issue').value = '';
            }
            return;
        }

    });
}

fillSagePayTestDataNit = function(){

    var shortCode = 'nit';
    var methodCode = 'sagepaynit';

    if(SuiteConfig.getConfig(shortCode,'mode') != 'test' && SuiteConfig.getConfig(shortCode,'mode') != 'simulator'){
        return;
    }

    SuiteConfig.getConfig(shortCode,'test_data').evalJSON().each(function(cc, ci){

        if(cc.code == $(methodCode + '_cc_type').getValue()){
            $(methodCode + '_cc_owner').value = (rstring() + ' ' + rstring());
            $(methodCode + '_expiration').setValue(Math.floor(Math.random()*12)+1);

            var d = new Date();
            $(methodCode + '_expiration_yr').setValue((parseInt(d.getFullYear())+(Math.floor(Math.random()*10)+1)));

            $(methodCode + '_cc_number').value = cc.ccn;
            $(methodCode + '_cc_cid').value = cc.cvv;

            if((typeof cc.isn != 'undefined')){
                $(methodCode + '_cc_issue').value = cc.isn;
            }else{
                $(methodCode + '_cc_issue').value = '';
            }

            return;
        }

    });
}

toggleNewCard = function(action){

	var adminFrms = $$("#order-billing_method_form [name='payment[method]']");
    if((typeof payment.form) == "undefined") {
        var frontFrms = $$("div#payment-method [name='payment[method]']");//IWD OnePageCheckout
    }
    else {
        var frontFrms = $$("#"+payment.form+" [name='payment[method]']");
    }
	var msFrms    = $$("#multishipping-billing-form [name='payment[method]']");

	if(adminFrms.length){
		var frms = adminFrms;
	}else if(msFrms.length){
		var frms = msFrms;
	}else{
		var frms = frontFrms;
	}

	var method = null;
	if(frms.length){
		frms.each(function(el){
			if(el.checked){
				method = el.value;
			}
		});
	}
	var frmSelector = 'div#payment_form_' + method;
	if(parseInt(action) == 2){

		$$(frmSelector + ' li', frmSelector + ' ul').invoke('show');
		$$(frmSelector + ' ul li.tokencard-radio input').each(function(radiob){
			//radiob.disabled = 'disabled';
			radiob.disabled = true;
		});

		if(adminFrms.length){
			$$(frmSelector + ' ul.paymentsage select').each(function(sl){
				sl.disabled = false;
			});
			$$(frmSelector + ' ul.paymentsage input').each(function(sl){
				sl.disabled = false;
			});
		}

		$$(frmSelector + ' ul li.tokencard-radio', frmSelector + ' a.addnew').invoke('hide');

        //hide tokens
        var token_lists = document.getElementsByClassName("tokensage");
        for(var i=0;i<token_lists.length;i++){
            token_lists[i].style.display="none";

            //move elsewhere to be sure (OSC)
            var auxContainer = document.getElementById("sagepaysuite_aux_token_container" + i);
            if(!auxContainer){
                auxContainer = document.createElement("div");
                auxContainer.setAttribute("id","sagepaysuite_aux_token_container" + i);
                auxContainer.setAttribute("style","display:none;");
                document.body.appendChild(auxContainer);
            }

            auxContainer.innerHTML = token_lists[i].innerHTML;
            token_lists[i].innerHTML = "";
        }

	}else{

		var tokenInputs = $$(frmSelector + ' ul li.tokencard-radio input');
		if(parseInt(tokenInputs.length) === 0){
			return;
		}
		if(adminFrms.length){
			$$(frmSelector + ' ul.paymentsage select').each(function(sl){
				sl.disabled = true;
			});
			$$(frmSelector + ' ul.paymentsage input').each(function(sl){
				sl.disabled = true;
			});
		}

		$$(frmSelector + ' ul.paymentsage li', frmSelector + ' ul.paymentsage').invoke('hide');
		tokenInputs.each(function(radiob){
			//radiob.removeAttribute('disabled');
			radiob.disabled = false;
		});
		$$(frmSelector + ' ul li.tokencard-radio', frmSelector + ' a.addnew').invoke('show');

        //use tokens
        var token_lists = document.getElementsByClassName("tokensage");
        for(var i=0;i<token_lists.length;i++){
            token_lists[i].style.display="block";

            //get from aux container to be sure (OSC)
            var auxContainer = document.getElementById("sagepaysuite_aux_token_container" + i);
            token_lists[i].innerHTML = auxContainer.innerHTML;
            auxContainer.innerHTML = "";
        }
	}

    //hide unneeded fields
    if(typeof SSCheckedsagepaydirectpro == 'function'){
        SSCheckedsagepaydirectpro();
    }

}

tokenRadioCheck = function(radioID, cvv){

    //disable all else
    $$('input.tokencvv').each(function(inp){
        if(inp.id != cvv.id){
            inp.disabled = 'disabled';
        }
    })

	try{
		$(radioID).checked = true;
	}catch(noex){}

	var adminFrms = $$("#order-billing_method_form [name='payment[method]']");
	if(adminFrms.length){
		var frmSelector = 'div#payment_form_sagepaydirectpro_moto';
		$$(frmSelector + ' ul.paymentsage select').each(function(sl){
			sl.disabled = true;
		});
		$$(frmSelector + ' ul.paymentsage input').each(function(sl){
			sl.disabled = true;
		});
		$$('input.tokencvv').each(function(sl){
			if(sl.id != cvv.id){
				sl.disabled = true;
			}
		});
	}
}

switchToken = function(radio,card_type){

    //set cc type
    if($("sagepaydirectpro_cc_type_token")){
        $("sagepaydirectpro_cc_type_token").value = card_type;
    }else if($("sagepaydirectpro_moto_cc_type_token")){
        $("sagepaydirectpro_moto_cc_type_token").value = card_type;
    }

	$$('div.tokencvv').invoke('hide');
	$$('input.tokencvv').each(function(inp){
		inp.disabled = 'disabled';
	})

	if($('serversecure')){
		$('serversecure').hide();
	}

	var divcont = radio.next('div');
	if((typeof divcont) != 'undefined'){
		divcont.down().next('input').removeAttribute('disabled');
		divcont.show();
	}
}

rstring = function (){
	//var length = 6;
	var length = rand(4, 9);

    var conso = new Array("b","c","d","f","g","h","j","k","l", "m","n","p","r","s"
    ,"t","v","w","x","y","z");
    var vocal = new Array("a","e","i","o","u");
    var max = length/2;
    var text = "";

    for(i=1; i<=max; i++){
	    text += conso[Math.floor(Math.random()*20)];
	    text += vocal[Math.floor(Math.random()*5)];
    }

    return text.charAt(0).toUpperCase() + text.slice(1);
}

rand = function (min, max) {
    var argc = arguments.length;
    if (argc === 0) {
        min = 0;
        max = 2147483647;
    } else if (argc === 1) {
        throw new Error('Warning: rand() expects exactly 2 parameters, 1 given');
    }
    return Math.floor(Math.random() * (max - min + 1)) + min;
}

document.observe("dom:loaded",function(){$$(".hidesuite").each(function(a){a.writeAttribute({disabled:"disabled"})});$$("div [id^=row_sagepaysuite_]").each(function(a){a.addClassName("tooltip-suite")})});
