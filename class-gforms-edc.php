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

	protected $_mandrill_host = 'smtp.mandrillapp.com';
	protected $_mandrill_port = 587;

	private static $_instance = null;

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
		add_filter( 'gform_submit_button', array( $this, 'form_submit_button' ), 10, 2 );
		add_action( 'gform_pre_submission', array( $this, 'pre_submission' ), 10, 1 );
		add_action( 'gform_after_submission', array( $this, 'after_submission' ), 10, 2 );
		add_action( 'gform_view', array( $this, 'view_form' ), 10, 1 );
		add_action( 'gform_post_paging', array( $this, 'form_paging'), 10, 3 );

		add_action( 'gform_field_advanced_settings', array( $this, 'render_reject_val_setting'), 10, 1 );
		add_action( 'gform_editor_js', array( $this, 'custom_field_setting_js'), 10 );
		add_filter( 'gform_tooltips', array( $this, 'reject_val_tooltip'), 10, 1 );

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
					array( 'field_types' => array( 'poll' ) )
				)
			)
		);

		return array_merge( parent::styles(), $styles );
	}


	// # FRONTEND FUNCTIONS --------------------------------------------------------------------------------------------

	/**
	 * Add the text in the plugin settings to the bottom of the form if enabled for this form.
	 *
	 * @param string $button The string containing the input tag to be filtered.
	 * @param array $form The form currently being displayed.
	 *
	 * @return string
	 */
	function form_submit_button( $button, $form ) {
		$settings = $this->get_form_settings( $form );
		if ( isset( $settings['enabled'] ) && true == $settings['enabled'] ) {
			$text   = $this->get_plugin_setting( 'mytextbox' );
			$button = "<div>{$text}</div>" . $button;
		}

		return $button;
	}


	// # ADMIN FUNCTIONS -----------------------------------------------------------------------------------------------

	/**
	 * Creates a custom page for this add-on.
	 */
	public function plugin_page() {
		echo 'This page appears in the Forms menu';
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
		return array(
			array(
				'title'  => esc_html__( 'EDC Form Settings', 'gforms-edc' ),
				'fields' => array(
					array(
						'label'   => esc_html__( 'Include Rejection Logic', 'gforms-edc' ),
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
	 * Performing a custom action at form paging.
	 *
	 * @param array $form The form currently being processed.
	 * @param array $source_page_number The page coming from.
	 * @param array $current_page_number The page currently viewed.
	 */
	public function form_paging( $form, $source_page_number, $current_page_number ) {
		/*foreach ($form['fields'] as $field) {
			var_dump($field);
		}
  	var_dump($_POST);*/
	}

	/**
	 * Performing a custom action at the end of the form submission process.
	 *
	 * @param array $form The form currently being processed.
	 */
	public function view_form( $form ) {
		// do something...
	}

	/**
	 * Performing a custom action at the end of the form submission process.
	 *
	 * @param array $form The form currently being processed.
	 */
	public function pre_submission( $form ) {

		// This section is used to validate any reasons for rejection, and append the data to the form
		$rejectionStatusId = $this->getFieldIDByLabel( $form, 'Status' );
		$rejectionReasonId = $this->getFieldIDByLabel( $form, 'Rejection Reason' );

		$rejectedStatus = false;
		$rejectedArr = array();
		$rejectedReason = null;

		foreach ($form['fields'] as $field) {

			$id = $field->id;

			// Age comparison
			if ( $field->type == 'date' ) {
				// reject if <19 or >31 yrs
				if ( $field->dateType == 'datefield' ) {
					$glue = ( $field->dateFormat == 'dmy' ? '-' : '/' );
					$birth = implode( $glue, $_POST[ 'input_' . $id ] );

					$from = new DateTime( $birth );
					$to   = new DateTime( 'today' );
					$age  = $from->diff( $to )->y;

					if ( $age < 19 || $age > 31 ) {
						$rejectedArr[ 'Age' ] = $age;
					}
				}
			}
			// BMI comparison
			elseif ( $field->label == 'BMI' ) {
				$bmi = ( isset( $_POST[ 'input_' . $field->id ] ) ? $_POST[ 'input_' . $field->id ] : false );
				if ( $bmi < 18 || $bmi >= 27 ) {
					$rejectedArr[ $field->label ] = $_POST[ 'input_' . $field->id ];
				}
			}
			// all other standard fields
			elseif ( isset( $field->rejectVal ) ) {
				$rValArr = explode( ',', $field->rejectVal );
				if ( isset( $_POST[ 'input_' . $field->id ] ) && in_array( $_POST[ 'input_' . $field->id ], $rValArr ) ) {
					$rejectedArr[ $field->label ] = $_POST[ 'input_' . $field->id ];
				}
			}

		}


		// Remove empty array values
		$rejectedArr = array_filter( $rejectedArr );

		// If still exist reasons for rejection
		if ( ! empty( $rejectedArr ) ) {

			// Update rejected status to TRUE
			$rejectedStatus = true;

			// And for each reason, append to final output string
			foreach ($rejectedArr as $key => $value) {
				$rejectedReason .= $key . ' ' . $value . '; ';
			}
		
		}
		
		// Finally, set form values to reflect rejections
		$_POST['input_' . $rejectionStatusId] = ( $rejectedStatus ? 'Rejected' : 'Approved' );
		$_POST['input_' . $rejectionReasonId] = $rejectedReason;
		
	}

	/**
	 * Performing a custom action at the end of the form submission process.
	 *
	 * @param array $entry The entry currently being processed.
	 * @param array $form The form currently being processed.
	 */
	public function after_submission( $entry, $form ) {

		// Evaluate the rules configured for the custom_logic setting.
		$result = $this->is_custom_logic_met( $form, $entry );

		// var_dump( $entry );
		// var_dump( $form );

		if ( $result ) {
			// Do something awesome because the rules were met.
			// var_dump($entry);
			// var_dump($form);

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

			// var_dump( $arr );
		}
	}


	// # SIMPLE CONDITION EXAMPLE --------------------------------------------------------------------------------------

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
	public function render_reject_val_setting( $position ) {
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
	<?php }

	/**
	 * Custom scripting for our custom field setting.
	 */
	public function custom_field_setting_js() { ?>
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
	<?php }

	/**
	 * Populate the "My Attribute" tooltip.
	 *
	 * @param array $tooltips Existing Gravity Forms tooltips.
	 * @return array The $tooltips array with a new key for
	 * form_field_reject_val.
	 */
	public function reject_val_tooltip( $tooltips ) {
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
		return strlen( $value ) < 10;
	}


	/**
	 * The helper to get field IDs for post-populate
	 *
	 * @param onj $form The form object.
	 * @param string $label The label to compare for id.
	 *
	 * @return bool
	 */
	public function getFieldIDByLabel( $form, $label ){
    foreach($form['fields'] as $field){
      if ($field->label == $label)
        return $field->id;
    }
    return false;
	}

}
