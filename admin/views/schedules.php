<?php
/**
 * Tela de agendamentos do Feeds IA.
 *
 * Permite:
 * - Definir horários fixos (HH:MM, 24h) em que um feed deve rodar.
 * - Selecionar dias da semana em português (Domingo a Sábado).
 * - Ativar ou desativar cada agendamento.
 *
 * Observações:
 * - Todos os cálculos de horário usam current_time('timestamp'), ou seja,
 *   o fuso configurado no WordPress (ex.: horário de Brasília).
 * - A execução respeita a combinação dia da semana + horário.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

// Notificações.
$notices = array();

// Feeds disponíveis para vincular.
$feeds = class_exists( 'Feeds_IA_Settings' ) ? Feeds_IA_Settings::get_feeds() : array();

// Agendamentos atuais.
$schedules = class_exists( 'Feeds_IA_Schedules' ) ? Feeds_IA_Schedules::get_schedules() : array();

// Mapa de dias: código interno → rótulo em português.
$day_labels = array(
	'sun' => 'Domingo',
	'mon' => 'Segunda-feira',
	'tue' => 'Terça-feira',
	'wed' => 'Quarta-feira',
	'thu' => 'Quinta-feira',
	'fri' => 'Sexta-feira',
	'sat' => 'Sábado',
);

// Tratamento de POST.
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['feeds_ia_schedules_nonce'] ) ) {
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['feeds_ia_schedules_nonce'] ) ), 'feeds_ia_save_schedules' ) ) {
		$notices[] = array(
			'type'    => 'error',
			'message' => 'Falha na validação do formulário. Tente novamente.',
		);
	} else {
		$action = isset( $_POST['feeds_ia_action'] ) ? sanitize_key( wp_unslash( $_POST['feeds_ia_action'] ) ) : 'save_schedules';

		if ( 'save_schedules' === $action ) {
			$posted = isset( $_POST['schedules'] ) && is_array( $_POST['schedules'] ) ? $_POST['schedules'] : array();
			$to_save = array();

			foreach ( $posted as $row ) {
				$row = is_array( $row ) ? $row : array();

				$id          = isset( $row['id'] ) ? wp_unslash( $row['id'] ) : '';
				$feed_id     = isset( $row['feed_id'] ) ? wp_unslash( $row['feed_id'] ) : '';
				$time_of_day = isset( $row['time_of_day'] ) ? wp_unslash( $row['time_of_day'] ) : '';
				$status      = isset( $row['status'] ) ? wp_unslash( $row['status'] ) : '';
				$days        = isset( $row['days_of_week'] ) && is_array( $row['days_of_week'] ) ? $row['days_of_week'] : array();
				$last_run    = isset( $row['last_run'] ) ? wp_unslash( $row['last_run'] ) : '';

				// Ignora agendamentos sem feed ou sem horário.
				if ( '' === trim( (string) $feed_id ) || '' === trim( (string) $time_of_day ) ) {
					continue;
				}

				$to_save[] = array(
					'id'           => $id,
					'feed_id'      => $feed_id,
					'time_of_day'  => $time_of_day,
					'days_of_week' => $days,
					'status'       => $status,
					'last_run'     => $last_run,
				);
			}

			if ( class_exists( 'Feeds_IA_Schedules' ) ) {
				Feeds_IA_Schedules::save_schedules( $to_save );
				$schedules = Feeds_IA_Schedules::get_schedules();
			}

			$notices[] = array(
				'type'    => 'success',
				'message' => 'Agendamentos salvos com sucesso.',
			);
		} elseif ( 'delete_schedule' === $action ) {
			$schedule_id = isset( $_POST['schedule_id'] ) ? sanitize_text_field( wp_unslash( $_POST['schedule_id'] ) ) : '';

			if ( '' === $schedule_id ) {
				$notices[] = array(
					'type'    => 'error',
					'message' => 'ID do agendamento não informado para exclusão.',
				);
			} elseif ( class_exists( 'Feeds_IA_Schedules' ) ) {
				Feeds_IA_Schedules::delete_schedule( $schedule_id );
				$schedules = Feeds_IA_Schedules::get_schedules();

				$notices[] = array(
					'type'    => 'success',
					'message' => 'Agendamento removido com sucesso.',
				);
			}
		}
	}
}

?>
<div class="wrap feeds-ia-wrap">
	<h1><?php echo esc_html( 'Feeds IA – Agendamentos' ); ?></h1>

	<p class="description">
		Os agendamentos definem horários fixos, em horário de Brasília (ou outro fuso configurado no WordPress),
		em que cada feed será processado automaticamente. O horário é sempre interpretado em formato 24h (por exemplo, <code>08:00</code>, <code>14:30</code>, <code>23:45</code>).
	</p>

	<?php if ( ! empty( $notices ) ) : ?>
		<?php foreach ( $notices as $notice ) : ?>
			<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
				<p><?php echo esc_html( $notice['message'] ); ?></p>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>

	<form method="post" action="">
		<?php wp_nonce_field( 'feeds_ia_save_schedules', 'feeds_ia_schedules_nonce' ); ?>
		<input type="hidden" name="feeds_ia_action" value="save_schedules" />

		<table class="widefat fixed striped feeds-ia-table-schedules">
			<thead>
				<tr>
					<th>Feed</th>
					<th>Horário (24h)</th>
					<th>Dias da semana</th>
					<th>Status</th>
					<th>Última execução</th>
					<th>Ações</th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! empty( $schedules ) ) : ?>
					<?php foreach ( $schedules as $index => $schedule ) : ?>
						<?php
						$schedule_id  = isset( $schedule['id'] ) ? $schedule['id'] : '';
						$feed_id      = isset( $schedule['feed_id'] ) ? $schedule['feed_id'] : '';
						$time_of_day  = isset( $schedule['time_of_day'] ) ? $schedule['time_of_day'] : '08:00';
						$days_of_week = isset( $schedule['days_of_week'] ) && is_array( $schedule['days_of_week'] ) ? $schedule['days_of_week'] : array();
						$status       = isset( $schedule['status'] ) ? $schedule['status'] : 'inactive';
						$last_run     = isset( $schedule['last_run'] ) ? $schedule['last_run'] : null;

						$last_run_display = '';
						if ( ! empty( $last_run ) && (int) $last_run > 0 ) {
							$last_run_display = date_i18n( 'd/m/Y H:i', (int) $last_run );
						}

						// Nome do feed.
						$feed_label = $feed_id;
						if ( ! empty( $feeds ) ) {
                            foreach ( $feeds as $feed ) {
                                if ( isset( $feed['id'] ) && $feed['id'] === $feed_id ) {
                                    $feed_label = ! empty( $feed['name'] ) ? $feed['name'] : $feed_id;
                                    break;
                                }
                            }
						}
						?>
						<tr>
							<td>
								<input
									type="hidden"
									name="schedules[<?php echo esc_attr( $index ); ?>][id]"
									value="<?php echo esc_attr( $schedule_id ); ?>"
								/>
								<select name="schedules[<?php echo esc_attr( $index ); ?>][feed_id]">
									<option value="">Selecione um feed</option>
									<?php if ( ! empty( $feeds ) ) : ?>
										<?php foreach ( $feeds as $feed ) : ?>
											<?php
											$fid  = isset( $feed['id'] ) ? $feed['id'] : '';
											$name = isset( $feed['name'] ) ? $feed['name'] : $fid;
											?>
											<option value="<?php echo esc_attr( $fid ); ?>" <?php selected( $feed_id, $fid ); ?>>
												<?php echo esc_html( $name ); ?>
											</option>
										<?php endforeach; ?>
									<?php endif; ?>
								</select>
							</td>
							<td>
								<input
									type="time"
									name="schedules[<?php echo esc_attr( $index ); ?>][time_of_day]"
									value="<?php echo esc_attr( $time_of_day ); ?>"
									step="60"
								/>
								<p class="description">
									Horário em formato 24h (HH:MM), interpretado no fuso horário configurado no WordPress.
								</p>
							</td>
							<td class="feeds-ia-days-column">
								<?php foreach ( $day_labels as $code => $label ) : ?>
									<label style="display:block;">
										<input
											type="checkbox"
											name="schedules[<?php echo esc_attr( $index ); ?>][days_of_week][]"
											value="<?php echo esc_attr( $code ); ?>"
											<?php checked( in_array( $code, $days_of_week, true ) ); ?>
										/>
										<?php echo esc_html( $label ); ?>
									</label>
								<?php endforeach; ?>
								<p class="description">
									Se nenhum dia for marcado, o plugin interpretará como "todos os dias".
								</p>
							</td>
							<td>
								<select name="schedules[<?php echo esc_attr( $index ); ?>][status]">
									<option value="active" <?php selected( $status, 'active' ); ?>>Ativo</option>
									<option value="inactive" <?php selected( $status, 'inactive' ); ?>>Inativo</option>
								</select>
							</td>
							<td>
								<?php echo $last_run_display ? esc_html( $last_run_display ) : '—'; ?>
								<input
									type="hidden"
									name="schedules[<?php echo esc_attr( $index ); ?>][last_run]"
									value="<?php echo esc_attr( $last_run ); ?>"
								/>
							</td>
							<td>
								<form method="post" action="" style="display:inline;">
									<?php wp_nonce_field( 'feeds_ia_save_schedules', 'feeds_ia_schedules_nonce' ); ?>
									<input type="hidden" name="feeds_ia_action" value="delete_schedule" />
									<input type="hidden" name="schedule_id" value="<?php echo esc_attr( $schedule_id ); ?>" />
									<button
										type="submit"
										class="button button-small button-link-delete"
										onclick="return confirm('Remover este agendamento?');"
									>
										Remover
									</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>

				<!-- Linha em branco para novo agendamento -->
				<tr>
					<td>
						<input
							type="hidden"
							name="schedules[new_1][id]"
							value=""
						/>
						<select name="schedules[new_1][feed_id]">
							<option value="">Selecione um feed</option>
							<?php if ( ! empty( $feeds ) ) : ?>
								<?php foreach ( $feeds as $feed ) : ?>
									<?php
									$fid  = isset( $feed['id'] ) ? $feed['id'] : '';
									$name = isset( $feed['name'] ) ? $feed['name'] : $fid;
									?>
									<option value="<?php echo esc_attr( $fid ); ?>">
										<?php echo esc_html( $name ); ?>
									</option>
								<?php endforeach; ?>
							<?php endif; ?>
						</select>
					</td>
					<td>
						<input
							type="time"
							name="schedules[new_1][time_of_day]"
							value="08:00"
							step="60"
						/>
					</td>
					<td class="feeds-ia-days-column">
						<?php foreach ( $day_labels as $code => $label ) : ?>
							<label style="display:block;">
								<input
									type="checkbox"
									name="schedules[new_1][days_of_week][]"
									value="<?php echo esc_attr( $code ); ?>"
									<?php checked( in_array( $code, array( 'mon', 'tue', 'wed', 'thu', 'fri' ), true ) ); ?>
								/>
								<?php echo esc_html( $label ); ?>
							</label>
						<?php endforeach; ?>
						<p class="description">
							Por padrão, os dias úteis vêm marcados. Ajuste conforme o ritmo de publicação desejado.
						</p>
					</td>
					<td>
						<select name="schedules[new_1][status]">
							<option value="active">Ativo</option>
							<option value="inactive">Inativo</option>
						</select>
					</td>
					<td>
						<input
							type="hidden"
							name="schedules[new_1][last_run]"
							value=""
						/>
						<span>—</span>
					</td>
					<td>
						<span>—</span>
					</td>
				</tr>
			</tbody>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary">
				Salvar agendamentos
			</button>
		</p>
	</form>
</div>
