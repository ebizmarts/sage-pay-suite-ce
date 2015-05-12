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
});
