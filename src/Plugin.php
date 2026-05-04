<?php

namespace VCardGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin {

	private static ?self $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init(): void {
		load_plugin_textdomain( 'vcard-generator', false, dirname( plugin_basename( VCARD_GENERATOR_FILE ) ) . '/languages' );

		PostType::register();
		Fields::register();
		Settings::register();
		Rewrite::register();
		AdminColumns::register();
		ScanTracker::register();
	}

	public static function activate(): void {
		PostType::register();
		Rewrite::flush();
		Settings::set_defaults();
	}

	public static function deactivate(): void {
		Rewrite::flush();
	}
}
