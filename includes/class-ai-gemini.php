<?php
/**
 * Provedor de IA baseado no Google Gemini para o plugin Feeds IA.
 *
 * Foco editorial:
 * - Reescrever notícias de RPG de mesa em português do Brasil.
 * - Manter todos os fatos: datas, valores, nomes de sistemas, cenários, suplementos e pessoas.
 * - Não inventar, não especular, não completar lacunas.
 * - Texto em terceira pessoa, tom informativo, vocabulário próprio de RPG de mesa.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe Feeds_IA_AI_Gemini
 *
 * Implementa a interface Feeds_IA_AI_Provider.
 */
class Feeds_IA_AI_Gemini implements Feeds_IA_AI_Provider {

	/**
	 * Endpoint base da API do Gemini.
	 *
	 * @var string
	 */
	protected $base_url = 'https://generativelanguage.googleapis.com/v1beta/models/';

	/**
	 * Configurações de IA (api_key, model, temperature, base_prompt).
	 *
	 * @var array
	 */
	protected $settings = array();

	/**
	 * Construtor.
	 *
	 * Carrega as configurações atuais de IA.
	 */
	public function __construct() {
		if ( class_exists( 'Feeds_IA_Settings' ) ) {
			$this->settings = Feeds_IA_Settings::get_ai_settings();
		} else {
			$this->settings = array();
		}
	}

	/**
	 * Reescreve um artigo usando o modelo Gemini.
	 *
	 * @param array $article Artigo pré-processado (title, content_text, link, image_url etc.).
	 * @param array $options Opções adicionais (não usado por enquanto).
	 *
	 * @return array|\WP_Error
	 */
	public function rewrite_article( array $article, array $options = array() ) {
		$api_key = isset( $this->settings['api_key'] ) ? trim( $this->settings['api_key'] ) : '';
		$model   = isset( $this->settings['model'] ) ? trim( $this->settings['model'] ) : '';
		$temp    = isset( $this->settings['temperature'] ) ? (float) $this->settings['temperature'] : 0.3;
		$prompt  = isset( $this->settings['base_prompt'] ) ? (string) $this->settings['base_prompt'] : '';

		if ( '' === $api_key || '' === $model ) {
			return new WP_Error(
				'feeds_ia_ai_config_missing',
				__( 'Configurações de IA incompletas: API key e/ou modelo ausentes.', 'feeds-ia' )
			);
		}

		// Texto original.
		$title        = isset( $article['title'] ) ? (string) $article['title'] : '';
		$content_text = isset( $article['content_text'] ) ? (string) $article['content_text'] : '';
		$link         = isset( $article['link'] ) ? (string) $article['link'] : '';
		$tags         = isset( $article['tags'] ) && is_array( $article['tags'] ) ? $article['tags'] : array();

		// Prompt base editorial: português do Brasil, RPG de mesa, sem invenção de fatos.
		$core_instructions = <<<EOT
Você é responsável por reescrever notícias sobre RPG de mesa em português do Brasil.

Regras editoriais obrigatórias:
- Use sempre português do Brasil.
- Escreva em terceira pessoa, com tom informativo e objetivo.
- Use vocabulário típico de RPG de mesa: sistema, cenário, suplemento, livro básico, campanha, mesa, one-shot, playtest, financiamento coletivo etc.
- Mantenha TODOS os fatos exatamente como no texto original:
  - Não altere datas, anos ou horários.
  - Não altere valores numéricos (preços, porcentagens, metas).
  - Não altere nomes próprios de pessoas, editoras, sistemas, cenários, suplementos, eventos, plataformas.
- NÃO invente informações, NÃO complete lacunas, NÃO especule.
- Se algo não estiver claro no texto original, apenas não mencione, em vez de deduzir.
- Não comente sobre o próprio processo de escrita, apenas apresente a notícia.

Tarefa:
- Reescreva o título e o corpo da notícia em português do Brasil, em terceira pessoa.
- Produza um resumo curto (1–2 frases) para ser usado como descrição de busca (meta description).
- OrganizE o corpo em parágrafos em HTML (<p>...</p>), sem títulos de seção.

Formato de saída (OBRIGATÓRIO):
Responda APENAS com um JSON válido, sem texto extra, sem explicações, sem markdown.
O JSON deve ter exatamente os campos:
{
  "title": "Título reescrito em português do Brasil",
  "content": "<p>Corpo da notícia em HTML, com vocabulário de RPG de mesa...</p>",
  "summary": "Resumo curto em português para meta description."
}
EOT;

		// Prompt livre configurável pelo usuário (opcional).
		$user_prompt = '';
		if ( '' !== $prompt ) {
			$user_prompt = "\n\nInstruções adicionais fornecidas pelo editor:\n" . $prompt;
		}

		// Conteúdo original (em português ou outro idioma).
		$source_block = <<<EOT

Texto original a ser reescrito:

TÍTULO:
{$title}

CONTEÚDO:
{$content_text}

LINK DA FONTE:
{$link}

TAGS (se houver): 
- {$this->implode_tags_for_prompt( $tags )}

EOT;

		$full_prompt = $core_instructions . $user_prompt . $source_block;

		// Monta URL do endpoint.
		$endpoint = sprintf(
	'%s%s:generateContent?key=%s',
	rtrim( $this->base_url, '/' ) . '/',
			rawurlencode( $model ),
			rawurlencode( $api_key )
		);

		$body = array(
			'contents' => array(
				array(
					'parts' => array(
						array(
							'text' => $full_prompt,
						),
					),
				),
			),
			'generationConfig' => array(
				'temperature'     => $temp,
				'maxOutputTokens' => 2048,
			),
		);

		$args = array(
			'method'      => 'POST',
			'timeout'     => 30,
			'headers'     => array(
				'Content-Type' => 'application/json',
			),
			'body'        => wp_json_encode( $body ),
			'data_format' => 'body',
		);

		$response = wp_remote_post( $endpoint, $args );

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'feeds_ia_ai_request_error',
				sprintf(
					/* translators: %s = error message */
					__( 'Erro ao chamar a API do Gemini: %s', 'feeds-ia' ),
					$response->get_error_message()
				)
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'feeds_ia_ai_http_error',
				sprintf(
					/* translators: 1 = http code, 2 = body */
					__( 'Resposta inesperada do Gemini (HTTP %1$d): %2$s', 'feeds-ia' ),
					$code,
					$raw
				)
			);
		}

		$data = json_decode( $raw, true );

		if ( ! is_array( $data ) ) {
			return new WP_Error(
				'feeds_ia_ai_json_error',
				__( 'Não foi possível decodificar a resposta do Gemini como JSON.', 'feeds-ia' )
			);
		}

		// Extrai o texto gerado (modelo Gemini).
		$text = self::extract_text_from_gemini_response( $data );

		if ( '' === $text ) {
			return new WP_Error(
				'feeds_ia_ai_empty_text',
				__( 'A resposta do Gemini não contém texto útil.', 'feeds-ia' )
			);
		}

		// Remove marcadores ```json ``` se o modelo os tiver incluído.
		$text = self::strip_markdown_json_fences( $text );

		$result = json_decode( $text, true );

		if ( ! is_array( $result ) ) {
			return new WP_Error(
				'feeds_ia_ai_result_json_error',
				__( 'A resposta do Gemini não é um JSON válido no formato esperado.', 'feeds-ia' )
			);
		}

		$title_out   = isset( $result['title'] ) ? (string) $result['title'] : '';
		$content_out = isset( $result['content'] ) ? (string) $result['content'] : '';
		$summary_out = isset( $result['summary'] ) ? (string) $result['summary'] : '';

		if ( '' === $title_out && '' === $content_out ) {
			return new WP_Error(
				'feeds_ia_ai_result_missing_fields',
				__( 'A resposta do Gemini não trouxe título nem conteúdo.', 'feeds-ia' )
			);
		}

		// Se conteúdo vier sem tags HTML de parágrafo, converte em <p>.
		if ( '' !== $content_out && ! preg_match( '/<p[\s>]/i', $content_out ) && ! preg_match( '/<h[1-6][\s>]/i', $content_out ) ) {
			$content_out = self::plain_text_to_paragraphs( $content_out );
		}

		return array(
			'title'   => $title_out,
			'content' => $content_out,
			'summary' => $summary_out,
			'model'   => $model,
		);
	}

	/**
	 * Testa a conexão com o Gemini usando as configurações atuais.
	 *
	 * Retorna true em caso de sucesso, ou WP_Error em caso de falha.
	 *
	 * @return true|\WP_Error
	 */
	public function test_connection() {
		$api_key = isset( $this->settings['api_key'] ) ? trim( $this->settings['api_key'] ) : '';
		$model   = isset( $this->settings['model'] ) ? trim( $this->settings['model'] ) : '';

		if ( '' === $api_key || '' === $model ) {
			return new WP_Error(
				'feeds_ia_ai_config_missing',
				__( 'Configurações de IA incompletas: API key e/ou modelo ausentes.', 'feeds-ia' )
			);
		}

		$core_test = <<<EOT
Você está testando a conexão do plugin Feeds IA do WordPress.

Responda APENAS com um JSON válido no formato:
{
  "ok": true,
  "message": "Texto curto em português do Brasil confirmando que a IA está acessível."
}
EOT;

		$endpoint = sprintf(
	'%s%s:generateContent?key=%s',
	rtrim( $this->base_url, '/' ) . '/',
			rawurlencode( $model ),
			rawurlencode( $api_key )
		);
		$body = array(
			'contents' => array(
				array(
					'parts' => array(
						array(
							'text' => $core_test,
						),
					),
				),
			),
			'generationConfig' => array(
				'temperature'     => 0.1,
				'maxOutputTokens' => 128,
			),
		);

		$args = array(
			'method'      => 'POST',
			'timeout'     => 20,
			'headers'     => array(
				'Content-Type' => 'application/json',
			),
			'body'        => wp_json_encode( $body ),
			'data_format' => 'body',
		);

		$response = wp_remote_post( $endpoint, $args );

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'feeds_ia_ai_request_error',
				sprintf(
					__( 'Erro ao testar a API do Gemini: %s', 'feeds-ia' ),
					$response->get_error_message()
				)
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'feeds_ia_ai_http_error',
				sprintf(
					__( 'Resposta inesperada do Gemini no teste (HTTP %1$d): %2$s', 'feeds-ia' ),
					$code,
					$raw
				)
			);
		}

		$data = json_decode( $raw, true );
		$text = self::extract_text_from_gemini_response( $data );
		$text = self::strip_markdown_json_fences( $text );
		$res  = json_decode( $text, true );

		if ( ! is_array( $res ) || ! isset( $res['ok'] ) || true !== $res['ok'] ) {
			return new WP_Error(
				'feeds_ia_ai_test_invalid',
				__( 'A resposta do teste do Gemini não está no formato esperado.', 'feeds-ia' )
			);
		}

		return true;
	}

	/**
	 * Extrai o texto principal da resposta do Gemini.
	 *
	 * @param array $response Dados já decodificados.
	 * @return string
	 */
	protected static function extract_text_from_gemini_response( array $response ) {
		if ( empty( $response['candidates'] ) || ! is_array( $response['candidates'] ) ) {
			return '';
		}

		$candidate = $response['candidates'][0];

		if ( empty( $candidate['content']['parts'] ) || ! is_array( $candidate['content']['parts'] ) ) {
			return '';
		}

		$texts = array();

		foreach ( $candidate['content']['parts'] as $part ) {
			if ( isset( $part['text'] ) && is_string( $part['text'] ) ) {
				$texts[] = $part['text'];
			}
		}

		return implode( "\n", $texts );
	}

	/**
	 * Remove cercas de markdown ```json ... ``` de um texto.
	 *
	 * @param string $text Texto possivelmente com cercas de código.
	 * @return string
	 */
	protected static function strip_markdown_json_fences( $text ) {
		$text = trim( (string) $text );

		// Remove cercas padrão ```json ... ``` ou ``` ... ```.
		$text = preg_replace( '/^\s*```(?:json)?\s*/i', '', $text );
		$text = preg_replace( '/\s*```\s*$/', '', $text );

		return trim( $text );
	}

	/**
	 * Converte texto simples em parágrafos HTML.
	 *
	 * @param string $text Texto puro.
	 * @return string
	 */
	protected static function plain_text_to_paragraphs( $text ) {
		$lines = preg_split( '/\r\n|\r|\n/', (string) $text );
		$parts = array();

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$parts[] = '<p>' . esc_html( $line ) . '</p>';
		}

		return implode( "\n\n", $parts );
	}

	/**
	 * Helper para exibir tags no prompt.
	 *
	 * @param array $tags Lista de tags.
	 * @return string
	 */
	protected function implode_tags_for_prompt( array $tags ) {
		$tags = array_map( 'trim', $tags );
		$tags = array_filter( $tags );

		if ( empty( $tags ) ) {
			return '';
		}

		return implode( ', ', $tags );
	}
}
