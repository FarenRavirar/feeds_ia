<?php
/**
 * Gerenciamento de agendamentos do Feeds IA.
 *
 * Cada agendamento define:
 * - Qual feed rodar.
 * - Em que horário (HH:MM, 24h).
 * - Em quais dias da semana.
 * - Status (ativo / inativo).
 *
 * Regras:
 * - Todos os cálculos de data/hora usam current_time('timestamp'),
 *   ou seja, o fuso horário configurado no WordPress (ex.: Brasília).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe Feeds_IA_Schedules
 */
class Feeds_IA_Schedules {

	/**
	 * Nome da option que armazena os agendamentos.
	 */
	const OPTION_NAME = 'feeds_ia_schedules';

	/**
	 * Retorna todos os agendamentos, já sanitizados.
	 *
	 * @return array
	 */
	public static function get_schedules() {
		$schedules = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $schedules ) ) {
			$schedules = array();
		}

		$sanitized = array();

		foreach ( $schedules as $schedule ) {
			$sanitized[] = self::sanitize_schedule( $schedule );
		}

		return $sanitized;
	}

	/**
	 * Salva a lista completa de agendamentos.
	 *
	 * @param array $schedules Lista de agendamentos.
	 */
	public static function save_schedules( array $schedules ) {
		$clean = array();

		foreach ( $schedules as $schedule ) {
			$clean[] = self::sanitize_schedule( $schedule );
		}

		update_option( self::OPTION_NAME, $clean );
	}

	/**
	 * Quantidade total de agendamentos.
	 *
	 * @return int
	 */
	public static function count_schedules() {
		$schedules = self::get_schedules();
		return count( $schedules );
	}

	/**
	 * Retorna apenas os agendamentos ativos.
	 *
	 * @return array
	 */
	public static function get_active_schedules() {
		$schedules = self::get_schedules();
		$active    = array();

		foreach ( $schedules as $schedule ) {
			if ( 'active' === $schedule['status'] ) {
				$active[] = $schedule;
			}
		}

		return $active;
	}

	/**
	 * Atualiza o campo last_run de um agendamento específico.
	 *
	 * @param string $schedule_id ID interno do agendamento.
	 * @param int    $timestamp   Timestamp a gravar.
	 */
	public static function update_last_run( $schedule_id, $timestamp ) {
		$schedule_id = sanitize_text_field( $schedule_id );
		$timestamp   = (int) $timestamp;

		if ( '' === $schedule_id || $timestamp <= 0 ) {
			return;
		}

		$schedules   = self::get_schedules();
		$updated_any = false;

		foreach ( $schedules as &$schedule ) {
			if ( $schedule['id'] === $schedule_id ) {
				$schedule['last_run'] = $timestamp;
				$updated_any          = true;
				break;
			}
		}
		unset( $schedule );

		if ( $updated_any ) {
			self::save_schedules( $schedules );
		}
	}

	/**
	 * Remove um agendamento pelo ID interno.
	 *
	 * @param string $schedule_id ID do agendamento.
	 */
	public static function delete_schedule( $schedule_id ) {
		$schedule_id = sanitize_text_field( $schedule_id );

		if ( '' === $schedule_id ) {
			return;
		}

		$schedules = self::get_schedules();
		$new       = array();

		foreach ( $schedules as $schedule ) {
			if ( $schedule['id'] === $schedule_id ) {
				continue;
			}
			$new[] = $schedule;
		}

		self::save_schedules( $new );
	}

	/**
	 * Sanitiza um agendamento.
	 *
	 * Estrutura:
	 * [
	 *   'id'           => string,
	 *   'feed_id'      => string,
	 *   'time_of_day'  => 'HH:MM',
	 *   'days_of_week' => ['sun','mon','tue','wed','thu','fri','sat'],
	 *   'status'       => 'active'|'inactive',
	 *   'last_run'     => int|null,
	 * ]
	 *
	 * @param array $schedule Dados crus.
	 * @return array
	 */
	public static function sanitize_schedule( $schedule ) {
		$defaults = array(
			'id'           => '',
			'feed_id'      => '',
			'time_of_day'  => '08:00',
			'days_of_week' => array( 'mon', 'tue', 'wed', 'thu', 'fri' ),
			'status'       => 'active',
			'last_run'     => null,
		);

		$schedule = wp_parse_args( is_array( $schedule ) ? $schedule : array(), $defaults );

		// ID: se vazio, gera um novo.
		$schedule['id'] = sanitize_text_field( $schedule['id'] );
		if ( '' === $schedule['id'] ) {
			$schedule['id'] = 'sched_' . wp_generate_password( 8, false, false );
		}

		// Feed ID.
		$schedule['feed_id'] = sanitize_text_field( $schedule['feed_id'] );

		// Horário do dia (HH:MM, 24h).
		$time = trim( (string) $schedule['time_of_day'] );
		if ( ! preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
			$time = '08:00';
		}
		$schedule['time_of_day'] = $time;

		// Dias da semana.
		$valid_days = array( 'sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat' );
		$days       = array();

		if ( isset( $schedule['days_of_week'] ) && is_array( $schedule['days_of_week'] ) ) {
			foreach ( $schedule['days_of_week'] as $day ) {
				$day = sanitize_key( $day );
				if ( in_array( $day, $valid_days, true ) ) {
					$days[] = $day;
				}
			}
		}

		// Se o array vier vazio, interpreta como "todos os dias".
		if ( empty( $days ) ) {
			$days = $valid_days;
		}

		$schedule['days_of_week'] = array_values( array_unique( $days ) );

		// Status.
		$status = sanitize_key( $schedule['status'] );
		if ( ! in_array( $status, array( 'active', 'inactive' ), true ) ) {
			$status = 'inactive';
		}
		$schedule['status'] = $status;

		// last_run.
		if ( null === $schedule['last_run'] || '' === $schedule['last_run'] ) {
			$schedule['last_run'] = null;
		} else {
			$schedule['last_run'] = (int) $schedule['last_run'];
			if ( $schedule['last_run'] <= 0 ) {
				$schedule['last_run'] = null;
			}
		}

		return $schedule;
	}

	/**
	 * Verifica se um agendamento está "vencido" e deve rodar agora.
	 *
	 * Regras:
	 * - Precisa estar com status 'active'.
	 * - O dia da semana atual (no fuso do WordPress) precisa estar em days_of_week.
	 * - O horário atual (H:i) precisa ser igual a time_of_day.
	 * - Se last_run já ocorreu no MESMO minuto (Y-m-d H:i), não roda novamente.
	 *
	 * @param array    $schedule  Agendamento sanitizado.
	 * @param int|null $timestamp Timestamp atual; se null, usa current_time('timestamp').
	 *
	 * @return bool
	 */
	public static function is_due( array $schedule, $timestamp = null ) {
		$schedule = self::sanitize_schedule( $schedule );

		if ( 'active' !== $schedule['status'] ) {
			return false;
		}

		$now_ts = ( null === $timestamp )
			? current_time( 'timestamp' )
			: (int) $timestamp;

		if ( $now_ts <= 0 ) {
			return false;
		}

		// Horário atual (HH:MM), respeitando o timezone do WordPress.
		$current_time_str = date_i18n( 'H:i', $now_ts );

		if ( $current_time_str !== $schedule['time_of_day'] ) {
			return false;
		}

		// Dia da semana atual (0 = domingo, 6 = sábado) → 'sun'...'sat'.
		$day_index = (int) date_i18n( 'w', $now_ts );
		$map_days  = array(
			0 => 'sun',
			1 => 'mon',
			2 => 'tue',
			3 => 'wed',
			4 => 'thu',
			5 => 'fri',
			6 => 'sat',
		);

		$current_day = isset( $map_days[ $day_index ] ) ? $map_days[ $day_index ] : 'sun';

		if ( ! in_array( $current_day, $schedule['days_of_week'], true ) ) {
			return false;
		}

		// Evita rodar duas vezes no mesmo minuto para o mesmo agendamento.
		if ( ! empty( $schedule['last_run'] ) && $schedule['last_run'] > 0 ) {
			$last_key = date_i18n( 'Y-m-d H:i', $schedule['last_run'] );
			$now_key  = date_i18n( 'Y-m-d H:i', $now_ts );

			if ( $last_key === $now_key ) {
				return false;
			}
		}

		return true;
	}
}
