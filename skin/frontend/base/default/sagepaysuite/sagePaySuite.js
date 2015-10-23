getSgpsAjaxUrl = function (method){

    var loc = window.location.href;

    loc = loc.replace('card/index/','card/');
    loc = loc.replace('card/index','card/');

    if(loc.slice(-1)== '/'){
        return loc+method;
    }else{
        return loc+'/'+method;
    }
}

tokenSetDefault = function (radioelement){

    new Ajax.Request(getSgpsAjaxUrl('default'), {
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
                if(rsp.mark.ccnickname == ''){
                    rsp.mark.ccnickname = 'Click to add a Description';
                }

                var newCardRow = new Template('<tr><td>#{cctype}</td><td class="nickname" id="nickname_#{id}"><div style="cursor: pointer" title="Click to edit the credit card description.">#{ccnickname}</div></td><td>#{ccnumber}</td><td>#{exp}</td><td class="a-center"><input#{defaultchecked} type="radio" value="#{id}" name="tokencard_def" onclick="tokenSetDefault(this);" /></td><td class="last"><a href="#{delurl}" onclick="if(confirm(\''+Translator.translate('Are you sure?')+'\')){removeCard(this); return false; }else{return false;}">'+Translator.translate('Delete')+'</a></td></tr>');
                new Insertion.Before($$('table#my-sagepaycards-table tbody tr')[0], newCardRow.evaluate(rsp.mark));
                setObservers();
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

updateNickname = function(card,nickname,successCallback,failureCallback) {

    this.url = getSgpsAjaxUrl('updatenickname/card/'+parseInt(card));
    new Ajax.Request(url, {
        method: 'post', //should be patch
        postBody: 'nickname='+nickname,
        onSuccess: function(result) {

            if(JSON.parse(result.transport.response).st == 'ok'){

                if(typeof successCallback == 'function'){
                    successCallback();
                }
                $('sageTokenCardLoading').hide();
            }

        },
        onFailure: function(){
            if(typeof failureCallback == 'function'){
                failureCallback();
            }
            $('sageTokenCardLoading').hide();
        },
        onLoading: function() {
            $('sageTokenCardLoading').show();
        }
    });

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
                if(rsp.text == 'full_redirect') {
                    setLocation(rsp.url);
                }else {
                    new Insertion.After('link-regcard', rsp.text);
                }
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

function trim (str) {
    return str.replace(/^\s\s*/, '').replace(/\s\s*$/, '');
}

editNickname = function(event){

    this.div = event.findElement();
    this.td = this.div.up('td');
    this.textField = '<input type="text" value="'+trim(this.div.innerHTML == 'Click to add a Description' ? '': this.div.innerHTML)+'"/>';
    this.td.update(this.textField);
    this.input = this.td.childElements()[0];

    this.div.stopObserving('click', editNickname);
    this.input.focus();
    this.input.observe('blur', saveNickname);
    this.input.observe('keydown', keyPressHandler);

}

keyPressHandler = function(event){
    if (event.keyCode == 13) {
        saveNickname(event);
    }
}

saveNickname = function(event){

    this.input = event.findElement();
    this.td = this.input.up('td');

    this.input.stopObserving('blur', saveNickname);

    this.nickname = this.input.value;
    this.id = this.input.up('td').id;
    this.id = parseInt(this.id.replace('nickname_',''));

    var self = this;

    this.successCallback = function(){

        if(self.nickname) {
            self.td.update('<div style="cursor: pointer" title="Click to edit the credit card description.">' + self.nickname + '</div>');
        }else {
            self.td.update('<div style="cursor: pointer" title="Click to edit the credit card description.">Click to add a Description</div>');
        }
        self.td.down('div').observe('click', editNickname);
    };

    this.failureCallback = function (){
        alert('Something went wrong, credit card description not changed.');
    }

    updateNickname(this.id,this.nickname,this.successCallback,this.failureCallback);
}

setObservers = function(){
    $$('.nickname').each(function(elem){
        Event.stopObserving(elem.down('div'), 'click', editNickname);
        Event.observe(elem.down('div'), 'click', editNickname);
    });
}

document.observe("dom:loaded", function() {
    setObservers();
});