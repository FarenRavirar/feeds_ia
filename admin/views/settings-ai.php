<?php
/**
 * Tela de configuração da IA (Google Gemini) do plugin Feeds IA.
 *
 * Regras editoriais:
 * - Reescrever notícias de RPG de mesa em português do Brasil.
 * - Manter todos os fatos (datas, valores, nomes, sistemas, cenários, suplementos).
 * - Não inventar informações, não especular.
 * - Texto em terceira pessoa, tom informativo.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}



// Permissão mínima.
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'feeds-ia' ) );
}

// Processa envio do formulário (salvar e/ou testar).
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['feeds_ia_ai_settings'] ) ) {

	check_admin_referer( 'feeds_ia_save_ai', 'feeds_ia_ai_nonce' );

	// Configurações enviadas pelo formulário.
	$raw_settings = wp_unslash( $_POST['feeds_ia_ai_settings'] );

	// Salva configurações de IA.
	if ( class_exists( 'Feeds_IA_Settings' ) ) {
		Feeds_IA_Settings::save_ai_settings( $raw_settings );
	}

	$did_test = false;
	$test_result = null;

	// Se o usuário clicou em "Testar conexão com IA".
	if ( isset( $_POST['feeds_ia_ai_test'] ) ) {
		$did_test = true;

		if ( class_exists( 'Feeds_IA_AI_Gemini' ) ) {
			$provider    = new Feeds_IA_AI_Gemini();
			$test_result = $provider->test_connection();
		} else {
			$test_result = new WP_Error(
				'feeds_ia_ai_provider_missing',
				__( 'Classe Feeds_IA_AI_Gemini não encontrada.', 'feeds-ia' )
			);
		}
	}

	// Notice de configurações salvas.
	echo '<div class="notice notice-success is-dismissible"><p>';
	echo esc_html__( 'Configurações de IA salvas com sucesso.', 'feeds-ia' );
	echo '</p></div>';

	// Notice de teste, se houver.
	if ( $did_test ) {
		if ( is_wp_error( $test_result ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>';
			echo esc_html__( 'Falha ao testar a conexão com o Gemini:', 'feeds-ia' ) . ' ';
			echo esc_html( $test_result->get_error_message() );
			echo '</p></div>';

			// Opcional: registrar no logger.
			if ( class_exists( 'Feeds_IA_Logger' ) ) {
				Feeds_IA_Logger::log(
					array(
						'type'    => 'ai_test',
						'status'  => 'error',
						'message' => $test_result->get_error_message(),
					)
				);
			}
		} else {
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo esc_html__( 'Conexão com o Gemini testada com sucesso.', 'feeds-ia' );
			echo '</p></div>';

			if ( class_exists( 'Feeds_IA_Logger' ) ) {
				Feeds_IA_Logger::log(
					array(
						'type'    => 'ai_test',
						'status'  => 'success',
						'message' => 'Conexão com o Gemini OK.',
					)
				);
			}
		}
	}
}

// Carrega configurações atuais para preencher o formulário.
$settings = class_exists( 'Feeds_IA_Settings' )
	? Feeds_IA_Settings::get_ai_settings()
	: array();

$api_key     = isset( $settings['api_key'] ) ? $settings['api_key'] : '';
$model       = isset( $settings['model'] ) ? $settings['model'] : '';
$temperature = isset( $settings['temperature'] ) ? (float) $settings['temperature'] : 0.3;
$base_prompt = isset( $settings['base_prompt'] ) ? $settings['base_prompt'] : '';

?>
<div class="wrap">
	<h1><?php esc_html_e( 'Feeds IA – IA & Prompt', 'feeds-ia' ); ?></h1>

	<p class="description">
		<?php
		echo esc_html__(
			'Esta tela controla a integração do plugin com a IA do Google Gemini. O objetivo é reescrever notícias de RPG de mesa em português do Brasil, mantendo todos os fatos, datas, valores e nomes próprios exatamente como na fonte.',
			'feeds-ia'
		);
		?>
	</p>

	<form method="post" action="">
		<?php wp_nonce_field( 'feeds_ia_save_ai', 'feeds_ia_ai_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="feeds-ia-api-key"><?php esc_html_e( 'API Key do Google Gemini', 'feeds-ia' ); ?></label>
					</th>
					<td>
						<input
							type="password"
							id="feeds-ia-api-key"
							name="feeds_ia_ai_settings[api_key]"
							class="regular-text"
							value="<?php echo esc_attr( $api_key ); ?>"
							autocomplete="off"
						/>
						<p class="description">
							<?php
							printf(
								wp_kses(
									/* translators: %s = link para Google AI Studio */
									__( 'Criar ou gerenciar a chave no <a href="%s" target="_blank" rel="noopener noreferrer">Google AI Studio</a>. A chave será usada apenas pelo servidor do WordPress.', 'feeds-ia' ),
									array(
										'a' => array(
											'href'   => array(),
											'target' => array(),
											'rel'    => array(),
										),
									)
								),
								'https://aistudio.google.com/'
							);
							?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="feeds-ia-model"><?php esc_html_e( 'Modelo do Gemini', 'feeds-ia' ); ?></label>
					</th>
					<td>
						<input
							type="text"
							id="feeds-ia-model"
							name="feeds_ia_ai_settings[model]"
							class="regular-text"
							value="<?php echo esc_attr( $model ); ?>"
							placeholder="gemini-2.5-flash"
						/>
						<p class="description">
							<?php
							esc_html_e(
								'Informe o identificador exato do modelo configurado no Google (por exemplo, gemini-2.5-flash).',
								'feeds-ia'
							);
							?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="feeds-ia-temperature"><?php esc_html_e( 'Temperatura', 'feeds-ia' ); ?></label>
					</th>
					<td>
						<input
							type="number"
							step="0.1"
							min="0"
							max="1"
							id="feeds-ia-temperature"
							name="feeds_ia_ai_settings[temperature]"
							value="<?php echo esc_attr( $temperature ); ?>"
						/>
						<p class="description">
							<?php
							esc_html_e(
								'Controle de variação criativa do texto. Valores baixos (0–0,3) mantêm o texto mais próximo do original; valores altos geram variação maior.',
								'feeds-ia'
							);
							?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="feeds-ia-base-prompt"><?php esc_html_e( 'Prompt base adicional', 'feeds-ia' ); ?></label>
					</th>
					<td>
						<textarea
							id="feeds-ia-base-prompt"
							name="feeds_ia_ai_settings[base_prompt]"
							class="large-text"
							rows="6"
						><?php echo esc_textarea( $base_prompt ); ?></textarea>
						<p class="description">
							<?php
							esc_html_e(
								'Este texto será acrescentado às instruções fixas do plugin. O plugin já força a saída em português do Brasil, em terceira pessoa, com foco em RPG de mesa, sem invenção de fatos. Use este campo apenas para ajustes finos de tom ou estrutura, sem pedir nada que contrarie essas regras.',
								'feeds-ia'
							);
							?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>

		<p class="submit">
			<button type="submit" name="feeds_ia_ai_save" class="button button-primary">
				<?php esc_html_e( 'Salvar configurações', 'feeds-ia' ); ?>
			</button>

			<button type="submit" name="feeds_ia_ai_test" class="button">
				<?php esc_html_e( 'Testar conexão com IA', 'feeds-ia' ); ?>
			</button>
		</p>
	</form>

	<h2><?php esc_html_e( 'Resumo das regras editoriais aplicadas pela IA', 'feeds-ia' ); ?></h2>
	<ul>
		<li><?php esc_html_e( 'Saída sempre em português do Brasil.', 'feeds-ia' ); ?></li>
		<li><?php esc_html_e( 'Texto em terceira pessoa, tom informativo e voltado para RPG de mesa.', 'feeds-ia' ); ?></li>
		<li><?php esc_html_e( 'Preservação rígida de datas, valores numéricos, nomes de pessoas, editoras, sistemas, cenários e suplementos.', 'feeds-ia' ); ?></li>
		<li><?php esc_html_e( 'Nenhuma invenção de fatos, nenhuma especulação.', 'feeds-ia' ); ?></li>
		<li><?php esc_html_e( 'Todos os posts criados pelo plugin são salvos como rascunho para revisão humana.', 'feeds-ia' ); ?></li>
	</ul>
</div>
