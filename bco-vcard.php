<?php
/**
 * Plugin Name: BCO vCard
 * Plugin URI:  https://boileau.co/plugins/bco-vcard
 * Description: Manage employee contact records and serve them as downloadable .vcf files with QR code generation and scan tracking.
 * Version:     1.0.0
 * Author:      Boileau Creative Operations
 * Author URI:  https://boileau.co/
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bco-vcard
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP:      8.1
 */

namespace BCO\vCard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BCO_VCARD_VERSION', '1.0.0' );
define( 'BCO_VCARD_FILE', __FILE__ );
define( 'BCO_VCARD_DIR', plugin_dir_path( __FILE__ ) );
define( 'BCO_VCARD_URL', plugin_dir_url( __FILE__ ) );

// PSR-4 autoloader for BCO\vCard namespace → src/
spl_autoload_register( function ( string $class ): void {
	$prefix = 'BCO\\vCard\\';
	if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
		return;
	}
	$relative = substr( $class, strlen( $prefix ) );
	$file = BCO_VCARD_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
	if ( file_exists( $file ) ) {
		require $file;
	}
} );

// Vendor autoloader (chillerlan/php-qrcode and dependencies).
if ( file_exists( BCO_VCARD_DIR . 'vendor/autoload.php' ) ) {
	require_once BCO_VCARD_DIR . 'vendor/autoload.php';
}

register_activation_hook( __FILE__, [ Plugin::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ Plugin::class, 'deactivate' ] );

add_action( 'plugins_loaded', function (): void {
	Plugin::get_instance()->init();
} );
