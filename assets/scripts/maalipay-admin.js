jQuery( function( $ ) {
	'use strict';

	/**
	 * Object to handle MaaliPay admin functions.
	 */
	var wc_maalicard_admin = {
		isTestMode: function() {
			return $( '#woocommerce_maalicard_payment_testmode' ).is( ':checked' );
		},

		/**
		 * Initialize.
		 */
		init: function() {
			$( document.body ).on( 'change', '#woocommerce_maalicard_payment_testmode', function() {
				var test_api_username = $( '#woocommerce_maalicard_payment_sandbox_api_username' ).parents( 'tr' ).eq( 0 ),
					test_api_password = $( '#woocommerce_maalicard_payment_sandbox_api_password' ).parents( 'tr' ).eq( 0 ),
					test_api_signature = $( '#woocommerce_maalicard_payment_sandbox_api_signature' ).parents( 'tr' ).eq( 0 ),
					live_api_username = $( '#woocommerce_maalicard_payment_api_username' ).parents( 'tr' ).eq( 0 ),
					live_api_password = $( '#woocommerce_maalicard_payment_api_password' ).parents( 'tr' ).eq( 0 ),
					live_api_signature = $( '#woocommerce_maalicard_payment_api_signature' ).parents( 'tr' ).eq( 0 );

				if ( $( this ).is( ':checked' ) ) {
					test_api_username.show();
					test_api_password.show();
					test_api_signature.show();
					live_api_username.hide();
					live_api_password.hide();
					live_api_signature.hide();
				} else {
					test_api_username.hide();
					test_api_password.hide();
					test_api_signature.hide();
					live_api_username.show();
					live_api_password.show();
					live_api_signature.show();
				}
			} );

			$( '#woocommerce_maalicard_payment_testmode' ).change();
		}
	};

	wc_maalicard_admin.init();
});
