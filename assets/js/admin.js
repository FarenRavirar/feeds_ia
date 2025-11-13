/**
 * Comportamentos de interface para o painel do plugin Feeds IA.
 *
 * Focos:
 * - Feedback visual ao testar a conexão com a IA (Gemini).
 * - Feedback visual ao acionar "Processar agora" ou "Remover" em Feeds.
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
		setupFeedActionButtons();
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
	 * Configura os botões de ação por feed na tela de Feeds.
	 *
	 * Estrutura HTML atual (um único <form> envolvendo a tabela):
	 * - Hidden global: <input type="hidden" name="feeds_ia_action" value="save_feeds">
	 * - Hidden global: <input type="hidden" name="feed_id" id="feeds-ia-feed-id" value="">
	 * - Em cada linha:
	 *   <div class="feeds-ia-feed-actions">
	 *       <button type="submit"
	 *               class="button feeds-ia-btn-run-feed"
	 *               data-feeds-ia-action="run_feed_now"
	 *               data-feed-id="...">Processar agora</button>
	 *       <button type="submit"
	 *               class="button feeds-ia-btn-delete-feed"
	 *               data-feeds-ia-action="delete_feed"
	 *               data-feed-id="...">Remover</button>
	 *   </div>
	 *
	 * Comportamento:
	 * - Ao clicar em "Processar agora":
	 *   - Define feeds_ia_action = run_feed_now e feed_id = ID do feed.
	 *   - Desabilita o botão, muda o texto para "Processando..." e envia o form.
	 * - Ao clicar em "Remover":
	 *   - Pede confirmação.
	 *   - Define feeds_ia_action = delete_feed e feed_id = ID do feed.
	 *   - Desabilita o botão, muda o texto para "Removendo..." e envia o form.
	 */
	function setupFeedActionButtons() {
		var containers = document.querySelectorAll('.feeds-ia-feed-actions');
		if (!containers.length) {
			return;
		}

		containers.forEach(function (container) {
			var runBtn = container.querySelector('.feeds-ia-btn-run-feed');
			var deleteBtn = container.querySelector('.feeds-ia-btn-delete-feed');

			if (runBtn) {
				attachFeedActionHandler(runBtn, 'Processando...', false);
			}
			if (deleteBtn) {
				attachFeedActionHandler(deleteBtn, 'Removendo...', true);
			}
		});
	}

	/**
	 * Anexa o handler de ação (processar/remover) a um botão de feed.
	 *
	 * @param {HTMLButtonElement|HTMLInputElement} btn
	 * @param {string} loadingLabel Texto durante o processamento.
	 * @param {boolean} askConfirm  Se deve pedir confirmação (para remover).
	 */
	function attachFeedActionHandler(btn, loadingLabel, askConfirm) {
		btn.addEventListener('click', function (event) {
			if (btn.disabled) {
				return;
			}

			var action = btn.getAttribute('data-feeds-ia-action') || '';
			var feedId = btn.getAttribute('data-feed-id') || '';

			if (!action || !feedId) {
				return;
			}

			if (askConfirm) {
				var confirmed = window.confirm('Tem certeza de que deseja remover este feed?');
				if (!confirmed) {
					return;
				}
			}

			var form = btn.closest('form');
			if (!form) {
				return;
			}

			var actionInput = form.querySelector('input[name="feeds_ia_action"]');
			var feedIdInput = form.querySelector('input[name="feed_id"]');

			if (!actionInput || !feedIdInput) {
				return;
			}

			// Não deixa o submit padrão ocorrer antes de configurarmos os campos.
			event.preventDefault();

			actionInput.value = action;
			feedIdInput.value = feedId;

			if (!btn.dataset.originalText) {
				btn.dataset.originalText = btn.textContent || btn.value || '';
			}

			btn.disabled = true;
			btn.classList.add('feeds-ia-btn-loading');

			if (btn.tagName.toLowerCase() === 'button') {
				btn.textContent = loadingLabel;
			} else {
				btn.value = loadingLabel;
			}

			form.submit();
		});
	}
})();
