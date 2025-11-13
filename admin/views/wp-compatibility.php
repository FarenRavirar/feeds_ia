<?php
/**
 * Arquivo de compatibilidade para views administrativas.
 * Garante que as funções essenciais do WordPress estejam disponíveis.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Verifica se as funções essenciais do WordPress estão disponíveis
$required_functions = [
    'current_user_can',
    'wp_die',
    'check_admin_referer',
    'wp_unslash',
    'esc_html__',
    'esc_attr',
    'esc_textarea',
    'wp_nonce_field',
    'wp_kses',
    'is_wp_error'
];

foreach ( $required_functions as $function ) {
    if ( ! function_exists( $function ) ) {
        if ( function_exists( 'wp_die' ) ) {
            wp_die( sprintf( 'Função requerida do WordPress não encontrada: %s', $function ) );
        } else {
            exit( sprintf( 'Função requerida do WordPress não encontrada: %s', $function ) );
        }
    }
}