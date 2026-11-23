<?php
/**
 * Plugin Name: Open Form Builder
 * Description: Build your own forms with custom questions and answer types.
 * Version: 0.3.0
 * Author: Your Name
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Bump this number whenever the database structure changes.
define( 'OFB_DB_VERSION', '5' );

register_activation_hook( __FILE__, 'ofb_create_tables' );

function ofb_create_tables() {
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset = $wpdb->get_charset_collate();
    $forms   = $wpdb->prefix . 'ofb_forms';
    $fields  = $wpdb->prefix . 'ofb_fields';
    $subs    = $wpdb->prefix . 'ofb_submissions';

    dbDelta( "CREATE TABLE $forms (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        title varchar(255) NOT NULL,
        description text NULL,
        button_text varchar(100) NOT NULL DEFAULT 'Send',
        button_text varchar(100) NOT NULL DEFAULT 'Send',
        notify_email varchar(255) NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id)
    ) $charset;" );

    dbDelta( "CREATE TABLE $fields (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        form_id bigint(20) unsigned NOT NULL,
        type varchar(50) NOT NULL,
        label varchar(255) NOT NULL,
        help_text text NULL,
        placeholder varchar(255) NULL,
        options longtext NULL,
        width varchar(10) NOT NULL DEFAULT 'full',
        style varchar(20) NOT NULL DEFAULT 'default',
                style varchar(20) NOT NULL DEFAULT 'default',
        cond_field bigint(20) unsigned NOT NULL DEFAULT 0,
        cond_value varchar(255) NULL,
        required tinyint(1) NOT NULL DEFAULT 0,
        sort_order int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY  (id),
        KEY form_id (form_id)
    ) $charset;" );

    dbDelta( "CREATE TABLE $subs (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        form_id bigint(20) unsigned NOT NULL,
        data longtext NOT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY form_id (form_id)
    ) $charset;" );
}

// Runs on every request: if the stored version is old, update the tables once.
add_action( 'plugins_loaded', 'ofb_maybe_upgrade' );

function ofb_maybe_upgrade() {
    if ( get_option( 'ofb_db_version' ) !== OFB_DB_VERSION ) {
        ofb_create_tables();
        update_option( 'ofb_db_version', OFB_DB_VERSION );
    }
}

require_once plugin_dir_path( __FILE__ ) . 'includes/admin.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/frontend.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/submissions.php';