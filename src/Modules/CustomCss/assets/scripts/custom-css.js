(function($) {
    /**
     * Elemacy Custom CSS Live Preview
     * 
     * Mirrors Elementor Pro's approach by hooking into the 'editor/style/styleText' filter.
     * This ensures the custom CSS is included in Elementor's core style generation for the preview.
     */
    const ElemacyCustomCssModule = {
        addCustomCss(css, view) {
            if (!view || !view.model) {
                return css;
            }

            const model = view.model;
            const settings = model.get('settings');
            
            if (!settings) {
                return css;
            }

            const customCSS = settings.get('elemacy_custom_css');
            
            if (!customCSS) {
                return css;
            }

            let selector = '.elementor-element.elementor-element-' + model.get('id');
            
            if ('document' === model.get('elType')) {
                selector = elementor.config.document.settings.cssWrapperSelector || 
                           elementor.config.document.settings.css_wrapper_selector || 
                           '.elementor-page-' + elementor.config.document.id;
            }

            const processedCss = customCSS.replace(/selector/g, selector);
            
            return css + processedCss;
        },

        init() {
            elementor.hooks.addFilter('editor/style/styleText', this.addCustomCss.bind(this));
        }
    };

    $(window).on('elementor:init', () => {
        ElemacyCustomCssModule.init();
    });

    // Run immediately if already initialized
    if (window.elementor && window.elementor.initialized) {
        ElemacyCustomCssModule.init();
    }
})(jQuery);
