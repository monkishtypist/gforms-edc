<?php

/**
 * It would be nice to create a custom Height field with ft. and in. drop-downs 
 * as a compound field. This would make BMI calculations more reasonable in case 
 * users change field Labels etc.
 *
 */

class My_Custom_Field extends \GF_Field
{
    public $type = 'my_custom_field';
    public function get_form_editor_button()
    {
        return array(
            'group' => 'advanced_fields',
            'text'  => __('My Custom Field', 'gravityforms')
        );
    }
    public function get_form_editor_field_title()
    {
        return __('My Custom Fields', 'gravityforms');
    }
    public function get_form_editor_field_settings()
    {
        return array(
            'label_setting'
        );
    }
    public function get_field_content($value, $force_frontend_label, $form)
    {
        $form_id         = $form['id'];
        $admin_buttons   = $this->get_admin_buttons();
        $field_label     = $this->get_field_label($force_frontend_label, $value);
        $field_id        = is_admin() || $form_id == 0 ? "input_{$this->id}" : 'input_' . $form_id . "_{$this->id}";
        $field_content   = !is_admin() ? '{FIELD}' : $field_content = sprintf("%s<label class='gfield_label' for='%s'>%s</label>{FIELD}", $admin_buttons, $field_id, esc_html($field_label));
        return $field_content;
    }
    public function get_value_entry_detail($value, $currency = '', $use_text = false, $format = 'html', $media = 'screen')
    {
        if (is_array($value)) {
            $items = '';
            foreach ($value as $key => $item) {
                if (!empty($item)) {
                    switch ($format) {
                        case 'text' :
                            $items .= \GFCommon::selection_display($item, $this, $currency, $use_text) . ', ';
                            break;
                        default:
                            $items .= '<li>' . \GFCommon::selection_display( $item, $this, $currency, $use_text ) . '</li>';
                            break;
                    }
                }
            }
            if (empty($items)) {
                return '';
            } elseif ($format == 'text') {
                return substr($items, 0, strlen( $items ) - 2); 
            } else {
                return "<ul class='bulleted'>$items</ul>";
            }
        } else {
            return $value;
        }
    }
}

