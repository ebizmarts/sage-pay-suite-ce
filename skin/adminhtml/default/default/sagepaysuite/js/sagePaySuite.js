number_format=function(f,c,l,e){var b=f,a=c;var h=function(r,q){var i=Math.pow(10,q);return(Math.round(r*i)/i).toString()};b=!isFinite(+b)?0:+b;a=!isFinite(+a)?0:Math.abs(a);var p=(typeof e==="undefined")?",":e;var d=(typeof l==="undefined")?".":l;var o=(a>0)?h(b,a):h(Math.round(b),a);var m=h(Math.abs(b),a);var k,g;if(m>=1000){k=m.split(/\D/);g=k[0].length%3||3;k[0]=o.slice(0,g+(b<0))+k[0].slice(g).replace(/(\d{3})/g,p+"$1");o=k.join(d)}else{o=o.replace(".",d)}var j=o.indexOf(d);if(a>=1&&j!==-1&&(o.length-j-1)<a){o+=new Array(a-(o.length-j-1)).join(0)+"0"}else{if(a>=1&&j===-1){o+=d+new Array(a).join(0)+"0"}}return o};releasePayment=function(c,d,g){var f=$("amntToRelease");var e=f.getValue();if(f.visible()){if(confirm(Translator.translate("Are you sure?"))){new Ajax.Request(c,{method:"post",parameters:{orderID:d,usrname:g,amount:e},onSuccess:function(a){var b=a.responseText;if(b=="OK"){location.href=location.href}else{alert(b)}}})}}else{$("capture_payment_"+d).update(Translator.translate("Confirm Release"));f.show()}};abortPayment=function(c,d,e){if(confirm(Translator.translate("Are you sure?"))){new Ajax.Request(c,{method:"post",parameters:{orderID:d,usrname:e},onSuccess:function(a){var b=a.responseText;if(b=="OK"){location.href=location.href}else{alert(b)}}})}};voidPayment=function(c,d,e){if(confirm(Translator.translate("Are you sure?"))){new Ajax.Request(c,{method:"post",parameters:{orderID:d,usrname:e},onSuccess:function(a){var b=a.responseText;if(b=="OK"){location.href=location.href}else{alert(b)}}})}};cancelPayment=function(c,d,e){if(confirm(Translator.translate("Are you sure?"))){new Ajax.Request(c,{method:"post",parameters:{orderID:d,usrname:e},onSuccess:function(a){var b=a.responseText;if(b=="OK"){location.href=location.href}else{alert(b)}}})}};authorisePayment=function(c,d,g){var f=$("amntToAuthorise");var e=f.getValue();if(f.visible()){if(confirm(Translator.translate("Are you sure?"))){if(!parseFloat(e)){alert(Translator.translate("Please enter a valid value."));return}new Ajax.Request(c,{method:"post",parameters:{orderID:d,usrname:g,amount:e},onSuccess:function(a){var b=a.responseText;if(b=="OK"){location.href=location.href}else{alert(b)}}})}}else{$("authorise_payment_"+d).update(Translator.translate("Confirm Authorise"));f.show()}};refundPayment=function(c,d){var g=$("amntToRefund");var i=$("refundDescrTxtarea");var f=$("refund_type_id");var e=g.getValue();var h=i.getValue();if(g.visible()){if(confirm(Translator.translate("Are you sure?"))){if(!parseFloat(e)){alert(Translator.translate("Please enter a valid value to Refund."));return}new Ajax.Request(c,{method:"post",parameters:{orderID:d,amount:e,descr:h},onSuccess:function(a){var b=a.responseText;if(b=="OK"){location.href=location.href}else{alert(b)}}})}}else{$("refund_payment_"+d).update(Translator.translate("Confirm Refund"));g.show();g.adjacent("label")[0].show();g.ancestors()[0].show();f.show();f.adjacent("label")[0].show();f.ancestors()[0].show();i.show();i.adjacent("label")[0].show();i.ancestors()[0].show()}};obsRefundChange=function(c){var b=this.getValue();var a=$("amntToRefund");switch(b){case"mn":a.writeAttribute("value","");break;case"ot":a.writeAttribute("value",number_format(TOTAL_ORDER_AMOUNT,2,".",""));break;case"ot-sh":a.writeAttribute("value",number_format(SUB_TOTAL_ORDER_AMOUNT,2,".",""));break}};repeatPayment=function(j,k,i){var d=$("amntToRepeat");var h=$("repeatDescrTxtarea");var f=$("currency_type_id");var e=d.getValue();var g=h.getValue();var c=f.getValue();if(d.visible()){if(confirm(Translator.translate("Are you sure?"))){if(!parseFloat(e)){alert(Translator.translate("Please enter a valid value."));return}new Ajax.Request(j,{method:"post",parameters:{orderID:k,usrname:i,amount:e,descr:g,currncy:c,trnreptype:"REPEAT"},onSuccess:function(a){var b=a.responseText;if(b=="OK"){location.href=location.href}else{alert(b)}}})}}else{$("repeat_payment_"+k).update(Translator.translate("Confirm Repeat"));d.show();d.adjacent("label")[0].show();d.ancestors()[0].show();h.show();h.adjacent("label")[0].show();h.ancestors()[0].show();f.show();f.adjacent("label")[0].show();f.ancestors()[0].show()}};Event.observe(window,"load",function(){var a=$("refund_type_id");if(a){Event.observe(a,"change",obsRefundChange)}});

closeModalWindow = function(){
	window.parent.Control.Window.windows.each(function(w){
		if(w.container.visible()){
			w.close();
		}
	});
}

colorResults = function(){
	var uele = $$('ul#card-checks');
	if(uele.length>0){
		uele[0].childElements().each(function(li){

			var cp = li.select('span')[0];
			switch (cp.innerHTML){
				case 'MATCHED':
				  cp.setStyle({'color':'#0F8F08'});
				  break;
				case 'NOTMATCHED':
				  cp.setStyle({'color':'#EF0E0E'});
				  break;
				case 'NOTPROVIDED':
				  cp.setStyle({'color':'#9F9F9F'});
				case 'NOTCHECKED':
                case 'DATA NOT CHECKED':
                case 'SECURITY CODE MATCH ONLY':
				  cp.setStyle({'color':'#EF8100'});
				  break;
				default:
		 		  break;
			}

		});
	}
}

logSelectObserve = function(){
	var sl = $('sl-log-switcher');
	if(sl){
		sl.observe('change', function(ev){
			$('sagepaysuite-log-view').src = sl.getValue();
		});
	}
}

loadActions = function(){
	colorResults();
	logSelectObserve();

}

Event.observe(window, 'load', loadActions);


//Modify grid action for modals
var varienGridAction = {
    execute: function(select) {
        if(!select.value || !select.value.isJSON()) {
            return;
        }

        var config = select.value.evalJSON();
        if(config.confirm && !window.confirm(config.confirm)) {
            select.options[0].selected = true;
            return;
        }

        if(config.popup) {

        	if(config.modal){

				try{
					var cont = new Element('div',{className: 'lcontainer'});
			        var wndow = new Control.Modal(config.href,{
							     className: 'modal-sagepaysuite',
							     iframe: true,
							     insertRemoteContentAt: cont,
							     height: '800',
							     width: '1100',
							     fade: true
					 });
					 wndow.container.insert(cont);
					 wndow.open();
				}catch(mer){}

        	}else{
	            var win = window.open(config.href, 'action_window', 'width=500,height=600,resizable=1,scrollbars=1');
	            win.focus();
        	}
            select.options[0].selected = true;

        } else {
            setLocation(config.href);
        }
    }
};



var orphansGridMassaction = Class.create(varienGridMassaction, {
    onGridRowClick: function(grid, evt) {
        var tdElement = Event.findElement(evt, 'td');
        var trElement = Event.findElement(evt, 'tr');

        if(!$(tdElement).down('input')) {
            if($(tdElement).down('a') || $(tdElement).down('select')) {
                return;
            }
            if (trElement.title) {

				try{
					var cont = new Element('div',{className: 'lcontainer'});
			        var wndow = new Control.Modal(trElement.title,{
							     className: 'modal-sagepaysuite',
							     iframe: true,
							     insertRemoteContentAt: cont,
							     height: '800',
							     width: '1100',
							     fade: true
					 });
					 wndow.container.insert(cont);
					 wndow.open();
				}catch(mer){
					setLocation(trElement.title);
				}

            }
            else{
                var checkbox = Element.select(trElement, 'input');
                var isInput  = Event.element(evt).tagName == 'input';
                var checked = isInput ? checkbox[0].checked : !checkbox[0].checked;

                if(checked) {
                    this.checkedString = varienStringArray.add(checkbox[0].value, this.checkedString);
                } else {
                    this.checkedString = varienStringArray.remove(checkbox[0].value, this.checkedString);
                }
                this.grid.setCheckboxChecked(checkbox[0], checked);
                this.updateCount();
            }
            return;
        }

        if(Event.element(evt).isMassactionCheckbox) {
           this.setCheckbox(Event.element(evt));
        } else if (checkbox = this.findCheckbox(evt)) {
           checkbox.checked = !checkbox.checked;
           this.setCheckbox(checkbox);
        }
    }
});
