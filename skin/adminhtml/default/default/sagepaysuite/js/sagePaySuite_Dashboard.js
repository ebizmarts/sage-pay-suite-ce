document.observe('dom:loaded', function() {

    if(typeof currencies != 'undefined') {
        totalsPie();

        transactionsBar();

        new Control.Tabs('transactions_graph');
    }

});


var transactionsBar = function() {
    
    currencies.each(function(currency) {
        
        
        var trns = new Array();
            
        for (var i = 0; i < graphData.length; i++) {
                
            if(graphData[i].currency.toString() == currency.key) {
                trns.push(graphData[i]);
            }        
        }

        //transactions per day graph
        var margin = {
            top: 20, 
            right: 20, 
            bottom: 58, 
            left: 50
        },
        width = 960 - margin.left - margin.right,
        height = 500 - margin.top - margin.bottom;
        
        var formatFloat = d3.format(',f');

        var x = d3.scale.ordinal()
        .rangeRoundBands([0, width], .1);

        var y = d3.scale.linear()
        .range([height, 0]);

        var xAxis = d3.svg.axis()
        .scale(x)
        .orient("bottom");

        var yAxis = d3.svg.axis()
        .scale(y)
        .orient("left")
        .tickFormat(formatFloat);

        var w = width + margin.left + margin.right;
        var h = height + margin.top + margin.bottom;

        var svg = d3.select("#spDashboardWidgetTransactionsByDayGraphsContainer" + currency.key)
        .append("svg")
        .attr("width", w)
        .attr("height", h)
        .append("g")
        .attr("transform", "translate(" + margin.left + "," + margin.top + ")");
        
        trns.forEach(function(d) {
            if(d.currency == currency.key) {
                d.price = +d.price;
            }
        });

        x.domain(trns.map(function(d) {
            return d.date;
        }));
        
        var maxPrice = d3.max(trns, function(d) {
            return d.price;
        });
        
        y.domain([0, maxPrice]);

        svg.append("g")
        .attr("class", "x axis xaxis")
        .attr("transform", "translate(0," + height + ")")
        .call(xAxis);

        svg.append("g")
        .attr("class", "y axis")
        .call(yAxis)
        .append("text")
        .attr("transform", "rotate(-90)")
        .attr("y", 6)
        .attr("dy", ".71em")
        .style("text-anchor", "end")
        .text("Amount (" + currency.key + ")");

//        svg.selectAll(".yTicks")
//        .data(trns)
//        .enter().append("svg:line")
//        .attr('x1','90')
//        .attr('y1',function(d) {
//            return 400 - y(d);
//        })
//        .attr('x2',320)
//        .attr('y2',function(d) {
//            return 400 - y(d);
//        })
//        .style('stroke','lightgray');

        svg.selectAll("rect")
        .data(trns)
        .enter()
        .append("rect")
        .style('fill','teal')
        .on('mouseover', function(d, i) { 

            d3.select(this)
            .style('fill','#eb5e00'); 
            
            d3.select("#current-amount").html(d.formatted_price);
        }) 
        .on('mouseout', function(d,i) { 
            d3.select("#current-amount").html("");
            d3.select(this)
            .style('fill','teal'); 
        })
        .attr("x", function(d) {
            return x(d.date);
        })
        .attr("width", x.rangeBand())
        .attr("y", function(d) {
            return y(d.price);
        })
        .attr("height", function(d) {
            return height - y(d.price);
        });

        svg.selectAll(".xaxis text")  // select all the text elements for the xaxis
        .attr("transform", function(d) {
            return "translate(" + this.getBBox().height*-2 + "," + this.getBBox().height*+1.5 + ")rotate(-45)";
        });
        
    });    
    
}

var totalsPie = function() {


    var w = 300,                        //width
    h = 300,                            //height
    r = 100;                            //radius

    var vis = d3.select("#totalsPie")
    .append("svg:svg")              //create the SVG element inside the <body>
    .data([totalsData])                   //associate our data with the document
    .attr("width", w)           //set the width and height of our visualization (these will be attributes of the <svg> tag
    .attr("height", h)
    .append("svg:g")                //make a group to hold our pie chart
    .attr("transform", "translate(" + r + "," + r + ")")    //move the center of the pie chart from 0, 0 to radius, radius

    var arc = d3.svg.arc()              //this will create <path> elements for us using arc data
    .outerRadius(r);

    var pie = d3.layout.pie()           //this will create arc data for us given a list of values
    .value(function(d) {
        return d.value;
    });    //we must tell it out to access the value of each element in our data array

    var arcs = vis.selectAll("g.slice")     //this selects all <g> elements with class slice (there aren't any yet)
    .data(pie)                          //associate the generated pie data (an array of arcs, each having startAngle, endAngle and value properties)
    .enter()                            //this will create <g> elements for every "extra" data element that should be associated with a selection. The result is creating a <g> for every object in the data array
    .append("svg:g")                //create a group to hold each slice (we will have a <path> and a <text> element associated with each slice)
    .attr("class", "slice");    //allow us to style things in the slices (like text)

    arcs.append("svg:path")
    .attr("fill", function(d, i) {
        var myColor = "#339900";
                    
        if(d.data.status == "nok") {
            myColor = "#CC0000";
        }
                    
        return myColor;
                    
    } ) //set the color for each slice to be chosen from the color function defined above
    .attr("d", arc);                                    //this creates the actual SVG path using the associated data (pie) with the arc drawing function

    arcs.append("svg:text")                                     //add a label to each slice
    .attr("transform", function(d) {                    //set the label's origin to the center of the arc
        //we have to make sure to set these before calling arc.centroid
        d.innerRadius = 0;
        d.outerRadius = r;
        return "translate(" + arc.centroid(d) + ")";        //this gives us a pair of coordinates like [50, 50]
    })
    .attr("text-anchor", "middle")                          //center the text on it's origin
    .attr("font-family", "sans-serif")
    .attr("font-size", "11px")
    .attr("fill", "white")
    .text(function(d, i) {
        return totalsData[i].label;
    });        //get the label from our original data array


}
//function toggleDetailsGraphTransactionSummary(graphId){
//
//    if($('spDashboardWidgetTransactionSum_graph' + graphId + 'Details').getStyle('height') == '0px')
//    {
//        $('spDashboardWidgetTransactionSum_Graph' + graphId + 'DetailLink').update("hide details" );
//        d3.select('#spDashboardWidgetTransactionSum_graph' + graphId + 'Details').transition().style("height", "100px");
//    }else{
//        $('spDashboardWidgetTransactionSum_Graph' + graphId + 'DetailLink').update("show details" );
//        d3.select('#spDashboardWidgetTransactionSum_graph' + graphId + 'Details').transition().style("height", "0px");
//    }
//
//}