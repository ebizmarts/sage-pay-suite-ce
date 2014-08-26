tokenSetDefault = function (radioelement){

    new Ajax.Request((BLANK_URL.replace('js/blank.html', 'sgps/card/default')), {
        method: 'get',
        parameters: {
            card:radioelement.value
            },
        onSuccess: function(transport) {

            var rsp = transport.responseText.evalJSON();

            if(rsp.st != 'ok'){
                alert(rsp.text);
            }

            $('sageTokenCardLoading').hide();

        },
        onLoading: function(){
            $('sageTokenCardLoading').show();
        }
    })

}

evenOdd = function(row, index){
    var _class = ((index+1)%2 == 0 ? 'even' : 'odd');
    row.addClassName(_class);
}

updateEvenOdd = function(){
    var rows = $$('table#my-sagepaycards-table tbody tr');
    rows.invoke('removeClassName', 'odd').invoke('removeClassName', 'even');
    rows.each(
        function(row, index){
            evenOdd(row, index);
        });
}

postRegisterCard = function(frm){

    var safeForm = new Validation(frm);
    if(!safeForm.validate()){
        return;
    }

    frm.request({
        onComplete: function(trn){

            var rsp = trn.responseText.evalJSON();

            if(rsp.status != 'OK'){
                alert(rsp.status.toString() +' -> '+ rsp.statusdetail.toString());
            }else{
                var newCardRow = new Template('<tr><td>#{cctype}</td><td>#{ccnumber}</td><td>#{exp}</td><td class="a-center"><input#{defaultchecked} type="radio" value="#{id}" name="tokencard_def" onclick="tokenSetDefault(this);" /></td><td class="last"><a href="#{delurl}" onclick="if(confirm(\''+Translator.translate('Are you sure?')+'\')){removeCard(this); return false; }else{return false;}">'+Translator.translate('Delete')+'</a></td></tr>');

                new Insertion.Before($$('table#my-sagepaycards-table tbody tr')[0], newCardRow.evaluate(rsp.mark));
                if($('no-tokencards-tr')){
                    $('no-tokencards-tr').up().remove();
                }
                $('frmRegCard').remove();

                updateEvenOdd();
            //window.location.reload();
            }

            $('sageTokenCardLoading').hide();

        },
        onLoading: function(){
            $('sageTokenCardLoading').show();
        }
    })

}

removeCard = function(elem) {

    var oncheckout = elem.hasClassName('oncheckout');

    new Ajax.Request(elem.href, {
        method: 'get',
        onSuccess: function(transport) {
            try {
                var rsp = transport.responseText.evalJSON();

                if(rsp.st != 'ok') {
                    new Effect.Opacity(elem.up(), { from: 0.3, to: 1.0, duration: 0.5 });
                    alert(rsp.text);
                }
                else {
                    if(false === oncheckout) {
                        elem.up().up().fade({
                            afterFinish:function(){
                                elem.up().up().remove();
                                updateEvenOdd();
                            }
                        });
                    }
                    else {
                        elem.up().fade({
                            afterFinish:function() {

                                var daiv = elem.up('div');

                                elem.up().remove();

                                //If no tokens, open new token dialog
                                var tokens = daiv.select("li.tokencard-radio input").length;
                                if(parseInt(tokens) === 0) {
                                    toggleNewCard(2);
                                    $$("a.usexist").first().up().remove();
                                }

                            }
                        });
                    }
                }

                if(!oncheckout) {
                    $('sageTokenCardLoading').hide();
                }
            }catch(er){
                alert(er);
            }
        },
        onLoading: function() {
            if(!oncheckout) {
                if($('iframeRegCard')) {
                    $('iframeRegCard').remove();
                }
                else if($('frmRegCard')) {
                    $('frmRegCard').remove();
                }
                $('sageTokenCardLoading').show();
            }
            else {
                new Effect.Opacity(elem.up(), { from: 1.0, to: 0.3, duration: 0.5 });
            }

        }
    })

}

registerCard = function(url){
    new Ajax.Request(url, {
        method: 'get',
        onSuccess: function(transport) {

            var rsp = transport.responseText.evalJSON();

            if(rsp.url == 'ERROR'){
                alert(rsp.text);
            }else{
                new Insertion.After('link-regcard', rsp.text);
            }

            $('sageTokenCardLoading').hide();

        },
        onLoading: function(){

            if($('iframeRegCard')){
                $('iframeRegCard').remove();
            }else if($('frmRegCard')){
                $('frmRegCard').remove();
            }

            $('sageTokenCardLoading').show();

        }
    })
}