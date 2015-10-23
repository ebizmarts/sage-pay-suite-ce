Event.observe(window, 'load', function() {

    var slElements = $$('select.rel-to-selected');
    if( parseInt(slElements.length) > 0 ) {
            $$('select.rel-to-selected').each(function(sl){
                    var slvalue = sl.readAttribute('rel');
                    if(slvalue.length){
                            sl.select('option').each(function(opt){
                                    if(opt.readAttribute('value') == slvalue){
                                            opt.selected = "selected";
                                            return false;
                                    }
                            });
                    }
            });
    }


    if((typeof $("ebizmarts-helpdesk-trigger")) != "undefined") {
        Event.observe("ebizmarts-helpdesk-trigger", "click", function(event) {

            event.preventDefault();

            try {

                var cont = new Element('div', {className: 'lcontainer'});
                var wndow = new Control.Modal(this.href,{
                                     className: 'modal-sagepaysuite',
                                     iframe: true,
                                     insertRemoteContentAt: cont,
                                     height: '510',
                                     width: '550',
                                     fade: true
                 });
                 wndow.container.insert(cont);
                 wndow.open();

            } catch(mer) {
                popWin(this.href, 'helpdesk', 'width=550,height=550,top=0,left=0,resizable=yes,scrollbars=yes');
                console.error(mer);
            }
        });
    }

});