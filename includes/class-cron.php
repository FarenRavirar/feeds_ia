<?php
/**
 * Agendamento e execução periódica do Feeds IA.
 *
 * Regras:
 * - Usa wp_cron com intervalo fixo (15 minutos).
 * - Sempre considera o horário configurado no WordPress (ex.: Brasília).
 * - Primeiro processa agendamentos (class-schedules), depois usa frequência por feed
 *   para os feeds que não têm agendamento associado.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe Feeds_IA_Cron
 */
class Feeds_IA_Cron {

	/**
	 * Nome do hook do cron.
	 */
	const CRON_HOOK = 'feeds_ia_cron';

	/**
	 * Nome do intervalo personalizado (15 minutos).
	 */
	const CRON_SCHEDULE = 'feeds_ia_15min';

	/**
	 * Inicializa hooks do cron.
	 */
	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_custom_schedule' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_scheduled' ) );
	}

	/**
	 * Registra o evento agendado (chamado na ativação do plugin).
	 */
	public static function register_events() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			$timestamp = current_time( 'timestamp' );
			wp_schedule_event( $timestamp, self::CRON_SCHEDULE, self::CRON_HOOK );
		}
	}

	/**
	 * Remove eventos agendados (chamado na desativação do plugin).
	 */
	public static function clear_events() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
			$timestamp = wp_next_scheduled( self::CRON_HOOK );
		}
	}

	/**
	 * Adiciona um intervalo personalizado de 15 minutos.
	 *
	 * @param array $schedules Intervalos existentes.
	 * @return array
	 */
	public static function add_custom_schedule( $schedules ) {
		if ( ! isset( $schedules[ self::CRON_SCHEDULE ] ) ) {
			$schedules[ self::CRON_SCHEDULE ] = array(
				'interval' => 15 * MINUTE_IN_SECONDS,
				'display'  => __( 'A cada 15 minutos (Feeds IA)', 'feeds-ia' ),
			);
		}

		return $schedules;
	}

	/**
	 * Execução principal chamada pelo wp_cron.
	 *
	 * Fluxo:
	 * - Obtém timestamp atual no fuso do WordPress (ex.: Brasília).
	 * - Carrega feeds.
	 * - Executa primeiro os agendamentos (se houver).
	 * - Depois roda por frequência para feeds sem agendamento.
	 */
	public static function run_scheduled() {
		$now   = current_time( 'timestamp' );
		$feeds = Feeds_IA_Settings::get_feeds();

		if ( empty( $feeds ) || ! is_array( $feeds ) ) {
			return;
		}

		// Normaliza feeds.
		foreach ( $feeds as $i => $feed ) {
			$feeds[ $i ] = Feeds_IA_Settings::sanitize_feed_config( $feed );
		}

		$feeds_by_id = array();
		foreach ( $feeds as $feed ) {
			if ( ! empty( $feed['id'] ) ) {
				$feeds_by_id[ $feed['id'] ] = $feed;
			}
		}

		$feeds_processados_por_agendamento = array();

		// 1) Agendamentos (horário fixo/dias da semana).
		if ( class_exists( 'Feeds_IA_Schedules' ) ) {
			$schedules = Feeds_IA_Schedules::get_active_schedules();

			foreach ( $schedules as $schedule ) {
				$schedule = Feeds_IA_Schedules::sanitize_schedule( $schedule );

				if ( empty( $schedule['feed_id'] ) ) {
					continue;
				}

				if ( ! Feeds_IA_Schedules::is_due( $schedule, $now ) ) {
					continue;
				}

				if ( ! isset( $feeds_by_id[ $schedule['feed_id'] ] ) ) {
					continue;
				}

				$feed_config = $feeds_by_id[ $schedule['feed_id'] ];

				self::run_for_feed( $feed_config, $now );

				// Atualiza last_run do agendamento.
				Feeds_IA_Schedules::update_last_run( $schedule['id'], $now );

				$feeds_processados_por_agendamento[ $schedule['feed_id'] ] = true;
			}
		}

		// 2) Frequência por feed (somente para feeds sem agendamento).
		$feeds_atualizados = array();

		foreach ( $feeds as $feed ) {
			if ( empty( $feed['id'] ) ) {
				continue;
			}

			// Se já foi processado via agendamento, não usa frequência.
			if ( isset( $feeds_processados_por_agendamento[ $feed['id'] ] ) ) {
				$feeds_atualizados[] = $feed;
				continue;
			}

			if ( ! self::should_run_feed( $feed, $now ) ) {
				$feeds_atualizados[] = $feed;
				continue;
			}

			self::run_for_feed( $feed, $now );

			$feed['last_run']      = $now;
			$feeds_atualizados[] = $feed;
		}

		// Salva feeds com last_run atualizado.
		Feeds_IA_Settings::save_feeds( $feeds_atualizados );
	}

	/**
	 * Verifica se um feed deve rodar com base em frequência e last_run.
	 *
	 * @param array $feed   Configuração do feed (sanitizada).
	 * @param int   $now_ts Timestamp atual (WordPress timezone).
	 *
	 * @return bool
	 */
	protected static function should_run_feed( array $feed, $now_ts ) {
		$now_ts = (int) $now_ts;

		// Frequência em minutos → segundos.
		$frequency_minutes = isset( $feed['frequency'] ) ? (int) $feed['frequency'] : 0;

		if ( $frequency_minutes <= 0 ) {
			// Se frequência não for válida, não roda automaticamente.
			return false;
		}

		$interval = $frequency_minutes * MINUTE_IN_SECONDS;

		$last_run = isset( $feed['last_run'] ) ? (int) $feed['last_run'] : 0;

		// Nunca rodado: roda agora.
		if ( $last_run <= 0 ) {
			return true;
		}

		// Roda se o intervalo já foi ultrapassado.
		return ( $now_ts - $last_run ) >= $interval;
	}

	/**
	 * Executa o fluxo completo para um feed específico:
	 * - Coleta itens novos.
	 * - Pré-processa conteúdo.
	 * - Chama IA.
	 * - Cria posts em rascunho.
	 * - Registra logs de erro e de resumo.
	 *
	 * @param array $feed_config Configuração do feed.
	 * @param int   $now_ts      Timestamp atual (para logs, se necessário).
	 */
	public static function run_for_feed( array $feed_config, $now_ts = null ) {
		if ( ! class_exists( 'Feeds_IA_Feeds_Manager' )
			|| ! class_exists( 'Feeds_IA_Content_Processor' )
			|| ! class_exists( 'Feeds_IA_AI' )
			|| ! class_exists( 'Feeds_IA_Publisher' )
		) {
			return;
		}

		$feed_config = Feeds_IA_Settings::sanitize_feed_config( $feed_config );
		$feed_id     = isset( $feed_config['id'] ) ? sanitize_text_field( $feed_config['id'] ) : '';
		$feed_name   = isset( $feed_config['name'] ) ? wp_strip_all_tags( $feed_config['name'] ) : '';

		if ( null === $now_ts ) {
			$now_ts = current_time( 'timestamp' );
		}

		try {
			$items = Feeds_IA_Feeds_Manager::get_new_items_for_feed( $feed_config );
		} catch ( Exception $e ) {
			Feeds_IA_Logger::log(
				array(
					'feed_id'         => $feed_id,
					'title_original'  => '',
					'title_generated' => '',
					'status'          => 'error-feed',
					'message'         => 'Erro ao obter itens do feed: ' . $e->getMessage(),
					'post_id'         => null,
				)
			);
			return;
		}

		if ( empty( $items ) || ! is_array( $items ) ) {
			// Log informativo quando não há itens novos.
			Feeds_IA_Logger::log(
				array(
					'feed_id'         => $feed_id,
					'title_original'  => '',
					'title_generated' => '',
					'status'          => 'no-items',
					'message'         => 'Nenhum item novo encontrado para este feed.',
					'post_id'         => null,
				)
			);
			return;
		}

		$total_items     = count( $items );
		$created_count   = 0;
		$ai_error_count  = 0;
		$pub_error_count = 0;

		foreach ( $items as $item ) {
			// 1) Pré-processamento.
			$article         = Feeds_IA_Content_Processor::process_item( $item );
			$original_title  = isset( $article['title'] ) ? wp_strip_all_tags( $article['title'] ) : '';

			// 2) Reescrita com IA.
			$ai_result = Feeds_IA_AI::rewrite_article( $article );

			if ( is_wp_error( $ai_result ) ) {
				$ai_error_count++;

				Feeds_IA_Logger::log(
					array(
						'feed_id'         => $feed_id,
						'title_original'  => $original_title,
						'title_generated' => '',
						'status'          => 'error-ai',
						'message'         => $ai_result->get_error_message(),
						'post_id'         => null,
					)
				);

				continue;
			}

			// 3) Criação de rascunho.
			$post_id = Feeds_IA_Publisher::create_post( $feed_config, $article, $ai_result );

			if ( is_wp_error( $post_id ) ) {
				// create_post já registra log com status error-publish.
				$pub_error_count++;
				continue;
			}

			$created_count++;
		}

		// Log de resumo do processamento do feed.
		$summary_message = sprintf(
			'Processamento concluído para o feed "%1$s": %2$d itens novos, %3$d rascunhos criados, %4$d erros de IA, %5$d erros de publicação.',
			$feed_name ? $feed_name : $feed_id,
			$total_items,
			$created_count,
			$ai_error_count,
			$pub_error_count
		);

		Feeds_IA_Logger::log(
			array(
				'feed_id'         => $feed_id,
				'title_original'  => '',
				'title_generated' => '',
				'status'          => 'summary',
				'message'         => $summary_message,
				'post_id'         => null,
			)
		);
	}
}
