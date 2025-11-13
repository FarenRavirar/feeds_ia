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
		$title_ai   = isset( $ai_result['title'] ) ? wp_strip_all_tags( $ai_result['title'] ) : '';
		$post_title = $title_ai ? $title_ai : $original_title;

		// Sanidade: evita criar posts com texto reescrito muito curto em relação ao original.
		if ( self::is_ai_content_too_short( $article, $ai_result ) ) {
			$message = 'Texto gerado pela IA muito curto em relação ao conteúdo original. Post não criado.';
			Feeds_IA_Logger::log(
				array(
					'feed_id'         => $feed_id,
					'title_original'  => $original_title,
					'title_generated' => $post_title,
					'status'          => 'error-ai-too-short',
					'message'         => $message,
					'post_id'         => null,
				)
			);

			return new WP_Error(
				'feeds_ia_ai_too_short',
				__( 'Texto gerado pela IA muito curto em relação ao conteúdo original.', 'feeds-ia' )
			);
		}

		// Slug encurtado, preservando o núcleo factual.
		$post_slug = self::generate_slug( $post_title );

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

		if ( '' !== $post_slug ) {
			$postarr['post_name'] = $post_slug;
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

		$summary_clean = '';
		if ( isset( $ai_result['summary'] ) && is_string( $ai_result['summary'] ) ) {
			$summary_clean = wp_strip_all_tags( $ai_result['summary'] );
			if ( '' !== $summary_clean ) {
				update_post_meta( $post_id, '_feeds_ia_summary', $summary_clean );
			}
		}

		if ( isset( $ai_result['model'] ) && is_string( $ai_result['model'] ) ) {
			update_post_meta( $post_id, '_feeds_ia_model', sanitize_text_field( $ai_result['model'] ) );
		}

		// Hash simples para evitar duplicação futura.
		$hash_source = $post_title . '|' . $original_link . '|' . $original_guid;
		update_post_meta( $post_id, '_feeds_ia_hash', sha1( $hash_source ) );

		// Integração com Yoast SEO (quando ativo).
		if ( defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Meta' ) ) {

			// Meta description: usa o summary gerado pela IA (ou fallback já tratado na classe de IA).
			if ( '' !== $summary_clean ) {
				update_post_meta( $post_id, '_yoast_wpseo_metadesc', $summary_clean );
			}

			// Frase-chave de foco derivada do título (sem criar fatos novos).
			$focuskw = self::derive_focus_keyphrase_from_title( $post_title );
			if ( '' !== $focuskw ) {
				update_post_meta( $post_id, '_yoast_wpseo_focuskw', $focuskw );
			}

			// Título SEO encurtado, preservando o núcleo factual.
			$seo_title = self::generate_seo_title( $post_title );
			if ( '' !== $seo_title ) {
				update_post_meta( $post_id, '_yoast_wpseo_title', $seo_title );
			}
		}

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

	/**
	 * Gera um slug encurtado a partir do título, preservando o núcleo factual.
	 *
	 * @param string $title Título do post.
	 * @return string Slug sanitizado.
	 */
	protected static function generate_slug( $title ) {
		$base = sanitize_title( $title );

		if ( '' === $base ) {
			return '';
		}

		$max_length = 70;

		if ( strlen( $base ) <= $max_length ) {
			return $base;
		}

		$parts     = explode( '-', $base );
		$stopwords = array( 'de', 'da', 'do', 'das', 'dos', 'para', 'por', 'e', 'a', 'o', 'um', 'uma', 'no', 'na', 'nos', 'nas' );
		$filtered  = array();

		foreach ( $parts as $part ) {
			if ( in_array( $part, $stopwords, true ) ) {
				continue;
			}
			$filtered[] = $part;
		}

		$slug = implode( '-', $filtered );

		if ( '' === $slug ) {
			$slug = $base;
		}

		if ( strlen( $slug ) > $max_length ) {
			$slug = substr( $slug, 0, $max_length );
			$slug = rtrim( $slug, '-' );
		}

		return $slug;
	}

	/**
	 * Gera um título SEO encurtado, com base no título original.
	 *
	 * @param string $title Título original.
	 * @return string Título SEO.
	 */
	protected static function generate_seo_title( $title ) {
		$title = trim( wp_strip_all_tags( (string) $title ) );

		if ( '' === $title ) {
			return '';
		}

		// Faixa recomendada aproximada: até ~60 caracteres.
		$max_length = 60;

		if ( mb_strlen( $title ) <= $max_length ) {
			return $title;
		}

		$cut = mb_substr( $title, 0, $max_length );
		// Evita cortar no meio de uma palavra.
		$cut = preg_replace( '/\s+\S*$/u', '', $cut );

		return trim( $cut );
	}

	/**
	 * Deriva uma frase-chave de foco a partir do título, sem inventar informação.
	 *
	 * @param string $title Título do post.
	 * @return string Frase-chave.
	 */
	protected static function derive_focus_keyphrase_from_title( $title ) {
		$title = trim( wp_strip_all_tags( (string) $title ) );

		if ( '' === $title ) {
			return '';
		}

		// Usa as primeiras palavras do título como keyphrase.
		$words = preg_split( '/\s+/', $title );
		if ( ! is_array( $words ) ) {
			return $title;
		}

		$max_words = 10;
		if ( count( $words ) > $max_words ) {
			$words = array_slice( $words, 0, $max_words );
		}

		return trim( implode( ' ', $words ) );
	}

	/**
	 * Verifica se o conteúdo reescrito pela IA ficou muito curto
	 * em relação ao conteúdo original.
	 *
	 * @param array $article   Artigo original pré-processado.
	 * @param array $ai_result Saída da IA.
	 * @return bool True se for considerado curto demais.
	 */
	protected static function is_ai_content_too_short( array $article, array $ai_result ) {
		$original = isset( $article['content_text'] ) ? (string) $article['content_text'] : '';
		$rewritten = isset( $ai_result['content'] ) ? (string) $ai_result['content'] : '';

		$original = wp_strip_all_tags( $original );
		$rewritten = wp_strip_all_tags( $rewritten );

		$original = trim( preg_replace( '/\s+/', ' ', $original ) );
		$rewritten = trim( preg_replace( '/\s+/', ' ', $rewritten ) );

		if ( '' === $original || '' === $rewritten ) {
			return false;
		}

		$orig_words = preg_split( '/\s+/', $original );
		$rewr_words = preg_split( '/\s+/', $rewritten );

		if ( ! is_array( $orig_words ) || ! is_array( $rewr_words ) ) {
			return false;
		}

		$orig_count = count( $orig_words );
		$rewr_count = count( $rewr_words );

		if ( $orig_count <= 0 ) {
			return false;
		}

		$ratio = $rewr_count / $orig_count;

		// Limiar aproximado: menos de 60% do volume original é considerado curto demais.
		return ( $ratio < 0.6 );
	}
}
