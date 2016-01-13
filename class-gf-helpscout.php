<?php

GFForms::include_feed_addon_framework();

class GFHelpScout extends GFFeedAddOn {

	protected $_version = GF_HELPSCOUT_VERSION;
	protected $_min_gravityforms_version = '1.9.14.26';
	protected $_slug = 'gravityformshelpscout';
	protected $_path = 'gravityformshelpscout/helpscout.php';
	protected $_full_path = __FILE__;
	protected $_url = 'http://www.gravityforms.com/';
	protected $_title = 'Gravity Forms Help Scout Add-On';
	protected $_short_title = 'Help Scout';
	protected $_enable_rg_autoupgrade = true;
	protected $_single_feed_submission = true;
	protected $api = null;
	private static $_instance = null;

	/* Permissions */
	protected $_capabilities_settings_page = 'gravityforms_helpscout';
	protected $_capabilities_form_settings = 'gravityforms_helpscout';
	protected $_capabilities_uninstall = 'gravityforms_helpscout_uninstall';

	/* Members plugin integration */
	protected $_capabilities = array( 'gravityforms_helpscout', 'gravityforms_helpscout_uninstall' );

	/**
	 * Get instance of this class.
	 * 
	 * @access public
	 * @static
	 * @return $_instance
	 */
	public static function get_instance() {

		if ( self::$_instance == null ) {
			self::$_instance = new GFHelpScout();
		}

		return self::$_instance;

	}

	/**
	 * Register needed plugin hooks and PayPal delayed payment support.
	 * 
	 * @access public
	 * @return void
	 */
	public function init() {
		
		parent::init();
		
		add_action( 'gform_entry_detail_sidebar_middle', array( $this, 'add_entry_detail_panel' ), 10, 2 );
		
		add_action( 'admin_init', array( $this, 'maybe_create_conversation' ) );
		
		add_filter( 'gform_addnote_button', array( $this, 'add_note_checkbox' ) );
		
		add_action( 'gform_post_note_added', array( $this, 'add_note_to_conversation' ), 10, 6 );
		
		add_filter( 'gform_entries_column_filter', array( $this, 'add_entry_conversation_column_link' ), 10, 5 );
		
		$this->add_delayed_payment_support(
			array(
				'option_label' => esc_html__( 'Create conversation in Help Scout only when payment is received.', 'gravityformshelpscout' )
			)
		);
		
	}

	/**
	 * Setup plugin settings fields.
	 * 
	 * @access public
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
						'feedback_callback' => array( $this, 'initialize_api' )
					),
					array(
						'type'     => 'save',
						'messages' => array(
							'success' => __( 'Help Scout settings have been updated.', 'gravityformshelpscout' )
						),
					),
				),
			),
		);

	}

	/**
	 * Prepare plugin settings description.
	 *
	 * @return string
	 */
	public function plugin_settings_description() {

		$description = '<p>';
		$description .= sprintf(
			__( 'Help Scout makes it easy to provide your customers with a great support experience. Use Gravity Forms to collect customer information and automatically create a new Help Scout conversation. If you don\'t have a Help Scout account, you can %1$s sign up for one here.%2$s', 'gravityformshelpscout' ),
			'<a href="http://www.helpscout.net/" target="_blank">', '</a>'
		);
		$description .= '</p>';

		if ( ! $this->initialize_api() ) {

			$description .= '<p>';
			$description .= __( 'Gravity Forms Help Scout Add-On requires your API Key. You can find your API Key by visiting the API Keys page under Your Profile.', 'gravityformshelpscout' );
			$description .= '</p>';

		}

		return $description;

	}

	/**
	 * Setup fields for feed settings.
	 * 
	 * @access public
	 * @return array
	 */
	public function feed_settings_fields() {

		$general_settings = array(
			'title'  => '',
			'fields' => array(
				array(
					'name'          => 'feed_name',
					'type'          => 'text',
					'required'      => true,
					'class'         => 'medium',
					'label'         => __( 'Name', 'gravityformshelpscout' ),
					'tooltip'       => '<h6>' . __( 'Name', 'gravityformshelpscout' ) . '</h6>' . __( 'Enter a feed name to uniquely identify this setup.', 'gravityformshelpscout' ),
					'default_value' => $this->get_default_feed_name()
				),
				array(
					'name'     => 'mailbox',
					'type'     => 'select',
					'required' => true,
					'choices'  => $this->mailboxes_for_feed_setting(),
					'onchange' => "jQuery(this).parents('form').submit();",
					'label'    => __( 'Destination Mailbox', 'gravityformshelpscout' ),
					'tooltip'  => '<h6>' . __( 'Destination Mailbox', 'gravityformshelpscout' ) . '</h6>' . __( 'Select the Help Scout Mailbox this form entry will be sent to.', 'gravityformshelpscout' )
				),
				array(
					'name'       => 'user',
					'type'       => 'select',
					'dependency' => 'mailbox',
					'choices'    => $this->users_for_feed_settings(),
					'label'      => __( 'Assign To User', 'gravityformshelpscout' ),
					'tooltip'    => '<h6>' . __( 'Assign To User', 'gravityformshelpscout' ) . '</h6>' . __( 'Choose the Help Scout User this form entry will be assigned to.', 'gravityformshelpscout' )
				),
			),
		);


		$message_settings = array(
			'title'      => __( 'Message Details', 'gravityformshelpscout' ),
			'dependency' => 'mailbox',
			'fields'     => array(
				array(
					'name'          => 'customer_email',
					'type'          => 'field_select',
					'required'      => true,
					'label'         => __( 'Customer\'s Email Address', 'gravityformshelpscout' ),
					'default_value' => $this->get_first_field_by_type( 'email' ),
					'args'          => array(
						'input_types' => array( 'email', 'hidden' )
					)
				),
				array(
					'name'          => 'customer_first_name',
					'type'          => 'field_select',
					'label'         => __( 'Customer\'s First Name', 'gravityformshelpscout' ),
					'default_value' => $this->get_first_field_by_type( 'name', 3 ),
				),
				array(
					'name'          => 'customer_last_name',
					'type'          => 'field_select',
					'label'         => __( 'Customer\'s Last Name', 'gravityformshelpscout' ),
					'default_value' => $this->get_first_field_by_type( 'name', 6 ),
				),
				array(
					'name'          => 'tags',
					'type'          => 'text',
					'label'         => __( 'Tags', 'gravityformshelpscout' ),
					'class'         => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
				),
				array(
					'name'          => 'subject',
					'type'          => 'text',
					'required'      => true,
					'class'         => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
					'label'         => __( 'Subject', 'gravityformshelpscout' ),
					'default_value' => 'New submission from {form_title}',
				),
				array(
					'name'          => 'body',
					'type'          => 'textarea',
					'required'      => true,
					'use_editor'    => true,
					'class'         => 'large',
					'label'         => __( 'Message Body', 'gravityformshelpscout' ),
					'default_value' => '{all_fields}'
				),
			)
		);

		$file_fields_for_feed = $this->file_fields_for_feed_setup();

		if ( ! empty ( $file_fields_for_feed ) ) {

			$message_settings['fields'][] = array(
				'name'    => 'attachments',
				'type'    => 'checkbox',
				'label'   => __( 'Attachments', 'gravityformshelpscout' ),
				'choices' => $file_fields_for_feed
			);

		}

		if ( apply_filters( 'gform_helpscout_enable_cc', false ) ) {

			$message_settings['fields'][] = array(
				'name'     => 'cc',
				'type'     => 'text',
				'required' => true,
				'class'    => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
				'label'    => __( 'CC', 'gravityformshelpscout' ),
			);

		}

		if ( apply_filters( 'gform_helpscout_enable_bcc', false ) ) {

			$message_settings['fields'][] = array(
				'name'     => 'bcc',
				'type'     => 'text',
				'required' => true,
				'class'    => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
				'label'    => __( 'BCC', 'gravityformshelpscout' ),
			);

		}

		$option_settings = array(
			'title'      => __( 'Message Options', 'gravityformshelpscout' ),
			'dependency' => 'mailbox',
			'fields'     => array(
				array(
					'name'          => 'status',
					'type'          => 'select',
					'choices'       => $this->status_types_for_feed_setup(),
					'label'         => __( 'Message Status', 'gravityformshelpscout' ),
				),
				array(
					'name'          => 'type',
					'type'          => 'select',
					'choices'       => $this->message_types_for_feed_setup(),
					'label'         => __( 'Message Type', 'gravityformshelpscout' ),
				),
				array(
					'name'          => 'note',
					'type'          => 'textarea',
					'use_editor'    => true,
					'class'         => 'medium',
					'label'         => __( 'Note', 'gravityformshelpscout' ),
				),
				array(
					'name'          => 'auto_reply',
					'type'          => 'checkbox',
					'label'         => __( 'Auto Reply', 'gravityformshelpscout' ),
					'choices'       => array(
						array(
							'name'  => 'auto_reply',
							'label' => __( 'Send Help Scout auto reply when message is created', 'gravityformshelpscout' ),
						)
					)
				),
			)
		);

		$conditional_settings = array(
			'title'      => __( 'Feed Conditional Logic', 'gravityformshelpscout' ),
			'dependency' => 'mailbox',
			'fields'     => array(
				array(
					'name'           => 'feed_ondition',
					'type'           => 'feed_condition',
					'label'          => __( 'Conditional Logic', 'gravityformshelpscout' ),
					'checkbox_label' => __( 'Enable', 'gravityformshelpscout' ),
					'instructions'   => __( 'Export to Help Scout if', 'gravityformshelpscout' ),
					'tooltip'        => '<h6>' . __( 'Conditional Logic', 'gravityformshelpscout' ) . '</h6>' . __( 'When conditional logic is enabled, form submissions will only be exported to Help Scout when the condition is met. When disabled, all form submissions will be posted.', 'gravityformshelpscout' )
				),
			),
		);

		return array( $general_settings, $message_settings, $option_settings, $conditional_settings );

	}

	/**
	 * Prepare Help Scout Mailboxes for feed settings field.
	 * 
	 * @access public
	 * @return array $choices
	 */
	public function mailboxes_for_feed_setting() {

		/* Setup initial choices array. */
		$choices = array(
			array(
				'label' => __( 'Choose A Mailbox', 'gravityformshelpscout' ),
				'value' => ''
			)
		);

		/* If Help Scout instance is not initialized, return choices array. */
		if ( ! $this->initialize_api() ) {
			return $choices;
		}

		/* Get the Help Scout mailboxes. */
		$mailboxes = $this->api->getMailboxes( 99, array( 'id', 'name' ) );

		/* If there are mailboxes, add them to the choices array. */
		if ( $mailboxes ) {

			foreach ( $mailboxes->items as $mailbox ) {

				$choices[] = array(
					'label' => $mailbox->name,
					'value' => $mailbox->id
				);

			}

		}

		return $choices;

	}

	/**
	 * Prepare Help Scout Users for feed settings field.
	 * 
	 * @access public
	 * @return array $choices
	 */
	public function users_for_feed_settings() {

		/* Setup initial choice. */
		$choices = array(
			array(
				'label' => __( 'Do Not Assign', 'gravityformshelpscout' ),
				'value' => ''
			)
		);

		/* If Help Scout instance is not initialized, return choices array. */
		if ( ! $this->initialize_api() ) {
			return $choices;
		}

		/* Get current mailbox value. */
		$feed    = $this->get_current_feed();
		$mailbox = $feed ? $feed['meta']['mailbox'] : rgpost( '_gaddon_setting_mailbox' );

		if ( ! empty( $_POST ) ) {
			$mailbox = rgpost( '_gaddon_setting_mailbox' );
		}

		/* If no mailbox is set, return choices. */
		if ( rgblank( $mailbox ) ) {
			return $choices;
		}

		/* Get the users for mailbox and add to choices array. */
		try {

			$users = $this->api->getUsersForMailbox( $mailbox, 1, array( 'id', 'firstName', 'lastName' ) );

		} catch ( Exception $e ) {

			$this->log_error( __METHOD__ . '(): Failed to set get users for mailbox; ' . $e->getMessage() );

			return $choices;

		}

		if ( $users ) {

			foreach ( $users->items as $user ) {

				$choices[] = array(
					'label' => $user->firstName . ' ' . $user->lastName,
					'value' => $user->id
				);

			}

		}

		return $choices;

	}

	/**
	 * Prepare Help Scout Status Types for feed settings field.
	 * 
	 * @access public
	 * @return array $choices
	 */
	public function status_types_for_feed_setup() {

		return array(
			array(
				'label' => __( 'Active', 'gravityformshelpscout' ),
				'value' => 'active',
			),
			array(
				'label' => __( 'Pending', 'gravityformshelpscout' ),
				'value' => 'pending',
			),
			array(
				'label' => __( 'Closed', 'gravityformshelpscout' ),
				'value' => 'closed',
			),
			array(
				'label' => __( 'Spam', 'gravityformshelpscout' ),
				'value' => 'spam',
			)
		);

	}

	/**
	 * Prepare Help Scout Message Types for feed settings field.
	 * 
	 * @access public
	 * @return array $choices
	 */
	public function message_types_for_feed_setup() {

		return array(
			array(
				'label' => __( 'Email', 'gravityformshelpscout' ),
				'value' => 'email',
			),
			array(
				'label' => __( 'Chat', 'gravityformshelpscout' ),
				'value' => 'chat',
			),
			array(
				'label' => __( 'Phone', 'gravityformshelpscout' ),
				'value' => 'phone',
			),
		);

	}

	/**
	 * Prepare form file fields for feed settings field.
	 * 
	 * @access public
	 * @return array $choices
	 */
	public function file_fields_for_feed_setup() {

		/* Setup choices array. */
		$choices = array();

		/* Get the form. */
		$form = GFAPI::get_form( rgget( 'id' ) );

		/* Get file fields for the form. */
		$file_fields = GFCommon::get_fields_by_type( $form, array( 'fileupload' ) );

		if ( ! empty ( $file_fields ) ) {

			foreach ( $file_fields as $field ) {

				$choices[] = array(
					'name'          => 'attachments[' . $field->id . ']',
					'label'         => $field->label,
					'default_value' => 0,
				);

			}

		}

		return $choices;

	}

	/**
	 * Set feed creation control.
	 *
	 * @access public
	 * @return bool
	 */
	public function can_create_feed() {
		
		return $this->initialize_api();
		
	}

	/**
	 * Enable feed duplication.
	 * 
	 * @access public
	 * @return bool
	 */
	public function can_duplicate_feed( $feed_id ) {
		
		return true;
		
	}

	/**
	 * Configures which columns should be displayed on the feed list page.
	 *
	 * @return array
	 */
	public function feed_list_columns() {

		return array(
			'feed_name' => __( 'Name', 'gravityformshelpscout' ),
			'mailbox'   => __( 'Mailbox', 'gravityformshelpscout' ),
			'user'      => __( 'User', 'gravityformshelpscout' )
		);

	}

	/**
	 * Returns the value to be displayed in the mailbox name column.
	 * 
	 * @access public
	 * @param array $feed The feed being included in the feed list.
	 * @return string
	 */
	public function get_column_value_mailbox( $feed ) {

		/* If Help Scout instance is not initialized, return mailbox ID. */
		if ( ! $this->initialize_api() ) {
			return $feed['meta']['mailbox'];
		}

		try {

			$mailbox = $this->api->getMailbox( $feed['meta']['mailbox'] );

			return $mailbox->getName();

		} catch ( Exception $e ) {

			return $feed['meta']['mailbox'];

		}

	}

	/**
	 * Returns the value to be displayed in the user name column.
	 * 
	 * @access public
	 * @param array $feed The feed being included in the feed list.
	 * @return string
	 */
	public function get_column_value_user( $feed ) {

		/* If no user ID is set, return not assigned. */
		if ( rgblank( $feed['meta']['user'] ) ) {
			return __( 'No User Assigned', 'gravityformshelpscout' );
		}

		/* If Help Scout instance is not initialized, return user ID. */
		if ( ! $this->initialize_api() ) {
			return $feed['meta']['user'];
		}

		try {

			$user = $this->api->getUser( $feed['meta']['user'] );

			return $user->getFirstName() . ' ' . $user->getLastName();

		} catch ( Exception $e ) {

			return $feed['meta']['user'];

		}

	}

	/**
	 * Process feed, create conversation.
	 * 
	 * @access public
	 * @param array $feed The feed object to be processed.
	 * @param array $entry The entry object currently being processed.
	 * @param array $form The form object currently being processed.
	 * @return void
	 */
	public function process_feed( $feed, $entry, $form ) {

		/* If Help Scout instance is not initialized, exit. */
		if ( ! $this->initialize_api() ) {

			$this->log_error( __METHOD__ . '(): Failed to set up the API.' );
			return;

		}

		/* If this entry already has a Help Scout conversation, exit. */
		if ( gform_get_meta( $entry['id'], 'helpscout_conversation_id' ) ) {
			
			$this->log_debug( __METHOD__ . '(): Entry already has a Help Scout conversation associated to it. Skipping processing.' );
			return;
			
		}

		/* Prepare needed information. */
		$data = array(
			'email'       => $this->get_field_value( $form, $entry, $feed['meta']['customer_email'] ),
			'first_name'  => $this->get_field_value( $form, $entry, $feed['meta']['customer_first_name'] ),
			'last_name'   => $this->get_field_value( $form, $entry, $feed['meta']['customer_last_name'] ),
			'subject'     => GFCommon::replace_variables( $feed['meta']['subject'], $form, $entry, false, false, false, 'text' ),
			'body'        => GFCommon::replace_variables( $feed['meta']['body'], $form, $entry ),
			'attachments' => array(),
			'tags'        => GFCommon::replace_variables( $feed['meta']['tags'], $form, $entry )
		);

		/* If the email address is empty, exit. */
		if ( GFCommon::is_invalid_or_empty_email( $data['email'] ) ) {

			$this->log_error( __METHOD__ . "(): Email address must be provided." );
			return false;

		}

		/* Setup the mailbox for this conversation */
		$mailbox = new \HelpScout\model\ref\MailboxRef();
		$mailbox->setId( $feed['meta']['mailbox'] );

		/* Create the customer object */
		$customer = $this->api->getCustomerRefProxy( null, $data['email'] );
		$customer->setFirstName( $data['first_name'] );
		$customer->setLastName( $data['last_name'] );

		/* Create the conversation object */
		$conversation = new \HelpScout\model\Conversation();
		$conversation->setSubject( $data['subject'] );
		$conversation->setMailbox( $mailbox );
		$conversation->setCustomer( $customer );
		$conversation->setCreatedBy( $customer );
		$conversation->setType( $feed['meta']['type'] );

		/* Create the message thread */
		if ( gf_apply_filters( 'gform_helpscout_process_body_shortcodes', $form['id'], false, $form, $feed ) ) {
			$data['body'] = do_shortcode( $data['body'] );
		}
		$thread = new \HelpScout\model\thread\Customer();
		$thread->setCreatedBy( $customer );
		$thread->setBody( $data['body'] );
		$thread->setStatus( $feed['meta']['status'] );

		/* Assign this conversation to user if set */
		if ( ! rgempty( 'user', $feed['meta'] ) ) {

			$user = new \HelpScout\model\ref\PersonRef();
			$user->setId( $feed['meta']['user'] );
			$user->setType( 'user' );
			$thread->setAssignedTo( $user );

		}

		/* If feed has an attachments field assign, process the attachments. */
		if ( ! empty( $feed['meta']['attachments'] ) ) {

			$attachment_fields = array_keys( $feed['meta']['attachments'] );

			$attachment_files = array();

			foreach ( $attachment_fields as $attachment_field ) {

				$field_value = $this->get_field_value( $form, $entry, $attachment_field );
				$field_value = $this->is_json( $field_value ) ? json_decode( $field_value, true ) : $field_value;
				$field_value = strpos( $field_value, ' , ' ) !== FALSE ? explode( ' , ', $field_value ) : $field_value;

				if ( empty( $field_value ) ) {
					continue;
				}

				if ( is_array( $field_value ) ) {

					$attachment_files = array_merge( $attachment_files, $field_value );

				} else {

					$attachment_files[] = $field_value;

				}

			}

			if ( ! empty( $attachment_files ) ) {

				$attachments = $this->process_feed_attachments( $attachment_files );
				$thread->setAttachments( $attachments );

			}

		}

		/* Add tags to conversation */
		$tags = ! empty( $data['tags'] ) ? array_map( 'trim', explode( ',', $data['tags'] ) ) : array();
		$tags = gf_apply_filters( 'gform_helpscout_tags', $form['id'], $tags, $feed, $entry, $form );

		if ( ! empty( $tags ) ) {

			$conversation->setTags( $tags );

		}

		/* Add CC and BCC support if set. */
		if ( isset( $feed['meta']['cc'] ) ) {

			$data['cc'] = GFCommon::replace_variables( $feed['meta']['cc'], $form, $entry );
			$data['cc'] = ( is_array( $data['cc'] ) ) ? $data['cc'] : explode( ',', $data['cc'] );

			if ( ! empty( $data['cc'] ) ) {

				$thread->setCcList( $data['cc'] );

			}

		}

		if ( isset( $feed['meta']['bcc'] ) ) {

			$data['bcc'] = GFCommon::replace_variables( $feed['meta']['bcc'], $form, $entry );
			$data['bcc'] = ( is_array( $data['bcc'] ) ) ? $data['bcc'] : explode( ',', $data['bcc'] );

			if ( ! empty( $data['bcc'] ) ) {

				$thread->setCcList( $data['bcc'] );

			}

		}

		/* Assign the message thread to the conversation */
		$conversation->setThreads( array( $thread ) );

		/* Set thread count to 1 so Help Scout will include the conversation in the mailbox folder count */
		$conversation->setThreadCount( 1 );

		$this->log_debug( __METHOD__ . "(): Conversation to be created => " . print_r( $conversation, true ) );

		try {

			$auto_reply = ( rgars( $feed, 'meta/auto_reply' ) == '1' );

			/* Create the conversation. */
			$this->api->createConversation( $conversation, false, $auto_reply, true );

			gform_update_meta( $entry['id'], 'helpscout_conversation_id', $conversation->getId() );

			/* Log that conversation was created. */
			$this->log_debug( __METHOD__ . "(): Conversation has been created." );

		} catch ( Exception $e ) {

			/* Log that conversation was not created. */
			$this->log_error( __METHOD__ . "(): Conversation was not created; {$e->getMessage()}" );

			return;

		}
		
		/* Add conversation note if set. */
		if ( rgars( $feed, 'meta/note' ) ) {
			
			/* Replace variables for note. */
			$note_text = GFCommon::replace_variables( $feed['meta']['note'], $form, $entry );	
			
			/* Get API user. */
			$api_user = $this->api->getUserMe();
			
			/* Create note object. */
			$note = new \HelpScout\model\thread\Message();
			$note->setCreatedBy( $this->api->getUserRefProxy( $api_user->getId() ) );
			$note->setBody( $note_text );
			$note->setType( 'note' );
			
			try { 
				
				/* Post note to conversation. */
				$this->api->createThread( $conversation->getId(), $note );
				
				/* Log that note was added. */
				$this->log_debug( __METHOD__ . '(): Note was successfully added to conversation.' );
				
			} catch ( Exception $e ) {
				
				/* Log that note was not added. */
				$this->log_error( __METHOD__ . '(): Note was not added to conversation; ' . $e->getMessage() );
				
				return;
				
			}
			
		}

	}

	/**
	 * Process attachments for feed.
	 * 
	 * @access public
	 * @param array $files
	 * @return array $attachments
	 */
	public function process_feed_attachments( $files ) {

		/* Prepare attachments array. */
		$attachments = array();

		/* If Help Scout instance is not initialized, return attachments. */
		if ( ! $this->initialize_api() ) {
			return $attachments;
		}

		/* If there are no files, return. */
		if ( rgblank( $files ) ) {
			return $attachments;
		}

		/* Prepare attachment and add to array */
		foreach ( $files as $file ) {

			/* Get the file name and location of the file */
			$file_name     = basename( $file );
			$file_location = str_replace( WP_CONTENT_URL, WP_CONTENT_DIR, $file );

			/* Get the file's mime type */
			$file_info      = finfo_open( FILEINFO_MIME_TYPE );
			$file_mime_type = finfo_file( $file_info, $file_location );
			finfo_close( $file_info );

			/* Prepare the attachment object. */
			$attachment = new \HelpScout\model\Attachment();
			$attachment->setFileName( $file_name );
			$attachment->setMimeType( $file_mime_type );
			$attachment->setData( file_get_contents( $file_location ) );

			/* Create the attachment. */
			try {

				$this->api->createAttachment( $attachment );

				$attachments[] = $attachment;

			} catch ( Exception $e ) {

				$this->log_error( __METHOD__ . "(): Unable to upload attachment; {$e->getMessage()}" );

			}

		}

		return $attachments;

	}

	/**
	 * Add a panel to the entry view with details about the Help Scout conversation.
	 * 
	 * @access public
	 * @param array $form The form object currently being viewed.
	 * @param array $entry The entry object currently being viewed.
	 * @return void
	 */
	public function add_entry_detail_panel( $form, $entry ) {
		
		/* If the API isn't initialized, exit. */
		if ( ! $this->initialize_api() ) {
			return;
		}
		
		$html  = '<div id="helpscoutdiv" class="stuffbox">';
		$html .= '<h3 class="hndle" style="cursor:default;"><span>' . esc_html__( 'Help Scout Details', 'gravityformshelpscout' ) . '</span></h3>';
		$html .= '<div class="inside">';
		
		/* If no Help Scout conversation was created, add a button to create it. */
		if ( ! rgar( $entry, 'helpscout_conversation_id' ) ) {
			
			if ( ! $this->get_active_feeds() ) {
				return;
			}
			
			$html .= '<a href="' . add_query_arg( 'gf_helpscout', 'process' ) . '" class="button">' . esc_html__( 'Create Conversation', 'gravityformshelpscout' ) . '</a>';
		}
		
		if ( rgar( $entry, 'helpscout_conversation_id' ) ) {
			
			try {
				$conversation = $this->api->getConversation( rgar( $entry, 'helpscout_conversation_id' ) );
			} catch ( Exception $e ) {
				gform_delete_meta( $entry['id'], 'helpscout_conversation_id' );
				$this->log_error( __METHOD__ . '(): Could not get Help Scout conversation; ' . $e->getMessage() );
				return;
			}
			
			$html .= esc_html__( 'Conversation Id', 'gravityformshelpscout' ) . ': <a href="https://secure.helpscout.net/conversation/' . $conversation->getId() . '/' . $conversation->getNumber() . '/" target="_blank">' . $conversation->getId() . '</a><br /><br />';
			$html .= esc_html__( 'Status', 'gravityformshelpscout' ) . ': ' . ucwords( $conversation->getStatus() ) . '<br /><br />';
			$html .= esc_html__( 'Created At', 'gravityformshelpscout' ) . ': ' . GFCommon::format_Date( $conversation->getCreatedAt(), false, 'Y/m/d', true ) . '<br /><br />';
			$html .= esc_html__( 'Last Updated At', 'gravityformshelpscout' ) . ': ' . GFCommon::format_Date( $conversation->getModifiedAt(), false, 'Y/m/d', true ) . '<br /><br />';
			
		}
		
		$html .= '</div>';		
		$html .= '</div>';
		
		echo $html;
		
	}

	/**
	 * Create Help Scout creation on the entry view page.
	 * 
	 * @access public
	 * @return void
	 */
	public function maybe_create_conversation() {
		
		/* If we're not on the entry view page, exit. */
		if ( rgget( 'page' ) !== 'gf_entries' || rgget( 'view' ) !== 'entry' || rgget( 'gf_helpscout' ) !== 'process' ) {
			return;
		}
		
		/* Get the current form and entry. */
		$form  = GFAPI::get_form( rgget( 'id' ) );
		$entry = $this->get_current_entry();
		
		/* If a Help Scout conversation ID exists for this entry, exit. */
		if ( rgar( $entry, 'helpscout_conversation_id' ) ) {
			return;
		}
		
		/* Process feeds. */
		$this->maybe_process_feed( $entry, $form );
		
	}

	/**
	 * Insert "Add Note to Help Scout Conversation" checkbox to add note form.
	 * 
	 * @access public
	 * @param string $note_button
	 * @return string $note_button
	 */
	public function add_note_checkbox( $note_button ) {
		
		$entry = $this->get_current_entry();
		
		/* If API is not initialized or entry does not have a Help Scout conversation ID, return existing note button. */
		if ( ! $this->initialize_api() || is_wp_error( $entry ) || ! rgar( $entry, 'helpscout_conversation_id' ) ) {
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
	 * @access public
	 * @param int $note_id - The ID of the created note.
	 * @param int $entry_id - The ID of the entry the note belongs to.
	 * @param int $user_id - The ID of the user who created the note.
	 * @param string $user_name - The name of the user who created the note.
	 * @param string $note - The note contents.
	 * @param string $note_type - The note type.
	 * @return void
	 */
	public function add_note_to_conversation( $note_id, $entry_id, $user_id, $user_name, $note, $note_type ) {
		
		/* If Add Note checkbox not selected, exit. */
		if ( rgpost( 'helpscout_add_note' ) != '1' ) {
			return;
		}
		
		/* Get the entry. */
		$entry = GFAPI::get_entry( $entry_id );
		
		/* If API is not initialized or entry does not have a Help Scout conversation ID, exit. */
		if ( ! $this->initialize_api() || ! rgar( $entry, 'helpscout_conversation_id' ) ) {
			return;
		}
				
		/* Get API user. */
		$api_user = $this->api->getUserMe();
		
		/* Create note object. */
		$hs_note = new \HelpScout\model\thread\Message();
		$hs_note->setCreatedBy( $this->api->getUserRefProxy( $api_user->getId() ) );
		$hs_note->setBody( $note );
		$hs_note->setType( 'note' );
		
		try { 
			
			/* Post note to conversation. */
			$this->api->createThread( rgar( $entry, 'helpscout_conversation_id' ), $hs_note );
			
			/* Log that note was added. */
			$this->log_debug( __METHOD__ . '(): Note was successfully added to conversation.' );
			
		} catch ( Exception $e ) {
			
			/* Log that note was not added. */
			$this->log_error( __METHOD__ . '(): Note was not added to conversation; ' . $e->getMessage() );
			
		}
		
	}

	/**
	 * Initializes Help Scout API if API credentials are valid.
	 * 
	 * @access public
	 * @return bool|null
	 */
	public function initialize_api() {

		if ( ! is_null( $this->api ) ) {
			return true;
		}

		/* Load the API library. */
		if ( ! class_exists( 'HelpScout\ApiClient' ) ) {
			require_once 'includes/api/ApiClient.php';
		}

		/* Get the API Key. */
		$api_key = $this->get_plugin_setting( 'api_key' );

		/* If the API Key is empty, do not run a validation check. */
		if ( rgblank( $api_key ) ) {
			return null;
		}

		$this->log_debug( __METHOD__ . "(): Validating login for API Info for {$api_key}." );

		/* Setup a new Help Scout object with the API credentials. */
		$help_scout = HelpScout\ApiClient::getInstance();
		$help_scout->setKey( $api_key );

		try {

			/* Make a test request. */
			$help_scout->getMailboxes();

			/* Assign API object to class. */
			$this->api = $help_scout;

			/* Log that test passed. */
			$this->log_debug( __METHOD__ . '(): API credentials are valid.' );

			return true;

		} catch ( Exception $e ) {

			/* Log that test failed. */
			$this->log_error( __METHOD__ . '(): API credentials are invalid; ' . $e->getMessage() );

			return false;

		}

	}

	/**
	 * Add the conversation ID entry meta property.
	 *
	 * @param array $entry_meta An array of entry meta already registered with the gform_entry_meta filter.
	 * @param int $form_id The form id
	 *
	 * @return array The filtered entry meta array.
	 */
	public function get_entry_meta( $entry_meta, $form_id ) {
		$entry_meta['helpscout_conversation_id'] = array(
			'label'             => __( 'Help Scout Conversation ID', 'gravityformshelpscout' ),
			'is_numeric'        => true,
			'is_default_column' => false
		);

		return $entry_meta;
	}

	/**
	 * Helper function to get current entry.
	 * 
	 * @access public
	 * @return array $entry
	 */
	public function get_current_entry() {
		
		/* Get the current entry. */
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

	/**
	 * Add Help Scout conversation link to entry list column.
	 * 
	 * @access public
	 * @param string $value - Current value that will be displayed in this cell
	 * @param int $form_id - ID of the current form
	 * @param int $field_id - ID of the field that this column applies to
	 * @param array $entry - Current entry object
	 * @param string $query_string - Current page query string with search and pagination state
	 * @return string
	 */
	public function add_entry_conversation_column_link( $value, $form_id, $field_id, $entry, $query_string ) {
		
		/* If this is not the Help Scout Conversation ID column, return value. */
		if ( $field_id !== 'helpscout_conversation_id' ) {
			return $value;
		}
		
		/* If API is not initialized or entry does not have a Help Scout conversation ID, return value. */
		if ( ! $this->initialize_api() || ! rgar( $entry, 'helpscout_conversation_id' ) ) {
			return $value;
		}
		
		/* Get the conversation. */
		try {
			$conversation = $this->api->getConversation( rgar( $entry, 'helpscout_conversation_id' ) );
		} catch ( Exception $e ) {
			$this->log_error( __METHOD__ . '(): Could not get Help Scout conversation; ' . $e->getMessage() );
			return $value;
		}
		
		return '<a href="https://secure.helpscout.net/conversation/' . $conversation->getId() . '/' . $conversation->getNumber() . '/" target="_blank">' . $conversation->getId() . '</a>';
		
	}

}
