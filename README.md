# Sistema Arte

Plugin WordPress para gerenciamento de demandas de arte.

## Descricao
O **Sistema Arte** cria um fluxo de solicitacao e acompanhamento de artes dentro do proprio WordPress. A interface publica segue a ideia do mockup: formulario em uma coluna, lista de pendencias em outra e gerenciamento interno por Kanban no painel administrativo.

## Funcionalidades implementadas
- Formulario publico com:
  - Titulo da arte
  - Nome do solicitante
  - Local
  - Telefone/WhatsApp
  - Detalhes da solicitacao
  - Anexo opcional
  - Data de entrega
  - Prioridade
- Data de entrega padrao:
  - `7 dias` apos a data atual
  - Horario fixo em `17:00`
- Shortcodes disponíveis:
  - `[Sistema-Arte]` - Formulário completo com envio de demandas e tabela de pendências
  - `[Sistema-Arte-Acompanhar]` - Tabela individual para acompanhamento das demandas do usuário logado
- Custom Post Type `Demandas de Arte`
- Taxonomia de status com fluxo:
  - `Demanda`
  - `Fazer`
  - `Fazendo`
  - `Feito`
  - `Arquivada`
- Kanban administrativo com drag-and-drop
- Acao de `Arquivar demanda` direto no card do Kanban
- Menu de `Demandas Arquivadas` com opcao de restaurar
- Menu de `Locais` para gerenciar a lista suspensa do formulario
- ID sequencial personalizado no formato `A001`, `A002`, `A003`
- Upload de arquivos com suporte ampliado:
  - PDF, XLS, XLSX, CSV, TXT
  - PNG, JPG, JPEG, GIF, WEBP
  - DOC, DOCX, PPT, PPTX
  - ZIP, RAR

## Estrutura do plugin
- [sistema-arte.php](/c:/Users/Marco/Desktop/artes/sistema-arte.php)
- [assets/css/public.css](/c:/Users/Marco/Desktop/artes/assets/css/public.css)
- [assets/css/admin.css](/c:/Users/Marco/Desktop/artes/assets/css/admin.css)
- [assets/js/admin.js](/c:/Users/Marco/Desktop/artes/assets/js/admin.js)

## Instalacao
1. Compacte a pasta do projeto em `.zip` ou copie a pasta para `wp-content/plugins/sistema-arte`.
2. Ative o plugin no painel do WordPress.
3. O menu **Sistema Arte** aparecera no painel administrativo.
4. Crie uma pagina e adicione o shortcode `[Sistema-Arte]`.

## Como usar

### Shortcode `[Sistema-Arte]`
1. Crie uma página no WordPress.
2. Adicione o shortcode `[Sistema-Arte]` no conteúdo da página.
3. O formulário de envio e a lista de demandas pendentes serão exibidos automaticamente.
4. Usuários logados podem enviar novas demandas.
5. A equipe interna gerencia as demandas pelo painel administrativo no menu "Sistema Arte".

### Shortcode `[Sistema-Arte-Acompanhar]`
1. Crie uma segunda página para acompanhamento.
2. Adicione o shortcode `[Sistema-Arte-Acompanhar]`.
3. Usuários logados verão uma tabela com todas as suas demandas solicitadas.
4. A tabela exibe: ID, Título, Status (com indicador visual), Data de Entrega e Arte Pronta.
5. Se houver arte final disponível, um link para download será exibido.

### Fluxo de Trabalho
1. O solicitante acessa a página com `[Sistema-Arte]` e preenche o formulário.
2. A demanda é criada automaticamente com status `Demanda`.
3. A equipe interna acompanha pelo Kanban e move os cards conforme o progresso:
   - `Demanda` → `Fazer` → `Fazendo` → `Feito`
4. Quando necessário, a demanda pode ser arquivada e depois restaurada pelo menu de arquivadas.
5. O solicitante acompanha o progresso pela página com `[Sistema-Arte-Acompanhar]`.

## Observacoes
- A lista de `Locais` e gerenciada pelo painel administrativo, sem precisar editar codigo.
- O anexo enviado e tratado pelo sistema de midia do WordPress.
- O visual ainda pode ser refinado para ficar ainda mais proximo da referencia.

## Autor
Marco Antonio Vivas
