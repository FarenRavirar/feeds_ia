
# feeds_agent.md  
Guia técnico do projeto **feeds_ia** – Plugin de feeds com IA para WordPress (Artifício RPG)

---

## 1. Contexto e objetivo

O projeto **feeds_ia** implementa um plugin para WordPress voltado a **RPG de mesa**, com foco em:

- Ler **feeds RSS** de sites de RPG de mesa (notícias, anúncios de suplementos, cenários, financiamentos coletivos, Unearthed Arcana, playtests, eventos etc.).
- Processar o conteúdo com **IA (Google Gemini)** para gerar versões reescritas:
  - em **português do Brasil**,
  - em **terceira pessoa**,
  - com **vocabulário típico de RPG de mesa**.
- Criar **posts em rascunho** no WordPress contendo:
  - título reescrito;
  - texto reestruturado, preservando o volume de informação do original;
  - resumo curto para SEO (meta description);
  - metadados que ligam o rascunho à fonte original;
  - tentativa de definição de imagem destacada, quando o feed fornece imagem.

O plugin **nunca publica automaticamente**. Toda saída é salva como **rascunho** para revisão editorial manual no Artifício RPG.

Este documento é o **contrato de referência** para:

- desenvolvedores humanos que mantêm o plugin;
- agentes de IA que implementam, refatoram, depuram ou estendem o **feeds_ia**.

Qualquer alteração relevante de comportamento deve ser refletida aqui.

---

## 2. Princípios editoriais (invariantes obrigatórios)

As regras abaixo são estruturais e não podem ser violadas por código, prompts ou refatorações.

### 2.1. Sem invenção de fatos

- Não criar fatos novos.
- Não alterar:
  - datas;
  - valores numéricos;
  - nomes de pessoas;
  - nomes de sistemas de RPG;
  - nomes de suplementos, cenários, campanhas;
  - nomes de editoras, autores, marcas ou linhas de produto.
- Não inventar rumores, bastidores, opiniões ou leituras “entre linhas”.

Se o texto original for vago, a saída deve permanecer vaga na mesma medida, sem “preencher lacunas”.

### 2.2. Reescrita controlada (paráfrase estruturada)

- A tarefa da IA é **paráfrase** e **organização**, não criação livre.
- Obrigatório:
  - manter o conteúdo factual idêntico;
  - preservar o **nível de detalhe** do texto original;
  - reorganizar parágrafos apenas quando isso ajudar a leitura;
  - reescrever em português com clareza.
- Não permitido:
  - transformar uma matéria com vários parágrafos em um texto muito curto;
  - resumir a notícia a um “tweet” ou nota seca, se o original traz mais informação;
  - extrapolar contexto ou adicionar explicações que não constam na fonte.

Regra prática para o modelo:  
> manter um número aproximado de parágrafos e de informações equivalente ao texto original; se o original tiver 5–6 parágrafos, a versão reescrita também deve ter múltiplos parágrafos com o mesmo conteúdo factual.

### 2.3. Idioma sempre em português do Brasil

- Toda saída da IA deve ser **português do Brasil**.
- Quando o texto original estiver em outro idioma:
  - o resultado é uma **paráfrase em português**, fiel ao conteúdo;
  - não é tradução livre nem adaptação criativa.
- Evitar misturar inglês quando existir termo consolidado em português:
  - “livro básico”, “suplemento”, “cenário”, “campanha”, “RPG de mesa”, “financiamento coletivo”.

### 2.4. Vocabulário de RPG de mesa

- Preferir termos próprios de RPG de mesa:
  - “sistema”, “jogo”, “cenário”, “suplemento”, “livro básico”, “classe”, “subclasse”, “one-shot”, “campanha”, “sessão”, “playtest”, “Unearthed Arcana”, “ambientação” etc.
- Evitar termos genéricos que apagam o contexto (“produto de entretenimento”, “jogo de fantasia” sem especificar que é RPG, quando a fonte for clara).

### 2.5. Crédito à fonte

- Cada rascunho criado deve:
  - guardar a URL original em metadados;
  - exibir um parágrafo final de fonte, por exemplo:

    ```html
    <p><em>Fonte original: <a href="URL_ORIGINAL">URL_ORIGINAL</a></em></p>
    ```

- O texto não pode ocultar a origem nem se apropriar do conteúdo como se fosse inédito.

### 2.6. Modo de publicação (sempre rascunho)

- **Regra fixa:** todo post criado pelo plugin é salvo como **rascunho**:

  php
  'post_status' => 'draft';


* Mesmo que exista um campo “Modo de publicação” na tela de feeds:

  * o valor `publish` deve ser ignorado pela lógica interna;
  * a decisão final de publicar é sempre humana, via painel do WordPress.

### 2.7. Estilo de texto

* Narração em terceira pessoa.
* Tom informativo e objetivo, adequado a notas/notícias.
* Sem emoção explícita e sem opinião não presente na fonte.

### 2.8. Invariantes de SEO e metadados**

 * Todo rascunho gerado deve conter um **resumo curto em português do Brasil**, adequado para uso como metadescrição.
 * O plugin deve armazenar esse resumo em `_feeds_ia_summary` e, quando o Yoast SEO estiver ativo, no meta `_yoast_wpseo_metadesc` do post. ([Stack Overflow][2])
 * O plugin deve gravar uma **frase-chave de foco** derivada do próprio título (por exemplo, nome do sistema, suplemento ou produto citado), sem criar termos que não apareçam no texto. Essa frase-chave deve ser salva em `_yoast_wpseo_focuskw`. ([Stack Overflow][2])
 * O plugin deve gerar **slug** e, quando aplicável, **título SEO** dentro de faixas recomendadas:   * slug encurtado, preservando o núcleo factual (sistema, produto, ação “chega ao Brasil” etc.);   * título SEO com comprimento aproximado de 50–60 caracteres, sem cortar de maneira que altere o sentido da notícia. ([Stack Overflow][3])
 * Nenhum desses metadados pode contradizer o conteúdo factual do corpo do texto.
---

## 3. Escopo funcional

### 3.1. O que o plugin faz hoje

* Cadastro de múltiplos **feeds RSS** com:

  * nome interno;
  * URL RSS;
  * categoria de destino;
  * status (ativo/inativo);
  * frequência em minutos;
  * limite de itens por execução.
* Leitura periódica de feeds via `wp_cron`.
* Detecção de itens novos por GUID/link/combinação.
* Pré-processamento de conteúdo:

  * limpeza de HTML;
  * extração de texto;
  * extração de imagem principal quando disponível.
* Integração com **Google Gemini**:

  * envio do conteúdo bruto + instruções editoriais rígidas;
  * retorno em JSON (`title`, `content`, `summary`).
* Criação de posts:

  * tipo `post`;
  * status sempre `draft`;
  * conteúdo em HTML com parágrafo de fonte;
  * metadados `_feeds_ia_*` com origem da notícia.
* Registro de **logs** em option dedicada (`feeds_ia_logs`).
* Dashboard administrativo com resumo (feeds, execuções, rascunhos).
* Tela de agendamentos por horário/dia.
* Tela de IA & Prompt com botão de teste de conexão.
* Tela de feeds com botão “Processar agora”.

### 3.2. O que ainda não está implementado ou está parcial

* Integração explícita com **Yoast SEO** (ou plugin equivalente) para:

  * popular **meta description** com o `summary` gerado;
  * sugerir/atribuir **frase-chave de foco** com base no título;
  * ajustar campo **SEO title** dentro de limites de tamanho recomendados sem perder precisão factual;
  * respeitar tamanho recomendável de **slug** (encurtar slug muito longo, sem cortar de forma enganosa).
* Tratamento mais robusto de **imagem destacada**:

  * suportar feeds que usam `<media:content>`, `<enclosure>` ou outras estruturas além de `<img>`;
  * logar explicitamente quando não for possível obter imagem.

### 3.3. Fora de escopo neste momento

* Publicação automática (`post_status = 'publish'`).
* Criação de artigos longos de análise (modelo “5 capítulos”) – o plugin é voltado a notas/notícias.
* Monetização (AdSense, afiliados).

---

## 4. Estrutura de diretórios

```text
feeds_ia/
├── feeds_ia.php                        # arquivo principal do plugin
├── includes/
│   ├── class-loader.php                # registro/autoload de classes
│   ├── class-admin-menu.php            # menus e submenus no painel
│   ├── class-settings.php              # opções (feeds, IA, gerais)
│   ├── class-feeds-manager.php         # leitura e normalização de RSS
│   ├── class-content-processor.php     # limpeza e preparação de conteúdo
│   ├── class-ai-interface.php          # interface/fachada para provedores de IA
│   ├── class-ai-gemini.php             # integração com Google Gemini
│   ├── class-publisher.php             # criação de posts e metadados
│   ├── class-cron.php                  # rotinas de wp_cron
│   ├── class-logger.php                # logs
│   ├── class-stats.php                 # estatísticas para dashboard
│   └── class-schedules.php             # agendamentos por horário/dia
├── admin/
│   ├── views/
│   │   ├── dashboard.php               # painel de resumo
│   │   ├── settings-feeds.php          # tela de feeds
│   │   ├── settings-ai.php             # tela de IA & prompt
│   │   ├── schedules.php               # tela de agendamentos
│   │   └── logs.php                    # tela de logs
│   └── index.php                       # placeholder
├── assets/
│   ├── css/
│   │   └── admin.css                   # estilos do painel
│   └── js/
│       └── admin.js                    # scripts do painel
└── uninstall.php                       # remoção de options/metadados
```

---

## 5. Componentes principais e comportamento

### 5.1. Núcleo – `feeds_ia.php` e `class-loader.php`

* `feeds_ia.php`:

  * declara o plugin;
  * define constantes;
  * inclui `class-loader.php`;
  * registra hooks de ativação/desativação;
  * chama `Feeds_IA_Loader` em `plugins_loaded`.

* `class-loader.php`:

  * carrega as classes do plugin por convenção;
  * garante que classes como `Feeds_IA_Admin_Menu`, `Feeds_IA_Cron`, `Feeds_IA_Stats`, `Feeds_IA_Schedules` estejam disponíveis.

### 5.2. Administração – `class-admin-menu.php` + views

Submenus em “Feeds IA”:

1. **Dashboard** → `dashboard.php`
   Resumo de feeds, execuções e rascunhos recentes.

2. **Feeds** → `settings-feeds.php`
   Cadastro de feeds, configuração de frequência, itens por execução, botão “Processar agora”.

3. **IA & Prompt** → `settings-ai.php`
   Configuração de API key, modelo, temperatura e prompt base adicional. Botão “Testar conexão com IA”.

4. **Agendamentos** → `schedules.php`
   Associa feeds a horários e dias da semana (domingo a sábado, rótulos em português, formato 24h).

5. **Logs** → `logs.php`
   Filtros + tabela de logs.

**Assets administrativos**

* `admin.css` e `admin.js` são enfileirados somente nas telas do plugin, via `admin_enqueue_scripts`.

### 5.3. Configurações – `class-settings.php`

Options:

* `feeds_ia_feeds` – lista de feeds.

  Estrutura de cada feed:

  ```php
  [
    'id'            => string,
    'name'          => string,
    'url'           => string,
    'category'      => int,
    'status'        => 'active'|'inactive',
    'frequency'     => int,      // minutos
    'items_per_run' => int,
    'mode'          => 'draft'|'publish', // mas a publicação sempre força 'draft'
    'last_run'      => int|null,
  ]
  ```

* `feeds_ia_ai_settings` – configurações de IA:

  ```php
  [
    'api_key'     => string,
    'model'       => string,
    'temperature' => float,
    'base_prompt' => string,
  ]
  ```

* `feeds_ia_general` – configurações gerais (por exemplo, `default_author_id`).

### 5.4. Leitura de feeds – `class-feeds-manager.php`

* Usa `fetch_feed()` (SimplePie) para carregar RSS.

* Normaliza itens:

  ```php
  [
    'feed_id'      => string,
    'title'        => string,
    'content_raw'  => string,
    'link'         => string,
    'image_url'    => string|null,
    'tags'         => string[],
    'published_at' => int|null,
    'guid'         => string,
  ]
  ```

* Deduplicação por `_feeds_ia_original_link` / `_feeds_ia_original_guid`.

### 5.5. Processamento de conteúdo – `class-content-processor.php`

* Recebe item normalizado, limpa HTML, remove scripts/estilos.
* Extrai primeira `<img>` ou dados de imagem relevantes.
* Gera estrutura para IA:

  ```php
  [
    'feed_id'      => string,
    'title'        => string,
    'content_text' => string,
    'link'         => string,
    'image_url'    => string|null,
    'tags'         => string[],
    'published_at' => int|null,
    'guid'         => string,
  ]
  ```

### 5.6. IA – `class-ai-interface.php` e `class-ai-gemini.php`

* `Feeds_IA_AI_Provider` define a interface `rewrite_article( array $article, array $options = [] )`.
* `Feeds_IA_AI` lê configurações de IA e instancia `Feeds_IA_AI_Gemini`.

**`class-ai-gemini.php`**

* Monta prompt com:

  * descrição da tarefa (notícia de RPG de mesa);
  * regras editoriais (PT-BR, vocabulário RPG, sem invenção de fatos, preservar volume de informação);
  * instruções de formato de saída em JSON com `title`, `content`, `summary`.

* Envia request para `.../models/{model}:generateContent?key=API_KEY`.

* Pós-processa resposta:

  * extrai texto (`extract_text_from_gemini_response`);
  * remove fences ```json;
  * faz `json_decode`.

* Converte texto simples em `<p>` se não houver HTML.

* Expõe `test_connection()` para teste rápido de chave/modelo.

### 5.7. Publicação – `class-publisher.php`

* Cria o post com:

  ```php
  [
    'post_type'    => 'post',
    'post_status'  => 'draft',      // invariável
    'post_title'   => $ai_result['title'],
    'post_content' => $ai_result['content'] . $bloco_de_fonte,
    'post_author'  => $autor_definido,
    'post_category'=> [ $feed_config['category'] ],
  ]
  ```

* Grava metadados `_feeds_ia_*` (link, guid, feed_id, summary, modelo, hash).

* Tenta baixar e associar imagem destacada com base em `image_url`.

* Futuro: integração opcional com Yoast/SEO (ver seção 9.2).

### 5.8. Cron – `class-cron.php`

* Registra intervalo personalizado (ex.: `feeds_ia_15min`).
* Evento: `feeds_ia_cron`.
* `run_scheduled()`:

  * obtém feeds ativos;
  * verifica se `current_time('timestamp') - last_run >= frequency` (em minutos);
  * chama `run_for_feed()` para cada feed elegível.
* `run_for_feed()` executa pipeline completo (ler RSS → IA → rascunho) e registra logs.

### 5.9. Logs – `class-logger.php` + `admin/views/logs.php`

* Option `feeds_ia_logs` com entradas:

  ```php
  [
    'log_at'         => int,
    'feed_id'        => string,
    'feed_name'      => string,
    'status'         => string,
    'title_original' => string,
    'title_generated'=> string,
    'message'        => string,
    'post_id'        => int|null,
  ]
  ```

* Limite de histórico (ex.: 500 linhas).

* Tela de logs com filtros de feed, status e período; datas exibidas como `d/m/Y H:i`.

### 5.10. Estatísticas – `class-stats.php` + `dashboard.php`

* Calcula:

  * total de feeds;
  * feeds ativos/inativos;
  * rascunhos criados no total;
  * rascunhos criados nos últimos 30 dias.
* Dashboard exibe cards com esses números e listas de execuções/posts recentes.

### 5.11. Agendamentos – `class-schedules.php` + `schedules.php`

* Option `feeds_ia_schedules`:

  ```php
  [
    'id'           => string,
    'feed_id'      => string,
    'time_of_day'  => 'HH:MM',    // 24h
    'days_of_week' => string[],   // ['mon','tue',...]
    'status'       => 'active'|'inactive',
    'last_run'     => int|null,
  ]
  ```

* UI:

  * rótulos de dias em português (Domingo–Sábado);
  * horários em formato 24h;
  * exibição de `last_run` em `d/m/Y H:i`.

* Lógica de `is_due()` deve usar `current_time('timestamp')` e `date_i18n()`/`wp_date()` para respeitar o fuso configurado (Brasil).

### 5.12. Assets – `admin.js` e `admin.css`

* `admin.js`:

  * estado de carregamento para botões “Testar conexão com IA” e “Processar agora”;
  * desabilita botão, troca texto para “Testando...”/“Processando...”.

* `admin.css`:

  * estilos de cards de dashboard;
  * tabelas e filtros;
  * estilo de botões em carregamento (`.feeds-ia-btn-loading`).

---

## 6. Prompt de IA e formato de saída

### 6.1. Requisitos de conteúdo

As instruções fixas devem incluir:

* Saída **sempre em português do Brasil**.

* Narração em **terceira pessoa**, tom informativo.

* Uso de vocabulário de RPG de mesa.

* Proibição de inventar fatos, alterar datas/valores/nomes.

* Necessidade de **preservar o volume de informação**:

  > não transformar uma matéria em um parágrafo único quando o original traz múltiplos parágrafos; manter o mesmo conjunto de informações, em nova redação.

* Pedido de um **resumo curto** (1–2 frases) para meta description.

#### A resposta da IA é considerada **inválida** se:
* reduzir o corpo da notícia a um único parágrafo curto quando o original tiver múltiplos parágrafos;
* retornar um texto com volume muito inferior ao original (por exemplo, menos de ~60–70% das palavras).
* Quando isso ocorrer, o plugin deve **registrar log de erro** para o item e **não criar rascunho de post** a partir daquela resposta.

### 6.2. Formato de resposta

A saída deve ser **apenas JSON**:

```json
{
  "title": "Título reescrito em português do Brasil",
  "content": "<p>Conteúdo reestruturado em HTML...</p>",
  "summary": "Resumo curto em português para meta description."
}
```

Sem texto extra, cabeçalhos ou comentários de sistema.

### 6.3. Pós-processamento

* Remover fences `json, ` etc.
* `json_decode` em array associativo.
* Validar presença e tipo de `title`, `content`, `summary`.
* Se `content` vier sem `<p>`/`<h*>`, envolver linhas em `<p>...</p>`.
* Em caso de erro:

  * registrar log com `status = error-ai` e mensagem detalhada;
  * abortar criação do post para aquele item.

---

## 7. Datas, horários e fuso horário

* Assumir WordPress configurado para **Brasília** (`America/Sao_Paulo`).
* Para cálculos de tempo:

  * usar sempre `current_time('timestamp')`.
* Para exibição:

  * `date_i18n( 'd/m/Y H:i', $timestamp )`.
* Nos agendamentos, converter sempre para o fuso configurado e usar rótulos de dia em português.

---

## 8. Segurança, validação e performance

* Todas as telas exigem `current_user_can( 'manage_options' )`.
* Formulários com `wp_nonce_field()`/`wp_verify_nonce()`.
* Entrada sanitizada (`esc_url_raw`, `sanitize_text_field`, `sanitize_textarea_field`, casts para `int`).
* Saída escapada (`esc_html`, `esc_attr`, `esc_url`).
* Limite de itens por execução respeitado por feed.
* Timeout de chamadas para IA configurado (20–30s).
* Logs limitados (ex.: 500 registros).

---

## 9. Problemas observados no primeiro teste prático

O primeiro rascunho gerado a partir de um feed (exemplo: notícia sobre “Conjunto Introdutório de Arkham Horror RPG de Mesa Chega ao Brasil”) expôs os seguintes problemas:

1. **Texto excessivamente curto**

   * A IA devolveu um corpo de texto muito pequeno, com poucas frases, em nível de detalhe inferior ao original.
   * Isso viola a regra de “preservar o volume de informação” e gera algo semelhante a um tweet, não a uma nota informativa.

2. **Imagem destacada ausente**

   * O rascunho não recebeu imagem destacada, mesmo o feed tendo conteúdo com ilustração na página de origem.
   * É necessário verificar se o feed está expondo imagem nos campos esperados (`<img>`, `<media:content>`, `<enclosure>` etc.) e, quando houver, tentar baixá-la.

3. **Resumo não utilizado visivelmente**

   * O campo `summary` retornado pela IA não apareceu como metadescrição no Yoast SEO.
   * O resumo foi apenas armazenado em `_feeds_ia_summary` (quando armazenado), mas não houve integração com os campos de SEO.

4. **Integração inexistente com Yoast SEO**

   * A tela do Yoast mostrou:

     * **Metadescrição em branco**;
     * **Frase-chave de foco** vazia;
     * “Semáforo” reclamando de tamanho de título/slug.
   * O plugin hoje não escreve em nenhum meta específico do Yoast.

5. **Título e slug fora das recomendações do Yoast**

   * Título longo demais para o limite “ideal” do Yoast; slug extenso.
   * O plugin não aplica nenhuma política de:

     * sugerir título SEO dentro da faixa recomendada;
     * encurtar slug mantendo entendimento claro.

Esses pontos não quebram o funcionamento técnico do plugin, mas indicam **pendências de refinamento editorial e de SEO** que precisam ser documentadas e planejadas.

---

## 10. Itens pendentes e refatorações

### 10.1. Refatorar código existente

1. **Forçar `post_status = 'draft'`**
   Arquivo: `includes/class-publisher.php`

   * Garantir que qualquer valor de `mode` no feed seja ignorado na criação do post.
   * Documentar em comentário que autopublicação é proibida por política editorial.

2. **Reforçar volume de informação no prompt da IA**
   Arquivo: `includes/class-ai-gemini.php`

   * Ajustar `core_instructions` para incluir explicitamente:

     * manter número de parágrafos e riqueza de detalhes equivalentes ao texto original;
     * não reduzir notícia a um resumo curto.
   * Garantir que isso não incentive invenção de fatos (apenas preservação do conteúdo já presente).

3. **Tratar casos de `summary` vazio**
   Arquivo: `includes/class-ai-gemini.php` e/ou `class-publisher.php`

   * Se o JSON retornar `summary` vazio:

     * gerar fallback a partir das primeiras frases do conteúdo reescrito (sem adicionar fatos novos);
     * registrar log de aviso (`status = warning-summary-fallback`).

4. **Imagem destacada mais robusta**
   Arquivo: `includes/class-publisher.php` e, se necessário, `class-content-processor.php`

   * Revisar algoritmo de extração de imagem:

     * suportar `<media:content>`, `<enclosure>`, `og:image` quando possível;
     * registrar logs específicos quando não for encontrada imagem.
   * Corrigir qualquer uso de variáveis não passadas como parâmetro em `maybe_set_featured_image()`.

5. **Fuso horário nos agendamentos**
   Arquivo: `includes/class-schedules.php`

   * Substituir uso de `gmdate()` por `current_time('timestamp')` + `wp_date()/date_i18n()`.
   * Confirmar que a comparação `time_of_day` × horário atual acontece no fuso do WordPress (Brasil).

6. **Loader incluindo todas as classes**
   Arquivo: `includes/class-loader.php`

   * Garantir `require_once` ou autoload para `class-stats.php` e `class-schedules.php`.

7. **Enfileiramento de assets administrativos**
   Arquivo: `includes/class-admin-menu.php` (ou classe própria de assets)

   * Enfileirar `admin.css` e `admin.js` somente nas telas do plugin.

### 10.2. Implementar funcionalidades previstas

1. **Integração mínima com Yoast SEO (opcional, mas desejável)**
   Arquivos: `includes/class-publisher.php` e/ou helper específico

   * Detecção: se Yoast estiver ativo (por exemplo, `defined( 'WPSEO_VERSION' )` ou `class_exists( 'WPSEO_Meta' )`):
   * Preencher metadados do post:

     * **Meta description**: gravar `summary` em meta key apropriada (por exemplo, `_yoast_wpseo_metadesc` – confirmar nome exato antes de implementar).
     * **Frase-chave de foco**:

       * derivar a partir do título (por exemplo, o nome do sistema ou suplemento citado);
       * gravar em meta key correspondente (por exemplo, `_yoast_wpseo_focuskw` e `_yoast_wpseo_focuskw_text_input` – confirmar).
     * **SEO title**:

       * opcionalmente gravar um título SEO (igual ao título do post ou levemente abreviado);
       * procurar manter faixas de tamanho recomendadas pelo Yoast, sem omitir informações essenciais.
   * Registar log específico quando a integração com Yoast estiver ativa/desativada.

2. **Política de slug e título SEO**
   Arquivos: `class-publisher.php` e possivelmente helper de SEO

   * Antes de criar o post:

     * gerar `post_name` (slug) encurtado, removendo preposições e palavras vazias quando necessário, sem perder o núcleo factual (sistema, produto, ação “chega ao Brasil” etc.);
     * opcionalmente, preparar título SEO próprio, respeitando limite de tamanho recomendado, sem cortar de modo enganoso.
   * Importante: não alterar o título principal de forma que mude o sentido; qualquer abreviação deve preservar o núcleo factual.

3. **Dashboard mais informativo**
   Arquivo: `admin/views/dashboard.php` + `class-stats.php`

   * Acrescentar:

     * blocos com “últimos rascunhos gerados” (título + link para edição);
     * blocos com “últimas execuções de cron” (data/hora, feed, quantidade de itens).

4. **Melhor feedback visual nas telas**
   Arquivo: `assets/js/admin.js`, `assets/css/admin.css`, views

   * Notificações claras de:

     * “Configurações salvas”;
     * “Processamento concluído: X itens, Y rascunhos criados, Z erros”;
     * “Teste de IA: sucesso/erro (com mensagem resumida)”.

---

## 11. Checklist para agentes de desenvolvimento

Antes de considerar qualquer ciclo de desenvolvimento concluído:

* [ ] Todos os posts criados pelo plugin estão como **rascunho** (`post_status = 'draft'`).
* [ ] O texto reescrito mantém o **mesmo conjunto de informações** que o texto original, em português do Brasil, com vocabulário de RPG de mesa.
* [ ] Nenhuma data, número, nome de pessoa/autor/editora/sistema/suplemento foi alterado indevidamente.
* [ ] O resumo (`summary`) está sendo gerado e armazenado; quando Yoast estiver ativo, a meta description corresponde a esse resumo.
* [ ] A interface mostra datas/horas em formato `d/m/Y H:i`, respeitando o fuso horário configurado (Brasil).
* [ ] Dias da semana são exibidos em português nas telas de agendamento.
* [ ] Feeds podem ser cadastrados, editados, removidos e processados manualmente.
* [ ] O cron executa sem erros fatais e cria apenas rascunhos.
* [ ] Logs contêm informações suficientes para auditoria (incluindo testes de IA).
* [ ] O botão “Testar conexão com IA” retorna mensagens claras de sucesso ou falha.
* [ ] A desinstalação remove options `feeds_ia_*` e metadados `_feeds_ia_*`.

---

## 12. Extensões futuras mapeadas

Itens abaixo são sugestões de evolução, não obrigatórios:

* Integração com outros plugins de SEO além do Yoast, respeitando APIs públicas.
* Filtros por palavra-chave por feed (incluir/excluir itens no momento da leitura).
* Fila assíncrona de chamadas à IA para ambientes com grande volume de feeds.
* Modo “pré-análise”: criar rascunhos marcados com uma tag específica quando o feed contiver termos sensíveis (por exemplo, mudanças de licença, polêmicas de mercado), facilitando triagem manual.

Em qualquer evolução, as invariantes editoriais das seções 2 e 6 permanecem prioritárias.
