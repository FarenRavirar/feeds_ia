<?php
/**
 * Carregamento de CSS/JS para as telas administrativas do Feeds IA.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe responsável por registrar e enfileirar os assets do painel.
 */
class Feeds_IA_Admin_Assets {

	/**
	 * Inicializa hooks.
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Enfileira CSS e JS apenas nas telas do plugin Feeds IA.
	 *
	 * @param string $hook_suffix Sufixo da tela atual no admin.
	 */
	public static function enqueue( $hook_suffix ) {
		// Garante que constantes do plugin existam.
		if ( ! defined( 'FEEDS_IA_PLUGIN_URL' ) || ! defined( 'FEEDS_IA_VERSION' ) ) {
			return;
		}

		// Verifica parâmetro "page" da URL (?page=feeds-ia-...).
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		// Só carrega se for uma das telas do Feeds IA.
		if ( '' === $page || 0 !== strpos( $page, 'feeds-ia' ) ) {
			return;
		}

		// CSS administrativo.
		wp_enqueue_style(
			'feeds-ia-admin',
			FEEDS_IA_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			FEEDS_IA_VERSION
		);

		// JS administrativo.
		wp_enqueue_script(
			'feeds-ia-admin',
			FEEDS_IA_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			FEEDS_IA_VERSION,
			true
		);
	}
}
