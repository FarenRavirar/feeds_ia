# feeds_agent.md

Guia técnico do projeto **feeds_ia** – Plugin de feeds com IA para WordPress (Artifício RPG)

---

## 1. Visão geral e contexto

O projeto **feeds_ia** é um plugin de WordPress dedicado a **notícias de RPG de mesa**. A função principal é:

* Ler **feeds RSS** de sites de RPG (notícias, anúncios de suplementos, cenários, financiamentos coletivos, playtests etc.).
* Pré-processar o conteúdo, mantendo a estrutura factual.
* Enviar o texto para um modelo de IA (Google **Gemini**), com instruções rígidas de não inventar nada.
* Receber um JSON com:

  * `title` – título reescrito em português do Brasil;
  * `content` – corpo da notícia em HTML;
  * `summary` – resumo curto para meta description.
* Criar **posts em rascunho** no WordPress com:

  * título reescrito;
  * conteúdo reestruturado;
  * resumo salvo em metadados e, quando disponível, em campos do **Yoast SEO**;
  * metadados ligando o rascunho à fonte original;
  * tentativa de definir imagem destacada.

O plugin **nunca publica automaticamente**. Toda saída vai para **rascunhos**, para revisão editorial manual.

O pacote completo do plugin está versionado em:

> `https://github.com/FarenRavirar/feeds_ia`

Quando algum agente de IA não conseguir ler esse repositório diretamente, deve solicitar os arquivos necessários em blocos (um arquivo por resposta), refatorando sempre com base no código real.

---

## 2. Princípios editoriais invariáveis

Estes princípios são “pétreos”. Nenhuma refatoração, prompt ou extensão do plugin pode violá-los.

### 2.1. Proibição absoluta de invenção de fatos

* Nenhuma informação nova pode ser inventada.
* Não é permitido alterar:

  * datas, anos, horários;
  * valores numéricos (preço, meta de financiamento, porcentagens);
  * nomes de pessoas, autores, editoras, sistemas, cenários, suplementos, campanhas, eventos, marcas ou plataformas.
* Não é permitido inferir bastidores, rumores ou intenções que não estejam claramente no texto.

Quando algo estiver vago na fonte, o texto reescrito deve manter o mesmo grau de vagueza, sem completar lacunas.

### 2.2. Reescrita como paráfrase estruturada, não resumo

* A IA precisa **parafrasear e organizar**, não condensar.
* Obrigatório:

  * preservar o mesmo conjunto de fatos do original;
  * manter nível de detalhe equivalente;
  * reorganizar parágrafos apenas para clareza;
  * escrever em português do Brasil.
* Não é permitido transformar matérias de vários parágrafos em um texto minúsculo.

Regra prática:

> O corpo reescrito deve manter **pelo menos ~70% do número de palavras** do texto original, com quantidade de parágrafos e informações equivalente. Se o original tiver cinco parágrafos com detalhes específicos, a saída também deve ter múltiplos parágrafos cobrindo os mesmos detalhes.

### 2.3. Idioma

* Toda saída é em **português do Brasil**.
* Quando a fonte estiver em outro idioma, a saída é **paráfrase fiel em português**, não adaptação criativa.

### 2.4. Vocabulário de RPG de mesa

* Usar vocabulário específico de RPG:
  “sistema”, “jogo”, “cenário”, “suplemento”, “livro básico”, “classe”, “subclasse”, “mesa”, “one-shot”, “campanha”, “playtest”, “financiamento coletivo”, “ambientação” etc.
* Evitar termos genéricos que apaguem o contexto de RPG.

### 2.5. Estilo de texto

* Terceira pessoa.
* Tom informativo, objetivo.
* Sem emoção explícita, sem opinião que não conste na fonte.

### 2.6. Crédito à fonte

Todo rascunho precisa:

* Guardar a URL original em metadados (`_feeds_ia_original_link`).
* Exibir, no final do conteúdo, um parágrafo de fonte:

```html
<p><em>Fonte original: <a href="URL_ORIGINAL" target="_blank" rel="noopener noreferrer">URL_ORIGINAL</a></em></p>
```

### 2.7. Modo de publicação (sempre rascunho)

* Todo post criado é **sempre** `post_status = 'draft'`.
* Qualquer configuração de “publicar automaticamente” deve ser ignorada em código.
* A decisão de publicar é sempre manual.

### 2.8. SEO e metadados

Quando o plugin de SEO **Yoast** estiver ativo:

* O campo `summary` retornado pela IA deve ser gravado como:

  * metadado interno `_feeds_ia_summary`;
  * meta description do Yoast: `_yoast_wpseo_metadesc`.
* A **frase-chave de foco** deve ser derivada do próprio título (sem criar nomes novos). Regra mínima:

  * usar o próprio título ou um recorte literal que contenha o nome do sistema/produto;
  * gravar em `_yoast_wpseo_focuskw`.
* O **título SEO** pode ser igual ao título do post ou levemente abreviado, gravado em `_yoast_wpseo_title`, respeitando a faixa típica de 50–60 caracteres sem cortar de forma enganosa.
* O slug (`post_name`) deve ser enxuto, preservando:

  * o nome do sistema, cenário ou produto;
  * a ação principal (“é anunciado”, “entra em financiamento coletivo” etc.);
  * sem cortar nomes próprios no meio.

Nenhum desses campos de SEO pode contradizer o conteúdo factual do corpo.

---

## 3. Fluxo funcional do plugin

### 3.1. Visão geral

1. **Cadastro de feeds**
   Tela `Feeds IA → Feeds` (`admin/views/settings-feeds.php`):

   * nome interno;
   * URL do feed RSS;
   * categoria de destino;
   * status (ativo/inativo);
   * frequência em minutos;
   * itens por execução;
   * modo (apenas exibido; internamente tudo vira rascunho);
   * botão **Processar agora** por feed.

2. **Execução**
   Pode ocorrer de duas formas:

   * via `wp_cron`, através de `Feeds_IA_Cron::run_scheduled()`;
   * via botão **Processar agora**, através de `Feeds_IA_Cron::run_for_feed()`.

3. **Pipeline interno** para cada feed:

   * `Feeds_IA_Feeds_Manager` traz itens novos do RSS.
   * `Feeds_IA_Content_Processor` normaliza texto e extrai imagem.
   * `Feeds_IA_AI::rewrite_article()` chama o provedor (Gemini).
   * `Feeds_IA_Publisher::create_post()` cria rascunho e grava metadados.
   * `Feeds_IA_Logger::log()` registra cada etapa (sucesso/erro) e um resumo final por feed.

4. **Logs**
   Tela `Feeds IA → Logs` (`admin/views/logs.php`) lista eventos recentes com filtros.

### 3.2. Estrutura de arquivos conhecida

O plugin utiliza, entre outros:

* `includes/class-settings.php`
* `includes/class-admin-menu.php`
* `includes/class-cron.php`
* `includes/class-content-processor.php`
* `includes/class-ai-gemini.php`
* `includes/class-publisher.php`
* `includes/class-logger.php`
* `admin/views/settings-feeds.php`
* `admin/views/logs.php`
* `assets/js/admin.js`

Outros arquivos podem existir; a referência atual é sempre o repositório `feeds_ia` no GitHub.

---

## 4. Situação atual observada em testes (13/11/2025)

### 4.1. Logs de execução

Exemplo de log recente:

* Feed **Bell**:

  * “3 itens novos, 0 rascunhos criados, 3 erros de IA, 0 erros de publicação.”
  * Para cada item, status “Erro de IA” com mensagem “Resposta inválida do provedor de IA”.
* Feed **Joga o D20**:

  * “3 itens novos, 1 rascunhos criados, 2 erros de IA, 0 erros de publicação.”
  * Um item com status “Sucesso (rascunho criado)”.
  * Dois itens com “Resposta inválida do provedor de IA”.

Conclusão: o pipeline está rodando, mas a maior parte das respostas da IA está sendo rejeitada como inválida.

### 4.2. Rascunho criado – exemplo Mausritter

Em um dos casos bem-sucedidos (notícia sobre a Caixa Básica de **Mausritter**):

* O rascunho foi criado.
* O corpo do texto permaneceu **muito curto**, com apenas um parágrafo ou pouco mais, abaixo do volume de informação desejado.
* Não houve:

  * imagem destacada;
  * meta description preenchida pelo Yoast;
  * frase de foco do Yoast;
  * resumo visível além do parágrafo curto.

Ou seja:

* há rascunhos sendo criados, mas ainda:

  * com **texto mínimo**;
  * **sem integração com Yoast**;
  * **sem uso do `summary`**;
  * **sem imagem destacada** (salvo casos em que a imagem venha explicitamente da URL de `image_url`).

### 4.3. Problema adicional: “Processar agora” sem rascunhos

Ao clicar em **Processar agora** para alguns feeds (por exemplo, **Bell**), o plugin:

* exibe mensagem de sucesso de salvamento dos feeds;
* registra no log que houve itens novos;
* registra apenas erros de IA e **nenhum rascunho** criado.

Portanto, os problemas centrais hoje são:

1. Respostas de IA frequentemente inválidas (JSON ou estrutura).
2. Mesmo quando válidas, várias saídas são curtas demais.
3. Falta de integração com Yoast (meta description, foco, título SEO).
4. Falta de imagem destacada em muitos casos.
5. Logs pouco detalhados quanto ao motivo exato de cada erro de IA.
6. Botão “Processar agora” aparentemente bem-sucedido, mas sem rascunhos em vários feeds.

---

## 5. Especificação de comportamento desejado

### 5.1. Pré-processamento (`Feeds_IA_Content_Processor`)

Entrada por item de feed:

```php
[
  'feed_id'      => string,
  'title'        => string,
  'content_raw'  => string, // HTML do feed
  'link'         => string,
  'image_url'    => string|null,
  'tags'         => string[],
  'published_at' => int,
  'guid'         => string,
]
```

Saída:

```php
[
  'feed_id'      => string,
  'title'        => string,
  'content_text' => string,      // texto já limpo
  'link'         => string,
  'image_url'    => string|null, // já sanitizada
  'tags'         => string[],
  'published_at' => int,
  'guid'         => string,
]
```

Regras:

* Remover `<script>` e `<style>`.
* Converter `<br>`, `</p>`, `</div>`, `</li>` em quebras de linha.
* Remover demais tags com `wp_strip_all_tags`.
* Normalizar quebras múltiplas de linha (no máximo duas seguidas).
* Se `image_url` vier vazio:

  * tentar extrair a primeira `<img>` do HTML.
* Se o texto limpo ficar totalmente vazio:

  * usar o título e a URL da fonte como fallback mínimo.

### 5.2. Chamada de IA (`Feeds_IA_AI` + `Feeds_IA_AI_Gemini`)

#### 5.2.1. Prompt

O `core_instructions` deve incluir explicitamente:

* PT-BR, terceira pessoa, vocabulário de RPG.

* Proibição de inventar fatos.

* Exigência de preservar:

  * todas as informações factuais;
  * o nível de detalhe original.

* Exigência de manter **comprimento mínimo**:

  * instrução clara:

    > “O texto reescrito deve ter comprimento e nível de detalhe semelhantes ao original, jamais sendo reduzido a um parágrafo curto.”

* Pedido de resumo (`summary`) de 1–2 frases.

Formato de saída exigido:

```json
{
  "title": "Título reescrito em português do Brasil",
  "content": "<p>Corpo da notícia em HTML...</p>",
  "summary": "Resumo curto em português para meta description."
}
```

Sem markdown, sem texto fora do JSON.

#### 5.2.2. Validação da resposta

Ao receber o retorno:

1. Extrair texto do Gemini (candidates → content → parts).

2. Remover fences `e`json.

3. Fazer `json_decode()` para array.

4. Validar:

   * campos obrigatórios: `title`, `content`, `summary`;
   * tipos: todos `string`;
   * `content` não vazio após `trim`.

5. Comparar **comprimento**:

   * calcular número de palavras do `content_text` original;
   * calcular número de palavras de `content`;
   * se `content` tiver **menos de 70%** das palavras do original **OU** menos que um limite absoluto mínimo (por exemplo, 120–150 palavras), marcar como **inválido por texto curto**.

6. Verificar parágrafos:

   * se não houver `<p>` ou `<h>` no `content`, converter linhas em `<p>…</p>`;
   * caso, mesmo assim, o conteúdo fique numa única linha com poucas frases, considerar inválido.

Em caso de falha:

* retornar `WP_Error` com códigos diferenciados, por exemplo:

  * `feeds_ia_ai_invalid_json`
  * `feeds_ia_ai_missing_fields`
  * `feeds_ia_ai_empty_text`
  * `feeds_ia_ai_too_short`
  * `feeds_ia_ai_http_error`
* não criar rascunho.

### 5.3. Publicação (`Feeds_IA_Publisher`)

#### 5.3.1. Criação do post

* `post_type = 'post'`

* `post_status = 'draft'`

* `post_title`:

  * priorizar `ai_result['title']`;
  * fallback: título original do feed.

* `post_content`:

  * usar `ai_result['content']`;
  * anexar parágrafo “Fonte original […]”.

* `post_author`:

  * pegar de `feeds_ia_general['default_author_id']`, se definido;
  * fallback: usuário atual.

* Categoria:

  * usar ID configurado no feed, se houver.

* Slug:

  * gerar a partir do título;
  * remover palavras vazias (“de”, “do”, “da”, “um”, “uma” etc.), preservando nomes próprios e termo central (sistema/produto).

Metadados internos:

* `_feeds_ia_original_link`
* `_feeds_ia_original_guid`
* `_feeds_ia_feed_id`
* `_feeds_ia_summary` (texto do summary)
* `_feeds_ia_model` (modelo do Gemini)
* `_feeds_ia_hash` (hash de título+link+guid)

Imagem destacada:

* se `image_url` for válido:

  * usar função de sideload para baixar e anexar;
  * registrar log específico em caso de erro (`status = error-image`).

#### 5.3.2. Integração com Yoast SEO

Se Yoast estiver ativo (`class_exists( 'WPSEO_Meta' )` ou similar):

* Gravando SEO title:

  * meta `_yoast_wpseo_title`:

    * pode repetir `post_title` ou versão abreviada;
    * cortar para faixa aproximada de 50–60 caracteres sem quebrar nomes.

* Gravando meta description:

  * meta `_yoast_wpseo_metadesc`:

    * usar `summary` exatamente como retornado pela IA;
    * limpar HTML, se houver.

* Gravando foco:

  * meta `_yoast_wpseo_focuskw` e, se aplicável, `_yoast_wpseo_focuskw_text_input`:

    * extrair foco do título, por exemplo:

      * nome do sistema (“Mausritter”);
      * ou “Mausritter caixa básica em português”;
    * nunca introduzir nomes novos que não estejam no título.

Quando Yoast não estiver ativo:

* manter apenas `_feeds_ia_summary`;
* não registrar `_yoast_*`.

Sempre registrar log indicando se a integração Yoast foi aplicada ou não.

---

## 6. Logs (`Feeds_IA_Logger` + `admin/views/logs.php`)

### 6.1. Estrutura de armazenamento

A option `feeds_ia_logs` armazena lista de entradas, da mais recente para a mais antiga.

Campos recomendados:

```php
[
  'timestamp'       => int,    // current_time('timestamp')
  'feed_id'         => string, // ID interno do feed
  'feed_name'       => string, // nome exibido na UI
  'status'          => string, // ver tabela abaixo
  'title_original'  => string,
  'title_generated' => string,
  'message'         => string, // detalhe do erro ou resumo
  'post_id'         => int|null,
  'extra'           => array|null, // opcional: detalhes técnicos (tipo de erro da IA, trecho da resposta etc.)
]
```

Limite máximo recomendado: **500 entradas**; descartar as mais antigas ao exceder.

### 6.2. Status padronizados

Sugestão de status:

* `summary` – entrada de resumo da execução do feed:

  * mensagem no formato:
    `Processamento concluído para o feed "X": N itens novos, R rascunhos criados, A erros de IA, P erros de publicação.`
* `success` – rascunho criado com sucesso.
* `error-feed` – erro lendo o feed (HTTP, XML, SimplePie etc.).
* `error-ai-json` – resposta da IA com JSON inválido.
* `error-ai-missing-fields` – faltando `title`, `content` ou `summary`.
* `error-ai-empty` – resposta vazia.
* `error-ai-too-short` – resposta muito curta, abaixo do limite.
* `error-ai-http` – erro HTTP ao chamar a IA.
* `error-publish` – falha ao criar o post.
* `error-image` – falha ao baixar imagem destacada.

A mensagem (`message`) deve mencionar:

* motivo específico do erro;
* em caso de `error-ai-too-short`, valores usados na validação (palavras originais vs geradas).

### 6.3. Tela de logs

`admin/views/logs.php` deve:

* exibir filtros por:

  * feed;
  * status;
  * período (1, 7, 30, 90 dias).
* exibir colunas:

  * Data e hora (`d/m/Y H:i`, fuso do WordPress);
  * Feed;
  * Status;
  * Título original;
  * Título gerado;
  * Mensagem;
  * Link para edição do rascunho, quando `post_id` existir.

Adicionar botão **“Limpar logs”**, com:

* formulário separado com `POST` e nonce próprio;
* chamada a `Feeds_IA_Logger::clear_logs()`;
* mensagem de confirmação “Logs apagados com sucesso”.

---

## 7. Execução agendada e manual (`Feeds_IA_Cron`)

### 7.1. Agendamento periódico

* `Feeds_IA_Cron::CRON_HOOK` agendado com `wp_schedule_event`.
* Intervalo personalizado (por exemplo, 15 minutos).
* `run_scheduled()`:

  * obtém `current_time( 'timestamp' )`;
  * carrega feeds de `Feeds_IA_Settings::get_feeds()`;
  * para cada feed ativo:

    * verifica se `frequency` em minutos foi atingida via `should_run_feed()`;
    * chama `run_for_feed( $feed, $now_ts )`;
    * atualiza `last_run`.

### 7.2. Execução manual (“Processar agora”)

* `settings-feeds.php` envia `feeds_ia_action = run_feed_now` com `feed_id`.
* O handler chama `Feeds_IA_Cron::run_for_feed()` com a configuração do feed.
* Deve sempre registrar:

  * uma entrada `summary` ao final;
  * entradas individuais para cada item (sucesso ou erro).

Ponto a corrigir: hoje há feeds em que o log indica itens novos, mas nenhum rascunho é criado; esse comportamento precisa ser explicado por logs mais detalhados e corrigido ajustando:

* prompt e validação de IA;
* tratamento de erros em `run_for_feed()`.

---

## 8. Como um agente de IA deve trabalhar com este projeto

1. **Ler este `feeds_agent.md` na íntegra.**

2. **Verificar o código real**:

   * se for possível acessar `https://github.com/FarenRavirar/feeds_ia`, baixar o repositório completo;
   * se não for possível, solicitar explicitamente os arquivos necessários (um por resposta), sempre trabalhando em cima do código enviado.

3. **Construir um plano de ação explícito**, antes de alterar qualquer arquivo, por exemplo:

   * Ajustar prompt e validação de comprimento em `class-ai-gemini.php`.
   * Revisar `Feeds_IA_AI` (ou equivalente) para retornar `WP_Error` com códigos específicos.
   * Ajustar `class-publisher.php` para:

     * garantir `post_status = 'draft'`;
     * integrar Yoast (`_yoast_wpseo_*`);
     * tratar slug e título SEO;
     * reforçar imagem destacada.
   * Revisar `class-logger.php` e `admin/views/logs.php` para:

     * padronizar campos;
     * adicionar detalhe de erros;
     * incluir botão “Limpar logs”.
   * Revisar `class-cron.php` e `settings-feeds.php` para:

     * confirmar que “Processar agora” aciona `run_for_feed()` corretamente;
     * registrar logs de resumo por feed.

4. **Ao propor mudanças**:

   * sempre devolver o **arquivo completo**, e **um arquivo por resposta**;
   * não omitir trechos;
   * manter comentários em português descrevendo decisões importantes.

5. **Após cada conjunto de alterações**, sugerir testes objetivos:

   * cadastrar ou reutilizar feeds;
   * clicar em “Processar agora”;
   * verificar:

     * quantidade de rascunhos criada;
     * tamanho dos textos;
     * preenchimento de Yoast;
     * imagem destacada;
     * conteúdo e clareza dos logs.

---

## 9. Checklist de conformidade

Antes de considerar o plugin em estado estável, as seguintes condições precisam estar verdadeiras:

* Todos os posts criados pelo plugin aparecem como **rascunho**.
* O texto reescrito preserva o conjunto de informações da fonte, em português do Brasil, com vocabulário de RPG, sem invenção de fatos.
* A IA não gera textos minúsculos; a validação bloqueia qualquer resposta muito curta.
* O resumo (`summary`) está sendo gerado; quando Yoast está ativo, a meta description corresponde a esse resumo.
* A frase de foco e o título SEO do Yoast são preenchidos de forma coerente com o título do post e não inventam nomes.
* A URL da fonte aparece no final do conteúdo, com link clicável.
* Imagens destacadas são definidas sempre que o feed fornece imagem acessível; falhas de imagem aparecem em logs.
* A tela de logs mostra, para cada execução:

  * resumo por feed (itens novos, rascunhos, erros);
  * entradas de erro detalhando o tipo de problema de IA ou publicação;
  * link para editar rascunhos em sucesso.
* O botão “Limpar logs” funciona e zera o histórico.
* A execução via cron e via “Processar agora” produz o mesmo comportamento observável:

  * criação ou não de rascunhos;
  * mesmos tipos de log.

---

Este documento deve ser mantido atualizado sempre que o plugin **feeds_ia** for alterado. Qualquer novo agente de desenvolvimento ou agente de IA deve iniciar o trabalho relendo este arquivo, conferindo o código vigente no repositório e, em seguida, propondo um plano de ação alinhado com as regras editoriais e técnicas aqui descritas.
