<?php
// Liga o cache de configuracoes desta requisicao (ver getConfig em
// config/settings.php). Precisa vir antes de qualquer require.
// O cron le ~16 configuracoes por fatura e roda a cada minuto, entao sem cache
// o volume de consultas cresce junto com a base de clientes. O painel nao liga
// o cache, porque as telas gravam a configuracao e reexibem na mesma request.
define('CONFIG_CACHE_ATIVO', true);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/recorrencia.php';
require_once __DIR__ . '/../includes/motor_recorrencia.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/email_helpers.php';
require_once __DIR__ . '/../config/mercadopago.php';
require_once __DIR__ . '/../api/whatsapp_send.php';

$isHttp = (php_sapi_name() !== 'cli');
if ($isHttp) {
    header('Content-Type: text/plain; charset=utf-8');
    ignore_user_abort(true);
    // Limite folgado de proposito: o job consulta o Mercado Pago e o Inter uma
    // fatura por vez. Com poucas dezenas de faturas leva menos de 1 minuto, mas
    // a carga cresce junto com a base de clientes (cada consulta e uma chamada
    // HTTP externa). 120s cortava o job no meio em carteiras maiores.
    set_time_limit(3600);
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

// Ciclos que o admin ja excluiu. Carregado uma vez por execucao e usado na
// geracao (nao recriar) e na regua de cobranca (nao reenviar).
$faturasExcluidas = carregarFaturasExcluidas($pdo);

$faturasPendentes = $pdo->prepare("SELECT f.*, c.email, c.email2, c.celular, c.telefone, c.nome_razao, c.cpf_cnpj FROM faturas f JOIN clientes c ON f.cliente_id = c.id WHERE f.status IN ('pendente','vencido','atrasado') AND (f.mp_payment_id IS NOT NULL AND f.mp_payment_id != '' OR f.inter_codigo_solicitacao IS NOT NULL AND f.inter_codigo_solicitacao != '')");
$faturasPendentes->execute();
$pendentes = $faturasPendentes->fetchAll();

$apiAtivaGlobal = getApiAtiva();

// Define o contexto de tenant (admin) a partir do admin da fatura.
// Zera o contexto quando o adminId vem vazio. Sem isso, uma fatura sem
// admin_id herdaria o SMTP, o horario e os templates do admin processado na
// fatura anterior, e sairia com o e-mail em nome do tenant errado.
function cronTenantContext($adminId) {
    if (function_exists('getTenantAdminId')) {
        $_SESSION['tenant_admin_id'] = !empty($adminId) ? (int)$adminId : 0;
    }
    return $adminId ?: 0;
}

foreach ($pendentes as $fat) {
    $novoStatus = null;
    $dataPagamento = null;
    $regenerarPix = false;
    cronTenantContext($fat['admin_id'] ?? 0);
    $apiAtiva = $fat['api_pagamento'] ?: $apiAtivaGlobal;

    if ($apiAtiva === 'inter' && !empty($fat['inter_codigo_solicitacao'])) {
        // Cobranca expirada/cancelada de fatura vencida: nao ha mais nada a
        // receber do Inter e o status da fatura ja foi ajustado na primeira
        // consulta. Sem esta trava o cron chamava a API a cada minuto, para
        // sempre, sem alterar nada.
        if (!interConsultaVale($fat)) {
            $log[] = "[inter_adiada] {$fat['numero']} situacao " . strtoupper($fat['inter_situacao']) . " - ultima consulta ha mais de 6h";
            continue;
        }
        $detalhe = consultarCobrancaInter($fat['inter_codigo_solicitacao']);
        if ($detalhe && !isset($detalhe['erro'])) {
            $situacao = strtoupper($detalhe['situacao'] ?? $detalhe['cobranca']['situacao'] ?? '');
            if ($situacao !== '') {
                registrarSituacaoInter($pdo, $fat['id'], $situacao);
            }
            if (in_array($situacao, ['PAGA','RECEBIDO'])) { $novoStatus = 'pago'; $dataPagamento = date('Y-m-d'); }
            elseif ($situacao === 'VENCIDA') { $novoStatus = 'atrasado'; }
            elseif (interSituacaoTerminal($situacao)) {
                // A cobrança morreu no Inter. Enquanto a fatura não venceu o
                // cliente ainda pode pagar, então entra um PIX novo: o Inter
                // guarda o PIX dentro da cobrança e não devolve código novo
                // sozinho. Vencida, a fatura segue aberta como 'atrasado'.
                if (($fat['data_vencimento'] ?? '') >= date('Y-m-d')) {
                    $regenerarPix = true;
                } else {
                    $novoStatus = statusFaturaSemCancelar($fat['data_vencimento'] ?? '');
                }
            }
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
                elseif ($situacao === 'CANCELED') { $novoStatus = statusFaturaSemCancelar($fat['data_vencimento'] ?? ''); break; }
            }
        }
    } elseif ($apiAtiva === 'mercadopago' && !empty($fat['mp_payment_id'])) {
        $pagamento = consultarPagamento($fat['mp_payment_id']);
        if ($pagamento) {
            $statusMP = $pagamento['status'] ?? '';
            if ($statusMP === 'approved') { $novoStatus = 'pago'; $dataPagamento = date('Y-m-d'); }
            // Fatura nunca é cancelada sozinha: expiração/recusa/estorno do PIX
            // mantém a fatura ativa ('atrasado' se vencida, senão 'pendente');
            // enquanto não vencer, um novo PIX é gerado a cada execução do cron.
            elseif ($statusMP === 'refunded') { $novoStatus = statusFaturaSemCancelar($fat['data_vencimento'] ?? ''); }
            elseif (in_array($statusMP, ['cancelled', 'rejected'])) {
                if (($fat['data_vencimento'] ?? '') >= date('Y-m-d')) {
                    $regenerarPix = true;
                } else {
                    $novoStatus = 'atrasado';
                }
            }
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

        // PIX do gateway expirado/recusado: gera novo código de pagamento
        if (!empty($regenerarPix)) {
            if (regenerarPixFatura($pdo, $fat)) {
                $log[] = "[pix_regerado] {$fat['numero']} (" . strtoupper($apiAtiva) . " expirado)";
            } else {
                $log[] = "[pix_regerado_erro] {$fat['numero']}";
            }
        }
}

// Baixa automática de pagamentos concluída acima. A geração de faturas
// recorrentes (bloco abaixo) roda SEMPRE, independente do flag global de envio.
$cronAtivoGlobal = getConfigGlobal('cron_envio_ativo', '');

// Nao existe janela global de envio. A janela de cada admin (60 minutos a
// partir do seu envio_hora) e verificada dentro do laco da regua, mais abaixo,
// depois que cronTenantContext() ja fixou o admin da fatura. Antes havia um
// $naJanela aqui calculado sem contexto de tenant, que usava a hora global e
// nunca era lido.

// =====================================================
// GERAÇÃO AUTOMÁTICA DE FATURAS RECORRENTES (novo motor)
// =====================================================
$resultadoMotor = motorProcessar($pdo, [
    'origem' => 'cron_envio',
    'gerar_pagamento' => function ($faturaCompleta) use ($pdo, &$log) {
        if (!$faturaCompleta || empty($faturaCompleta['email'])) return null;
        $resultado = criarPagamento($faturaCompleta['descricao'], $faturaCompleta['valor_final'],
            $faturaCompleta['email'], $faturaCompleta['nome_razao'], $faturaCompleta['data_vencimento'] ?? null);
        if (isset($resultado['sucesso']) && $resultado['sucesso']) {
            $qr = $resultado['qr_code_copia_cola'] ?? '';
            $pixQr = $resultado['qr_code'] ?? '';
            $link = $resultado['link_pagamento'] ?? '';
            $apiAtiva = $resultado['api'] ?? getApiAtiva();
            $fid = (int)$faturaCompleta['id'];
            if ($apiAtiva === 'inter' || $apiAtiva === 'bb') {
                $pdo->prepare("UPDATE faturas SET pix_qrcode=?, pix_copia_cola=?, link_pagamento=?, mp_payment_id=?, inter_codigo_solicitacao=?, api_pagamento=? WHERE id=?")
                    ->execute([$pixQr, $qr, $link, null, $resultado['payment_id'] ?? null, $apiAtiva, $fid]);
            } else {
                $pdo->prepare("UPDATE faturas SET pix_qrcode=?, pix_copia_cola=?, link_pagamento=?, mp_payment_id=?, api_pagamento=? WHERE id=?")
                    ->execute([$pixQr, $qr, $link, $resultado['payment_id'] ?? '', $apiAtiva, $fid]);
            }
            $log[] = "[gerada_pagamento] {$faturaCompleta['numero']} -> {$link}";
        }
        return $resultado;
    },
]);
$log[] = "[motor] exec " . ($resultadoMotor['execucao_id'] ?? '-') . " emitidas=" . ($resultadoMotor['emitidas'] ?? 0) . " erros=" . ($resultadoMotor['erros'] ?? 0) . " res=" . ($resultadoMotor['resultado'] ?? '-');

// A regua de cobranca (e-mails/WhatsApp) abaixo so roda com o envio automatico
// ligado. O global (admin_id IS NULL) e apenas o PADRAO de cada admin: se algum
// admin ligou o envio para si, a regua roda para ele mesmo com o global
// desligado. Antes este if olhava so o global e abortava TUDO com die(), o que
// impedia um admin que ligou o envio no proprio painel de receber e-mail.
// As recorrencias acima ja foram geradas em qualquer caso.
$envioLigadoEmAlgumNivel = ($cronAtivoGlobal === '1');
if (!$envioLigadoEmAlgumNivel) {
    $stmtAtivos = $pdo->prepare("SELECT COUNT(*) FROM configuracoes WHERE chave = 'cron_envio_ativo' AND valor = '1'");
    $stmtAtivos->execute();
    $envioLigadoEmAlgumNivel = ((int)$stmtAtivos->fetchColumn() > 0);
}
if (!$envioLigadoEmAlgumNivel && $cronAtivoGlobal !== '') {
    file_put_contents(__DIR__ . '/cron_log.txt', date('Y-m-d H:i:s') . " - " . implode(" | ", $log) . "\n", FILE_APPEND);
    die("CRON executado (baixa/recorrencia): " . count($log) . " acoes\n");
}

$regua1 = (getConfig('regua_1_enviar_geracao', '0') === '1');
$regua2 = intval(getConfig('regua_2_dias_antes', '0'));
$regua3 = intval(getConfig('regua_3_dias_antes', '0'));
$regua4 = (getConfig('regua_4_no_vencimento', '0') === '1');
$regua5 = intval(getConfig('regua_5_dias_depois', '0'));

function buscarFaturas($pdo, $statuses) {
    $ph = implode(',', array_fill(0, count($statuses), '?'));
    // admin_id IS NOT NULL: fatura sem admin nao tem SMTP, horario nem templates
    // proprios. Antes ela entrava na fila e usava a configuracao do admin
    // processado na volta anterior do laco.
    $stmt = $pdo->prepare("SELECT f.*, c.nome_razao, c.email, c.email2, c.celular, c.telefone, c.cpf_cnpj FROM faturas f JOIN clientes c ON f.cliente_id = c.id WHERE f.admin_id IS NOT NULL AND f.admin_id > 0 AND f.status IN ($ph) AND (c.email IS NOT NULL AND c.email != '' OR c.email2 IS NOT NULL AND c.email2 != '' OR c.celular IS NOT NULL AND c.celular != '' OR c.telefone IS NOT NULL AND c.telefone != '')");
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
    return $enviado;
}

function montarAssunto($antes, $fat) {
    $chave = $antes ? 'template_email_assunto_antes' : 'template_email_assunto_depois';
    $assunto = getConfig($chave, 'Fatura ' . $fat['numero']);
    return str_replace(['{numero}', '{data_vencimento}', '{valor}'], [
        $fat['numero'], date('d/m/Y', strtotime($fat['data_vencimento'])), number_format($fat['valor_final'], 2, ',', '.')
    ], $assunto);
}

$faturas = buscarFaturas($pdo, ['pendente', 'vencido', 'atrasado']);

$dataAlvo2 = ($regua2 > 0) ? date('Y-m-d', strtotime("+{$regua2} days")) : null;
$dataAlvo3 = ($regua3 > 0) ? date('Y-m-d', strtotime("+{$regua3} days")) : null;
$dataAlvo5 = ($regua5 > 0) ? date('Y-m-d', strtotime("-{$regua5} days")) : null;

foreach ($faturas as &$fat) {
    $tipoEnviado = null;

    // Contexto de tenant (admin) da fatura — configurações específicas do admin
    cronTenantContext($fat['admin_id'] ?? 0);

    // Envio automático ativo para este admin? (flag por tenant; cai para a global)
    if (getConfig('cron_envio_ativo', $cronAtivoGlobal ?: '0') !== '1') continue;

    // Fatura de um ciclo excluido pelo admin: nao cobra. A fatura ja foi
    // removida de faturas, mas se ela ainda estiver na lista (lida antes da
    // exclusao, ou reimportada) a regua nao pode disparar e-mail/WhatsApp.
    if (!empty($fat['fatura_recorrente_id']) && cicloFaturaExcluida($faturasExcluidas, $fat['fatura_recorrente_id'], $fat['data_vencimento'])) {
        continue;
    }

    $smtpHost = getConfig('smtp_host', '');
    $smtpPort = getConfig('smtp_port', '587');
    $smtpUser = getConfig('smtp_usuario', '');
    $smtpPass = getConfig('smtp_senha', '');
    $smtpFrom = getConfig('smtp_from_email', '');
    $smtpNome = getConfig('smtp_from_nome', 'Sistema de Cobranca');
    $smtpSsl  = getConfig('smtp_ssl', 'tls');
    $smtpOk = !(empty($smtpHost) || empty($smtpUser) || empty($smtpFrom));
    $s = ['host'=>$smtpHost,'port'=>$smtpPort,'user'=>$smtpUser,'pass'=>$smtpPass,'from'=>$smtpFrom,'nome'=>$smtpNome,'ssl'=>$smtpSsl];

    $regua1 = (getConfig('regua_1_enviar_geracao', '0') === '1');
    $regua2 = intval(getConfig('regua_2_dias_antes', '0'));
    $regua3 = intval(getConfig('regua_3_dias_antes', '0'));
    $regua4 = (getConfig('regua_4_no_vencimento', '0') === '1');
    $regua5 = intval(getConfig('regua_5_dias_depois', '0'));
    $dataAlvo2 = ($regua2 > 0) ? date('Y-m-d', strtotime("+{$regua2} days")) : null;
    $dataAlvo3 = ($regua3 > 0) ? date('Y-m-d', strtotime("+{$regua3} days")) : null;
    $dataAlvo5 = ($regua5 > 0) ? date('Y-m-d', strtotime("-{$regua5} days")) : null;

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

    if ($tipoEnviado === null) continue;

    // Janela de envio por admin (60 min a partir do envio_hora do admin)
    $envioHoraAdmin = getConfig('envio_hora', '08:00');
    $tsAlvoAdmin = strtotime(date('Y-m-d') . ' ' . $envioHoraAdmin . ':00');
    if ($tsAlvoAdmin === false || time() < $tsAlvoAdmin || time() >= $tsAlvoAdmin + 3600) continue;

    if (empty($fat['pix_copia_cola']) && ($fat['status'] ?? '') !== 'pago' && !empty($fat['email'])) {
            $resultadoPix = criarPagamento($fat['descricao'], $fat['valor_final'], $fat['email'], $fat['nome_razao'], $fat['data_vencimento'] ?? null);
            if (isset($resultadoPix['sucesso']) && $resultadoPix['sucesso']) {
                $fat['pix_qrcode'] = $resultadoPix['qr_code'] ?? '';
                $fat['pix_copia_cola'] = $resultadoPix['qr_code_copia_cola'] ?? '';
                $fat['link_pagamento'] = $resultadoPix['link_pagamento'] ?? '';
                $apiPag = $resultadoPix['api'] ?? getApiAtiva();
                if ($apiPag === 'inter' || $apiPag === 'bb') {
                    $pdo->prepare("UPDATE faturas SET pix_qrcode = ?, pix_copia_cola = ?, link_pagamento = ?, mp_payment_id = ?, inter_codigo_solicitacao = ?, api_pagamento = ? WHERE id = ?")
                        ->execute([$fat['pix_qrcode'], $fat['pix_copia_cola'], $fat['link_pagamento'], null, $resultadoPix['payment_id'], $apiPag, $fat['id']]);
                } else {
                    $pdo->prepare("UPDATE faturas SET pix_qrcode = ?, pix_copia_cola = ?, link_pagamento = ?, mp_payment_id = ?, api_pagamento = ? WHERE id = ?")
                        ->execute([$fat['pix_qrcode'], $fat['pix_copia_cola'], $fat['link_pagamento'], $resultadoPix['payment_id'] ?? '', $apiPag, $fat['id']]);
                }
            }
        }

        $antes = ($tipoEnviado !== 'atraso');
        $diasRef = 0;
        if ($tipoEnviado === 'lembrete1') $diasRef = $regua2;
        elseif ($tipoEnviado === 'lembrete2') $diasRef = $regua3;
        elseif ($tipoEnviado === 'atraso') $diasRef = $regua5;

        // E-mail (apenas se o SMTP do admin estiver configurado e a fatura tiver e-mail)
        $emailEnviado = false;
        if ($smtpOk && !empty($fat['email'])) {
            $emailEnviado = enviarEAtualizar($pdo, $fat, $tipoEnviado, montarAssunto($antes, $fat), montarMensagemHtml($fat, $antes ? 'antes' : 'depois', $diasRef), montarMensagemTxt($fat, $antes ? 'antes' : 'depois', $diasRef), $s, $log);
        }

        // WhatsApp — enviado sempre que a fatura estiver na régua, independente de SMTP
        $whatsEnviado = false;
        if ($tipoEnviado === 'geracao' && enviarWhatsAppFatura($fat, 'antes', $diasRef)) {
            $whatsEnviado = true;
            $log[] = "[whatsapp_{$tipoEnviado}] {$fat['numero']} -> " . ($fat['celular'] ?? $fat['telefone']);
        }
        if (!$whatsEnviado && in_array($tipoEnviado, ['lembrete1','lembrete2']) && enviarWhatsAppFatura($fat, 'antes', $diasRef)) {
            $whatsEnviado = true;
            $log[] = "[whatsapp_{$tipoEnviado}] {$fat['numero']} -> " . ($fat['celular'] ?? $fat['telefone']);
        }
        if (!$whatsEnviado && in_array($tipoEnviado, ['vencimento','atraso']) && enviarWhatsAppFatura($fat, 'depois', $diasRef)) {
            $whatsEnviado = true;
            $log[] = "[whatsapp_{$tipoEnviado}] {$fat['numero']} -> " . ($fat['celular'] ?? $fat['telefone']);
        }

        // Sem SMTP (ou sem e-mail): marca o envio quando o WhatsApp foi entregue,
        // evitando disparos repetidos a cada execução do cron.
        if ($whatsEnviado && !$emailEnviado) {
            $stmtTipo = $pdo->prepare("UPDATE faturas SET ultimo_envio = CURDATE(), ultimo_envio_tipo = ? WHERE id = ?");
            $stmtTipo->execute([$tipoEnviado, $fat['id']]);
        }
}
unset($fat);

file_put_contents(__DIR__ . '/cron_log.txt', date('Y-m-d H:i:s') . " - " . implode(" | ", $log) . "\n", FILE_APPEND);
echo "CRON executado: " . count($log) . " acoes\n";
