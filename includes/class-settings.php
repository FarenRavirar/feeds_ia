<?php
/**
 * Configurações centrais do plugin Feeds IA.
 *
 * Armazena:
 * - Feeds cadastrados.
 * - Configurações de IA.
 * - Configurações gerais (ex.: autor padrão).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe Feeds_IA_Settings
 */
class Feeds_IA_Settings {

	/**
	 * Option de feeds.
	 */
	const OPTION_FEEDS = 'feeds_ia_feeds';

	/**
	 * Option de IA.
	 */
	const OPTION_AI = 'feeds_ia_ai_settings';

	/**
	 * Option geral.
	 */
	const OPTION_GENERAL = 'feeds_ia_general';

	/**
	 * Retorna a lista de feeds, já sanitizada.
	 *
	 * @return array
	 */
	public static function get_feeds() {
		$feeds = get_option( self::OPTION_FEEDS, array() );

		if ( ! is_array( $feeds ) ) {
			$feeds = array();
		}

		$out = array();

		foreach ( $feeds as $feed ) {
			$out[] = self::sanitize_feed_config( $feed );
		}

		return $out;
	}

		/**
	 * Salva a lista completa de feeds.
	 *
	 * Aceita dois formatos:
	 *
	 * 1) Formato "linha por linha" (recomendado):
	 *    [
	 *      0 => [ 'id' => ..., 'name' => ..., 'url' => ... ],
	 *      1 => [ ... ],
	 *      ...
	 *    ]
	 *
	 * 2) Formato "por coluna" (name="feeds_ia_feeds[name][]" etc.):
	 *    [
	 *      'id'            => [...],
	 *      'name'          => [...],
	 *      'url'           => [...],
	 *      'category'      => [...],
	 *      'status'        => [...],
	 *      'frequency'     => [...],
	 *      'items_per_run' => [...],
	 *      'mode'          => [...],
	 *      'last_run'      => [...],
	 *    ]
	 *
	 * Em ambos os casos, a saída final em OPTION_FEEDS será
	 * sempre uma lista de feeds sanitizados.
	 *
	 * @param array $feeds Lista de feeds (bruta).
	 */
	public static function save_feeds( array $feeds ) {

		// Caso 2: formato "por coluna" vindo diretamente do $_POST.
		if ( isset( $feeds['url'] ) && is_array( $feeds['url'] ) && ! isset( $feeds[0] ) ) {
			$cols = $feeds;

			$col_ids           = isset( $cols['id'] )            && is_array( $cols['id'] )            ? $cols['id']            : array();
			$col_names         = isset( $cols['name'] )          && is_array( $cols['name'] )          ? $cols['name']          : array();
			$col_urls          = isset( $cols['url'] )           && is_array( $cols['url'] )           ? $cols['url']           : array();
			$col_categories    = isset( $cols['category'] )      && is_array( $cols['category'] )      ? $cols['category']      : array();
			$col_status        = isset( $cols['status'] )        && is_array( $cols['status'] )        ? $cols['status']        : array();
			$col_frequency     = isset( $cols['frequency'] )     && is_array( $cols['frequency'] )     ? $cols['frequency']     : array();
			$col_items_per_run = isset( $cols['items_per_run'] ) && is_array( $cols['items_per_run'] ) ? $cols['items_per_run'] : array();
			$col_mode          = isset( $cols['mode'] )          && is_array( $cols['mode'] )          ? $cols['mode']          : array();
			$col_last_run      = isset( $cols['last_run'] )      && is_array( $cols['last_run'] )      ? $cols['last_run']      : array();

			// Usa o maior tamanho entre as colunas para iterar.
			$max_rows = max(
				count( $col_urls ),
				count( $col_ids ),
				count( $col_names ),
				count( $col_categories ),
				count( $col_status ),
				count( $col_frequency ),
				count( $col_items_per_run ),
				count( $col_mode ),
				count( $col_last_run )
			);

			$rows = array();

			for ( $i = 0; $i < $max_rows; $i++ ) {
				$rows[] = array(
					'id'            => isset( $col_ids[ $i ] )           ? $col_ids[ $i ]           : '',
					'name'          => isset( $col_names[ $i ] )         ? $col_names[ $i ]         : '',
					'url'           => isset( $col_urls[ $i ] )          ? $col_urls[ $i ]          : '',
					'category'      => isset( $col_categories[ $i ] )    ? $col_categories[ $i ]    : '',
					'status'        => isset( $col_status[ $i ] )        ? $col_status[ $i ]        : '',
					'frequency'     => isset( $col_frequency[ $i ] )     ? $col_frequency[ $i ]     : '',
					'items_per_run' => isset( $col_items_per_run[ $i ] ) ? $col_items_per_run[ $i ] : '',
					'mode'          => isset( $col_mode[ $i ] )          ? $col_mode[ $i ]          : '',
					'last_run'      => isset( $col_last_run[ $i ] )      ? $col_last_run[ $i ]      : '',
				);
			}

			$feeds = $rows;
		}

		// A partir daqui, $feeds deve estar no formato "linha por linha".
		$clean = array();

		foreach ( $feeds as $feed ) {
			if ( ! is_array( $feed ) ) {
				continue;
			}

			// Ignora linhas sem URL.
			$url = isset( $feed['url'] ) ? trim( (string) $feed['url'] ) : '';
			if ( '' === $url ) {
				continue;
			}

			$feed['url'] = $url;

			$clean[] = self::sanitize_feed_config( $feed );
		}

		update_option( self::OPTION_FEEDS, $clean );
	}


	/**
	 * Procura um feed pelo ID interno.
	 *
	 * @param string $feed_id ID interno do feed.
	 * @return array|null
	 */
	public static function get_feed_by_id( $feed_id ) {
		$feed_id = sanitize_text_field( $feed_id );

		if ( '' === $feed_id ) {
			return null;
		}

		$feeds = self::get_feeds();

		foreach ( $feeds as $feed ) {
			if ( isset( $feed['id'] ) && $feed['id'] === $feed_id ) {
				return $feed;
			}
		}

		return null;
	}

	/**
	 * Sanitiza a configuração de um feed.
	 *
	 * Estrutura final esperada:
	 * [
	 *   'id'            => string,
	 *   'name'          => string,
	 *   'url'           => string,
	 *   'category'      => int,
	 *   'status'        => 'active'|'inactive',
	 *   'frequency'     => int,   // em minutos
	 *   'items_per_run' => int,
	 *   'mode'          => 'draft'|'publish', // hoje ignorado na publicação (sempre draft)
	 *   'last_run'      => int|null,
	 * ]
	 *
	 * @param array $feed Configuração bruta.
	 * @return array
	 */
	public static function sanitize_feed_config( $feed ) {
		$defaults = array(
			'id'            => '',
			'name'          => '',
			'url'           => '',
			'category'      => 0,
			'status'        => 'active',
			'frequency'     => 60, // minutos
			'items_per_run' => 3,
			'mode'          => 'draft',
			'last_run'      => null,
		);

		$feed = wp_parse_args( is_array( $feed ) ? $feed : array(), $defaults );

		// ID interno do feed.
		$feed['id'] = sanitize_text_field( $feed['id'] );
		if ( '' === $feed['id'] ) {
			$feed['id'] = 'feed_' . wp_generate_password( 8, false, false );
		}

		// Nome.
		$feed['name'] = sanitize_text_field( $feed['name'] );

		// URL do RSS.
		$feed['url'] = esc_url_raw( $feed['url'] );

		// Categoria (ID).
		$feed['category'] = (int) $feed['category'];
		if ( $feed['category'] < 0 ) {
			$feed['category'] = 0;
		}

		// Status.
		$status = sanitize_key( $feed['status'] );
		if ( ! in_array( $status, array( 'active', 'inactive' ), true ) ) {
			$status = 'inactive';
		}
		$feed['status'] = $status;

		// Frequência em minutos.
		$freq = (int) $feed['frequency'];
		if ( $freq <= 0 ) {
			$freq = 60;
		}
		$feed['frequency'] = $freq;

		// Limite de itens por execução.
		$items = (int) $feed['items_per_run'];
		if ( $items <= 0 ) {
			$items = 3;
		}
		$feed['items_per_run'] = $items;

		// Modo (ainda que a publicação force draft, o campo é mantido na configuração).
		$mode = sanitize_key( $feed['mode'] );
		if ( ! in_array( $mode, array( 'draft', 'publish' ), true ) ) {
			$mode = 'draft';
		}
		$feed['mode'] = $mode;

		// last_run.
		if ( null === $feed['last_run'] || '' === $feed['last_run'] ) {
			$feed['last_run'] = null;
		} else {
			$feed['last_run'] = (int) $feed['last_run'];
			if ( $feed['last_run'] <= 0 ) {
				$feed['last_run'] = null;
			}
		}

		return $feed;
	}

	/**
	 * Retorna as configurações de IA, já sanitizadas.
	 *
	 * Estrutura:
	 * [
	 *   'api_key'     => string,
	 *   'model'       => string,
	 *   'temperature' => float,
	 *   'base_prompt' => string,
	 * ]
	 *
	 * @return array
	 */
	public static function get_ai_settings() {
		$settings = get_option( self::OPTION_AI, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$defaults = array(
			'api_key'     => '',
			'model'       => '',
			'temperature' => 0.3,
			'base_prompt' => '',
		);

		$settings = wp_parse_args( $settings, $defaults );

		$settings['api_key']     = trim( (string) $settings['api_key'] );
		$settings['model']       = trim( (string) $settings['model'] );
		$settings['temperature'] = (float) $settings['temperature'];
		$settings['base_prompt'] = (string) $settings['base_prompt'];

		// Temperatura entre 0 e 1.
		if ( $settings['temperature'] < 0 ) {
			$settings['temperature'] = 0.0;
		} elseif ( $settings['temperature'] > 1 ) {
			$settings['temperature'] = 1.0;
		}

		return $settings;
	}

	/**
	 * Salva as configurações de IA.
	 *
	 * @param array $settings Configurações brutas.
	 */
	public static function save_ai_settings( array $settings ) {
		$clean = self::sanitize_ai_settings( $settings );
		update_option( self::OPTION_AI, $clean );
	}

	/**
	 * Sanitiza configurações de IA.
	 *
	 * @param array $settings Configurações brutas.
	 * @return array
	 */
	public static function sanitize_ai_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();

		$ai = array(
			'api_key'     => '',
			'model'       => '',
			'temperature' => 0.3,
			'base_prompt' => '',
		);

		if ( isset( $settings['api_key'] ) ) {
			$ai['api_key'] = trim( (string) $settings['api_key'] );
		}

		if ( isset( $settings['model'] ) ) {
			$ai['model'] = trim( (string) $settings['model'] );
		}

		if ( isset( $settings['temperature'] ) ) {
			$ai['temperature'] = (float) $settings['temperature'];
		}

		if ( isset( $settings['base_prompt'] ) ) {
			$ai['base_prompt'] = (string) $settings['base_prompt'];
		}

		// Temperatura entre 0 e 1.
		if ( $ai['temperature'] < 0 ) {
			$ai['temperature'] = 0.0;
		} elseif ( $ai['temperature'] > 1 ) {
			$ai['temperature'] = 1.0;
		}

		return $ai;
	}

	/**
	 * Retorna as configurações gerais.
	 *
	 * Estrutura:
	 * [
	 *   'default_author_id' => int,
	 * ]
	 *
	 * @return array
	 */
	public static function get_general_settings() {
		$general = get_option( self::OPTION_GENERAL, array() );

		if ( ! is_array( $general ) ) {
			$general = array();
		}

		$defaults = array(
			'default_author_id' => 0,
		);

		$general = wp_parse_args( $general, $defaults );

		$general['default_author_id'] = (int) $general['default_author_id'];
		if ( $general['default_author_id'] < 0 ) {
			$general['default_author_id'] = 0;
		}

		return $general;
	}

	/**
	 * Salva configurações gerais.
	 *
	 * @param array $general Configurações gerais brutas.
	 */
	public static function save_general_settings( array $general ) {
		$clean = self::sanitize_general_settings( $general );
		update_option( self::OPTION_GENERAL, $clean );
	}

	/**
	 * Sanitiza configurações gerais.
	 *
	 * @param array $general Configurações gerais brutas.
	 * @return array
	 */
	public static function sanitize_general_settings( $general ) {
		$general = is_array( $general ) ? $general : array();

		$out = array(
			'default_author_id' => 0,
		);

		if ( isset( $general['default_author_id'] ) ) {
			$out['default_author_id'] = (int) $general['default_author_id'];
			if ( $out['default_author_id'] < 0 ) {
				$out['default_author_id'] = 0;
			}
		}

		return $out;
	}
}
