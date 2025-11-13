/**
 * Comportamentos de interface para o painel do plugin Feeds IA.
 *
 * Focos:
 * - Feedback visual ao testar a conexão com a IA (Gemini).
 * - Feedback visual ao acionar "Processar agora" para um feed.
 * - Organização mínima para futuras evoluções (AJAX, indicadores etc.).
 */

(function () {
	'use strict';

	/**
	 * Inicialização geral quando o DOM estiver pronto.
	 */
	document.addEventListener('DOMContentLoaded', function () {
		// Garante que estamos em uma tela do plugin.
		var root = document.querySelector('.feeds-ia-wrap');
		if (!root) {
			return;
		}

		setupTestAIButton();
		setupRunFeedNowButtons();
	});

	/**
	 * Configura o botão "Testar conexão com IA" na tela de IA & Prompt.
	 *
	 * Comportamento:
	 * - Ao clicar, desabilita o botão e altera o texto para "Testando...".
	 * - O formulário é enviado normalmente; ao recarregar a página,
	 *   o texto volta ao estado original.
	 */
	function setupTestAIButton() {
		var btn = document.getElementById('feeds-ia-test-ai');
		if (!btn) {
			return;
		}

		btn.addEventListener('click', function () {
			if (btn.disabled) {
				return;
			}

			// Guarda o texto original apenas uma vez.
			if (!btn.dataset.originalText) {
				btn.dataset.originalText = btn.textContent || btn.value || '';
			}

			btn.disabled = true;
			btn.classList.add('feeds-ia-btn-loading');

			if (btn.tagName.toLowerCase() === 'button') {
				btn.textContent = 'Testando...';
			} else {
				btn.value = 'Testando...';
			}

			// O submit continua acontecendo normalmente.
		});
	}

	/**
	 * Configura os botões "Processar agora" na tela de Feeds.
	 *
	 * Estrutura HTML atual:
	 * - Um <form> com hidden feeds_ia_action=run_feed_now,
	 *   contendo um <button> simples.
	 *
	 * Comportamento:
	 * - Ao enviar o form, desabilita o botão e troca o texto
	 *   para "Processando...", evitando múltiplos cliques.
	 */
	function setupRunFeedNowButtons() {
		var containers = document.querySelectorAll('.feeds-ia-feed-actions');
		if (!containers.length) {
			return;
		}

		containers.forEach(function (container) {
			var form = container.querySelector('form[action=""], form');
			if (!form) {
				return;
			}

			// Confere se este form é de "run_feed_now".
			var actionInput = form.querySelector('input[name="feeds_ia_action"][value="run_feed_now"]');
			if (!actionInput) {
				return;
			}

			var btn = form.querySelector('button[type="submit"]');
			if (!btn) {
				return;
			}

			form.addEventListener('submit', function () {
				if (btn.disabled) {
					return;
				}

				if (!btn.dataset.originalText) {
					btn.dataset.originalText = btn.textContent || btn.value || '';
				}

				btn.disabled = true;
				btn.classList.add('feeds-ia-btn-loading');

				if (btn.tagName.toLowerCase() === 'button') {
					btn.textContent = 'Processando...';
				} else {
					btn.value = 'Processando...';
				}
			});
		});
	}
})();
