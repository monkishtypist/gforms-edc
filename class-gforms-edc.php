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

  public $is_duplicate = false;

  protected $_mandrill_host = 'smtp.mandrillapp.com';
  protected $_mandrill_port = 587;

  private static $_instance = null;

  public $_mandrill;
  public $_madrill_api_key;

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

    add_action( 'gform_post_paging', array( $this, 'action_post_paging' ), 10, 3 );
    add_action( 'gform_pre_submission', array( $this, 'action_pre_submission' ), 10, 1 );
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

  // # FRONTEND FUNCTIONS --------------------------------------------------------------------------------------------

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
					),
          array(
            'label' => esc_html__( 'Condition', 'gforms-edc' ),
            'type'  => 'custom_logic_type',
            'name'  => 'custom_logic',
          ),
        ),
      ),
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
    $entry_meta[ 'is_duplicate' ] = array(
        'label' => 'Duplicate',
        'is_numeric' => true,
        'is_default_column' => true,
        'update_entry_meta_callback' => array( $this, 'update_entry_meta' )
    );
    $entry_meta[ 'mandrill_status' ] = array(
        'label' => 'Mandrill Status',
        'is_numeric' => false,
        'is_default_column' => false,
        'update_entry_meta_callback' => array( $this, 'update_entry_meta' )
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
		$is_duplicate = rgar( $entry, 'is_duplicate' );
		$info = '<span>' . $is_duplicate . '</span><br/><br/>';
		printf( esc_html__( 'Duplicate: %s', 'gforms-edc' ), $info );
	}

  /**
   * Performing a custom action at form paging.
   *
   * @param array $form The form currently being processed.
   * @param array $source_page_number The page coming from.
   * @param array $current_page_number The page currently viewed.
   */
  public function action_post_paging( $form, $source_page_number, $current_page_number ) {

    return;

  }

  /**
   * Performing a custom action at the beginning of the form submission process.
   *
   * @param array $form The form currently being processed.
   */
  public function action_pre_submission( $form ) {

    // This section is used to validate any reasons for rejection, and append the data to the form
    $rejection_status_id = $this->get_field_id_by_label( $form, 'Status' );
    $rejection_reason_id = $this->get_field_id_by_label( $form, 'Rejection Reason' );

    $rejected_status = false;
    $rejected_array = array();
    $rejected_reason_string = null;

    foreach ($form['fields'] as $field) {
      $id = $field->id;
      // Age comparison
      if ( $field->type == 'date' ) {
        $glue = ( $field->dateFormat == 'dmy' ? '-' : '/' );
        $date_array = array_filter( $_POST[ 'input_' . $id ] );
        if ( ! empty( $date_array ) ) {
          $birth 	= implode( $glue, $date_array );
          $from 	= new DateTime( $birth );
          $to   	= new DateTime( 'today' );
          $age  	= $from->diff( $to )->y;

          if ( $age < 19 || $age > 31 ) {
            $rejected_array[ 'Age' ] = $age;
          }
        }
        continue;
      }
      // BMI comparison
      if ( $field->label == 'BMI' ) {
      	// set defaults
      	$bmi = 0;
      	// get field id's
      	$bmi_field_id = $form[ 'gforms-edc' ][ 'bmifield' ]; // $this->get_field_id_by_label( $form, 'BMI' );
      	$height_field_id_ft = $form[ 'gforms-edc' ][ 'htftfield' ]; // $this->get_field_id_by_label( $form, 'Height (feet)' );
      	$height_field_id_in = $form[ 'gforms-edc' ][ 'htinfield' ]; // $this->get_field_id_by_label( $form, '(inches)' );
      	$weight_field_id = $form[ 'gforms-edc' ][ 'wtfield' ]; // $this->get_field_id_by_label( $form, 'Weight (lbs)' );
      	// calculate height and weight and convert to metric (m and kg)
      	$height = ( ( ( ( $_POST[ 'input_' . $height_field_id_ft ] ) * 12 ) + $_POST[ 'input_' . $height_field_id_in ] ) * 0.0254 );
      	$weight = ( $_POST[ 'input_' . $weight_field_id ] * 0.453592 );
      	// calculate BMI from formula `w/h^2`
      	if ( $height > 0 && $weight > 0 ) {
	      	$bmi = ( $weight ) / pow( $height, 2 );
	      	$_POST[ 'input_' . $bmi_field_id ] = $bmi;
      	} else {
      		$_POST[ 'input_' . $bmi_field_id ] = 'N/A';
      	}
      	// determine rejection
        if ( $bmi < 18 || $bmi >= 27 ) {
          $rejected_array[ $field->label ] = $_POST[ 'input_' . $field->id ];
        }
        continue;
      }
      // all other standard fields
      if ( isset( $field->rejectVal ) ) {
        $rValArr = explode( ',', $field->rejectVal );
        if ( isset( $_POST[ 'input_' . $field->id ] ) && in_array( $_POST[ 'input_' . $field->id ], $rValArr ) ) {
          $rejected_array[ $field->label ] = $_POST[ 'input_' . $field->id ];
        }
      }

    }

    // Remove empty array values
    $rejected_array = array_filter( $rejected_array );

    // If still exist reasons for rejection
    if ( ! empty( $rejected_array ) ) {

      // Update rejected status to TRUE
      $rejected_status = true;

      // And for each reason, append to final output string
      foreach ( $rejected_array as $key => $value ) {
        $rejected_reason_string .= $key . ' ' . $value . '; ';
      }
    
    }
    
    // Finally, set form values to reflect rejections
    $_POST[ 'input_' . $rejection_status_id ] = ( $rejected_status ? 'Rejected' : 'Approved' );
    $_POST[ 'input_' . $rejection_reason_id ] = $rejected_reason_string;
    
  }

  /**
   * Performing a custom action at the end of the form submission process.
   *
   * @param array $entry The entry currently being processed.
   * @param array $form The form currently being processed.
   */
  public function action_after_submission( $entry, $form ) {

  	$is_duplicate = ( $this->is_duplicate_email( $form, $entry ) ? 'Yes' : 'No' );

  	gform_update_meta( $entry['id'], 'is_duplicate', $is_duplicate );

    $arr = array();

    foreach ($form['fields'] as $field) {
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
    }

    // MANDRILL --------------------------------------------------------------------------------------------------
	  try {
	    $message = array(
	        'html' => '<p>Example HTML content</p>',
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
	    gform_update_meta( $entry['id'], 'mandrill_status', $result[0]['status'] );

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
  public function settings_custom_logic_type( $field, $echo = true ) {

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
  }

  /**
   * Build an array of choices containing fields which are compatible with conditional logic.
   *
   * @return array
   */
  public function get_conditional_logic_fields() {
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
  }

  /**
   * Evaluate the conditional logic.
   *
   * @param array $form The form currently being processed.
   * @param array $entry The entry currently being processed.
   *
   * @return bool
   */
  public function is_custom_logic_met( $form, $entry ) {
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
   * Populate the "My Attribute" tooltip.
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
   * @param onj    $form             An array containing all the current form's properties.
   * @param str   $types             Indicates the type of field to be returned.
   * @param bool  $use_input_type   Optional. Default is false. If available should the fields inputType property be used instead of the type property.
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

  public function is_duplicate_email( $form, $entry ) {
  	$fields = GFAPI::get_fields_by_type( $form, array( 'email' ), true );
  	if ( ! empty( $fields ) ) {
	  	$entries = $this->get_entries_by_key( $form, $fields[0]->id, $entry[ $fields[0]->id ] );
			if ( ! empty( $entries ) && count( $entries ) > 1 ) {
				return true;
			}
			return false;
		}
		return array( 'Error' => 'no email field' );
  }





}
