<?php
/**
 * Processador de conteúdo bruto dos feeds para o Feeds IA.
 *
 * Responsável por:
 * - Limpar e normalizar HTML em texto utilizável.
 * - Preparar estrutura de artigo para envio à IA.
 * - Tentar extrair imagem principal quando possível.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe Feeds_IA_Content_Processor
 */
class Feeds_IA_Content_Processor {

	/**
	 * Processa um item bruto retornado pelo Feeds_IA_Feeds_Manager
	 * e devolve uma estrutura pronta para envio à IA.
	 *
	 * Entrada esperada:
	 * [
	 *   'feed_id'      => string,
	 *   'title'        => string,
	 *   'content_raw'  => string (HTML),
	 *   'link'         => string,
	 *   'image_url'    => string|null,
	 *   'tags'         => string[],
	 *   'published_at' => int,
	 *   'guid'         => string,
	 * ]
	 *
	 * Saída:
	 * [
	 *   'feed_id'      => string,
	 *   'title'        => string,
	 *   'content_text' => string,  // texto limpo, sem tags supérfluas
	 *   'link'         => string,
	 *   'image_url'    => string|null,
	 *   'tags'         => string[],
	 *   'published_at' => int,
	 *   'guid'         => string,
	 * ]
	 *
	 * @param array $item
	 * @return array
	 */
	public static function process_item( array $item ) {
		$defaults = array(
			'feed_id'      => '',
			'title'        => '',
			'content_raw'  => '',
			'link'         => '',
			'image_url'    => null,
			'tags'         => array(),
			'published_at' => time(),
			'guid'         => '',
		);

		$item = wp_parse_args( $item, $defaults );

		$title       = is_string( $item['title'] ) ? $item['title'] : '';
		$content_raw = is_string( $item['content_raw'] ) ? $item['content_raw'] : '';
		$link        = is_string( $item['link'] ) ? $item['link'] : '';
		$image_url   = $item['image_url'];
		$tags        = is_array( $item['tags'] ) ? $item['tags'] : array();

		// Se ainda não houver imagem, tenta extrair do HTML.
		if ( empty( $image_url ) && ! empty( $content_raw ) ) {
			$image_url = self::extract_image_from_html( $content_raw );
		}

		// Normaliza texto: remove tags desnecessárias, scripts, estilos.
		$content_text = self::html_to_clean_text( $content_raw );

		// Garante que não fique vazio; se tudo falhar, usa título + link como fallback mínimo.
		if ( '' === trim( $content_text ) ) {
			$content_text = $title;
			if ( '' !== $link ) {
				$content_text .= "\n\n" . sprintf( __( 'Mais detalhes na fonte original: %s', 'feeds-ia' ), $link );
			}
		}

		return array(
			'feed_id'      => sanitize_text_field( $item['feed_id'] ),
			'title'        => $title,
			'content_text' => $content_text,
			'link'         => esc_url_raw( $link ),
			'image_url'    => ( $image_url ? esc_url_raw( $image_url ) : null ),
			'tags'         => array_map( 'sanitize_text_field', $tags ),
			'published_at' => intval( $item['published_at'] ),
			'guid'         => sanitize_text_field( $item['guid'] ),
		);
	}

	/**
	 * Converte HTML em texto limpo, preservando quebras de linha básicas.
	 *
	 * @param string $html
	 * @return string
	 */
	protected static function html_to_clean_text( $html ) {
		if ( ! is_string( $html ) || '' === trim( $html ) ) {
			return '';
		}

		// Remove scripts e estilos.
		$html = preg_replace( '#<script[^>]*>.*?</script>#is', '', $html );
		$html = preg_replace( '#<style[^>]*>.*?</style>#is', '', $html );

		// Converte algumas tags em quebras de linha.
		$break_tags = array( '<br>', '<br/>', '<br />', '</p>', '</div>', '</li>' );
		$html       = str_ireplace( $break_tags, "\n", $html );

		// Remove demais tags HTML.
		$text = wp_strip_all_tags( $html, true );

		// Decodifica entidades.
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) );

		// Normaliza quebras de linha e espaços.
		$text = preg_replace( "/\r\n|\r/", "\n", $text );
		$text = preg_replace( "/\n{3,}/", "\n\n", $text ); // máximo 2 quebras seguidas.
		$text = trim( $text );

		return $text;
	}

	/**
	 * Tenta extrair uma URL de imagem do HTML fornecido.
	 *
	 * Estratégia simples:
	 * - Procura pela primeira tag <img> e retorna o atributo src.
	 *
	 * @param string $html
	 * @return string|null
	 */
protected static function extract_image_from_html( $html ) {
	if ( ! is_string( $html ) || '' === trim( $html ) ) {
		return null;
	}

	$matches = array(); // opcional, apenas para silenciar linter

	if ( preg_match( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $matches ) ) {
		if ( isset( $matches[1] ) && is_string( $matches[1] ) && '' !== trim( $matches[1] ) ) {
			return $matches[1];
		}
	}

	return null;
}

}
