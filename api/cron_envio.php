<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/email_helpers.php';
require_once __DIR__ . '/../config/mercadopago.php';
require_once __DIR__ . '/../api/whatsapp_send.php';

$pdo = getConnection();
if (!$pdo) { die("Erro de conexao"); }

$log = [];
$hoje = date('Y-m-d');

$faturasPendentes = $pdo->prepare("SELECT * FROM faturas WHERE status IN ('pendente','vencido','atrasado') AND (mp_payment_id IS NOT NULL AND mp_payment_id != '' OR inter_codigo_solicitacao IS NOT NULL AND inter_codigo_solicitacao != '')");
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
            }
        $log[] = "[baixa] {$fat['numero']} -> {$novoStatus}";
    }
}

$cronAtivo = getConfig('cron_envio_ativo', '0');
if ($cronAtivo !== '1') {
    file_put_contents(__DIR__ . '/cron_log.txt', date('Y-m-d H:i:s') . " - " . implode(" | ", $log) . "\n", FILE_APPEND);
    die("CRON executado (baixa): " . count($log) . " acoes\n");
}

$smtpHost = getConfig('smtp_host', '');
$smtpPort = getConfig('smtp_port', '587');
$smtpUser = getConfig('smtp_usuario', '');
$smtpPass = getConfig('smtp_senha', '');
$smtpFrom = getConfig('smtp_from_email', '');
$smtpNome = getConfig('smtp_from_nome', 'Sistema de Cobranca');
$smtpSsl  = getConfig('smtp_ssl', 'tls');

if (empty($smtpHost) || empty($smtpUser) || empty($smtpFrom)) { die("SMTP nao configurado"); }

$regua1 = (getConfig('regua_1_enviar_geracao', '0') === '1');
$regua2 = intval(getConfig('regua_2_dias_antes', '0'));
$regua3 = intval(getConfig('regua_3_dias_antes', '0'));
$regua4 = (getConfig('regua_4_no_vencimento', '0') === '1');
$regua5 = intval(getConfig('regua_5_dias_depois', '0'));

function buscarFaturas($pdo, $statuses) {
    $ph = implode(',', array_fill(0, count($statuses), '?'));
    $stmt = $pdo->prepare("SELECT f.*, c.nome_razao, c.email, c.celular, c.telefone FROM faturas f JOIN clientes c ON f.cliente_id = c.id WHERE f.status IN ($ph) AND c.email IS NOT NULL AND c.email != ''");
    $stmt->execute($statuses);
    return $stmt->fetchAll();
}

function enviarEAtualizar($pdo, $fat, $tipo, $assunto, $html, $txt, $s, &$log) {
    $enviado = enviarEmail($s['host'], $s['port'], $s['user'], $s['pass'], $s['from'], $s['nome'], $s['ssl'], $fat['email'], $fat['nome_razao'], $assunto, $html, $txt);
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
        $antes = ($tipoEnviado !== 'atraso');
        $diasRef = 0;
        if ($tipoEnviado === 'lembrete1') $diasRef = $regua2;
        elseif ($tipoEnviado === 'lembrete2') $diasRef = $regua3;
        elseif ($tipoEnviado === 'atraso') $diasRef = $regua5;

        enviarEAtualizar($pdo, $fat, $tipoEnviado, montarAssunto($antes, $fat), montarMensagemHtml($fat, $antes ? 'antes' : 'depois', $diasRef), montarMensagemTxt($fat, $antes ? 'antes' : 'depois', $diasRef), $s, $log);
        $fat['ultimo_envio_tipo'] = $tipoEnviado;

        if ($tipoEnviado === 'lembrete1' && enviarWhatsAppFatura($fat, 'antes', $regua2)) {
            $log[] = "[whatsapp_lembrete1] {$fat['numero']} -> " . ($fat['celular'] ?? $fat['telefone']);
        }
    }
}
unset($fat);

file_put_contents(__DIR__ . '/cron_log.txt', date('Y-m-d H:i:s') . " - " . implode(" | ", $log) . "\n", FILE_APPEND);
echo "CRON executado: " . count($log) . " acoes\n";
