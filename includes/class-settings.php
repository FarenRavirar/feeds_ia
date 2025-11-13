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
	 * @param array $feeds Lista de feeds.
	 */
	public static function save_feeds( array $feeds ) {
		$clean = array();

		foreach ( $feeds as $feed ) {
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
