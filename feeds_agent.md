# feeds_agent.md  
Guia técnico do projeto **feeds_ia** – Plugin de feeds com IA para WordPress (Artifício RPG)

---

## 1. Contexto e objetivo

O projeto **feeds_ia** define um plugin para WordPress cujo objetivo é:

- Ler **feeds RSS** de sites relacionados a **RPG de mesa** (notícias, anúncios de suplementos, cenários, financiamentos coletivos, Unearthed Arcana, playtests, eventos etc.).
- Processar o conteúdo usando **IA (Google Gemini)** para gerar versões reescritas:
  - em **português do Brasil**,
  - em **terceira pessoa**,
  - com **vocabulário típico de RPG de mesa**,
  - com foco informativo (formato notícia / nota), não análise longa.
- Criar **posts em rascunho** no WordPress, com:
  - título reescrito,
  - texto reestruturado,
  - metadados de rastreio da fonte original,
  - possibilidade de imagem destacada derivada do feed.

O plugin **nunca publica automaticamente**. Toda saída é salva como **rascunho** para revisão editorial no Artifício RPG.

Este documento é o **contrato de referência** para:

- Desenvolvedores humanos que mantêm o plugin.
- Agentes de IA responsáveis por implementar, refatorar, depurar ou estender o **feeds_ia**.

Qualquer alteração relevante de comportamento deve ser refletida aqui.

---

## 2. Princípios editoriais (invariantes obrigatórios)

Estas regras são estruturais e não podem ser violadas por código, por prompts nem por refatorações.

### 2.1. Sem invenção de fatos

- Não criar fatos novos.
- Não alterar:
  - datas,
  - valores numéricos,
  - nomes de pessoas,
  - nomes de sistemas de RPG,
  - nomes de suplementos, cenários, campanhas,
  - nomes de editoras, autores, marcas ou linhas de produto.
- Não inventar rumores, bastidores, opiniões ou leituras “entre linhas”.

Se o texto original for vago, a saída deve permanecer vaga na mesma medida, sem “preencher lacunas”.

### 2.2. Reescrita controlada (paráfrase estruturada)

- A tarefa da IA é **paráfrase** e **organização**, não criação livre.
- Obrigatório:
  - manter o conteúdo factual idêntico,
  - deixar o texto mais claro quando possível,
  - reorganizar parágrafos quando isso ajuda a leitura.
- Não permitido:
  - extrapolar contexto,
  - adicionar “explicações” que não estejam na fonte,
  - transformar nota simples em artigo opinativo.

### 2.3. Idioma sempre em português do Brasil

- Toda saída da IA deve ser **PT-BR**.
- Quando o texto original estiver em inglês ou outro idioma:
  - o resultado é uma **paráfrase em português**, fiel ao conteúdo,
  - não é uma tradução livre, nem adaptação criativa.
- Evitar misturar inglês quando houver termo consolidado em português:
  - “livro básico” (não apenas “core book”),
  - “suplemento”, “cenário”, “mesa”, “campanha”, “financiamento coletivo”, “RPG de mesa”.

### 2.4. Vocabulário de RPG de mesa

- Preferir termos próprios do campo de RPG de mesa:
  - “sistema”, “jogo”, “cenário”, “suplemento”, “livro básico”, “classe”, “subclasse”, “one-shot”, “campanha”, “sessão”, “playtest”, “Unearthed Arcana”, “ambientação” etc.
- Evitar diluir o contexto em termos genéricos (“produto de entretenimento” em vez de “suplemento de RPG”).

### 2.5. Crédito à fonte

- Cada post deve:
  - guardar a URL original em metadados,
  - exibir um parágrafo final de fonte, por exemplo:

    ```html
    <p><em>Fonte original: <a href="URL_ORIGINAL">URL_ORIGINAL</a></em></p>
    ```

- O texto não deve ocultar a origem nem se apropriar indevidamente do conteúdo.

### 2.6. Modo de publicação (sempre rascunho)

- **Regra fixa:** todo post criado pelo plugin é salvo como **rascunho**:
  - `post_status = 'draft'`.
- Mesmo que exista, na tela de feeds, um campo “Modo de publicação”:
  - o valor `publish` deve ser **ignorado** pela lógica interna,
  - a decisão editorial final é sempre humana, via painel do WordPress.

### 2.7. Estilo de texto

- Narração em terceira pessoa.
- Tom informativo e objetivo, adequado a notas/notícias.
- Sem emoção explícita e sem opinião não presente na fonte.

---

## 3. Escopo funcional atual

### 3.1. O que o plugin faz

- Cadastro de múltiplos **feeds RSS** com:
  - nome interno,
  - URL RSS,
  - categoria de destino,
  - status (ativo/inativo),
  - frequência em minutos,
  - limite de itens por execução.
- Leitura periódica de feeds via `wp_cron`.
- Detecção de itens novos por GUID/link/combinação.
- Pré-processamento de conteúdo:
  - limpeza de HTML,
  - extração de texto,
  - extração de imagem principal.
- Integração com **Google Gemini**:
  - envio do conteúdo bruto + instruções rígidas,
  - retorno no formato JSON (`title`, `content`, `summary`).
- Criação de posts:
  - tipo `post`,
  - status **sempre** `draft`,
  - conteúdo em HTML com bloco final de fonte,
  - metadados de rastreio da origem.
- Registro de **logs** em opção dedicada (`feeds_ia_logs`) com limite de histórico.
- Dashboard administrativo com:
  - contagem de feeds ativos/inativos,
  - contagem de rascunhos criados,
  - execuções recentes do cron,
  - rascunhos recentes criados pelo plugin.
- Tela de **agendamentos** com:
  - associação de feeds a horários fixos (HH:MM, 24h),
  - seleção de dias da semana em português,
  - ativação/desativação de cada agendamento.
- Tela de **logs** com filtros por feed, status e período.
- Tela de **configuração de IA** com:
  - API key,
  - modelo,
  - temperatura,
  - prompt base,
  - botão “Testar conexão com IA”.
- Tela de **configuração de feeds** com:
  - cadastro/edição/remoção,
  - botão “Processar agora” por feed.
- Desinstalação (`uninstall.php`) removendo:
  - options `feeds_ia_*`,
  - metadados `_feeds_ia_*` anexados a posts.

### 3.2. Fora de escopo atual

- Publicação automática (`post_status = 'publish'`).
- SEO avançado (ações diretas em plugins de SEO).
- Integração com AdSense ou com redes de anúncios.

---

## 4. Estrutura de diretórios

```text
feeds_ia/
├── feeds_ia.php                        # Arquivo principal do plugin
├── includes/
│   ├── class-loader.php                # Registro/autoload de classes do plugin
│   ├── class-admin-menu.php            # Menus e submenus no painel WP
│   ├── class-settings.php              # Configurações (feeds, IA, gerais)
│   ├── class-feeds-manager.php         # Leitura e normalização de RSS
│   ├── class-content-processor.php     # Limpeza e preparação do conteúdo
│   ├── class-ai-interface.php          # Interface e fachada para provedores de IA
│   ├── class-ai-gemini.php             # Provedor de IA (Google Gemini)
│   ├── class-publisher.php             # Criação de posts e metadados
│   ├── class-cron.php                  # Agendamentos via wp_cron
│   ├── class-logger.php                # Logs e consulta de logs
│   ├── class-stats.php                 # Estatísticas para dashboard
│   └── class-schedules.php             # Agendamentos por horário/dia
├── admin/
│   ├── views/
│   │   ├── dashboard.php               # Painel de resumo (cards, execuções, rascunhos)
│   │   ├── settings-feeds.php          # Tela de feeds (cadastro, processar agora)
│   │   ├── settings-ai.php             # Tela de IA (config + teste de conexão)
│   │   ├── schedules.php               # Tela de agendamentos (horário, dias, status)
│   │   └── logs.php                    # Tela de logs (filtros, listagem)
│   └── index.php                       # Placeholder (segurança)
├── assets/
│   ├── css/
│   │   └── admin.css                   # Estilos do painel (cards, tabelas, filtros)
│   └── js/
│       └── admin.js                    # Scripts do painel (feedback de botões)
└── uninstall.php                       # Limpeza de options e metadados na remoção
````

---

## 5. Componentes principais e comportamento

### 5.1. Núcleo do plugin – `feeds_ia.php` + `class-loader.php`

**feeds_ia.php**

* Declara o cabeçalho do plugin.
* Define constantes:

  * `FEEDS_IA_VERSION`
  * `FEEDS_IA_PLUGIN_DIR`
  * `FEEDS_IA_PLUGIN_URL`
* Inclui `includes/class-loader.php`.
* Registra hooks:

  * `register_activation_hook()` → registra cron e inicializa estruturas necessárias.
  * `register_deactivation_hook()` → limpa cron, sem apagar dados.
* Inicializa o plugin em `plugins_loaded`, delegando para `Feeds_IA_Loader`.

**class-loader.php**

* Responsável por carregar as classes do plugin.
* Garante que classes como:

  * `Feeds_IA_Admin_Menu`,
  * `Feeds_IA_Settings`,
  * `Feeds_IA_Cron`,
  * `Feeds_IA_Logger`,
  * `Feeds_IA_Stats`,
  * `Feeds_IA_Schedules`
    sejam carregadas antes de uso.
* Pode implementar autoloader por convenção (por exemplo, `Feeds_IA_Algo` → `includes/class-algo.php`).

---

### 5.2. Administração – `class-admin-menu.php` + views

**Menus e submenus**

* Cria menu principal “Feeds IA” no painel.
* Submenus esperados:

  * **Dashboard** → `admin/views/dashboard.php`
  * **Feeds** → `admin/views/settings-feeds.php`
  * **IA & Prompt** → `admin/views/settings-ai.php`
  * **Agendamentos** → `admin/views/schedules.php`
  * **Logs** → `admin/views/logs.php`

**Assets de administração**

* Em `admin_enqueue_scripts`, enfileirar:

  * `assets/css/admin.css`
  * `assets/js/admin.js`
* Carregar apenas em telas do plugin (por id da tela ou `$_GET['page']`).

---

### 5.3. Configurações – `class-settings.php`

**Options principais**

* `feeds_ia_feeds`
  Estrutura: lista de feeds cadastrados.

  Cada feed contém:

  ```php
  [
    'id'            => string,  // ID interno estável
    'name'          => string,  // Nome interno, para exibição
    'url'           => string,  // URL RSS
    'category'      => int,     // ID da categoria WP
    'status'        => 'active'|'inactive',
    'frequency'     => int,     // minutos entre execuções (caso cron por frequência esteja ativo)
    'items_per_run' => int,     // limite de itens por execução
    'mode'          => string,  // 'draft'|'publish' (mas lógica interna força 'draft')
    'last_run'      => int|null // timestamp da última execução
  ]
  ```

* `feeds_ia_ai_settings`
  Estrutura mínima:

  ```php
  [
    'api_key'     => string,
    'model'       => string, // ex.: 'gemini-1.5-flash'
    'temperature' => float,  // ex.: 0.2
    'base_prompt' => string  // ajustes finos de tom (sem quebrar invariantes)
  ]
  ```

* `feeds_ia_general`
  Reservado para configurações gerais. Possível uso futuro:

  * `default_author_id` (autor padrão para os rascunhos),
  * flags de comportamento global.

**Responsabilidades**

* Ler/gravar essas options.
* Garantir valores padrão seguros.
* Expor métodos utilitários:

  * `get_feeds()`, `save_feeds( $feeds )`,
  * `get_ai_settings()`, `save_ai_settings( $settings )`,
  * `get_general_settings()`.

---

### 5.4. Leitura de feeds – `class-feeds-manager.php`

**Funções principais**

* Receber um `feed_config` (entrada da `feeds_ia_feeds`).

* Usar `fetch_feed()` (SimplePie) para carregar o RSS.

* Normalizar itens em estrutura interna:

  ```php
  [
    'feed_id'      => string,
    'title'        => string,
    'content_raw'  => string,       // HTML/texto original do feed
    'link'         => string,       // URL original da matéria
    'image_url'    => string|null,  // imagem principal quando identificável
    'tags'         => string[],     // tags/categorias do feed, se houver
    'published_at' => int|null,     // timestamp
    'guid'         => string        // GUID do item (ou hash)
  ]
  ```

* Deduplicação:

  * Verifica se já existe um post com:

    * `_feeds_ia_original_link = link`, ou
    * `_feeds_ia_original_guid = guid`.
  * Se existir, o item é ignorado.

---

### 5.5. Processamento de conteúdo – `class-content-processor.php`

**Entrada**

* Item normalizado da `Feeds_IA_Feeds_Manager`.

**Saída**

* Estrutura preparada para IA:

  ```php
  [
    'feed_id'      => string,
    'title'        => string,
    'content_text' => string,       // texto limpo, sem scripts/estilos
    'link'         => string,
    'image_url'    => string|null,
    'tags'         => string[],
    'published_at' => int|null,
    'guid'         => string
  ]
  ```

**Tarefas**

* Remover elementos indesejados (`<script>`, `<style>` etc.).
* Normalizar quebras de linha.
* Extrair primeira `<img>` útil como candidata a imagem destacada.
* Garantir que `content_text` esteja adequado para ser passado à IA (sem HTML desnecessário).

---

### 5.6. IA – `class-ai-interface.php` e `class-ai-gemini.php`

**Interface e fachada – `class-ai-interface.php`**

* Define `Feeds_IA_AI_Provider`:

  ```php
  interface Feeds_IA_AI_Provider {
      /**
       * @param array $article Estrutura com 'title', 'content_text', 'link', 'image_url' etc.
       * @param array $options Opções adicionais (modelo, temperatura etc.).
       * @return array ['title' => string, 'content' => string, 'summary' => string]
       */
      public function rewrite_article( array $article, array $options = array() );
  }
  ```

* Classe estática `Feeds_IA_AI`:

  * lê `feeds_ia_ai_settings`,
  * instancia `Feeds_IA_AI_Gemini`,
  * monta `options`,
  * repassa chamada.

**Implementação Gemini – `class-ai-gemini.php`**

* Lê:

  * API key,
  * modelo,
  * temperatura,
  * prompt base adicional.
* Constrói prompt final contendo:

  * descrição da tarefa (reescrever notícia de RPG de mesa),
  * invariantes editoriais (sem invenção, PT-BR, vocabulário RPG, terceira pessoa),
  * pedido explícito de resposta em **JSON** com campos:

    * `title` (título reescrito),
    * `content` (HTML),
    * `summary` (resumo curto para meta description).
* Faz requisição via `wp_remote_post()` ao endpoint do Google Gemini.
* Pós-processa a resposta:

  * remove blocos tipo `json` se vierem,
  * decodifica JSON,
  * valida presença de `title`, `content`, `summary`,
  * garante que `content` esteja em HTML (envolvendo parágrafos em `<p>` quando necessário).
* Método de teste:

  ```php
  public function test_connection()
  ```

  * Envia um prompt mínimo para verificar:

    * se a chave é válida,
    * se o modelo está acessível.
  * Retorna `true` em sucesso ou `WP_Error` em falha, para uso na tela “IA & Prompt”.

---

### 5.7. Publicação – `class-publisher.php`

**Entrada**

* Configuração de feed.
* Estrutura `$article` (pré-processada).
* Estrutura `$ai_result` (resultado da IA).

**Criação do post**

* Cria post com:

  ```php
  [
    'post_type'   => 'post',
    'post_status' => 'draft',              // INVARIANTE: sempre rascunho
    'post_title'  => $ai_result['title'],
    'post_content'=> $ai_result['content'] . $bloco_de_fonte,
    'post_author' => (int) $default_author_id_ou_author_atual,
    'post_category' => [ $feed_config['category'] ], // se configurado
  ]
  ```

* Adiciona bloco de fonte:

  ```html
  <p><em>Fonte original: <a href="LINK_ORIGINAL">LINK_ORIGINAL</a></em></p>
  ```

**Metadados**

* Grava em `_feeds_ia_*`:

  * `_feeds_ia_original_link`
  * `_feeds_ia_original_guid`
  * `_feeds_ia_feed_id`
  * `_feeds_ia_summary`
  * `_feeds_ia_model`
  * `_feeds_ia_hash` (hash de deduplicação interna)

**Imagem destacada (opcional)**

* Se `image_url` existir:

  * baixa a imagem,
  * cria attachment no WordPress,
  * vincula como `featured image` do post.
* Em caso de erro:

  * registra log com status `error-image`.

---

### 5.8. Cron – `class-cron.php`

**Agendamento base**

* Registra intervalo personalizado (ex.: `feeds_ia_15min`) para rodar a cada N minutos.
* Cria um evento `feeds_ia_cron` anexado a esse intervalo.

**Execução**

* `run_scheduled()`:

  * obtém lista de feeds ativos,
  * verifica, por feed, se:

    * está ativo, e
    * a diferença entre o horário atual (`current_time('timestamp')`) e `last_run` é maior que `frequency` (minutos).
  * para cada feed elegível:

    * chama `run_for_feed( $feed_config )`.

* `run_for_feed( $feed_config )`:

  * obtém itens novos via `Feeds_IA_Feeds_Manager`,
  * pré-processa com `Feeds_IA_Content_Processor`,
  * chama `Feeds_IA_AI` para reescrever,
  * cria posts via `Feeds_IA_Publisher`,
  * atualiza `last_run`,
  * registra logs com contagem de rascunhos criados e mensagens de erro/sucesso.

**Integração com agendamentos**

* `class-schedules.php` fornece agendamentos adicionais por horário/dia.
* A integração pode seguir uma de duas estratégias:

  * usar **apenas frequência** por feed, ou
  * combinar frequência + agendamentos (ex.: rodar feed se agendamento estiver “vencido” para aquele dia/horário).
* O cálculo de horário/dia deve usar **sempre** `current_time('timestamp')`, respeitando o fuso do WordPress (ex.: Brasília).

---

### 5.9. Logs – `class-logger.php` e `admin/views/logs.php`

**Armazenamento**

* Option `feeds_ia_logs` com lista de entradas, cada uma contendo:

  ```php
  [
    'log_at'         => int,     // timestamp
    'feed_id'        => string,
    'feed_name'      => string,
    'status'         => string,  // success, error-ai, error-feed, error-publish, error-image, test-ai-*
    'title_original' => string,
    'title_generated'=> string,
    'message'        => string,
    'post_id'        => int|null
  ]
  ```

* Política de retenção: manter no máximo N entradas (ex.: 500), descartando as mais antigas.

**Leitura**

* Métodos utilitários para filtragem por:

  * `feed_id`,
  * `status`,
  * intervalo de dias.

**Tela de logs**

* Filtros:

  * feed (select),
  * status (select com rótulos em português),
  * período (últimas 24h, 7 dias, 30 dias, 90 dias).
* Tabela:

  * Data e hora (formato `d/m/Y H:i`),
  * Feed,
  * Status,
  * Título original,
  * Título gerado,
  * Mensagem,
  * Link para “Editar rascunho” quando `post_id` existir.

---

### 5.10. Estatísticas – `class-stats.php` e `admin/views/dashboard.php`

**`class-stats.php`**

* Gera resumo com:

  * total de feeds,
  * feeds ativos/inativos,
  * total de rascunhos criados pelo plugin,
  * rascunhos criados nas últimas 24h,
  * rascunhos criados nos últimos 7 dias.
* Opcionalmente, fornece listas:

  * execuções recentes (`get_recent_runs()`),
  * rascunhos recentes (`get_recent_posts()`).

**`admin/views/dashboard.php`**

* Exibe:

  * cards com resumo de feeds e rascunhos,
  * tabela de execuções recentes (com data/hora `d/m/Y H:i`),
  * tabela de rascunhos recentes (título clicável levando ao editor de post).

---

### 5.11. Agendamentos – `class-schedules.php` e `admin/views/schedules.php`

**Armazenamento**

Option `feeds_ia_schedules`:

```php
[
  'id'           => string,     // ID do agendamento
  'feed_id'      => string,     // referência a um feed
  'time_of_day'  => 'HH:MM',    // horário em 24h (ex.: '08:00', '22:30')
  'days_of_week' => string[],   // ['mon', 'tue', ...] (interno)
  'status'       => 'active'|'inactive',
  'last_run'     => int|null    // timestamp da última execução desse agendamento
]
```

**Dia da semana**

* Internamente: códigos (`sun`, `mon`, `tue`, `wed`, `thu`, `fri`, `sat`).
* UI (administrativa): rótulos em português:

  * Domingo
  * Segunda-feira
  * Terça-feira
  * Quarta-feira
  * Quinta-feira
  * Sexta-feira
  * Sábado

**Tela de agendamentos**

* Para cada agendamento:

  * seleção de feed,
  * campo `time` (HTML5) em formato `HH:MM` (24h),
  * checkboxes para dias da semana,
  * status ativo/inativo,
  * exibição de `last_run` em `d/m/Y H:i`.
* Linha em branco para criação de novo agendamento.

**Horário e fuso**

* Todo cálculo de horário/dia deve usar:

  * `current_time( 'timestamp' )` para obter o momento atual,
  * `date_i18n()` ou `wp_date()` para extrair:

    * hora/minuto,
    * dia da semana, no fuso do WordPress (ex.: Brasília).

---

### 5.12. Frontend administrativo – `assets/js/admin.js` e `assets/css/admin.css`

**admin.js**

* Comportamentos:

  * Botão “Testar conexão com IA”:

    * ao clicar:

      * desabilita o botão,
      * muda texto para “Testando...”,
      * aplica classe `.feeds-ia-btn-loading`,
      * envia o form normalmente; o estado visual é restaurado após o reload.
  * Botões “Processar agora” por feed:

    * ao submeter o form daquela linha:

      * desabilita o botão,
      * troca texto para “Processando...”,
      * aplica `.feeds-ia-btn-loading`.

**admin.css**

* Layout:

  * `.feeds-ia-wrap`: largura máxima, alinhamento com painel WP.
  * `.feeds-ia-dashboard-metrics`: cards de resumo.
  * `.feeds-ia-card`: box com sombra leve, usado no dashboard.
* Tabelas:

  * espaçamento consistente,
  * quebra de linhas em títulos longos.
* Filtros:

  * `.feeds-ia-filters`: layout flex, responsivo.
* Botões em carregamento:

  * `.feeds-ia-btn-loading`: opacidade reduzida, cursor neutro.
  * spinner simples via `::after` com animação `@keyframes feeds-ia-spin`.

---

## 6. Prompt de IA e formato de saída

### 6.1. Requisitos de prompt

Qualquer construção de prompt usada pelo plugin deve:

* Informar que a tarefa é reescrever uma **notícia de RPG de mesa**.

* Especificar:

  * texto em **português do Brasil**,
  * narração em **terceira pessoa**,
  * vocabulário de RPG de mesa,
  * proibição absoluta de invenção/modificação de fatos,
  * preservação de datas, valores, nomes, títulos de suplementos, cenários, sistemas etc.

* Exigir saída em **JSON puro** com a seguinte estrutura:

  ```json
  {
    "title": "Título reescrito em português do Brasil",
    "content": "<p>Conteúdo reestruturado em HTML...</p>",
    "summary": "Resumo curto para meta description."
  }
  ```

### 6.2. Pós-processamento da resposta

* Remover envoltórios como blocos `json` se existirem.
* `json_decode` em modo associativo.
* Validar:

  * `title` não vazio,
  * `content` não vazio,
  * `summary` não vazio.
* Garantir que `content` esteja em HTML adequado para WordPress.

---

## 7. Datas, horários e Brasil

### 7.1. Fuso horário

* Assumir que o WordPress está configurado para o fuso de **Brasília** (`America/Sao_Paulo`).
* Cálculo de hora atual:

  * usar sempre `current_time( 'timestamp' )`.
* Jamais usar `time()` / `gmdate()` diretamente para lógica de cron ou agendamento.

### 7.2. Formato de exibição

* Exibir data/hora sempre em formato **24h**, por exemplo:

  * `07/11/2025 08:30`
  * `13/02/2026 21:05`
* Use:

  ```php
  date_i18n( 'd/m/Y H:i', $timestamp );
  ```

### 7.3. Dias da semana

* Interface administrativa deve usar nomes em português:

  * Domingo
  * Segunda-feira
  * Terça-feira
  * Quarta-feira
  * Quinta-feira
  * Sexta-feira
  * Sábado

---

## 8. Segurança, validação e performance

### 8.1. Segurança

* Todas as telas do plugin exigem:

  * `current_user_can( 'manage_options' )`.
* Formulários:

  * `wp_nonce_field()` e `wp_verify_nonce()` em todas as ações (salvar feeds, salvar IA, salvar agendamentos, processar feed, testar IA, apagar registros).
* Entrada:

  * URLs: `esc_url_raw`.
  * Textos curtos: `sanitize_text_field`.
  * Textos longos (prompt, mensagens): `sanitize_textarea_field`.
  * IDs e status: `sanitize_key` / cast para int quando necessário.
* Saída:

  * `esc_html`, `esc_attr`, `esc_url` nas views.

### 8.2. Performance

* Limitar itens por execução (`items_per_run` por feed).
* Definir timeouts razoáveis em chamadas à IA.
* Aproveitar cache do SimplePie para RSS.
* Limitar tamanho de `feeds_ia_logs` (ex.: 500 entradas).

---

## 9. Guia operacional para agentes de IA

### 9.1. Ordem de leitura antes de modificar

1. Ler este `feeds_agent.md` inteiro.
2. Localizar o arquivo relevante:

   * comportamento de leitura? → `class-feeds-manager.php`;
   * comportamento da IA? → `class-ai-interface.php` + `class-ai-gemini.php`;
   * publicação e meta? → `class-publisher.php`;
   * cron/agendamentos? → `class-cron.php` + `class-schedules.php`;
   * telas? → `admin/views/*.php`;
   * logs/estatísticas? → `class-logger.php`, `class-stats.php`.
3. Verificar se a mudança desejada altera algum **invariante**:

   * se sim, não aplicar; sugerir alteração do contrato e atualizar este documento explicitamente.

### 9.2. Regras ao escrever ou refatorar código

* Manter o prefixo:

  * Classes: `Feeds_IA_...`
  * Funções auxiliares: `feeds_ia_...`
* Seguir padrões WordPress:

  * indentação com tab,
  * uso de hooks (`add_action`, `add_filter`) onde apropriado.
* Documentar métodos com PHPDoc mínimo:

  * parâmetros,
  * tipo de retorno.

### 9.3. Ao adicionar nova funcionalidade

* Verificar se há impacto em:

  * fluxo de IA (prompts),
  * invariantes editoriais,
  * escopo de logs e dashboard.
* Atualizar este `feeds_agent.md`:

  * descrever nova opção/fluxo,
  * indicar se é “implementado” ou “planejado”.

### 9.4. Ao corrigir bugs

* Se envolver:

  * status do post,
  * idioma do texto,
  * vocabulário/editorial,
  * datas/horários,
* Validar que as mudanças reforçam as regras descritas nas seções 2, 6 e 7, jamais as flexibilizando.

---

## 10. Checklist de validação

Antes de considerar o plugin estável após qualquer alteração:

* [ ] Todos os posts criados pelo plugin estão com `post_status = 'draft'`.
* [ ] Títulos e conteúdos gerados pela IA estão em **português do Brasil**, com vocabulário de RPG de mesa.
* [ ] Nenhuma data, número, nome de pessoa/autor/editora/sistema/suplemento foi modificado em relação à fonte original.
* [ ] Todas as telas do plugin exibem datas/horas em formato `d/m/Y H:i`, respeitando o fuso de Brasília (ou o fuso configurado no WordPress).
* [ ] Labels de dias da semana aparecem em português nas telas de agendamento.
* [ ] Feeds podem ser cadastrados, editados, removidos e processados (“Processar agora”) sem erros.
* [ ] O cron executa sem warnings ou fatals e cria apenas rascunhos.
* [ ] A tela de logs exibe entradas recentes com informações completas e coerentes.
* [ ] O botão “Testar conexão com IA” funciona e sinaliza erro ou sucesso de forma clara.
* [ ] A desinstalação remove options `feeds_ia_*` e metadados `_feeds_ia_*` associados a posts.

---

## 11. Extensões futuras mapeadas (opcionais)

Estas ideias não estão obrigatoriamente implementadas, mas são direções coerentes com o contrato atual:

* **Integração explícita com plugins de SEO**
  Gravar `summary` em campos específicos de Yoast/RankMath, respeitando APIs públicas desses plugins.

* **Filtros avançados por feed**
  Regras específicas por origem (ex.: ignorar posts de certa categoria do feed, limitar por palavra-chave).

* **Fila assíncrona de IA**
  Em ambientes de alto volume, usar fila separada para chamadas de IA, reduzindo o peso do cron.

Qualquer uma dessas extensões deve preservar todos os invariantes editoriais descritos nas seções 2 e 6.