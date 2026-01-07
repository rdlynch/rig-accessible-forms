<?php
namespace RIGAF;
if ( ! defined( 'ABSPATH' ) ) exit;
require_once RIGAF_PATH . 'includes/class-rigaf-form-cpt.php';
require_once RIGAF_PATH . 'includes/class-rigaf-render.php';
require_once RIGAF_PATH . 'includes/class-rigaf-validator.php';
require_once RIGAF_PATH . 'includes/class-rigaf-export.php';
class Plugin {
    private static $instance = null;
    public static function instance() { if ( null === self::$instance ) { self::$instance = new self(); } return self::$instance; }
    public function __construct() {
        add_action( 'init', [ $this, 'register_assets' ] );
        add_action( 'init', [ $this, 'register_shortcodes' ] );
        add_action( 'init', [ $this, 'register_cpts' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_a11y_styles' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'frontend_assets' ] );
        add_action( 'admin_post_rigaf_submit', [ $this, 'handle_submit' ] );
        add_action( 'admin_post_nopriv_rigaf_submit', [ $this, 'handle_submit' ] );
        add_action( 'add_meta_boxes', [ $this, 'add_fields_metabox' ] );
        add_action( 'save_post_rigaf_form', [ $this, 'save_fields_json' ] );
        add_action( 'admin_menu', [ $this, 'admin_menu' ] );
        add_action( 'admin_post_rigaf_save_builder', [ $this, 'save_builder' ] );
        add_action( 'admin_post_rigaf_export_csv', [ $this, 'export_csv' ] );
    }
    public function activate() {
        $this->register_cpts();
        flush_rewrite_rules();
    }
    public function register_assets() {
        wp_register_style( 'rigaf-frontend', RIGAF_URL . 'assets/css/frontend.css', [], RIGAF_VERSION, 'all' );
        wp_register_script( 'rigaf-frontend', RIGAF_URL . 'assets/js/frontend.js', [], RIGAF_VERSION, true );
        wp_localize_script( 'rigaf-frontend', 'rigafI18n', ['errorSummaryTitle'=>__('There is a problem','rigaf'),'errorSummaryInstruction'=>__('Fix the following and resubmit.','rigaf')] );
    }
    public function frontend_assets() {}
    public function admin_a11y_styles( $hook ) {
        wp_add_inline_style( 'wp-admin', '.rigaf-help{display:block;margin-top:4px;color:#444;} .rigaf-textarea{width:100%;max-width:700px;} .rigaf-label{font-weight:600;} .rigaf-table input, .rigaf-table select, .rigaf-table textarea{width:100%;} .rigaf-actions .button{margin-right:6px} .rigaf-kb-controls button{margin-right:4px}' );
    }
    public function register_shortcodes() { add_shortcode( 'rigaf_form', [ $this, 'shortcode_form' ] ); }
    public function register_cpts() { \RIGAF\Form_CPT::register(); }
    public function shortcode_form( $atts ) {
        $atts = shortcode_atts( [ 'id' => 0 ], $atts, 'rigaf_form' );
        $form_id = absint( $atts['id'] );
        if ( ! $form_id ) return '';
        wp_enqueue_style( 'rigaf-frontend' ); wp_enqueue_script( 'rigaf-frontend' );
        return \RIGAF\Render::form( $form_id );
    }
    public function handle_submit() {
        if ( ! isset( $_POST['rigaf_nonce'] ) || ! wp_verify_nonce( $_POST['rigaf_nonce'], 'rigaf_submit' ) ) { wp_die( __( 'Invalid request.', 'rigaf' ), 400 ); }
        $form_id = isset( $_POST['rigaf_form_id'] ) ? absint( $_POST['rigaf_form_id'] ) : 0;
        if ( ! $form_id ) wp_die( __( 'Form not found.', 'rigaf' ), 400 );

        // Anti-spam checks
        $errors = [];

        // Check honeypot
        if ( ! empty( $_POST['rigaf_hp'] ) ) {
            $errors['_global'] = __( 'Spam detected.', 'rigaf' );
        }

        // Check referer for additional CSRF protection
        $referer = wp_get_referer();
        if ( ! $referer || ! wp_validate_redirect( $referer, false ) ) {
            wp_die( __( 'Invalid request source.', 'rigaf' ), 403 );
        }

        // Rate limiting by IP
        $user_ip = $this->get_client_ip();
        $rate_limit_key = 'rigaf_rate_' . md5( $user_ip . $form_id );
        $submissions = get_transient( $rate_limit_key );
        $max_submissions = apply_filters( 'rigaf_rate_limit_max', 5, $form_id );
        $rate_window = apply_filters( 'rigaf_rate_limit_window', 900, $form_id ); // 15 minutes

        if ( false === $submissions ) {
            set_transient( $rate_limit_key, 1, $rate_window );
        } else {
            if ( $submissions >= $max_submissions ) {
                wp_die( __( 'Too many submission attempts. Please try again later.', 'rigaf' ), 429 );
            }
            set_transient( $rate_limit_key, $submissions + 1, $rate_window );
        }

        // Timing check - form must be displayed for at least 3 seconds
        if ( isset( $_POST['rigaf_ts'] ) ) {
            $timestamp = absint( $_POST['rigaf_ts'] );
            $elapsed = time() - $timestamp;
            $min_time = apply_filters( 'rigaf_min_submit_time', 3, $form_id );
            if ( $elapsed < $min_time ) {
                $errors['_global'] = __( 'Form submitted too quickly. Please try again.', 'rigaf' );
            }
        }

        $fields = json_decode( (string) get_post_meta( $form_id, '_rigaf_fields', true ), true ); if ( ! is_array( $fields ) ) $fields = [];
        if ( ! empty( $_FILES ) ) { require_once ABSPATH . 'wp-admin/includes/file.php'; }
        $validator = new \RIGAF\Validator( $fields, $_POST, $_FILES );
        $validation_errors = $validator->validate();
        $errors = array_merge( $errors, $validation_errors );
        if ( ! empty( $errors ) ) {
            $store_key = 'rigaf_' . sanitize_key( $_POST['rigaf_nonce'] );
            set_transient( $store_key, [ 'errors' => $errors, 'old' => wp_unslash( $_POST ) ], MINUTE_IN_SECONDS * 10 );
            wp_safe_redirect( add_query_arg( [ 'rigaf_err' => $store_key ], wp_get_referer() ?: home_url() ) ); exit;
        }
        $entry_id = wp_insert_post([ 'post_type'=>'rigaf_entry','post_status'=>'private','post_title'=>'Entry for form #'.$form_id ]);
        $submitted = [];
        foreach ( $fields as $f ) {
            $name = sanitize_key( $f['name'] ); $type = isset( $f['type'] ) ? $f['type'] : 'text'; $val='';
            if ( $type === 'file' ) {
                if ( isset($_FILES[$name]) && $_FILES[$name]['size']>0 ) { $uploaded = wp_handle_upload( $_FILES[$name], ['test_form'=>false] ); if ( empty($uploaded['error']) ) { $val = isset($uploaded['url']) ? $uploaded['url'] : ''; } }
            } elseif ( $type === 'checkbox_group' ) {
                $arr = isset($_POST[$name]) ? (array) $_POST[$name] : []; $val = implode(', ', array_map('sanitize_text_field', wp_unslash($arr)));
            } elseif ( $type === 'address' ) {
                $parts = []; foreach(['street','city','state','zip'] as $p){ $parts[$p] = isset($_POST[$name.'_'.$p]) ? sanitize_text_field( wp_unslash($_POST[$name.'_'.$p]) ) : ''; }
                $val = trim($parts['street'].' | '.$parts['city'].' | '.$parts['state'].' | '.$parts['zip']);
            } else {
                $raw = isset($_POST[$name]) ? wp_unslash($_POST[$name]) : ''; if ( is_array($raw) ) $raw = implode(', ', array_map('sanitize_text_field',$raw)); $val = sanitize_textarea_field($raw);
            }
            $submitted[$name] = $val; if ($entry_id && !is_wp_error($entry_id)) { update_post_meta($entry_id, $name, $val); }
        }
        if ($entry_id && !is_wp_error($entry_id)) { update_post_meta($entry_id, '_rigaf_form_id', $form_id ); }
        $this->maybe_send_notifications( $form_id, $submitted );
        wp_safe_redirect( add_query_arg( [ 'rigaf_ok' => 1 ], wp_get_referer() ?: home_url() ) ); exit;
    }
    private function maybe_send_notifications( $form_id, $data ) {
        $admin_to = sanitize_text_field( get_post_meta( $form_id, '_rigaf_notify_to', true ) );
        $notify_submitter = (bool) get_post_meta( $form_id, '_rigaf_notify_submitter', true );
        $submitter_field = sanitize_key( get_post_meta( $form_id, '_rigaf_submitter_email_field', true ) );
        $subject = (string) get_post_meta( $form_id, '_rigaf_notify_subject', true );
        $body    = (string) get_post_meta( $form_id, '_rigaf_notify_body', true );
        $extra_to = (string) get_post_meta( $form_id, '_rigaf_notify_extra', true );
        $rules_json = (string) get_post_meta( $form_id, '_rigaf_notify_rules', true );
        $rules = json_decode( $rules_json, true ); if ( ! is_array( $rules ) ) $rules = [];
        $repl = function( $text ) use ( $data ) { return preg_replace_callback('/\{field:([a-zA-Z0-9_\-]+)\}/', function($m) use ($data){ $k = sanitize_key($m[1]); return isset($data[$k]) ? $data[$k] : ''; }, $text); };
        $targets = [];
        if ( $admin_to ) $targets[] = $admin_to;
        if ( $extra_to ) { $parts = array_map( 'trim', explode( ',', $extra_to ) ); $targets = array_merge( $targets, $parts ); }
        if ( $notify_submitter && $submitter_field && ! empty( $data[ $submitter_field ] ) && is_email( $data[ $submitter_field ] ) ) { $targets[] = $data[ $submitter_field ]; }
        foreach ( $rules as $rule ) {
            if ( ! isset( $rule['when']['field'], $rule['when']['op'], $rule['when']['value'], $rule['to'] ) ) continue;
            $field = sanitize_key( $rule['when']['field'] ); $op = $rule['when']['op']; $value = $rule['when']['value']; $actual = isset($data[$field]) ? $data[$field] : ''; $ok=false;
            if ( in_array($op,['==','!=','contains','!contains'], true) ) {
                $a=(string)$actual; $b=(string)$value;
                if ($op==='==') $ok=($a===$b);
                if ($op==='!=') $ok=($a!==$b);
                if ($op==='contains') $ok=(strpos($a,$b)!==false);
                if ($op==='!contains') $ok=(strpos($a,$b)===false);
            } else {
                $a=floatval(preg_replace('/[^\d\.\-]/','',(string)$actual)); $b=floatval($value);
                if ($op==='>') $ok=($a>$b);
                if ($op==='>=') $ok=($a>=$b);
                if ($op=== '<') $ok=($a<$b);
                if ($op=== '<=') $ok=($a<=$b);
            }
            if ( $ok && is_email($rule['to']) ) { $targets[] = $rule['to']; }
        }
        $targets = array_filter( array_unique( array_map( 'sanitize_email', $targets ) ) );
        if ( empty( $targets ) ) return;
        $subj = $repl( $subject ?: sprintf( __( 'New submission for "%s"', 'rigaf' ), get_the_title( $form_id ) ) );
        $body_text = $repl( $body );
        if ( empty( $body_text ) ) { $lines=[]; foreach($data as $k=>$v){ $lines[]=$k.': '.$v; } $body_text = implode("\n",$lines); }
        foreach ( $targets as $to ) { wp_mail( $to, $subj, $body_text ); }
    }
    public function add_fields_metabox() {
        add_meta_box('rigaf_fields',__('Form Fields (JSON or use Builder)','rigaf'),[ $this,'render_fields_metabox' ],'rigaf_form','normal','high');
        add_meta_box('rigaf_notify',__('Notifications','rigaf'),[ $this,'render_notify_metabox' ],'rigaf_form','side');
    }
    public function render_fields_metabox( $post ) {
        $json = get_post_meta( $post->ID, '_rigaf_fields', true ); if ( empty($json) ) $json='[]';
        wp_nonce_field( 'rigaf_save_fields', 'rigaf_save_fields_nonce' );
        echo '<p><a class="button button-primary" href="'. esc_url( admin_url('admin.php?page=rigaf_builder&form_id='.$post->ID) ) .'">'. esc_html__('Open Accessible Builder', 'rigaf') .'</a></p>';
        echo '<label for="rigaf_fields_json" class="rigaf-label">' . esc_html__( 'Fields JSON', 'rigaf' ) . '</label>';
        echo '<textarea id="rigaf_fields_json" name="rigaf_fields_json" rows="12" class="rigaf-textarea" aria-describedby="rigaf_fields_help">' . esc_textarea( $json ) . '</textarea>';
        echo '<span id="rigaf_fields_help" class="rigaf-help">' . esc_html__( 'Supported types: text, email, tel, textarea, select, radio, checkbox, checkbox_group, date, file, address.', 'rigaf' ) . '</span>';
    }
    public function render_notify_metabox( $post ) {
        $to = get_post_meta( $post->ID, '_rigaf_notify_to', true );
        $extra = get_post_meta( $post->ID, '_rigaf_notify_extra', true );
        $notify_submitter = (bool) get_post_meta( $post->ID, '_rigaf_notify_submitter', true );
        $submitter_field = get_post_meta( $post->ID, '_rigaf_submitter_email_field', true );
        $subject = get_post_meta( $post->ID, '_rigaf_notify_subject', true );
        $body    = get_post_meta( $post->ID, '_rigaf_notify_body', true );
        $rules   = get_post_meta( $post->ID, '_rigaf_notify_rules', true );
        echo '<label for="rigaf_notify_to" class="rigaf-label">' . esc_html__( 'Notify admin address', 'rigaf' ) . '</label>';
        printf( '<input type="email" id="rigaf_notify_to" name="rigaf_notify_to" value="%s" class="widefat" />', esc_attr( $to ) );
        echo '<span class="rigaf-help">' . esc_html__( 'Primary admin recipient. Leave blank to disable.', 'rigaf' ) . '</span>';
        echo '<label for="rigaf_notify_extra" class="rigaf-label">' . esc_html__( 'Additional recipients (comma separated)', 'rigaf' ) . '</label>';
        printf( '<input type="text" id="rigaf_notify_extra" name="rigaf_notify_extra" value="%s" class="widefat" />', esc_attr( $extra ) );
        echo '<label for="rigaf_notify_submitter" class="rigaf-label">' . esc_html__( 'Notify submitter', 'rigaf' ) . '</label>';
        printf( '<input type="checkbox" id="rigaf_notify_submitter" name="rigaf_notify_submitter" value="1" %s />', checked( $notify_submitter, true, false ) );
        echo '<span class="rigaf-help">' . esc_html__( 'If checked, an email will be sent to the address in the field below.', 'rigaf' ) . '</span>';
        echo '<label for="rigaf_submitter_email_field" class="rigaf-label">' . esc_html__( 'Submitter email field name', 'rigaf' ) . '</label>';
        printf( '<input type="text" id="rigaf_submitter_email_field" name="rigaf_submitter_email_field" value="%s" class="widefat" />', esc_attr( $submitter_field ) );
        echo '<label for="rigaf_notify_subject" class="rigaf-label">' . esc_html__( 'Email subject (tokens like {field:your_name})', 'rigaf' ) . '</label>';
        printf( '<input type="text" id="rigaf_notify_subject" name="rigaf_notify_subject" value="%s" class="widefat" />', esc_attr( $subject ) );
        echo '<label for="rigaf_notify_body" class="rigaf-label">' . esc_html__( 'Email body (plain text; tokens allowed)', 'rigaf' ) . '</label>';
        printf( '<textarea id="rigaf_notify_body" name="rigaf_notify_body" rows="6" class="widefat">%s</textarea>', esc_textarea( $body ) );
        echo '<label for="rigaf_notify_rules" class="rigaf-label">' . esc_html__( 'Conditional notification rules (JSON)', 'rigaf' ) . '</label>';
        printf( '<textarea id="rigaf_notify_rules" name="rigaf_notify_rules" rows="6" class="widefat" aria-describedby="rigaf_rules_help">%s</textarea>', esc_textarea( $rules ) );
        echo '<span id="rigaf_rules_help" class="rigaf-help">' . esc_html__( 'Example: [{"when":{"field":"budget","op":">=","value":100000},"to":"grants@example.com"}]', 'rigaf' ) . '</span>';
    }
    public function save_fields_json( $post_id ) {
        if ( ! isset( $_POST['rigaf_save_fields_nonce'] ) || ! wp_verify_nonce( $_POST['rigaf_save_fields_nonce'], 'rigaf_save_fields' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;
        if ( isset( $_POST['rigaf_fields_json'] ) ) {
            $raw = wp_unslash( $_POST['rigaf_fields_json'] ); $decoded = json_decode( $raw, true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) { update_post_meta( $post_id, '_rigaf_fields', wp_json_encode( $decoded ) ); }
        }
        foreach ( ['rigaf_notify_to','rigaf_notify_extra','rigaf_submitter_email_field','rigaf_notify_subject','rigaf_notify_body','rigaf_notify_rules'] as $k ) {
            if ( isset($_POST[$k]) ) update_post_meta( $post_id, '_' . $k, wp_unslash( $_POST[$k] ) );
        }
        update_post_meta( $post_id, '_rigaf_notify_submitter', isset($_POST['rigaf_notify_submitter']) ? 1 : 0 );
    }
    public function admin_menu() {
        add_submenu_page('edit.php?post_type=rigaf_form',__('Accessible Builder','rigaf'),__('Builder','rigaf'),'edit_posts','rigaf_builder',[ $this,'render_builder_page' ]);
        add_submenu_page('edit.php?post_type=rigaf_form',__('Export CSV','rigaf'),__('Export CSV','rigaf'),'edit_posts','rigaf_export',[ $this,'render_export_page' ]);
    }
    public function render_builder_page() {
        $form_id = isset($_GET['form_id']) ? absint($_GET['form_id']) : 0;
        if ( ! $form_id ) { echo '<div class="wrap"><h1>'. esc_html__('Accessible Builder', 'rigaf') .'</h1><p>'. esc_html__('Choose a form from the Forms screen, then click "Open Accessible Builder".', 'rigaf') .'</p></div>'; return; }
        $fields = json_decode( (string) get_post_meta( $form_id, '_rigaf_fields', true ), true ); if ( ! is_array($fields) ) $fields = [];
        echo '<div class="wrap"><h1>'. esc_html__('Accessible Builder', 'rigaf') .'</h1><p>'. esc_html__('Use the keyboard to reorder fields. Each row has Move Up and Move Down buttons.', 'rigaf') .'</p>';
        echo '<form method="post" action="'. esc_url( admin_url('admin-post.php') ) .'">'; wp_nonce_field('rigaf_save_builder', 'rigaf_save_builder_nonce');
        echo '<input type="hidden" name="action" value="rigaf_save_builder" /><input type="hidden" name="form_id" value="'. esc_attr($form_id) .'" />';
        echo '<table class="widefat rigaf-table" role="grid" aria-label="Form fields"><thead><tr><th>'.esc_html__('Type','rigaf').'</th><th>'.esc_html__('Name','rigaf').'</th><th>'.esc_html__('Label','rigaf').'</th><th>'.esc_html__('Required','rigaf').'</th><th>'.esc_html__('Options (JSON)','rigaf').'</th><th>'.esc_html__('Help','rigaf').'</th><th>'.esc_html__('Actions','rigaf').'</th></tr></thead><tbody id="rigaf-rows">';
        $i=0; foreach($fields as $f){ $type=$f['type']??'text'; $name=$f['name']??''; $label=$f['label']??''; $req=!empty($f['required'])?1:0; $opts=isset($f['options'])?wp_json_encode($f['options']):''; $help=$f['help']??'';
            echo '<tr>';
            printf('<td><select name="fields[%1$d][type]">%2$s</select></td>', $i, self::options_html($type));
            printf('<td><input aria-label="Field name row %1$d" name="fields[%1$d][name]" value="%2$s"/></td>', $i, esc_attr($name));
            printf('<td><input aria-label="Field label row %1$d" name="fields[%1$d][label]" value="%2$s"/></td>', $i, esc_attr($label));
            printf('<td><input type="checkbox" aria-label="Required row %1$d" name="fields[%1$d][required]" value="1" %2$s/></td>', $i, checked($req,1,false));
            printf('<td><textarea aria-label="Options row %1$d" name="fields[%1$d][options]" rows="2">%2$s</textarea></td>', $i, esc_textarea($opts));
            printf('<td><input aria-label="Help row %1$d" name="fields[%1$d][help]" value="%2$s"/></td>', $i, esc_attr($help));
            echo '<td class="rigaf-actions"><div class="rigaf-kb-controls">';
            echo '<button name="move" value="up:'.$i.'" class="button" aria-label="Move row '.$i.' up">'.esc_html__('Move Up','rigaf').'</button>';
            echo '<button name="move" value="down:'.$i.'" class="button" aria-label="Move row '.$i.' down">'.esc_html__('Move Down','rigaf').'</button>';
            echo '<button name="remove" value="'.$i.'" class="button" aria-label="Remove row '.$i.'">'.esc_html__('Remove','rigaf').'</button>';
            echo '</div></td></tr>'; $i++;
        }
        echo '<tr>';
        printf('<td><select name="fields[%1$d][type]">%2$s</select></td>', $i, self::options_html('text'));
        printf('<td><input aria-label="New field name" name="fields[%1$d][name]" value=""/></td>', $i);
        printf('<td><input aria-label="New field label" name="fields[%1$d][label]" value=""/></td>', $i);
        printf('<td><input type="checkbox" aria-label="New field required" name="fields[%1$d][required]" value="1"/></td>', $i);
        printf('<td><textarea aria-label="New field options" name="fields[%1$d][options]" rows="2"></textarea></td>', $i);
        printf('<td><input aria-label="New field help" name="fields[%1$d][help]" value=""/></td>', $i);
        echo '<td></td></tr>';
        echo '</tbody></table>'; submit_button( __('Save Fields','rigaf') ); echo '</form></div>';
    }
    private static function options_html($current){ $types=['text','email','tel','textarea','select','radio','checkbox','checkbox_group','date','file','address']; $out=''; foreach($types as $t){ $out.='<option value="'.esc_attr($t).'" '.selected($current,$t,false).'>'.esc_html(ucfirst(str_replace('_',' ',$t))).'</option>'; } return $out; }
    public function save_builder() {
        if ( ! isset($_POST['rigaf_save_builder_nonce']) || ! wp_verify_nonce($_POST['rigaf_save_builder_nonce'], 'rigaf_save_builder') ) wp_die('Invalid');
        $form_id = isset($_POST['form_id']) ? absint($_POST['form_id']) : 0; if ( ! $form_id ) wp_die('Invalid form'); if ( ! current_user_can('edit_post', $form_id) ) wp_die('Unauthorized');
        $fields = isset($_POST['fields']) ? $_POST['fields'] : []; $clean=[];
        foreach ( $fields as $f ) {
            if ( empty($f['name']) ) continue;
            $row = ['type'=>sanitize_text_field($f['type']),'name'=>sanitize_key($f['name']),'label'=>sanitize_text_field($f['label']),'required'=>!empty($f['required']),'help'=>sanitize_text_field($f['help'])];
            if ( ! empty($f['options']) ) { $opts = json_decode( wp_unslash( $f['options'] ), true ); if ( json_last_error() === JSON_ERROR_NONE && is_array($opts) ) { $row['options'] = $opts; } }
            $clean[] = $row;
        }
        update_post_meta( $form_id, '_rigaf_fields', wp_json_encode( $clean ) ); wp_safe_redirect( admin_url('admin.php?page=rigaf_builder&form_id='.$form_id.'&saved=1') ); exit;
    }
    public function render_export_page() {
        echo '<div class="wrap"><h1>'. esc_html__('Export CSV','rigaf') .'</h1><form method="post" action="'. esc_url( admin_url('admin-post.php') ) .'">';
        wp_nonce_field('rigaf_export_csv','rigaf_export_csv_nonce');
        echo '<input type="hidden" name="action" value="rigaf_export_csv"/><p><label for="form_id" class="rigaf-label">'. esc_html__('Form','rigaf') .'</label><select id="form_id" name="form_id">';
        $forms = get_posts(['post_type'=>'rigaf_form','numberposts'=>-1,'post_status'=>'any']); foreach($forms as $f){ printf('<option value="%1$d">%2$s</option>', $f->ID, esc_html($f->post_title) ); }
        echo '</select></p>'; submit_button( __('Download CSV','rigaf') ); echo '</form></div>';
    }
    public function export_csv() {
        if ( ! isset($_POST['rigaf_export_csv_nonce']) || ! wp_verify_nonce($_POST['rigaf_export_csv_nonce'],'rigaf_export_csv') ) wp_die('Invalid');
        $form_id = isset($_POST['form_id']) ? absint($_POST['form_id']) : 0; if ( ! $form_id ) wp_die('Invalid form'); \RIGAF\Export::csv( $form_id );
    }

    /**
     * Get client IP address with proxy support
     * @return string
     */
    private function get_client_ip() {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ( $ip_keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
                // Handle multiple IPs (X-Forwarded-For can contain multiple IPs)
                if ( strpos( $ip, ',' ) !== false ) {
                    $ips = explode( ',', $ip );
                    $ip = trim( $ips[0] );
                }
                // Validate IP address
                if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) !== false ) {
                    return $ip;
                }
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) !== false ) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }
}
