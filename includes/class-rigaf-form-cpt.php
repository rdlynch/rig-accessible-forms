<?php
namespace RIGAF;
if ( ! defined( 'ABSPATH' ) ) exit;
class Form_CPT {
    public static function register() {
        register_post_type( 'rigaf_form', [
            'public' => false,
            'show_ui' => true,
            'label' => __('Forms','rigaf'),
            'labels' => [
                'name' => __('Forms', 'rigaf'),
                'singular_name' => __('Form', 'rigaf'),
                'add_new' => __('Add New Form', 'rigaf'),
                'add_new_item' => __('Add New Form', 'rigaf'),
                'edit_item' => __('Edit Form', 'rigaf'),
                'new_item' => __('New Form', 'rigaf'),
                'view_item' => __('View Form', 'rigaf'),
                'search_items' => __('Search Forms', 'rigaf'),
                'not_found' => __('No forms found', 'rigaf'),
                'not_found_in_trash' => __('No forms found in trash', 'rigaf'),
            ],
            'supports' => ['title'],
            'menu_icon' => 'dashicons-forms',
            'show_in_rest' => true, // Enable REST API for block editor
            'rest_base' => 'rigaf_form',
        ]);

        register_post_type( 'rigaf_entry', [
            'public' => false,
            'show_ui' => true,
            'label' => __('Entries','rigaf'),
            'labels' => [
                'name' => __('Entries', 'rigaf'),
                'singular_name' => __('Entry', 'rigaf'),
            ],
            'supports' => ['title'],
            'show_in_menu' => 'edit.php?post_type=rigaf_form',
            'show_in_rest' => false,
        ]);
    }
}
