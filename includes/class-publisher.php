<?php
/**
 * Publicação de posts no WordPress para o plugin Feeds IA.
 *
 * Política editorial:
 * - Todos os posts criados pelo plugin são salvos como RASCUNHO.
 * - Nunca publicar automaticamente (post_status = 'draft').
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe responsável por criar e atualizar posts a partir dos dados dos feeds.
 */
class Feeds_IA_Publisher {

	/**
	 * Cria um post no WordPress a partir de um artigo processado e da saída da IA.
	 *
	 * @param array $feed_config Configuração do feed (vindo de Feeds_IA_Settings).
	 * @param array $article     Artigo pré-processado (title, content_text, link, image_url etc.).
	 * @param array $ai_result   Resultado da IA (title, content, summary).
	 *
	 * @return int|\WP_Error ID do post criado ou WP_Error em caso de falha.
	 */
	public static function create_post( array $feed_config, array $article, array $ai_result ) {
		$feed_id        = isset( $feed_config['id'] ) ? sanitize_text_field( $feed_config['id'] ) : '';
		$original_title = isset( $article['title'] ) ? wp_strip_all_tags( $article['title'] ) : '';
		$original_link  = isset( $article['link'] ) ? esc_url_raw( $article['link'] ) : '';
		$original_guid  = isset( $article['guid'] ) ? sanitize_text_field( $article['guid'] ) : '';

		// Título final: prioriza IA, cai para o título original se vazio.
		$title_ai = isset( $ai_result['title'] ) ? wp_strip_all_tags( $ai_result['title'] ) : '';
		$post_title = $title_ai ? $title_ai : $original_title;

		// Conteúdo final do post (HTML + crédito à fonte).
		$post_content = self::build_post_content( $article, $ai_result );

		// Autor padrão: pode ser configurado em feeds_ia_general, senão usa usuário atual.
		$author_id = get_current_user_id();
		if ( class_exists( 'Feeds_IA_Settings' ) ) {
			$general = Feeds_IA_Settings::get_general_settings();
			if ( isset( $general['default_author_id'] ) && $general['default_author_id'] > 0 ) {
				$author_id = (int) $general['default_author_id'];
			}
		}

		// Categoria de destino.
		$category_id = 0;
		if ( isset( $feed_config['category'] ) && (int) $feed_config['category'] > 0 ) {
			$category_id = (int) $feed_config['category'];
		}

		// Política fixa: SEMPRE rascunho. Ignora qualquer configuração de "publish".
		$postarr = array(
			'post_type'    => 'post',
			'post_status'  => 'draft',
			'post_title'   => $post_title,
			'post_content' => $post_content,
			'post_author'  => $author_id,
		);

		if ( $category_id > 0 ) {
			$postarr['post_category'] = array( $category_id );
		}

		// Insere post como rascunho.
		$post_id = wp_insert_post( $postarr, true );

		if ( is_wp_error( $post_id ) ) {
			Feeds_IA_Logger::log(
				array(
					'feed_id'         => $feed_id,
					'title_original'  => $original_title,
					'title_generated' => $post_title,
					'status'          => 'error-publish',
					'message'         => 'Falha ao criar post: ' . $post_id->get_error_message(),
					'post_id'         => null,
				)
			);

			return $post_id;
		}

		// Metadados de rastreio.
		if ( $original_link ) {
			update_post_meta( $post_id, '_feeds_ia_original_link', $original_link );
		}

		if ( $original_guid ) {
			update_post_meta( $post_id, '_feeds_ia_original_guid', $original_guid );
		}

		if ( $feed_id ) {
			update_post_meta( $post_id, '_feeds_ia_feed_id', $feed_id );
		}

		if ( isset( $ai_result['summary'] ) && is_string( $ai_result['summary'] ) ) {
			update_post_meta( $post_id, '_feeds_ia_summary', wp_strip_all_tags( $ai_result['summary'] ) );
		}

		if ( isset( $ai_result['model'] ) && is_string( $ai_result['model'] ) ) {
			update_post_meta( $post_id, '_feeds_ia_model', sanitize_text_field( $ai_result['model'] ) );
		}

		// Hash simples para evitar duplicação futura.
		$hash_source = $post_title . '|' . $original_link . '|' . $original_guid;
		update_post_meta( $post_id, '_feeds_ia_hash', sha1( $hash_source ) );

		// Imagem destacada (se houver).
		if ( ! empty( $article['image_url'] ) ) {
			self::maybe_set_featured_image( $post_id, $article['image_url'] );
		}

		// Log de sucesso.
		Feeds_IA_Logger::log(
			array(
				'feed_id'         => $feed_id,
				'title_original'  => $original_title,
				'title_generated' => $post_title,
				'status'          => 'success',
				'message'         => 'Post criado como rascunho.',
				'post_id'         => (int) $post_id,
			)
		);

		return (int) $post_id;
	}

	/**
	 * Monta o conteúdo final do post a partir do artigo e da saída da IA.
	 *
	 * - Usa o HTML da IA (se existir).
	 * - Caso não exista, usa o texto limpo do artigo.
	 * - Adiciona sempre um parágrafo final de crédito à fonte.
	 *
	 * @param array $article   Artigo pré-processado.
	 * @param array $ai_result Resultado da IA.
	 *
	 * @return string HTML final do conteúdo.
	 */
	protected static function build_post_content( array $article, array $ai_result ) {
		$content = '';

		if ( ! empty( $ai_result['content'] ) && is_string( $ai_result['content'] ) ) {
			$content = $ai_result['content'];
		} elseif ( ! empty( $article['content_text'] ) ) {
			// Fallback: texto limpo convertido em parágrafos simples.
			$paragraphs = preg_split( '/\r\n|\r|\n/', (string) $article['content_text'] );
			$buffer     = array();

			foreach ( $paragraphs as $p ) {
				$p = trim( $p );
				if ( '' === $p ) {
					continue;
				}
				$buffer[] = '<p>' . esc_html( $p ) . '</p>';
			}

			$content = implode( "\n\n", $buffer );
		}

		// Crédito à fonte.
		$link = isset( $article['link'] ) ? esc_url( $article['link'] ) : '';
		if ( $link ) {
			$content .= "\n\n" . sprintf(
				'<p><em>Fonte original: <a href="%1$s" target="_blank" rel="noopener noreferrer">%1$s</a></em></p>',
				$link
			);
		}

		return $content;
	}

	/**
	 * Define uma imagem destacada para o post, a partir de uma URL.
	 *
	 * @param int    $post_id   ID do post.
	 * @param string $image_url URL da imagem a ser baixada.
	 */
	protected static function maybe_set_featured_image( $post_id, $image_url ) {
		$post_id   = (int) $post_id;
		$image_url = esc_url_raw( $image_url );

		if ( $post_id <= 0 || empty( $image_url ) ) {
			return;
		}

		// Evita redefinir thumbnail se já existir.
		if ( has_post_thumbnail( $post_id ) ) {
			return;
		}

		// Usa a função core para baixar e anexar a imagem.
		$attachment_id = self::sideload_image_as_attachment( $post_id, $image_url );

		if ( is_wp_error( $attachment_id ) ) {
			Feeds_IA_Logger::log(
				array(
					'feed_id'         => '',
					'title_original'  => '',
					'title_generated' => '',
					'status'          => 'error-image',
					'message'         => 'Falha ao baixar imagem destacada: ' . $attachment_id->get_error_message(),
					'post_id'         => $post_id,
				)
			);
			return;
		}

		if ( $attachment_id > 0 ) {
			set_post_thumbnail( $post_id, $attachment_id );
		}
	}

	/**
	 * Baixa uma imagem remota e a registra como anexos do post.
	 *
	 * Esta função encapsula media_sideload_image para retornar o ID do attachment,
	 * em vez de HTML.
	 *
	 * @param int    $post_id   ID do post.
	 * @param string $image_url URL da imagem.
	 *
	 * @return int|\WP_Error ID do attachment ou WP_Error.
	 */
	protected static function sideload_image_as_attachment( $post_id, $image_url ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		// O terceiro parâmetro ($desc) é opcional; usa-se uma descrição simples.
		$result = media_sideload_image( $image_url, $post_id, '', 'id' );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return (int) $result;
	}
}
