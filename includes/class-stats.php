<?php
/**
 * Estatísticas do Feeds IA.
 *
 * Responsável por:
 * - Resumo geral (feeds, rascunhos, atividade recente).
 * - Execuções recentes (com base em logs).
 * - Rascunhos recentes criados pelo plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Feeds_IA_Stats {

	/**
	 * Retorna um resumo geral para o dashboard.
	 *
	 * Estrutura:
	 * [
	 *   'feeds_total'         => int,
	 *   'feeds_active'        => int,
	 *   'feeds_inactive'      => int,
	 *   'posts_total'         => int,  // posts com meta _feeds_ia_feed_id
	 *   'posts_last_30_days'  => int,
	 *   'last_run_at'         => int|null,  // timestamp da última execução bem-sucedida
	 *   'last_run_readable'   => string,    // data/hora formatada d/m/Y H:i
	 * ]
	 *
	 * Em caso de erro, devolve zeros e nulls seguros.
	 *
	 * @return array
	 */
	public static function get_summary() {
		$summary = array(
			'feeds_total'        => 0,
			'feeds_active'       => 0,
			'feeds_inactive'     => 0,
			'posts_total'        => 0,
			'posts_last_30_days' => 0,
			'last_run_at'        => null,
			'last_run_readable'  => '—',
		);

		// Feeds
		if ( class_exists( 'Feeds_IA_Settings' ) ) {
			$feeds = Feeds_IA_Settings::get_feeds();
			if ( is_array( $feeds ) ) {
				$summary['feeds_total'] = count( $feeds );

				$active   = 0;
				$inactive = 0;

				foreach ( $feeds as $feed ) {
					$status = isset( $feed['status'] ) ? $feed['status'] : 'inactive';
					if ( 'active' === $status ) {
						$active++;
					} else {
						$inactive++;
					}
				}

				$summary['feeds_active']   = $active;
				$summary['feeds_inactive'] = $inactive;
			}
		}

		// Posts criados pelo Feeds IA (qualquer status) – identifica pela meta _feeds_ia_feed_id.
		$posts_query = new WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => array( 'draft', 'pending', 'publish' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => '_feeds_ia_feed_id',
						'compare' => 'EXISTS',
					),
				),
				'no_found_rows'  => true,
			)
		);

		if ( ! is_wp_error( $posts_query ) ) {
			$post_ids = $posts_query->posts;
			$summary['posts_total'] = is_array( $post_ids ) ? count( $post_ids ) : 0;
		}

		// Posts criados nos últimos 30 dias.
		$thirty_days_ago = strtotime( '-30 days', current_time( 'timestamp' ) );

		$recent_posts_query = new WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => array( 'draft', 'pending', 'publish' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'date_query'     => array(
					array(
						'after'     => gmdate( 'Y-m-d H:i:s', $thirty_days_ago ),
						'inclusive' => true,
						'column'    => 'post_date_gmt',
					),
				),
				'meta_query'     => array(
					array(
						'key'     => '_feeds_ia_feed_id',
						'compare' => 'EXISTS',
					),
				),
				'no_found_rows'  => true,
			)
		);

		if ( ! is_wp_error( $recent_posts_query ) ) {
			$post_ids_last_30 = $recent_posts_query->posts;
			$summary['posts_last_30_days'] = is_array( $post_ids_last_30 ) ? count( $post_ids_last_30 ) : 0;
		}

		// Última execução bem-sucedida (com base em logs).
		if ( class_exists( 'Feeds_IA_Logger' ) ) {
			$logs = Feeds_IA_Logger::get_logs(
				array(
					'status' => 'success',
					'days'   => 90,  // janela de 90 dias para inspecionar
					'limit'  => 1,
				)
			);

			if ( ! empty( $logs ) && isset( $logs[0]['log_at'] ) ) {
				$last_ts                    = (int) $logs[0]['log_at'];
				$summary['last_run_at']     = $last_ts;
				$summary['last_run_readable'] = date_i18n( 'd/m/Y H:i', $last_ts );
			}
		}

		return $summary;
	}

	/**
	 * Retorna execuções recentes com base nos logs.
	 *
	 * Cada item segue a estrutura retornada pelo Feeds_IA_Logger::get_logs().
	 *
	 * @param int $limit Quantidade máxima de registros.
	 * @return array
	 */
	public static function get_recent_runs( $limit = 10 ) {
		if ( ! class_exists( 'Feeds_IA_Logger' ) ) {
			return array();
		}

		$logs = Feeds_IA_Logger::get_logs(
			array(
				'days'  => 30,
				'limit' => $limit,
			)
		);

		return is_array( $logs ) ? $logs : array();
	}

	/**
	 * Retorna rascunhos recentes criados pelo plugin.
	 *
	 * Estrutura simplificada:
	 * [
	 *   [
	 *     'ID'        => int,
	 *     'title'     => string,
	 *     'date'      => int (timestamp),
	 *     'status'    => string,
	 *     'edit_link' => string,
	 *   ],
	 *   ...
	 * ]
	 *
	 * @param int $limit Quantidade máxima.
	 * @return array
	 */
	public static function get_recent_posts( $limit = 10 ) {
		$query = new WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => array( 'draft', 'pending', 'publish' ),
				'posts_per_page' => $limit,
				'meta_query'     => array(
					array(
						'key'     => '_feeds_ia_feed_id',
						'compare' => 'EXISTS',
					),
				),
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		if ( is_wp_error( $query ) || empty( $query->posts ) ) {
			return array();
		}

		$result = array();

		foreach ( $query->posts as $post ) {
			$post_id   = $post->ID;
			$post_date = get_post_time( 'U', true, $post_id ); // timestamp (fuso WP)

			$result[] = array(
				'ID'        => $post_id,
				'title'     => get_the_title( $post_id ),
				'date'      => $post_date,
				'status'    => get_post_status( $post_id ),
				'edit_link' => get_edit_post_link( $post_id ),
			);
		}

		return $result;
	}
}