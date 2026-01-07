<?php
namespace RIGAF;
if ( ! defined( 'ABSPATH' ) ) exit;
class Render {
    public static function form( $form_id ) {
        $fields_json = get_post_meta( $form_id, '_rigaf_fields', true );
        $fields = json_decode( $fields_json, true ); if ( ! is_array( $fields ) ) $fields = [];
        $action = esc_url( admin_url( 'admin-post.php' ) ); $nonce = wp_create_nonce( 'rigaf_submit' );
        $old=[]; $errors=[];
        if ( isset( $_GET['rigaf_err'] ) ) { $store = get_transient( sanitize_key( $_GET['rigaf_err'] ) ); if ( is_array( $store ) ) { $old = $store['old'] ?? []; $errors = $store['errors'] ?? []; delete_transient( sanitize_key( $_GET['rigaf_err'] ) ); } }
        $has_file=false; foreach($fields as $f){ if(($f['type']??'')==='file'){ $has_file=true; break; } }
        ob_start();
        if ( isset( $_GET['rigaf_ok'] ) ) { echo '<div class="rigaf-success" role="status" aria-live="polite">' . esc_html__( 'Thank you. Your submission has been received.', 'rigaf' ) . '</div>'; }
        if ( ! empty( $errors ) ) {
            echo '<div class="rigaf-error-summary" role="alert" aria-live="assertive" tabindex="-1"><p><strong>' . esc_html__( 'There is a problem.', 'rigaf' ) . '</strong> ' . esc_html__( 'Fix the following and resubmit.', 'rigaf' ) . '</p><ul>';
            foreach ( $errors as $key=>$msg ){ if($key==='_global') continue; printf('<li><a href="#%1$s">%2$s</a></li>', esc_attr($key), esc_html($msg)); } echo '</ul></div>';
        }
        echo '<form method="post" action="'.$action.'" '.( $has_file ? 'enctype="multipart/form-data"' : '' ).' novalidate>';
        echo '<input type="hidden" name="action" value="rigaf_submit" /><input type="hidden" name="rigaf_form_id" value="'.absint($form_id).'" /><input type="hidden" name="rigaf_nonce" value="'.esc_attr($nonce).'" />';
        echo '<input type="hidden" name="rigaf_ts" value="'.time().'" />';
        echo '<div class="rigaf-hp" aria-hidden="true" hidden><label>Leave this field empty<input type="text" name="rigaf_hp" value="" tabindex="-1" autocomplete="off"></label></div>';
        foreach ( $fields as $f ) {
            $type=$f['type']??'text'; $name=sanitize_key($f['name']); $label=$f['label']??ucfirst($name); $required=!empty($f['required']); $placeholder=$f['placeholder']??''; $help=$f['help']??''; $options=(isset($f['options']) && is_array($f['options']))?$f['options']:[];
            $field_id=$name; $describedby=$name.'_help'; $err_id=$name.'_error'; $has_error=isset($errors[$name]); $value=isset($old[$name]) ? wp_kses_post($old[$name]) : '';
            echo '<div class="rigaf-field'.( $has_error ? ' rigaf-has-error' : '' ).'">';
            switch($type){
                case 'checkbox':
                    echo '<div class="rigaf-checkbox">'; printf('<input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s aria-describedby="%4$s" %5$s/>', esc_attr($field_id), esc_attr($name), checked($value,'1',false), esc_attr($describedby), $required?'aria-required="true" required':'' ); printf('<label for="%1$s">%2$s%3$s</label>', esc_attr($field_id), esc_html($label), $required?' '.esc_html__('(required)','rigaf'):'' ); echo '</div>'; break;
                case 'checkbox_group':
                    printf('<fieldset aria-describedby="%1$s"><legend>%2$s%3$s</legend>', esc_attr($describedby), esc_html($label), $required?' '.esc_html__('(required)','rigaf'):'' );
                    foreach($options as $opt){ $ov = isset($opt['value'])?$opt['value']:(string)$opt; $ot=isset($opt['label'])?$opt['label']:(string)$opt; $cid=$field_id.'_'.sanitize_key($ov); $checked = ($value && strpos((string)$value,(string)$ov)!==false);
                        echo '<div class="rigaf-checkbox">'; printf('<input type="checkbox" id="%1$s" name="%2$s[]\" value="%3$s" %4$s %5$s/>', esc_attr($cid), esc_attr($name), esc_attr($ov), $checked?'checked':'', $required?'aria-required="true"':'' ); printf('<label for="%1$s">%2$s</label>', esc_attr($cid), esc_html($ot)); echo '</div>'; }
                    echo '</fieldset>'; break;
                case 'radio':
                    printf('<fieldset aria-describedby="%1$s"><legend>%2$s%3$s</legend>', esc_attr($describedby), esc_html($label), $required?' '.esc_html__('(required)','rigaf'):'' );
                    foreach($options as $opt){ $ov = isset($opt['value'])?$opt['value']:(string)$opt; $ot=isset($opt['label'])?$opt['label']:(string)$opt; $rid=$field_id.'_'.sanitize_key($ov);
                        echo '<div>'; printf('<input type="radio" id="%1$s" name="%2$s" value="%3$s" %4$s %5$s/>', esc_attr($rid), esc_attr($name), esc_attr($ov), checked($value,$ov,false), $required?'aria-required="true"':'' ); printf('<label for="%1$s">%2$s</label>', esc_attr($rid), esc_html($ot)); echo '</div>'; }
                    echo '</fieldset>'; break;
                case 'select':
                    printf('<label for="%1$s">%2$s%3$s</label>', esc_attr($field_id), esc_html($label), $required?' '.esc_html__('(required)','rigaf'):'' );
                    printf('<select id="%1$s" name="%2$s" aria-describedby="%3$s" %4$s>', esc_attr($field_id), esc_attr($name), esc_attr($describedby), $required?'aria-required="true" required':'' );
                    echo '<option value="">'.esc_html__('Select an option','rigaf').'</option>'; foreach($options as $opt){ $ov=isset($opt['value'])?$opt['value']:(string)$opt; $ot=isset($opt['label'])?$opt['label']:(string)$opt; printf('<option value="%1$s" %2$s>%3$s</option>', esc_attr($ov), selected($value,$ov,false), esc_html($ot)); } echo '</select>'; break;
                case 'file':
                    $allowed_exts = isset($f['allowed_extensions']) && is_array($f['allowed_extensions']) ? $f['allowed_extensions'] : ['jpg','jpeg','png','gif','pdf','doc','docx','xls','xlsx'];
                    $accept_attr = '.' . implode(',.', array_map('strtolower', $allowed_exts));
                    printf('<label for="%1$s">%2$s%3$s</label>', esc_attr($field_id), esc_html($label), $required?' '.esc_html__('(required)','rigaf'):'' );
                    printf('<input type="file" id="%1$s" name="%2$s" accept="%3$s" aria-describedby="%4$s" %5$s/>', esc_attr($field_id), esc_attr($name), esc_attr($accept_attr), esc_attr($describedby), $required?'aria-required="true" required':'' );
                    break;
                case 'date':
                    printf('<label for="%1$s">%2$s%3$s</label>', esc_attr($field_id), esc_html($label), $required?' '.esc_html__('(required)','rigaf'):'' ); printf('<input type="date" id="%1$s" name="%2$s" value="%3$s" aria-describedby="%4$s" %5$s/>', esc_attr($field_id), esc_attr($name), esc_attr($value), esc_attr($describedby), $required?'aria-required="true" required':'' ); break;
                case 'address':
                    printf('<fieldset aria-describedby="%1$s"><legend>%2$s%3$s</legend>', esc_attr($describedby), esc_html($label), $required?' '.esc_html__('(required)','rigaf'):'' );
                    printf('<label for="%1$s_street">%2$s</label><input id="%1$s_street" name="%3$s_street" value="%4$s"/>', esc_attr($field_id), esc_html__('Street','rigaf'), esc_attr($name), esc_attr( isset($old[$name.'_street'])?$old[$name.'_street']:'' ));
                    printf('<label for="%1$s_city">%2$s</label><input id="%1$s_city" name="%3$s_city" value="%4$s"/>', esc_attr($field_id), esc_html__('City','rigaf'), esc_attr($name), esc_attr( isset($old[$name.'_city'])?$old[$name.'_city']:'' ));
                    printf('<label for="%1$s_state">%2$s</label><input id="%1$s_state" name="%3$s_state" value="%4$s"/>', esc_attr($field_id), esc_html__('State','rigaf'), esc_attr($name), esc_attr( isset($old[$name.'_state'])?$old[$name.'_state']:'' ));
                    printf('<label for="%1$s_zip">%2$s</label><input id="%1$s_zip" name="%3$s_zip" value="%4$s"/>', esc_attr($field_id), esc_html__('ZIP','rigaf'), esc_attr($name), esc_attr( isset($old[$name.'_zip'])?$old[$name.'_zip']:'' ));
                    echo '</fieldset>'; break;
                case 'textarea':
                    printf('<label for="%1$s">%2$s%3$s</label>', esc_attr($field_id), esc_html($label), $required?' '.esc_html__('(required)','rigaf'):'' );
                    printf('<textarea id="%1$s" name="%2$s" rows="5" placeholder="%3$s" aria-describedby="%4$s" %5$s>%6$s</textarea>', esc_attr($field_id), esc_attr($name), esc_attr($placeholder), esc_attr($describedby), $required?'aria-required="true" required':'', esc_textarea($value) ); break;
                default:
                    $input_type = in_array($type,['email','text','tel'],true)?$type:'text';
                    printf('<label for="%1$s">%2$s%3$s</label>', esc_attr($field_id), esc_html($label), $required?' '.esc_html__('(required)','rigaf'):'' );
                    printf('<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" placeholder="%5$s" aria-describedby="%6$s" %7$s/>', esc_attr($input_type), esc_attr($field_id), esc_attr($name), esc_attr($value), esc_attr($placeholder), esc_attr($describedby), $required?'aria-required="true" required':'' );
            }
            if ( $help ) { printf('<div id="%1$s" class="rigaf-helptext">%2$s</div>', esc_attr($describedby), esc_html($help) ); }
            if ( $has_error ) { printf('<div id="%1$s" class="rigaf-error" role="alert">%2$s</div>', esc_attr($err_id), esc_html($errors[$name]) ); }
            echo '</div>';
        }
        echo '<div><button type="submit" class="rigaf-submit">'.esc_html__('Submit','rigaf').'</button></div></form>';
        return ob_get_clean();
    }
}
