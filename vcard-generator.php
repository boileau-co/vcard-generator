<?php
/**
 * Plugin Name: vCard Generator
 * Plugin URI:  https://github.com/boileau-co/vcard-generator
 * GitHub Plugin URI: boileau-co/vcard-generator
 * Description: Manage employee contact records and serve them as downloadable .vcf files, with QR code generation and scan tracking.
 * Version:     1.0.2
 * Author:      Boileau & Co.
 * Author URI:  https://boileau.co/
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: vcard-generator
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP:      8.1
 */

namespace VCardGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VCARD_GENERATOR_VERSION', '1.0.2' );
define( 'VCARD_GENERATOR_FILE', __FILE__ );
define( 'VCARD_GENERATOR_DIR', plugin_dir_path( __FILE__ ) );
define( 'VCARD_GENERATOR_URL', plugin_dir_url( __FILE__ ) );

// PSR-4 autoloader for VCardGenerator namespace → src/
spl_autoload_register( function ( string $class ): void {
	$prefix = 'VCardGenerator\\';
	if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
		return;
	}
	$relative = substr( $class, strlen( $prefix ) );
	$file = VCARD_GENERATOR_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
	if ( file_exists( $file ) ) {
		require $file;
	}
} );

// Vendor autoloader (chillerlan/php-qrcode and dependencies).
if ( file_exists( VCARD_GENERATOR_DIR . 'vendor/autoload.php' ) ) {
	require_once VCARD_GENERATOR_DIR . 'vendor/autoload.php';
}

// Plugin Update Checker – self-update from GitHub.
require VCARD_GENERATOR_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$vcard_generator_update_checker = PucFactory::buildUpdateChecker(
	'https://github.com/boileau-co/vcard-generator/',
	__FILE__,
	'vcard-generator'
);
// $vcard_generator_update_checker->setAuthentication( 'your-token-here' );
$vcard_generator_update_checker->setBranch( 'main' );

register_activation_hook( __FILE__, [ Plugin::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ Plugin::class, 'deactivate' ] );

add_action( 'plugins_loaded', function (): void {
	Plugin::get_instance()->init();
} );
