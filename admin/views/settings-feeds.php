<?php
/**
 * Tela de configuração de feeds do Feeds IA.
 *
 * Permite:
 * - Cadastrar feeds RSS voltados a notícias de RPG de mesa.
 * - Ajustar categoria, frequência, quantidade de itens por execução.
 * - Excluir feeds.
 * - Processar um feed imediatamente ("Processar agora"), criando rascunhos.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

$notices = array();

// Estado atual dos feeds.
$feeds = Feeds_IA_Settings::get_feeds();

// Tratamento de POST.
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['feeds_ia_feeds_nonce'] ) ) {
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['feeds_ia_feeds_nonce'] ) ), 'feeds_ia_save_feeds' ) ) {
		$notices[] = array(
			'type'    => 'error',
			'message' => 'Falha na validação do formulário. Tente novamente.',
		);
	} else {
		$action = isset( $_POST['feeds_ia_action'] ) ? sanitize_key( wp_unslash( $_POST['feeds_ia_action'] ) ) : 'save_feeds';

		if ( 'save_feeds' === $action ) {
			$posted_feeds = isset( $_POST['feeds'] ) && is_array( $_POST['feeds'] ) ? $_POST['feeds'] : array();
			$to_save      = array();

			foreach ( $posted_feeds as $row ) {
				$row = is_array( $row ) ? $row : array();

				$id            = isset( $row['id'] ) ? wp_unslash( $row['id'] ) : '';
				$name          = isset( $row['name'] ) ? wp_unslash( $row['name'] ) : '';
				$url           = isset( $row['url'] ) ? wp_unslash( $row['url'] ) : '';
				$category      = isset( $row['category'] ) ? wp_unslash( $row['category'] ) : '';
				$status        = isset( $row['status'] ) ? wp_unslash( $row['status'] ) : '';
				$frequency     = isset( $row['frequency'] ) ? wp_unslash( $row['frequency'] ) : '';
				$items_per_run = isset( $row['items_per_run'] ) ? wp_unslash( $row['items_per_run'] ) : '';
				$mode          = isset( $row['mode'] ) ? wp_unslash( $row['mode'] ) : '';
				$last_run      = isset( $row['last_run'] ) ? wp_unslash( $row['last_run'] ) : '';

				// Ignora linhas completamente vazias (sem URL).
				if ( '' === trim( (string) $url ) ) {
					continue;
				}

				$to_save[] = array(
					'id'            => $id,
					'name'          => $name,
					'url'           => $url,
					'category'      => $category,
					'status'        => $status,
					'frequency'     => $frequency,
					'items_per_run' => $items_per_run,
					'mode'          => $mode,
					'last_run'      => $last_run,
				);
			}

			Feeds_IA_Settings::save_feeds( $to_save );
			$feeds = Feeds_IA_Settings::get_feeds();

			$notices[] = array(
				'type'    => 'success',
				'message' => 'Feeds salvos com sucesso.',
			);
		} elseif ( 'run_feed_now' === $action ) {
			$feed_id = isset( $_POST['feed_id'] ) ? sanitize_text_field( wp_unslash( $_POST['feed_id'] ) ) : '';

			if ( '' === $feed_id ) {
				$notices[] = array(
					'type'    => 'error',
					'message' => 'ID do feed não informado para processamento.',
				);
			} else {
				$feed_config = Feeds_IA_Settings::get_feed_by_id( $feed_id );

				if ( empty( $feed_config ) ) {
					$notices[] = array(
						'type'    => 'error',
						'message' => 'Feed não encontrado para processamento imediato.',
					);
				} else {
					Feeds_IA_Cron::run_for_feed( $feed_config );
					$notices[] = array(
						'type'    => 'success',
						'message' => 'Processamento imediato disparado para o feed selecionado. Novos itens serão criados como rascunho.',
					);
					// Atualiza feeds após possível atualização de last_run dentro do fluxo.
					$feeds = Feeds_IA_Settings::get_feeds();
				}
			}
		} elseif ( 'delete_feed' === $action ) {
			$feed_id = isset( $_POST['feed_id'] ) ? sanitize_text_field( wp_unslash( $_POST['feed_id'] ) ) : '';

			if ( '' === $feed_id ) {
				$notices[] = array(
					'type'    => 'error',
					'message' => 'ID do feed não informado para exclusão.',
				);
			} else {
				$current = Feeds_IA_Settings::get_feeds();
				$new     = array();

				foreach ( $current as $feed ) {
					if ( isset( $feed['id'] ) && $feed['id'] === $feed_id ) {
						continue;
					}
					$new[] = $feed;
				}

				Feeds_IA_Settings::save_feeds( $new );
				$feeds = Feeds_IA_Settings::get_feeds();

				$notices[] = array(
					'type'    => 'success',
					'message' => 'Feed removido com sucesso.',
				);
			}
		}
	}
}

?>
<div class="wrap feeds-ia-wrap">
	<h1><?php echo esc_html( 'Feeds IA – Feeds' ); ?></h1>

	<?php if ( ! empty( $notices ) ) : ?>
		<?php foreach ( $notices as $notice ) : ?>
			<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
				<p><?php echo esc_html( $notice['message'] ); ?></p>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>

	<p class="description">
		Esta tela define quais feeds RSS de RPG de mesa serão monitorados pelo plugin.
		Cada feed será lido periodicamente; notícias novas serão reescritas em português, com vocabulário de RPG, e salvas como rascunho no WordPress.
	</p>

	<form method="post" action="">
		<?php wp_nonce_field( 'feeds_ia_save_feeds', 'feeds_ia_feeds_nonce' ); ?>
		<input type="hidden" name="feeds_ia_action" value="save_feeds" />

		<table class="widefat fixed striped feeds-ia-table-feeds">
			<thead>
				<tr>
					<th>Nome interno</th>
					<th>URL do feed RSS</th>
					<th>Categoria</th>
					<th>Status</th>
					<th>Frequência (min)</th>
					<th>Itens por execução</th>
					<th>Modo</th>
					<th>Última execução</th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! empty( $feeds ) ) : ?>
					<?php foreach ( $feeds as $index => $feed ) : ?>
						<?php
						$feed_id   = isset( $feed['id'] ) ? $feed['id'] : '';
						$name      = isset( $feed['name'] ) ? $feed['name'] : '';
						$url       = isset( $feed['url'] ) ? $feed['url'] : '';
						$category  = isset( $feed['category'] ) ? (int) $feed['category'] : 0;
						$status    = isset( $feed['status'] ) ? $feed['status'] : 'inactive';
						$frequency = isset( $feed['frequency'] ) ? (int) $feed['frequency'] : 60;
						$items     = isset( $feed['items_per_run'] ) ? (int) $feed['items_per_run'] : 3;
						$mode      = isset( $feed['mode'] ) ? $feed['mode'] : 'draft';
						$last_run  = isset( $feed['last_run'] ) ? $feed['last_run'] : null;

						$last_run_display = '';
						if ( ! empty( $last_run ) && (int) $last_run > 0 ) {
							// Exibição no fuso configurado no WordPress (ex.: Brasília), formato 24h.
							$last_run_display = date_i18n( 'd/m/Y H:i', (int) $last_run );
						}
						?>
						<tr>
							<td>
								<input
									type="hidden"
									name="feeds[<?php echo esc_attr( $index ); ?>][id]"
									value="<?php echo esc_attr( $feed_id ); ?>"
								/>
								<input
									type="text"
									name="feeds[<?php echo esc_attr( $index ); ?>][name]"
									value="<?php echo esc_attr( $name ); ?>"
									class="regular-text"
									placeholder="Ex.: Notícias oficiais D&D"
								/>
							</td>
							<td>
								<input
									type="url"
									name="feeds[<?php echo esc_attr( $index ); ?>][url]"
									value="<?php echo esc_attr( $url ); ?>"
									class="regular-text"
									placeholder="https://exemplo.com/feed/"
								/>
							</td>
							<td>
								<?php
								wp_dropdown_categories(
									array(
										'show_option_all' => '(Sem categoria)',
										'hide_empty'      => 0,
										'name'            => 'feeds[' . esc_attr( $index ) . '][category]',
										'selected'        => $category,
									)
								);
								?>
							</td>
							<td>
								<select name="feeds[<?php echo esc_attr( $index ); ?>][status]">
									<option value="active" <?php selected( $status, 'active' ); ?>>Ativo</option>
									<option value="inactive" <?php selected( $status, 'inactive' ); ?>>Inativo</option>
								</select>
							</td>
							<td>
								<input
									type="number"
									name="feeds[<?php echo esc_attr( $index ); ?>][frequency]"
									value="<?php echo esc_attr( $frequency ); ?>"
									min="5"
									step="5"
									style="width: 80px;"
								/>
							</td>
							<td>
								<input
									type="number"
									name="feeds[<?php echo esc_attr( $index ); ?>][items_per_run]"
									value="<?php echo esc_attr( $items ); ?>"
									min="1"
									max="20"
									style="width: 80px;"
								/>
							</td>
							<td>
								<select name="feeds[<?php echo esc_attr( $index ); ?>][mode]">
									<option value="draft" <?php selected( $mode, 'draft' ); ?>>Rascunho</option>
									<option value="publish" <?php selected( $mode, 'publish' ); ?>>Publicar (ignorado: sempre rascunho)</option>
								</select>
								<p class="description">
									A publicação automática é desativada no código; todos os posts são salvos como rascunho.
								</p>
							</td>
							<td>
								<?php if ( $last_run_display ) : ?>
									<span><?php echo esc_html( $last_run_display ); ?></span>
								<?php else : ?>
									<span>—</span>
								<?php endif; ?>

								<div class="feeds-ia-feed-actions">
									<form method="post" action="" style="display:inline;">
										<?php wp_nonce_field( 'feeds_ia_save_feeds', 'feeds_ia_feeds_nonce' ); ?>
										<input type="hidden" name="feeds_ia_action" value="run_feed_now" />
										<input type="hidden" name="feed_id" value="<?php echo esc_attr( $feed_id ); ?>" />
										<button type="submit" class="button button-small">
											Processar agora
										</button>
									</form>

									<form method="post" action="" style="display:inline;margin-left:4px;">
										<?php wp_nonce_field( 'feeds_ia_save_feeds', 'feeds_ia_feeds_nonce' ); ?>
										<input type="hidden" name="feeds_ia_action" value="delete_feed" />
										<input type="hidden" name="feed_id" value="<?php echo esc_attr( $feed_id ); ?>" />
										<button type="submit" class="button button-small button-link-delete" onclick="return confirm('Remover este feed?');">
											Remover
										</button>
									</form>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>

				<!-- Linha em branco para novo feed -->
				<tr>
					<td>
						<input
							type="hidden"
							name="feeds[new_1][id]"
							value=""
						/>
						<input
							type="text"
							name="feeds[new_1][name]"
							value=""
							class="regular-text"
							placeholder="Novo feed de RPG"
						/>
					</td>
					<td>
						<input
							type="url"
							name="feeds[new_1][url]"
							value=""
							class="regular-text"
							placeholder="https://exemplo.com/feed/"
						/>
					</td>
					<td>
						<?php
						wp_dropdown_categories(
							array(
								'show_option_all' => '(Sem categoria)',
								'hide_empty'      => 0,
								'name'            => 'feeds[new_1][category]',
								'selected'        => 0,
							)
						);
						?>
					</td>
					<td>
						<select name="feeds[new_1][status]">
							<option value="active">Ativo</option>
							<option value="inactive">Inativo</option>
						</select>
					</td>
					<td>
						<input
							type="number"
							name="feeds[new_1][frequency]"
							value="60"
							min="5"
							step="5"
							style="width: 80px;"
						/>
					</td>
					<td>
						<input
							type="number"
							name="feeds[new_1][items_per_run]"
							value="3"
							min="1"
							max="20"
							style="width: 80px;"
						/>
					</td>
					<td>
						<select name="feeds[new_1][mode]">
							<option value="draft">Rascunho</option>
							<option value="publish">Publicar (ignorado)</option>
						</select>
					</td>
					<td>
						<input
							type="hidden"
							name="feeds[new_1][last_run]"
							value=""
						/>
						<span>—</span>
					</td>
				</tr>
			</tbody>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary">
				Salvar feeds
			</button>
		</p>
	</form>
</div>
