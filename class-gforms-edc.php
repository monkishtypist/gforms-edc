<?php

GFForms::include_addon_framework();

class GFEdcAddOn extends GFAddOn {

	protected $_version = GF_EDC_ADDON_VERSION;
	protected $_min_gravityforms_version = '1.9';
	protected $_slug = 'gforms-edc';
	protected $_path = 'gforms-edc/gforms-edc.php';
	protected $_full_path = __FILE__;
	protected $_title = 'Gravity Forms EDC Add-On';
	protected $_short_title = 'EDC';

	// public $is_duplicate = false;

	protected $_mandrill_host = 'smtp.mandrillapp.com';
	protected $_mandrill_port = 587;

	private static $_instance = null;

	// public $_mandrill;
	// public $_madrill_api_key;

	/**
	 * Get an instance of this class.
	 *
	 * @return GFEdcAddOn
	 */
	public static function get_instance() {
		if ( self::$_instance == null ) {
			self::$_instance = new GFEdcAddOn();
		}

		return self::$_instance;
	}

	/**
	 * Handles hooks and loading of language files.
	 */
	public function init() {
		parent::init();
		
		add_filter( 'gform_entry_meta', array( $this, 'filter_entry_meta' ), 10, 2);
		add_filter( 'gform_tooltips', array( $this, 'filter_tooltips' ), 10, 1 );
		add_filter( 'gform_merge_tag_filter', array( $this, 'filter_all_fields' ), 10, 5 );
		add_filter( 'gform_confirmation_anchor', create_function( "","return false;" ) );
		// add_filter( 'gform_pre_render', array( $this, 'filter_pre_render') );
		// add_filter( 'gform_submit_button', array( $this, 'filter_form_submit_button' ), 10, 2 );
		add_filter( 'gform_export_fields', array( $this, 'filter_gform_export_fields' ) );
		add_filter( 'gform_export_field_value', array( $this, 'filter_gform_export_field_value' ), 10, 4 );

		add_action( 'gform_post_paging', array( $this, 'action_post_paging' ), 10, 3 );
		add_action( 'gform_pre_submission', array( $this, 'action_pre_submission' ), 10, 1 );
		// add_action( 'gform_entry_created', array( $this, 'action_entry_created' ), 10, 2 );
		add_action( 'gform_after_submission', array( $this, 'action_after_submission' ), 10, 2 );
		add_action( 'gform_field_advanced_settings', array( $this, 'action_field_advanced_settings' ), 10, 1 );
		add_action( 'gform_editor_js', array( $this, 'action_editor_js' ), 10 );
		add_action( 'gform_enqueue_scripts', array( $this, 'action_enqueue_scripts' ), 10, 2 );
	}

	// # SCRIPTS & STYLES -----------------------------------------------------------------------------------------------

	/**
	 * Return the scripts which should be enqueued.
	 *
	 * @return array
	 */
	public function scripts() {
		$scripts = array(
			array(
				'handle'	=> 'scripts_js',
				'src'		 => $this->get_base_url() . '/js/scripts.js',
				'version' => $this->_version,
				'deps'		=> array( 'jquery' ),
				/*'strings' => array(
					'first'	=> esc_html__( 'First Choice', 'gforms-edc' ),
					'second' => esc_html__( 'Second Choice', 'gforms-edc' ),
					'third'	=> esc_html__( 'Third Choice', 'gforms-edc' )
				),*/
				'enqueue' => array(
					// array( 'field_types' => array( 'select', 'radio', 'checkbox', 'html' ) ) // we are not using this method to enqueue at this time
				)
			)
		);

		return array_merge( parent::scripts(), $scripts );
	}

	/**
	 * Return the stylesheets which should be enqueued.
	 *
	 * @return array
	 */
	public function styles() {
		$styles = array(
			array(
				'handle'	=> 'styles_css',
				'src'		 => $this->get_base_url() . '/css/styles.css',
				'version' => $this->_version,
				'enqueue' => array(
					array( 'field_types' => array( 'select', 'radio', 'checkbox', 'html' ) )
				)
			)
		);

		return array_merge( parent::styles(), $styles );
	}

	// # FILTER FUNCTIONS --------------------------------------------------------------------------------------------

	/**
	 * Custom meta fields.
	 *
	 * @param array $entry_meta Entry meta array.
	 * @param array $form_id The ID of the form from which the entry value was submitted.
	 */
	public function filter_entry_meta( $entry_meta, $form_id ) {

		$form = GFAPI::get_form( $form_id );

		if ( ! $this->edc_active( $form ) ) return $entry_meta;

		$entry_meta[ 'edc_mandrill_status' ] = array(
				'label' => 'Mandrill Status',
				'is_numeric' => false,
				'is_default_column' => false,
				// 'update_entry_meta_callback' => array( $this, 'update_entry_meta' )
		);
		
		return $entry_meta;
	}

	/**
	 * Populate the custom field tooltip.
	 *
	 * @param array $tooltips Existing Gravity Forms tooltips.
	 * @return array The $tooltips array with a new key for
	 * form_field_reject_val.
	 */
	public function filter_tooltips( $tooltips ) {
		$tooltips['form_field_reject_val'] = sprintf(
			'<h6>%s</h6>%s',
			__( 'Rejection Value', 'gforms-edc' ),
			__( 'Comma separated list. Mark as rejected if this field contains any one of these values.', 'gforms-edc' )
		);

		return $tooltips;
	}

	/**
	 * Performing a custom action at form render.
	 *
	 */
	public function filter_pre_render( $form ) {

		if ( ! $this->edc_active( $form ) ) return $form;

		?>
		<script type="text/javascript">
			jQuery(document).bind('gform_page_loaded', function(event, form_id, current_page) {
				jQuery(document).scrollTop(0);
			});
		</script>
		<?php

		return $form;
	}

	/**
	 * Filter {all_fields} merge tag.
	 *
	 * @param string $value The current merge tag value to be filtered.
	 * @param string $merge_tag The merge tag being executed.
	 * @param string $modifier The string containing any modifiers for this merge tag.
	 * @param string $field The current field.
	 *
	 * @return string
	 */
	public function filter_all_fields( $value, $merge_tag, $modifier, $field, $raw_value ) {
		if ( $merge_tag == 'all_fields' && $field->type == 'checkbox' ) {
			$new_array = array_filter( $raw_value );
			$new_value = implode( ", ", $new_array );
			return $new_value;
		} else {
			return $value;
		}
	}
	
	/**
	 * Add the text in the plugin settings to the bottom of the form if enabled for this form.
	 *
	 * @param string $button The string containing the input tag to be filtered.
	 * @param array $form The form currently being displayed.
	 *
	 * @return string
	 */
	public function filter_form_submit_button( $button, $form ) {
		$settings = $this->get_form_settings( $form );
		if ( isset( $settings['enabled'] ) && true == $settings['enabled'] ) {
			$text	 = $this->get_plugin_setting( 'mytextbox' );
			$button = "<div class=\"sub-btn\">{$text}</div>" . $button;
		}

		return $button;
	}

	/**
	 * Remove entry options from export page
	 *
	 * @param obj $form the current form object
	 *
	 * @return obj
	 */
	public function filter_gform_export_fields( $form ) {
		
		if ( ! $this->edc_active( $form ) ) return $form;

		$fields_to_remove = array(
			'payment_amount',
			'payment_date',
			'payment_status',
			'transaction_id',
			'user_agent',
			'ip',
			'post_id',
			'created_by',
			'id',
			'source_url',
			'date_created',
			'partial_entry_id',
			'required_fields_percent_complete',
			'edc_mandrill_status',
			48, 49, 57, 58, 59
		);

		$types = array( 'name', 'address' );

		array_unshift( $form['fields'], array( 'id' => 'created_date', 'label' => __( 'Entry Date', 'gravityforms' ) ) );


		foreach ( $form['fields'] as $key => $field ) {
			$field_id = is_object( $field ) ? $field->id : $field['id'];
			if ( in_array( $field_id, $fields_to_remove ) ) {
				unset ( $form['fields'][ $key ] );
			}
			if ( is_object( $field ) && in_array( $field->get_input_type(), $types ) ) {
				foreach ( $field->inputs as $i => $input ) {
					if ( rgar( $input, 'isHidden' ) ) {
						unset ( $field->inputs[ $i ] );
					}
				}
			}
		}

		return $form;
	}

	/**
	 * Modify entry values before export
	 *
	 * @param obj $form the current form object
	 *
	 * @return obj
	 */
	public function filter_gform_export_field_value( $value, $form_id, $field_id, $entry ) {

		$form = GFAPI::get_form( $form_id );

		if ( ! $this->edc_active( $form ) ) return $form;

		$field = RGFormsModel::get_field( $form, $field_id );

		$entry_date = rgar( $entry, 'date_created' ); // returns the entry date

		if ( $field_id == 'created_date' ) {
			$date = new DateTime( $entry_date );
			$value = $date->format( 'm/d/Y' );
		}

		return $value;

	}

	// # ACTION HOOKS --------------------------------------------------------------------------------------------------

	/**
	 * Custom meta callback.
	 *
	 * @param array $key Entry meta key.
	 * @param array $lead The lead object.
	 * @param array $form The form object.
	 */
	public function update_entry_meta( $key, $lead, $form ) {
		return;
	}

	/**
	 * Performing a custom action at form paging.
	 *
	 * @param array $form The form currently being processed.
	 * @param array $source_page_number The page coming from.
	 * @param array $current_page_number The page currently viewed.
	 */
	public function action_post_paging( $form, $source_page_number, $current_page_number ) {}

	/**
	 * Performing a custom action at the beginning of the form submission process.
	 *
	 * @param array $form The form currently being processed.
	 */
	public function action_pre_submission( $form ) {

		if ( ! $this->edc_active( $form ) ) return;

		$duplicate = $this->is_post_duplicate_email( $_POST, $form );

		if ( $duplicate['is_duplicate'] ) {
			$result_field_id = $this->get_field_id_by_label( $form, 'result' );
			$duplicate_field_id = $this->get_field_id_by_label( $form, 'duplicate' );
			$_POST[ 'input_' . $result_field_id ] = $duplicate['duplicate_status'];
			$_POST[ 'input_' . $duplicate_field_id ] = 'Duplicate';
		}

		$approval_status = $this->update_approval_status( $_POST, $form, true );
	}

	/**
	 * This hook fires after the lead has been created but before the post has been 
	 * created, notifications have been sent and the confirmation has been processed.
	 *
	 * @param array $entry The entry currently being processed.
	 * @param array $form The form currently being processed.
	 */
	public function action_entry_created( $entry, $form ) {}

	/**
	 * Performing a custom action at the end of the form submission process.
	 *
	 * @param array $entry The entry currently being processed.
	 * @param array $form The form currently being processed.
	 */
	public function action_after_submission( $entry, $form ) {

		if ( ! $this->edc_active( $form ) ) return;

		// get Mandrill settings from plugin settings
		if ( $this->get_plugin_setting('mandrillAPIKey') ) {
			$this->_mandrill_api_key = $this->get_plugin_setting('mandrillAPIKey');
			$this->_mandrill = new Mandrill( $this->_mandrill_api_key );
		} else {
			return;
		}

		// defaults
		$trigger_email = false;
		$mandrill_template = false;

		// determin status of entry
		$status_field_id = $this->get_field_id_by_label( $form, 'result' );
		$status = ( $status_field_id >= 0 ? $entry[ $status_field_id ] : false );
		
		$is_duplicate = ( $duplicate_field_id >= 0 ? $entry[ $duplicate_field_id ] : NULL );
		$duplicate_field_id = $this->get_field_id_by_label( $form, 'duplicate' );

		// assign the appropriate Mandrill email template
		switch ($status) {
			case 'Rejected':
				$mandrill_template = $this->get_plugin_setting('mandrillRejectedTemplate');
				$trigger_email = true;
				break;
			case 'Approved':
			default:
				$mandrill_template = $this->get_plugin_setting('mandrillApprovedTemplate');
				$trigger_email = ( empty( $is_duplicate ) ? false : true ); // only send Mandrill Approved on duplicate approved entries.
				break;
		}

		// add status to the dataLayer for GTM
		?>
		<script type="text/javascript">
			window.dataLayer = window.dataLayer || [];
			window.dataLayer.push({
				'application_status':'<?php echo $status; ?>'
			});
			<?php foreach ($entry as $key => $value) { ?>
				window.dataLayer.push({ "<?php echo $key; ?>": "<?php echo $value; ?>" });
			<?php } ?>
		</script>
		<?php

		// get all the necessary field values
		$name_field_id = $this->get_field_id_by_type( $form, 'name' );
		$name_first = $entry[ $name_field_id . '.3' ];
		$name_last = $entry[ $name_field_id . '.6' ];

		$email_field_id = $this->get_field_id_by_type( $form, 'email' );
		$email = $entry[ $email_field_id ];

		$campaign = $entry[ $this->get_field_id_by_label( $form, 'UTM Campaign' ) ];

		$site = get_site_url();
		$parsed_site = parse_url( $site );

		// to Send or Not to Send...
		if ( $this->get_plugin_setting('mandrillEmail') && $trigger_email ) :
			// MANDRILL
			$mandrill_email = $this->get_plugin_setting('mandrillEmail');
			try {
				$template_name = $mandrill_template;
				$template_content = array(
					array(
						'name' => 'pre_header',
						'content' => 'Thank you for applying with Egg Donor Central'
					)
				);
				$message = array(
						// 'html' => '',
						// 'text' => '',
						'subject' => 'Thank you for applying with Egg Donor Central',
						'from_email' => $mandrill_email,
						'from_name' => 'Fairfax Egg Bank',
						'to' => array(
								array(
									'email' => $email,
									'name' => $name_first . ' ' . $name_last,
									'type' => 'to'
								)
						),
						'headers' => array('Reply-To' => $mandrill_email),
						'important' => false,
						'track_opens' => true,
						'track_clicks' => true,
						'auto_text' => null,
						'auto_html' => null,
						'inline_css' => null,
						'url_strip_qs' => null,
						'preserve_recipients' => null,
						'view_content_link' => null,
						// 'bcc_address' => 'message.bcc_address@example.com',
						'tracking_domain' => null,
						'signing_domain' => null,
						'return_path_domain' => null,
						'merge' => true,
						'merge_language' => 'mailchimp',
						'merge_vars' => array(
								array(
										'rcpt' => $email,
										'vars' => array()
								)
						),
						'tags' => array( 'application' ),
						// 'subaccount' => 'customer-123',
						'google_analytics_domains' => array( $parsed_site['host'] ),
						'google_analytics_campaign' => $campaign,
						'metadata' => array('website' => get_site_url() ),
						'recipient_metadata' => array(
								array(
										'rcpt' => $email,
										'values' => array(
											'name_first' => $name_first,
											'name_last' => $name_last
										)
								)
						)
				);
				$async = false;
				$ip_pool = 'Main Pool';
				$send_at = gmdate( "Y-m-d H:i:s", time() );
				$result = $this->_mandrill->messages->sendTemplate($template_name, $template_content, $message, $async, $ip_pool, $send_at);
				gform_update_meta( $entry['id'], 'edc_mandrill_status', $result[0]['status'] );
			}
			catch(Mandrill_Error $e) {
					// Mandrill errors are thrown as exceptions
					echo 'A mandrill error occurred: ' . get_class($e) . ' - ' . $e->getMessage();
					// A mandrill error occurred: Mandrill_Unknown_Subaccount - No subaccount exists with the id 'customer-123'
					throw $e;
			}
		else:
			gform_update_meta( $entry['id'], 'edc_mandrill_status', 'Not triggered' );
		endif;
	}

	// # ADMIN FUNCTIONS -----------------------------------------------------------------------------------------------

	/**
	 * Creates a custom page for this add-on.
	 */
	public function plugin_page() {
		echo '<h2>This add-on extends Grvity Forms with custom functionality for EDC Application forms.</h2>';
		echo '<p>To use this plugin, you must complete the folowwing:</p>';
		
		echo '<h3><a href="' . admin_url( 'admin.php?page=gf_settings&subview=gforms-edc' ) . '">Plugin Settings</a></h3>';
		echo '<p>You must add your <i>MailChimp API key</i> and <i>Mandrill SMTP</i> username and password by going to Forms > Settings > <a href="' . admin_url( 'admin.php?page=gf_settings&subview=gforms-edc' ) . '">EDC</a>.</p>';
		echo '<p>In addition, you must include the Mandrill "From" email address, as well as the template slugs for `Approved`, `Rejected`, and `Duplicate` entries. If the associated slug is not added, Mandrill will not attempt to send any email. Mandrill template slugs can be found by going to Mandrill > Outbound > <a href="https://mandrillapp.com/templates" target="_blank">Templates</a>.</p>';
		
		echo '<h3><a href="' . admin_url( 'admin.php?page=gf_edit_forms' ) . '">Form Fields</a></h3>';
		echo '<p>The following form fields are required to handle certain logic, and must be added by the administrator.</p>';
		echo '<p><b>BMI</b> (<i>hidden</i>) - this field shall display the calculated BMI in the admin area. This is a hidden field type with the label `BMI`.</p>';
		echo '<p><b>Height</b> (<i>dropdown | number</i>) - two fields, these fields shall be used to indicate the user\'s height, one field for feet and the other inches. The information must be stored as numbers.</p>';
		echo '<p><b>Weight</b> (<i>number</i>) - this field shall be used to determine the user\'s wieght in pounds.</p>';
		echo '<p><i>If any of `height`, `weight`, or `BMI` fields are missing, the BMI shall not be calculated.</i></p>';
		echo '<p><b>result</b> (<i>hidden</i>) - this field shall be used to store the application result (Approved, Rejected, Duplicate).</p>';
		echo '<p><b>reason</b> (<i>hidden</i>) - this field shall be used to store the application result reason, for example a list of the rejected fields.</p>';
		echo '<p><b><i>Reject If</i> Custom Field Setting</b> - each field shall now have a custom field for rejection value. This comma separated list of rejection values shall be used to calculate when to trigger the `rejection` of the application. If this field setting is left blank, the field shall not be used to calculate rejection.';
	
		echo '<h3>Form Settings</h3>';
		echo '<p>After creating your form, you must update the form settings to match your custom fields. On the form settings page you must associate height, weight, and BMI parameters to the appropriate fields.';

		echo '<h2>Additional Requirements</h2>';
		echo '<p>The following additional add-ons are required for complete functional requirements for EDC Application Form:</p>';
		echo '<ol><li><b>Gravity Forms</b> with developer\'s license</li>';
		echo '<li><b>Partial Entries Add-on</b> for storing each step of the form within GForms entries.</li>';
		echo '<li><b>MailChimp Add-on</b> for adding applicants to various lead lists.</li>';
		echo '<li><b>Gravity Perks</b> plugin with <i>GP PReview Submission</i> perk for displaying the submission preview page in the last step.</li>';
		echo '<li><b>AJAX set to TRUE</b> in order to render form paging correctly.</li>';
		echo '</ol>';
	}

	/**
	 * Configures the settings which should be rendered on the add-on settings tab.
	 *
	 * @return array
	 */
	public function plugin_settings_fields() {
		return array(
			array(
				'title'	=> esc_html__( 'EDC Settings', 'gforms-edc' ),
				'fields' => array(
					array(
						'name'							=> 'mailchimpApiKey',
						'label'						 => esc_html__( 'MailChimp API Key', 'gforms-edc' ),
						'tooltip'					 => esc_html__( 'Enter your MailChimp API key', 'gforms-edc' ),
						'type'							=> 'text',
						'class'						 => 'small',
						'feedback_callback' => array( $this, 'is_valid_setting' ),
					),
					array(
						'name'							=> 'mandrillEmail',
						'label'						 => esc_html__( 'Mandrill "From" Email', 'gforms-edc' ),
						'tooltip'					 => esc_html__( 'The return email address from which Mandrill emails shall be sent', 'gforms-edc' ),
						'type'							=> 'text',
						'class'						 => 'small',
						'feedback_callback' => array( $this, 'is_valid_setting' ),
					),
					array(
						'name'							=> 'mandrillUser',
						'label'						 => esc_html__( 'Mandrill SMTP Username', 'gforms-edc' ),
						'tooltip'					 => esc_html__( 'Enter your Mandrill SMTP Username', 'gforms-edc' ),
						'type'							=> 'text',
						'class'						 => 'small',
						'feedback_callback' => array( $this, 'is_valid_setting' ),
					),
					array(
						'name'							=> 'mandrillPassword',
						'label'						 => esc_html__( 'Mandrill SMTP Username', 'gforms-edc' ),
						'tooltip'					 => esc_html__( 'Enter your Mandrill SMTP Username', 'gforms-edc' ),
						'type'							=> 'text',
						'class'						 => 'small',
						'feedback_callback' => array( $this, 'is_valid_setting' ),
					),
					array(
						'name'							=> 'mandrillAPIKey',
						'label'						 => esc_html__( 'Mandrill API Key', 'gforms-edc' ),
						'tooltip'					 => esc_html__( 'Enter your Mandrill API Key', 'gforms-edc' ),
						'type'							=> 'text',
						'class'						 => 'small',
						'feedback_callback' => array( $this, 'is_valid_setting' ),
					),
					array(
						'name'							=> 'mandrillApprovedTemplate',
						'label'						 => esc_html__( 'Mandrill Template: Approved', 'gforms-edc' ),
						'tooltip'					 => esc_html__( 'Enter the Mandrill template slug', 'gforms-edc' ),
						'type'							=> 'text',
						'class'						 => 'small',
						'feedback_callback' => array( $this, 'is_valid_setting' ),
					),
					array(
						'name'							=> 'mandrillRejectedTemplate',
						'label'						 => esc_html__( 'Mandrill Template: Rejected', 'gforms-edc' ),
						'tooltip'					 => esc_html__( 'Enter the Mandrill template slug', 'gforms-edc' ),
						'type'							=> 'text',
						'class'						 => 'small',
						'feedback_callback' => array( $this, 'is_valid_setting' ),
					),
					array(
						'name'							=> 'mandrillDuplicateTemplate',
						'label'						 => esc_html__( 'Mandrill Template: Duplicate', 'gforms-edc' ),
						'tooltip'					 => esc_html__( 'Enter the Mandrill template slug', 'gforms-edc' ),
						'type'							=> 'text',
						'class'						 => 'small',
						'feedback_callback' => array( $this, 'is_valid_setting' ),
					)
				)
			)
		);
	}

	/**
	 * Configures the settings which should be rendered on the Form Settings > EDC tab.
	 *
	 * @return array
	 */
	public function form_settings_fields( $form ) {

		$fields = array();

		foreach ( $form[ 'fields' ] as $field ) {
			$len = 60;
			$end = ( strlen( $field->label ) > $len ? '...' : '' );
			$fields[] = array(
				'label' => substr( esc_html__( $field->label, 'gforms-edc' ), 0, $len ) . $end,
				'value' => $field->id,
			);
		}

		return array(
			array(
				'title'	=> esc_html__( 'EDC Form Settings', 'gforms-edc' ),
				'fields' => array(
					array(
						'label'	 => esc_html__( 'Enable EDC Custom Logic', 'gforms-edc' ),
						'type'		=> 'checkbox',
						'name'		=> 'edc_enabled',
						'tooltip' => esc_html__( 'Should this form include rejection logic and associated trigger actions?', 'gforms-edc' ),
						'choices' => array(
							array(
								'label' => esc_html__( 'Yes', 'gforms-edc' ),
								'name'	=> 'rejectionLogic',
							),
						),
					),
					array(
						'label'	 => esc_html__( 'BMI Field', 'gforms-edc' ),
						'type'		=> 'select',
						'name'		=> 'bmifield',
						'tooltip' => esc_html__( 'Select the field used to indicate BMI.', 'gforms-edc' ),
						'choices' => $fields,
					),
					array(
						'label'	 => esc_html__( 'Height (ft) Field', 'gforms-edc' ),
						'type'		=> 'select',
						'name'		=> 'htftfield',
						'tooltip' => esc_html__( 'Select the field used to indicate height in feet.', 'gforms-edc' ),
						'choices' => $fields,
					),
					array(
						'label'	 => esc_html__( 'Height (in) Field', 'gforms-edc' ),
						'type'		=> 'select',
						'name'		=> 'htinfield',
						'tooltip' => esc_html__( 'Select the field used to indicate height in inches.', 'gforms-edc' ),
						'choices' => $fields,
					),
					array(
						'label'	 => esc_html__( 'Weight Field', 'gforms-edc' ),
						'type'		=> 'select',
						'name'		=> 'wtfield',
						'tooltip' => esc_html__( 'Select the field used to indicate weight.', 'gforms-edc' ),
						'choices' => $fields,
					)
				)
			)
		);
	}

	// # CUSTOM FIELD SETTINGS -------------------------------------------------------------------------------------------------------
	
	/**
	 * Render the autocomplete attribute setting.
	 *
	 * @param int $position The current property position.
	 */
	public function action_field_advanced_settings( $position ) {

		// Replace 150 with whatever position you want.
		if ( 150 !== $position ) {
			return;
		} ?>
		<li class="reject_val_setting field_setting">
			<ul>
				<li>
					<label for="field_reject_val" class="section_label">
						<?php esc_html_e( '"Reject If" Value', 'gforms-edc' ); ?>
						<?php gform_tooltip( 'form_field_reject_val' ); ?>
					</label>
					<input id="field_reject_val" type="text" onchange="SetFieldProperty('rejectVal', this.value)" />
				</li>
			</ul>
		</li>
		<?php 
	}

	/**
	 * Custom scripting for our custom field setting.
	 */
	public function action_editor_js() { ?>
		<script type="text/javascript">
			/*
			 * Tell Gravity Forms that every field type should
			 * support the .reject_val_setting input.
			 */
			jQuery.map(fieldSettings, function (el, i) {
				fieldSettings[i] += ', .reject_val_setting';
			});
			/*
			 * When the field settings are initialized, populate
			 * the custom field setting.
			 */
			jQuery(document).on('gform_load_field_settings', function(ev, field) {
				jQuery('#field_reject_val').val(field.rejectVal || '');
			});
		</script>
		<?php 
	}

	/**
	 * Custom javascript
	 */
	public function action_enqueue_scripts( $form, $is_ajax ) {

		if ( ! $this->edc_active( $form ) ) return false;

		wp_enqueue_script( 'gform_script', plugin_dir_url( __FILE__ ) . '/js/scripts.js' );
	}

	// # HELPERS -------------------------------------------------------------------------------------------------------

	/**
	 * Is EDC active on form?
	 *
	 * @param obj $form The form object.
	 */
	public function edc_active( $form ) {
		if ( isset($form[ 'gforms-edc' ][ 'rejectionLogic' ]) && $form[ 'gforms-edc' ][ 'rejectionLogic' ] ) {
			return true;
		}
		return false;
	}

	/**
	 * Update approval status and rejection reasons if any
	 *
	 * @param obj $entry The entry object.
	 * @param obj $form The form object.
	 */
	public function update_approval_status( $entry, $form, $is_post = false, $update_entry = true ) {

		// set defaults
		$rejected_array = array();
		$rejected_reason_string = null;

		// if POST object, we need to append array keys
		$post_input = ( $is_post ? 'input_' : null );
		
		// custom form fields we will be updating
		$result_field_id = $this->get_field_id_by_label( $form, 'result' );
		$reason_field_id = $this->get_field_id_by_label( $form, 'reason' );
		$duplicate_field_id = $this->get_field_id_by_label( $form, 'duplicate' );

		$result = $entry[ $post_input . $result_field_id ];

		// set default approval status
		if ( $result == 'Approved' || empty( $result ) ) {
			$approved = true;
		}
		else {
			$approved = false;
		}


		foreach ($form['fields'] as $field) {
			$field_id = $field->id;
			
			// Age comparison
			if ( $field->type == 'date' ) {
				if ( $is_post ) {
					$date = implode( "/", $entry[ 'input_' . $field_id ] );
				}
				else {
					$date = $entry[ $field_id ];
				}
				if ( ! empty( $date ) ) {
					$from 	= new DateTime( $date );
					$to	 	= new DateTime( 'today' );
					$age		= $from->diff( $to )->y;

					if ( $age < 19 || $age > 31 ) {
						$rejected_array[ 'Age' ] = $age;
					}
				}
				continue;
			}
			
			// BMI comparison
			if ( isset($form[ 'gforms-edc' ][ 'bmifield' ] ) && isset($form[ 'gforms-edc' ][ 'htftfield' ]) && isset($form[ 'gforms-edc' ][ 'htinfield' ]) && isset($form[ 'gforms-edc' ][ 'wtfield' ]) && $field_id == $form[ 'gforms-edc' ][ 'bmifield' ] ) {
				// set defaults
				$bmi = 0;
				// get field id's
				$height_field_id_ft = $form[ 'gforms-edc' ][ 'htftfield' ];
				$height_field_id_in = $form[ 'gforms-edc' ][ 'htinfield' ];
				$weight_field_id = $form[ 'gforms-edc' ][ 'wtfield' ];
				// calculate height and weight and convert to metric (m and kg)
				$height = ( ( ( ( $entry[ $post_input . $height_field_id_ft ] ) * 12 ) + $entry[ $post_input . $height_field_id_in ] ) * 0.0254 );
				$weight = ( $entry[ $post_input . $weight_field_id ] * 0.453592 );
				// calculate BMI from formula `w/h^2` and set BMI field value
				if ( $height > 0 && $weight > 0 ) {
					$bmi = ( $weight ) / pow( $height, 2 );
				} else {
					$bmi = null;
				}
				if ( $update_entry ) :
					if ( $is_post ) {
						$_POST[ $post_input . $field_id ] = $bmi;
					}
					else {
						GFAPI::update_entry_field( $entry['id'], $field_id, $bmi );
					}
				endif;
				// determine rejection
				if ( $bmi < 18 || $bmi >= 27 ) {
					$rejected_array[ 'BMI' ] = $bmi;
				}
				continue;
			}
			
			// all other standard fields
			if ( isset( $field->rejectVal ) ) {
				$reject_val_arr = array_map( 'trim', explode( ',', $field->rejectVal ) );
				if ( isset( $entry[ $post_input . $field_id ] ) && in_array( $entry[ $post_input . $field_id ], $reject_val_arr ) ) {
					$rejected_array[ $field->label ] = $entry[ $post_input . $field_id ];
				}
			}

		}

		// Remove empty array values
		$rejected_array = array_filter( $rejected_array );

		// If still exist reasons for rejection
		if ( ! empty( $rejected_array ) ) {

			// Update rejected status to TRUE
			$approved = false;

			// And for each reason, append to final output string
			foreach ( $rejected_array as $key => $value ) {
				$rejected_reason_string .= $key . ' ' . $value . '; ';
			}
		
		}
		
		if ( $update_entry ) :
			// Finally, set form values to reflect rejections
			if ( $is_post ) {
				$_POST[ $post_input . $result_field_id ] = $this->bool_to_approve_reject( $approved ); // set result
				$_POST[ $post_input . $reason_field_id ] = $rejected_reason_string; // set reason
			}
			else {
				$approval_string = $this->bool_to_approve_reject( $approved );
				GFAPI::update_entry_field( $entry['id'], $result_field_id, $this->bool_to_approve_reject( $approved ) );
				GFAPI::update_entry_field( $entry['id'], $reason_field_id, $rejected_reason_string );
			}
		endif;

		return $approved;
	}

	/**
	 * The feedback callback for the 'mytextbox' setting on the plugin settings page and the 'mytext' setting on the form settings page.
	 *
	 * @param string $value The setting value.
	 *
	 * @return bool
	 */
	public function is_valid_setting( $value ) {
		return strlen( $value ) > 0;
	}

	/**
	 * The helper to get field IDs by field Type
	 *
	 * @param obj $form An array containing all the current form's properties.
	 * @param str $types Indicates the type of field to be returned.
	 * @param bool $use_input_type If available should the fields inputType property be used instead of the type property.
	 *
	 * @return array
	 */
	public function get_field_id_by_type( $form, $type, $use_input_type = false ) {
		if ( is_array( $type ) ) {
			$type = $type[0];
		}
		$fields = GFAPI::get_fields_by_type( $form, array( $type ), $use_input_type );
		if ( ! empty( $fields ) ) {
			return $fields[0]->id;
		}
		return false;
	}

	/**
	 * The helper to get field IDs by field Label
	 *
	 * @param onj $form The form object.
	 * @param string $label The label to compare for id.
	 *
	 * @return bool
	 */
	public function get_field_id_by_label( $form, $label ) {
		$fields = array();
		foreach( $form[ 'fields' ] as $field ) {
			if ( $field->label == $label || $field->label == ucwords($label) )
				$fields[] = $field;
		}
		if ( ! empty( $fields ) ) {
			return $fields[0]->id;
		}
		return false;
	}

	/**
	 * Get entries by email
	 *
	 * @param obj $form The form object.
	 * @param obj $post_object The post object.
	 *
	 * @return array
	 */
	public function get_entries_by_key( $form, $key, $value ) {

		$search_criteria = array(
			'status'				=> 'active',
			'field_filters' => array(
				'mode' 				=> 'all',
				array(
					'key'	 		=> $key,
					'value' 		=> $value
				)
			)
		);

		$sorting = array( 'key' => 'id', 'direction' => 'ASC', 'is_numeric' => true );

		$entries = GFAPI::get_entries( $form['id'], $search_criteria, $sorting );

		return $entries;
	}

	/**
	 * Check if email already exists in entries
	 *
	 * @param obj $form The form object.
	 * @param obj $entry The entry object.
	 * @param bool $partial_entries If this form uses partial entries.
	 *
	 * @return array
	 */
	public function is_entry_duplicate_email( $entry, $form ) {
		$fields = GFAPI::get_fields_by_type( $form, array( 'email' ), true );
		if ( ! empty( $fields ) ) {
			$entries = $this->get_entries_by_key( $form, $fields[0]->id, $entry[ $fields[0]->id ] );
			if ( ! empty( $entries ) && count( $entries ) > 1 ) {
				return true;
			}
			return false;
		}
		return null;
	}

	/**
	 * Check if POST email already exists in entries
	 *
	 * @param obj $form The form object.
	 * @param obj $entry The entry object.
	 * @param bool $partial_entries If this form uses partial entries.
	 *
	 * @return array
	 */
	public function is_post_duplicate_email( $post, $form ) {

		$fields = GFAPI::get_fields_by_type( $form, array( 'email' ), true );
		
		if ( ! empty( $fields ) ):
			
			$entries = $this->get_entries_by_key( $form, $fields[0]->id, $post[ 'input_' . $fields[0]->id ] );
			$result_field_id = $this->get_field_id_by_label( $form, 'result' );

			$result = array('is_duplicate' => false, 'duplicate_status' => null);

			if ( count( $entries ) > 0 ) {
				// one or more pre-existing entries?
				foreach ($entries as $entry) {
					if ( ! isset( $entry['partial_entry_percent'] ) || empty( $entry['partial_entry_percent'] ) ) {
						// exists completed entries
						$result['is_duplicate'] = true;
					}
					if ( isset( $entry[ $result_field_id ] ) && $entry[ $result_field_id ] == 'Rejected' ) {
						// get status of duplicates
						$result['duplicate_status'] = 'Rejected';
					}
				}
			}
		
		endif;

		return $result;
		
	}

	/**
	 * Convert boolean to Approved / Rejected
	 *
	 * @param bool $bool The boolean
	 *
	 * @return string
	 */
	public function bool_to_approve_reject( $bool = true ) {
		if ( $bool ) {
			return 'Approved';
		}
		return 'Rejected';
	}

	/**
	 * Convert boolean to Yes / No
	 *
	 * @param bool $bool The boolean
	 *
	 * @return string
	 */
	public function bool_to_yes_no( $bool = false ) {
		if ( $bool ) {
			return 'Yes';
		}
		return 'No';
	}

}
