<?php
/**
 * Tela de logs do Feeds IA.
 *
 * Exibe:
 * - Data e hora da ocorrência (formato 24h, timezone do WordPress, ex.: Brasília).
 * - Feed de origem.
 * - Título original e título gerado.
 * - Status.
 * - Mensagem detalhada.
 * - Link para o rascunho, quando existir.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}



if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

$notices = array();

// Tratamento de POST para limpar logs
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['feeds_ia_logs_nonce'] ) ) {
	if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['feeds_ia_logs_nonce'] ) ), 'feeds_ia_clear_logs' ) ) {
		$action = isset( $_POST['feeds_ia_action'] ) ? sanitize_key( wp_unslash( $_POST['feeds_ia_action'] ) ) : '';

		if ( 'clear_logs' === $action ) {
			Feeds_IA_Logger::clear_logs();
			$notices[] = array(
				'type'    => 'success',
				'message' => 'Logs limpos com sucesso.',
			);
		}
	} else {
		$notices[] = array(
			'type'    => 'error',
			'message' => 'Falha na validação do formulário.',
		);
	}
}

// Filtros via GET.
$filter_feed   = isset( $_GET['feeds_ia_filter_feed'] ) ? sanitize_text_field( wp_unslash( $_GET['feeds_ia_filter_feed'] ) ) : '';
$filter_status = isset( $_GET['feeds_ia_filter_status'] ) ? sanitize_text_field( wp_unslash( $_GET['feeds_ia_filter_status'] ) ) : '';
$filter_days   = isset( $_GET['feeds_ia_filter_days'] ) ? (int) $_GET['feeds_ia_filter_days'] : 7;

if ( $filter_days <= 0 ) {
	$filter_days = 7;
}

$args = array(
	'feed_id' => $filter_feed,
	'status'  => $filter_status,
	'days'    => $filter_days,
	'limit'   => 50,
);

$logs = array();
if ( class_exists( 'Feeds_IA_Logger' ) ) {
	$logs = Feeds_IA_Logger::get_logs( $args );
}

// Lista de feeds para o filtro.
$feeds = array();
if ( class_exists( 'Feeds_IA_Settings' ) ) {
	$feeds = Feeds_IA_Settings::get_feeds();
}

$statuses = array(
	''                           => 'Todos os status',
	'success'                    => 'Sucesso (rascunho criado)',
	'feed-start'                 => 'Início de processamento',
	'feed-completed'             => 'Processamento concluído',
	'feed-completed-with-errors' => 'Concluído com erros',
	'error-feed'                 => 'Erro de feed',
	'error-ai'                   => 'Erro de IA',
	'error-publish'              => 'Erro ao criar rascunho',
	'error-image'                => 'Erro de imagem destacada',
);

?>
<div class="wrap feeds-ia-wrap">
	<h1><?php echo esc_html( 'Feeds IA – Logs' ); ?></h1>

	<?php if ( ! empty( $notices ) ) : ?>
		<?php foreach ( $notices as $notice ) : ?>
			<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
				<p><?php echo esc_html( $notice['message'] ); ?></p>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>

	<p class="description">
		Esta tela registra cada tentativa de importação realizada pelo Feeds IA.
		Todos os horários respeitam o fuso configurado no WordPress (por exemplo, horário de Brasília), em formato 24h (<code>d/m/Y H:i</code>).
	</p>

	<form method="get" action="">
		<input type="hidden" name="page" value="feeds-ia-logs" />

		<div class="feeds-ia-filters">
			<div class="feeds-ia-filter-item">
				<label for="feeds_ia_filter_feed"><strong>Feed</strong></label><br />
				<select name="feeds_ia_filter_feed" id="feeds_ia_filter_feed">
					<option value="">Todos os feeds</option>
					<?php if ( ! empty( $feeds ) ) : ?>
						<?php foreach ( $feeds as $feed ) : ?>
							<?php
							$fid  = isset( $feed['id'] ) ? $feed['id'] : '';
							$name = isset( $feed['name'] ) ? $feed['name'] : $fid;
							?>
							<option value="<?php echo esc_attr( $fid ); ?>" <?php selected( $filter_feed, $fid ); ?>>
								<?php echo esc_html( $name ); ?>
							</option>
						<?php endforeach; ?>
					<?php endif; ?>
				</select>
			</div>

			<div class="feeds-ia-filter-item">
				<label for="feeds_ia_filter_status"><strong>Status</strong></label><br />
				<select name="feeds_ia_filter_status" id="feeds_ia_filter_status">
					<?php foreach ( $statuses as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filter_status, $value ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="feeds-ia-filter-item">
				<label for="feeds_ia_filter_days"><strong>Período</strong></label><br />
				<select name="feeds_ia_filter_days" id="feeds_ia_filter_days">
					<option value="1" <?php selected( $filter_days, 1 ); ?>>Últimas 24 horas</option>
					<option value="7" <?php selected( $filter_days, 7 ); ?>>Últimos 7 dias</option>
					<option value="30" <?php selected( $filter_days, 30 ); ?>>Últimos 30 dias</option>
					<option value="90" <?php selected( $filter_days, 90 ); ?>>Últimos 90 dias</option>
				</select>
			</div>

			<div class="feeds-ia-filter-item feeds-ia-filter-submit">
				<button type="submit" class="button">
					Filtrar
				</button>
			</div>
		</div>
	</form>

	<table class="widefat fixed striped feeds-ia-table-logs">
		<thead>
			<tr>
				<th>Data e hora</th>
				<th>Feed</th>
				<th>Status</th>
				<th>Título original</th>
				<th>Título gerado</th>
				<th>Mensagem</th>
				<th>Rascunho</th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! empty( $logs ) ) : ?>
				<?php foreach ( $logs as $log ) : ?>
					<?php
					$log_ts     = isset( $log['log_at'] ) ? (int) $log['log_at'] : 0;
					$feed_name  = isset( $log['feed_name'] ) ? $log['feed_name'] : '';
					$status     = isset( $log['status'] ) ? $log['status'] : '';
					$title_orig = isset( $log['title_original'] ) ? $log['title_original'] : '';
					$title_gen  = isset( $log['title_generated'] ) ? $log['title_generated'] : '';
					$message    = isset( $log['message'] ) ? $log['message'] : '';
					$post_id    = isset( $log['post_id'] ) ? (int) $log['post_id'] : 0;

					$date_display = $log_ts > 0 ? date_i18n( 'd/m/Y H:i', $log_ts ) : '—';

					$edit_link = $post_id ? get_edit_post_link( $post_id ) : '';
					?>
					<tr>
						<td><?php echo esc_html( $date_display ); ?></td>
						<td><?php echo esc_html( $feed_name ); ?></td>
						<td><?php echo esc_html( $status ); ?></td>
						<td><?php echo esc_html( $title_orig ); ?></td>
						<td><?php echo esc_html( $title_gen ); ?></td>
						<td><?php echo esc_html( $message ); ?></td>
						<td>
							<?php if ( $edit_link ) : ?>
								<a href="<?php echo esc_url( $edit_link ); ?>">Editar rascunho</a>
							<?php else : ?>
								<span>—</span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr>
					<td colspan="7">
						Nenhum log encontrado para os filtros selecionados.
					</td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>

	<div class="feeds-ia-logs-actions">
		<form method="post" action="" style="display: inline-block;">
			<?php wp_nonce_field( 'feeds_ia_clear_logs', 'feeds_ia_logs_nonce' ); ?>
			<input type="hidden" name="feeds_ia_action" value="clear_logs" />
			<button type="submit" class="button button-secondary" 
				onclick="return confirm('Tem certeza de que deseja limpar todos os logs? Esta ação não pode ser desfeita.');">
				Limpar todos os logs
			</button>
		</form>
	</div>
</div>
