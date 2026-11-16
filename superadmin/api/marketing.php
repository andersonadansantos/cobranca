<?php
// =====================================================
// API AJAX - MODULO MARKETING (Super Admin)
// =====================================================
require_once __DIR__ . '/../auth.php';
requireSuper();
require_once __DIR__ . '/../../config/marketing_helpers.php';

@set_time_limit(600);
ignore_user_abort(false);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

function mkt_resposta($dados, $codigo = 200) {
    http_response_code($codigo);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

function mkt_erro($mensagem, $codigo = 400) {
    mkt_resposta(['ok' => false, 'mensagem' => $mensagem], $codigo);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mkt_erro('Metodo nao permitido.', 405);
}

$token = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!mkt_csrf_validar($token)) {
    mkt_erro('Sessao expirada. Recarregue a pagina e tente novamente.', 419);
}

$acao = (string) ($_POST['acao'] ?? '');

// -----------------------------------------------------
// Config. ChatGPT (OpenAI) + Google Programmable Search
// -----------------------------------------------------
if ($acao === 'salvar_openai') {
    $atual = mkt_openai_config();
    $key = trim((string) ($_POST['api_key'] ?? ''));
    $modelo = trim((string) ($_POST['modelo'] ?? ''));
    if ($modelo === '' || !preg_match('/^[A-Za-z0-9._-]{2,80}$/', $modelo)) {
        mkt_erro('Informe um modelo valido (ex.: gpt-4o-mini).');
    }
    if ($key === '' && $atual['key'] === '') {
        mkt_erro('Informe a API Key da OpenAI.');
    }
    $ok = mkt_config_set('mkt_openai_modelo', $modelo);
    if ($key !== '') $ok = mkt_config_set('mkt_openai_key', $key, true) && $ok;
    if (!$ok) mkt_erro('Nao foi possivel salvar a configuracao da OpenAI.');

    mkt_resposta(['ok' => true, 'mensagem' => 'Configuracao da OpenAI salva.', 'mascara' => mkt_mascarar($key !== '' ? $key : $atual['key'])]);
}

if ($acao === 'testar_openai') {
    $cfg = mkt_openai_config();
    $r = mkt_openai_testar($cfg);
    mkt_resposta(['ok' => (bool) $r['ok'], 'mensagem' => $r['mensagem'], 'modelos' => $r['modelos'] ?? []]);
}

if ($acao === 'salvar_google') {
    $atual = mkt_google_config();
    $key = trim((string) ($_POST['google_key'] ?? ''));
    $cx = trim((string) ($_POST['google_cx'] ?? ''));
    if ($cx === '' && $atual['cx'] === '') {
        mkt_erro('Informe o ID do mecanismo de busca (CX) do Google.');
    }
    if ($cx !== '' && !preg_match('/^[A-Za-z0-9:_-]{5,60}$/', $cx)) {
        mkt_erro('CX invalido. Copie o identificador do Programmable Search Engine.');
    }
    if ($key === '' && $atual['key'] === '') {
        mkt_erro('Informe a API Key do Google.');
    }
    $ok = mkt_config_set('mkt_google_cx', $cx);
    if ($key !== '') $ok = mkt_config_set('mkt_google_key', $key, true) && $ok;
    if (!$ok) mkt_erro('Nao foi possivel salvar a configuracao do Google.');

    mkt_resposta(['ok' => true, 'mensagem' => 'Configuracao do Google Programmable Search salva.', 'mascara' => mkt_mascarar($key !== '' ? $key : $atual['key'])]);
}

if ($acao === 'testar_google') {
    $cfg = mkt_google_config();
    $r = mkt_google_testar($cfg);
    mkt_resposta(['ok' => (bool) $r['ok'], 'mensagem' => $r['mensagem']]);
}

// -----------------------------------------------------
// Config. Nicho
// -----------------------------------------------------
if ($acao === 'salvar_nicho') {
    $nome = trim((string) ($_POST['nome'] ?? ''));
    $descricao = trim((string) ($_POST['descricao'] ?? ''));
    $cidade = trim((string) ($_POST['cidade'] ?? ''));
    $estado = mkt_estado_valido($_POST['estado'] ?? '');
    $regiao = trim((string) ($_POST['regiao'] ?? ''));
    $limiteWa = (int) ($_POST['limite_whatsapp'] ?? MKT_MAX_WHATSAPP_DIA);
    $limiteEm = (int) ($_POST['limite_email'] ?? MKT_MAX_EMAIL_DIA);

    if ($nome === '') mkt_erro('Informe o nome do nicho.');
    if ($cidade === '') mkt_erro('Informe a cidade.');
    if ($estado === '') mkt_erro('Informe um estado (UF) valido.');
    if ($limiteWa < 1 || $limiteWa > MKT_MAX_WHATSAPP_DIA) $limiteWa = MKT_MAX_WHATSAPP_DIA;
    if ($limiteEm < 1 || $limiteEm > MKT_MAX_EMAIL_DIA) $limiteEm = MKT_MAX_EMAIL_DIA;

    $ok = mkt_config_set('mkt_nicho_nome', mb_substr($nome, 0, 120, 'UTF-8'));
    $ok = mkt_config_set('mkt_nicho_descricao', mb_substr($descricao, 0, 500, 'UTF-8')) && $ok;
    $ok = mkt_config_set('mkt_nicho_cidade', mb_substr($cidade, 0, 120, 'UTF-8')) && $ok;
    $ok = mkt_config_set('mkt_nicho_estado', $estado) && $ok;
    $ok = mkt_config_set('mkt_nicho_regiao', mb_substr($regiao, 0, 120, 'UTF-8')) && $ok;
    $ok = mkt_config_set('mkt_limite_whatsapp', (string) $limiteWa) && $ok;
    $ok = mkt_config_set('mkt_limite_email', (string) $limiteEm) && $ok;
    if (!$ok) mkt_erro('Nao foi possivel salvar o nicho.');

    mkt_resposta(['ok' => true, 'mensagem' => 'Configuracao do nicho salva com sucesso.']);
}

if ($acao === 'pesquisar') {
    $stats = [];
    $r = mkt_pesquisar_contatos($stats);
    mkt_resposta(['ok' => (bool) $r['ok'], 'mensagem' => $r['mensagem'], 'stats' => $r['stats']]);
}

// -----------------------------------------------------
// Config. Cron Job
// -----------------------------------------------------
if ($acao === 'salvar_cron') {
    $ativo = (string) ($_POST['ativo'] ?? '0') === '1' ? '1' : '0';
    $horario = trim((string) ($_POST['horario'] ?? '09:00'));
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $horario)) {
        mkt_erro('Horario invalido. Use o formato HH:MM (24 horas).');
    }
    $ok = mkt_config_set('mkt_cron_ativo', $ativo);
    $ok = mkt_config_set('mkt_cron_horario', $horario) && $ok;
    if (!$ok) mkt_erro('Nao foi possivel salvar o agendamento.');

    $proxima = mkt_cron_proxima_execucao();
    mkt_resposta([
        'ok' => true,
        'mensagem' => 'Agendamento salvo. Fuso horario: America/Sao_Paulo.',
        'proxima' => $proxima ? $proxima->format('d/m/Y H:i') : null,
        'ativo' => $ativo === '1',
    ]);
}

if ($acao === 'executar_agora') {
    $r = mkt_executar_rotina('manual');
    mkt_resposta(['ok' => (bool) $r['ok'], 'mensagem' => $r['mensagem'], 'resultado' => $r]);
}

if ($acao === 'processar_fila') {
    $r = mkt_processar_fila();
    $partes = [$r['whatsapp_enviados'] . ' WhatsApp e ' . $r['emails_enviados'] . ' e-mail enviado(s)'];
    if ($r['falhas'] > 0) $partes[] = $r['falhas'] . ' falha(s)';
    if ($r['pendencias']) $partes[] = implode(' | ', $r['pendencias']);
    mkt_resposta(['ok' => true, 'mensagem' => 'Fila processada: ' . implode('. ', $partes) . '.', 'resultado' => $r]);
}

// -----------------------------------------------------
// Imagens dos templates
// -----------------------------------------------------
if ($acao === 'listar_imagens') {
    $canal = (string) ($_POST['canal'] ?? '');
    if (!in_array($canal, ['whatsapp', 'email'], true)) mkt_erro('Canal invalido.');
    $out = [];
    foreach (mkt_arquivo_listar($canal) as $img) {
        $out[] = [
            'id' => (int) $img['id'],
            'nome' => $img['nome_original'],
            'mime' => $img['mime'],
            'modo' => $img['modo'],
            'tamanho' => (int) $img['tamanho'],
            'url' => mkt_arquivo_url($img['id']),
            'placeholder' => '{{imagem:' . (int) $img['id'] . '}}',
        ];
    }
    mkt_resposta(['ok' => true, 'imagens' => $out]);
}

if ($acao === 'upload_imagem') {
    $canal = (string) ($_POST['canal'] ?? '');
    if (!in_array($canal, ['whatsapp', 'email'], true)) mkt_erro('Canal invalido.');
    $modo = (string) ($_POST['modo'] ?? 'inline');
    $r = mkt_arquivo_salvar_upload($canal, $_FILES['imagem'] ?? null, $modo);
    if (!$r['ok']) mkt_erro($r['mensagem']);

    $img = $r['arquivo'];
    mkt_resposta([
        'ok' => true,
        'mensagem' => 'Imagem enviada com sucesso.',
        'imagem' => [
            'id' => (int) $img['id'],
            'nome' => $img['nome_original'],
            'mime' => $img['mime'],
            'modo' => $img['modo'],
            'tamanho' => (int) $img['tamanho'],
            'url' => mkt_arquivo_url($img['id']),
            'placeholder' => '{{imagem:' . (int) $img['id'] . '}}',
        ],
    ]);
}

if ($acao === 'alterar_modo_imagem') {
    $id = (int) ($_POST['id'] ?? 0);
    $modo = (string) ($_POST['modo'] ?? '');
    if ($id <= 0) mkt_erro('Imagem invalida.');
    if (!in_array($modo, ['inline', 'anexo'], true)) mkt_erro('Modo invalido.');
    if (!mkt_arquivo_atualizar_modo($id, $modo)) mkt_erro('Imagem nao encontrada ou sem alteracao.');
    mkt_resposta(['ok' => true, 'mensagem' => $modo === 'anexo' ? 'Imagem marcada como anexo.' : 'Imagem marcada para o corpo do e-mail.']);
}

if ($acao === 'excluir_imagem') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) mkt_erro('Imagem invalida.');
    if (!mkt_arquivo_excluir($id)) mkt_erro('Nao foi possivel excluir a imagem.');
    mkt_resposta(['ok' => true, 'mensagem' => 'Imagem excluida.']);
}

// -----------------------------------------------------
// Templates
// -----------------------------------------------------
if ($acao === 'salvar_template') {
    $canal = (string) ($_POST['canal'] ?? '');
    if (!in_array($canal, ['whatsapp', 'email'], true)) mkt_erro('Canal invalido.');
    $conteudo = (string) ($_POST['conteudo'] ?? '');
    if (trim($conteudo) === '') mkt_erro('O conteudo do template nao pode ficar vazio.');
    if (strlen($conteudo) > 60000) mkt_erro('Template muito longo (limite de 60.000 caracteres).');

    $dados = [
        'remetente_nome' => (string) ($_POST['remetente_nome'] ?? ''),
        'remetente_email' => (string) ($_POST['remetente_email'] ?? ''),
        'assunto' => (string) ($_POST['assunto'] ?? ''),
        'conteudo' => $conteudo,
    ];
    if ($canal === 'email' && trim($dados['assunto']) === '') mkt_erro('Informe o assunto do e-mail.');
    if ($canal === 'email' && $dados['remetente_email'] !== '' && mkt_normalizar_email($dados['remetente_email']) === '') {
        mkt_erro('E-mail do remetente invalido.');
    }
    if (!mkt_template_salvar($canal, $dados)) mkt_erro('Nao foi possivel salvar o template.');
    mkt_resposta(['ok' => true, 'mensagem' => 'Template de ' . ($canal === 'email' ? 'e-mail' : 'WhatsApp') . ' salvo com sucesso.']);
}

if ($acao === 'testar_whatsapp') {
    $numero = trim((string) ($_POST['numero'] ?? ''));
    if (mkt_normalizar_whatsapp($numero) === '') mkt_erro('Informe um numero de WhatsApp valido com DDD.');
    $cfg = mkt_evolution_config();
    if (!mkt_evolution_configurada($cfg)) {
        mkt_erro('API Evolution nao configurada. Abra a aba API Evolution e salve URL, API Key e instancia.');
    }
    $st = mkt_evolution_status($cfg);
    if ($st['estado'] !== 'conectado') {
        mkt_erro('WhatsApp nao esta conectado (estado: ' . $st['estado'] . '). Conecte na aba API Evolution.');
    }
    $tpl = mkt_template_obter('whatsapp');
    $exemplo = [
        'empresa' => 'Empresa Exemplo',
        'nome_contato' => 'Contato',
        'nicho' => mkt_nicho_config()['nome'] ?: 'seu segmento',
        'cidade' => mkt_nicho_config()['cidade'] ?: 'Sua Cidade',
        'estado' => mkt_nicho_config()['estado'] ?: 'UF',
    ];
    $texto = trim(mkt_texto_sem_placeholders_imagem(mkt_template_render($tpl['conteudo'], $exemplo)));

    $midias = [];
    foreach (mkt_arquivo_listar('whatsapp') as $img) {
        $caminho = mkt_arquivo_caminho($img['arquivo'] ?? '');
        if ($caminho !== '') $midias[] = $img + ['caminho' => $caminho];
    }

    if ($midias) {
        $enviadas = 0;
        $erros = [];
        foreach ($midias as $indice => $img) {
            $legenda = $indice === 0 ? '[TESTE] ' . $texto : '';
            $r = mkt_evolution_enviar_midia($cfg, $numero, $img['caminho'], $img['mime'], $img['nome_original'], $legenda);
            if ($r['ok']) $enviadas++;
            else $erros[] = ($r['error'] ?: ('HTTP ' . $r['http']));
        }
        if ($enviadas === 0) {
            mkt_erro('Falha no envio de teste: ' . implode(' | ', $erros));
        }
        $aviso = $enviadas < count($midias) ? ' (' . $enviadas . ' de ' . count($midias) . ' imagens aceitas)' : '';
        mkt_resposta(['ok' => true, 'mensagem' => 'Teste com imagem enviado para ' . mkt_formatar_whatsapp($numero) . $aviso . '.']);
    }

    $r = mkt_evolution_enviar($cfg, $numero, '[TESTE] ' . $texto);
    if (!$r['ok']) {
        mkt_erro('Falha no envio de teste: ' . ($r['error'] ?: ('HTTP ' . $r['http'])));
    }
    mkt_resposta(['ok' => true, 'mensagem' => 'Mensagem de teste enviada para ' . mkt_formatar_whatsapp($numero) . '.']);
}

if ($acao === 'testar_email') {
    $destino = mkt_normalizar_email($_POST['email'] ?? '');
    if ($destino === '') mkt_erro('Informe um e-mail valido para o teste.');
    $smtp = mkt_smtp_global_config();
    if ($smtp['host'] === '' || $smtp['user'] === '' || $smtp['pass'] === '' || $smtp['from_email'] === '') {
        mkt_erro('SMTP global incompleto. Configure host, usuario, senha e remetente em Super Admin > SMTP.');
    }
    require_once __DIR__ . '/../../config/email_helpers.php';
    $tpl = mkt_template_obter('email');
    $exemplo = [
        'empresa' => 'Empresa Exemplo',
        'nome_contato' => 'Contato',
        'nicho' => mkt_nicho_config()['nome'] ?: 'seu segmento',
        'cidade' => mkt_nicho_config()['cidade'] ?: 'Sua Cidade',
        'estado' => mkt_nicho_config()['estado'] ?: 'UF',
    ];
    $assunto = '[TESTE] ' . mkt_template_render($tpl['assunto'], $exemplo);
    $html = mkt_template_render($tpl['conteudo'], $exemplo);
    $aplicado = mkt_email_aplicar_imagens($html, mkt_arquivo_listar('email'));
    $html = $aplicado['html'];
    $texto = trim(strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $html)));
    $fromEmail = $tpl['remetente_email'] !== '' ? $tpl['remetente_email'] : $smtp['from_email'];
    $fromNome = $tpl['remetente_nome'] !== '' ? $tpl['remetente_nome'] : $smtp['from_nome'];

    $ok = enviarEmailMarketing($smtp['host'], $smtp['port'], $smtp['user'], $smtp['pass'], $fromEmail, $fromNome, $smtp['ssl'],
        $destino, 'Teste Marketing', $assunto, $html, $texto, $aplicado['inline'], $aplicado['anexos']);
    if (!$ok) mkt_erro('Falha no envio de teste. Verifique as configuracoes SMTP e o log do servidor.');
    $resumoImg = '';
    if ($aplicado['inline'] || $aplicado['anexos']) {
        $resumoImg = ' (' . count($aplicado['inline']) . ' imagem(ns) no corpo e ' . count($aplicado['anexos']) . ' em anexo)';
    }
    mkt_resposta(['ok' => true, 'mensagem' => 'E-mail de teste enviado para ' . $destino . $resumoImg . '.']);
}

// -----------------------------------------------------
// Contatos e logs
// -----------------------------------------------------
if ($acao === 'bloquear_contato') {
    $id = (int) ($_POST['id'] ?? 0);
    $optOut = (string) ($_POST['opt_out'] ?? '1') === '1' ? 1 : 0;
    if ($id <= 0) mkt_erro('Contato invalido.');
    $pdo = getConnection();
    $stmt = $pdo->prepare("UPDATE marketing_contatos SET opt_out = ?, status = ? WHERE id = ?");
    $novoStatus = $optOut ? 'bloqueado' : 'coletado';
    $stmt->execute([$optOut, $novoStatus, $id]);
    if ($stmt->rowCount() === 0) mkt_erro('Contato nao encontrado.');
    if ($optOut) {
        $pdo->prepare("UPDATE marketing_envios SET status='cancelado', erro='Contato opt-out' WHERE contato_id=? AND status IN ('aguardando','falha')")->execute([$id]);
    }
    mkt_resposta(['ok' => true, 'mensagem' => $optOut ? 'Contato bloqueado e removido da fila.' : 'Contato reativado.']);
}

if ($acao === 'contatos') {
    $filtros = [
        'busca' => trim((string) ($_POST['busca'] ?? '')),
        'nicho' => trim((string) ($_POST['nicho'] ?? '')),
        'status' => trim((string) ($_POST['status'] ?? '')),
    ];
    $linhas = mkt_contatos_listar($filtros);
    $out = [];
    foreach ($linhas as $c) {
        $out[] = [
            'id' => (int) $c['id'],
            'empresa' => $c['empresa'],
            'nome_contato' => $c['nome_contato'] ?? '',
            'nicho' => $c['nicho'],
            'cidade' => $c['cidade'],
            'estado' => $c['estado'],
            'whatsapp' => $c['whatsapp'],
            'email' => $c['email'],
            'origem' => $c['origem'],
            'data' => $c['data_coleta'] ? date('d/m/Y', strtotime($c['data_coleta'])) : '',
            'status' => $c['status'],
            'opt_out' => (int) $c['opt_out'],
        ];
    }
    mkt_resposta(['ok' => true, 'contatos' => $out]);
}

if ($acao === 'logs') {
    $filtros = [
        'data_ini' => trim((string) ($_POST['data_ini'] ?? '')),
        'data_fim' => trim((string) ($_POST['data_fim'] ?? '')),
        'nicho' => trim((string) ($_POST['nicho'] ?? '')),
        'canal' => trim((string) ($_POST['canal'] ?? '')),
        'status' => trim((string) ($_POST['status'] ?? '')),
    ];
    foreach (['data_ini', 'data_fim'] as $campo) {
        if ($filtros[$campo] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtros[$campo])) $filtros[$campo] = '';
    }
    $out = [];
    foreach (mkt_logs_listar($filtros) as $l) {
        $out[] = [
            'id' => (int) $l['id'],
            'empresa' => $l['empresa'],
            'whatsapp' => $l['whatsapp'],
            'email' => $l['email'],
            'canal' => $l['canal'],
            'assunto' => $l['assunto'],
            'criado_em' => $l['criado_em'] ? date('d/m/Y H:i', strtotime($l['criado_em'])) : '',
            'enviado_em' => $l['enviado_em'] ? date('d/m/Y H:i', strtotime($l['enviado_em'])) : '',
            'status' => $l['status'],
            'resposta_api' => $l['resposta_api'],
            'erro' => $l['erro'],
            'tentativas' => (int) $l['tentativas'],
        ];
    }
    mkt_resposta(['ok' => true, 'logs' => $out]);
}

if ($acao === 'status_geral') {
    $stats = mkt_stats();
    $cron = mkt_cron_config();
    $ultima = mkt_ultima_execucao();
    $proxima = mkt_cron_proxima_execucao();
    $execucoes = mkt_execucoes_listar(10);
    $hist = [];
    foreach ($execucoes as $e) {
        $hist[] = [
            'id' => (int) $e['id'],
            'origem' => $e['origem'],
            'iniciado_em' => $e['iniciado_em'] ? date('d/m/Y H:i', strtotime($e['iniciado_em'])) : '',
            'finalizado_em' => $e['finalizado_em'] ? date('d/m/Y H:i', strtotime($e['finalizado_em'])) : '',
            'status' => $e['status'],
            'whatsapp_novos' => (int) $e['whatsapp_novos'],
            'emails_novos' => (int) $e['emails_novos'],
            'duplicados' => (int) $e['duplicados'],
            'whatsapp_enviados' => (int) $e['whatsapp_enviados'],
            'emails_enviados' => (int) $e['emails_enviados'],
            'falhas' => (int) $e['falhas'],
            'mensagem' => $e['mensagem'],
        ];
    }
    mkt_resposta([
        'ok' => true,
        'stats' => $stats,
        'cron' => [
            'ativo' => $cron['ativo'],
            'horario' => $cron['horario'],
            'proxima' => $proxima ? $proxima->format('d/m/Y H:i') : null,
            'ultima' => $ultima ? [
                'iniciado_em' => $ultima['iniciado_em'] ? date('d/m/Y H:i', strtotime($ultima['iniciado_em'])) : '',
                'status' => $ultima['status'],
                'origem' => $ultima['origem'],
                'mensagem' => $ultima['mensagem'],
            ] : null,
            'ja_executou_hoje' => mkt_cron_ja_executou_hoje(),
        ],
        'execucoes' => $hist,
    ]);
}

// -----------------------------------------------------
// ENVIOS MANUAIS (listas, importacao, campanhas e logs)
// -----------------------------------------------------
if ($acao === 'manual_painel') {
    $disp = mkt_manual_disponiveis();
    $stats = mkt_manual_stats();
    $filas = mkt_manual_filas_resumo();
    $campanhas = [];
    foreach (mkt_campanhas_listar(200) as $cp) {
        // Status consolidado continua sendo exibido, mas cada fila tem o
        // proprio estado (em andamento, pausada, limite, concluida etc.).
        $campanhas[] = [
            'id' => (int) $cp['id'],
            'nome' => $cp['nome'],
            'descricao' => (string) ($cp['descricao'] ?? ''),
            'lista_id' => (int) $cp['lista_id'],
            'lista_nome' => (string) ($cp['lista_nome'] ?? ''),
            'canais' => $cp['canais'],
            'data_inicio' => $cp['data_inicio'] ? date('d/m/Y', strtotime($cp['data_inicio'])) : '',
            'hora_inicio' => substr((string) $cp['hora_inicio'], 0, 5),
            'status' => $cp['status'],
            'ativa' => (int) $cp['ativa'],
            'wa_ativa' => (int) ($cp['wa_ativa'] ?? 0),
            'em_ativa' => (int) ($cp['em_ativa'] ?? 0),
            'wa_status' => mkt_campanha_canal_status($cp, 'whatsapp', $disp),
            'em_status' => mkt_campanha_canal_status($cp, 'email', $disp),
            'criado_em' => $cp['criado_em'] ? date('d/m/Y H:i', strtotime($cp['criado_em'])) : '',
            'total_contatos' => (int) $cp['total_contatos'],
            'wa_total' => (int) ($cp['wa_total'] ?? 0),
            'em_total' => (int) ($cp['em_total'] ?? 0),
            'wa_enviados' => (int) $cp['wa_enviados'],
            'em_enviados' => (int) $cp['em_enviados'],
            'wa_pendentes' => (int) ($cp['wa_pendentes'] ?? 0),
            'em_pendentes' => (int) ($cp['em_pendentes'] ?? 0),
            'wa_falhas' => (int) ($cp['wa_falhas'] ?? 0),
            'em_falhas' => (int) ($cp['em_falhas'] ?? 0),
            'pendentes' => (int) $cp['pendentes'],
            'falhas' => (int) $cp['falhas'],
        ];
    }
    $listas = [];
    foreach (mkt_listas_listar() as $l) {
        $listas[] = [
            'id' => (int) $l['id'],
            'nome' => $l['nome'],
            'descricao' => (string) ($l['descricao'] ?? ''),
            'total_contatos' => (int) $l['total_contatos'],
            'total_campanhas' => (int) $l['total_campanhas'],
            'criado_em' => $l['criado_em'] ? date('d/m/Y H:i', strtotime($l['criado_em'])) : '',
        ];
    }
    $tplWa = mkt_template_obter('whatsapp');
    $tplEm = mkt_template_obter('email');
    mkt_resposta([
        'ok' => true,
        'stats' => $stats,
        'limites' => $disp['limites'],
        'uso' => $disp['uso'],
        'disponiveis' => $disp['disponiveis'],
        'filas' => $filas,
        'listas' => $listas,
        'campanhas' => $campanhas,
        'templates' => [
            'whatsapp' => ['atualizado_em' => $tplWa['atualizado_em'] ?? null],
            'email' => ['assunto' => $tplEm['assunto'], 'atualizado_em' => $tplEm['atualizado_em'] ?? null],
        ],
        'manual_max' => ['whatsapp' => MKT_MANUAL_MAX_WHATSAPP_DIA, 'email' => MKT_MANUAL_MAX_EMAIL_DIA],
    ]);
}

if ($acao === 'manual_lista_criar') {
    $r = mkt_lista_criar($_POST['nome'] ?? '', $_POST['descricao'] ?? '');
    if (!$r['ok']) mkt_erro($r['mensagem']);
    mkt_resposta(['ok' => true, 'mensagem' => $r['mensagem'], 'lista' => $r['lista']]);
}

if ($acao === 'manual_lista_excluir') {
    $r = mkt_lista_excluir((int) ($_POST['id'] ?? 0));
    mkt_resposta($r);
}

if ($acao === 'manual_lista_contatos') {
    $lista = mkt_lista_obter((int) ($_POST['lista_id'] ?? 0));
    if (!$lista) mkt_erro('Lista nao encontrada.');
    $out = [];
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT c.id, c.empresa, c.nome_contato, c.whatsapp, c.email, c.nicho, c.cidade, c.estado, c.opt_out
            FROM marketing_lista_contatos lc JOIN marketing_contatos c ON c.id = lc.contato_id
            WHERE lc.lista_id = ? ORDER BY c.id DESC LIMIT 1000");
        $stmt->execute([(int) $lista['id']]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $out[] = [
                'id' => (int) $c['id'],
                'empresa' => $c['empresa'],
                'nome_contato' => (string) ($c['nome_contato'] ?? ''),
                'whatsapp' => (string) ($c['whatsapp'] ?? ''),
                'email' => (string) ($c['email'] ?? ''),
                'nicho' => (string) ($c['nicho'] ?? ''),
                'cidade' => (string) ($c['cidade'] ?? ''),
                'estado' => (string) ($c['estado'] ?? ''),
                'opt_out' => (int) $c['opt_out'],
            ];
        }
    } catch (Throwable $e) {
        mkt_erro('Nao foi possivel listar os contatos da lista.');
    }
    mkt_resposta(['ok' => true, 'lista' => ['id' => (int) $lista['id'], 'nome' => $lista['nome']], 'contatos' => $out]);
}

if ($acao === 'manual_importar_previa') {
    $listaId = (int) ($_POST['lista_id'] ?? 0);
    $r = mkt_import_salvar_arquivo($_FILES['arquivo'] ?? null);
    if (!$r['ok']) mkt_erro($r['mensagem']);

    $prep = mkt_import_preparar(mkt_import_caminho($r['token']));
    if (!$prep['ok']) {
        mkt_import_excluir($r['token']);
        mkt_erro($prep['erro']);
    }
    $analise = mkt_import_analisar($prep['linhas'], $prep['mapa'], $listaId);
    $linhas = [];
    foreach (array_slice($analise['linhas'], 0, 300) as $l) {
        $linhas[] = [
            'numero' => (int) $l['numero'],
            'empresa' => $l['empresa'],
            'nome_contato' => $l['nome_contato'],
            'whatsapp' => $l['whatsapp'],
            'email' => $l['email'],
            'nicho' => $l['nicho'],
            'cidade' => $l['cidade'],
            'estado' => $l['estado'],
            'status' => $l['status'],
            'motivo' => $l['motivo'],
        ];
    }
    $cabecalhos = [];
    foreach ($prep['mapa'] as $campo => $indice) {
        $cabecalhos[$campo] = $prep['cabecalhos'][$indice] ?? '';
    }
    mkt_resposta([
        'ok' => true,
        'token' => $r['token'],
        'mensagem' => 'Arquivo analisado. Confira a previa antes de confirmar.',
        'resumo' => $analise['resumo'],
        'total_linhas' => count($analise['linhas']),
        'linhas_exibidas' => count($linhas),
        'linhas' => $linhas,
        'colunas' => $cabecalhos,
    ]);
}

if ($acao === 'manual_importar_confirmar') {
    $token = (string) ($_POST['token'] ?? '');
    $listaId = (int) ($_POST['lista_id'] ?? 0);
    $listaNome = trim((string) ($_POST['lista_nome'] ?? ''));
    $resolver = mkt_lista_resolver($listaId, $listaNome);
    if (!$resolver['ok']) mkt_erro($resolver['mensagem']);
    $r = mkt_import_confirmar($token, (int) $resolver['lista']['id']);
    if (!$r['ok']) mkt_erro($r['mensagem']);
    mkt_resposta($r);
}

if ($acao === 'manual_importar_cancelar') {
    $token = (string) ($_POST['token'] ?? '');
    mkt_import_excluir($token);
    mkt_resposta(['ok' => true, 'mensagem' => 'Importacao cancelada e arquivo removido.']);
}

if ($acao === 'manual_contato_criar') {
    $listaId = (int) ($_POST['lista_id'] ?? 0);
    $listaNome = trim((string) ($_POST['lista_nome'] ?? ''));
    $resolver = mkt_lista_resolver($listaId, $listaNome);
    if (!$resolver['ok']) mkt_erro($resolver['mensagem']);
    $r = mkt_manual_contato_criar((int) $resolver['lista']['id'], [
        'empresa' => $_POST['empresa'] ?? '',
        'nome_contato' => $_POST['nome_contato'] ?? '',
        'whatsapp' => $_POST['whatsapp'] ?? '',
        'email' => $_POST['email'] ?? '',
        'nicho' => $_POST['nicho'] ?? '',
        'cidade' => $_POST['cidade'] ?? '',
        'estado' => $_POST['estado'] ?? '',
    ]);
    if (!$r['ok']) mkt_erro($r['mensagem']);
    mkt_resposta(['ok' => true, 'mensagem' => $r['mensagem'], 'contato_id' => $r['contato_id'], 'lista' => $resolver['lista']]);
}

if ($acao === 'manual_limites_salvar') {
    $wa = (int) ($_POST['whatsapp'] ?? MKT_MANUAL_MAX_WHATSAPP_DIA);
    $em = (int) ($_POST['email'] ?? MKT_MANUAL_MAX_EMAIL_DIA);
    if ($wa < 1 || $em < 1) mkt_erro('Os limites devem ser maiores que zero.');
    if (!mkt_manual_limites_salvar($wa, $em)) mkt_erro('Nao foi possivel salvar os limites.');
    $disp = mkt_manual_disponiveis();
    mkt_resposta([
        'ok' => true,
        'mensagem' => 'Limites diarios manuais salvos (WhatsApp ' . $disp['limites']['whatsapp'] . ', e-mail ' . $disp['limites']['email'] . ').',
        'limites' => $disp['limites'],
        'disponiveis' => $disp['disponiveis'],
        'uso' => $disp['uso'],
    ]);
}

if ($acao === 'manual_campanha_criar') {
    $r = mkt_campanha_criar([
        'nome' => $_POST['nome'] ?? '',
        'descricao' => $_POST['descricao'] ?? '',
        'lista_id' => (int) ($_POST['lista_id'] ?? 0),
        'canais' => $_POST['canais'] ?? '',
        'data_inicio' => $_POST['data_inicio'] ?? '',
        'hora_inicio' => $_POST['hora_inicio'] ?? '',
        'ativa' => (string) ($_POST['ativa'] ?? '0') === '1',
    ]);
    if (!$r['ok']) mkt_erro($r['mensagem']);
    mkt_resposta($r);
}

if ($acao === 'manual_campanha_editar') {
    $r = mkt_campanha_atualizar((int) ($_POST['id'] ?? 0), [
        'nome' => $_POST['nome'] ?? '',
        'descricao' => $_POST['descricao'] ?? '',
        'lista_id' => (int) ($_POST['lista_id'] ?? 0),
        'canais' => $_POST['canais'] ?? '',
        'data_inicio' => $_POST['data_inicio'] ?? '',
        'hora_inicio' => $_POST['hora_inicio'] ?? '',
        'ativa' => (string) ($_POST['ativa'] ?? '0') === '1',
    ]);
    if (!$r['ok']) mkt_erro($r['mensagem']);
    mkt_resposta($r);
}

if ($acao === 'manual_campanha_acao') {
    $r = mkt_campanha_acao((int) ($_POST['id'] ?? 0), (string) ($_POST['subacao'] ?? ''), (string) ($_POST['canal'] ?? ''));
    mkt_resposta($r);
}

if ($acao === 'manual_campanha_obter') {
    $campanha = mkt_campanha_obter((int) ($_POST['id'] ?? 0));
    if (!$campanha) mkt_erro('Campanha nao encontrada.');
    $m = mkt_campanha_metricas((int) $campanha['id']);
    $disp = mkt_manual_disponiveis();
    $campanhaComMetricas = array_merge($campanha, $m);
    mkt_resposta([
        'ok' => true,
        'campanha' => [
            'id' => (int) $campanha['id'],
            'nome' => $campanha['nome'],
            'descricao' => (string) ($campanha['descricao'] ?? ''),
            'lista_id' => (int) $campanha['lista_id'],
            'lista_nome' => (string) ($campanha['lista_nome'] ?? ''),
            'canais' => $campanha['canais'],
            'data_inicio' => $campanha['data_inicio'] ? date('d/m/Y', strtotime($campanha['data_inicio'])) : '',
            'data_inicio_iso' => $campanha['data_inicio'],
            'hora_inicio' => substr((string) $campanha['hora_inicio'], 0, 5),
            'status' => $campanha['status'],
            'ativa' => (int) $campanha['ativa'],
            'wa_ativa' => (int) ($campanha['wa_ativa'] ?? 0),
            'em_ativa' => (int) ($campanha['em_ativa'] ?? 0),
            'wa_status' => mkt_campanha_canal_status($campanhaComMetricas, 'whatsapp', $disp),
            'em_status' => mkt_campanha_canal_status($campanhaComMetricas, 'email', $disp),
            'criado_em' => $campanha['criado_em'] ? date('d/m/Y H:i', strtotime($campanha['criado_em'])) : '',
            'iniciada_em' => $campanha['iniciada_em'] ? date('d/m/Y H:i', strtotime($campanha['iniciada_em'])) : '',
            'concluida_em' => $campanha['concluida_em'] ? date('d/m/Y H:i', strtotime($campanha['concluida_em'])) : '',
        ],
        'metricas' => $m,
    ]);
}

if ($acao === 'manual_campanha_preview') {
    $r = mkt_campanha_preview((int) ($_POST['id'] ?? 0));
    if (!$r['ok']) mkt_erro($r['mensagem']);
    mkt_resposta($r);
}

if ($acao === 'manual_processar_agora') {
    $r = mkt_manual_processar();
    mkt_resposta([
        'ok' => (bool) $r['ok'],
        'mensagem' => implode(' ', (array) ($r['mensagens'] ?? ['Processamento concluido.'])),
        'resultado' => $r,
    ]);
}

if ($acao === 'manual_logs') {
    $filtros = [
        'campanha_id' => (int) ($_POST['campanha_id'] ?? 0),
        'canal' => trim((string) ($_POST['canal'] ?? '')),
        'data_ini' => trim((string) ($_POST['data_ini'] ?? '')),
        'data_fim' => trim((string) ($_POST['data_fim'] ?? '')),
        'status' => trim((string) ($_POST['status'] ?? '')),
    ];
    foreach (['data_ini', 'data_fim'] as $campo) {
        if ($filtros[$campo] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtros[$campo])) $filtros[$campo] = '';
    }
    $out = [];
    foreach (mkt_manual_logs($filtros) as $l) {
        $out[] = [
            'id' => (int) $l['id'],
            'campanha_id' => (int) $l['campanha_id'],
            'campanha' => $l['campanha'],
            'empresa' => $l['empresa'],
            'nome_contato' => (string) ($l['nome_contato'] ?? ''),
            'whatsapp' => (string) ($l['whatsapp'] ?? ''),
            'email' => (string) ($l['email'] ?? ''),
            'canal' => $l['canal'],
            'status' => $l['status'],
            'tentativas' => (int) $l['tentativas'],
            'criado_em' => $l['criado_em'] ? date('d/m/Y H:i', strtotime($l['criado_em'])) : '',
            'enviado_em' => $l['enviado_em'] ? date('d/m/Y H:i', strtotime($l['enviado_em'])) : '',
            'resposta_api' => (string) ($l['resposta_api'] ?? ''),
            'erro' => (string) ($l['erro'] ?? ''),
        ];
    }
    mkt_resposta(['ok' => true, 'logs' => $out]);
}

mkt_erro('Acao invalida.');
