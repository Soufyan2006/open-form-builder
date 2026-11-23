<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ---------- Helpers ---------- */

function ofb_field_types() {
    return array(
        'section'  => 'Section heading',
        'text'     => 'Open text field',
        'textarea' => 'Long text',
        'email'    => 'Email',
        'phone'    => 'Phone number',
        'date'     => 'Date',
        'yesno'    => 'Yes / No',
        'dropdown' => 'Dropdown',
        'radio'    => 'Multiple choice (one answer)',
        'checkbox' => 'Checkboxes (multiple answers)',
        'consent'  => 'Agreement checkbox',
        'file'     => 'File upload (coming later)',
    );
}

// Types that need a list of answer options.
function ofb_types_with_options() {
    return array( 'dropdown', 'radio', 'checkbox' );
}

function ofb_back_url( $form_id = 0 ) {
    $url = admin_url( 'admin.php?page=ofb-forms' );
    return $form_id ? $url . '&form_id=' . (int) $form_id : $url;
}

/* ---------- Menu and router ---------- */

add_action( 'admin_menu', 'ofb_register_admin_menu' );

function ofb_register_admin_menu() {
    add_menu_page( 'Forms', 'Forms', 'manage_options', 'ofb-forms', 'ofb_render_admin_page', 'dashicons-feedback', 30 );
}

function ofb_render_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;

    echo '<div class="wrap">';
    if ( $form_id ) {
        ofb_render_edit_form( $form_id );
    } else {
        ofb_render_form_list();
    }
    echo '</div>';
}

/* ---------- View 1: list of forms ---------- */

function ofb_render_form_list() {
    global $wpdb;
    $forms = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}ofb_forms ORDER BY id DESC" );
    ?>
    <h1>Forms</h1>

    <h2>Create a new form</h2>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="ofb_create_form">
        <?php wp_nonce_field( 'ofb_create_form' ); ?>
        <table class="form-table">
            <tr>
                <th><label for="title">Form name</label></th>
                <td><input type="text" id="title" name="title" class="regular-text" required></td>
            </tr>
            <tr>
                <th><label for="description">Description</label></th>
                <td><textarea id="description" name="description" class="large-text" rows="3"></textarea></td>
            </tr>
        </table>
        <?php submit_button( 'Create form' ); ?>
    </form>

    <h2>Your forms</h2>
    <?php if ( empty( $forms ) ) : ?>
        <p>No forms yet.</p>
    <?php else : ?>
        <table class="widefat striped">
            <thead><tr><th>ID</th><th>Name</th><th>Shortcode</th><th>Created</th><th></th></tr></thead>
            <tbody>
            <?php foreach ( $forms as $form ) : ?>
                <tr>
                    <td><?php echo (int) $form->id; ?></td>
                    <td><strong><?php echo esc_html( $form->title ); ?></strong></td>
                    <td><code>[ofb_form id="<?php echo (int) $form->id; ?>"]</code></td>
                    <td><?php echo esc_html( $form->created_at ); ?></td>
                    <td>
                        <a href="<?php echo esc_url( ofb_back_url( $form->id ) ); ?>">Edit</a> |
                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ofb_delete_form&form_id=' . $form->id ), 'ofb_delete_form_' . $form->id ) ); ?>"
                           onclick="return confirm('Delete this form and all its questions?');">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif;
}

/* ---------- View 2: edit one form ---------- */

function ofb_render_edit_form( $form_id ) {
    global $wpdb;

    $form = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ofb_forms WHERE id = %d", $form_id ) );
    if ( ! $form ) {
        echo '<h1>Form not found</h1><p><a href="' . esc_url( ofb_back_url() ) . '">Back to forms</a></p>';
        return;
    }

    $fields = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ofb_fields WHERE form_id = %d ORDER BY sort_order ASC, id ASC",
        $form_id
    ) );
    $types = ofb_field_types();

    // Are we editing an existing question?
    $edit_id = isset( $_GET['field_id'] ) ? absint( $_GET['field_id'] ) : 0;
    $edit    = null;
    foreach ( $fields as $f ) {
        if ( (int) $f->id === $edit_id ) {
            $edit = $f;
        }
    }

    $v = array(
        'type'        => 'text',
        'label'       => '',
        'help_text'   => '',
        'placeholder' => '',
        'options'     => '',
        'width'       => 'full',
        'style'       => 'default',
        'required'    => 0,
        'cond_field'  => 0,
        'cond_value'  => '',
    );
    if ( $edit ) {
        $opts = $edit->options ? json_decode( $edit->options, true ) : array();
        $v    = array(
            'type'        => $edit->type,
            'label'       => $edit->label,
            'help_text'   => (string) $edit->help_text,
            'placeholder' => (string) $edit->placeholder,
            'options'     => is_array( $opts ) ? implode( "\n", $opts ) : '',
            'width'       => $edit->width,
            'style'       => $edit->style,
            'required'    => (int) $edit->required,
            'cond_field'  => (int) $edit->cond_field,
            'cond_value'  => (string) $edit->cond_value,
        );
    }
    ?>
    <h1><?php echo esc_html( $form->title ); ?></h1>
    <p><a href="<?php echo esc_url( ofb_back_url() ); ?>">&larr; Back to forms</a>
       &nbsp;|&nbsp; Shortcode: <code>[ofb_form id="<?php echo (int) $form_id; ?>"]</code></p>

    <h2>Form settings</h2>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="ofb_update_form">
        <input type="hidden" name="form_id" value="<?php echo (int) $form_id; ?>">
        <?php wp_nonce_field( 'ofb_update_form' ); ?>
        <table class="form-table">
            <tr>
                <th><label for="f_title">Form name</label></th>
                <td><input type="text" id="f_title" name="title" class="regular-text" value="<?php echo esc_attr( $form->title ); ?>" required></td>
            </tr>
            <tr>
                <th><label for="f_desc">Description</label></th>
                <td><textarea id="f_desc" name="description" class="large-text" rows="2"><?php echo esc_textarea( $form->description ); ?></textarea></td>
            </tr>
            <tr>
                <th><label for="f_btn">Button text</label></th>
                <td><input type="text" id="f_btn" name="button_text" class="regular-text" value="<?php echo esc_attr( $form->button_text ); ?>"></td>
            </tr>
                        <tr>
                <th><label for="f_notify">Notify by email</label></th>
                <td>
                    <input type="text" id="f_notify" name="notify_email" class="regular-text" value="<?php echo esc_attr( $form->notify_email ); ?>">
                    <p class="description">Optional. Separate several addresses with a comma.</p>
                </td>
            </tr>
        </table>
        <?php submit_button( 'Save settings', 'secondary' ); ?>
    </form>

    <h2>Questions</h2>
    <?php if ( empty( $fields ) ) : ?>
        <p>No questions yet. Add the first one below.</p>
    <?php else : ?>
        <table class="widefat striped">
            <thead><tr><th>#</th><th>Question / title</th><th>Type</th><th>Width</th><th>Options</th><th>Req.</th><th>Order</th><th></th></tr></thead>
            <tbody>
            <?php foreach ( $fields as $i => $field ) :
                $options = $field->options ? json_decode( $field->options, true ) : array();
                $move    = function ( $dir ) use ( $form_id, $field ) {
                    return wp_nonce_url(
                        admin_url( 'admin-post.php?action=ofb_move_field&dir=' . $dir . '&form_id=' . $form_id . '&field_id=' . $field->id ),
                        'ofb_move_field_' . $field->id
                    );
                }; ?>
                <tr>
                    <td><?php echo (int) $i + 1; ?></td>
                    <td><?php echo $field->type === 'section' ? '<strong>' . esc_html( $field->label ) . '</strong>' : esc_html( $field->label ); ?></td>
                    <td><?php echo esc_html( isset( $types[ $field->type ] ) ? $types[ $field->type ] : $field->type ); ?></td>
                    <td><?php echo esc_html( $field->width ); ?></td>
                    <td><?php echo esc_html( is_array( $options ) ? implode( ', ', $options ) : '' ); ?></td>
                    <td><?php echo $field->required ? 'Yes' : 'No'; ?></td>
                    <td>
                        <?php if ( $i > 0 ) : ?><a href="<?php echo esc_url( $move( 'up' ) ); ?>">&uarr;</a><?php endif; ?>
                        <?php if ( $i < count( $fields ) - 1 ) : ?><a href="<?php echo esc_url( $move( 'down' ) ); ?>">&darr;</a><?php endif; ?>
                    </td>
                    <td>
                        <a href="<?php echo esc_url( ofb_back_url( $form_id ) . '&field_id=' . $field->id . '#ofb-question-form' ); ?>">Edit</a> |
                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ofb_delete_field&form_id=' . $form_id . '&field_id=' . $field->id ), 'ofb_delete_field_' . $field->id ) ); ?>"
                           onclick="return confirm('Delete this question?');">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h2 id="ofb-question-form"><?php echo $edit ? 'Edit question' : 'Add a question'; ?></h2>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="ofb_save_field">
        <input type="hidden" name="form_id" value="<?php echo (int) $form_id; ?>">
        <input type="hidden" name="field_id" value="<?php echo $edit ? (int) $edit->id : 0; ?>">
        <?php wp_nonce_field( 'ofb_save_field' ); ?>
        <table class="form-table">
            <tr>
                <th><label for="type">Type</label></th>
                <td>
                    <select id="type" name="type">
                        <?php foreach ( $types as $key => $name ) : ?>
                            <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $v['type'], $key ); ?>><?php echo esc_html( $name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="label">Question / title</label></th>
                <td><input type="text" id="label" name="label" class="large-text" value="<?php echo esc_attr( $v['label'] ); ?>" required></td>
            </tr>
            <tr>
                <th><label for="help_text">Help text</label></th>
                <td>
                    <textarea id="help_text" name="help_text" class="large-text" rows="3"><?php echo esc_textarea( $v['help_text'] ); ?></textarea>
                    <p class="description">Shown under the question (or under a section title). Links and <code>&lt;em&gt;</code> are allowed.</p>
                </td>
            </tr>
            <tr data-ofb-for="text textarea email phone date">
                <th><label for="placeholder">Placeholder</label></th>
                <td><input type="text" id="placeholder" name="placeholder" class="regular-text" value="<?php echo esc_attr( $v['placeholder'] ); ?>"></td>
            </tr>
            <tr data-ofb-for="dropdown radio checkbox">
                <th><label for="options">Answer options</label></th>
                <td>
                    <textarea id="options" name="options" class="large-text" rows="6"><?php echo esc_textarea( $v['options'] ); ?></textarea>
                    <p class="description">One option per line.</p>
                </td>
            </tr>
            <tr data-ofb-for="radio checkbox">
                <th><label for="style">Layout of the options</label></th>
                <td>
                    <select id="style" name="style">
                        <option value="default" <?php selected( $v['style'], 'default' ); ?>>List</option>
                        <option value="columns" <?php selected( $v['style'], 'columns' ); ?>>Two columns</option>
                        <option value="card" <?php selected( $v['style'], 'card' ); ?>>Cards</option>
                    </select>
                </td>
            </tr>
                        <tr>
                <th><label for="cond_field">Show only if</label></th>
                <td>
                    <select id="cond_field" name="cond_field">
                        <option value="0">Always show</option>
                        <?php foreach ( $fields as $f ) :
                            if ( $f->type === 'section' || $f->type === 'file' || ( $edit && (int) $f->id === (int) $edit->id ) ) {
                                continue;
                            } ?>
                            <option value="<?php echo (int) $f->id; ?>" <?php selected( $v['cond_field'], (int) $f->id ); ?>><?php echo esc_html( $f->label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    is
                    <input type="text" name="cond_value" class="regular-text" value="<?php echo esc_attr( $v['cond_value'] ); ?>" placeholder="exact answer, e.g. Other (please specify)">
                    <p class="description">The question is only shown when the chosen question has exactly this answer. For checkboxes it also works when the answer is one of the ticked options.</p>
                </td>
            </tr>
            <tr>
                <th><label for="width">Width</label></th>
                <td>
                    <select id="width" name="width">
                        <option value="full" <?php selected( $v['width'], 'full' ); ?>>Full width</option>
                        <option value="half" <?php selected( $v['width'], 'half' ); ?>>Half (two fields side by side)</option>
                    </select>
                </td>
            </tr>
            <tr data-ofb-hide-for="section">
                <th>Required</th>
                <td><label><input type="checkbox" name="required" value="1" <?php checked( $v['required'], 1 ); ?>> This question must be answered</label></td>
            </tr>
        </table>
        <?php submit_button( $edit ? 'Save question' : 'Add question', 'primary', 'submit', false ); ?>
        <?php if ( $edit ) : ?>
            <a href="<?php echo esc_url( ofb_back_url( $form_id ) ); ?>" class="button">Cancel</a>
        <?php endif; ?>
    </form>

    <script>
    // Show only the settings that make sense for the chosen type.
    (function () {
        var type = document.getElementById('type');
        var rows = document.querySelectorAll('[data-ofb-for], [data-ofb-hide-for]');
        function update() {
            var t = type.value;
            rows.forEach(function (row) {
                var show = true;
                if (row.dataset.ofbFor) {
                    show = row.dataset.ofbFor.split(' ').indexOf(t) !== -1;
                }
                if (row.dataset.ofbHideFor) {
                    show = row.dataset.ofbHideFor.split(' ').indexOf(t) === -1;
                }
                row.style.display = show ? '' : 'none';
            });
        }
        type.addEventListener('change', update);
        update();
    })();
    </script>
    <?php
}

/* ---------- Handlers ---------- */

add_action( 'admin_post_ofb_create_form', 'ofb_handle_create_form' );

function ofb_handle_create_form() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Not allowed.' );
    }
    check_admin_referer( 'ofb_create_form' );

    global $wpdb;
    $title = sanitize_text_field( wp_unslash( isset( $_POST['title'] ) ? $_POST['title'] : '' ) );
    $desc  = sanitize_textarea_field( wp_unslash( isset( $_POST['description'] ) ? $_POST['description'] : '' ) );

    if ( $title === '' ) {
        wp_safe_redirect( ofb_back_url() );
        exit;
    }

    $wpdb->insert( $wpdb->prefix . 'ofb_forms', array(
        'title'       => $title,
        'description' => $desc,
        'created_at'  => current_time( 'mysql' ),
    ) );

    wp_safe_redirect( ofb_back_url( $wpdb->insert_id ) );
    exit;
}

add_action( 'admin_post_ofb_update_form', 'ofb_handle_update_form' );

function ofb_handle_update_form() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Not allowed.' );
    }
    check_admin_referer( 'ofb_update_form' );

    global $wpdb;
    $form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
    $title   = sanitize_text_field( wp_unslash( isset( $_POST['title'] ) ? $_POST['title'] : '' ) );
    $desc    = sanitize_textarea_field( wp_unslash( isset( $_POST['description'] ) ? $_POST['description'] : '' ) );
        $button  = sanitize_text_field( wp_unslash( isset( $_POST['button_text'] ) ? $_POST['button_text'] : '' ) );
    $notify  = sanitize_text_field( wp_unslash( isset( $_POST['notify_email'] ) ? $_POST['notify_email'] : '' ) );

    // Keep only valid addresses.
    $notify = implode( ', ', array_filter( array_map( 'trim', explode( ',', $notify ) ), 'is_email' ) );

    if ( $form_id && $title !== '' ) {
        $wpdb->update(
            $wpdb->prefix . 'ofb_forms',
            array(
                'title'        => $title,
                'description'  => $desc,
                'button_text'  => $button !== '' ? $button : 'Send',
                'notify_email' => $notify,
            ),
            array( 'id' => $form_id ),
            array( '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );
    }
    wp_safe_redirect( ofb_back_url( $form_id ) );
    exit;
}

// One handler for both adding and editing a question.
add_action( 'admin_post_ofb_save_field', 'ofb_handle_save_field' );

function ofb_handle_save_field() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Not allowed.' );
    }
    check_admin_referer( 'ofb_save_field' );

    global $wpdb;
    $table    = $wpdb->prefix . 'ofb_fields';
    $form_id  = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
    $field_id = isset( $_POST['field_id'] ) ? absint( $_POST['field_id'] ) : 0;
    $type     = isset( $_POST['type'] ) ? sanitize_key( $_POST['type'] ) : '';
    $label    = sanitize_text_field( wp_unslash( isset( $_POST['label'] ) ? $_POST['label'] : '' ) );
    $help     = wp_kses_post( wp_unslash( isset( $_POST['help_text'] ) ? $_POST['help_text'] : '' ) );
    $place    = sanitize_text_field( wp_unslash( isset( $_POST['placeholder'] ) ? $_POST['placeholder'] : '' ) );
    $raw      = sanitize_textarea_field( wp_unslash( isset( $_POST['options'] ) ? $_POST['options'] : '' ) );
    $width    = isset( $_POST['width'] ) ? sanitize_key( $_POST['width'] ) : 'full';
    $style    = isset( $_POST['style'] ) ? sanitize_key( $_POST['style'] ) : 'default';

        $cond_field = isset( $_POST['cond_field'] ) ? absint( $_POST['cond_field'] ) : 0;
    $cond_value = sanitize_text_field( wp_unslash( isset( $_POST['cond_value'] ) ? $_POST['cond_value'] : '' ) );

    // The condition must point at a question of this same form.
    if ( $cond_field ) {
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ofb_fields WHERE id = %d AND form_id = %d",
            $cond_field,
            $form_id
        ) );
        if ( ! $exists || $cond_field === $field_id || $cond_value === '' ) {
            $cond_field = 0;
            $cond_value = '';
        }
    }

    // Whitelists: only accept values we know.
    if ( ! in_array( $width, array( 'full', 'half' ), true ) ) {
        $width = 'full';
    }
    if ( ! in_array( $style, array( 'default', 'columns', 'card' ), true ) ) {
        $style = 'default';
    }
    if ( ! $form_id || $label === '' || ! array_key_exists( $type, ofb_field_types() ) ) {
        wp_safe_redirect( ofb_back_url( $form_id ) );
        exit;
    }

    $options = null;
    if ( in_array( $type, ofb_types_with_options(), true ) ) {
        $lines = array_filter( array_map( 'trim', explode( "\n", $raw ) ), 'strlen' );
        if ( empty( $lines ) ) {
            // A dropdown or choice question without options makes no sense.
            wp_safe_redirect( ofb_back_url( $form_id ) );
            exit;
        }
        $options = wp_json_encode( array_values( $lines ) );
    }

    $data = array(
        'type'        => $type,
        'label'       => $label,
        'help_text'   => $help,
        'placeholder' => $place,
        'options'     => $options,
        'width'       => $width,
        'style'       => $style,
        'required'    => ( $type !== 'section' && ! empty( $_POST['required'] ) ) ? 1 : 0,
        'cond_field'  => $cond_field,
        'cond_value'  => $cond_value,
    );

    if ( $field_id ) {
        // Editing: the form_id in WHERE makes sure the question belongs to this form.
        $wpdb->update( $table, $data, array( 'id' => $field_id, 'form_id' => $form_id ) );
    } else {
        $max = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(sort_order) FROM $table WHERE form_id = %d", $form_id ) );
        $data['form_id']    = $form_id;
        $data['sort_order'] = (int) $max + 1;
        $wpdb->insert( $table, $data );
    }

    wp_safe_redirect( ofb_back_url( $form_id ) );
    exit;
}

add_action( 'admin_post_ofb_move_field', 'ofb_handle_move_field' );

function ofb_handle_move_field() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Not allowed.' );
    }
    $field_id = isset( $_GET['field_id'] ) ? absint( $_GET['field_id'] ) : 0;
    $form_id  = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
    $dir      = ( isset( $_GET['dir'] ) && $_GET['dir'] === 'up' ) ? 'up' : 'down';
    check_admin_referer( 'ofb_move_field_' . $field_id );

    global $wpdb;
    $table = $wpdb->prefix . 'ofb_fields';

    $ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
        "SELECT id FROM $table WHERE form_id = %d ORDER BY sort_order ASC, id ASC",
        $form_id
    ) ) );

    $pos    = array_search( $field_id, $ids, true );
    $target = ( $dir === 'up' ) ? $pos - 1 : $pos + 1;

    if ( $pos !== false && isset( $ids[ $target ] ) ) {
        // Swap the two neighbours, then renumber everything 0,1,2,...
        $tmp           = $ids[ $pos ];
        $ids[ $pos ]   = $ids[ $target ];
        $ids[ $target ] = $tmp;

        foreach ( $ids as $i => $id ) {
            $wpdb->update( $table, array( 'sort_order' => $i ), array( 'id' => $id ), array( '%d' ), array( '%d' ) );
        }
    }

    wp_safe_redirect( ofb_back_url( $form_id ) );
    exit;
}

add_action( 'admin_post_ofb_delete_field', 'ofb_handle_delete_field' );

function ofb_handle_delete_field() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Not allowed.' );
    }
    $field_id = isset( $_GET['field_id'] ) ? absint( $_GET['field_id'] ) : 0;
    $form_id  = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
    check_admin_referer( 'ofb_delete_field_' . $field_id );

    global $wpdb;
    $wpdb->delete( $wpdb->prefix . 'ofb_fields', array( 'id' => $field_id, 'form_id' => $form_id ), array( '%d', '%d' ) );

    wp_safe_redirect( ofb_back_url( $form_id ) );
    exit;
}

add_action( 'admin_post_ofb_delete_form', 'ofb_handle_delete_form' );

function ofb_handle_delete_form() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Not allowed.' );
    }
    $form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
    check_admin_referer( 'ofb_delete_form_' . $form_id );

    global $wpdb;
    $wpdb->delete( $wpdb->prefix . 'ofb_fields', array( 'form_id' => $form_id ), array( '%d' ) );
    $wpdb->delete( $wpdb->prefix . 'ofb_submissions', array( 'form_id' => $form_id ), array( '%d' ) );
    $wpdb->delete( $wpdb->prefix . 'ofb_forms', array( 'id' => $form_id ), array( '%d' ) );

    wp_safe_redirect( ofb_back_url() );
    exit;
}