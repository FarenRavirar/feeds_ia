<?php
/**
 * Interface e helpers para provedores de IA no plugin Feeds IA.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface que todos os provedores de IA devem implementar.
 *
 * Responsável por receber um "artigo" já pré-processado
 * e devolver uma versão reescrita, mantendo o conteúdo factual.
 */
interface Feeds_IA_AI_Provider {

	/**
	 * Reescreve um artigo utilizando IA.
	 *
	 * Entrada típica em $article:
	 * [
	 *   'feed_id'      => string,
	 *   'title'        => string,
	 *   'content_text' => string,
	 *   'link'         => string,
	 *   'image_url'    => string|null,
	 *   'tags'         => string[],
	 *   'published_at' => int,
	 *   'guid'         => string,
	 * ]
	 *
	 * Opções em $options (podem variar por provedor):
	 * [
	 *   'base_prompt' => string,
	 *   'model'       => string,
	 *   'temperature' => float|null,
	 * ]
	 *
	 * Saída esperada:
	 * [
	 *   'title'   => string, // título reescrito
	 *   'content' => string, // corpo em HTML
	 *   'summary' => string, // resumo curto para meta description
	 * ]
	 *
	 * @param array $article
	 * @param array $options
	 * @return array
	 */
	public function rewrite_article( array $article, array $options = array() );
}

/**
 * Helper estático para obtenção do provedor de IA
 * com base nas configurações armazenadas no WordPress.
 */
class Feeds_IA_AI {

	/**
	 * Retorna uma instância do provedor de IA configurado,
	 * ou null se não houver configuração suficiente.
	 *
	 * Atualmente assume o uso da implementação Feeds_IA_AI_Gemini.
	 *
	 * @return Feeds_IA_AI_Provider|null
	 */
	public static function get_provider() {
		$ai_settings = Feeds_IA_Settings::get_ai_settings();

		// Sem API key ou modelo, não há como usar IA.
		if ( empty( $ai_settings['api_key'] ) || empty( $ai_settings['model'] ) ) {
			return null;
		}

		// Hoje só existe a implementação Gemini; no futuro,
		// poderia haver um campo "provider" em $ai_settings.
		if ( class_exists( 'Feeds_IA_AI_Gemini', true ) ) {
			return new Feeds_IA_AI_Gemini( $ai_settings );
		}

		return null;
	}

	/**
	 * Atalho para reescrever um artigo via IA usando o provedor atual.
	 *
	 * Lança WP_Error se não houver provedor disponível
	 * ou se a chamada falhar.
	 *
	 * @param array $article
	 * @return array|WP_Error
	 */
	public static function rewrite_article( array $article ) {
		$provider = self::get_provider();

		if ( ! $provider ) {
			return new WP_Error(
				'feeds_ia_no_ai_provider',
				__( 'Nenhum provedor de IA está configurado corretamente (verifique API key e modelo).', 'feeds-ia' )
			);
		}

		$ai_settings = Feeds_IA_Settings::get_ai_settings();

		$options = array(
			'base_prompt' => isset( $ai_settings['base_prompt'] ) ? $ai_settings['base_prompt'] : '',
			'model'       => isset( $ai_settings['model'] ) ? $ai_settings['model'] : '',
			'temperature' => isset( $ai_settings['temperature'] ) ? $ai_settings['temperature'] : null,
		);

		try {
			$result = $provider->rewrite_article( $article, $options );
		} catch ( Exception $e ) {
			return new WP_Error(
				'feeds_ia_ai_exception',
				sprintf(
					/* translators: %s: mensagem de erro da IA */
					__( 'Erro ao processar IA: %s', 'feeds-ia' ),
					$e->getMessage()
				)
			);
		}

		// Valida estrutura mínima do retorno.
		if ( ! is_array( $result )
			|| ! isset( $result['title'], $result['content'], $result['summary'] )
		) {
			return new WP_Error(
				'feeds_ia_ai_invalid_response',
				__( 'Resposta inválida do provedor de IA.', 'feeds-ia' )
			);
		}

		return $result;
	}
}
