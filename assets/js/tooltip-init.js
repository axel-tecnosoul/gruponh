(function($) {
	"use strict";
	var tooltip_init = {
		init: function() {
			$("button").tooltip();
			$("a").tooltip();
			$("input").tooltip();
			$("img").tooltip();
      $("td").tooltip();
		}
	};
    tooltip_init.init();
})(jQuery);