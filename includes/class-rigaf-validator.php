<?php
namespace RIGAF;
if ( ! defined( 'ABSPATH' ) ) exit;
class Validator {
    protected $fields; protected $input; protected $files;
    public function __construct( $fields, $input, $files = [] ) { $this->fields = is_array($fields)?$fields:[]; $this->input = is_array($input)?$input:[]; $this->files = is_array($files)?$files:[]; }
    public function validate() {
        $errors = [];
        foreach ( $this->fields as $f ) {
            $name = isset($f['name']) ? $f['name'] : ''; if ( ! $name ) continue;
            $required = ! empty( $f['required'] ); $type = isset($f['type']) ? $f['type'] : 'text';
            if ( $type === 'file' ) {
                $file = $this->files[$name] ?? null;
                if ( $required && ( empty($file) || empty($file['size']) ) ) {
                    $errors[$name] = __('This file is required.','rigaf');
                    continue;
                }
                if ( $file && ! empty($file['size']) ) {
                    // Check for upload errors
                    if ( ! empty( $file['error'] ) && $file['error'] !== UPLOAD_ERR_OK ) {
                        $errors[$name] = __('File upload failed. Please try again.','rigaf');
                        continue;
                    }
                    // Get max file size from field config or use 5MB default
                    $max_size = isset($f['max_size']) ? absint($f['max_size']) : 5242880; // 5MB in bytes
                    if ( $file['size'] > $max_size ) {
                        $errors[$name] = sprintf( __('File size exceeds the maximum allowed size of %s MB.','rigaf'), number_format($max_size / 1048576, 1) );
                        continue;
                    }
                    // Get allowed MIME types from field config or use defaults
                    $allowed_types = isset($f['allowed_types']) && is_array($f['allowed_types'])
                        ? $f['allowed_types']
                        : ['image/jpeg','image/png','image/gif','application/pdf','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
                    // Validate MIME type using WordPress function for better security
                    $filetype = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
                    $real_mime = $filetype['type'];
                    if ( ! $real_mime || ! in_array( $real_mime, $allowed_types, true ) ) {
                        $errors[$name] = __('This file type is not allowed.','rigaf');
                        continue;
                    }
                    // Additional extension validation
                    $allowed_extensions = isset($f['allowed_extensions']) && is_array($f['allowed_extensions'])
                        ? $f['allowed_extensions']
                        : ['jpg','jpeg','png','gif','pdf','doc','docx','xls','xlsx'];
                    $ext = $filetype['ext'];
                    if ( ! $ext || ! in_array( strtolower($ext), array_map('strtolower', $allowed_extensions), true ) ) {
                        $errors[$name] = __('This file extension is not allowed.','rigaf');
                        continue;
                    }
                }
                continue;
            }
            if ( $type === 'checkbox_group' ) { $arr = isset($this->input[$name])?(array)$this->input[$name]:[]; if ( $required && empty($arr) ) { $errors[$name]=__('Select at least one option.','rigaf'); } continue; }
            if ( $type === 'address' ) { if ( $required ) { foreach(['street','city','state','zip'] as $p){ if ( empty( $this->input[$name.'_'.$p] ) ) { $errors[$name] = __('Complete the address.','rigaf'); break; } } } continue; }
            $val = $this->input[$name] ?? ''; $str = is_array($val) ? implode(', ',$val) : trim((string)$val);
            if ( $required && $str === '' ) { $errors[$name] = __('This field is required.','rigaf'); continue; }
            if ( $str !== '' ) {
                if ( $type === 'email' && ! is_email($str) ) { $errors[$name] = __('Enter a valid email address.','rigaf'); }
                if ( $type === 'tel' && ! preg_match('/^[0-9\-\+\s\(\)]+$/', $str) ) { $errors[$name] = __('Enter a valid phone number.','rigaf'); }
                if ( $type === 'date' && ! preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $str) ) { $errors[$name] = __('Enter a valid date (YYYY-MM-DD).','rigaf'); }

                // Advanced validation rules
                // Custom regex pattern
                if ( isset($f['custom_pattern']) && ! empty($f['custom_pattern']) ) {
                    $pattern = $f['custom_pattern'];
                    if ( @preg_match($pattern, '') === false ) {
                        // Invalid regex pattern, skip
                    } elseif ( ! preg_match($pattern, $str) ) {
                        $custom_msg = isset($f['custom_pattern_message']) ? $f['custom_pattern_message'] : __('This field does not match the required format.','rigaf');
                        $errors[$name] = $custom_msg;
                    }
                }

                // Min length validation
                if ( isset($f['min_length']) && is_numeric($f['min_length']) ) {
                    $min = absint($f['min_length']);
                    if ( mb_strlen($str, 'UTF-8') < $min ) {
                        $errors[$name] = sprintf( __('This field must be at least %d characters long.','rigaf'), $min );
                    }
                }

                // Max length validation
                if ( isset($f['max_length']) && is_numeric($f['max_length']) ) {
                    $max = absint($f['max_length']);
                    if ( mb_strlen($str, 'UTF-8') > $max ) {
                        $errors[$name] = sprintf( __('This field must not exceed %d characters.','rigaf'), $max );
                    }
                }

                // Min value validation (for numeric fields)
                if ( isset($f['min_value']) && is_numeric($f['min_value']) ) {
                    $numeric_value = floatval($str);
                    $min_val = floatval($f['min_value']);
                    if ( is_numeric($str) && $numeric_value < $min_val ) {
                        $errors[$name] = sprintf( __('This value must be at least %s.','rigaf'), $f['min_value'] );
                    }
                }

                // Max value validation (for numeric fields)
                if ( isset($f['max_value']) && is_numeric($f['max_value']) ) {
                    $numeric_value = floatval($str);
                    $max_val = floatval($f['max_value']);
                    if ( is_numeric($str) && $numeric_value > $max_val ) {
                        $errors[$name] = sprintf( __('This value must not exceed %s.','rigaf'), $f['max_value'] );
                    }
                }
            }
        }
        return $errors;
    }
}
