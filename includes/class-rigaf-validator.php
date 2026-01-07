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
            if ( $type === 'file' ) { $file = $this->files[$name] ?? null; if ( $required && ( empty($file) || empty($file['size']) ) ) { $errors[$name] = __('This file is required.','rigaf'); continue; }
                if ( $file && ! empty($file['size']) ) { $ok_types=['image/jpeg','image/png','application/pdf','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document']; if ( ! in_array($file['type'],$ok_types,true) ) { $errors[$name]=__('This file type is not allowed.','rigaf'); } } continue; }
            if ( $type === 'checkbox_group' ) { $arr = isset($this->input[$name])?(array)$this->input[$name]:[]; if ( $required && empty($arr) ) { $errors[$name]=__('Select at least one option.','rigaf'); } continue; }
            if ( $type === 'address' ) { if ( $required ) { foreach(['street','city','state','zip'] as $p){ if ( empty( $this->input[$name.'_'.$p] ) ) { $errors[$name] = __('Complete the address.','rigaf'); break; } } } continue; }
            $val = $this->input[$name] ?? ''; $str = is_array($val) ? implode(', ',$val) : trim((string)$val);
            if ( $required && $str === '' ) { $errors[$name] = __('This field is required.','rigaf'); continue; }
            if ( $str !== '' ) {
                if ( $type === 'email' && ! is_email($str) ) { $errors[$name] = __('Enter a valid email address.','rigaf'); }
                if ( $type === 'tel' && ! preg_match('/^[0-9\-\+\s\(\)]+$/', $str) ) { $errors[$name] = __('Enter a valid phone number.','rigaf'); }
                if ( $type === 'date' && ! preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $str) ) { $errors[$name] = __('Enter a valid date (YYYY-MM-DD).','rigaf'); }
            }
        }
        return $errors;
    }
}
