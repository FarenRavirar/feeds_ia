<?php
/**
 * Logger simples do plugin Feeds IA.
 *
 * Responsável por:
 * - Registrar eventos de importação (sucesso/erro) em uma option.
 * - Fornecer API para leitura filtrada dos logs.
 *
 * Armazenamento:
 * - Option: feeds_ia_logs
 * - Estrutura: array de entradas, cada uma:
 *   [
 *     'timestamp'       => int,
 *     'feed_id'         => string,
 *     'title_original'  => string,
 *     'title_generated' => string,
 *     'status'          => string, // ex.: success, error-feed, error-ai, error-publish, error-image
 *     'message'         => string,
 *     'post_id'         => int|null,
 *   ]
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe Feeds_IA_Logger
 */
class Feeds_IA_Logger {

	/**
	 * Nome da option onde os logs são armazenados.
	 */
	const OPTION_LOGS = 'feeds_ia_logs';

	/**
	 * Número máximo de entradas de log a manter.
	 *
	 * Quando o limite é excedido, as entradas mais antigas são descartadas.
	 */
	const MAX_ENTRIES = 500;

	/**
	 * Registra uma entrada de log.
	 *
	 * Exemplo de chamada:
	 * Feeds_IA_Logger::log([
	 *   'status'          => 'success',
	 *   'feed_id'         => 'feed_abc',
	 *   'feed_name'       => 'Nome do Feed',
	 *   'title_original'  => 'Título original',
	 *   'title_generated' => 'Título gerado',
	 *   'message'         => '',
	 *   'post_id'         => 123,
	 * ]);
	 *
	 * @param array $entry
	 */
	public static function log( array $entry ) {
		$defaults = array(
			'log_at'          => current_time( 'timestamp' ),
			'feed_id'         => '',
			'feed_name'       => '',
			'title_original'  => '',
			'title_generated' => '',
			'status'          => '',
			'message'         => '',
			'post_id'         => null,
		);

		$entry = wp_parse_args( $entry, $defaults );

		$entry['log_at']          = intval( $entry['log_at'] );
		$entry['feed_id']         = sanitize_text_field( $entry['feed_id'] );
		$entry['feed_name']       = sanitize_text_field( $entry['feed_name'] );
		$entry['title_original']  = sanitize_text_field( $entry['title_original'] );
		$entry['title_generated'] = sanitize_text_field( $entry['title_generated'] );
		$entry['status']          = sanitize_key( $entry['status'] );
		$entry['message']         = is_string( $entry['message'] ) ? $entry['message'] : '';
		$entry['post_id']         = ( null === $entry['post_id'] || '' === $entry['post_id'] )
			? null
			: intval( $entry['post_id'] );

		$logs = get_option( self::OPTION_LOGS, array() );
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		// Adiciona entrada ao início (mais recente primeiro).
		array_unshift( $logs, $entry );

		// Garante o limite máximo de entradas.
		if ( count( $logs ) > self::MAX_ENTRIES ) {
			$logs = array_slice( $logs, 0, self::MAX_ENTRIES );
		}

		update_option( self::OPTION_LOGS, $logs, false );
	}

	/**
	 * Retorna logs filtrados.
	 *
	 * Parâmetros aceitos em $args:
	 * [
	 *   'feed_id' => string (opcional),
	 *   'status'  => string (opcional),
	 *   'limit'   => int    (opcional, padrão 50),
	 * ]
	 *
	 * Retorno: array de entradas idênticas às armazenadas.
	 *
	 * @param array $args
	 * @return array
	 */
	public static function get_logs( array $args = array() ) {
		$defaults = array(
			'feed_id' => '',
			'status'  => '',
			'limit'   => 50,
		);
		$args = wp_parse_args( $args, $defaults );

		$filter_feed   = sanitize_text_field( $args['feed_id'] );
		$filter_status = sanitize_key( $args['status'] );
		$limit         = max( 1, intval( $args['limit'] ) );

		$logs = get_option( self::OPTION_LOGS, array() );
		if ( ! is_array( $logs ) || empty( $logs ) ) {
			return array();
		}

		$result = array();

		foreach ( $logs as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			// Sanitiza mínimo ao ler.
			$entry_defaults = array(
				'log_at'          => 0,
				'feed_id'         => '',
				'feed_name'       => '',
				'title_original'  => '',
				'title_generated' => '',
				'status'          => '',
				'message'         => '',
				'post_id'         => null,
			);
			// Compatibilidade com logs antigos que usavam 'timestamp'
			if ( isset( $entry['timestamp'] ) && ! isset( $entry['log_at'] ) ) {
				$entry['log_at'] = $entry['timestamp'];
			}
			$entry = wp_parse_args( $entry, $entry_defaults );

			// Filtro por feed_id, se fornecido.
			if ( '' !== $filter_feed && $entry['feed_id'] !== $filter_feed ) {
				continue;
			}

			// Filtro por status, se fornecido.
			if ( '' !== $filter_status && $entry['status'] !== $filter_status ) {
				continue;
			}

			$result[] = $entry;

			if ( count( $result ) >= $limit ) {
				break;
			}
		}

		return $result;
	}

	/**
	 * Registra o início de uma execução de feed.
	 *
	 * @param array $feed_config Configuração do feed.
	 * @param string $trigger Tipo de execução ('manual', 'cron', 'schedule').
	 */
	public static function log_feed_start( array $feed_config, $trigger = 'manual' ) {
		$feed_name = isset( $feed_config['name'] ) ? $feed_config['name'] : '';
		$feed_id = isset( $feed_config['id'] ) ? $feed_config['id'] : '';

		if ( empty( $feed_name ) && ! empty( $feed_id ) ) {
			$feed_name = $feed_id;
		}

		self::log( array(
			'feed_id'         => $feed_id,
			'feed_name'       => $feed_name,
			'status'          => 'feed-start',
			'message'         => sprintf( 'Iniciando processamento do feed (%s)', $trigger ),
			'title_original'  => '',
			'title_generated' => '',
			'post_id'         => null,
		) );
	}

	/**
	 * Registra o resultado de uma execução de feed.
	 *
	 * @param array $feed_config Configuração do feed.
	 * @param int $items_found Número de itens encontrados.
	 * @param int $posts_created Número de posts criados.
	 * @param int $errors Número de erros.
	 */
	public static function log_feed_result( array $feed_config, $items_found = 0, $posts_created = 0, $errors = 0 ) {
		$feed_name = isset( $feed_config['name'] ) ? $feed_config['name'] : '';
		$feed_id = isset( $feed_config['id'] ) ? $feed_config['id'] : '';

		if ( empty( $feed_name ) && ! empty( $feed_id ) ) {
			$feed_name = $feed_id;
		}

		$status = 'feed-completed';
		if ( $errors > 0 ) {
			$status = 'feed-completed-with-errors';
		}

		$message = sprintf(
			'Processamento concluído: %d itens encontrados, %d rascunhos criados',
			$items_found,
			$posts_created
		);

		if ( $errors > 0 ) {
			$message .= sprintf( ', %d erros', $errors );
		}

		self::log( array(
			'feed_id'         => $feed_id,
			'feed_name'       => $feed_name,
			'status'          => $status,
			'message'         => $message,
			'title_original'  => '',
			'title_generated' => '',
			'post_id'         => null,
		) );
	}

	/**
	 * Limpa todos os logs armazenados.
	 *
	 * Pode ser usado futuramente em uma ação de "Limpar logs" no painel.
	 */
	public static function clear_logs() {
		delete_option( self::OPTION_LOGS );
	}
}
