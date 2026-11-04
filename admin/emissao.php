<?php
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/recorrencia.php';
requireAdmin();
requirePlanoAtivo();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/mercadopago.php';
require_once __DIR__ . '/../config/email_helpers.php';
require_once __DIR__ . '/../api/whatsapp_send.php';

$pdo = getConnection();
$adminIdE = (int)$_SESSION['admin_id'];
$mensagem = '';
$tipo = '';

// Ações na fatura recorrente
if (isset($_GET['pago'])) {
    $id = intval($_GET['pago']);
    $stFat = $pdo->prepare("SELECT f.id FROM faturas f WHERE f.fatura_recorrente_id = ? AND f.admin_id = ? AND f.status != 'pago' ORDER BY f.data_vencimento DESC LIMIT 1");
    $stFat->execute([$id, $adminIdE]);
    $fatPagaId = (int)$stFat->fetchColumn();
    $stmt = $pdo->prepare("UPDATE faturas SET status = 'pago', data_pagamento = CURDATE() WHERE fatura_recorrente_id = ? AND admin_id = ? AND status != 'pago' ORDER BY data_vencimento DESC LIMIT 1");
    $stmt->execute([$id, $adminIdE]);
    if ($fatPagaId > 0 && $stmt->rowCount() > 0) {
        $stFat2 = $pdo->prepare("SELECT f.*, c.nome_razao, c.email, c.email2, c.cpf_cnpj, c.celular, c.telefone FROM faturas f JOIN clientes c ON f.cliente_id = c.id WHERE f.id = ? AND f.admin_id = ?");
        $stFat2->execute([$fatPagaId, $adminIdE]);
        $fatPaga = $stFat2->fetch();
        if ($fatPaga && !empty($fatPaga['email'])) {
            $fatPaga['data_pagamento'] = date('Y-m-d');
            if (function_exists('enviarEmailPagamento')) {
                enviarEmailPagamento($fatPaga);
            }
        }
    }
    header('Location: emissao.php?msg=pago');
    exit;
}
if (isset($_GET['cancelar'])) {
    $id = intval($_GET['cancelar']);

    $stFats = $pdo->prepare("SELECT * FROM faturas WHERE fatura_recorrente_id = ? AND admin_id = ? AND status IN ('pendente','vencido','atrasado')");
    $stFats->execute([$id, $adminIdE]);
    while ($fat = $stFats->fetch()) {
        cancelarCobrancaFatura($fat);
    }

    $stmt = $pdo->prepare("UPDATE faturas_recorrentes SET status = 'cancelado' WHERE id = ? AND admin_id = ?");
    $stmt->execute([$id, $adminIdE]);
    $stmt = $pdo->prepare("UPDATE faturas SET status = 'cancelado' WHERE fatura_recorrente_id = ? AND admin_id = ? AND status IN ('pendente','vencido','atrasado')");
    $stmt->execute([$id, $adminIdE]);
    header('Location: emissao.php?msg=cancelado');
    exit;
}
if (isset($_GET['excluir'])) {
    $id = intval($_GET['excluir']);
    $stmt = $pdo->prepare("SELECT * FROM faturas WHERE fatura_recorrente_id = ? AND admin_id = ?");
    $stmt->execute([$id, $adminIdE]);
    $faturasRec = $stmt->fetchAll();
    foreach ($faturasRec as $fat) {
        cancelarCobrancaFatura($fat);
    }
    excluirFaturasComLapide($pdo, $faturasRec, 'excluir_recorrencia');
    $stmt = $pdo->prepare("DELETE FROM faturas_recorrentes WHERE id = ? AND admin_id = ?");
    $stmt->execute([$id, $adminIdE]);
    header('Location: emissao.php?msg=excluido');
    exit;
}
if (isset($_GET['enviar'])) {
    $frId = intval($_GET['enviar']);
    $stmt = $pdo->prepare("
        SELECT f.id, f.admin_id, f.numero, f.descricao, f.valor_final, f.data_vencimento, f.link_pagamento,
               f.pix_copia_cola, f.pix_qrcode,
               c.nome_razao, c.email, c.email2, c.cpf_cnpj
        FROM faturas f
        JOIN clientes c ON f.cliente_id = c.id
        WHERE f.fatura_recorrente_id = ? AND f.admin_id = ? AND f.status IN ('pendente','vencido','atrasado')
        ORDER BY f.data_vencimento DESC LIMIT 1
    ");
    $stmt->execute([$frId, $adminIdE]);
    $fatura = $stmt->fetch();
    if ($fatura && !empty($fatura['email'])) {
        $ok = enviarEmailFatura($fatura, 'antes');
        header('Location: emissao.php?msg=' . ($ok ? 'enviado' : 'erro_envio'));
    } else {
        header('Location: emissao.php?msg=sem_email');
    }
    exit;
}
if (isset($_GET['whatsapp'])) {
    $frId = intval($_GET['whatsapp']);
    $stmt = $pdo->prepare("
        SELECT f.id, f.admin_id, f.numero, f.descricao, f.valor_final, f.data_vencimento, f.link_pagamento,
               f.pix_copia_cola, f.pix_qrcode,
               c.nome_razao, c.email, c.email2, c.cpf_cnpj, c.celular, c.telefone
        FROM faturas f
        JOIN clientes c ON f.cliente_id = c.id
        WHERE f.fatura_recorrente_id = ? AND f.admin_id = ? AND f.status IN ('pendente','vencido','atrasado')
        ORDER BY f.data_vencimento DESC LIMIT 1
    ");
    $stmt->execute([$frId, $adminIdE]);
    $fatura = $stmt->fetch();
    if ($fatura) {
        $ok = enviarWhatsAppFatura($fatura, 'antes');
        header('Location: emissao.php?msg=' . ($ok ? 'whatsapp_enviado' : 'whatsapp_erro'));
    } else {
        header('Location: emissao.php?msg=sem_fatura');
    }
    exit;
}

// Ações em uma fatura gerada específica
if (isset($_GET['fatura_pago'])) {
    $id = intval($_GET['fatura_pago']);
    $stmt = $pdo->prepare("UPDATE faturas SET status = 'pago', data_pagamento = CURDATE() WHERE id = ? AND admin_id = ? AND status != 'pago'");
    $stmt->execute([$id, $adminIdE]);
    if ($stmt->rowCount() > 0) {
        $stFat = $pdo->prepare("SELECT f.*, c.nome_razao, c.email, c.email2, c.cpf_cnpj, c.celular, c.telefone FROM faturas f JOIN clientes c ON f.cliente_id = c.id WHERE f.id = ? AND f.admin_id = ?");
        $stFat->execute([$id, $adminIdE]);
        $fatPaga = $stFat->fetch();
        if ($fatPaga && !empty($fatPaga['email'])) {
            $fatPaga['data_pagamento'] = date('Y-m-d');
            if (function_exists('enviarEmailPagamento')) {
                enviarEmailPagamento($fatPaga);
            }
        }
    }
    header('Location: emissao.php?msg=pago');
    exit;
}
if (isset($_GET['fatura_cancelar'])) {
    $id = intval($_GET['fatura_cancelar']);
    $stFat = $pdo->prepare("SELECT * FROM faturas WHERE id = ? AND admin_id = ?");
    $stFat->execute([$id, $adminIdE]);
    $fat = $stFat->fetch();
    if ($fat) {
        if ($fat['status'] === 'pago') {
            $stmt = $pdo->prepare("UPDATE faturas SET status = 'pendente', data_pagamento = NULL WHERE id = ? AND admin_id = ?");
            $stmt->execute([$id, $adminIdE]);
            header('Location: emissao.php?msg=fatura_desmarcada');
            exit;
        } elseif (in_array($fat['status'], ['pendente', 'vencido', 'atrasado'])) {
            cancelarCobrancaFatura($fat);
            $stmt = $pdo->prepare("UPDATE faturas SET status = 'cancelado' WHERE id = ? AND admin_id = ?");
            $stmt->execute([$id, $adminIdE]);
        }
    }
    header('Location: emissao.php?msg=fatura_cancelada');
    exit;
}
if (isset($_GET['fatura_excluir'])) {
    $id = intval($_GET['fatura_excluir']);
    $stmt = $pdo->prepare("SELECT * FROM faturas WHERE id = ? AND admin_id = ?");
    $stmt->execute([$id, $adminIdE]);
    $fat = $stmt->fetch();
    if ($fat) {
        cancelarCobrancaFatura($fat);
        // A lapide impede o cron de recriar e reenviar este ciclo na proxima
        // execucao. Sem ela, apagar a fatura so durava ate o proximo cron.
        excluirFaturasComLapide($pdo, [$fat], 'excluir_fatura');
    }
    header('Location: emissao.php?msg=fatura_excluida');
    exit;
}
if (isset($_GET['fatura_enviar'])) {
    $id = intval($_GET['fatura_enviar']);
    $stmt = $pdo->prepare("
        SELECT f.id, f.admin_id, f.numero, f.descricao, f.valor_final, f.data_vencimento, f.link_pagamento,
               f.pix_copia_cola, f.pix_qrcode,
               c.nome_razao, c.email, c.email2, c.cpf_cnpj
        FROM faturas f
        JOIN clientes c ON f.cliente_id = c.id
        WHERE f.id = ? AND f.admin_id = ?
    ");
    $stmt->execute([$id, $adminIdE]);
    $fatura = $stmt->fetch();
    if ($fatura && !empty($fatura['email'])) {
        $ok = enviarEmailFatura($fatura, 'antes');
        header('Location: emissao.php?msg=' . ($ok ? 'enviado' : 'erro_envio'));
    } else {
        header('Location: emissao.php?msg=sem_email');
    }
    exit;
}
if (isset($_GET['fatura_whatsapp'])) {
    $id = intval($_GET['fatura_whatsapp']);
    $stmt = $pdo->prepare("
        SELECT f.id, f.admin_id, f.numero, f.descricao, f.valor_final, f.data_vencimento, f.link_pagamento,
               f.pix_copia_cola, f.pix_qrcode,
               c.nome_razao, c.email, c.email2, c.cpf_cnpj, c.celular, c.telefone
        FROM faturas f
        JOIN clientes c ON f.cliente_id = c.id
        WHERE f.id = ? AND f.admin_id = ?
    ");
    $stmt->execute([$id, $adminIdE]);
    $fatura = $stmt->fetch();
    if ($fatura) {
        $ok = enviarWhatsAppFatura($fatura, 'antes');
        header('Location: emissao.php?msg=' . ($ok ? 'whatsapp_enviado' : 'whatsapp_erro'));
    } else {
        header('Location: emissao.php?msg=sem_fatura');
    }
    exit;
}

// Gerar boleto (PDF) de uma fatura gerada específica
if (isset($_GET['fatura_boleto'])) {
    $id = intval($_GET['fatura_boleto']);
    $stmt = $pdo->prepare("SELECT * FROM faturas WHERE id = ? AND admin_id = ?");
    $stmt->execute([$id, $adminIdE]);
    $fat = $stmt->fetch();

    if (!$fat) {
        header('Location: emissao.php');
        exit;
    }
    if (!empty($fat['boleto_url'])) {
        header('Location: ' . $fat['boleto_url']);
        exit;
    }

    $stmtCli = $pdo->prepare("SELECT * FROM clientes WHERE id = ? AND admin_id = ?");
    $stmtCli->execute([$fat['cliente_id'], $adminIdE]);
    $cli = $stmtCli->fetch();

    $result = criarBoleto(
        $fat['descricao'], $fat['valor_final'], $cli['nome_razao'] ?? '',
        $cli['cpf_cnpj'] ?? '', $cli['email'] ?? '', $cli['cep'] ?? '',
        $cli['logradouro'] ?? '', $cli['numero'] ?? '', $cli['bairro'] ?? '',
        $cli['cidade'] ?? '', $cli['estado'] ?? '', $fat['data_vencimento'] ?? null
    );

    if (isset($result['sucesso']) && $result['sucesso'] && !empty($result['boleto_url'])) {
        $apiAgora = getApiAtiva();
        if ($apiAgora === 'inter' || $apiAgora === 'bb') {
            $stmt = $pdo->prepare("UPDATE faturas SET boleto_url = ?, inter_codigo_solicitacao = ? WHERE id = ? AND admin_id = ?");
            $stmt->execute([$result['boleto_url'], $result['payment_id'], $id, $adminIdE]);
        } elseif (!empty($fat['mp_payment_id'])) {
            $stmt = $pdo->prepare("UPDATE faturas SET boleto_url = ? WHERE id = ? AND admin_id = ?");
            $stmt->execute([$result['boleto_url'], $id, $adminIdE]);
        } else {
            $stmt = $pdo->prepare("UPDATE faturas SET boleto_url = ?, mp_payment_id = ? WHERE id = ? AND admin_id = ?");
            $stmt->execute([$result['boleto_url'], $result['payment_id'], $id, $adminIdE]);
        }
        header('Location: ' . $result['boleto_url']);
    } else {
        $erroDetalhe = is_array($result)
            ? ($result['erro'] ?? trim(json_encode($result)))
            : 'Resposta inválida da API de pagamento';
        if (empty($erroDetalhe)) {
            $erroDetalhe = isset($result['sucesso']) ? 'API retornou sucesso mas sem URL do boleto.' : 'Erro desconhecido.';
        }
        $apiAtivaNome = getApiAtiva();
        error_log("[EMISSAO][BOLETO] Fatura #{$id} | API={$apiAtivaNome} | Erro: " . $erroDetalhe);
        @file_put_contents(__DIR__ . '/../boleto_debug.log', date('Y-m-d H:i:s') . " | Fatura #{$id} | API={$apiAtivaNome} | ERRO: " . $erroDetalhe . "\n", FILE_APPEND);
        header('Location: emissao.php?msg=erro_boleto&api=' . urlencode($apiAtivaNome) . '&det=' . urlencode(substr($erroDetalhe, 0, 300)));
    }
    exit;
}

// Obter código PIX copia e cola de uma fatura gerada específica
if (isset($_GET['fatura_pix'])) {
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    $id = intval($_GET['fatura_pix']);
    $stmt = $pdo->prepare("SELECT * FROM faturas WHERE id = ? AND admin_id = ?");
    $stmt->execute([$id, $adminIdE]);
    $fat = $stmt->fetch();

    if (!$fat) {
        echo json_encode(['ok' => false, 'erro' => 'Fatura não encontrada.']);
        exit;
    }
    if ($fat['status'] === 'pago') {
        echo json_encode(['ok' => false, 'erro' => 'Esta fatura já está paga.']);
        exit;
    }

    $pix = (string) ($fat['pix_copia_cola'] ?? '');
    $apiFatura = ($fat['api_pagamento'] ?? '') ?: getApiAtiva();
    $expirado = false;

    // Detectar PIX expirado: vencimento passou = banco rejeita o código
    if ($pix !== '' && $fat['status'] !== 'pago') {
        $vencimentoTs = strtotime($fat['data_vencimento'] ?? '');
        $hojeTs = strtotime(date('Y-m-d'));
        $vencimentoPassou = ($vencimentoTs !== false && $vencimentoTs < $hojeTs);

        if ($vencimentoPassou && $apiFatura !== 'pix_manual') {
            $expirado = true;
        }

        // Inter: validação extra via API (caso cobrança foi cancelada antes do vencimento)
        if (!$expirado && $apiFatura === 'inter' && !empty($fat['inter_codigo_solicitacao'])) {
            $detalheExp = consultarCobrancaInter($fat['inter_codigo_solicitacao']);
            if (isset($detalheExp['erro'])) {
                $expirado = true;
            } else {
                $sit = strtoupper($detalheExp['situacao'] ?? ($detalheExp['cobranca']['situacao'] ?? ''));
                if (in_array($sit, ['EXPIRADA', 'CANCELADA', 'VENCIDA', 'REMOVIDA_PELO_USUARIO_RECEBEDOR'], true)) {
                    $expirado = true;
                }
            }
        }

        error_log("[FATURA PIX] ID={$id} venc={$fat['data_vencimento']} expirado=" . ($expirado ? 'SIM' : 'NAO') . " api={$apiFatura}");
    }

    $jaTemCobranca = !empty($fat['inter_codigo_solicitacao']) || !empty($fat['mp_payment_id']);
    $precisaGerar = ($pix === '' && !$jaTemCobranca && empty($fat['link_pagamento'])) || $expirado;

    if ($precisaGerar) {
        // Gera nova cobrança (mesma lógica do painel do usuário); substitui a anterior se expirada
        $stmtCli = $pdo->prepare("SELECT * FROM clientes WHERE id = ? AND admin_id = ?");
        $stmtCli->execute([$fat['cliente_id'], $adminIdE]);
        $cli = $stmtCli->fetch();

        $result = criarPagamento($fat['descricao'], $fat['valor_final'], $cli['email'] ?? '', $cli['nome_razao'] ?? '', $fat['data_vencimento'] ?? null);
        error_log("[FATURA PIX] criarPagamento returned: " . json_encode(array_keys($result)));
        if (isset($result['sucesso']) && $result['sucesso']) {
            $apiUsada = $result['api'] ?? getApiAtiva();
            if ($apiUsada === 'inter' || $apiUsada === 'bb') {
                $stmtUp = $pdo->prepare("UPDATE faturas SET pix_qrcode = ?, pix_copia_cola = ?, link_pagamento = ?, mp_payment_id = ?, inter_codigo_solicitacao = ?, api_pagamento = ? WHERE id = ? AND admin_id = ?");
                $stmtUp->execute([$result['qr_code'] ?? '', $result['qr_code_copia_cola'] ?? '', $result['link_pagamento'] ?? '', null, $result['payment_id'] ?? '', $apiUsada, $id, $adminIdE]);
            } else {
                $stmtUp = $pdo->prepare("UPDATE faturas SET pix_qrcode = ?, pix_copia_cola = ?, link_pagamento = ?, mp_payment_id = ?, api_pagamento = ? WHERE id = ? AND admin_id = ?");
                $stmtUp->execute([$result['qr_code'] ?? '', $result['qr_code_copia_cola'] ?? '', $result['link_pagamento'] ?? '', $result['payment_id'] ?? '', $apiUsada, $id, $adminIdE]);
            }
            $pix = (string) ($result['qr_code_copia_cola'] ?? '');
        } elseif ($expirado) {
            error_log("[FATURA PIX] Falha ao regenerar PIX expirado ID={$id}: " . ($result['erro'] ?? json_encode($result)));
            echo json_encode(['ok' => false, 'erro' => 'O PIX anterior expirou e não foi possível gerar um novo: ' . ($result['erro'] ?? 'erro desconhecido')]);
            exit;
        }
    } elseif ($pix === '' && $jaTemCobranca && !empty($fat['inter_codigo_solicitacao']) && in_array($apiFatura, ['inter', ''], true)) {
        // Cobrança Inter existe mas o PIX ainda não foi preenchido — consulta como faz o painel do usuário
        $detalhe = consultarCobrancaInter($fat['inter_codigo_solicitacao']);
        if ($detalhe && !isset($detalhe['erro'])) {
            $pixArr = $detalhe['pix'] ?? ($detalhe['cobranca']['pix'] ?? []);
            $pixNovo = (string) ($pixArr['pixCopiaECola'] ?? '');
            $qrNovo = (string) ($pixArr['qrcode'] ?? '');
            if ($pixNovo !== '') {
                $stmtUp = $pdo->prepare("UPDATE faturas SET pix_copia_cola = ?, pix_qrcode = ? WHERE id = ? AND admin_id = ? AND (pix_copia_cola IS NULL OR pix_copia_cola = '')");
                $stmtUp->execute([$pixNovo, $qrNovo, $id, $adminIdE]);
                $pix = $pixNovo;
            }
        }
    }

    if ($pix !== '') {
        echo json_encode(['ok' => true, 'pix' => $pix, 'renovado' => $expirado]);
    } else {
        echo json_encode(['ok' => false, 'erro' => 'Nenhum código PIX disponível para esta fatura ainda. Abra a fatura no painel ou aguarde a geração.']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['bulk_delete']) || !empty($_POST['ids']))) {
    $ids = array_map('intval', $_POST['ids']);
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $stFats = $pdo->prepare("SELECT * FROM faturas WHERE fatura_recorrente_id IN ($ph) AND admin_id = ?");
    $stFats->execute(array_merge($ids, [$adminIdE]));
    $faturasRec = $stFats->fetchAll();
    foreach ($faturasRec as $fat) {
        cancelarCobrancaFatura($fat);
    }
    excluirFaturasComLapide($pdo, $faturasRec, 'excluir_lote');
    $stmt = $pdo->prepare("DELETE FROM faturas_recorrentes WHERE id IN ($ph) AND admin_id = ?");
    $stmt->execute(array_merge($ids, [$adminIdE]));
    header('Location: emissao.php?msg=excluido');
    exit;
}
if (isset($_GET['msg'])) {
    $msgs = [
        'salvo' => ['Fatura criada com sucesso!', 'success'],
        'pago' => ['Fatura marcada como paga!', 'success'],
        'cancelado' => ['Fatura recorrente cancelada!', 'warning'],
        'excluido' => ['Fatura recorrente excluída!', 'warning'],
        'fatura_cancelada' => ['Fatura cancelada!', 'warning'],
        'fatura_desmarcada' => ['Fatura desmarcada como paga!', 'warning'],
        'fatura_excluida' => ['Fatura excluída!', 'warning'],
        'enviado' => ['E-mail de cobrança enviado com sucesso!', 'success'],
        'erro_envio' => ['Erro ao enviar e-mail. Verifique as configurações SMTP.', 'danger'],
        'sem_email' => ['Cliente não possui e-mail cadastrado.', 'warning'],
        'whatsapp_enviado' => ['Fatura enviada via WhatsApp com sucesso!', 'success'],
        'whatsapp_erro' => ['Erro ao enviar WhatsApp. Verifique as configurações.', 'danger'],
        'sem_fatura' => ['Nenhuma fatura pendente encontrada para esta recorrência.', 'warning'],
        'erro_boleto' => ['Erro ao gerar boleto. Verifique a configuração da API de pagamento.', 'danger'],
        'lote_criado' => ['Boleto em Lote criado com sucesso!', 'success'],
        'carne_criado' => ['Carnê gerado com sucesso!', 'success'],
        'erro' => ['Erro ao salvar.', 'danger'],
    ];
    if (isset($msgs[$_GET['msg']])) {
        $mensagem = $msgs[$_GET['msg']][0];
        $tipo = $msgs[$_GET['msg']][1];
        if ($_GET['msg'] === 'carne_criado') {
            $qtdCarne = (int)($_GET['qtd'] ?? 0);
            $bolCarne = (int)($_GET['bol'] ?? 0);
            $pixCarne = (int)($_GET['pix'] ?? 0);
            $frCarne = (int)($_GET['fr'] ?? 0);
            $mensagem = '<strong>Carnê gerado com sucesso!</strong> ' . $qtdCarne . ' parcelas mensais criadas.';
            if ($bolCarne > 0) {
                $mensagem .= ' (' . $bolCarne . ' boleto(s) registrado(s)';
                if ($pixCarne > 0) $mensagem .= ', ' . $pixCarne . ' PIX';
                $mensagem .= ')';
            }
            if ($frCarne > 0) {
                $mensagem .= ' <a href="carne_pdf.php?fr=' . $frCarne . '" target="_blank" class="btn btn-sm btn-outline-success ms-2"><i class="fas fa-file-pdf me-1"></i>Visualizar / Baixar Carnê Completo</a>';
            }
        }
        if ($_GET['msg'] === 'lote_criado') {
            $qtdLote = (int)($_GET['qtd'] ?? 0);
            $bolLote = (int)($_GET['bol'] ?? 0);
            $pixLote = (int)($_GET['pix'] ?? 0);
            if ($qtdLote > 0) {
                $mensagem .= '<br><small>' . $qtdLote . ' parcela(s) gerada(s)';
                $mensagem .= $bolLote < $qtdLote
                    ? ' — ' . $bolLote . ' boleto(s) emitido(s); as demais podem ser geradas no botão de boleto de cada fatura'
                    : ' — todos os boletos emitidos';
                if ($pixLote > 0) {
                    $mensagem .= '; ' . $pixLote . ' PIX com QR Code válido gerado(s)';
                }
                $mensagem .= '.</small>';
            }
        }
        if ($_GET['msg'] === 'erro_boleto') {
            if (!empty($_GET['api'])) {
                $mensagem .= '<br><small><strong>API usada na tentativa:</strong> ' . htmlspecialchars($_GET['api']) . '</small>';
            }
            if (!empty($_GET['det'])) {
                $mensagem .= '<br><small><strong>Detalhe:</strong> ' . htmlspecialchars(substr($_GET['det'], 0, 300)) . '</small>';
            }
        }
    }
}

// Salvar fatura recorrente
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = intval($_POST['cliente_id'] ?? 0);
    $descricao = trim($_POST['descricao'] ?? '');
    
    // Tratamento de valor monetário no padrão brasileiro (ex: R$ 1.500,50 ou 1500.50)
    $valorRaw = trim((string)($_POST['valor'] ?? '0'));
    if (strpos($valorRaw, ',') !== false) {
        $valorLimpo = preg_replace('/[^\d,]/', '', $valorRaw);
        $valorLimpo = str_replace(',', '.', $valorLimpo);
    } else {
        $valorLimpo = preg_replace('/[^\d.]/', '', $valorRaw);
    }
    $valor = round((float)$valorLimpo, 2);
    $frequencia = $_POST['frequencia'] ?? 'mensal';
    // Vencimento da 1ª Transação: obrigatório e válido. O dia do mês dele é a
    // âncora da recorrência (dia_vencimento / dia_ancora).
    $validacaoVenc = recorrenciaValidarPrimeiroVencimento($_POST['data_primeiro_vencimento'] ?? '');
    $primeiroVencimentoData = $validacaoVenc['data'];
    $dia_vencimento = $validacaoVenc['ok'] ? (int)substr($primeiroVencimentoData, 8, 2) : 1;
    $data_inicio = date('Y-m-d');
    $data_fim = !empty($_POST['data_fim']) ? $_POST['data_fim'] : null;
    // A recorrencia e sempre Indeterminada: NULL = gerar sem limite de
    // transacoes. O campo "Qtd. Transacoes" nao define mais o fim do ciclo.
    $quantidade_transacoes = null;
    // Vencimento = propria competencia (campo "Dias ate o vencimento" removido).
    $dias_vencimento = 0;
    $politica_atraso = in_array($_POST['politica_atraso'] ?? '', ['todas', 'mais_recente'], true)
        ? $_POST['politica_atraso'] : 'todas';

    // Periodicidade: somente as opções oferecidas no cadastro.
    if (!in_array($frequencia, RECORRENCIAS_CADASTRO, true)) {
        $frequencia = 'mensal';
    }
    // Antecedencia da emissao automatica pelo CRON, definida pela frequencia.
    $antecedencia_emissao = recorrenciaAntecedenciaEmissao($frequencia);

    // ===== CARNÊ (ou Boleto em Lote legado) =====
    // Materializa N faturas de uma vez com vencimentos mensais consecutivos
    // (a 1ª parcela vence na data escolhida em data_primeiro_vencimento e as demais
    // nos meses seguintes preservando o dia âncora com clamping de fim de mês).
    if ($frequencia === 'carne' || $frequencia === 'lote') {
        $quantidade_boletos = max(1, min(72, intval($_POST['quantidade_boletos'] ?? ($_POST['quantidade_transacoes'] ?? 12))));
        if ($cliente_id <= 0 || empty($descricao) || $valor <= 0) {
            $mensagem = 'Preencha todos os campos obrigatórios.';
            $tipo = 'danger';
        } elseif (!$validacaoVenc['ok']) {
            $mensagem = $validacaoVenc['motivo'];
            $tipo = 'danger';
        } else {
            try {
                $stChkCli = $pdo->prepare("SELECT * FROM clientes WHERE id = ? AND admin_id = ?");
                $stChkCli->execute([$cliente_id, $adminIdE]);
                $cliCarne = $stChkCli->fetch();
                if (!$cliCarne) {
                    $mensagem = 'Cliente inválido.';
                    $tipo = 'danger';
                    $erroCliente = true;
                } else {
                    $limiteFat = verificarLimitePlano('faturas', $adminIdE);
                    if (!$limiteFat['ok']) {
                        $mensagem = $limiteFat['mensagem'];
                        $tipo = 'danger';
                    } else {
                        $numeroCarne = generateInvoiceNumber();
                        $ancora = $dia_vencimento;
                        $dataBase = date('Y-m-d');

                        // Calcula os vencimentos mensais consecutivos usando a regra de âncora e clamping
                        $vencimentos = calcularVencimentosCarne($primeiroVencimentoData, $quantidade_boletos);

                        $pdo->beginTransaction();
                        $stmtFr = $pdo->prepare("INSERT INTO faturas_recorrentes
                            (admin_id, cliente_id, descricao, valor, frequencia, dia_vencimento, dia_ancora,
                             data_inicio, data_fim, data_proxima_fatura, data_ultima_fatura,
                             antecedencia_emissao, dias_vencimento, politica_atraso,
                             quantidade_transacoes, numero, ativo, status)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, NULL, ?, ?, ?, ?, ?, 1, 'ativa')");
                        $stmtFr->execute([
                            $adminIdE, $cliente_id, $descricao, $valor,
                            $frequencia,
                            $dia_vencimento, $ancora, $dataBase,
                            $antecedencia_emissao, 0, $politica_atraso,
                            $quantidade_boletos, $numeroCarne,
                        ]);
                        $frId = (int)$pdo->lastInsertId();

                        $stmtFat = $pdo->prepare("INSERT INTO faturas
                            (admin_id, cliente_id, fatura_recorrente_id, carne_id, parcela_atual, total_parcelas,
                             numero, descricao, valor, valor_final, data_emissao, competencia, data_vencimento,
                             status, acesso_token, api_pagamento, ultimo_envio_tipo)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, 'pendente', ?, ?, 'agendado')");
                        $stmtBoleto = $pdo->prepare("UPDATE faturas SET boleto_url = ?, boleto_codigo_barras = ?, boleto_linha_digitavel = ?, mp_payment_id = ?, inter_codigo_solicitacao = ?, api_pagamento = ? WHERE id = ? AND admin_id = ?");
                        $stmtPix = $pdo->prepare("UPDATE faturas SET pix_qrcode = ?, pix_copia_cola = ?, link_pagamento = ?, pix_inter_codigo = ? WHERE id = ? AND admin_id = ?");

                        $parcelas = [];
                        $boletosOk = 0;
                        $pixOk = 0;
                        $apiAgora = getApiAtiva();
                        for ($idx = 0; $idx < $quantidade_boletos; $idx++) {
                            $parcNum = $idx + 1;
                            $venc = $vencimentos[$idx] ?? date('Y-m-d', strtotime("+$idx month", strtotime($primeiroVencimentoData)));
                            $comp = $venc;
                            $numParcRotulo = sprintf('%02d/%02d', $parcNum, $quantidade_boletos);
                            $descFat = $descricao . ' (' . $numParcRotulo . ')';
                            $fatNum = generateInvoiceNumber();
                            $tokenAcesso = generateAcessoToken();

                            $stmtFat->execute([
                                $adminIdE, $cliente_id, $frId, $frId, $parcNum, $quantidade_boletos,
                                $fatNum, $descFat, $valor, $valor, $comp, $venc,
                                $tokenAcesso, $apiAgora,
                            ]);
                            $fid = (int)$pdo->lastInsertId();
                            $parcelas[] = ['id' => $fid, 'venc' => $venc, 'num' => $descFat];

                            // Boleto por parcela registrado no gateway configurado
                            $resBol = criarBoleto(
                                $descFat, $valor, $cliCarne['nome_razao'] ?? '',
                                $cliCarne['cpf_cnpj'] ?? '', $cliCarne['email'] ?? '', $cliCarne['cep'] ?? '',
                                $cliCarne['logradouro'] ?? '', $cliCarne['numero'] ?? '', $cliCarne['bairro'] ?? '',
                                $cliCarne['cidade'] ?? '', $cliCarne['estado'] ?? '', $venc
                            );
                            $temBoleto = false;
                            if (is_array($resBol) && !empty($resBol['sucesso']) && !empty($resBol['boleto_url'])) {
                                $stmtBoleto->execute([
                                    $resBol['boleto_url'],
                                    $resBol['boleto_codigo_barras'] ?? '',
                                    $resBol['boleto_linha_digitavel'] ?? '',
                                    $resBol['payment_id'] ?? '',
                                    in_array($apiAgora, ['inter', 'bb'], true) ? ($resBol['payment_id'] ?? '') : '',
                                    $resBol['api'] ?? $apiAgora,
                                    $fid, $adminIdE,
                                ]);
                                $boletosOk++;
                                $temBoleto = true;
                            }

                            // PIX da parcela
                            $pixCopia = (string)($resBol['qr_code_copia_cola'] ?? '');
                            $pixQr = (string)($resBol['qr_code'] ?? '');
                            $pixCod = $temBoleto ? (string)($resBol['payment_id'] ?? '') : '';
                            $precisaPixExtra = in_array($apiAgora, ['inter', 'bb'], true)
                                ? ($pixCopia === '')
                                : true;
                            if ($precisaPixExtra) {
                                $resPix = criarPagamento($descFat, $valor, $cliCarne['email'] ?? '', $cliCarne['nome_razao'] ?? '', $venc, 2);
                                if (is_array($resPix) && !empty($resPix['sucesso'])) {
                                    $pixCopia = (string)($resPix['qr_code_copia_cola'] ?? '');
                                    $pixQr = (string)($resPix['qr_code'] ?? '');
                                    $pixCod = (string)($resPix['payment_id'] ?? '');
                                }
                            }
                            if ($pixCopia !== '' || $pixQr !== '') {
                                $stmtPix->execute([$pixQr, $pixCopia, '', $pixCod, $fid, $adminIdE]);
                                $pixOk++;
                            }
                        }

                        // Envio imediato da 1ª parcela (apenas uma vez para a emissão)
                        if (!empty($parcelas[0])) {
                            $stF = $pdo->prepare("SELECT f.*, c.nome_razao, c.celular, c.telefone, c.email, c.email2, c.cpf_cnpj FROM faturas f JOIN clientes c ON f.cliente_id = c.id WHERE f.id = ? AND f.admin_id = ?");
                            $stF->execute([$parcelas[0]['id'], $adminIdE]);
                            $fat1 = $stF->fetch();
                            if ($fat1 && !empty($fat1['email'])) {
                                enviarEmailFatura($fat1, 'geracao');
                            }
                            if ($fat1 && (!empty($fat1['celular']) || !empty($fat1['telefone']))) {
                                enviarWhatsAppFatura($fat1, 'geracao');
                            }
                            $pdo->prepare("UPDATE faturas SET ultimo_envio = CURDATE(), ultimo_envio_tipo = 'geracao' WHERE id = ? AND admin_id = ?")
                                ->execute([$parcelas[0]['id'], $adminIdE]);
                        }

                        $pdo->commit();
                        $msgTipo = ($frequencia === 'carne') ? 'carne_criado' : 'lote_criado';
                        header('Location: emissao.php?msg=' . $msgTipo . '&fr=' . $frId . '&qtd=' . $quantidade_boletos . '&bol=' . $boletosOk . '&pix=' . $pixOk);
                        exit;
                    }
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $mensagem = 'Erro: ' . $e->getMessage();
                $tipo = 'danger';
            }
        }
    } elseif (!$validacaoVenc['ok']) {
        $mensagem = $validacaoVenc['motivo'];
        $tipo = 'danger';
    } elseif ($cliente_id <= 0 || empty($descricao) || $valor <= 0) {
        $mensagem = 'Preencha todos os campos obrigatórios.';
        $tipo = 'danger';
    } else {
        try {
            // Valida que o cliente pertence ao admin logado
            $stChkCli = $pdo->prepare("SELECT id FROM clientes WHERE id = ? AND admin_id = ?");
            $stChkCli->execute([$cliente_id, $adminIdE]);
            if (!$stChkCli->fetch()) {
                $mensagem = 'Cliente inválido.';
                $tipo = 'danger';
                $erroCliente = true;
            } else {
            $limiteFat = verificarLimitePlano('faturas', $adminIdE);
            if (!$limiteFat['ok']) {
                $mensagem = $limiteFat['mensagem'];
                $tipo = 'danger';
            } else {
            $numero = generateInvoiceNumber($adminIdE);
            $ancora = $dia_vencimento;
            $proximaComp = proximoVencimentoRecorrencia($frequencia, $primeiroVencimentoData, $ancora);

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO faturas_recorrentes
                (admin_id, cliente_id, descricao, valor, frequencia, dia_vencimento, dia_ancora,
                 data_inicio, data_fim, data_proxima_fatura, data_ultima_fatura,
                 antecedencia_emissao, dias_vencimento, politica_atraso,
                 quantidade_transacoes, numero, ativo, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, 1, 'ativa')");
            $stmt->execute([
                $adminIdE, $cliente_id, $descricao, $valor, $frequencia,
                $dia_vencimento, $ancora, $data_fim,
                $proximaComp,
                $primeiroVencimentoData,
                $antecedencia_emissao, $dias_vencimento, $politica_atraso,
                $quantidade_transacoes ?: null, $numero,
            ]);
            $faturaRecorrenteId = (int)$pdo->lastInsertId();

            // Emitir IMEDIATAMENTE a primeira fatura da recorrência
            $fatNum1 = generateInvoiceNumber($adminIdE);
            $tokenAcesso1 = generateAcessoToken();
            $apiAgora = getApiAtiva();

            $stmtFat1 = $pdo->prepare("INSERT INTO faturas
                (admin_id, cliente_id, fatura_recorrente_id, carne_id, parcela_atual, total_parcelas,
                 numero, descricao, valor, valor_final, data_emissao, competencia, data_vencimento,
                 status, acesso_token, api_pagamento, ultimo_envio_tipo)
                VALUES (?, ?, ?, NULL, 1, ?, ?, ?, ?, ?, CURDATE(), ?, ?, 'pendente', ?, ?, 'geracao')");
            $stmtFat1->execute([
                $adminIdE, $cliente_id, $faturaRecorrenteId,
                $quantidade_transacoes ?: null,
                $fatNum1, $descricao, $valor, $valor,
                $primeiroVencimentoData, $primeiroVencimentoData,
                $tokenAcesso1, $apiAgora,
            ]);
            $fid1 = (int)$pdo->lastInsertId();

            // Buscar dados completos do cliente para registro de boleto / PIX
            $stCliData = $pdo->prepare("SELECT * FROM clientes WHERE id = ? AND admin_id = ?");
            $stCliData->execute([$cliente_id, $adminIdE]);
            $cliData = $stCliData->fetch() ?: [];

            // Gerar boleto para a primeira fatura
            $resBol = criarBoleto(
                $descricao, $valor, $cliData['nome_razao'] ?? '',
                $cliData['cpf_cnpj'] ?? '', $cliData['email'] ?? '', $cliData['cep'] ?? '',
                $cliData['logradouro'] ?? '', $cliData['numero'] ?? '', $cliData['bairro'] ?? '',
                $cliData['cidade'] ?? '', $cliData['estado'] ?? '', $primeiroVencimentoData
            );
            $temBoleto = false;
            if (is_array($resBol) && !empty($resBol['sucesso']) && !empty($resBol['boleto_url'])) {
                $pdo->prepare("UPDATE faturas SET boleto_url = ?, boleto_codigo_barras = ?, boleto_linha_digitavel = ?, mp_payment_id = ?, inter_codigo_solicitacao = ?, api_pagamento = ? WHERE id = ? AND admin_id = ?")
                    ->execute([
                        $resBol['boleto_url'],
                        $resBol['boleto_codigo_barras'] ?? '',
                        $resBol['boleto_linha_digitavel'] ?? '',
                        $resBol['payment_id'] ?? '',
                        in_array($apiAgora, ['inter', 'bb'], true) ? ($resBol['payment_id'] ?? '') : '',
                        $resBol['api'] ?? $apiAgora,
                        $fid1, $adminIdE,
                    ]);
                $temBoleto = true;
            }

            // Gerar PIX para a primeira fatura
            $pixCopia = (string)($resBol['qr_code_copia_cola'] ?? '');
            $pixQr = (string)($resBol['qr_code'] ?? '');
            $pixCod = $temBoleto ? (string)($resBol['payment_id'] ?? '') : '';
            $precisaPixExtra = in_array($apiAgora, ['inter', 'bb'], true)
                ? ($pixCopia === '')
                : true;
            if ($precisaPixExtra) {
                $resPix = criarPagamento($descricao, $valor, $cliData['email'] ?? '', $cliData['nome_razao'] ?? '', $primeiroVencimentoData, 2);
                if (is_array($resPix) && !empty($resPix['sucesso'])) {
                    $pixCopia = (string)($resPix['qr_code_copia_cola'] ?? '');
                    $pixQr = (string)($resPix['qr_code'] ?? '');
                    $pixCod = (string)($resPix['payment_id'] ?? '');
                }
            }
            if ($pixCopia !== '' || $pixQr !== '') {
                $pdo->prepare("UPDATE faturas SET pix_qrcode = ?, pix_copia_cola = ?, link_pagamento = ?, pix_inter_codigo = ? WHERE id = ? AND admin_id = ?")
                    ->execute([$pixQr, $pixCopia, '', $pixCod, $fid1, $adminIdE]);
            }

            // Envio imediato da primeira fatura (1 e-mail e 1 WhatsApp)
            $stF1 = $pdo->prepare("SELECT f.*, c.nome_razao, c.celular, c.telefone, c.email, c.email2, c.cpf_cnpj FROM faturas f JOIN clientes c ON f.cliente_id = c.id WHERE f.id = ? AND f.admin_id = ?");
            $stF1->execute([$fid1, $adminIdE]);
            $fat1 = $stF1->fetch();
            if ($fat1 && !empty($fat1['email'])) {
                enviarEmailFatura($fat1, 'geracao');
            }
            if ($fat1 && (!empty($fat1['celular']) || !empty($fat1['telefone']))) {
                enviarWhatsAppFatura($fat1, 'geracao');
            }
            $pdo->prepare("UPDATE faturas SET ultimo_envio = CURDATE(), ultimo_envio_tipo = 'geracao' WHERE id = ? AND admin_id = ?")
                ->execute([$fid1, $adminIdE]);

            $pdo->commit();
            header('Location: emissao.php?msg=salvo');
            exit;
                }
            }
        } catch (PDOException $e) {
            $mensagem = 'Erro: ' . $e->getMessage();
            $tipo = 'danger';
        }
    }
}

$stmtCliList = $pdo->prepare("SELECT id, nome_razao, cpf_cnpj FROM clientes WHERE ativo = 1 AND admin_id = ? ORDER BY nome_razao");
$stmtCliList->execute([$adminIdE]);
$clientes = $stmtCliList->fetchAll();

$filtro_status = $_GET['filtro_status'] ?? '';
$filtro_busca = trim($_GET['filtro_busca'] ?? '');

$sql = "
    SELECT base.* FROM (
        SELECT fr.*, c.nome_razao, c.cpf_cnpj, c.celular, c.telefone,
        (SELECT f.link_pagamento FROM faturas f WHERE f.fatura_recorrente_id = fr.id AND f.status IN ('pendente','vencido','atrasado') ORDER BY f.data_vencimento DESC, f.id DESC LIMIT 1) AS ultimo_link,
        (SELECT f.numero FROM faturas f WHERE f.fatura_recorrente_id = fr.id ORDER BY f.data_vencimento DESC, f.id DESC LIMIT 1) AS ultimo_numero,
        (SELECT f.status FROM faturas f WHERE f.fatura_recorrente_id = fr.id ORDER BY f.data_vencimento DESC, f.id DESC LIMIT 1) AS ultimo_status,
        (SELECT f.data_vencimento FROM faturas f WHERE f.fatura_recorrente_id = fr.id AND f.status IN ('pendente','vencido','atrasado') ORDER BY f.data_vencimento ASC, f.id ASC LIMIT 1) AS proximo_vencimento,
        (SELECT f.data_vencimento FROM faturas f WHERE f.fatura_recorrente_id = fr.id ORDER BY f.data_vencimento DESC, f.id DESC LIMIT 1) AS ultimo_vencimento
        FROM faturas_recorrentes fr 
        JOIN clientes c ON fr.cliente_id = c.id 
        WHERE (fr.ativo = 1 OR fr.status = 'cancelado') AND fr.admin_id = ?
    ) AS base
    WHERE 1=1
";
$params = [$adminIdE];

if ($filtro_status !== '') {
    $sql .= " AND base.ultimo_status = ?";
    $params[] = $filtro_status;
}
if ($filtro_busca !== '') {
    $sql .= " AND (base.nome_razao LIKE ? OR base.cpf_cnpj LIKE ? OR base.ultimo_numero LIKE ? OR base.descricao LIKE ?)";
    $params[] = '%' . $filtro_busca . '%';
    $params[] = '%' . $filtro_busca . '%';
    $params[] = '%' . $filtro_busca . '%';
    $params[] = '%' . $filtro_busca . '%';
}

$sql .= " ORDER BY base.criado_em DESC";

$countSql = "SELECT COUNT(*) FROM (
    SELECT fr.id FROM faturas_recorrentes fr 
    JOIN clientes c ON fr.cliente_id = c.id 
    WHERE (fr.ativo = 1 OR fr.status = 'cancelado') AND fr.admin_id = ?";
$countParams = [$adminIdE];

if ($filtro_status !== '') {
    $countSql .= " AND (SELECT f.status FROM faturas f WHERE f.fatura_recorrente_id = fr.id ORDER BY f.data_vencimento DESC, f.id DESC LIMIT 1) = ?";
    $countParams[] = $filtro_status;
}

$countSql .= ") AS cnt";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($countParams);
$totalFaturasRecorrentes = $countStmt->fetchColumn();

$perPage = intval($_GET['per_page'] ?? 10);
if (!in_array($perPage, [10, 20, 50, 100])) $perPage = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$totalPages = max(1, ceil($totalFaturasRecorrentes / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$sql .= " LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$faturasRecorrentes = $stmt->fetchAll();

// Todas as faturas geradas (manualmente ou automaticamente pelo cron)
// de cada recorrência listada, para exibição no histórico expansível.
$frIds = array_column($faturasRecorrentes, 'id');
$faturasPorRecorrencia = [];
if ($frIds) {
    $phIds = implode(',', array_fill(0, count($frIds), '?'));
    $stmtFatsFr = $pdo->prepare("SELECT id, fatura_recorrente_id, numero, valor_final, data_emissao, data_vencimento, status, pix_copia_cola, parcela_atual, total_parcelas FROM faturas WHERE admin_id = ? AND fatura_recorrente_id IN ($phIds) ORDER BY parcela_atual ASC, data_vencimento ASC, numero DESC");
    $stmtFatsFr->execute(array_merge([$adminIdE], $frIds));
    foreach ($stmtFatsFr->fetchAll() as $ffr) {
        $faturasPorRecorrencia[$ffr['fatura_recorrente_id']][] = $ffr;
    }
}

$pageTitle = 'Emissão de Faturas';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5>Emissão de Faturas</h5>
        </div>
        <a href="https://wa.me/5591982675573" target="_blank" class="btn btn-light btn-sm ms-auto me-2" style="font-size:0.8rem;border:1px solid #dee2e6;"><i class="fas fa-headset"></i> Suporte</a>
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                <img src="<?= htmlspecialchars(function_exists('getAdminAvatarUrl') ? getAdminAvatarUrl() : ($_SESSION['admin_avatar'] ?? '/cobranca/assets/img/avatars/admin.svg')) ?>" alt="Avatar" class="rounded-circle me-2" width="32" height="32" style="object-fit:cover;" onerror="this.onerror=null;this.src='/cobranca/assets/img/avatars/admin.svg';">
                <span class="text-muted d-none d-md-inline"><?= htmlspecialchars($_SESSION['admin_nome']) ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="/cobranca/admin/perfil.php"><i class="fas fa-user-edit me-2"></i>Editar Perfil</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="/cobranca/admin/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Sair</a></li>
            </ul>
        </div>
    </div>

    <div class="content-area fade-in">
        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $tipo ?> alert-dismissible fade show">
                <?= $mensagem ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="form-card mb-4">
            <h6 class="mb-3"><i class="fas fa-plus-circle me-2"></i>Nova Fatura</h6>
            <form method="POST" id="formNovaFatura">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Cliente *</label>
                        <select name="cliente_id" class="form-select" required>
                            <option value="">Selecione o cliente...</option>
                            <?php foreach ($clientes as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nome_razao']) ?> (<?= htmlspecialchars($c['cpf_cnpj']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Descrição *</label>
                        <input type="text" name="descricao" class="form-control" placeholder="Ex: Mensalidade Janeiro" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Valor (R$) *</label>
                        <input type="text" name="valor" id="campo_valor" class="form-control" placeholder="R$ 0,00" required inputmode="numeric" oninput="mascaraMoeda(this)" autocomplete="off">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Frequência</label>
                        <select name="frequencia" id="frequencia" class="form-select">
                            <?php foreach (RECORRENCIAS_CADASTRO as $fq): ?>
                                <option value="<?= $fq ?>"><?= htmlspecialchars(recorrenciaRotulo($fq)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-md-3">
                        <label class="form-label">Vencimento da 1ª Transação (cobrar em) *</label>
                        <input type="date" name="data_primeiro_vencimento" id="data_primeiro_vencimento" class="form-control" min="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-3" id="wrapQtdBoletos" style="display:none;">
                        <label class="form-label">Quantidade de boletos *</label>
                        <input type="number" name="quantidade_boletos" id="quantidade_boletos" class="form-control" min="1" max="72" placeholder="Ex: 12" value="12">
                        <small class="text-muted">Boletos mensais consecutivos</small>
                    </div>
                    <div class="col-md-3" id="wrapPoliticaAtraso">
                        <label class="form-label">Se o cron atrasar</label>
                        <select name="politica_atraso" class="form-select">
                            <option value="todas">Emitir todas as pendentes</option>
                            <option value="mais_recente">Emitir só a mais recente</option>
                        </select>
                    </div>
                    <div class="col-md-4" id="wrapCronHint">
                        <small class="text-muted d-block mt-4">Emissão automática pelo CRON: Semanal 2 dias, Quinzenal 5 dias; Mensal, Trimestral, Semestral e Anual 10 dias antes do vencimento.</small>
                    </div>
                    <div class="col-md-4" id="wrapCarneHint" style="display:none;">
                        <small class="text-muted d-block mt-4"><i class="fas fa-info-circle text-primary me-1"></i>O carnê gera todas as parcelas mensais de uma vez, cada uma com seu próprio boleto e PIX.</small>
                    </div>
                    <div class="col-md-2 d-flex align-items-end ms-auto">
                        <button type="submit" id="btnSubmitEmissao" class="btn btn-primary w-100">
                            <i class="fas fa-save me-1"></i> <span id="btnSubmitTexto">Criar Fatura</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="table-card">
            <div class="p-3 border-bottom">
                <h6 class="mb-0"><i class="fas fa-sync-alt me-2"></i>Recorrentes Ativas</h6>
            </div>
            <div class="p-3 border-bottom">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label small">Status</label>
                        <div class="d-flex gap-1 flex-wrap">
                            <a href="?filtro_status=&filtro_busca=<?= urlencode($filtro_busca) ?>" class="btn btn-sm <?= $filtro_status === '' ? 'btn-dark' : 'btn-outline-dark' ?>"><i class="bi bi-square-fill me-1" style="color:#6c757d;font-size:.6rem"></i> Todos</a>
                            <a href="?filtro_status=pendente&filtro_busca=<?= urlencode($filtro_busca) ?>" class="btn btn-sm <?= $filtro_status === 'pendente' ? 'btn-warning' : 'btn-outline-warning' ?>"><i class="bi bi-square-fill me-1" style="color:#fd7e14;font-size:.6rem"></i> Pendente</a>
                            <a href="?filtro_status=pago&filtro_busca=<?= urlencode($filtro_busca) ?>" class="btn btn-sm <?= $filtro_status === 'pago' ? 'btn-success' : 'btn-outline-success' ?>"><i class="bi bi-square-fill me-1" style="color:#198754;font-size:.6rem"></i> Pago</a>
                            <a href="?filtro_status=vencido&filtro_busca=<?= urlencode($filtro_busca) ?>" class="btn btn-sm <?= $filtro_status === 'vencido' ? 'btn-danger' : 'btn-outline-danger' ?>"><i class="bi bi-square-fill me-1" style="color:#dc3545;font-size:.6rem"></i> Atrasado</a>
                            <a href="?filtro_status=cancelado&filtro_busca=<?= urlencode($filtro_busca) ?>" class="btn btn-sm <?= $filtro_status === 'cancelado' ? 'btn-secondary' : 'btn-outline-secondary' ?>"><i class="bi bi-square-fill me-1" style="color:#6c757d;font-size:.6rem"></i> Cancelado</a>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small"><i class="fas fa-square me-1" style="color:#6f42c1;font-size:.6rem"></i>Nome <i class="fas fa-square ms-2 me-1" style="color:#b19cd9;font-size:.6rem"></i>CPF/CNPJ <i class="fas fa-square ms-2 me-1" style="color:#d4a0e8;font-size:.6rem"></i>Nº Fatura</label>
                        <input type="text" name="filtro_busca" class="form-control form-control-sm" placeholder="Buscar por nome, CPF/CNPJ ou Nº Fatura..." value="<?= htmlspecialchars($filtro_busca) ?>">
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-search"></i></button>
                    </div>
                    <div class="col-md-1">
                        <a href="emissao.php" class="btn btn-sm btn-outline-secondary w-100"><i class="bi bi-x-lg"></i></a>
                    </div>
                </form>
            </div>
            <?php if (empty($faturasRecorrentes)): ?>
                <div class="text-center text-muted py-4">Nenhuma fatura recorrente criada</div>
            <?php else: ?>
                <form method="POST" id="bulkForm">
                <div class="p-3 border-bottom d-flex align-items-center gap-2 flex-wrap">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="selectAll">
                        <label class="form-check-label small" for="selectAll">Selecionar todos</label>
                    </div>
                    <button type="submit" name="bulk_delete" class="btn btn-sm btn-outline-danger" onclick="event.preventDefault(); showConfirmForm('Excluir Selecionados','Excluir permanentemente os selecionados?', this.closest('form'))"><i class="bi bi-trash3 me-1"></i>Excluir Selecionados</button>
                </div>
                <div class="p-3">
                    <?php foreach ($faturasRecorrentes as $fr): ?>
                        <?php
$statusClasses = [
    'pendente' => 'bg-warning text-dark',
    'pago' => 'bg-success',
    'vencido' => 'bg-danger',
    'atrasado' => 'bg-danger',
    'cancelado' => 'bg-secondary'
];

// Proxima data do ciclo pela regra compartilhada (includes/recorrencia.php).
// Antes a tela mostrava so nome/descricao/frequencia: os dados da fatura
// (numero, status, vencimentos) eram buscados no SQL e nunca impressos, e a
// recorrencia parecia vazia - dava a impressao de que a fatura nao existia.
$frProximo = null;
if (($fr['status'] ?? 'ativa') !== 'cancelado'
    && !recorrenciaSemCiclo($fr['frequencia'] ?? '')) {
    // A coluna que o cron consulta tem prioridade; so se ainda nao existir
    // (recorrencia antiga, antes da migracao) deriva da ultima fatura emitida.
    // O dia-ancora precisa ser repassado, senao o dia escolhido no cadastro
    // seria ignorado e a data "escorregaria" (spec 7).
    if (!empty($fr['data_proxima_fatura'])) {
        $frProximo = substr((string)$fr['data_proxima_fatura'], 0, 10);
    } elseif (!empty($fr['ultimo_vencimento'])) {
        $frAncora = recorrenciaAncoraDoRegistro($fr);
        $frProximo = proximoVencimentoRecorrencia(
            $fr['frequencia'],
            $fr['ultimo_vencimento'],
            $frAncora
        );
    }
}
?>
<div class="fr-item mb-2 <?= ($fr['status'] ?? 'ativa') === 'cancelado' ? 'opacity-50' : '' ?>">
                            <div class="fr-row collapsed" data-fr-target="#hist<?= (int)$fr['id'] ?>" aria-expanded="false" aria-controls="hist<?= (int)$fr['id'] ?>" title="Clique para ver as faturas geradas" onclick="return alternarHistoricoRecorrencia(this);">
                                <div class="form-check mb-0" style="min-width:22px;padding-left:1.4em;" onclick="event.stopPropagation();">
                                    <input class="form-check-input bulk-check" type="checkbox" name="ids[]" value="<?= (int)$fr['id'] ?>" id="ck<?= (int)$fr['id'] ?>">
                                    <label class="form-check-label" for="ck<?= (int)$fr['id'] ?>"></label>
                                </div>
                                <div class="fr-cliente">
                                    <span class="fr-dado-label">Nome do Cliente</span>
                                    <strong><?= htmlspecialchars($fr['nome_razao']) ?></strong>
                                    <small class="d-block text-muted"><?= htmlspecialchars($fr['descricao']) ?></small>
                                </div>
                                <div class="fr-dado">
                                    <span class="fr-dado-label">Frequência</span>
                                    <span class="badge bg-info"><?= htmlspecialchars(recorrenciaRotulo($fr['frequencia'])) ?></span>
                                </div>
                                <div class="fr-dado">
                                    <span class="fr-dado-label">Última Fatura</span>
                                    <?php if (!empty($fr['ultimo_numero'])): ?>
                                    <strong><?= htmlspecialchars($fr['ultimo_numero']) ?></strong>
                                    <small class="d-block text-muted">venc. <?= date('d/m/Y', strtotime($fr['ultimo_vencimento'])) ?></small>
                                    <?php else: ?>
                                    <strong class="text-muted">—</strong>
                                    <small class="d-block text-muted">nenhuma emitida</small>
                                    <?php endif; ?>
                                </div>
                                <div class="fr-dado">
                                    <span class="fr-dado-label">Status</span>
                                    <?php if (!empty($fr['ultimo_status'])): ?>
                                    <span class="badge <?= $statusClasses[$fr['ultimo_status']] ?? 'bg-secondary' ?>"><?= ucfirst($fr['ultimo_status']) ?></span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">Sem fatura</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($frProximo !== null): ?>
                                <div class="fr-dado">
                                    <span class="fr-dado-label">Próxima</span>
                                    <strong><?= date('d/m/Y', strtotime($frProximo)) ?></strong>
                                </div>
                                <?php endif; ?>
                                <div class="fr-acoes">
                                    <span class="fr-chevron text-muted"><i class="bi bi-chevron-down"></i></span>
                                    <?php if (in_array(($fr['frequencia'] ?? ''), ['lote', 'carne'], true)): ?>
                                    <a href="carne_pdf.php?fr=<?= (int)$fr['id'] ?>" target="_blank" class="acao-btn acao-btn-success ms-2" title="Visualizar / Baixar carnê (PDF com todos os boletos)" onclick="event.stopPropagation();"><i class="bi bi-file-earmark-pdf"></i></a>
                                    <?php endif; ?>
                                    <?php if (($fr['status'] ?? 'ativa') !== 'cancelado'): ?>
                                    <a href="?excluir=<?= (int)$fr['id'] ?>" class="acao-btn acao-btn-danger ms-2" title="Excluir recorrência" onclick="event.stopPropagation(); return confirmarExcluirRecorrencia(this, <?= htmlspecialchars(json_encode((string)$fr['nome_razao'], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_TAG), ENT_QUOTES) ?>);"><i class="bi bi-trash3"></i></a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="collapse fr-hist" id="hist<?= (int)$fr['id'] ?>">
                                <div class="fr-hist-inner">
                                    <?php $faturasFr = $faturasPorRecorrencia[$fr['id']] ?? []; ?>
                                    <?php if (empty($faturasFr)): ?>
                                        <span class="text-muted small">Nenhuma fatura gerada ainda para esta recorrência.</span>
                                    <?php else: ?>
                                    <table class="table table-sm mb-0 align-middle">
                                        <thead>
                                            <tr>
                                                <th>Fatura</th>
                                                <th>Emissão</th>
                                                <th>Vencimento</th>
                                                <th>Valor</th>
                                                <th>Status</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($faturasFr as $fh): ?>
                                            <tr class="<?= in_array($fh['status'], ['pendente','vencido','atrasado']) && $fh['data_vencimento'] < date('Y-m-d') ? 'table-danger' : '' ?>">
                                                <td><strong><?= htmlspecialchars($fh['numero']) ?></strong><?php if (!empty($fh['total_parcelas'])): ?><small class="d-block text-muted">Parcela <?= (int)$fh['parcela_atual'] ?>/<?= (int)$fh['total_parcelas'] ?></small><?php endif; ?></td>
                                                <td><?= date('d/m/Y', strtotime($fh['data_emissao'])) ?></td>
                                                <td><?= date('d/m/Y', strtotime($fh['data_vencimento'])) ?></td>
                                                <td>R$ <?= number_format($fh['valor_final'], 2, ',', '.') ?></td>
                                                <td><span class="badge <?= $statusClasses[$fh['status']] ?? 'bg-secondary' ?>"><?= ucfirst($fh['status']) ?></span></td>
                                                <td>
                                                    <div class="d-inline-flex gap-1 align-items-center">
                                                        <?php if (!in_array($fh['status'], ['pago', 'cancelado'])): ?>
                                                        <a href="#" class="acao-btn acao-btn-success" title="Enviar fatura via WhatsApp" data-bs-toggle="modal" data-bs-target="#modalEnviarWhatsApp" data-url="?fatura_whatsapp=<?= $fh['id'] ?>"><i class="bi bi-whatsapp"></i></a>
                                                        <a href="#" class="acao-btn acao-btn-primary" title="Enviar e-mail de cobrança" data-bs-toggle="modal" data-bs-target="#modalEnviarEmail" data-url="?fatura_enviar=<?= $fh['id'] ?>"><i class="bi bi-envelope-fill"></i></a>
                                                        <a href="?fatura_boleto=<?= $fh['id'] ?>" target="_blank" class="acao-btn acao-btn-secondary" title="Gerar boleto em PDF"><i class="bi bi-upc-scan"></i></a>
                                                        <button type="button" class="acao-btn acao-btn-dark" title="Copiar código PIX copia e cola" data-fatura="<?= $fh['id'] ?>" onclick="copiarPixFatura(this)"><i class="bi bi-qr-code"></i></button>
                                                        <a href="#" class="acao-btn acao-btn-success" title="Pago" data-bs-toggle="modal" data-bs-target="#modalMarcarPago" data-url="?fatura_pago=<?= $fh['id'] ?>"><i class="bi bi-check-circle-fill"></i></a>
                                                        <a href="#" class="acao-btn acao-btn-warning" title="Cancelar" onclick="event.preventDefault(); showConfirm('Cancelar Fatura','Deseja cancelar esta fatura?','?fatura_cancelar=<?= $fh['id'] ?>','primary')"><i class="bi bi-x-circle-fill"></i></a>
                                                        <?php endif; ?>
                                                        <?php if ($fh['status'] === 'pago'): ?>
                                                        <a href="recibos.php?fatura_id=<?= (int)$fh['id'] ?>" class="acao-btn acao-btn-primary" title="Ver Recibo"><i class="bi bi-file-earmark-text"></i></a>
                                                        <?php endif; ?>
                                                        <a href="#" class="acao-btn acao-btn-danger" title="Excluir" data-bs-toggle="modal" data-bs-target="#modalExcluir" data-url="?fatura_excluir=<?= $fh['id'] ?>"><i class="bi bi-trash3"></i></a>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                </form>
            <?php endif; ?>
            <?php if ($totalFaturasRecorrentes > 0): ?>
            <div class="p-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <small class="text-muted">Mostrando <?= count($faturasRecorrentes) ?> de <?= $totalFaturasRecorrentes ?> registros</small>
                    <select class="form-select form-select-sm" style="width:auto;" onchange="window.location.href='?page=1&per_page='+this.value+'&filtro_status=<?= urlencode($filtro_status) ?>&filtro_busca=<?= urlencode($filtro_busca) ?>'">
                        <?php foreach ([10,20,50,100] as $opt): ?>
                            <option value="<?= $opt ?>" <?= $perPage === $opt ? 'selected' : '' ?>><?= $opt ?>/página</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php $baseUrl = '?per_page=' . $perPage . '&filtro_status=' . urlencode($filtro_status) . '&filtro_busca=' . urlencode($filtro_busca); ?>
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $baseUrl ?>&page=<?= $page - 1 ?>">«</a>
                        </li>
                        <?php
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        if ($start > 1): ?>
                            <li class="page-item"><a class="page-link" href="<?= $baseUrl ?>&page=1">1</a></li>
                            <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                        <?php endif;
                        for ($i = $start; $i <= $end; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="<?= $baseUrl ?>&page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor;
                        if ($end < $totalPages): ?>
                            <?php if ($end < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                            <li class="page-item"><a class="page-link" href="<?= $baseUrl ?>&page=<?= $totalPages ?>"><?= $totalPages ?></a></li>
                        <?php endif; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $baseUrl ?>&page=<?= $page + 1 ?>">»</a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEnviarEmail" tabindex="-1" aria-labelledby="modalEnviarEmailLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="modalEnviarEmailLabel"><i class="bi bi-envelope-fill me-2"></i>Enviar Cobrança</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                Deseja reenviar a cobrança por e-mail?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <a href="#" id="btnConfirmarEnviar" class="btn btn-primary btn-sm">Sim, enviar</a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEnviarWhatsApp" tabindex="-1" aria-labelledby="modalEnviarWhatsAppLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="modalEnviarWhatsAppLabel"><i class="bi bi-whatsapp me-2"></i>Enviar Fatura via WhatsApp</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                Deseja enviar a cobrança via WhatsApp?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <a href="#" id="btnConfirmarEnviarWhatsApp" class="btn btn-success btn-sm">Sim, enviar</a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalMarcarPago" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-check-circle-fill me-2 text-success"></i>Marcar como Paga</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                Deseja marcar esta fatura como paga?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <a href="#" id="btnConfirmarPago" class="btn btn-success btn-sm">Sim, marcar como paga</a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalExcluir" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-trash3 me-2 text-danger"></i>Excluir Fatura</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                Deseja excluir esta fatura? Esta ação não pode ser desfeita.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <a href="#" id="btnConfirmarExcluir" class="btn btn-danger btn-sm">Sim, excluir</a>
            </div>
        </div>
    </div>
</div>

<script>
// Expande/fecha o historico de faturas da recorrencia.
// Usa o Collapse do Bootstrap quando disponivel e cai para um toggle
// proprio de classes se o bundle nao tiver carregado, para que a lista
// abra de qualquer forma. A classe "collapsed" e o aria-expanded sao
// sincronizados aqui porque o data-api do Bootstrap so faz isso quando
// o gatilho tem data-bs-toggle, e aqui o controle e via JS.
function alternarHistoricoRecorrencia(row) {
    var alvo = document.querySelector(row.getAttribute('data-fr-target') || '');
    if (!alvo) return true;
    var marcarAberto = function () {
        row.classList.remove('collapsed');
        row.setAttribute('aria-expanded', 'true');
    };
    var marcarFechado = function () {
        row.classList.add('collapsed');
        row.setAttribute('aria-expanded', 'false');
    };
    var estavaAberto = alvo.classList.contains('show');
    try {
        if (window.bootstrap && bootstrap.Collapse) {
            var inst = bootstrap.Collapse.getOrCreateInstance(alvo, { toggle: false });
            if (estavaAberto) inst.hide(); else inst.show();
            if (estavaAberto) marcarFechado(); else marcarAberto();
            return false;
        }
    } catch (e) { /* segue para o fallback */ }
    if (estavaAberto) {
        alvo.classList.remove('show');
        marcarFechado();
    } else {
        alvo.classList.add('show');
        marcarAberto();
    }
    return false;
}

// Confirma a exclusao da recorrencia. O link tem href real, entao a
// exclusao acontece mesmo se o painel nao conseguir abrir o modal.
function confirmarExcluirRecorrencia(link, nome) {
    var url = link.getAttribute('href');
    var msg = 'Excluir permanentemente a recorrencia de ' + nome +
              ' e todas as suas faturas? Esta acao nao pode ser desfeita.';
    try {
        if (typeof showConfirm === 'function' && document.getElementById('confirmModal')) {
            showConfirm('Excluir Recorrencia', msg, url);
            return false;
        }
    } catch (e) { /* segue para o confirm nativo */ }
    return window.confirm(msg);
}

// Liga um listener sem derrubar o script inteiro caso o elemento nao exista.
function on(id, evento, fn) {
    var el = document.getElementById(id);
    if (el) el.addEventListener(evento, fn);
}

// Carnê: exibe dinamicamente o campo obrigatorio "Quantidade de boletos",
// oculta controles de atraso do cron e atualiza o botao para "Criar Carnê".
function aplicarModoCarne() {
    var freq = document.getElementById('frequencia');
    var wrapQtd = document.getElementById('wrapQtdBoletos');
    var qtdInput = document.getElementById('quantidade_boletos');
    var wrapPolitica = document.getElementById('wrapPoliticaAtraso');
    var wrapCronHint = document.getElementById('wrapCronHint');
    var wrapCarneHint = document.getElementById('wrapCarneHint');
    var btnTexto = document.getElementById('btnSubmitTexto');
    if (!freq) return;

    var isCarne = (freq.value === 'carne' || freq.value === 'lote');

    if (wrapQtd) wrapQtd.style.display = isCarne ? 'block' : 'none';
    if (qtdInput) {
        qtdInput.required = isCarne;
        qtdInput.disabled = !isCarne;
        if (isCarne && (!qtdInput.value || parseInt(qtdInput.value, 10) < 1)) {
            qtdInput.value = 12;
        }
    }
    if (wrapPolitica) wrapPolitica.style.display = isCarne ? 'none' : 'block';
    if (wrapCronHint) wrapCronHint.style.display = isCarne ? 'none' : 'block';
    if (wrapCarneHint) wrapCarneHint.style.display = isCarne ? 'block' : 'none';
    if (btnTexto) {
        btnTexto.textContent = isCarne ? 'Criar Carnê' : 'Criar Fatura';
    }
}
on('frequencia', 'change', aplicarModoCarne);
document.addEventListener('DOMContentLoaded', aplicarModoCarne);

// Evita duplo clique em formulario de criacao.
function travarFormulario(form, evento) {
    if (!form) return;
    form.addEventListener(evento, function () {
        var botao = form.querySelector('button[type="submit"]');
        if (botao) { botao.disabled = true; botao.classList.add('disabled'); }
    }, true);
}

on('modalEnviarEmail', 'show.bs.modal', function(event) {
    var button = event.relatedTarget;
    var url = button.getAttribute('data-url');
    if (!url) { url = '?enviar=' + button.getAttribute('data-id'); }
    document.getElementById('btnConfirmarEnviar').href = url;
});
on('modalEnviarWhatsApp', 'show.bs.modal', function(event) {
    var button = event.relatedTarget;
    var url = button.getAttribute('data-url');
    if (!url) { url = '?whatsapp=' + button.getAttribute('data-id'); }
    document.getElementById('btnConfirmarEnviarWhatsApp').href = url;
});
on('modalMarcarPago', 'show.bs.modal', function(event) {
    var button = event.relatedTarget;
    if (!button) return;
    var url = button.getAttribute('data-url');
    if (!url) { url = '?pago=' + button.getAttribute('data-id'); }
    document.getElementById('btnConfirmarPago').href = url;
});
on('modalExcluir', 'show.bs.modal', function(event) {
    var button = event.relatedTarget;
    if (!button) return;
    var url = button.getAttribute('data-url');
    if (!url) { url = '?excluir=' + button.getAttribute('data-id'); }
    document.getElementById('btnConfirmarExcluir').href = url;
});
on('selectAll', 'change', function() {
    var checks = document.querySelectorAll('.bulk-check');
    for (var i = 0; i < checks.length; i++) { checks[i].checked = this.checked; }
});

// Mascara monetária brasileira: R$ 0.000,00
function mascaraMoeda(el) {
    var v = el.value.replace(/\D/g, '');
    if (!v || parseInt(v, 10) === 0) {
        el.value = '';
        return;
    }
    var num = (parseInt(v, 10) / 100).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
    el.value = 'R$ ' + num;
}

var campoValorEl = document.getElementById('campo_valor');
if (campoValorEl) {
    campoValorEl.addEventListener('paste', function(e) {
        e.preventDefault();
        var pasted = (e.clipboardData || window.clipboardData).getData('text');
        if (!pasted) return;
        pasted = pasted.trim().replace(/^R\$\s*/i, '');
        var num = 0;
        if (pasted.indexOf(',') !== -1) {
            num = parseFloat(pasted.replace(/\./g, '').replace(',', '.'));
        } else {
            num = parseFloat(pasted);
        }
        if (isNaN(num) || num <= 0) {
            this.value = '';
        } else {
            this.value = 'R$ ' + num.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    });
}

// Modal de carregamento enquanto a fatura e gerada. Vale para fatura
// avulsa e para recorrencia: os dois saem do mesmo formulario.
var formNovaFatura = document.getElementById('formNovaFatura');
if (formNovaFatura) {
    // Validar valor antes de travar o formulário
    formNovaFatura.addEventListener('submit', function (e) {
        var cv = document.getElementById('campo_valor');
        if (cv) {
            var raw = cv.value.replace(/\D/g, '');
            if (!raw || parseInt(raw, 10) <= 0) {
                e.preventDefault();
                e.stopImmediatePropagation();
                cv.focus();
                cv.classList.add('is-invalid');
                alert('Por favor, informe um valor válido maior que zero (R$ 0,00).');
                return false;
            } else {
                cv.classList.remove('is-invalid');
            }
        }
    }, true);

    travarFormulario(formNovaFatura, 'submit');
    formNovaFatura.addEventListener('submit', function () {
        var el = document.getElementById('modalCriando');
        if (!el) return;
        try {
            if (window.bootstrap && bootstrap.Modal) {
                new bootstrap.Modal(el).show();
            } else {
                el.style.display = 'block';
                el.classList.add('show');
            }
        } catch (e) { /* a pagina recarrega de qualquer forma */ }
    });
}
function copiarPixFatura(btn) {
    var id = btn.getAttribute('data-fatura');
    if (!id) return;
    var icon = btn.querySelector('i');
    var iconOriginal = icon ? icon.className : '';
    if (icon) { icon.className = 'fas fa-spinner fa-spin'; }
    fetch('emissao.php?fatura_pix=' + id, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (!d || !d.ok || !d.pix) {
                showAlert('Aviso', (d && d.erro) ? d.erro : 'Nenhum código PIX disponível para esta fatura.');
                if (icon) { icon.className = iconOriginal; }
                return;
            }
            var codigo = d.pix;
            var done = function() {
                if (icon) {
                    icon.className = 'fas fa-check';
                    btn.classList.replace('btn-outline-dark', 'btn-success');
                    setTimeout(function() {
                        icon.className = iconOriginal;
                        btn.classList.replace('btn-success', 'btn-outline-dark');
                    }, 1500);
                }
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(codigo).then(done).catch(function() { fallbackCopiarPix(codigo, done); });
            } else {
                fallbackCopiarPix(codigo, done);
            }
        })
        .catch(function() {
            showAlert('Erro', 'Erro ao buscar o código PIX.');
            if (icon) { icon.className = iconOriginal; }
        });
}
function fallbackCopiarPix(codigo, done) {
    var ta = document.createElement('textarea');
    ta.value = codigo;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); } catch (e) {}
    document.body.removeChild(ta);
    done();
}
</script>

<div class="modal fade" id="modalCriando" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content text-center">
            <div class="modal-body py-4">
                <div class="spinner-border text-primary mb-3" role="status" style="width:3rem;height:3rem;"></div>
                <h6>Criando fatura...</h6>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
