$(document).ready(function(){
	// http://plugins.learningjquery.com/expander/index.html#options
	$('div.ThankedByBox').expander({slicePoint: 200, expandText: gdn.definition('ExpandText'), userCollapseText: gdn.definition('CollapseText')});
	$('div.ThankedByBox span.details > a:last').addClass('Last');
/*      var setExpander = function() {
            $Expander = $('.Expander');
            $('.Expander').expander({slicePoint: 200, expandText: gdn.definition('ExpandText'), userCollapseText: gdn.definition('CollapseText')});
         };
         setExpander();*/
});



