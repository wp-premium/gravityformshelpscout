<?php

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

GFForms::include_feed_addon_framework();

/**
 * Gravity Forms Help Scout Add-On.
 *
 * @since     1.0
 * @package   GravityForms
 * @author    Rocketgenius
 * @copyright Copyright (c) 2016, Rocketgenius
 */
class GFHelpScout extends GFFeedAddOn {

	/**
	 * Contains an instance of this class, if available.
	 *
	 * @since  1.0
	 * @access private
	 * @var    object $_instance If available, contains an instance of this class.
	 */
	private static $_instance = null;

	/**
	 * Defines the version of the Help Scout Add-On.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_version Contains the version, defined from helpscout.php
	 */
	protected $_version = GF_HELPSCOUT_VERSION;

	/**
	 * Defines the minimum Gravity Forms version required.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_min_gravityforms_version The minimum version required.
	 */
	protected $_min_gravityforms_version = '1.9.14.26';

	/**
	 * Defines the plugin slug.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_slug The slug used for this plugin.
	 */
	protected $_slug = 'gravityformshelpscout';

	/**
	 * Defines the main plugin file.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_path The path to the main plugin file, relative to the plugins folder.
	 */
	protected $_path = 'gravityformshelpscout/helpscout.php';

	/**
	 * Defines the full path to this class file.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_full_path The full path.
	 */
	protected $_full_path = __FILE__;

	/**
	 * Defines the URL where this Add-On can be found.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string The URL of the Add-On.
	 */
	protected $_url = 'http://www.gravityforms.com/';

	/**
	 * Defines the title of this Add-On.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_title The title of the Add-On.
	 */
	protected $_title = 'Gravity Forms Help Scout Add-On';

	/**
	 * Defines the short title of the Add-On.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_short_title The short title.
	 */
	protected $_short_title = 'Help Scout';

	/**
	 * Defines if Add-On should use Gravity Forms servers for update data.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    bool
	 */
	protected $_enable_rg_autoupgrade = true;

	/**
	 * Defines if only the first matching feed will be processed.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    bool
	 */
	protected $_single_feed_submission = true;

	/**
	 * Defines the capability needed to access the Add-On settings page.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_capabilities_settings_page The capability needed to access the Add-On settings page.
	 */
	protected $_capabilities_settings_page = 'gravityforms_helpscout';

	/**
	 * Defines the capability needed to access the Add-On form settings page.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_capabilities_form_settings The capability needed to access the Add-On form settings page.
	 */
	protected $_capabilities_form_settings = 'gravityforms_helpscout';

	/**
	 * Defines the capability needed to uninstall the Add-On.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_capabilities_uninstall The capability needed to uninstall the Add-On.
	 */
	protected $_capabilities_uninstall = 'gravityforms_helpscout_uninstall';

	/**
	 * Defines the capabilities needed for the Help Scout Add-On
	 *
	 * @since  1.0
	 * @access protected
	 * @var    array $_capabilities The capabilities needed for the Add-On
	 */
	protected $_capabilities = array( 'gravityforms_helpscout', 'gravityforms_helpscout_uninstall' );

	/**
	 * Contains an instance of the Help Scout API library, if available.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    object $api If available, contains an instance of the Help Scout API library.
	 */
	protected $api = null;

	/**
	 * Get instance of this class.
	 *
	 * @since  1.0
	 * @access public
	 * @static
	 *
	 * @return $_instance
	 */
	public static function get_instance() {

		if ( null === self::$_instance ) {
			self::$_instance = new GFHelpScout();
		}

		return self::$_instance;

	}

	/**
	 * Register needed plugin hooks and PayPal delayed payment support.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @uses GFAddOn::is_gravityforms_supported()
	 * @uses GFFeedAddOn::add_delayed_payment_support()
	 */
	public function init() {

		parent::init();

		if ( $this->is_gravityforms_supported( '2.0-beta-3' ) ) {
			add_filter( 'gform_entry_detail_meta_boxes', array( $this, 'register_meta_box' ), 10, 3 );
		} else {
			add_action( 'gform_entry_detail_sidebar_middle', array( $this, 'add_entry_detail_panel' ), 10, 2 );
		}

		add_action( 'admin_init', array( $this, 'maybe_create_conversation' ) );

		add_filter( 'gform_addnote_button', array( $this, 'add_note_checkbox' ) );

		add_action( 'gform_post_note_added', array( $this, 'add_note_to_conversation' ), 10, 6 );

		add_filter( 'gform_entries_column_filter', array( $this, 'add_entry_conversation_column_link' ), 10, 5 );

		add_filter( 'gform_entry_list_bulk_actions', array( $this, 'add_bulk_action' ), 10, 2 );
		add_action( 'gform_entry_list_action_helpscout', array( $this, 'process_bulk_action' ), 10, 3 );

		$this->add_delayed_payment_support(
			array(
				'option_label' => esc_html__( 'Create conversation in Help Scout only when payment is received.', 'gravityformshelpscout' ),
			)
		);

	}





	// # PLUGIN SETTINGS -----------------------------------------------------------------------------------------------

	/**
	 * Setup plugin settings fields.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @uses GFHelpScout::plugin_settings_description()
	 *
	 * @return array
	 */
	public function plugin_settings_fields() {

		return array(
			array(
				'title'       => '',
				'description' => $this->plugin_settings_description(),
				'fields'      => array(
					array(
						'name'              => 'api_key',
						'label'             => __( 'API Key', 'gravityformshelpscout' ),
						'type'              => 'text',
						'class'             => 'medium',
						'feedback_callback' => array( $this, 'initialize_api' ),
					),
					array(
						'type'     => 'save',
						'messages' => array(
							'success' => __( 'Help Scout settings have been updated.', 'gravityformshelpscout' ),
						),
					),
				),
			),
		);

	}

	/**
	 * Prepare plugin settings description.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @uses GFHelpScout::initialize_api()
	 *
	 * @return string
	 */
	public function plugin_settings_description() {

		// Prepare description.
		$description = sprintf(
			'<p>%s</p>',
			sprintf(
				esc_html__( 'Help Scout makes it easy to provide your customers with a great support experience. Use Gravity Forms to collect customer information and automatically create a new Help Scout conversation. If you don\'t have a Help Scout account, you can %1$ssign up for one here.%2$s', 'gravityformshelpscout' ),
				'<a href="http://www.helpscout.net/" target="_blank">', '</a>'
			)
		);

		// Add API key location instructions.
		if ( ! $this->initialize_api() ) {

			$description .= sprintf(
				'<p>%s</p>',
				esc_html__( 'Gravity Forms Help Scout Add-On requires your API Key. You can find your API Key by visiting the API Keys page under Your Profile.', 'gravityformshelpscout' )
			);

		}

		return $description;

	}





	// # FEED SETTINGS -------------------------------------------------------------------------------------------------

	/**
	 * Setup fields for feed settings.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @uses GFAddOn::add_field_after()
	 * @uses GFAddOn::get_first_field_by_type()
	 * @uses GFFeedAddOn::get_default_feed_name()
	 * @uses GFHelpScout::file_fields_for_feed_setup()
	 * @uses GFHelpScout::mailboxes_for_feed_setting()
	 * @uses GFHelpScout::message_types_for_feed_setup()
	 * @uses GFHelpScout::status_types_for_feed_setup()
	 * @uses GFHelpScout::users_for_feed_settings()
	 *
	 * @return array
	 */
	public function feed_settings_fields() {

		$settings = array(
			array(
				'fields' => array(
					array(
						'name'          => 'feed_name',
						'type'          => 'text',
						'required'      => true,
						'class'         => 'medium',
						'label'         => esc_html__( 'Name', 'gravityformshelpscout' ),
						'default_value' => $this->get_default_feed_name(),
						'tooltip'       => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Name', 'gravityformshelpscout' ),
							esc_html__( 'Enter a feed name to uniquely identify this setup.', 'gravityformshelpscout' )
						),
					),
					array(
						'name'          => 'mailbox',
						'type'          => 'select',
						'required'      => true,
						'choices'       => $this->mailboxes_for_feed_setting(),
						'onchange'      => "jQuery(this).parents('form').submit();",
						'label'         => esc_html__( 'Destination Mailbox', 'gravityformshelpscout' ),
						'tooltip'       => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Destination Mailbox', 'gravityformshelpscout' ),
							esc_html__( 'Select the Help Scout Mailbox this form entry will be sent to.', 'gravityformshelpscout' )
						),
					),
					array(
						'name'          => 'user',
						'type'          => 'select',
						'dependency'    => 'mailbox',
						'choices'       => $this->users_for_feed_settings(),
						'label'         => esc_html__( 'Assign To User', 'gravityformshelpscout' ),
						'tooltip'       => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Assign To User', 'gravityformshelpscout' ),
							esc_html__( 'Choose the Help Scout User this form entry will be assigned to.', 'gravityformshelpscout' )
						),
					),
				),
			),
			array(
				'title'      => esc_html__( 'Customer Details', 'gravityformshelpscout' ),
				'dependency' => 'mailbox',
				'fields'     => array(
					array(
						'name'          => 'customer_email',
						'type'          => 'field_select',
						'required'      => true,
						'label'         => esc_html__( 'Email Address', 'gravityformshelpscout' ),
						'default_value' => $this->get_first_field_by_type( 'email' ),
						'args'          => array( 'input_types' => array( 'email', 'hidden' ) ),
					),
					array(
						'name'          => 'customer_first_name',
						'type'          => 'field_select',
						'label'         => esc_html__( 'First Name', 'gravityformshelpscout' ),
						'default_value' => $this->get_first_field_by_type( 'name', 3 ),
					),
					array(
						'name'          => 'customer_last_name',
						'type'          => 'field_select',
						'label'         => esc_html__( 'Last Name', 'gravityformshelpscout' ),
						'default_value' => $this->get_first_field_by_type( 'name', 6 ),
					),
					array(
						'name'          => 'customer_phone',
						'type'          => 'field_select',
						'required'      => false,
						'label'         => esc_html__( 'Phone Number', 'gravityformshelpscout' ),
						'default_value' => $this->get_first_field_by_type( 'phone' ),
						'args'          => array( 'input_types' => array( 'phone', 'hidden' ) ),
					),
				),
			),
			array(
				'title'      => esc_html__( 'Message Details', 'gravityformshelpscout' ),
				'dependency' => 'mailbox',
				'fields'     => array(
					array(
						'name'          => 'tags',
						'type'          => 'text',
						'label'         => esc_html__( 'Tags', 'gravityformshelpscout' ),
						'class'         => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
					),
					array(
						'name'          => 'subject',
						'type'          => 'text',
						'required'      => true,
						'class'         => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
						'label'         => esc_html__( 'Subject', 'gravityformshelpscout' ),
						'default_value' => 'New submission from {form_title}',
					),
					array(
						'name'          => 'body',
						'type'          => 'textarea',
						'required'      => true,
						'use_editor'    => true,
						'class'         => 'large',
						'label'         => esc_html__( 'Message Body', 'gravityformshelpscout' ),
						'default_value' => '{all_fields}',
					),
				),
			),
			array(
				'title'      => esc_html__( 'Message Options', 'gravityformshelpscout' ),
				'dependency' => 'mailbox',
				'fields'     => array(
					array(
						'name'          => 'status',
						'type'          => 'select',
						'choices'       => $this->status_types_for_feed_setup(),
						'label'         => esc_html__( 'Message Status', 'gravityformshelpscout' ),
					),
					array(
						'name'          => 'type',
						'type'          => 'select',
						'choices'       => $this->message_types_for_feed_setup(),
						'label'         => esc_html__( 'Message Type', 'gravityformshelpscout' ),
					),
					array(
						'name'          => 'note',
						'type'          => 'textarea',
						'use_editor'    => true,
						'class'         => 'medium',
						'label'         => esc_html__( 'Note', 'gravityformshelpscout' ),
					),
					array(
						'name'          => 'auto_reply',
						'type'          => 'checkbox',
						'label'         => esc_html__( 'Auto Reply', 'gravityformshelpscout' ),
						'choices'       => array(
							array(
								'name'  => 'auto_reply',
								'label' => esc_html__( 'Send Help Scout auto reply when message is created', 'gravityformshelpscout' ),
							),
						),
					),
				),
			),
			array(
				'title'      => esc_html__( 'Feed Conditional Logic', 'gravityformshelpscout' ),
				'dependency' => 'mailbox',
				'fields'     => array(
					array(
						'name'           => 'feed_ondition',
						'type'           => 'feed_condition',
						'label'          => esc_html__( 'Conditional Logic', 'gravityformshelpscout' ),
						'checkbox_label' => esc_html__( 'Enable', 'gravityformshelpscout' ),
						'instructions'   => esc_html__( 'Export to Help Scout if', 'gravityformshelpscout' ),
						'tooltip'        => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Conditional Logic', 'gravityformshelpscout' ),
							esc_html__( 'When conditional logic is enabled, form submissions will only be exported to Help Scout when the condition is met. When disabled, all form submissions will be posted.', 'gravityformshelpscout' )
						),
					),
				),
			),
		);

		// Get available file fields.
		$file_fields = $this->file_fields_for_feed_setup();

		// If file fields are available, add feed setting.
		if ( ! empty( $file_fields ) ) {

			// Prepare field.
			$field = array(
				'name'    => 'attachments',
				'type'    => 'checkbox',
				'label'   => esc_html__( 'Attachments', 'gravityformshelpscout' ),
				'choices' => $file_fields,
			);

			// Add field.
			$settings = $this->add_field_after( 'body', $field, $settings );

		}

		/**
		 * Enable the display of the CC setting on the Help Scout feed.
		 *
		 * @since  1.0
		 *
		 * @param bool $enable_cc Display CC setting.
		 */
		$enable_cc = apply_filters( 'gform_helpscout_enable_cc', true );

		// If CC field is enabled, add feed setting.
		if ( $enable_cc ) {

			// Prepare field.
			$field = array(
				'name'     => 'cc',
				'type'     => 'text',
				'required' => false,
				'class'    => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
				'label'    => esc_html__( 'CC', 'gravityformshelpscout' ),
			);

			// Add field.
			$settings = $this->add_field_after( empty( $file_fields ) ? 'body' : 'attachments', $field, $settings );

		}

		/**
		 * Enable the display of the BCC setting on the Help Scout feed.
		 *
		 * @since  1.0
		 *
		 * @param bool $enable_bcc Display BCC setting.
		 */
		$enable_bcc = apply_filters( 'gform_helpscout_enable_bcc', false );

		// If BCC field is enabled, add feed setting.
		if ( $enable_bcc ) {

			// Prepare field.
			$field = array(
				'name'     => 'bcc',
				'type'     => 'text',
				'required' => false,
				'class'    => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
				'label'    => esc_html__( 'BCC', 'gravityformshelpscout' ),
			);

			// Add field.
			$settings = $this->add_field_after( $enable_cc ? 'cc' : ( empty( $file_fields ) ? 'body' : 'attachments' ), $field, $settings );

		}

		return $settings;

	}

	/**
	 * Prepare Help Scout Mailboxes for feed settings field.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @uses GFAddOn::log_error()
	 * @uses GFHelpScout::initialize_api()
	 *
	 * @return array
	 */
	public function mailboxes_for_feed_setting() {

		// Initialize choices array.
		$choices = array(
			array(
				'label' => __( 'Choose A Mailbox', 'gravityformshelpscout' ),
				'value' => '',
			),
		);

		// If Help Scout instance is not initialized, return choices.
		if ( ! $this->initialize_api() ) {
			return $choices;
		}

		try {

			// Get the Help Scout mailboxes.
			$mailboxes = $this->api->getMailboxes( 99, array( 'id', 'name' ) );

		} catch ( Exception $e ) {

			// Log that mailboxes could not be retrieved.
			$this->log_error( __METHOD__ . '(): Failed to get mailboxes; ' . $e->getMessage() );

			return $choices;

		}

		// If there are no mailboxes, return.
		if ( ! $mailboxes ) {
			return $choices;
		}

		// Loop through mailboxes.
		foreach ( $mailboxes->items as $mailbox ) {

			// Add mailbox as choice.
			$choices[] = array(
				'label' => $mailbox->name,
				'value' => $mailbox->id,
			);

		}

		return $choices;

	}

	/**
	 * Prepare Help Scout Users for feed settings field.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @uses GFAddOn::get_setting()
	 * @uses GFAddOn::log_error()
	 * @uses GFHelpScout::initialize_api()
	 *
	 * @return array
	 */
	public function users_for_feed_settings() {

		// Initialize choices array.
		$choices = array(
			array(
				'label' => __( 'Do Not Assign', 'gravityformshelpscout' ),
				'value' => '',
			),
		);

		// If Help Scout instance is not initialized, return choices.
		if ( ! $this->initialize_api() ) {
			return $choices;
		}

		// Get current mailbox value.
		$mailbox = $this->get_setting( 'mailbox' );

		// If no mailbox is set, return choices.
		if ( rgblank( $mailbox ) ) {
			return $choices;
		}

		try {

			// Get users for mailbox.
			$users = $this->api->getUsersForMailbox( $mailbox, 1, array( 'id', 'firstName', 'lastName' ) );

		} catch ( Exception $e ) {

			// Log that users could not be retrieved.
			$this->log_error( __METHOD__ . '(): Failed to get users for mailbox; ' . $e->getMessage() );

			return $choices;

		}

		// If no users were found, return.
		if ( ! $users ) {
			return $choices;
		}

		// Loop through users.
		foreach ( $users->items as $user ) {

			// Add user as choice.
			$choices[] = array(
				'label' => $user->firstName . ' ' . $user->lastName,
				'value' => $user->id,
			);

		}

		return $choices;

	}

	/**
	 * Prepare Help Scout Status Types for feed settings field.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @return array
	 */
	public function status_types_for_feed_setup() {

		return array(
			array(
				'label' => esc_html__( 'Active', 'gravityformshelpscout' ),
				'value' => 'active',
			),
			array(
				'label' => esc_html__( 'Pending', 'gravityformshelpscout' ),
				'value' => 'pending',
			),
			array(
				'label' => esc_html__( 'Closed', 'gravityformshelpscout' ),
				'value' => 'closed',
			),
			array(
				'label' => esc_html__( 'Spam', 'gravityformshelpscout' ),
				'value' => 'spam',
			),
		);

	}

	/**
	 * Prepare Help Scout Message Types for feed settings field.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @return array
	 */
	public function message_types_for_feed_setup() {

		return array(
			array(
				'label' => esc_html__( 'Email', 'gravityformshelpscout' ),
				'value' => 'email',
			),
			array(
				'label' => esc_html__( 'Chat', 'gravityformshelpscout' ),
				'value' => 'chat',
			),
			array(
				'label' => esc_html__( 'Phone', 'gravityformshelpscout' ),
				'value' => 'phone',
			),
		);

	}

	/**
	 * Prepare form file fields for feed settings field.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @uses GFAPI::get_form()
	 * @uses GFCommon::get_fields_by_type()
	 *
	 * @return array
	 */
	public function file_fields_for_feed_setup() {

		// Initialize choices array.
		$choices = array();

		// Get current form.
		$form = GFAPI::get_form( rgget( 'id' ) );

		// Get file fields for form.
		$file_fields = GFCommon::get_fields_by_type( $form, array( 'fileupload' ) );

		// If no file fields were found, return.
		if ( empty( $file_fields ) ) {
			return $choices;
		}

		// Loop through file fields.
		foreach ( $file_fields as $field ) {

			// Add field as choice.
			$choices[] = array(
				'name'          => 'attachments[' . $field->id . ']',
				'label'         => $field->label,
				'default_value' => 0,
			);

		}

		return $choices;

	}





	// # FEED LIST -----------------------------------------------------------------------------------------------------

	/**
	 * Set feed creation control.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @uses GFHelpScout::initialize_api()
	 *
	 * @return bool
	 */
	public function can_create_feed() {

		return $this->initialize_api();

	}

	/**
	 * Enable feed duplication.
	 *
	 * @since  1.3
	 * @access public
	 *
	 * @param int $feed_id Feed to be duplicated.
	 *
	 * @return bool
	 */
	public function can_duplicate_feed( $feed_id ) {

		return true;

	}

	/**
	 * Configures which columns should be displayed on the feed list page.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @return array
	 */
	public function feed_list_columns() {

		return array(
			'feed_name' => esc_html__( 'Name', 'gravityformshelpscout' ),
			'mailbox'   => esc_html__( 'Mailbox', 'gravityformshelpscout' ),
			'user'      => esc_html__( 'User', 'gravityformshelpscout' ),
		);

	}

	/**
	 * Returns the value to be displayed in the mailbox name column.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param array $feed The current Feed object.
	 *
	 * @uses GFAddOn::log_error()
	 * @uses GFHelpScout::initialize_api()
	 *
	 * @return string
	 */
	public function get_column_value_mailbox( $feed ) {

		// If Help Scout instance is not initialized, return mailbox ID.
		if ( ! $this->initialize_api() ) {
			return rgars( $feed, 'meta/mailbox' );
		}

		try {

			// Get feed mailbox.
			$mailbox = $this->api->getMailbox( rgars( $feed, 'meta/mailbox' ) );

			return esc_html( $mailbox->getName() );

		} catch ( Exception $e ) {

			// Log that mailbox could not be retrieved.
			$this->log_error( __METHOD__ . '(): Unable to get mailbox for feed; ' . $e->getMessage() );

			return rgars( $feed, 'meta/mailbox' );

		}

	}

	/**
	 * Returns the value to be displayed in the user name column.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param array $feed The current Feed object.
	 *
	 * @uses GFAddOn::log_error()
	 * @uses GFHelpScout::initialize_api()
	 *
	 * @return string
	 */
	public function get_column_value_user( $feed ) {

		// If no user ID is set, return not assigned.
		if ( rgblank( $feed['meta']['user'] ) ) {
			return esc_html__( 'No User Assigned', 'gravityformshelpscout' );
		}

		// If Help Scout instance is not initialized, return user ID.
		if ( ! $this->initialize_api() ) {
			return rgars( $feed, 'meta/user' );
		}

		try {

			// Get user for feed.
			$user = $this->api->getUser( rgars( $feed, 'meta/user' ) );

			return esc_html( $user->getFirstName() . ' ' . $user->getLastName() );

		} catch ( Exception $e ) {

			// Log that user could not be retrieved.
			$this->log_error( __METHOD__ . '(): Unable to get user for feed; ' . $e->getMessage() );

			return rgars( $feed, 'meta/user' );

		}

	}





	// # FEED PROCESSING -----------------------------------------------------------------------------------------------

	/**
	 * Process feed, create conversation.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param array $feed  The current Feed object.
	 * @param array $entry The current Entry object.
	 * @param array $form  The current Form object.
	 *
	 * @uses GFAddOn::get_field_value()
	 * @uses GFAddOn::is_json()
	 * @uses GFAddOn::log_debug()
	 * @uses GFCommon::is_invalid_or_empty_email()
	 * @uses GFCommon::replace_variables()
	 * @uses GFFeedAddOn::add_feed_error()
	 * @uses GFHelpScout::initialize_api()
	 */
	public function process_feed( $feed, $entry, $form ) {

		// If Help Scout instance is not initialized, exit.
		if ( ! $this->initialize_api() ) {
			$this->add_feed_error( esc_html__( 'Unable to create conversation because API was not initialized.', 'gravityformshelpscout' ), $feed, $entry, $form );
			return;
		}

		// If this entry already has a Help Scout conversation, exit.
		if ( gform_get_meta( $entry['id'], 'helpscout_conversation_id' ) ) {
			$this->log_debug( __METHOD__ . '(): Entry already has a Help Scout conversation associated to it. Skipping processing.' );
			return;
		}

		// Prepare conversation data.
		$data = array(
			'email'       => $this->get_field_value( $form, $entry, $feed['meta']['customer_email'] ),
			'first_name'  => $this->get_field_value( $form, $entry, $feed['meta']['customer_first_name'] ),
			'last_name'   => $this->get_field_value( $form, $entry, $feed['meta']['customer_last_name'] ),
			'phone'       => $this->get_field_value( $form, $entry, $feed['meta']['customer_phone'] ),
			'subject'     => GFCommon::replace_variables( $feed['meta']['subject'], $form, $entry, false, false, false, 'text' ),
			'body'        => GFCommon::replace_variables( $feed['meta']['body'], $form, $entry ),
			'attachments' => array(),
			'tags'        => GFCommon::replace_variables( $feed['meta']['tags'], $form, $entry ),
		);

		// If the email address is invalid, exit.
		if ( GFCommon::is_invalid_or_empty_email( $data['email'] ) ) {
			$this->add_feed_error( esc_html__( 'Unable to create conversation because a valid email address was not provided.', 'gravityformshelpscout' ), $feed, $entry, $form );
			return false;
		}

		// Loop through first and last name fields.
		foreach ( array( 'first_name', 'last_name' ) as $field_to_check ) {

			// If field value is longer than 40 characters, truncate.
			if ( strlen( $data[ $field_to_check ] ) > 40 ) {

				// Log that we are truncating field value.
				$this->log_debug( __METHOD__ . "(): Truncating $field_to_check field value because it is longer than maximum length allowed." );

				// Truncate value.
				$data[ $field_to_check ] = substr( $data[ $field_to_check ], 0, 40 );

			}

		}

		// Initialize mailbox object for conversation.
		$mailbox = new \HelpScout\model\ref\MailboxRef();
		$mailbox->setId( $feed['meta']['mailbox'] );

		// Initialize customer object.
		$customer = $this->api->getCustomerRefProxy( null, $data['email'] );
		$customer->setFirstName( $data['first_name'] );
		$customer->setLastName( $data['last_name'] );
		$customer->setPhone( $data['phone'] );

		// Initialize conversation object.
		$conversation = new \HelpScout\model\Conversation();
		$conversation->setSubject( $data['subject'] );
		$conversation->setMailbox( $mailbox );
		$conversation->setCustomer( $customer );
		$conversation->setCreatedBy( $customer );
		$conversation->setType( $feed['meta']['type'] );

		// If enabled, Process shortcodes for conversation body.
		if ( gf_apply_filters( 'gform_helpscout_process_body_shortcodes', $form['id'], false, $form, $feed ) ) {
			$data['body'] = do_shortcode( $data['body'] );
		}

		// Initialize message thread object.
		$thread = new \HelpScout\model\thread\Customer();
		$thread->setCreatedBy( $customer );
		$thread->setBody( $data['body'] );
		$thread->setStatus( $feed['meta']['status'] );

		// If defined, Assign this conversation to Help Scout user.
		if ( ! rgempty( 'user', $feed['meta'] ) ) {
			$user = new \HelpScout\model\ref\PersonRef();
			$user->setId( $feed['meta']['user'] );
			$user->setType( 'user' );
			$thread->setAssignedTo( $user );
		}

		// If feed has an attachments field assigned, process attachments.
		if ( ! empty( $feed['meta']['attachments'] ) ) {

			// Get attachment field IDs.
			$attachment_fields = array_keys( $feed['meta']['attachments'] );

			// Initialize attachment files array.
			$attachment_files = array();

			// Loop through attachment fields.
			foreach ( $attachment_fields as $attachment_field ) {

				// Get field value.
				$field_value = $this->get_field_value( $form, $entry, $attachment_field );
				$field_value = $this->is_json( $field_value ) ? json_decode( $field_value, true ) : $field_value;
				$field_value = strpos( $field_value, ' , ' ) !== false ? explode( ' , ', $field_value ) : $field_value;

				// If no field value is set, skip field.
				if ( empty( $field_value ) ) {
					continue;
				}

				// Add field value to attachment files array.
				if ( is_array( $field_value ) ) {
					$attachment_files = array_merge( $attachment_files, $field_value );
				} else {
					$attachment_files[] = $field_value;
				}

			}

			// If attachment files were found, add them to conversation thread.
			if ( ! empty( $attachment_files ) ) {

				// Prepare attachment file objects.
				$attachments = $this->process_feed_attachments( $attachment_files, $feed, $entry, $form );

				// Add attachments to conversation thread.
				$thread->setAttachments( $attachments );

			}

		}

		// Prepare conversation tags.
		$tags = ! empty( $data['tags'] ) ? array_map( 'trim', explode( ',', $data['tags'] ) ) : array();
		$tags = gf_apply_filters( array( 'gform_helpscout_tags', $form['id'] ), $tags, $feed, $entry, $form );

		// If tags are set, add them to conversation.
		if ( ! empty( $tags ) ) {
			$conversation->setTags( $tags );
		}

		// If defined, add CC email addresses.
		if ( rgars( $feed, 'meta/cc' ) ) {

			// Get CC email addresses.
			$data['cc'] = GFCommon::replace_variables( $feed['meta']['cc'], $form, $entry );
			$data['cc'] = ( is_array( $data['cc'] ) ) ? $data['cc'] : explode( ',', $data['cc'] );
			$data['cc'] = array_filter( $data['cc'] );

			// If CC email addresses are set, add them to conversation thread.
			if ( ! empty( $data['cc'] ) ) {
				$thread->setCcList( $data['cc'] );
			}

		}

		// If defined, add BCC email addresses.
		if ( rgars( $feed, 'meta/bcc' ) ) {

			// Get BCC email addresses.
			$data['bcc'] = GFCommon::replace_variables( $feed['meta']['bcc'], $form, $entry );
			$data['bcc'] = ( is_array( $data['bcc'] ) ) ? $data['bcc'] : explode( ',', $data['bcc'] );
			$data['bcc'] = array_filter( $data['bcc'] );

			// If BCC email addresses are set, add them to conversation thread.
			if ( ! empty( $data['bcc'] ) ) {
				$thread->setBccList( $data['bcc'] );
			}

		}

		// Assign the message thread to the conversation object.
		$conversation->setThreads( array( $thread ) );

		// Set thread count to 1 so Help Scout will include the conversation in the mailbox folder count.
		$conversation->setThreadCount( 1 );

		// Log the conversation to be created.
		$this->log_debug( __METHOD__ . '(): Conversation to be created => ' . print_r( $conversation, true ) );

		try {

			// Set the auto reply flag.
			$auto_reply = ( rgars( $feed, 'meta/auto_reply' ) == '1' );

			// Create the conversation.
			$this->api->createConversation( $conversation, false, $auto_reply, true );

			// Add conversation ID to entry meta.
			gform_update_meta( $entry['id'], 'helpscout_conversation_id', $conversation->getId() );

			// Log that conversation was created.
			$this->log_debug( __METHOD__ . '(): Conversation has been created.' );

		} catch ( Exception $e ) {

			// Log that conversation was not created.
			$this->add_feed_error( 'Conversation was not created; ' . $e->getMessage(), $feed, $entry, $form );

			return;

		}

		// If enabled, add conversation note.
		if ( rgars( $feed, 'meta/note' ) ) {

			// Prepare note contents.
			$note_text = GFCommon::replace_variables( $feed['meta']['note'], $form, $entry );

			if ( gf_apply_filters( 'gform_helpscout_process_note_shortcodes', $form['id'], false, $form, $feed ) ) {
				$note_text = do_shortcode( $note_text );
			}

			if ( empty( $note_text ) ) {
				return;
			}

			// Get API user.
			$api_user = $this->api->getUserMe();

			// Initialize note object.
			$note = new \HelpScout\model\thread\Message();
			$note->setCreatedBy( $this->api->getUserRefProxy( $api_user->getId() ) );
			$note->setBody( $note_text );
			$note->setType( 'note' );

			try {

				// Post note to conversation.
				$this->api->createThread( $conversation->getId(), $note );

				// Log that note was added.
				$this->log_debug( __METHOD__ . '(): Note was successfully added to conversation.' );

			} catch ( Exception $e ) {

				// Log that note was not added.
				$this->add_feed_error( 'Note was not added to conversation; ' . $e->getMessage(), $feed, $entry, $form );

				return;

			}

		}

	}

	/**
	 * Process attachments for feed.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param array $files File paths to convert to Help Scout attachments.
	 * @param array $feed  The current Feed object.
	 * @param array $entry The current Entry object.
	 * @param array $form  The current Form object.
	 *
	 * @uses GFFeedAddOn::add_feed_error()
	 * @uses GFHelpScout::initialize_api()
	 *
	 * @return array
	 */
	public function process_feed_attachments( $files, $feed, $entry, $form ) {

		// Initialize attachments array.
		$attachments = array();

		// If Help Scout instance is not initialized or no files are ready for conversion, return attachments.
		if ( ! $this->initialize_api() || rgblank( $files ) ) {
			return $attachments;
		}

		// Loop through files.
		foreach ( $files as $file ) {

			// Get the file name and path.
			$file_name     = basename( $file );
			$file_path = str_replace( WP_CONTENT_URL, WP_CONTENT_DIR, $file );

			// Get the file's mime type.
			$file_info      = finfo_open( FILEINFO_MIME_TYPE );
			$file_mime_type = finfo_file( $file_info, $file_path );
			finfo_close( $file_info );

			// Initialize attachment object.
			$attachment = new \HelpScout\model\Attachment();
			$attachment->setFileName( $file_name );
			$attachment->setMimeType( $file_mime_type );
			$attachment->setData( file_get_contents( $file_path ) );

			try {

				// Create the attachment.
				$this->api->createAttachment( $attachment );

				// Add attachment to attachments array.
				$attachments[] = $attachment;

			} catch ( Exception $e ) {

				// Log that attachment could not be created.
				$this->add_feed_error( 'Unable to upload attachment; ' . $e->getMessage(), $feed, $entry, $form );

			}

		}

		return $attachments;

	}





	// # ENTRY DETAILS -------------------------------------------------------------------------------------------------

	/**
	 * Add Create Conversation to entry list bulk actions.
	 *
	 * @since  1.4.2
	 * @access public
	 *
	 * @param array $actions Bulk actions.
	 * @param int   $form_id The current form ID.
	 *
	 * @return array
	 */
	public function add_bulk_action( $actions = array(), $form_id = '' ) {

		// Add action.
		$actions['helpscout'] = esc_html__( 'Create Help Scout Conversation', 'gravityformshelpscout' );

		return $actions;

	}

	/**
	 * Process Help Scout entry list bulk actions.
	 *
	 * @since  1.4.2
	 * @access public
	 *
	 * @param string $action  Action being performed.
	 * @param array  $entries The entry IDs the action is being applied to.
	 * @param int    $form_id The current form ID.
	 *
	 * @uses GFAPI::get_entry()
	 * @uses GFAPI::get_form()
	 * @uses GFFeedAddOn::maybe_process_feed()
	 * @uses GFHelpScout::get_entry_conversation_id()
	 */
	public function process_bulk_action( $action = '', $entries = array(), $form_id = '' ) {

		// If no entries are being processed, return.
		if ( empty( $entries ) ) {
			return;
		}

		// Get the current form.
		$form = GFAPI::get_form( $form_id );

		// Loop through entries.
		foreach ( $entries as $entry_id ) {

			// Get the entry.
			$entry = GFAPI::get_entry( $entry_id );

			// If a Help Scout conversation ID exists for this entry, skip.
			if ( $this->get_entry_conversation_id( $entry ) ) {
				continue;
			}

			// Process feeds.
			$this->maybe_process_feed( $entry, $form );

		}

	}





	// # ENTRY DETAILS -------------------------------------------------------------------------------------------------

	/**
	 * Add the Help Scout details meta box to the entry detail page.
	 *
	 * @since  1.3.1
	 * @access public
	 *
	 * @param array $meta_boxes The properties for the meta boxes.
	 * @param array $entry      The entry currently being viewed/edited.
	 * @param array $form       The form object used to process the current entry.
	 *
	 * @uses GFFeedAddOn::get_active_feeds()
	 * @uses GFHelpScout::initialize_api()
	 *
	 * @return array
	 */
	public function register_meta_box( $meta_boxes, $entry, $form ) {

		if ( $this->get_active_feeds( $form['id'] ) && $this->initialize_api() ) {
			$meta_boxes[ $this->_slug ] = array(
				'title'    => esc_html__( 'Help Scout Details', 'gravityformshelpscout' ),
				'callback' => array( $this, 'add_details_meta_box' ),
				'context'  => 'side',
			);
		}

		return $meta_boxes;

	}

	/**
	 * The callback used to echo the content to the meta box.
	 *
	 * @since  1.3.1
	 * @access public
	 *
	 * @param array $args An array containing the form and entry objects.
	 *
	 * @uses GFHelpScout::get_panel_markup()
	 */
	public function add_details_meta_box( $args ) {

		echo $this->get_panel_markup( $args['form'], $args['entry'] );

	}

	/**
	 * Generate the markup for use in the meta box.
	 *
	 * @since  1.3.1
	 * @access public
	 *
	 * @param array $form  The current Form object.
	 * @param array $entry The current Entry object.
	 *
	 * @uses GFAddOn::log_error()
	 * @uses GFCommon::format_date()
	 * @uses GFHelpScout::get_entry_conversation_id()
	 *
	 * @return string
	 */
	public function get_panel_markup( $form, $entry ) {

		// Initialize HTML string.
		$html = '';

		// Get conversation ID.
		$conversation_id = $this->get_entry_conversation_id( $entry );

		// If a Help Scout conversation exists, display conversation details.
		if ( $conversation_id ) {

			try {

				// Get conversation.
				$conversation = $this->api->getConversation( $conversation_id );

			} catch ( Exception $e ) {

				// Delete conversation ID from entry.
				gform_delete_meta( $entry['id'], 'helpscout_conversation_id' );

				// Log that conversation could not be retrieved.
				$this->log_error( __METHOD__ . '(): Could not get Help Scout conversation; ' . $e->getMessage() );

				return '';
			}

			$html .= esc_html__( 'Conversation ID', 'gravityformshelpscout' ) . ': <a href="https://secure.helpscout.net/conversation/' . $conversation->getId() . '/' . $conversation->getNumber() . '/" target="_blank">' . $conversation->getId() . '</a><br /><br />';
			$html .= esc_html__( 'Status', 'gravityformshelpscout' ) . ': ' . ucwords( $conversation->getStatus() ) . '<br /><br />';
			$html .= esc_html__( 'Created At', 'gravityformshelpscout' ) . ': ' . GFCommon::format_date( $conversation->getCreatedAt(), false, 'Y/m/d', true ) . '<br /><br />';
			$html .= esc_html__( 'Last Updated At', 'gravityformshelpscout' ) . ': ' . GFCommon::format_date( $conversation->getModifiedAt(), false, 'Y/m/d', true ) . '<br /><br />';

		} else {

			// Get create conversation URL.
			$url = add_query_arg( array( 'gf_helpscout' => 'process', 'lid' => $entry['id'] ) );

			// Display create conversation button.
			$html .= '<a href="' . esc_url( $url ) . '" class="button">' . esc_html__( 'Create Conversation', 'gravityformshelpscout' ) . '</a>';

		}

		return $html;

	}

	/**
	 * Add a panel to the entry view with details about the Help Scout conversation.
	 *
	 * @since  1.3
	 * @access public
	 *
	 * @param array $form  The current Form object.
	 * @param array $entry The current Entry object.
	 *
	 * @uses GFFeedAddOn::get_active_feeds()
	 * @uses GFHelpScout::get_panel_markup()
	 * @uses GFHelpScout::initialize_api()
	 *
	 * @return string
	 */
	public function add_entry_detail_panel( $form, $entry ) {

		// If the API isn't initialized, return.
		if ( ! $this->get_active_feeds( $form['id'] ) || ! $this->initialize_api() ) {
			return;
		}

		$html  = '<div id="helpscoutdiv" class="stuffbox">';
		$html .= '<h3 class="hndle" style="cursor:default;"><span>' . esc_html__( 'Help Scout Details', 'gravityformshelpscout' ) . '</span></h3>';
		$html .= '<div class="inside">';
		$html .= $this->get_panel_markup( $form, $entry );
		$html .= '</div>';
		$html .= '</div>';

		echo $html;

	}

	/**
	 * Create Help Scout creation on the entry view page.
	 *
	 * @since  1.3
	 * @access public
	 *
	 * @uses GFAddOn::get_current_entry()
	 * @uses GFAPI::get_form()
	 * @uses GFFeedAddOn::maybe_process_feed()
	 * @uses GFHelpScout::get_entry_conversation_id()
	 */
	public function maybe_create_conversation() {

		// If we're not on the entry view page, return.
		if ( rgget( 'page' ) !== 'gf_entries' || rgget( 'view' ) !== 'entry' || rgget( 'gf_helpscout' ) !== 'process' ) {
			return;
		}

		// Get the current form and entry.
		$form  = GFAPI::get_form( rgget( 'id' ) );
		$entry = $this->get_current_entry();

		// If a Help Scout conversation ID exists for this entry, return.
		if ( $this->get_entry_conversation_id( $entry ) ) {
			return;
		}

		// Process feeds.
		$this->maybe_process_feed( $entry, $form );

	}

	/**
	 * Insert "Add Note to Help Scout Conversation" checkbox to add note form.
	 *
	 * @since  1.3
	 * @access public
	 *
	 * @param string $note_button Add note button.
	 *
	 * @uses GFAddOn::get_current_entry()
	 * @uses GFHelpScout::get_entry_conversation_id()
	 * @uses GFHelpScout::initialize_api()
	 *
	 * @return string $note_button
	 */
	public function add_note_checkbox( $note_button ) {

		// Get current entry.
		$entry = $this->get_current_entry();

		// If API is not initialized or entry does not have a Help Scout conversation ID, return existing note button.
		if ( ! $this->initialize_api() || is_wp_error( $entry ) || ! $this->get_entry_conversation_id( $entry ) ) {
			return $note_button;
		}

		$note_button .= '<span style="float:right;line-height:28px;">';
		$note_button .= '<input type="checkbox" name="helpscout_add_note" value="1" id="gform_helpscout_add_note" style="margin-top:0;" ' . checked( rgpost( 'helpscout_add_note' ), '1', false ) . ' /> ';
		$note_button .= '<label for="gform_helpscout_add_note">' . esc_html__( 'Add Note to Help Scout Conversation', 'gravityformshelpscout' ) . '</label>';
		$note_button .= '</span>';

		return $note_button;

	}

	/**
	 * Add note to Help Scout conversation.
	 *
	 * @since  1.3
	 * @access public
	 *
	 * @param int    $note_id   The ID of the created note.
	 * @param int    $entry_id  The ID of the entry the note belongs to.
	 * @param int    $user_id   The ID of the user who created the note.
	 * @param string $user_name The name of the user who created the note.
	 * @param string $note      The note contents.
	 * @param string $note_type The note type.
	 *
	 * @uses GFAddOn::log_debug()
	 * @uses GFAddOn::log_error()
	 * @uses GFAPI::get_entry()
	 * @uses GFHelpScout::get_entry_conversation_id()
	 * @uses GFHelpScout::initialize_api()
	 */
	public function add_note_to_conversation( $note_id, $entry_id, $user_id, $user_name, $note, $note_type ) {

		// If add note checkbox not selected, return.
		if ( rgpost( 'helpscout_add_note' ) !== '1' ) {
			return;
		}

		// Get entry.
		$entry = GFAPI::get_entry( $entry_id );

		// Get conversation ID.
		$conversation_id = $this->get_entry_conversation_id( $entry );

		// If API is not initialized or entry does not have a Help Scout conversation ID, exit.
		if ( ! $this->initialize_api() || ! $conversation_id ) {
			return;
		}

		// Get API user.
		$api_user = $this->api->getUserMe();

		// Initialize note object.
		$hs_note = new \HelpScout\model\thread\Message();
		$hs_note->setCreatedBy( $this->api->getUserRefProxy( $api_user->getId() ) );
		$hs_note->setBody( $note );
		$hs_note->setType( 'note' );

		try {

			// Post note to conversation.
			$this->api->createThread( $conversation_id, $hs_note );

			// Log that note was added.
			$this->log_debug( __METHOD__ . '(): Note was successfully added to conversation.' );

		} catch ( Exception $e ) {

			// Log that note was not added.
			$this->log_error( __METHOD__ . '(): Note was not added to conversation; ' . $e->getMessage() );

		}

	}





	// # HELPER METHODS ------------------------------------------------------------------------------------------------

	/**
	 * Initializes Help Scout API if API credentials are valid.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @uses GFAddOn::get_plugin_setting()
	 * @uses GFAddOn::log_debug()
	 * @uses GFAddOn::log_error()
	 *
	 * @return bool|null
	 */
	public function initialize_api() {

		// If API library is already loaded, return.
		if ( ! is_null( $this->api ) ) {
			return true;
		}

		// Load the API library.
		if ( ! class_exists( 'Curl' ) ) {
			require_once 'includes/curl/curl.php';
		}

		// Load the API library.
		if ( ! class_exists( 'HelpScout\ApiClient' ) ) {
			require_once 'includes/api/src/HelpScout/ApiClient.php';
		}

		// Get the API Key.
		$api_key = $this->get_plugin_setting( 'api_key' );

		// If the API Key is empty, do not run a validation check.
		if ( rgblank( $api_key ) ) {
			return null;
		}

		// Log that we're validating API credentials.
		$this->log_debug( __METHOD__ . '(): Validating API credentials.' );

		// Initialize a Help Scout object with the API credentials.
		$help_scout = HelpScout\ApiClient::getInstance();
		$help_scout->setKey( $api_key );

		try {

			// Make a test request.
			$help_scout->getMailboxes();

			// Assign API object to class.
			$this->api = $help_scout;

			// Log that test passed.
			$this->log_debug( __METHOD__ . '(): API credentials are valid.' );

			return true;

		} catch ( Exception $e ) {

			// Log that test failed.
			$this->log_error( __METHOD__ . '(): API credentials are invalid; ' . $e->getMessage() );

			return false;

		}

	}

	/**
	 * Add the conversation ID entry meta property.
	 *
	 * @since  1.3
	 * @access public
	 * @param  array $entry_meta An array of entry meta already registered with the gform_entry_meta filter.
	 * @param  int   $form_id The form id.
	 *
	 * @return array The filtered entry meta array.
	 */
	public function get_entry_meta( $entry_meta, $form_id ) {

		$entry_meta['helpscout_conversation_id'] = array(
			'label'             => __( 'Help Scout Conversation ID', 'gravityformshelpscout' ),
			'is_numeric'        => true,
			'is_default_column' => false,
		);

		return $entry_meta;

	}

	/**
	 * Helper function to get current entry.
	 *
	 * @since  1.3.1
	 * @access public
	 *
	 * @uses GFAddOn::is_gravityforms_supported()
	 * @uses GFAPI::get_entries()
	 * @uses GFAPI::get_entry()
	 * @uses GFCommon::get_base_path()
	 * @uses GFEntryDetail::get_current_entry()
	 *
	 * @return array $entry
	 */
	public function get_current_entry() {

		if ( $this->is_gravityforms_supported( '2.0-beta-3' ) ) {

			if ( ! class_exists( 'GFEntryDetail' ) ) {
				require_once( GFCommon::get_base_path() . '/entry_detail.php' );
			}

			return GFEntryDetail::get_current_entry();

		} else {

			$entry_id = rgpost( 'entry_id' ) ? absint( rgpost( 'entry_id' ) ) : absint( rgget( 'lid' ) );

			if ( $entry_id > 0 ) {

				return GFAPI::get_entry( $entry_id );

			} else {

				$position = rgget( 'pos' ) ? rgget( 'pos' ) : 0;
				$paging   = array( 'offset' => $position, 'page_size' => 1 );
				$entries  = GFAPI::get_entries( rgget( 'id' ), array(), null, $paging );

				return $entries[0];

			}

		}

	}

	/**
	 * Add Help Scout conversation link to entry list column.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param string $value Current value that will be displayed in this cell.
	 * @param int    $form_id ID of the current form.
	 * @param int    $field_id ID of the field that this column applies to.
	 * @param array  $entry Current entry object.
	 * @param string $query_string Current page query string with search and pagination state.
	 *
	 * @uses GFAddOn::log_error()
	 * @uses GFHelpScout::get_entry_conversation_id()
	 * @uses GFHelpScout::initialize_api()
	 *
	 * @return string
	 */
	public function add_entry_conversation_column_link( $value, $form_id, $field_id, $entry, $query_string ) {

		// If this is not the Help Scout Conversation ID column, return value.
		if ( 'helpscout_conversation_id' !== $field_id ) {
			return $value;
		}

		// Get conversation ID.
		$conversation_id = $this->get_entry_conversation_id( $entry );

		// If API is not initialized or entry does not have a Help Scout conversation ID, return value.
		if ( ! $this->initialize_api() || ! $conversation_id ) {
			return $value;
		}

		try {

			// Get the conversation.
			$conversation = $this->api->getConversation( $conversation_id );

		} catch ( Exception $e ) {

			// Log that conversation could not be retrieved.
			$this->log_error( __METHOD__ . '(): Could not get Help Scout conversation; ' . $e->getMessage() );

			return $value;

		}

		return '<a href="https://secure.helpscout.net/conversation/' . $conversation->getId() . '/' . $conversation->getNumber() . '/" target="_blank">' . $conversation->getId() . '</a>';

	}

	/**
	 * Retrieve the conversation id for the current entry.
	 *
	 * @since  1.3.1
	 * @access public
	 *
	 * @param array $entry The entry currently being viewed/edited.
	 *
	 * @return string
	 */
	public function get_entry_conversation_id( $entry ) {

		// Define entry meta key.
		$key = 'helpscout_conversation_id';

		// Get conversation ID.
		$id = rgar( $entry, $key );

		if ( empty( $id ) && rgget( 'gf_helpscout' ) === 'process' ) {
			$id = gform_get_meta( $entry['id'], $key );
		}

		return $id;

	}

}
