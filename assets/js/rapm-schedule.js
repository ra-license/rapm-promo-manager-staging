/**
 * Shared scheduling engine for every RAPM display mode. Every asset is
 * always rendered in the page HTML with its schedule as data attributes
 * (data-rapm-start / data-rapm-end) — deliberately not filtered server-side
 * by the current time. Under a full-page cache plugin (WP Rocket etc.),
 * server-rendered HTML is only generated once and then served statically
 * for hours, so a server-side time check would "freeze" whichever assets
 * happened to be active when the cache was built. This module decides
 * what's actually active in the visitor's own browser instead, which
 * works correctly no matter how the HTML itself is cached — this is the
 * exact fix NovaSlider already proved out in production.
 */
( function ( window ) {
	'use strict';

	function isActive( el ) {
		var start = el.getAttribute( 'data-rapm-start' );
		var end   = el.getAttribute( 'data-rapm-end' );
		var now   = new Date();
		if ( start && now < new Date( start ) ) { return false; }
		if ( end && now > new Date( end ) ) { return false; }
		return true;
	}

	/**
	 * @param {string} selector      CSS selector for the .swiper root element.
	 * @param {object} options
	 * @param {number} options.loopMinSlides  Minimum active slides before Swiper loop mode is enabled.
	 * @param {string} options.effect         Swiper effect ('slide'|'fade').
	 * @param {boolean} options.autoplay
	 * @param {number} options.autoplaySpeed
	 * @param {string} options.nav            'both'|'arrows'|'dots'|'none'.
	 */
	function init( selector, options ) {
		var root = document.querySelector( selector + '[data-rapm-carousel]' );
		if ( ! root ) { return; }

		var wrapper    = root.querySelector( '.swiper-wrapper' );
		var allSlides  = Array.prototype.slice.call( wrapper.querySelectorAll( '.swiper-slide' ) );
		var instance   = null;

		function build() {
			allSlides.forEach( function ( s ) {
				if ( s.parentNode === wrapper ) { wrapper.removeChild( s ); }
			} );
			var active = allSlides.filter( isActive );

			if ( 0 === active.length ) {
				root.style.display = 'none';
				return;
			}
			active.forEach( function ( s ) { wrapper.appendChild( s ); } );
			root.style.display = '';

			if ( instance ) {
				instance.destroy( true, true );
				instance = null;
			}

			if ( typeof window.Swiper === 'undefined' ) {
				return; // Not loaded yet (e.g. delayed by a JS optimizer) — slides stay visible as a static stack.
			}

			var config = {
				loop:   active.length >= ( options.loopMinSlides || 2 ),
				effect: options.effect || 'slide',
			};
			if ( 'fade' === config.effect ) {
				config.fadeEffect = { crossFade: true };
			}
			if ( options.autoplay ) {
				config.autoplay = { delay: options.autoplaySpeed || 7000, disableOnInteraction: false, pauseOnMouseEnter: true };
			}
			if ( 'both' === options.nav || 'arrows' === options.nav ) {
				config.navigation = {
					nextEl: selector + ' .swiper-button-next',
					prevEl: selector + ' .swiper-button-prev',
				};
			}
			if ( 'both' === options.nav || 'dots' === options.nav ) {
				config.pagination = { el: selector + ' .swiper-pagination', clickable: true };
			}

			instance = new window.Swiper( selector, config );
		}

		build();

		// Re-check periodically so a scheduled asset can appear or
		// disappear on a page a visitor left open — or one served straight
		// from a full-page cache — without a reload.
		setInterval( function () {
			var stillActive   = allSlides.filter( isActive );
			var currentlyShown = wrapper.querySelectorAll( '.swiper-slide' ).length;
			var changed = stillActive.length !== currentlyShown ||
				stillActive.some( function ( s ) { return s.parentNode !== wrapper; } );
			if ( changed ) { build(); }
		}, 60000 );
	}

	window.RAPM_Schedule = { init: init, isActive: isActive };
} )( window );
