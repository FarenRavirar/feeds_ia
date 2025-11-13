<?php
/**
 * Loader principal do plugin Feeds IA.
 *
 * Responsável por:
 * - Incluir todas as classes necessárias.
 * - Inicializar componentes centrais (menus, cron, assets).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe Feeds_IA_Loader
 */
class Feeds_IA_Loader {

	/**
	 * Ponto de entrada do loader.
	 *
	 * Deve ser chamado a partir de feeds_ia.php após definição das constantes
	 * FEEDS_IA_PLUGIN_DIR e FEEDS_IA_PLUGIN_URL.
	 */
	public static function init() {
		self::includes();
		self::init_classes();
	}

	/**
	 * Inclui todos os arquivos de classe utilizados pelo plugin.
	 */
	protected static function includes() {
		if ( ! defined( 'FEEDS_IA_PLUGIN_DIR' ) ) {
			return;
		}

		$base = trailingslashit( FEEDS_IA_PLUGIN_DIR ) . 'includes/';

		// Núcleo administrativo.
		require_once $base . 'class-admin-menu.php';
		require_once $base . 'class-admin-assets.php';

		// Configurações.
		require_once $base . 'class-settings.php';

		// Importação e processamento de conteúdo.
		require_once $base . 'class-feeds-manager.php';
		require_once $base . 'class-content-processor.php';

		// IA (interface, fachada e provedor Gemini).
		require_once $base . 'class-ai-interface.php';
		require_once $base . 'class-ai-gemini.php';

		// Publicação.
		require_once $base . 'class-publisher.php';

		// Cron/execução periódica.
		require_once $base . 'class-cron.php';

		// Logs e métricas.
		require_once $base . 'class-logger.php';
		require_once $base . 'class-stats.php';

		// Agendamentos por horário/dia.
		require_once $base . 'class-schedules.php';
	}

	/**
	 * Inicializa componentes que expõem método estático de bootstrap.
	 */
	protected static function init_classes() {
		// Assets administrativos (CSS/JS).
		if ( class_exists( 'Feeds_IA_Admin_Assets' ) ) {
			Feeds_IA_Admin_Assets::init();
		}

		// Menus e páginas do painel.
		if ( class_exists( 'Feeds_IA_Admin_Menu' ) ) {
			Feeds_IA_Admin_Menu::init();
		}

		// Cron e execução agendada.
		if ( class_exists( 'Feeds_IA_Cron' ) ) {
			Feeds_IA_Cron::init();
		}
	}
}
