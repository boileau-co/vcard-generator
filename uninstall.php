<?php
/**
 * Runs when the plugin is deleted via the WP admin.
 * Only deletes data if the "On uninstall, delete all data" option is enabled.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$delete_data = (bool) get_option( 'bco_vcard_delete_on_uninstall', false );

if ( ! $delete_data ) {
	return;
}

global $wpdb;

// Delete all bco_vcard posts and their meta.
$post_ids = $wpdb->get_col(
	$wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", 'bco_vcard' )
);

foreach ( $post_ids as $post_id ) {
	wp_delete_post( (int) $post_id, true );
}

// Delete all plugin options.
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE 'bco\_vcard\_%'"
);

// Clean up any orphaned postmeta (posts already deleted above, but just in case).
$wpdb->query(
	"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '\_bco\_vcard\_%'"
);
