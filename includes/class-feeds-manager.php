<?php
/**
 * Gerenciador de feeds RSS do plugin Feeds IA.
 *
 * Responsável por:
 * - Ler cada feed RSS configurado.
 * - Normalizar itens em uma estrutura padrão.
 * - Deduplicar itens já importados (por GUID/link).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe Feeds_IA_Feeds_Manager
 */
class Feeds_IA_Feeds_Manager {

	/**
	 * Retorna uma lista de novos itens para um feed específico.
	 *
	 * Cada item retornado segue a estrutura:
	 * [
	 *   'feed_id'      => string,
	 *   'title'        => string,
	 *   'content_raw'  => string,
	 *   'link'         => string,
	 *   'image_url'    => string|null,
	 *   'tags'         => string[] (nomes das categorias/tags do feed),
	 *   'published_at' => int (timestamp Unix),
	 *   'guid'         => string,
	 * ]
	 *
	 * @param array $feed_config Configuração de um feed (id, url, etc.).
	 * @return array
	 */
	public static function get_new_items_for_feed( array $feed_config ) {
		$feed = Feeds_IA_Settings::sanitize_feed_config( $feed_config );

		if ( empty( $feed['url'] ) ) {
			return array();
		}

		// Garante que as funções de feed do WP estejam disponíveis.
		if ( ! function_exists( 'fetch_feed' ) ) {
			require_once ABSPATH . WPINC . '/feed.php';
		}

		$feed_obj = fetch_feed( $feed['url'] );

		if ( is_wp_error( $feed_obj ) ) {
			// Se o logger existir, registra erro de feed.
			if ( class_exists( 'Feeds_IA_Logger' ) && method_exists( 'Feeds_IA_Logger', 'log' ) ) {
				Feeds_IA_Logger::log(
					array(
						'status'   => 'error-feed',
						'feed_id'  => $feed['id'],
						'message'  => sprintf(
							'Erro ao ler feed %s: %s',
							$feed['url'],
							$feed_obj->get_error_message()
						),
						'post_id'  => null,
						'title_original'  => '',
						'title_generated' => '',
					)
				);
			}

			return array();
		}

		$max_items = max( 1, intval( $feed['items_per_run'] ) );

		// Busca mais itens do que o limite, para compensar deduplicação e filtros.
		$buffer_items = $max_items * 3;

		$items = $feed_obj->get_items( 0, $buffer_items );

		if ( empty( $items ) ) {
			return array();
		}

		$new_items = array();

		foreach ( $items as $item ) {
			// GUID e link.
			$guid = $item->get_id();
			$link = $item->get_permalink();

			$link = is_string( $link ) ? esc_url_raw( $link ) : '';

			// Data de publicação (timestamp Unix).
			$published_ts = $item->get_date( 'U' );
			if ( ! $published_ts ) {
				$published_ts = time();
			} else {
				$published_ts = intval( $published_ts );
			}

			// Se houver last_run, ignora itens anteriores a esse momento.
			if ( ! empty( $feed['last_run'] ) && $feed['last_run'] > 0 ) {
				if ( $published_ts <= intval( $feed['last_run'] ) ) {
					continue;
				}
			}

			// Deduplicação por GUID / link nos posts já criados.
			if ( self::post_exists_by_guid_or_link( $guid, $link ) ) {
				continue;
			}

			// Título.
			$title = $item->get_title();
			if ( ! is_string( $title ) ) {
				$title = '';
			}

			// Conteúdo bruto.
			$content_raw = $item->get_content();
			if ( ! is_string( $content_raw ) || '' === trim( $content_raw ) ) {
				// Alguns feeds usam "description".
				$description = $item->get_description();
				if ( is_string( $description ) ) {
					$content_raw = $description;
				} else {
					$content_raw = '';
				}
			}

			// Tags / categorias do item.
			$tags = array();
			$categories = $item->get_categories();
			if ( ! empty( $categories ) && is_array( $categories ) ) {
				foreach ( $categories as $cat ) {
					$label = $cat->get_label();
					if ( is_string( $label ) && '' !== trim( $label ) ) {
						$tags[] = trim( $label );
					}
				}
			}

			// Imagem principal (tentativa básica: enclosure).
			$image_url = null;
			$enclosure = $item->get_enclosure();
			if ( $enclosure && is_object( $enclosure ) ) {
				$enc_link = $enclosure->get_link();
				if ( is_string( $enc_link ) && '' !== trim( $enc_link ) ) {
					$image_url = esc_url_raw( $enc_link );
				}
			}

			$new_items[] = array(
				'feed_id'      => $feed['id'],
				'title'        => $title,
				'content_raw'  => $content_raw,
				'link'         => $link,
				'image_url'    => $image_url,
				'tags'         => $tags,
				'published_at' => $published_ts,
				'guid'         => is_string( $guid ) ? $guid : '',
			);

			if ( count( $new_items ) >= $max_items ) {
				break;
			}
		}

		return $new_items;
	}

	/**
	 * Verifica se já existe um post WordPress vinculado ao GUID ou ao link original.
	 *
	 * Deduplicação simples:
	 * - Procura posts com meta _feeds_ia_original_guid = $guid
	 * - Ou meta _feeds_ia_original_link = $link
	 *
	 * @param string $guid
	 * @param string $link
	 * @return bool
	 */
	protected static function post_exists_by_guid_or_link( $guid, $link ) {
		$meta_query = array( 'relation' => 'OR' );

		if ( is_string( $guid ) && '' !== trim( $guid ) ) {
			$meta_query[] = array(
				'key'   => '_feeds_ia_original_guid',
				'value' => $guid,
			);
		}

		if ( is_string( $link ) && '' !== trim( $link ) ) {
			$meta_query[] = array(
				'key'   => '_feeds_ia_original_link',
				'value' => $link,
			);
		}

		// Se não tiver GUID nem link, não há como deduplicar.
		if ( count( $meta_query ) <= 1 ) {
			return false;
		}

		$query_args = array(
			'post_type'      => 'post',
			'post_status'    => array( 'publish', 'draft', 'pending', 'future' ),
			'meta_query'     => $meta_query,
			'fields'         => 'ids',
			'posts_per_page' => 1,
			'no_found_rows'  => true,
		);

		$existing = get_posts( $query_args );

		return ! empty( $existing );
	}
}
