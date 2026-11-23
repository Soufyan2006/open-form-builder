<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ---------- Shortcode: [ofb_form id="1"] ---------- */

add_shortcode('ofb_form', 'ofb_shortcode');

function ofb_shortcode($atts)
{
    global $wpdb;

    $atts = shortcode_atts(array('id' => 0), $atts, 'ofb_form');
    $form_id = absint($atts['id']);

    $form = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ofb_forms WHERE id = %d",
        $form_id
    ));
    if (!$form) {
        return '';
    }

    $fields = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ofb_fields WHERE form_id = %d ORDER BY sort_order ASC, id ASC",
        $form_id
    ));

    $status = isset($_GET['ofb_status']) ? sanitize_key($_GET['ofb_status']) : '';
    $sent = isset($_GET['ofb_form']) ? absint($_GET['ofb_form']) : 0;
    $button = $form->button_text ? $form->button_text : 'Send';

    ob_start();
    ?>
    <div class="ofb-form">
        <h3 class="ofb-title"><?php echo esc_html($form->title); ?></h3>
        <?php if ($form->description): ?>
            <p class="ofb-description"><?php echo esc_html($form->description); ?></p>
        <?php endif; ?>

        <?php if ($sent === $form_id && $status === 'success'): ?>
            <p class="ofb-message ofb-success">Thank you, your answers have been sent.</p>
        <?php elseif ($sent === $form_id && $status === 'error'): ?>
            <p class="ofb-message ofb-error">Please check the form: a required question is empty or an answer is not valid.</p>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="ofb_submit">
            <input type="hidden" name="form_id" value="<?php echo (int) $form_id; ?>">
            <input type="hidden" name="ofb_return" value="<?php echo esc_url(get_permalink()); ?>">
            <?php wp_nonce_field('ofb_submit_' . $form_id); ?>

            <div class="ofb-fields">
                <?php
                $section_number = 0;
                foreach ($fields as $field) {
                    if ($field->type === 'section') {
                        $section_number++;
                    }
                    ofb_render_field($field, $section_number);
                }
                ?>
            </div>

            <button type="submit" class="ofb-submit"><?php echo esc_html($button); ?></button>
        </form>
    </div>
    <?php
    return ob_get_clean();
}

/* ---------- Draw one question ---------- */

function ofb_render_field($field, $section_number = 0)
{
    $name = 'ofb_field_' . (int) $field->id;
    $required = $field->required ? 'required' : '';
    $options = $field->options ? json_decode($field->options, true) : array();
    $options = is_array($options) ? $options : array();
    $place = $field->placeholder ? $field->placeholder : '';
    $width = ($field->width === 'half') ? 'half' : 'full';
    $style = in_array($field->style, array('columns', 'card'), true) ? $field->style : 'default';
    $star = $field->required ? ' <span class="ofb-req">*</span>' : '';

    // Sections are headings, not questions.
    if ($field->type === 'section') {
        echo '<div class="ofb-section ofb-field--full">';
        echo '<span class="ofb-section-number">' . (int) $section_number . '</span>';
        echo '<h4 class="ofb-section-title">' . esc_html($field->label) . '</h4>';
        if ($field->help_text) {
            echo '<div class="ofb-section-text">' . wp_kses_post($field->help_text) . '</div>';
        }
        echo '</div>';
        return;
    }

    $cond_attr = '';
    if ((int) $field->cond_field > 0 && $field->cond_value !== '') {
        $cond_attr = ' data-ofb-cond-field="' . (int) $field->cond_field . '" data-ofb-cond-value="' . esc_attr($field->cond_value) . '" hidden';
    }

    echo '<div class="ofb-field ofb-field--' . esc_attr($field->type) . ' ofb-field--' . esc_attr($width) . '" data-ofb-id="' . (int) $field->id . '"' . $cond_attr . '>';

    $is_group = in_array($field->type, array('yesno', 'radio', 'checkbox'), true);

    // The agreement checkbox puts the question next to the box, so no label here.
    if ($field->type !== 'consent') {
        if ($is_group) {
            echo '<span class="ofb-label">' . esc_html($field->label) . $star . '</span>';
        } else {
            echo '<label class="ofb-label" for="' . esc_attr($name) . '">' . esc_html($field->label) . $star . '</label>';
        }
    }

    switch ($field->type) {

        case 'textarea':
            printf(
                '<textarea id="%1$s" name="%1$s" placeholder="%2$s" rows="5" %3$s></textarea>',
                esc_attr($name),
                esc_attr($place),
                $required
            );
            break;

        case 'dropdown':
            echo '<select id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" ' . $required . '>';
            echo '<option value="">Choose...</option>';
            foreach ($options as $opt) {
                echo '<option value="' . esc_attr($opt) . '">' . esc_html($opt) . '</option>';
            }
            echo '</select>';
            break;

        case 'radio':
        case 'yesno':
        case 'checkbox':
            $choices = ($field->type === 'yesno') ? array('Yes', 'No') : $options;
            $input = ($field->type === 'checkbox') ? 'checkbox' : 'radio';
            // Checkboxes send an array, so the name ends in [].
            $input_name = ($input === 'checkbox') ? $name . '[]' : $name;
            // A browser can't require "at least one" of several checkboxes; PHP checks that.
            $req_attr = ($input === 'radio') ? $required : '';

            echo '<div class="ofb-choices ofb-choices--' . esc_attr($style) . '">';
            foreach ($choices as $opt) {
                echo '<label class="ofb-choice"><input type="' . esc_attr($input) . '" name="' . esc_attr($input_name) . '" value="' . esc_attr($opt) . '" ' . $req_attr . '> <span>' . esc_html($opt) . '</span></label>';
            }
            echo '</div>';
            break;

        case 'consent':
            echo '<label class="ofb-choice ofb-consent"><input type="checkbox" name="' . esc_attr($name) . '" value="1" ' . $required . '> <span>' . esc_html($field->label) . $star . '</span></label>';
            break;

                    case 'file':
            $accept = '.' . implode( ',.', ofb_allowed_extensions() );
            printf( '<input type="file" id="%1$s" name="%1$s" accept="%2$s" %3$s>',
                esc_attr( $name ), esc_attr( $accept ), $required );
            echo '<div class="ofb-help">Allowed: ' . esc_html( strtoupper( implode( ', ', ofb_allowed_extensions() ) ) )
                . '. Max ' . esc_html( size_format( ofb_max_upload_size() ) ) . '.</div>';
            break;

        default:
            $html_types = array(
                'text' => 'text',
                'email' => 'email',
                'phone' => 'tel',
                'date' => 'date',
            );
            $input_type = isset($html_types[$field->type]) ? $html_types[$field->type] : 'text';
            printf(
                '<input type="%1$s" id="%2$s" name="%2$s" placeholder="%3$s" %4$s>',
                esc_attr($input_type),
                esc_attr($name),
                esc_attr($place),
                $required
            );
    }

    if ($field->help_text) {
        echo '<div class="ofb-help">' . wp_kses_post($field->help_text) . '</div>';
    }

    echo '</div>';
}

/* ---------- Handle the submission ---------- */

add_action('admin_post_ofb_submit', 'ofb_handle_submit');
add_action('admin_post_nopriv_ofb_submit', 'ofb_handle_submit');

function ofb_handle_submit()
{
    global $wpdb;

    $form_id = isset($_POST['form_id']) ? absint($_POST['form_id']) : 0;
    check_admin_referer('ofb_submit_' . $form_id);

    $return = isset($_POST['ofb_return']) ? esc_url_raw(wp_unslash($_POST['ofb_return'])) : home_url('/');
    $return = wp_validate_redirect($return, home_url('/'));

    $fields = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ofb_fields WHERE form_id = %d ORDER BY sort_order ASC, id ASC",
        $form_id
    ));

    if (!$fields) {
        wp_safe_redirect($return);
        exit;
    }

    $answers = array();
    $error = false;
    $uploaded = array(); // paths of files saved during this request

    // 1. Work out which questions are visible, using the submitted answers.
    //    This happens once, BEFORE the main loop.
    $submitted = array();
    foreach ($fields as $f) {
        $k = 'ofb_field_' . (int) $f->id;
        $val = isset($_POST[$k]) ? wp_unslash($_POST[$k]) : '';
        $submitted[(int) $f->id] = array_map('sanitize_text_field', array_filter((array) $val, 'is_scalar'));
    }

    $visible = array();
    for ($pass = 0; $pass < 3; $pass++) {
        foreach ($fields as $f) {
            if ((int) $f->cond_field === 0) {
                $visible[(int) $f->id] = true;
                continue;
            }
            $parent_ok = !empty($visible[(int) $f->cond_field]);
            $match = in_array($f->cond_value, $submitted[(int) $f->cond_field] ?? array(), true);
            $visible[(int) $f->id] = $parent_ok && $match;
        }
    }

    // 2. The loop that checks and collects the answers.
    foreach ($fields as $field) {
        // Sections are just headings: nothing to answer.
        if ($field->type === 'section') {
            continue;
        }

        // A hidden question is skipped: not required, not saved.
        if (empty($visible[(int) $field->id])) {
            continue;
        }

        $key = 'ofb_field_' . (int) $field->id;
        $raw = isset($_POST[$key]) ? wp_unslash($_POST[$key]) : '';
        $allowed = $field->options ? json_decode($field->options, true) : array();
        $allowed = is_array($allowed) ? $allowed : array();
        $file_info = null;

        if ($field->type === 'checkbox') {
            // Several answers: keep only the ones that are real options.
            $picked = array();
            foreach ((array) $raw as $item) {
                if (is_array($item)) {
                    continue;
                }
                $item = sanitize_text_field($item);
                if ($item === '') {
                    continue;
                }
                if (!in_array($item, $allowed, true)) {
                    $error = true;
                    continue;
                }
                $picked[] = $item;
            }
            $value = implode(', ', $picked);

        } elseif ($field->type === 'consent') {
            $value = (!is_array($raw) && $raw === '1') ? 'Yes' : '';
            
                    } elseif ( $field->type === 'file' ) {
            $value = '';
            $file  = isset( $_FILES[ $key ] ) ? $_FILES[ $key ] : null;

            if ( $file && isset( $file['error'] ) && $file['error'] !== UPLOAD_ERR_NO_FILE ) {
                $file_info = ofb_handle_upload( $file );
                if ( $file_info ) {
                    $uploaded[] = $file_info['file'];
                    $value      = sanitize_file_name( $file['name'] ); // original name, for display
                } else {
                    $error = true;
                }
            }

        } else {
            $raw = is_array($raw) ? '' : $raw;
            $value = ($field->type === 'textarea') ? sanitize_textarea_field($raw) : sanitize_text_field($raw);

            if ($value !== '') {
                if ($field->type === 'email' && !is_email($value)) {
                    $error = true;
                }
                if (in_array($field->type, array('dropdown', 'radio'), true) && !in_array($value, $allowed, true)) {
                    $error = true;
                }
                if ($field->type === 'yesno' && !in_array($value, array('Yes', 'No'), true)) {
                    $error = true;
                }
            }
        }

        if ($field->required && $value === '') {
            $error = true;
        }

        $answers[$field->id] = array(
            'label' => $field->label,
            'value' => $value,
        );
    }

    if ($error) {
        wp_safe_redirect(add_query_arg(array('ofb_status' => 'error', 'ofb_form' => $form_id), $return));
        exit;
    }

    $wpdb->insert($wpdb->prefix . 'ofb_submissions', array(
        'form_id' => $form_id,
        'data' => wp_json_encode($answers),
        'created_at' => current_time('mysql'),
    ));

    $submission_id = (int) $wpdb->insert_id;
    $form = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ofb_forms WHERE id = %d", $form_id));
    if ($form) {
        ofb_send_notification($form, $answers, $submission_id);
    }

    wp_safe_redirect(add_query_arg(array('ofb_status' => 'success', 'ofb_form' => $form_id), $return));
    exit;
}

/* ---------- Generic styling (override the colours in Elementor or your theme) ---------- */

add_action('wp_head', 'ofb_frontend_css');

function ofb_frontend_css()
{
    ?>
    <style>
        .ofb-form {
            --ofb-accent: #234420;
            --ofb-button: #c04a14;
            --ofb-button-text: #ffffff;
            --ofb-border: #8a9a82;
            --ofb-input-bg: #f6f2eb;
            --ofb-line: #b5b5a5;
        }

        .ofb-fields {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px 26px;
        }

        .ofb-field--full,
        .ofb-section {
            grid-column: 1 / -1;
        }

        .ofb-field--half {
            grid-column: span 1;
        }

        @media (max-width: 640px) {
            .ofb-field--half {
                grid-column: 1 / -1;
            }
        }

        .ofb-label {
            display: block;
            margin-bottom: 8px;
        }

        .ofb-req {
            color: #c0392b;
        }

        .ofb-field input[type="text"],
        .ofb-field input[type="email"],
        .ofb-field input[type="tel"],
        .ofb-field input[type="date"],
        .ofb-field select,
        .ofb-field textarea {
            width: 100%;
            padding: 14px 16px;
            box-sizing: border-box;
            background: var(--ofb-input-bg);
            border: 1px solid var(--ofb-border);
            border-radius: 10px;
        }

        .ofb-help {
            font-style: italic;
            font-size: .92em;
            margin-top: 8px;
        }

        .ofb-section {
            border-top: 1px solid var(--ofb-line);
            padding-top: 28px;
            margin-top: 8px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 14px;
        }

        .ofb-section-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--ofb-accent);
            color: #fff;
        }

        .ofb-section-title {
            margin: 0;
            color: var(--ofb-accent);
        }

        .ofb-section-text {
            flex-basis: 100%;
        }

        .ofb-choices--columns {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px 20px;
        }

        .ofb-choice {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            margin-bottom: 8px;
        }

        .ofb-choices--card .ofb-choice {
            padding: 16px 18px;
            background: var(--ofb-input-bg);
            border: 1px solid var(--ofb-border);
            border-radius: 10px;
        }

        @media (max-width: 640px) {
            .ofb-choices--columns {
                grid-template-columns: 1fr;
            }
        }

        .ofb-submit {
            margin-top: 28px;
            padding: 16px 34px;
            border: 0;
            border-radius: 10px;
            cursor: pointer;
            background: var(--ofb-button);
            color: var(--ofb-button-text);
        }

        .ofb-message {
            padding: 12px;
            border-radius: 6px;
        }

        .ofb-success {
            background: #e6f4ea;
            color: #1e6b34;
        }

        .ofb-error {
            background: #fdecea;
            color: #a12622;
        }
    </style>
    <?php
}
add_action('wp_footer', 'ofb_frontend_js');

function ofb_frontend_js()
{
    ?>
    <script>
        (function () {
            function answers(form, id) {
                var box = form.querySelector('[data-ofb-id="' + id + '"]');
                if (!box || box.hasAttribute('hidden')) { return []; }
                var out = [];
                box.querySelectorAll('input, select, textarea').forEach(function (el) {
                    if ((el.type === 'radio' || el.type === 'checkbox') && !el.checked) { return; }
                    if (el.value !== '') { out.push(el.value); }
                });
                return out;
            }

            function update(form) {
                // Run a few times so chains (A shows B, B shows C) settle.
                for (var pass = 0; pass < 3; pass++) {
                    form.querySelectorAll('[data-ofb-cond-field]').forEach(function (box) {
                        var show = answers(form, box.dataset.ofbCondField).indexOf(box.dataset.ofbCondValue) !== -1;
                        box.hidden = !show;
                        // Hidden fields must not block the browser's required check.
                        box.querySelectorAll('input, select, textarea').forEach(function (el) {
                            if (el.dataset.ofbWasRequired === undefined) {
                                el.dataset.ofbWasRequired = el.required ? '1' : '0';
                            }
                            el.disabled = !show;
                            el.required = show && el.dataset.ofbWasRequired === '1';
                        });
                    });
                }
            }

            document.querySelectorAll('.ofb-form form').forEach(function (form) {
                form.addEventListener('change', function () { update(form); });
                update(form);
            });
        })();
    </script>
    <?php
}
/* ---------- File uploads ---------- */

function ofb_allowed_upload_types() {
    return apply_filters( 'ofb_allowed_upload_types', array(
        'jpg|jpeg' => 'image/jpeg',
        'png'      => 'image/png',
        'pdf'      => 'application/pdf',
        'doc'      => 'application/msword',
        'docx'     => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ) );
}

function ofb_allowed_extensions() {
    $ext = array();
    foreach ( array_keys( ofb_allowed_upload_types() ) as $group ) {
        $ext = array_merge( $ext, explode( '|', $group ) );
    }
    return $ext;
}

function ofb_max_upload_size() {
    return apply_filters( 'ofb_max_upload_size', 5 * MB_IN_BYTES );
}

// Store uploads in their own folder.
function ofb_upload_dir( $dirs ) {
    $dirs['subdir'] = '/ofb';
    $dirs['path']   = $dirs['basedir'] . '/ofb';
    $dirs['url']    = $dirs['baseurl'] . '/ofb';
    return $dirs;
}

// Random file name, original extension kept.
function ofb_unique_filename( $dir, $name, $ext ) {
    return wp_generate_password( 16, false ) . strtolower( $ext );
}

// Returns array( 'file' => path, 'url' => url ) or false.
function ofb_handle_upload( $file ) {
    if ( ! is_array( $file ) || empty( $file['name'] ) || is_array( $file['name'] ) ) {
        return false;
    }
    if ( $file['error'] !== UPLOAD_ERR_OK || $file['size'] > ofb_max_upload_size() ) {
        return false;
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';

    add_filter( 'upload_dir', 'ofb_upload_dir' );
    $result = wp_handle_upload( $file, array(
        'test_form'                => false,
        'mimes'                    => ofb_allowed_upload_types(),
        'unique_filename_callback' => 'ofb_unique_filename',
    ) );
    remove_filter( 'upload_dir', 'ofb_upload_dir' );

    if ( isset( $result['error'] ) ) {
        return false;
    }
    return array( 'file' => $result['file'], 'url' => $result['url'] );
}