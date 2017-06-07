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
    
    add_filter( 'gform_submit_button', array( $this, 'filter_form_submit_button' ), 10, 2 );
    add_filter( 'gform_entry_meta', array( $this, 'filter_entry_meta' ), 10, 2);
		add_filter( 'gform_entry_info', array( $this, 'filter_entry_info' ), 10, 2 );
    add_filter( 'gform_tooltips', array( $this, 'filter_tooltips' ), 10, 1 );
    add_filter( 'gform_merge_tag_filter', array( $this, 'filter_all_fields' ), 10, 5 );
    add_filter( 'gform_confirmation', array( $this, 'filter_custom_confirmation' ), 10, 4 );

    add_action( 'gform_post_paging', array( $this, 'action_post_paging' ), 10, 3 );
    add_action( 'gform_pre_submission', array( $this, 'action_pre_submission' ), 10, 1 );
    add_action( 'gform_entry_created', array( $this, 'action_entry_created' ), 10, 2 );
    add_action( 'gform_after_submission', array( $this, 'action_after_submission' ), 10, 2 );
    add_action( 'gform_field_advanced_settings', array( $this, 'action_field_advanced_settings' ), 10, 1 );
    add_action( 'gform_editor_js', array( $this, 'action_editor_js' ), 10 );

    if ( $this->get_plugin_setting('mandrillAPIKey') ) {
    	
    	$this->_mandrill_api_key = $this->get_plugin_setting('mandrillAPIKey');
    	$this->_mandrill = new Mandrill( $this->_mandrill_api_key );
    }


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
        'handle'  => 'scripts_js',
        'src'     => $this->get_base_url() . '/js/scripts.js',
        'version' => $this->_version,
        'deps'    => array( 'jquery' ),
        'strings' => array(
          'first'  => esc_html__( 'First Choice', 'gforms-edc' ),
          'second' => esc_html__( 'Second Choice', 'gforms-edc' ),
          'third'  => esc_html__( 'Third Choice', 'gforms-edc' )
        ),
        'enqueue' => array(
          array(
            'admin_page' => array( 'form_settings' ),
            'tab'        => 'gforms-edc'
          )
        )
      ),

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
        'handle'  => 'styles_css',
        'src'     => $this->get_base_url() . '/css/styles.css',
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
   * Filter {all_fields} merge tag.
   *
   * @param string $value The current merge tag value to be filtered.
   * @param string $merge_tag The merge tag being executed.
   * @param string $modifier The string containing any modifiers for this merge tag.
   * @param string $field The current field.
   *
   * @return string
   */
	function filter_all_fields( $value, $merge_tag, $modifier, $field, $raw_value ) {
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
  function filter_form_submit_button( $button, $form ) {
    $settings = $this->get_form_settings( $form );
    if ( isset( $settings['enabled'] ) && true == $settings['enabled'] ) {
      $text   = $this->get_plugin_setting( 'mytextbox' );
      $button = "<div class=\"sub-btn\">{$text}</div>" . $button;
    }

    return $button;
  }

  /**
   * Custom confirmation message...
   *
   * @param 
   *
   */
  public function filter_custom_confirmation( $confirmation, $form, $entry, $ajax ) {
  	$is_duplicate = $this->is_duplicate_email( $form, $entry );
  	$approved = $this->get_approval_status( $entry, $form );

  	$name_field_id = $this->get_field_id_by_type( $form, 'name' );
  	$name_first = $entry[ $name_field_id . '.3' ];

  	if ( $is_duplicate ) {
  		$confirmation = '<p>Hello ' . $name_first . ',</p>
				<p>Thank you for taking the time to submit an online application for our donor egg program. It appears you have previously submitted an application. Therefore we are unable to accept your online application at this time.</p>
				<p>If your answers to the application questionnaire have changed, please contact us at <a href="mailto:donor@fairfaxeggbank.com">donor@fairfaxeggbank.com</a> and we can assist you with updating your application.</p>
				<p>Sincerely,<br />
					<b>The Fairfax EggBank Donor Egg Team</b></p>';
  	} elseif ( $approved ) {
			$confirmation = '<h1>Congratulations ' . $name_first . '! Give yourself a pat on the back — less than 39% pass the initial screening of the egg donor process but you did!</h1>
				<p>So what\'s next? The full long-form application. We admit... it\'s called "long" for a reason. But every question is critical to ensure we have an accurate understanding of your health and any associated risks in becoming a donor.</p>
				<p><b>WHAT YOU’LL NEED TO DO:</b>
					<ul>
					 	<li>Click on the CONTINUE button below and register to start the application.</li>
					 	<li>Fill out the "profile" and "medical" section. DON’T WORRY about completing the "personal summary" or the "essay summary" — this will be completed later in the process.</li>
					 	<li>When you\'re done, recheck your answers, complete the electronic signature, and click on SUBMIT in the medical summary section. If you don’t do this, we won\'t receive notification of your submission.</li>
					</ul>
				</p>
				<a class="btn button gform_button" href="www.givfdonor.com">CONTINUE</a>
				<p><b>WHEN THE APP IS DUE:</b> You have 14 days to complete your application in order to be entered into a drawing to win an extra $100.  Please make sure to contact us if you get locked out of your account for any reason. (You may continue to apply past the 14 days, however you will not be eligible for the drawing)</p>
				<p><b>HOW TO WIN AN EXTRA $100:</b> Each month, we hold a drawing for a $100 gift certificate. If you finish the application more than 7 days ahead of deadline, you\'ll be entered 3 times into our raffle. Otherwise, if you finish within the deadline, you\'ll be entered 1 time into our raffle. <b>Make sure to be thorough — incomplete and/or inaccurate answers will lead to disqualification.</b> If you win, we will contact you via e-mail.</p>
				<p><b>WHAT COMES NEXT:</b> Once you have submitted your form, our Clinical Geneticist will begin the review process. We will be in touch within a couple of weeks to advise whether you will move forward in the egg donation process or not.</p>
				<p>So mark your calendar to keep the deadline in sight! We deeply thank you for the commitment you\'re making to become a donor. If you have any questions or concerns, don\'t hesitate to contact us at <a href="mailto:donor@fairfaxeggbank.com">donor@fairfaxeggbank.com</a>.</p>
				<p>Sincerely,<br />
					<b>The Fairfax EggBank Donor Egg Team</b></p>';
		} else {
			$confirmation = '<p>Hello ' . $name_first . ',</p>
				<p>Thank you for taking the time to submit an online application for our donor egg program. We regret to inform you that we are unable to accept you into our egg donation program based on the information provided.</p>
				<p>Many factors are involved in our eligibility determination such as requirements put forth by the FDA, clinical geneticists and our medical directors. Unfortunately we are unable to disclose the specific reasons why an applicant may not be eligible, however our basic requirements can be found on our website at <a href="http://www.eggdonorcentral.com/egg-donor-requirements">http://www.eggdonorcentral.com/egg-donor-requirements</a> for your reference. You may also find our FAQ section to be helpful at <a href="https://www.eggdonorcentral.com/faqs">https://www.eggdonorcentral.com/faqs</a>.</p>
				<p>We greatly appreciate your interest in our program and wish you all the best.</p>
				<p>Sincerely,<br /><b>The Fairfax EggBank Team</b></p>';
		}

		return $confirmation;

  }

  // # ADMIN FUNCTIONS -----------------------------------------------------------------------------------------------

  /**
   * Creates a custom page for this add-on.
   */
  public function plugin_page() {
    echo '<h2>This add-on extends Grvity Forms with custom functionality for EDC Application forms.</h2>';
    echo '<p>To use this plugin, you must complete the folowwing:</p>';
    
    echo '<h3><a href="' . admin_url( 'admin.php?page=gf_settings&subview=gforms-edc' ) . '">Plugin Settings</a></h3>';
    echo '<p>You must add your <i>MailChimp API key</i> and <i>Mandrill SMTP</i> username and password by going to <a href="' . admin_url( 'admin.php?page=gf_settings&subview=gforms-edc' ) . '">Forms > Settings > EDC</a>.</p>';
    
    echo '<h3><a href="' . admin_url( 'admin.php?page=gf_edit_forms' ) . '">Form Fields</a></h3>';
    echo '<p>The following form fields are required to handle certain logic, and must be added by the administrator.</p>';
    echo '<p><b>Status</b> (<i>hidden</i>) - this field shall display `Approved` or `Rejected` based on the rejection parameters for each field. This is a hidden field type with the label `Status`.</p>';
    echo '<p><b>Rejection Reason</b> (<i>hidden</i>) - this field shall display a text string representing all the fields that qualified as "rejected" based on the field\'s rejection parameters. This is a hidden field type with the label `Rejection Reason`.</p>';
    echo '<p><b>BMI</b> (<i>hidden</i>) - this field shall display the calculated BMI in the admin area. This is a hidden field type with the label `BMI`.</p>';
    echo '<p><b>Height</b> (<i>dropdown | number</i>) - two fields, these fields shall be used to indicate the user\'s height, one field for feet and the other inches. The information must be stored as numbers.</p>';
    echo '<p><b>Weight</b> (<i>number</i>) - this field shall be used to determine the user\'s wieght in pounds.</p>';
    echo '<p><i>If any of `height`, `weight`, or `BMI` fields are missing, the BMI shall not be calculated.</i></p>';
    echo '<p><b><i>Reject If</i> Custom Field Setting</b> - each field shall now have a custom field for rejection value. This comma separated list of rejection values shall be used to calculate when to trigger the `rejection` of the application. If this field setting is left blank, the field shall not be used to calculate rejection.';
    
    echo '<h3>Form Settings</h3>';
    echo '<p>After creating your form, you must update the form settings to match your custom fields. On the form settings page you must associate height, weight, and BMI parameters to the appropriate fields.';

    echo '<h2>Additional Requirements</h2>';
    echo '<p>The following additional add-ons are required for complete functioanl requirements for EDC Application Form:</p>';
    echo '<ol><li><b>Gravity Forms</b> with developer\'s license</li>';
    echo '<li><b>Partial Entries Add-on</b> for storing each step of the form within GForms entries.</li>';
    echo '<li><b>MailChimp Add-on</b> for adding applicants to various lead lists.</li>';
    echo '<li><b>Gravity Perks</b> plugin with <i>GP PReview Submission</i> perk for displaying the submission preview page in the last step.</li>';
  }

  /**
   * Configures the settings which should be rendered on the add-on settings tab.
   *
   * @return array
   */
  public function plugin_settings_fields() {
    return array(
      array(
        'title'  => esc_html__( 'EDC Settings', 'gforms-edc' ),
        'fields' => array(
          array(
            'name'              => 'mailchimpApiKey',
            'label'             => esc_html__( 'MailChimp API Key', 'gforms-edc' ),
            'tooltip'           => esc_html__( 'Enter your MailChimp API key', 'gforms-edc' ),
            'type'              => 'text',
            'class'             => 'small',
            'feedback_callback' => array( $this, 'is_valid_setting' ),
          ),
          array(
            'name'              => 'mandrillUser',
            'label'             => esc_html__( 'Mandrill SMTP Username', 'gforms-edc' ),
            'tooltip'           => esc_html__( 'Enter your Mandrill SMTP Username', 'gforms-edc' ),
            'type'              => 'text',
            'class'             => 'small',
            'feedback_callback' => array( $this, 'is_valid_setting' ),
          ),
          array(
            'name'              => 'mandrillPassword',
            'label'             => esc_html__( 'Mandrill SMTP Username', 'gforms-edc' ),
            'tooltip'           => esc_html__( 'Enter your Mandrill SMTP Username', 'gforms-edc' ),
            'type'              => 'text',
            'class'             => 'small',
            'feedback_callback' => array( $this, 'is_valid_setting' ),
          ),
          array(
            'name'              => 'mandrillAPIKey',
            'label'             => esc_html__( 'Mandrill API Key', 'gforms-edc' ),
            'tooltip'           => esc_html__( 'Enter your Mandrill API Key', 'gforms-edc' ),
            'type'              => 'text',
            'class'             => 'small',
            'feedback_callback' => array( $this, 'is_valid_setting' ),
          )
        )
      )
    );
  }

  /**
   * Configures the settings which should be rendered on the Form Settings > Simple Add-On tab.
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
        'title'  => esc_html__( 'EDC Form Settings', 'gforms-edc' ),
        'fields' => array(
          array(
            'label'   => esc_html__( 'Enable EDC Custom Logic', 'gforms-edc' ),
            'type'    => 'checkbox',
            'name'    => 'enabled',
            'tooltip' => esc_html__( 'Should this form include rejection logic and associated trigger actions?', 'gforms-edc' ),
            'choices' => array(
              array(
                'label' => esc_html__( 'Yes', 'gforms-edc' ),
                'name'  => 'rejectionLogic',
              ),
            ),
          ),
          array(
						'label'   => esc_html__( 'BMI Field', 'gforms-edc' ),
						'type'    => 'select',
						'name'    => 'bmifield',
						'tooltip' => esc_html__( 'Select the field used to indicate BMI.', 'gforms-edc' ),
						'choices' => $fields,
					),
          array(
						'label'   => esc_html__( 'Height (ft) Field', 'gforms-edc' ),
						'type'    => 'select',
						'name'    => 'htftfield',
						'tooltip' => esc_html__( 'Select the field used to indicate height in feet.', 'gforms-edc' ),
						'choices' => $fields,
					),
          array(
						'label'   => esc_html__( 'Height (in) Field', 'gforms-edc' ),
						'type'    => 'select',
						'name'    => 'htinfield',
						'tooltip' => esc_html__( 'Select the field used to indicate height in inches.', 'gforms-edc' ),
						'choices' => $fields,
					),
          array(
						'label'   => esc_html__( 'Weight Field', 'gforms-edc' ),
						'type'    => 'select',
						'name'    => 'wtfield',
						'tooltip' => esc_html__( 'Select the field used to indicate weight.', 'gforms-edc' ),
						'choices' => $fields,
					)
        )
      )
    );
  }

  // # ACTION HOOKS --------------------------------------------------------------------------------------------------

  /**
   * Custom meta fields.
   *
   * @param array $entry_meta Entry meta array.
   * @param array $form_id The ID of the form from which the entry value was submitted.
   */
  public function filter_entry_meta( $entry_meta, $form_id ) {
    $entry_meta[ 'edc_is_duplicate' ] = array(
        'label' => 'Duplicate',
        'is_numeric' => true,
        'is_default_column' => true,
        // 'update_entry_meta_callback' => array( $this, 'update_entry_meta' )
    );
    $entry_meta[ 'edc_application_status' ] = array(
        'label' => 'Application Status',
        'is_numeric' => false,
        'is_default_column' => true,
        // 'update_entry_meta_callback' => array( $this, 'update_entry_meta' )
    );
    $entry_meta[ 'edc_application_rejection_details' ] = array(
        'label' => 'Rejection Details',
        'is_numeric' => false,
        'is_default_column' => false,
        // 'update_entry_meta_callback' => array( $this, 'update_entry_meta' )
    );
    $entry_meta[ 'edc_mandrill_status' ] = array(
        'label' => 'Mandrill Status',
        'is_numeric' => false,
        'is_default_column' => false,
        // 'update_entry_meta_callback' => array( $this, 'update_entry_meta' )
    );
    return $entry_meta;
	}

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
	 * Target for the gform_entry_info action. Displays the progress information on the entry detail page.
	 *
	 * @param $form_id
	 * @param $entry
	 */
	public function filter_entry_info( $form_id, $entry ) {
		$br = '<br /><br />';
		$is_duplicate = rgar( $entry, 'edc_is_duplicate' );
		$application_status = rgar( $entry, 'edc_application_status' );
		printf( esc_html__( 'Duplicate: %s %s', 'gforms-edc' ), $is_duplicate, $br );
		printf( esc_html__( 'Application Status: %s %s', 'gforms-edc' ), $application_status, $br );
		if ( $application_status == 'Rejected' ) {
			$application_details = rgar( $entry, 'edc_application_rejection_details' );
			printf( esc_html__( 'Reason for Rejection: %s %s', 'gforms-edc' ), $application_details, $br );
		}
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
  public function action_pre_submission( $form ) {}

  /**
   * This hook fires after the lead has been created but before the post has been 
   * created, notifications have been sent and the confirmation has been processed.
   *
   * @param array $entry The entry currently being processed.
   * @param array $form The form currently being processed.
   */
  public function action_entry_created( $entry, $form ) {

  	$is_duplicate = $this->update_is_duplicate_email( $entry, $form );

  	$approval_status = $this->update_approval_status( $entry, $form );
  }

  /**
   * Performing a custom action at the end of the form submission process.
   *
   * @param array $entry The entry currently being processed.
   * @param array $form The form currently being processed.
   */
  public function action_after_submission( $entry, $form ) {

    // $arr = array();

    /*foreach ($form['fields'] as $field) {
      $id = $field->id;
      $name = $field->label;

      if ( isset( $entry[ $id ] ) ) {
        $arr[ $name ] = $entry[ $id ];
      }
      elseif ( is_array( $field->inputs ) ) {
        $new_arr = array();
        foreach ($field->inputs as $input) {
          if ( ! isset($input['isHidden']) && isset( $entry[ $input['id'] ] ) && ! empty( $entry[ $input['id'] ] ) ) {
            $new_arr[ $input['label'] ] = $entry[ $input['id'] ];
          }
        }
        $arr[ $name ] = $new_arr;
      }
      else {
        // nothing...
      }
    }*/

    // MANDRILL --------------------------------------------------------------------------------------------------
	  try {
	    $message = array(
	        'html' => $this->get_mandrill_html( $entry, $form ),
	        'text' => 'Example text content',
	        'subject' => 'example subject',
	        'from_email' => 'tim@monkishtypist.com',
	        'from_name' => 'Example Name',
	        'to' => array(
	            array(
	                'email' => 'tim@monkishtypist.com',
	                'name' => 'Recipient Name',
	                'type' => 'to'
	            )
	        ),
	        'headers' => array('Reply-To' => 'message.reply@example.com'),
	        'important' => false,
	        'track_opens' => null,
	        'track_clicks' => null,
	        'auto_text' => null,
	        'auto_html' => null,
	        'inline_css' => null,
	        'url_strip_qs' => null,
	        'preserve_recipients' => null,
	        'view_content_link' => null,
	        'bcc_address' => 'message.bcc_address@example.com',
	        'tracking_domain' => null,
	        'signing_domain' => null,
	        'return_path_domain' => null,
	        'merge' => true,
	        'merge_language' => 'mailchimp',
	        'global_merge_vars' => array(
	            array(
	                'name' => 'merge1',
	                'content' => 'merge1 content'
	            )
	        ),
	        'merge_vars' => array(
	            array(
	                'rcpt' => 'recipient.email@example.com',
	                'vars' => array(
	                    array(
	                        'name' => 'merge2',
	                        'content' => 'merge2 content'
	                    )
	                )
	            )
	        ),
	        'tags' => array('password-resets'),
	        'subaccount' => 'customer-123',
	        'google_analytics_domains' => array('example.com'),
	        'google_analytics_campaign' => 'message.from_email@example.com',
	        'metadata' => array('website' => 'www.example.com'),
	        'recipient_metadata' => array(
	            array(
	                'rcpt' => 'recipient.email@example.com',
	                'values' => array('user_id' => 123456)
	            )
	        ),
	        'attachments' => array(
	            array(
	                'type' => 'text/plain',
	                'name' => 'myfile.txt',
	                'content' => 'ZXhhbXBsZSBmaWxl'
	            )
	        ),
	        'images' => array(
	            array(
	                'type' => 'image/png',
	                'name' => 'IMAGECID',
	                'content' => 'ZXhhbXBsZSBmaWxl'
	            )
	        )
	    );
	    $async = false;
	    $ip_pool = 'Main Pool';
	    $send_at = gmdate( "Y-m-d H:i:s", time() );
	    $result = $this->_mandrill->messages->send($message, $async, $ip_pool, $send_at);
	    print_r($result);
	    /*
	    Array
	    (
	        [0] => Array
	            (
	                [email] => recipient.email@example.com
	                [status] => sent
	                [reject_reason] => hard-bounce
	                [_id] => abc123abc123abc123abc123abc123
	            )
	    
	    )
	    */
	    gform_update_meta( $entry['id'], 'edc_mandrill_status', $result[0]['status'] );

		} catch(Mandrill_Error $e) {
		    // Mandrill errors are thrown as exceptions
		    echo 'A mandrill error occurred: ' . get_class($e) . ' - ' . $e->getMessage();
		    // A mandrill error occurred: Mandrill_Unknown_Subaccount - No subaccount exists with the id 'customer-123'
		    throw $e;
		}
  }


  // # SIMPLE CONDITION ------------------------------------------------------------------------------------------------

  /**
   * Define the markup for the custom_logic_type type field.
   *
   * @param array $field The field properties.
   * @param bool|true $echo Should the setting markup be echoed.
   */
  /*public function settings_custom_logic_type( $field, $echo = true ) {

    // Get the setting name.
    $name = $field['name'];

    // Define the properties for the checkbox to be used to enable/disable access to the simple condition settings.
    $checkbox_field = array(
      'name'    => $name,
      'type'    => 'checkbox',
      'choices' => array(
        array(
          'label' => esc_html__( 'Enabled', 'gforms-edc' ),
          'name'  => $name . '_enabled',
        ),
      ),
      'onclick' => "if(this.checked){jQuery('#{$name}_condition_container').show();} else{jQuery('#{$name}_condition_container').hide();}",
    );

    // Determine if the checkbox is checked, if not the simple condition settings should be hidden.
    $is_enabled      = $this->get_setting( $name . '_enabled' ) == '1';
    $container_style = ! $is_enabled ? "style='display:none;'" : '';

    // Put together the field markup.
    $str = sprintf( "%s<div id='%s_condition_container' %s>%s</div>",
      $this->settings_checkbox( $checkbox_field, false ),
      $name,
      $container_style,
      $this->simple_condition( $name )
    );

    echo $str;
  }*/

  /**
   * Build an array of choices containing fields which are compatible with conditional logic.
   *
   * @return array
   */
  /*public function get_conditional_logic_fields() {
    $form   = $this->get_current_form();
    $fields = array();
    foreach ( $form['fields'] as $field ) {
      if ( $field->is_conditional_logic_supported() ) {
        $inputs = $field->get_entry_inputs();

        if ( $inputs ) {
          $choices = array();

          foreach ( $inputs as $input ) {
            if ( rgar( $input, 'isHidden' ) ) {
              continue;
            }
            $choices[] = array(
              'value' => $input['id'],
              'label' => GFCommon::get_label( $field, $input['id'], true )
            );
          }

          if ( ! empty( $choices ) ) {
            $fields[] = array( 'choices' => $choices, 'label' => GFCommon::get_label( $field ) );
          }

        } else {
          $fields[] = array( 'value' => $field->id, 'label' => GFCommon::get_label( $field ) );
        }

      }
    }

    return $fields;
  }*/

  /**
   * Evaluate the conditional logic.
   *
   * @param array $form The form currently being processed.
   * @param array $entry The entry currently being processed.
   *
   * @return bool
   */
  /*public function is_custom_logic_met( $form, $entry ) {
    if ( $this->is_gravityforms_supported( '2.0.7.4' ) ) {
      // Use the helper added in Gravity Forms 2.0.7.4.

      return $this->is_simple_condition_met( 'custom_logic', $form, $entry );
    }

    // Older version of Gravity Forms, use our own method of validating the simple condition.
    $settings = $this->get_form_settings( $form );

    $name       = 'custom_logic';
    $is_enabled = rgar( $settings, $name . '_enabled' );

    if ( ! $is_enabled ) {
      // The setting is not enabled so we handle it as if the rules are met.

      return true;
    }

    // Build the logic array to be used by Gravity Forms when evaluating the rules.
    $logic = array(
      'logicType' => 'all',
      'rules'     => array(
        array(
          'fieldId'  => rgar( $settings, $name . '_field_id' ),
          'operator' => rgar( $settings, $name . '_operator' ),
          'value'    => rgar( $settings, $name . '_value' ),
        ),
      )
    );

    return GFCommon::evaluate_conditional_logic( $logic, $form, $entry );
  }*/


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


  // # HELPERS -------------------------------------------------------------------------------------------------------

  /**
   * Update approval status and rejection reasons if any
   *
   * @param obj $entry The entry object.
   * @param obj $form The form object.
   */
  public function update_approval_status( $entry, $form ) {

    $approved = true;
    $rejected_array = array();
    $rejected_reason_string = null;

    foreach ($form['fields'] as $field) {
      $field_id = $field->id;
      
      // Age comparison
      if ( $field->type == 'date' ) {
        $date = $entry[ $field_id ];
        if ( ! empty( $date ) ) {
          $from 	= new DateTime( $date );
          $to   	= new DateTime( 'today' );
          $age  	= $from->diff( $to )->y;

          if ( $age < 19 || $age > 31 ) {
            $rejected_array[ 'Age' ] = $age;
          }
        }
        continue;
      }
      
      // BMI comparison
      if ( $field_id == $form[ 'gforms-edc' ][ 'bmifield' ] ) {
      	// set defaults
      	$bmi = 0;
      	// get field id's
      	$height_field_id_ft = $form[ 'gforms-edc' ][ 'htftfield' ];
      	$height_field_id_in = $form[ 'gforms-edc' ][ 'htinfield' ];
      	$weight_field_id = $form[ 'gforms-edc' ][ 'wtfield' ];
      	// calculate height and weight and convert to metric (m and kg)
      	$height = ( ( ( ( $entry[ $height_field_id_ft ] ) * 12 ) + $entry[ $height_field_id_in ] ) * 0.0254 );
      	$weight = ( $entry[ $weight_field_id ] * 0.453592 );
      	// calculate BMI from formula `w/h^2` and set BMI field value
      	if ( $height > 0 && $weight > 0 ) {
	      	$bmi = ( $weight ) / pow( $height, 2 );
      	} else {
      		$bmi = null;
      	}
      	$bmi_result = GFAPI::update_entry_field( $entry['id'], $field_id, $bmi );
      	// determine rejection
        if ( $bmi < 18 || $bmi >= 27 ) {
          $rejected_array[ 'BMI' ] = $bmi;
        }
        continue;
      }
      
      // all other standard fields
      if ( isset( $field->rejectVal ) ) {
        $reject_val_arr = explode( ',', $field->rejectVal );
        if ( isset( $entry[ $field_id ] ) && in_array( $entry[ $field_id ], $reject_val_arr ) ) {
          $rejected_array[ $field->label ] = $entry[ $field_id ];
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
    
    // Finally, set form values to reflect rejections
    $approval_string = $this->bool_to_approve_reject( $approved );
    gform_update_meta( $entry['id'], 'edc_application_status', $approval_string );
    gform_update_meta( $entry['id'], 'edc_application_rejection_details', $rejected_reason_string );

    return $approved;
  }

  public function get_approval_status( $entry, $form ) {

    $approved = true;

    foreach ($form['fields'] as $field) {
      $field_id = $field->id;
      
      // Age comparison
      if ( $field->type == 'date' ) {
        $date = $entry[ $field_id ];
        if ( ! empty( $date ) ) {
          $from 	= new DateTime( $date );
          $to   	= new DateTime( 'today' );
          $age  	= $from->diff( $to )->y;

          if ( $age < 19 || $age > 31 ) {
            $approved = false;
          }
        }
        continue;
      }
      
      // BMI comparison
      if ( $field_id == $form[ 'gforms-edc' ][ 'bmifield' ] ) {
      	// set defaults
      	$bmi = 0;
      	// get field id's
      	$height_field_id_ft = $form[ 'gforms-edc' ][ 'htftfield' ];
      	$height_field_id_in = $form[ 'gforms-edc' ][ 'htinfield' ];
      	$weight_field_id = $form[ 'gforms-edc' ][ 'wtfield' ];
      	// calculate height and weight and convert to metric (m and kg)
      	$height = ( ( ( ( $entry[ $height_field_id_ft ] ) * 12 ) + $entry[ $height_field_id_in ] ) * 0.0254 );
      	$weight = ( $entry[ $weight_field_id ] * 0.453592 );
      	// calculate BMI from formula `w/h^2` and set BMI field value
      	if ( $height > 0 && $weight > 0 ) {
	      	$bmi = ( $weight ) / pow( $height, 2 );
      	} else {
      		$bmi = null;
      	}
      	// determine rejection
        if ( $bmi < 18 || $bmi >= 27 ) {
          $approved = false;
        }
        continue;
      }
      
      // all other standard fields
      if ( isset( $field->rejectVal ) ) {
        $reject_val_arr = explode( ',', $field->rejectVal );
        if ( isset( $entry[ $field_id ] ) && in_array( $entry[ $field_id ], $reject_val_arr ) ) {
          $approved = false;
        }
      }

    }

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
      if ( $field->label == $label )
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
	    'status'        => 'active',
	    'field_filters' => array(
        'mode' 				=> 'all',
        array(
          'key'   		=> $key,
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
  public function is_duplicate_email( $form, $entry, $partial_entries = true ) {
  	$fields = GFAPI::get_fields_by_type( $form, array( 'email' ), true );
  	$count = ( $partial_entries ? 1 : 0 );
  	if ( ! empty( $fields ) ) {
	  	$entries = $this->get_entries_by_key( $form, $fields[0]->id, $entry[ $fields[0]->id ] );
			if ( ! empty( $entries ) && count( $entries ) > $count ) {
				return true;
			}
			return false;
		}
		return null;
  }

  /**
   * Check if email already exists in entries and update status
   *
   * @param obj $form The form object.
   * @param obj $entry The entry object.
   * @param bool $partial_entries If this form uses partial entries.
   *
   * @return array
   */
  public function update_is_duplicate_email( $entry, $form, $partial_entries = true ) {

  	$is_duplicate = $this->bool_to_yes_no( $this->is_duplicate_email( $form, $entry, $partial_entries ) );

  	gform_update_meta( $entry['id'], 'edc_is_duplicate', $is_duplicate );
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


  // MANDRILL --------------------------------------------------------------------------------------------------

  public function get_mandrill_html( $entry, $form, $approved = true ) {

  	$name_field_id = $this->get_field_id_by_type( $form, 'name' );
  	$name_first = $entry[ $name_field_id . '.3' ];

  	if ( $approved ) {
			$html = "<p>Hello {$name_first}!</p>
				<p><b>Congratulations! Give yourself a pat on the back — less than 39% pass the initial screening of the egg donor process but you did!</b></p>
				<p>So what's next? The full long-form application. We admit... it's called \"long\" for a reason. But every question is critical to ensure we have an accurate understanding of your health and any associated risks in becoming a donor.</p>
				<p><b>WHAT YOU'LL NEED TO DO:</b><ul><li>Save this e-mail! It will be critical as a checklist.</li><li>If you haven't already, visit <a href=\"www.givfdonor.com\">www.givfdonor.com</a> and register to start the application.</li><li>Fill out the \"profile\" and \"medical\" section. DON'T WORRY about completing the \"personal summary\" or the \"essay summary\" — this will be completed later in the process.</li><li>When you're done, recheck your answers, complete the electronic signature, and click on SUBMIT in the medical summary section. If you don't do this, we won't receive notification of your submission.</li></ul></p>
				<p><b>WHEN THE APP IS DUE:</b> You have 14 days to complete your application in order to be entered into a drawing to win an extra $100. Please make sure to contact us if you get locked out of your account for any reason. (You may continue to apply past the 14 days, however you will not be eligible for the drawing)</p>
				<p><b>HOW TO WIN AN EXTRA $100:</b> Each month, we hold a drawing for a $100 gift certificate. If you finish the application more than 7 days ahead of deadline, you'll be entered 3 times into our raffle. Otherwise, if you finish within the deadline, you'll be entered 1 time into our raffle. Make sure to be thorough — incomplete and/or inaccurate answers will lead to disqualification. If you win, we will contact you via e-mail.</p>
				<p><b>WHAT COMES NEXT:</b> Once you have submitted your form, our Clinical Geneticist will begin the review process. We will be in touch within a couple of weeks to advise whether you will move forward in the egg donation process or not.</p>
				<p>So mark your calendar to keep the deadline in sight! We deeply thank you for the commitment you're making to become a donor. If you have any questions or concerns, don't hesitate to contact us at <a href=\"mailto:donor@fairfaxeggbank.com\">donor@fairfaxeggbank.com</a>.</p>
				<hr />
				<p><b>Checklist of to do's:</b><br />
					[ ] Set your target date for completing the application. Mark it on your calendar.<br />
					[ ] Gather documentation of your and your family's medical history.<br />
					[ ] Register at www.givfdonor.com to start the application.<br />
					[ ] Fill out the \"profile\" and \"medical\" sections of the application.<br />
					[ ] Re-check every question once you've completed the application.<br />
					[ ] Complete the electronic signature.<br />
					[ ] Click SUBMIT.</p>
				<p>Sincerely,<br /><b>The Fairfax EggBank Donor Egg Team</b></p>";
		} else {
			$html = "<p>Hello {$name_first}!</p>
				<p>Thank you for taking the time to submit an online application for our donor egg program. We regret to inform you that we are unable to accept you into our egg donation program based on the information provided.</p>
				<p>Many factors are involved in our eligibility determination such as requirements put forth by the FDA, clinical geneticists and our medical directors. Unfortunately we are unable to disclose the specific reasons why an applicant may not be eligible, however our basic requirements can be found on our website at <a href=\"http://www.eggdonorcentral.com/egg-donor-requirements\">http://www.eggdonorcentral.com/egg-donor-requirements</a> for your reference. You may also find our FAQ section to be helpful at <a href=\"https://www.eggdonorcentral.com/faqs\">https://www.eggdonorcentral.com/faqs</a>.</p>
				<p>We greatly appreciate your interest in our program and wish you all the best.</p>
				<p>Sincerely,<br /><b>The Fairfax EggBank Team</b></p>";
		}

		return $html;

	}

}
