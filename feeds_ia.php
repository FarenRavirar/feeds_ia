<?php
/**
 * Plugin Name: Feeds IA
 * Description: Automatiza a importação de feeds RSS com reescrita via IA para criação de posts no WordPress.
 * Version: 0.1.0
 * Author: Artifício RPG
 * Text Domain: feeds-ia
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Constantes básicas do plugin.
 */
define( 'FEEDS_IA_VERSION', '0.1.0' );
define( 'FEEDS_IA_PLUGIN_FILE', __FILE__ );
define( 'FEEDS_IA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FEEDS_IA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Carrega o autoloader do plugin.
 *
 * Observação: este arquivo deve existir em
 * FEEDS_IA_PLUGIN_DIR . 'includes/class-loader.php'
 * antes de o plugin ser ativado.
 */
require_once FEEDS_IA_PLUGIN_DIR . 'includes/class-loader.php';

/**
 * Carrega o domínio de tradução do plugin.
 */
function feeds_ia_load_textdomain() {
	load_plugin_textdomain(
		'feeds-ia',
		false,
		dirname( plugin_basename( FEEDS_IA_PLUGIN_FILE ) ) . '/languages'
	);
}
add_action( 'init', 'feeds_ia_load_textdomain' );

/**
 * Bootstrap do plugin.
 *
 * A inicialização principal é delegada à classe Feeds_IA_Loader,
 * que será responsável por registrar menus, hooks e serviços.
 */
function feeds_ia_bootstrap() {
	if ( class_exists( 'Feeds_IA_Loader' ) ) {
		Feeds_IA_Loader::init();
	}
}
add_action( 'plugins_loaded', 'feeds_ia_bootstrap' );

/**
 * Rotina de ativação do plugin.
 *
 * Deve registrar eventos de cron e estruturas necessárias,
 * se a classe responsável já estiver carregada.
 */
function feeds_ia_activate() {
	// Evita execução direta fora do WordPress.
	if ( ! function_exists( 'is_blog_installed' ) || ! is_blog_installed() ) {
		return;
	}

	// Registra eventos de cron se a classe existir.
	if ( class_exists( 'Feeds_IA_Cron' ) && method_exists( 'Feeds_IA_Cron', 'register_events' ) ) {
		Feeds_IA_Cron::register_events();
	}

	// Poderia haver outras rotinas de instalação aqui (criação de tabela de logs etc.).
}
register_activation_hook( FEEDS_IA_PLUGIN_FILE, 'feeds_ia_activate' );

/**
 * Rotina de desativação do plugin.
 *
 * Deve limpar eventos de cron e outros agendamentos,
 * se a classe responsável já estiver carregada.
 */
function feeds_ia_deactivate() {
	if ( class_exists( 'Feeds_IA_Cron' ) && method_exists( 'Feeds_IA_Cron', 'clear_events' ) ) {
		Feeds_IA_Cron::clear_events();
	}
}
register_deactivation_hook( FEEDS_IA_PLUGIN_FILE, 'feeds_ia_deactivate' );