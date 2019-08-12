window.GFHelpScoutAdmin = null;

( function( $ ) {

	GFHelpScoutAdmin = function () {

		var self = this;

		self.init = function() {

			self.$customAppKey    = $( '#customAppKey' );
			self.$customAppSecret = $( '#customAppSecret' );
			self.$authButton      = $( '#authButton' );

			self.$customAppKey.on( 'change', self.saveAppKeys );
			self.$customAppSecret.on( 'change', self.saveAppKeys );

			self.handleAuthButton();

		};

		self.saveAppKeys = function() {

			// Disable auth anytime we're checking our keys.
			self.handleAuthButton( false );

			if( ! self.$customAppKey.val() || ! self.$customAppSecret.val() ) {
				return;
			}

			var spinner = new gfAjaxSpinner( self.$authButton, false, 'position:relative;top:6px;left:6px;' );

			$.post( ajaxurl, {
				action:     'gform_helpscout_save_app_keys',
				app_key:    self.$customAppKey.val(),
				app_secret: self.$customAppSecret.val(),
				nonce:      gform_helpscout_plugin_settings_strings.nonce_save
			}, function( response ) {
				spinner.destroy();
				if( response.success ) {
					self.handleAuthButton( response.data.authUrl );
				} else {
					self.handleAuthButton( false );
				}
			} );
		};

		self.handleAuthButton = function( authUrl ) {

			if( authUrl ) {
				self.$authButton.attr( 'href', authUrl );
			} else if( authUrl === false ) {
				self.$authButton.attr( 'href', '' );
			}

			if( self.$authButton.attr( 'href' ) ) {
				self.$authButton.removeClass( 'disabled' );
			} else {
				self.$authButton.addClass( 'disabled' );
			}

		};

		self.init();

	};

	$( document ).ready( GFHelpScoutAdmin );

} )( jQuery );
