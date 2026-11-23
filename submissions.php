<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ---------- Menu ---------- */

add_action( 'admin_menu', 'ofb_register_submissions_menu' );

function ofb_register_submissions_menu() {
    add_submenu_page( 'ofb-forms', 'Submissions', 'Submissions', 'manage_options', 'ofb-submissions', 'ofb_render_submissions_page' );
}

function ofb_submissions_url( $args = array() ) {
    return add_query_arg( $args, admin_url( 'admin.php?page=ofb-submissions' ) );
}

/* ---------- Router ---------- */

function ofb_render_submissions_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $view = isset( $_GET['view'] ) ? absint( $_GET['view'] ) : 0;

    echo '<div class="wrap">';
    if ( $view ) {
        ofb_render_submission_detail( $view );
    } else {
        ofb_render_submission_list();
    }
    echo '</div>';
}

/* ---------- View 1: list ---------- */

function ofb_render_submission_list() {
    global $wpdb;
    $subs_table  = $wpdb->prefix . 'ofb_submissions';
    $forms_table = $wpdb->prefix . 'ofb_forms';

    $form_id  = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
    $paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
    $per_page = 20;
    $offset   = ( $paged - 1 ) * $per_page;

    $forms  = $wpdb->get_results( "SELECT id, title FROM $forms_table ORDER BY title ASC" );
    $titles = array();
    foreach ( $forms as $f ) {
        $titles[ (int) $f->id ] = $f->title;
    }

    // prepare() runs here, so $where is already safe to place in the next queries.
    $where = $form_id ? $wpdb->prepare( 'WHERE form_id = %d', $form_id ) : '';
    $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $subs_table $where" );
    $rows  = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM $subs_table $where ORDER BY id DESC LIMIT %d OFFSET %d",
        $per_page,
        $offset
    ) );
    $pages = max( 1, (int) ceil( $total / $per_page ) );
    ?>
    <h1>Submissions</h1>

    <form method="get" style="margin:12px 0;">
        <input type="hidden" name="page" value="ofb-submissions">
        <select name="form_id">
            <option value="0">All forms</option>
            <?php foreach ( $forms as $f ) : ?>
                <option value="<?php echo (int) $f->id; ?>" <?php selected( $form_id, (int) $f->id ); ?>><?php echo esc_html( $f->title ); ?></option>
            <?php endforeach; ?>
        </select>
        <button class="button">Filter</button>
        <?php if ( $form_id ) : ?>
            <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ofb_export&form_id=' . $form_id ), 'ofb_export_' . $form_id ) ); ?>">Export CSV</a>
        <?php endif; ?>
    </form>

    <?php if ( empty( $rows ) ) : ?>
        <p>No submissions yet.</p>
    <?php else : ?>
        <table class="widefat striped">
            <thead><tr><th>ID</th><th>Date</th><th>Form</th><th>Preview</th><th></th></tr></thead>
            <tbody>
            <?php foreach ( $rows as $row ) :
                $answers = json_decode( $row->data, true );
                $answers = is_array( $answers ) ? $answers : array();
                $preview = array();
                foreach ( $answers as $a ) {
                    if ( isset( $a['value'] ) && $a['value'] !== '' ) {
                        $preview[] = $a['value'];
                    }
                    if ( count( $preview ) >= 3 ) {
                        break;
                    }
                }
                ?>
                <tr>
                    <td><?php echo (int) $row->id; ?></td>
                    <td><?php echo esc_html( $row->created_at ); ?></td>
                    <td><?php echo esc_html( isset( $titles[ (int) $row->form_id ] ) ? $titles[ (int) $row->form_id ] : '(deleted form)' ); ?></td>
                    <td><?php echo esc_html( mb_strimwidth( implode( ' · ', $preview ), 0, 90, '…' ) ); ?></td>
                    <td>
                        <a href="<?php echo esc_url( ofb_submissions_url( array( 'view' => $row->id ) ) ); ?>">View</a> |
                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ofb_delete_submission&id=' . $row->id ), 'ofb_delete_submission_' . $row->id ) ); ?>"
                           onclick="return confirm('Delete this submission?');">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ( $pages > 1 ) : ?>
            <div class="tablenav"><div class="tablenav-pages">
                <?php
                echo wp_kses_post( paginate_links( array(
                    'base'      => add_query_arg( 'paged', '%#%' ),
                    'format'    => '',
                    'current'   => $paged,
                    'total'     => $pages,
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                ) ) );
                ?>
            </div></div>
        <?php endif; ?>
    <?php endif;
}

/* ---------- View 2: one submission ---------- */

function ofb_render_submission_detail( $id ) {
    global $wpdb;

    $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ofb_submissions WHERE id = %d", $id ) );
    if ( ! $row ) {
        echo '<h1>Submission not found</h1><p><a href="' . esc_url( ofb_submissions_url() ) . '">Back</a></p>';
        return;
    }

    $form_title = $wpdb->get_var( $wpdb->prepare( "SELECT title FROM {$wpdb->prefix}ofb_forms WHERE id = %d", $row->form_id ) );
    $answers    = json_decode( $row->data, true );
    $answers    = is_array( $answers ) ? $answers : array();
    ?>
    <h1>Submission #<?php echo (int) $row->id; ?></h1>
    <p>
        <a href="<?php echo esc_url( ofb_submissions_url() ); ?>">&larr; Back to submissions</a>
        &nbsp;|&nbsp; <?php echo esc_html( $form_title ? $form_title : '(deleted form)' ); ?>
        &nbsp;|&nbsp; <?php echo esc_html( $row->created_at ); ?>
    </p>

    <table class="widefat striped" style="max-width:900px;">
        <tbody>
        <?php foreach ( $answers as $a ) : ?>
            <tr>
                <th style="width:35%;"><?php echo esc_html( isset( $a['label'] ) ? $a['label'] : '' ); ?></th>
                <td><?php echo nl2br( esc_html( isset( $a['value'] ) && $a['value'] !== '' ? $a['value'] : '—' ) ); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <p style="margin-top:20px;">
        <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ofb_delete_submission&id=' . $row->id ), 'ofb_delete_submission_' . $row->id ) ); ?>"
           onclick="return confirm('Delete this submission?');">Delete submission</a>
    </p>
    <?php
}

/* ---------- Delete ---------- */

add_action( 'admin_post_ofb_delete_submission', 'ofb_handle_delete_submission' );

function ofb_handle_delete_submission() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Not allowed.' );
    }
    $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
    check_admin_referer( 'ofb_delete_submission_' . $id );

    global $wpdb;
    $wpdb->delete( $wpdb->prefix . 'ofb_submissions', array( 'id' => $id ), array( '%d' ) );

    wp_safe_redirect( ofb_submissions_url() );
    exit;
}

/* ---------- CSV export ---------- */

add_action( 'admin_post_ofb_export', 'ofb_handle_export' );

function ofb_handle_export() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Not allowed.' );
    }
    $form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
    check_admin_referer( 'ofb_export_' . $form_id );

    global $wpdb;
    $fields = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, label, type FROM {$wpdb->prefix}ofb_fields WHERE form_id = %d AND type <> 'section' ORDER BY sort_order ASC, id ASC",
        $form_id
    ) );
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ofb_submissions WHERE form_id = %d ORDER BY id ASC",
        $form_id
    ) );

    nocache_headers();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="form-' . $form_id . '-' . gmdate( 'Y-m-d' ) . '.csv"' );

    $out = fopen( 'php://output', 'w' );
    fwrite( $out, "\xEF\xBB\xBF" ); // BOM so Excel reads accents and € correctly.

    // Semicolon is what Dutch Excel expects.
    $header = array( 'ID', 'Date' );
    foreach ( $fields as $f ) {
        $header[] = $f->label;
    }
    fputcsv( $out, array_map( 'ofb_csv_safe', $header ), ';', '"', '\\' );

    foreach ( $rows as $row ) {
        $answers = json_decode( $row->data, true );
        $answers = is_array( $answers ) ? $answers : array();

        $line = array( $row->id, $row->created_at );
        foreach ( $fields as $f ) {
            $line[] = isset( $answers[ $f->id ]['value'] ) ? $answers[ $f->id ]['value'] : '';
        }
        fputcsv( $out, array_map( 'ofb_csv_safe', $line ), ';', '"', '\\' );
    }

    fclose( $out );
    exit;
}

// Stops "=cmd|..." style formula injection when someone opens the file in Excel.
function ofb_csv_safe( $value ) {
    $value = (string) $value;
    if ( $value !== '' && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
        return "'" . $value;
    }
    return $value;
}

/* ---------- E-mail notification ---------- */

function ofb_send_notification( $form, $answers, $submission_id ) {
    if ( empty( $form->notify_email ) ) {
        return;
    }
    $to = array_filter( array_map( 'trim', explode( ',', $form->notify_email ) ), 'is_email' );
    if ( empty( $to ) ) {
        return;
    }

    $lines = array();
    foreach ( $answers as $a ) {
        $value   = ( $a['value'] !== '' ) ? $a['value'] : '-';
        $lines[] = $a['label'] . ":\n" . $value . "\n";
    }

    $body  = "A new submission was received for \"" . $form->title . "\".\n\n";
    $body .= implode( "\n", $lines );
    $body .= "\nView in the dashboard:\n" . ofb_submissions_url( array( 'view' => $submission_id ) ) . "\n";

    // A failed mail must never break the form, so we ignore the result.
    wp_mail( $to, 'New submission: ' . $form->title, $body );
}