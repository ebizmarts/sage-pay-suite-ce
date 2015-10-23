addValidationClass = function(obj){
    if(obj.hasClassName('validation-passed')){
        obj.removeClassName('validation-passed');
    }
    obj.addClassName('validate-issue-number');
}
changecsvclass = function(obj) {

	var methodCode = 'sagepaydirectpro';

	if((typeof FORM_KEY) != 'undefined'){
		methodCode = 'sagepaydirectpro_moto';
	}

    var ccTypeContainer = $(methodCode + '_cc_type');
    var ccCVNContainer = $(methodCode + '_cc_cid');

    fillSagePayTestData();

    if(ccTypeContainer)
    {
        if(ccTypeContainer.value == 'LASER' && ccCVNContainer.hasClassName('required-entry'))
        {
            if(ccCVNContainer) {
                ccCVNContainer.removeClassName('required-entry');
            }
        }
        if(ccTypeContainer.value != 'LASER' && !ccCVNContainer.hasClassName('required-entry'))
        {
            if(ccCVNContainer) {
                ccCVNContainer.addClassName('required-entry');
            }
        }
    }
}

Validation.addAllThese([
    ['validate-ccsgpdp-number', 'Please enter a valid credit card number.', function(v, elm) {
        // remove non-numerics

        var ccTypeContainer = $(elm.id.substr(0,elm.id.indexOf('_cc_number')) + '_cc_type');
        if (ccTypeContainer && typeof Validation.creditCartTypes.get(ccTypeContainer.value) != 'undefined'
            && Validation.creditCartTypes.get(ccTypeContainer.value)[2] == false) {
            if (!Validation.get('IsEmpty').test(v) && Validation.get('validate-digits').test(v)) {
                return true;
            } else {
                return false;
            }
        }

        if (ccTypeContainer.value == 'OT' ||  ccTypeContainer.value == 'UKE' || ccTypeContainer.value == 'DELTA' || ccTypeContainer.value == 'MAESTRO' || ccTypeContainer.value == 'SOLO' || ccTypeContainer.value == 'SWITCH' || ccTypeContainer.value == 'LASER' || ccTypeContainer.value == 'JCB' || ccTypeContainer.value == 'DC') {
            return true;
        }

        return validateCreditCard(v);
    }],
    ['validate-ccsgpdp-cvn', 'Please enter a valid credit card verification number.', function(v, elm) {
        var ccTypeContainer = $(elm.id.substr(0,elm.id.indexOf('_cc_cid')) + '_cc_type');
        var ccCVNContainer = $(elm.id.substr(0,elm.id.indexOf('_cc_cid')) + '_cc_cid');
        if(ccTypeContainer)
        {
            if(ccTypeContainer.value == 'LASER' && ccCVNContainer.hasClassName('required-entry'))
            {
                if(ccCVNContainer) {
                    ccCVNContainer.removeClassName('required-entry');
                }
            }
            if(ccTypeContainer.value != 'LASER' && !ccCVNContainer.hasClassName('required-entry'))
            {
                if(ccCVNContainer) {
                    ccCVNContainer.addClassName('required-entry');
                }
            }
        }
        else
        {
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
            case 'AMEX' :
                re = new RegExp('^[0-9]{4}$');
                break;
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
    }],
    ['validate-ccsgpdp-type', 'Credit card number doesn\'t match credit card type', function(v, elm) {
        // remove credit card number delimiters such as "-" and space
        elm.value = removeDelimiters(elm.value);
        v         = removeDelimiters(v);

        var ccTypeContainer = $(elm.id.substr(0,elm.id.indexOf('_cc_number')) + '_cc_type');
        if (!ccTypeContainer) {
            return true;
        }
        var ccType = ccTypeContainer.value;

        // Other card type or switch or solo card
        if (ccType == 'MCDEBIT' || ccType == 'OT' ||  ccType == 'UKE' || ccType == 'DELTA' || ccType == 'MAESTRO' || ccType == 'SOLO' || ccType == 'SWITCH' || ccType == 'LASER' || ccType == 'JCB' || ccType == 'DC') {
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

        if(ccMatchedType != ccType) {
            return false;
        }

        return true;
    }],
    ['validate-ccsgpdp-type-select', 'Card type doesn\'t match credit card number', function(v, elm) {
        var ccNumberContainer = $(elm.id.substr(0,elm.id.indexOf('_cc_type')) + '_cc_number');
        return Validation.get('validate-ccsgpdp-type').test(ccNumberContainer.value, ccNumberContainer);
    }],
    ['validate-issue-number', 'Issue Number must have at least two characters', function(v, elm) {

        if(v.length > 0 && !(v.match(new RegExp('^([0-9]{1}|[0-9]{2})$')))){
            return false;
        }

        return true;
    }]
]);

Validation.addAllThese([
    ['validate-cc-ukss', 'Please enter issue number or start date for switch/solo card type.', function(v,elm) {
              var endposition;

              if (elm.id.match(/(.)+_cc_issue$/)) {
                  endposition = elm.id.indexOf('_cc_issue');
              } else if (elm.id.match(/(.)+_start_month$/)) {
                  endposition = elm.id.indexOf('_start_month');
              } else {
                  endposition = elm.id.indexOf('_start_year');
              }

              var prefix = elm.id.substr(0,endposition);

              var ccTypeContainer = $(prefix + '_cc_type');

              if (!ccTypeContainer) {
                    return true;
              }
              var ccType = ccTypeContainer.value;

              if(ccType!='SS'){
                  return true;
              }

              $(prefix + '_cc_issue').advaiceContainer
                = $(prefix + '_start_month').advaiceContainer
                = $(prefix + '_start_year').advaiceContainer
                = $(prefix + '_cc_type_ss_div').down('ul li.adv-container');

              var ccIssue   =  $(prefix + '_cc_issue').value;
              var ccSMonth  =  $(prefix + '_start_month').value;
              var ccSYear   =  $(prefix + '_start_year').value;

              if((!ccIssue && !ccSMonth && !ccSYear) ||
                 (!ccIssue && !ccSMonth && ccSYear)  ||
                 (!ccIssue && ccSMonth && !ccSYear)
              ){
                  return false;
              }

              return true;

    }]
]);