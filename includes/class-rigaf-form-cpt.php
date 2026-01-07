<?php
namespace RIGAF;
if ( ! defined( 'ABSPATH' ) ) exit;
class Form_CPT {
    public static function register() {
        register_post_type( 'rigaf_form', ['public'=>false,'show_ui'=>true,'label'=>__('Forms','rigaf'),'supports'=>['title'],'menu_icon'=>'dashicons-forms'] );
        register_post_type( 'rigaf_entry', ['public'=>false,'show_ui'=>true,'label'=>__('Entries','rigaf'),'supports'=>['title'],'show_in_menu'=>'edit.php?post_type=rigaf_form'] );
    }
}
