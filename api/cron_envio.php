<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/email_helpers.php';
require_once __DIR__ . '/../config/mercadopago.php';
require_once __DIR__ . '/../api/whatsapp_send.php';

$isHttp = (php_sapi_name() !== 'cli');
if ($isHttp) {
    header('Content-Type: text/plain; charset=utf-8');
    ignore_user_abort(true);
    set_time_limit(120);
    $tokenEsperado = getConfig('cron_token', '');
    $tokenRecebido = $_GET['token'] ?? '';
    if ($tokenEsperado === '' || !hash_equals($tokenEsperado, $tokenRecebido)) {
        http_response_code(403);
        die("Acesso negado - token inválido.");
    }
}

$pdo = getConnection();
if (!$pdo) { die("Erro de conexao"); }

$log = [];
$hoje = date('Y-m-d');

$faturasPendentes = $pdo->prepare("SELECT f.*, c.email, c.celular, c.telefone, c.nome_razao, c.cpf_cnpj FROM faturas f JOIN clientes c ON f.cliente_id = c.id WHERE f.status IN ('pendente','vencido','atrasado') AND (f.mp_payment_id IS NOT NULL AND f.mp_payment_id != '' OR f.inter_codigo_solicitacao IS NOT NULL AND f.inter_codigo_solicitacao != '')");
$faturasPendentes->execute();
$pendentes = $faturasPendentes->fetchAll();

$apiAtiva = getApiAtiva();

foreach ($pendentes as $fat) {
    $novoStatus = null;
    $dataPagamento = null;

    if ($apiAtiva === 'inter' && !empty($fat['inter_codigo_solicitacao'])) {
        $detalhe = consultarCobrancaInter($fat['inter_codigo_solicitacao']);
        if ($detalhe && !isset($detalhe['erro'])) {
            $situacao = strtoupper($detalhe['situacao'] ?? $detalhe['cobranca']['situacao'] ?? '');
            if (in_array($situacao, ['PAGA','RECEBIDO'])) { $novoStatus = 'pago'; $dataPagamento = date('Y-m-d'); }
            elseif ($situacao === 'VENCIDA') { $novoStatus = 'vencido'; }
            elseif (in_array($situacao, ['EXPIRADO','EXPIRADA','CANCELADO','CANCELADA'])) { $novoStatus = 'cancelado'; }
        }
    } elseif ($apiAtiva === 'bb' && !empty($fat['mp_payment_id'])) {
        $detalhe = consultarBoletoBB($fat['mp_payment_id']);
        if ($detalhe && !isset($detalhe['erro'])) {
            $situacao = strtoupper($detalhe['situacaoBoleto']['codigoSituacaoBoleto'] ?? '');
            if (in_array($situacao, ['BAIXADO','PAGO','RECEBIDO'])) { $novoStatus = 'pago'; $dataPagamento = date('Y-m-d'); }
        }
    } elseif ($apiAtiva === 'pagbank' && !empty($fat['mp_payment_id'])) {
        $detalhe = consultarPedidoPagBank($fat['mp_payment_id']);
        if ($detalhe) {
            $charges = $detalhe['charges'] ?? [];
            foreach ($charges as $charge) {
                $situacao = strtoupper($charge['status'] ?? '');
                if ($situacao === 'PAID') { $novoStatus = 'pago'; $dataPagamento = date('Y-m-d'); break; }
                elseif ($situacao === 'CANCELED') { $novoStatus = 'cancelado'; break; }
            }
        }
    } elseif ($apiAtiva === 'mercadopago' && !empty($fat['mp_payment_id'])) {
        $pagamento = consultarPagamento($fat['mp_payment_id']);
        if ($pagamento) {
            $statusMP = $pagamento['status'] ?? '';
            if ($statusMP === 'approved') { $novoStatus = 'pago'; $dataPagamento = date('Y-m-d'); }
            elseif (in_array($statusMP, ['cancelled','refunded'])) { $novoStatus = 'cancelado'; }
        }
    }

        if ($novoStatus !== null && $novoStatus !== $fat['status']) {
            $stmt = $pdo->prepare("UPDATE faturas SET status = ?, data_pagamento = ? WHERE id = ? AND status != 'pago'");
            $stmt->execute([$novoStatus, $dataPagamento, $fat['id']]);
            if ($novoStatus === 'pago') {
                $stmtLog = $pdo->prepare("INSERT INTO pagamentos_log (fatura_id, mp_payment_id, mp_status, mp_status_detail, valor_pago, tipo_pagamento, dados_raw) VALUES (?, ?, 'approved', 'Baixa automatica via cron', ?, ?, ?)");
                $stmtLog->execute([$fat['id'], $fat['mp_payment_id'] ?? $fat['inter_codigo_solicitacao'] ?? '', $fat['valor_final'], $apiAtiva, json_encode(['source' => 'cron'])]);
                if (!empty($fat['email'])) {
                    $fat['data_pagamento'] = $dataPagamento;
                    enviarEmailPagamento($fat);
                }
                if (enviarWhatsAppFatura($fat, 'pagamento')) {
                    $log[] = "[whatsapp_pagamento] {$fat['numero']} -> " . ($fat['celular'] ?? $fat['telefone']);
                }
            }
        $log[] = "[baixa] {$fat['numero']} -> {$novoStatus}";
    }
}

$cronAtivo = getConfig('cron_envio_ativo', '0');
if ($cronAtivo !== '1') {
    file_put_contents(__DIR__ . '/cron_log.txt', date('Y-m-d H:i:s') . " - " . implode(" | ", $log) . "\n", FILE_APPEND);
    die("CRON executado (baixa): " . count($log) . " acoes\n");
}

// Janela de envio: como o cron-job.org executa a URL a cada poucos minutos,
// os e-mails/WhatsApp da régua só são enviados dentro da janela configurada
// em envio_hora (60 minutos a partir do horário definido).
$envioHora = getConfig('envio_hora', '08:00');
$tsAlvo = strtotime(date('Y-m-d') . ' ' . $envioHora . ':00');
$naJanela = false;
if ($tsAlvo !== false && time() >= $tsAlvo && time() < $tsAlvo + 3600) {
    $naJanela = true;
}

// =====================================================
// GERAÇÃO AUTOMÁTICA DE FATURAS RECORRENTES
// Gera a próxima fatura quando o prazo da frequência vence.
// As novas faturas são criadas com ultimo_envio_tipo = NULL
// e passam pela régua de cobrança abaixo (1º envio, lembretes, etc).
// =====================================================
function proximoVencimentoRecorrencia($frequencia, $dataBase, $diaVenc) {
    if ($frequencia === 'diaria') return date('Y-m-d', strtotime($dataBase . ' +1 day'));
    if ($frequencia === 'semanal') return date('Y-m-d', strtotime($dataBase . ' +7 days'));
    if ($frequencia === 'quinzenal') return date('Y-m-d', strtotime($dataBase . ' +15 days'));
    $meses = [
        'mensal' => 1, 'bimestral' => 2, 'trimestral' => 3,
        'semestral' => 6, 'anual' => 12,
    ][$frequencia] ?? 0;
    if ($meses <= 0) return null;
    $dia = max(1, min(31, intval($diaVenc ?: 1)));
    $ano = intval(date('Y', strtotime($dataBase)));
    $mesBase = intval(date('m', strtotime($dataBase)));
    $novoMes = $mesBase + $meses;
    while ($novoMes > 12) { $novoMes -= 12; $ano++; }
    $ultimoDia = intval(date('t', strtotime("{$ano}-{$novoMes}-01")));
    $dia = min($dia, $ultimoDia);
    return date('Y-m-d', strtotime("{$ano}-{$novoMes}-{$dia}"));
}

$stmtRec = $pdo->prepare("SELECT fr.*, c.email, c.celular, c.telefone, c.nome_razao, c.cpf_cnpj
    FROM faturas_recorrentes fr
    JOIN clientes c ON fr.cliente_id = c.id
    WHERE fr.ativo = 1 AND (fr.status = 'ativa' OR fr.status IS NULL OR fr.status = '')
    AND fr.frequencia IS NOT NULL AND fr.frequencia != '' AND fr.frequencia != 'unica'");
$stmtRec->execute();
$recorrentes = $stmtRec->fetchAll();

foreach ($recorrentes as $rec) {
    $stmtUlt = $pdo->prepare("SELECT data_vencimento FROM faturas WHERE fatura_recorrente_id = ? ORDER BY data_vencimento DESC, id DESC LIMIT 1");
    $stmtUlt->execute([$rec['id']]);
    $ultima = $stmtUlt->fetch();

    if (!$ultima) {
        $diaVenc = max(1, min(31, intval($rec['dia_vencimento'] ?? 1)));
        $primeiraVenc = date('Y-m-' . str_pad($diaVenc, 2, '0', STR_PAD_LEFT));
        if ($primeiraVenc < $hoje) {
            $primeiraVenc = date('Y-m-' . str_pad($diaVenc, 2, '0', STR_PAD_LEFT), strtotime('+1 month'));
        }
        if ($primeiraVenc > $hoje) continue;
        $proximaVenc = $primeiraVenc;
    } else {
        if ($ultima['data_vencimento'] > $hoje) continue;
        $proximaVenc = proximoVencimentoRecorrencia($rec['frequencia'], $ultima['data_vencimento'], $rec['dia_vencimento'] ?? 1);
        $guard = 0;
        while ($proximaVenc !== null && $proximaVenc < $hoje && $guard < 500) {
            $proximaVenc = proximoVencimentoRecorrencia($rec['frequencia'], $proximaVenc, $rec['dia_vencimento'] ?? 1);
            $guard++;
        }
        if ($proximaVenc === null) continue;
    }

    if (!empty($rec['data_fim']) && $proximaVenc > $rec['data_fim']) {
        $pdo->prepare("UPDATE faturas_recorrentes SET ativo = 0, status = 'cancelado' WHERE id = ?")->execute([$rec['id']]);
        continue;
    }

    $stmtEx = $pdo->prepare("SELECT COUNT(*) FROM faturas WHERE fatura_recorrente_id = ? AND data_vencimento = ?");
    $stmtEx->execute([$rec['id'], $proximaVenc]);
    if ($stmtEx->fetchColumn() > 0) continue;

    $numero = generateInvoiceNumber();
    $acessoToken = function_exists('generateAcessoToken') ? generateAcessoToken() : bin2hex(random_bytes(32));

    $stmt = $pdo->prepare("INSERT INTO faturas (cliente_id, fatura_recorrente_id, numero, descricao, valor, valor_final, data_emissao, data_vencimento, status, acesso_token, api_pagamento) VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?, 'pendente', ?, ?)");
    $stmt->execute([$rec['cliente_id'], $rec['id'], $numero, $rec['descricao'], $rec['valor'], $rec['valor'], $proximaVenc, $acessoToken, getApiAtiva()]);
    $faturaId = $pdo->lastInsertId();

    $stmtFat = $pdo->prepare("SELECT f.*, c.nome_razao, c.email, c.celular, c.telefone, c.cpf_cnpj FROM faturas f JOIN clientes c ON f.cliente_id = c.id WHERE f.id = ?");
    $stmtFat->execute([$faturaId]);
    $faturaCompleta = $stmtFat->fetch();

    if ($faturaCompleta && !empty($faturaCompleta['email'])) {
        $faturaCompleta['pix_copia_cola'] = $faturaCompleta['pix_copia_cola'] ?? '';
        $faturaCompleta['pix_qrcode'] = $faturaCompleta['pix_qrcode'] ?? '';
        $faturaCompleta['link_pagamento'] = $faturaCompleta['link_pagamento'] ?? '';
        $resultado = criarPagamento($faturaCompleta['descricao'], $faturaCompleta['valor_final'], $faturaCompleta['email'], $faturaCompleta['nome_razao']);
        if (isset($resultado['sucesso']) && $resultado['sucesso']) {
            $qr = $resultado['qr_code_copia_cola'] ?? '';
            $pixQr = $resultado['qr_code'] ?? '';
            $link = $resultado['link_pagamento'] ?? '';
            $apiAtiva = getApiAtiva();
            if ($apiAtiva === 'inter' || $apiAtiva === 'bb') {
                $pdo->prepare("UPDATE faturas SET pix_qrcode = ?, pix_copia_cola = ?, link_pagamento = ?, mp_payment_id = ?, inter_codigo_solicitacao = ? WHERE id = ?")->execute([$pixQr, $qr, $link, null, $resultado['payment_id'], $faturaId]);
            } else {
                $pdo->prepare("UPDATE faturas SET pix_qrcode = ?, pix_copia_cola = ?, link_pagamento = ?, mp_payment_id = ? WHERE id = ?")->execute([$pixQr, $qr, $link, $resultado['payment_id'] ?? '', $faturaId]);
            }
            $log[] = "[gerada_pagamento] {$numero} -> {$link}";
        }
    }

    $log[] = "[gerada_auto] {$numero} -> {$proximaVenc} (freq {$rec['frequencia']})";
}

$smtpHost = getConfig('smtp_host', '');
$smtpPort = getConfig('smtp_port', '587');
$smtpUser = getConfig('smtp_usuario', '');
$smtpPass = getConfig('smtp_senha', '');
$smtpFrom = getConfig('smtp_from_email', '');
$smtpNome = getConfig('smtp_from_nome', 'Sistema de Cobranca');
$smtpSsl  = getConfig('smtp_ssl', 'tls');

if (!$naJanela) {
    file_put_contents(__DIR__ . '/cron_log.txt', date('Y-m-d H:i:s') . " - Envio de emails fora da janela ({$envioHora}). " . implode(" | ", $log) . "\n", FILE_APPEND);
    die("CRON executado (fora da janela de envio): " . count($log) . " acoes\n");
}

if (empty($smtpHost) || empty($smtpUser) || empty($smtpFrom)) { die("SMTP nao configurado"); }

$regua1 = (getConfig('regua_1_enviar_geracao', '0') === '1');
$regua2 = intval(getConfig('regua_2_dias_antes', '0'));
$regua3 = intval(getConfig('regua_3_dias_antes', '0'));
$regua4 = (getConfig('regua_4_no_vencimento', '0') === '1');
$regua5 = intval(getConfig('regua_5_dias_depois', '0'));

function buscarFaturas($pdo, $statuses) {
    $ph = implode(',', array_fill(0, count($statuses), '?'));
    $stmt = $pdo->prepare("SELECT f.*, c.nome_razao, c.email, c.celular, c.telefone, c.cpf_cnpj FROM faturas f JOIN clientes c ON f.cliente_id = c.id WHERE f.status IN ($ph) AND c.email IS NOT NULL AND c.email != ''");
    $stmt->execute($statuses);
    return $stmt->fetchAll();
}

function enviarEAtualizar($pdo, $fat, $tipo, $assunto, $html, $txt, $s, &$log) {
    $anexoPdf = '';
    if (!empty($fat['pix_copia_cola']) && !in_array($fat['status'] ?? '', ['pago', 'cancelado'])) {
        if (!function_exists('gerarPixPdfFatura')) {
            require_once __DIR__ . '/../config/pix_pdf.php';
        }
        try {
            $anexoPdf = gerarPixPdfFatura($fat);
        } catch (Throwable $e) {
            $anexoPdf = '';
        }
    }
    if ($anexoPdf && is_file($anexoPdf)) {
        $enviado = enviarEmailComAnexo($s['host'], $s['port'], $s['user'], $s['pass'], $s['from'], $s['nome'], $s['ssl'], $fat['email'], $fat['nome_razao'], $assunto, $html, $txt, $anexoPdf, 'Fatura_' . $fat['numero'] . '.pdf');
        @unlink($anexoPdf);
    } else {
        $enviado = enviarEmail($s['host'], $s['port'], $s['user'], $s['pass'], $s['from'], $s['nome'], $s['ssl'], $fat['email'], $fat['nome_razao'], $assunto, $html, $txt);
    }
    if ($enviado) {
        $stmt = $pdo->prepare("UPDATE faturas SET ultimo_envio = CURDATE(), ultimo_envio_tipo = ? WHERE id = ?");
        $stmt->execute([$tipo, $fat['id']]);
        $log[] = "[$tipo] {$fat['numero']} -> {$fat['email']}";
    } else {
        $log[] = "[erro {$tipo}] {$fat['numero']} -> {$fat['email']}";
    }
}

function montarAssunto($antes, $fat) {
    $chave = $antes ? 'template_email_assunto_antes' : 'template_email_assunto_depois';
    $assunto = getConfig($chave, 'Fatura ' . $fat['numero']);
    return str_replace(['{numero}', '{data_vencimento}', '{valor}'], [
        $fat['numero'], date('d/m/Y', strtotime($fat['data_vencimento'])), number_format($fat['valor_final'], 2, ',', '.')
    ], $assunto);
}

$s = ['host'=>$smtpHost,'port'=>$smtpPort,'user'=>$smtpUser,'pass'=>$smtpPass,'from'=>$smtpFrom,'nome'=>$smtpNome,'ssl'=>$smtpSsl];
$faturas = buscarFaturas($pdo, ['pendente', 'vencido', 'atrasado']);

$dataAlvo2 = ($regua2 > 0) ? date('Y-m-d', strtotime("+{$regua2} days")) : null;
$dataAlvo3 = ($regua3 > 0) ? date('Y-m-d', strtotime("+{$regua3} days")) : null;
$dataAlvo5 = ($regua5 > 0) ? date('Y-m-d', strtotime("-{$regua5} days")) : null;

foreach ($faturas as &$fat) {
    $tipoEnviado = null;

    if ($regua1 && $fat['ultimo_envio_tipo'] === null) {
        $tipoEnviado = 'geracao';
    } elseif ($regua2 > 0 && $fat['data_vencimento'] === $dataAlvo2 && !in_array($fat['ultimo_envio_tipo'], ['lembrete1','lembrete2','vencimento','atraso'])) {
        $tipoEnviado = 'lembrete1';
    } elseif ($regua3 > 0 && $fat['data_vencimento'] === $dataAlvo3 && !in_array($fat['ultimo_envio_tipo'], ['lembrete1','lembrete2','vencimento','atraso'])) {
        $tipoEnviado = 'lembrete2';
    } elseif ($regua4 && $fat['data_vencimento'] === $hoje && !in_array($fat['ultimo_envio_tipo'], ['vencimento','atraso'])) {
        $tipoEnviado = 'vencimento';
    } elseif ($regua5 > 0 && $fat['data_vencimento'] === $dataAlvo5 && $fat['ultimo_envio_tipo'] !== 'atraso') {
        $tipoEnviado = 'atraso';
    }

    if ($tipoEnviado !== null) {
        if (empty($fat['pix_copia_cola']) && ($fat['status'] ?? '') !== 'pago' && !empty($fat['email'])) {
            $resultadoPix = criarPagamento($fat['descricao'], $fat['valor_final'], $fat['email'], $fat['nome_razao']);
            if (isset($resultadoPix['sucesso']) && $resultadoPix['sucesso']) {
                $fat['pix_qrcode'] = $resultadoPix['qr_code'] ?? '';
                $fat['pix_copia_cola'] = $resultadoPix['qr_code_copia_cola'] ?? '';
                $fat['link_pagamento'] = $resultadoPix['link_pagamento'] ?? '';
                $apiPag = getApiAtiva();
                if ($apiPag === 'inter' || $apiPag === 'bb') {
                    $pdo->prepare("UPDATE faturas SET pix_qrcode = ?, pix_copia_cola = ?, link_pagamento = ?, mp_payment_id = ?, inter_codigo_solicitacao = ? WHERE id = ?")
                        ->execute([$fat['pix_qrcode'], $fat['pix_copia_cola'], $fat['link_pagamento'], null, $resultadoPix['payment_id'], $fat['id']]);
                } else {
                    $pdo->prepare("UPDATE faturas SET pix_qrcode = ?, pix_copia_cola = ?, link_pagamento = ?, mp_payment_id = ? WHERE id = ?")
                        ->execute([$fat['pix_qrcode'], $fat['pix_copia_cola'], $fat['link_pagamento'], $resultadoPix['payment_id'] ?? '', $fat['id']]);
                }
            }
        }
        $antes = ($tipoEnviado !== 'atraso');
        $diasRef = 0;
        if ($tipoEnviado === 'lembrete1') $diasRef = $regua2;
        elseif ($tipoEnviado === 'lembrete2') $diasRef = $regua3;
        elseif ($tipoEnviado === 'atraso') $diasRef = $regua5;

        enviarEAtualizar($pdo, $fat, $tipoEnviado, montarAssunto($antes, $fat), montarMensagemHtml($fat, $antes ? 'antes' : 'depois', $diasRef), montarMensagemTxt($fat, $antes ? 'antes' : 'depois', $diasRef), $s, $log);
        $fat['ultimo_envio_tipo'] = $tipoEnviado;

        if ($tipoEnviado === 'geracao' && enviarWhatsAppFatura($fat, 'antes', $diasRef)) {
            $log[] = "[whatsapp_{$tipoEnviado}] {$fat['numero']} -> " . ($fat['celular'] ?? $fat['telefone']);
        }
        if (in_array($tipoEnviado, ['lembrete1','lembrete2']) && enviarWhatsAppFatura($fat, 'antes', $diasRef)) {
            $log[] = "[whatsapp_{$tipoEnviado}] {$fat['numero']} -> " . ($fat['celular'] ?? $fat['telefone']);
        }
        if (in_array($tipoEnviado, ['vencimento','atraso']) && enviarWhatsAppFatura($fat, 'depois', $diasRef)) {
            $log[] = "[whatsapp_{$tipoEnviado}] {$fat['numero']} -> " . ($fat['celular'] ?? $fat['telefone']);
        }
    }
}
unset($fat);

file_put_contents(__DIR__ . '/cron_log.txt', date('Y-m-d H:i:s') . " - " . implode(" | ", $log) . "\n", FILE_APPEND);
echo "CRON executado: " . count($log) . " acoes\n";
