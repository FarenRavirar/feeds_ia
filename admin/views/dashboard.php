<?php
/**
 * Dashboard do Feeds IA.
 *
 * Exibe:
 * - Resumo geral (feeds, rascunhos, atividade recente).
 * - Execuções recentes (logs).
 * - Rascunhos recentes criados pelo plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

$summary      = class_exists( 'Feeds_IA_Stats' ) ? Feeds_IA_Stats::get_summary() : array();
$recent_runs  = class_exists( 'Feeds_IA_Stats' ) ? Feeds_IA_Stats::get_recent_runs( 10 ) : array();
$recent_posts = class_exists( 'Feeds_IA_Stats' ) ? Feeds_IA_Stats::get_recent_posts( 10 ) : array();

// Valores seguros.
$feeds_total        = isset( $summary['feeds_total'] ) ? (int) $summary['feeds_total'] : 0;
$feeds_active       = isset( $summary['feeds_active'] ) ? (int) $summary['feeds_active'] : 0;
$feeds_inactive     = isset( $summary['feeds_inactive'] ) ? (int) $summary['feeds_inactive'] : 0;
$posts_total        = isset( $summary['posts_total'] ) ? (int) $summary['posts_total'] : 0;
$posts_last_30_days = isset( $summary['posts_last_30_days'] ) ? (int) $summary['posts_last_30_days'] : 0;
$last_run_readable  = isset( $summary['last_run_readable'] ) ? $summary['last_run_readable'] : '—';

?>
<div class="wrap feeds-ia-wrap">
	<h1><?php echo esc_html( 'Feeds IA – Dashboard' ); ?></h1>

	<p class="description">
		Resumo da atividade de importação automática para conteúdos de RPG de mesa.
		Todas as datas consideram o fuso horário configurado no WordPress (por exemplo, horário de Brasília), em formato 24h (<code>d/m/Y H:i</code>).
	</p>

	<div class="feeds-ia-dashboard-metrics">
		<div class="feeds-ia-card">
			<h2>Feeds</h2>
			<ul>
				<li><strong>Total:</strong> <?php echo esc_html( $feeds_total ); ?></li>
				<li><strong>Ativos:</strong> <?php echo esc_html( $feeds_active ); ?></li>
				<li><strong>Inativos:</strong> <?php echo esc_html( $feeds_inactive ); ?></li>
			</ul>
		</div>

		<div class="feeds-ia-card">
			<h2>Rascunhos criados</h2>
			<ul>
				<li><strong>Total (todas as datas):</strong> <?php echo esc_html( $posts_total ); ?></li>
				<li><strong>Últimos 30 dias:</strong> <?php echo esc_html( $posts_last_30_days ); ?></li>
			</ul>
		</div>

		<div class="feeds-ia-card">
			<h2>Última execução</h2>
			<ul>
				<li><strong>Última execução bem-sucedida:</strong> <?php echo esc_html( $last_run_readable ); ?></li>
			</ul>
			<p class="description">
				Este valor é baseado no log mais recente com status de sucesso.
			</p>
		</div>

		<div class="feeds-ia-card">
			<h2>Notas sobre o fluxo</h2>
			<ul>
				<li>Todos os posts são criados como <strong>rascunho</strong>.</li>
				<li>Os textos são reescritos em <strong>português do Brasil</strong>.</li>
				<li>O plugin registra cada execução na aba <strong>Logs</strong>.</li>
			</ul>
		</div>
	</div>

	<h2>Execuções recentes</h2>

	<table class="widefat fixed striped feeds-ia-table-runs">
		<thead>
			<tr>
				<th>Data e hora</th>
				<th>Feed</th>
				<th>Status</th>
				<th>Título original</th>
				<th>Título gerado</th>
				<th>Mensagem</th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! empty( $recent_runs ) ) : ?>
				<?php foreach ( $recent_runs as $run ) : ?>
					<?php
					$log_ts     = isset( $run['log_at'] ) ? (int) $run['log_at'] : 0;
					$date_disp  = $log_ts > 0 ? date_i18n( 'd/m/Y H:i', $log_ts ) : '—';
					$feed_name  = isset( $run['feed_name'] ) ? $run['feed_name'] : '';
					$status     = isset( $run['status'] ) ? $run['status'] : '';
					$title_orig = isset( $run['title_original'] ) ? $run['title_original'] : '';
					$title_gen  = isset( $run['title_generated'] ) ? $run['title_generated'] : '';
					$message    = isset( $run['message'] ) ? $run['message'] : '';
					?>
					<tr>
						<td><?php echo esc_html( $date_disp ); ?></td>
						<td><?php echo esc_html( $feed_name ); ?></td>
						<td><?php echo esc_html( $status ); ?></td>
						<td><?php echo esc_html( $title_orig ); ?></td>
						<td><?php echo esc_html( $title_gen ); ?></td>
						<td><?php echo esc_html( $message ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr>
					<td colspan="6">Nenhuma execução registrada dentro do período considerado.</td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>

	<h2>Rascunhos recentes criados pelo Feeds IA</h2>

	<table class="widefat fixed striped feeds-ia-table-posts">
		<thead>
			<tr>
				<th>Data e hora</th>
				<th>Título</th>
				<th>Status</th>
				<th>Ação</th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! empty( $recent_posts ) ) : ?>
				<?php foreach ( $recent_posts as $post_item ) : ?>
					<?php
					$post_id   = isset( $post_item['ID'] ) ? (int) $post_item['ID'] : 0;
					$title     = isset( $post_item['title'] ) ? $post_item['title'] : '';
					$status    = isset( $post_item['status'] ) ? $post_item['status'] : '';
					$date_ts   = isset( $post_item['date'] ) ? (int) $post_item['date'] : 0;
					$date_disp = $date_ts > 0 ? date_i18n( 'd/m/Y H:i', $date_ts ) : '—';
					$edit_link = isset( $post_item['edit_link'] ) ? $post_item['edit_link'] : '';
					?>
					<tr>
						<td><?php echo esc_html( $date_disp ); ?></td>
						<td><?php echo esc_html( $title ); ?></td>
						<td><?php echo esc_html( $status ); ?></td>
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
					<td colspan="4">Nenhum rascunho criado pelo plugin foi encontrado.</td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>
</div>