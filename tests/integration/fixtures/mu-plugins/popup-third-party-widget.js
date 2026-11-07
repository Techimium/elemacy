/**
 * Fixture "third-party addon" widget script. Marks its own element
 * initialized the same way any real Elementor widget handler would — so
 * browser tests can observe whether this specific dependency was actually
 * delivered and executed, not merely that the widget's markup rendered.
 */
(function ($) {
	'use strict';

	var Handler = function ($scope) {
		$scope.find('[data-elemacy-fixture-thirdparty]').attr('data-elemacy-fixture-thirdparty-initialized', 'true');
	};

	$(window).on('elementor/frontend/init', function () {
		elementorFrontend.hooks.addAction('frontend/element_ready/elemacy-fixture-thirdparty.default', Handler);
	});
})(jQuery);
