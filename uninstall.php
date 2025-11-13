<?php
/**
 * Rotina de desinstalação do plugin Feeds IA.
 *
 * Este arquivo é executado apenas quando o plugin é REMOVIDO
 * pela interface do WordPress (não apenas desativado).
 *
 * Responsável por:
 * - Remover options usadas pelo plugin.
 * - Remover metadados específicos adicionados aos posts.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove options armazenadas na tabela wp_options.
 */
delete_option( 'feeds_ia_feeds' );
delete_option( 'feeds_ia_ai_settings' );
delete_option( 'feeds_ia_general' );
delete_option( 'feeds_ia_logs' );

/**
 * Remove metadados adicionados aos posts pelo plugin.
 *
 * Obs.: delete_post_meta_by_key remove a meta para TODOS os posts
 * em que a chave estiver presente.
 */
if ( function_exists( 'delete_post_meta_by_key' ) ) {
	$meta_keys = array(
		'_feeds_ia_original_link',
		'_feeds_ia_original_guid',
		'_feeds_ia_feed_id',
		'_feeds_ia_summary',
		'_feeds_ia_model',
		'_feeds_ia_hash',
	);

	foreach ( $meta_keys as $meta_key ) {
		delete_post_meta_by_key( $meta_key );
	}
}
