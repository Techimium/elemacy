/**
 * Editor view for the reusable `elemacy_query` control — a select2 that searches
 * the server live as the user types, instead of rendering a fixed option list.
 *
 * It extends Elementor's base Select2 control view and adds two things, mirroring
 * Elementor Pro's query control:
 *   - a select2 `ajax` transport that asks the server for matches (the control's
 *     `autocomplete` config decides what is searched);
 *   - a value-title lookup on render, so an already-saved value shows its label
 *     even though it was never part of a pre-rendered option list.
 *
 * The action tags must match QueryControlManager::AUTOCOMPLETE_ACTION / TITLES_ACTION.
 */
( function () {
	'use strict';

	var AUTOCOMPLETE_ACTION = 'elemacy_query_autocomplete';
	var TITLES_ACTION = 'elemacy_query_titles';

	function register() {
		var Select2View = elementor.modules.controls.Select2;

		if ( ! Select2View ) {
			return;
		}

		var QueryControlView = Select2View.extend( {
			isTitlesReceived: false,

			getAutocompleteData: function getAutocompleteData() {
				return elementorCommon.helpers.cloneObject( this.model.get( 'autocomplete' ) || {} );
			},

			getSelect2Placeholder: function getSelect2Placeholder() {
				return {
					id: '',
					text: this.model.get( 'placeholder' ) || '',
				};
			},

			getSelect2DefaultOptions: function getSelect2DefaultOptions() {
				var self = this;

				return jQuery.extend( Select2View.prototype.getSelect2DefaultOptions.apply( this, arguments ), {
					ajax: {
						transport: function transport( params, success, failure ) {
							return elementorCommon.ajax.addRequest( AUTOCOMPLETE_ACTION, {
								data: {
									autocomplete: self.getAutocompleteData(),
									q: params.data.q,
								},
								success: success,
								error: failure,
							} );
						},
						data: function data( params ) {
							return { q: params.term };
						},
						cache: true,
					},
					minimumInputLength: 1,
				} );
			},

			getValueTitles: function getValueTitles() {
				var self = this;
				var ids = this.getControlValue();

				if ( ! ids || ( Array.isArray( ids ) && ! ids.length ) ) {
					return;
				}

				if ( ! Array.isArray( ids ) ) {
					ids = [ ids ];
				}

				elementorCommon.ajax.loadObjects( {
					action: TITLES_ACTION,
					ids: ids,
					data: {
						get_titles: self.getAutocompleteData(),
						unique_id: self.cid + TITLES_ACTION,
					},
					before: function before() {
						self.addControlSpinner();
					},
					success: function success( data ) {
						self.isTitlesReceived = true;
						self.model.set( 'options', data );
						self.render();
					},
				} );
			},

			addControlSpinner: function addControlSpinner() {
				this.ui.select.prop( 'disabled', true );
				this.$el.find( '.elementor-control-title' ).after( '<span class="elementor-control-spinner">&nbsp;<i class="eicon-spinner eicon-animation-spin"></i>&nbsp;</span>' );
			},

			onReady: function onReady() {
				if ( ! this.isTitlesReceived ) {
					this.getValueTitles();
				}
			},
		} );

		elementor.addControlView( 'elemacy_query', QueryControlView );
	}

	if ( window.elementor ) {
		register();
	} else {
		jQuery( window ).on( 'elementor:init', register );
	}
}() );
