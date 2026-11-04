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

if (isset($_GET['fatura_pago'])) {
    $id = intval($_GET['fatura_pago']);
    $stmt = $pdo->prepare("UPDATE faturas SET status = 'pago', data_pagamento = CURDATE() WHERE id = ? AND admin_id = ?");
    $stmt->execute([$id, $adminIdE]);
    header('Location: emissao.php?msg=pago');
    exit;
}

if (isset($_GET['fatura_desmarcar_pago'])) {
    $id = intval($_GET['fatura_desmarcar_pago']);
    $stmt = $pdo->prepare("UPDATE faturas SET status = 'pendente', data_pagamento = NULL WHERE id = ? AND admin_id = ?");
    $stmt->execute([$id, $adminIdE]);
    header('Location: emissao.php?msg=fatura_desmarcada');
    exit;
}

if (isset($_GET['fatura_cancelar'])) {
    $id = intval($_GET['fatura_cancelar']);
    $stmt = $pdo->prepare("SELECT * FROM faturas WHERE id = ? AND admin_id = ?");
    $stmt->execute([$id, $adminIdE]);
    $fat = $stmt->fetch();
    if ($fat) {
        cancelarCobrancaFatura($fat);
        $stmt = $pdo->prepare("UPDATE faturas SET status = 'cancelado' WHERE id = ? AND admin_id = ?");
        $stmt->execute([$id, $adminIdE]);
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
        excluirFaturasComLapide($pdo, [$fat], 'excluir_fatura');
    }
    header('Location: emissao.php?msg=fatura_excluida');
    exit;
}

// Salvar fatura recorrente ou Carnê
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = intval($_POST['cliente_id'] ?? 0);
    $descricao = trim($_POST['descricao'] ?? '');
    
    // Tratamento de valor monetário brasileiro (ex: R$ 1.500,50 ou 1500.50)
    $valorRaw = trim((string)($_POST['valor'] ?? '0'));
    if (strpos($valorRaw, ',') !== false) {
        $valorLimpo = preg_replace('/[^\d,]/', '', $valorRaw);
        $valorLimpo = str_replace(',', '.', $valorLimpo);
    } else {
        $valorLimpo = preg_replace('/[^\d.]/', '', $valorRaw);
    }
    $valor = round((float)$valorLimpo, 2);
    $frequencia = $_POST['frequencia'] ?? 'mensal';
    
    $validacaoVenc = recorrenciaValidarPrimeiroVencimento($_POST['data_primeiro_vencimento'] ?? '');
    $primeiroVencimentoData = $validacaoVenc['data'];
    $dia_vencimento = $validacaoVenc['ok'] ? (int)substr($primeiroVencimentoData, 8, 2) : 1;
    $data_fim = !empty($_POST['data_fim']) ? $_POST['data_fim'] : null;
    $quantidade_transacoes = null;
    $dias_vencimento = 0;
    $politica_atraso = in_array($_POST['politica_atraso'] ?? '', ['todas', 'mais_recente'], true)
        ? $_POST['politica_atraso'] : 'todas';

    if (!in_array($frequencia, RECORRENCIAS_CADASTRO, true)) {
        $frequencia = 'mensal';
    }
    $antecedencia_emissao = recorrenciaAntecedenciaEmissao($frequencia);

    // ===== CARNÊ =====
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
                } else {
                    $limiteFat = verificarLimitePlano('faturas', $adminIdE);
                    if (!$limiteFat['ok']) {
                        $mensagem = $limiteFat['mensagem'];
                        $tipo = 'danger';
                    } else {
                        $numeroCarne = generateInvoiceNumber();
                        $ancora = $dia_vencimento;
                        $dataBase = date('Y-m-d');
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

                            $pixCopia = (string)($resBol['qr_code_copia_cola'] ?? '');
                            $pixQr = (string)($resBol['qr_code'] ?? '');
                            $pixCod = $temBoleto ? (string)($resBol['payment_id'] ?? '') : '';
                            $precisaPixExtra = in_array($apiAgora, ['inter', 'bb'], true) ? ($pixCopia === '') : true;
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

                        // Envio imediato da 1ª parcela
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
            $stChkCli = $pdo->prepare("SELECT id FROM clientes WHERE id = ? AND admin_id = ?");
            $stChkCli->execute([$cliente_id, $adminIdE]);
            if (!$stChkCli->fetch()) {
                $mensagem = 'Cliente inválido.';
                $tipo = 'danger';
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

                    $stCliData = $pdo->prepare("SELECT * FROM clientes WHERE id = ? AND admin_id = ?");
                    $stCliData->execute([$cliente_id, $adminIdE]);
                    $cliData = $stCliData->fetch() ?: [];

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

                    $pixCopia = (string)($resBol['qr_code_copia_cola'] ?? '');
                    $pixQr = (string)($resBol['qr_code'] ?? '');
                    $pixCod = $temBoleto ? (string)($resBol['payment_id'] ?? '') : '';
                    $precisaPixExtra = in_array($apiAgora, ['inter', 'bb'], true) ? ($pixCopia === '') : true;
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

// Carregar clientes ativos
$stmtCliList = $pdo->prepare("SELECT id, nome_razao, cpf_cnpj FROM clientes WHERE ativo = 1 AND admin_id = ? ORDER BY nome_razao");
$stmtCliList->execute([$adminIdE]);
$clientes = $stmtCliList->fetchAll();

// Filtros e listagem de recorrências/carnês
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

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$faturasRecorrentes = $stmt->fetchAll();

// Faturas filhas de cada recorrência
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

// Mensagens
if (isset($_GET['msg'])) {
    $msgs = [
        'salvo' => ['Cobrança cadastrada e 1ª fatura emitida com sucesso!', 'success'],
        'pago' => ['Fatura marcada como liquidada!', 'success'],
        'cancelado' => ['Operação cancelada com sucesso.', 'warning'],
        'excluido' => ['Registro excluído com sucesso.', 'warning'],
        'fatura_cancelada' => ['Fatura cancelada.', 'warning'],
        'fatura_desmarcada' => ['Fatura retornada para status pendente.', 'warning'],
        'fatura_excluida' => ['Fatura excluída.', 'warning'],
        'enviado' => ['E-mail de cobrança enviado!', 'success'],
        'whatsapp_enviado' => ['Notificação via WhatsApp enviada!', 'success'],
        'carne_criado' => ['Carnê gerado com sucesso!', 'success'],
    ];
    if (isset($msgs[$_GET['msg']])) {
        $mensagem = $msgs[$_GET['msg']][0];
        $tipo = $msgs[$_GET['msg']][1];
    }
}

$pageTitle = 'Emissão de Faturas & Carnês';
$pageCategory = 'Cobranças';

include __DIR__ . '/../includes_v2/header.php';
include __DIR__ . '/../includes_v2/sidebar_admin.php';
?>

<?php if ($mensagem): ?>
    <div class="alert alert-<?= $tipo ?> alert-dismissible fade show border-0 shadow-sm mb-4" style="border-radius:12px;">
        <?= htmlspecialchars($mensagem) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- FORMULÁRIO DE NOVA FATURA / CARNÊ -->
<div class="fintech-card mb-4">
    <div class="fintech-card-header">
        <h2 class="fintech-card-title">
            <i class="bi bi-file-earmark-plus text-primary"></i> Nova Cobrança / Emissão de Carnê
        </h2>
    </div>

    <form method="POST" id="formNovaFatura">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="fintech-form-label">Cliente *</label>
                <select name="cliente_id" class="form-select" required>
                    <option value="">Selecione o cliente...</option>
                    <?php foreach ($clientes as $c): ?>
                        <option value="<?= $c['id'] ?>">
                            <?= htmlspecialchars($c['nome_razao']) ?> (<?= htmlspecialchars($c['cpf_cnpj']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="fintech-form-label">Descrição da Cobrança *</label>
                <input type="text" name="descricao" class="form-control" placeholder="Ex: Mensalidade de Consultoria" required>
            </div>
            <div class="col-md-2">
                <label class="fintech-form-label">Valor (R$) *</label>
                <input type="text" name="valor" id="campo_valor" class="form-control" placeholder="R$ 0,00" required inputmode="numeric" oninput="mascaraMoeda(this)" autocomplete="off">
            </div>
            <div class="col-md-2">
                <label class="fintech-form-label">Frequência</label>
                <select name="frequencia" id="frequencia" class="form-select">
                    <?php foreach (RECORRENCIAS_CADASTRO as $fq): ?>
                        <option value="<?= $fq ?>" <?= $fq === 'mensal' ? 'selected' : '' ?>>
                            <?= htmlspecialchars(recorrenciaRotulo($fq)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="fintech-form-label">Vencimento da 1ª Transação *</label>
                <input type="date" name="data_primeiro_vencimento" id="data_primeiro_vencimento" class="form-control" min="<?= date('Y-m-d') ?>" required>
            </div>

            <!-- Campo dinâmico para Carnê -->
            <div class="col-md-3" id="wrapQtdBoletos" style="display:none;">
                <label class="fintech-form-label">Quantidade de Parcelas (Carnê) *</label>
                <input type="number" name="quantidade_boletos" id="quantidade_boletos" class="form-control" min="1" max="72" value="12" placeholder="Ex: 12">
                <small class="text-muted" style="font-size:11px;">Gera boletos mensais consecutivos</small>
            </div>

            <div class="col-md-3" id="wrapPoliticaAtraso">
                <label class="fintech-form-label">Política em Atraso</label>
                <select name="politica_atraso" class="form-select">
                    <option value="todas">Emitir todas as pendentes</option>
                    <option value="mais_recente">Emitir apenas a mais recente</option>
                </select>
            </div>

            <div class="col-md-3 d-flex align-items-end ms-auto">
                <button type="submit" id="btnSubmitEmissao" class="btn-fintech-primary w-100 justify-content-center">
                    <i class="bi bi-check2"></i> <span id="btnSubmitTexto">Criar Cobrança</span>
                </button>
            </div>
        </div>
    </form>
</div>

<!-- LISTAGEM DE CARNÊS E RECORRÊNCIAS -->
<div class="fintech-table-container">
    <div class="p-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h3 class="fintech-card-title m-0" style="font-size:15px;">
            <i class="bi bi-arrow-repeat text-muted"></i> Recorrências e Carnês Cadastrados (<?= count($faturasRecorrentes) ?>)
        </h3>

        <form method="GET" class="d-flex align-items-center gap-2">
            <input type="text" name="filtro_busca" class="form-control form-control-sm" placeholder="Buscar por cliente, documento ou fatura..." value="<?= htmlspecialchars($filtro_busca) ?>" style="width:260px;">
            <button type="submit" class="btn-fintech-secondary btn-sm"><i class="bi bi-search"></i></button>
        </form>
    </div>

    <div class="table-responsive">
        <table class="fintech-table">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Descrição</th>
                    <th>Valor / Frequência</th>
                    <th>Próximo Vencimento</th>
                    <th>Status</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($faturasRecorrentes)): ?>
                <tr>
                    <td colspan="6" class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                        Nenhuma recorrência ou carnê encontrado.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($faturasRecorrentes as $fr): 
                        $stRec = strtolower($fr['ultimo_status'] ?? 'pendente');
                        $badgeRec = 'fintech-badge-pendente';
                        if ($stRec === 'pago') $badgeRec = 'fintech-badge-pago';
                        elseif ($stRec === 'vencido' || $stRec === 'atrasado') $badgeRec = 'fintech-badge-vencido';
                        elseif ($stRec === 'cancelado') $badgeRec = 'fintech-badge-cancelado';
                    ?>
                    <tr>
                        <td>
                            <strong class="d-block text-dark"><?= htmlspecialchars($fr['nome_razao']) ?></strong>
                            <small class="text-muted"><?= htmlspecialchars($fr['cpf_cnpj']) ?></small>
                        </td>
                        <td>
                            <span class="text-dark fw-medium"><?= htmlspecialchars($fr['descricao']) ?></span>
                            <?php if ($fr['frequencia'] === 'carne'): ?>
                                <span class="badge bg-light text-primary border ms-1" style="font-size:10px;">Carnê (<?= $fr['quantidade_transacoes'] ?>x)</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="fintech-table-val">R$ <?= number_format($fr['valor'], 2, ',', '.') ?></span>
                            <small class="text-muted d-block" style="font-size:11px;"><?= ucfirst($fr['frequencia']) ?></small>
                        </td>
                        <td>
                            <?php if (!empty($fr['proximo_vencimento'])): ?>
                                <span class="text-dark"><?= date('d/m/Y', strtotime($fr['proximo_vencimento'])) ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="fintech-badge <?= $badgeRec ?>">
                                <?= ucfirst($stRec) ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <?php if ($fr['frequencia'] === 'carne'): ?>
                                    <a href="/cobranca/admin/carne_pdf.php?fr=<?= $fr['id'] ?>" target="_blank" class="btn-fintech-icon" title="Imprimir / Baixar Carnê Completo">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($fr['ultimo_link'])): ?>
                                    <a href="<?= htmlspecialchars($fr['ultimo_link']) ?>" target="_blank" class="btn-fintech-icon" title="Abrir Link da Fatura">
                                        <i class="bi bi-box-arrow-up-right"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="emissao.php?enviar=<?= $fr['id'] ?>" class="btn-fintech-icon" title="Enviar E-mail" onclick="return confirm('Enviar e-mail para o cliente?');">
                                    <i class="bi bi-envelope"></i>
                                </a>
                                <a href="emissao.php?whatsapp=<?= $fr['id'] ?>" class="btn-fintech-icon" title="Enviar WhatsApp" onclick="return confirm('Enviar cobrança via WhatsApp?');">
                                    <i class="bi bi-whatsapp"></i>
                                </a>
                                <a href="emissao.php?excluir=<?= $fr['id'] ?>" class="btn-fintech-icon text-danger" title="Excluir" onclick="return confirm('Deseja excluir esta recorrência e suas faturas?');">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Alternância dinâmica de Carnê
var freqSelect = document.getElementById('frequencia');
var wrapQtd = document.getElementById('wrapQtdBoletos');
var btnTexto = document.getElementById('btnSubmitTexto');

function checarModoCarne() {
    var isCarne = freqSelect.value === 'carne';
    if (wrapQtd) wrapQtd.style.display = isCarne ? 'block' : 'none';
    if (btnTexto) btnTexto.textContent = isCarne ? 'Gerar Carnê Completo' : 'Criar Cobrança';
}

if (freqSelect) {
    freqSelect.addEventListener('change', checarModoCarne);
    checarModoCarne();
}

// Máscara monetária R$ 0.000,00
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
</script>

<?php include __DIR__ . '/../includes_v2/footer.php'; ?>

