<?php
/**
 * Tela de configuração da IA (Gemini) para o Feeds IA.
 *
 * Permite:
 * - Definir API key, modelo, temperatura e prompt base.
 * - Testar a conexão com a IA usando as configurações atuais.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

$notices    = array();
$ai_settings = Feeds_IA_Settings::get_ai_settings();

// Tratamento de POST (salvar / testar).
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['feeds_ia_ai_nonce'] ) ) {
	if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['feeds_ia_ai_nonce'] ) ), 'feeds_ia_save_ai' ) ) {

		$action = isset( $_POST['feeds_ia_action'] ) ? sanitize_key( wp_unslash( $_POST['feeds_ia_action'] ) ) : 'save_ai';

		$input = array(
			'api_key'     => isset( $_POST['feeds_ia_api_key'] ) ? wp_unslash( $_POST['feeds_ia_api_key'] ) : '',
			'model'       => isset( $_POST['feeds_ia_model'] ) ? wp_unslash( $_POST['feeds_ia_model'] ) : '',
			'temperature' => isset( $_POST['feeds_ia_temperature'] ) ? wp_unslash( $_POST['feeds_ia_temperature'] ) : '',
			'base_prompt' => isset( $_POST['feeds_ia_base_prompt'] ) ? wp_unslash( $_POST['feeds_ia_base_prompt'] ) : '',
		);

		Feeds_IA_Settings::save_ai_settings( $input );
		$ai_settings = Feeds_IA_Settings::get_ai_settings();

		if ( 'test_ai' === $action ) {
			// Testa conexão com a IA usando as configurações recém-salvas.
			$provider = new Feeds_IA_AI_Gemini();
			$result   = $provider->test_connection();

			if ( is_wp_error( $result ) ) {
				$notices[] = array(
					'type'    => 'error',
					'message' => sprintf(
						'Falha ao testar a conexão com a IA: %s',
						esc_html( $result->get_error_message() )
					),
				);
			} else {
				$notices[] = array(
					'type'    => 'success',
					'message' => 'Conexão com a IA testada com sucesso. O Gemini está acessível e respondendo.',
				);
			}
		} else {
			$notices[] = array(
				'type'    => 'success',
				'message' => 'Configurações de IA salvas com sucesso.',
			);
		}
	} else {
		$notices[] = array(
			'type'    => 'error',
			'message' => 'Falha na validação do formulário. Tente novamente.',
		);
	}
}

?>
<div class="wrap feeds-ia-wrap">
	<h1><?php echo esc_html( 'Feeds IA – IA & Prompt' ); ?></h1>

	<?php if ( ! empty( $notices ) ) : ?>
		<?php foreach ( $notices as $notice ) : ?>
			<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
				<p><?php echo esc_html( $notice['message'] ); ?></p>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>

	<p class="description">
		Esta tela controla a integração do plugin com a IA do Google Gemini.
		O objetivo é reescrever notícias de RPG de mesa em português do Brasil, mantendo todos os fatos, datas, valores e nomes próprios exatamente como na fonte.
	</p>

	<form method="post" action="">
		<?php wp_nonce_field( 'feeds_ia_save_ai', 'feeds_ia_ai_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="feeds_ia_api_key">API Key do Google Gemini</label>
					</th>
					<td>
						<input
							type="password"
							name="feeds_ia_api_key"
							id="feeds_ia_api_key"
							class="regular-text"
							value="<?php echo esc_attr( $ai_settings['api_key'] ); ?>"
							autocomplete="off"
						/>
						<p class="description">
							Criar ou gerenciar a chave no Google AI Studio. A chave será usada apenas pelo servidor do WordPress.
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="feeds_ia_model">Modelo do Gemini</label>
					</th>
					<td>
						<input
							type="text"
							name="feeds_ia_model"
							id="feeds_ia_model"
							class="regular-text"
							value="<?php echo esc_attr( $ai_settings['model'] ); ?>"
							placeholder="ex.: gemini-1.5-flash"
						/>
						<p class="description">
							Informe o identificador exato do modelo configurado no Google (por exemplo, <code>gemini-1.5-flash</code>).
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="feeds_ia_temperature">Temperatura</label>
					</th>
					<td>
						<input
							type="number"
							step="0.1"
							min="0"
							max="1"
							name="feeds_ia_temperature"
							id="feeds_ia_temperature"
							value="<?php echo esc_attr( $ai_settings['temperature'] ); ?>"
							style="width: 80px;"
						/>
						<p class="description">
							Controle de variação criativa do texto. Valores baixos (0–0,3) mantêm o texto mais próximo do original; valores altos geram variação maior.
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row" valign="top">
						<label for="feeds_ia_base_prompt">Prompt base adicional</label>
					</th>
					<td>
						<textarea
							name="feeds_ia_base_prompt"
							id="feeds_ia_base_prompt"
							rows="8"
							cols="50"
							class="large-text code"
						><?php echo esc_textarea( $ai_settings['base_prompt'] ); ?></textarea>
						<p class="description">
							Este texto será acrescentado às instruções fixas do plugin.
							O plugin já força a saída em português do Brasil, em terceira pessoa, com foco em RPG de mesa, sem invenção de fatos.
							Use este campo apenas para ajustes finos de tom ou estrutura, sem pedir nada que contrarie essas regras.
						</p>
					</td>
				</tr>
			</tbody>
		</table>

		<p class="submit">
			<button
				type="submit"
				name="feeds_ia_action"
				value="save_ai"
				class="button button-primary"
			>
				Salvar configurações
			</button>

			<button
				type="submit"
				name="feeds_ia_action"
				value="test_ai"
				class="button"
				id="feeds-ia-test-ai"
			>
				Testar conexão com IA
			</button>
		</p>
	</form>

	<hr />

	<h2>Resumo das regras editoriais aplicadas pela IA</h2>
	<ul>
		<li>Saída sempre em português do Brasil.</li>
		<li>Texto em terceira pessoa, tom informativo e voltado para RPG de mesa.</li>
		<li>Preservação rígida de datas, valores numéricos, nomes de pessoas, editoras, sistemas, cenários e suplementos.</li>
		<li>Nenhuma invenção de fatos, nenhuma especulação.</li>
		<li>Todos os posts criados pelo plugin são salvos como rascunho para revisão humana.</li>
	</ul>
</div>
