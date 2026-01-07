<?php
namespace RIGAF;
if ( ! defined( 'ABSPATH' ) ) exit;
class Export {
    public static function csv( $form_id ) {
        $fields = json_decode( (string) get_post_meta( $form_id, '_rigaf_fields', true ), true ); if ( ! is_array( $fields ) ) $fields = [];
        $headers = ['entry_id','date']; foreach($fields as $f){ $headers[] = $f['name']; }
        $entries = get_posts(['post_type'=>'rigaf_entry','posts_per_page'=>-1,'post_status'=>'any','meta_key'=>'_rigaf_form_id','meta_value'=>$form_id]);
        header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="rigaf_entries_'.$form_id.'.csv"');
        $out = fopen('php://output','w'); fputcsv($out, $headers);
        foreach($entries as $e){ $row = [$e->ID, get_post_time('c', true, $e)]; foreach($fields as $f){ $row[] = get_post_meta($e->ID, $f['name'], true); } fputcsv($out,$row); }
        fclose($out); exit;
    }
}
