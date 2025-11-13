<?php
/**
 * Registro de menus e submenus administrativos do Feeds IA.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe responsável pelos menus do painel.
 */
class Feeds_IA_Admin_Menu {

	/**
	 * Inicializa hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menus' ) );
	}

	/**
	 * Registra menu principal e submenus.
	 */
	public static function register_menus() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Menu principal: Dashboard.
		$hook = add_menu_page(
			__( 'Feeds IA', 'feeds-ia' ),
			__( 'Feeds IA', 'feeds-ia' ),
			'manage_options',
			'feeds-ia-dashboard',
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-rss',
			59
		);

		// Dashboard (subitem espelho).
		add_submenu_page(
			'feeds-ia-dashboard',
			__( 'Feeds IA – Dashboard', 'feeds-ia' ),
			__( 'Dashboard', 'feeds-ia' ),
			'manage_options',
			'feeds-ia-dashboard',
			array( __CLASS__, 'render_dashboard' )
		);

		// Feeds.
		add_submenu_page(
			'feeds-ia-dashboard',
			__( 'Feeds IA – Feeds', 'feeds-ia' ),
			__( 'Feeds', 'feeds-ia' ),
			'manage_options',
			'feeds-ia-feeds',
			array( __CLASS__, 'render_settings_feeds' )
		);

		// IA & Prompt.
		add_submenu_page(
			'feeds-ia-dashboard',
			__( 'Feeds IA – IA & Prompt', 'feeds-ia' ),
			__( 'IA & Prompt', 'feeds-ia' ),
			'manage_options',
			'feeds-ia-ai',
			array( __CLASS__, 'render_settings_ai' )
		);

		// Agendamentos.
		add_submenu_page(
			'feeds-ia-dashboard',
			__( 'Feeds IA – Agendamentos', 'feeds-ia' ),
			__( 'Agendamentos', 'feeds-ia' ),
			'manage_options',
			'feeds-ia-schedules',
			array( __CLASS__, 'render_schedules' )
		);

		// Logs.
		add_submenu_page(
			'feeds-ia-dashboard',
			__( 'Feeds IA – Logs', 'feeds-ia' ),
			__( 'Logs', 'feeds-ia' ),
			'manage_options',
			'feeds-ia-logs',
			array( __CLASS__, 'render_logs' )
		);
	}

	/**
	 * Renderiza a view de Dashboard.
	 */
	public static function render_dashboard() {
		self::include_view( 'dashboard.php' );
	}

	/**
	 * Renderiza a view de configuração de feeds.
	 */
	public static function render_settings_feeds() {
		self::include_view( 'settings-feeds.php' );
	}

	/**
	 * Renderiza a view de configuração de IA & Prompt.
	 */
	public static function render_settings_ai() {
		self::include_view( 'settings-ai.php' );
	}

	/**
	 * Renderiza a view de Agendamentos.
	 */
	public static function render_schedules() {
		self::include_view( 'schedules.php' );
	}

	/**
	 * Renderiza a view de Logs.
	 */
	public static function render_logs() {
		self::include_view( 'logs.php' );
	}

	/**
	 * Inclui arquivo de view dentro de admin/views.
	 *
	 * @param string $file Nome do arquivo de view.
	 */
	protected static function include_view( $file ) {
		if ( ! defined( 'FEEDS_IA_PLUGIN_DIR' ) ) {
			return;
		}

		$path = trailingslashit( FEEDS_IA_PLUGIN_DIR ) . 'admin/views/' . $file;

		if ( file_exists( $path ) ) {
			include $path;
		} else {
			echo '<div class="wrap"><h1>Feeds IA</h1><p>' .
				esc_html__( 'View não encontrada.', 'feeds-ia' ) .
				'</p></div>';
		}
	}
}
