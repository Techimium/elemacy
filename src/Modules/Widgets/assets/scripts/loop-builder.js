(function($) {
    'use strict';

    /**
     * Loop Builder Frontend Script
     * Initialize any JS functionality needed for the loop builder.
     */
    var WidgetLoopBuilderHandler = function($scope, $) {
        var $loopGrid = $scope.find('.elemacy-loop-grid');
        
        if (!$loopGrid.length) {
            return;
        }

        // Potential initialization of Masonry or AJAX pagination here
        // For now, it leverages CSS Grid and standard pagination.
    };

    $(window).on('elementor/frontend/init', function() {
        elementorFrontend.hooks.addAction('frontend/element_ready/elemacy-loop-builder.default', WidgetLoopBuilderHandler);
    });
})(jQuery);
