<?php
// =====================================================
// INTEGRAÇÃO PIX (BANCO INTER) - SUPERADMIN
// Credenciais SEPARADAS das do painel admin (prefixo super_)
// Responsável pelos pagamentos de planos dos admins
// =====================================================

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/settings.php';

function getConfigInterSuper() {
    $chaves = ['super_inter_client_id', 'super_inter_client_secret', 'super_inter_conta', 'super_inter_webhook_url', 'super_inter_cert_crt', 'super_inter_cert_key', 'super_inter_cert_webhook'];
    $config = [];
    foreach ($chaves as $chave) {
        $config[$chave] = getConfig($chave, '');
    }
    return $config;
}

function resolverCaminhoCertSuper($caminho) {
    if (!empty($caminho) && !file_exists($caminho)) {
        $nome = basename(str_replace('\\', '/', $caminho));
        $alternativo = __DIR__ . '/inter_certs_super/' . $nome;
        if (file_exists($alternativo)) {
            return $alternativo;
        }
    }
    return $caminho;
}

function getInterSuperBaseUrl() {
    $config = getConfigInterSuper();
    $certCrt = resolverCaminhoCertSuper($config['super_inter_cert_crt'] ?? '');
    if (!empty($certCrt) && file_exists($certCrt)) {
        $certData = openssl_x509_parse(file_get_contents($certCrt));
        $issuerCn = $certData['issuer']['CN'] ?? '';
        $issuerO = $certData['issuer']['O'] ?? '';
        if (stripos($issuerCn, 'UAT') !== false || stripos($issuerO, 'UAT') !== false) {
            return 'https://cdpj-sandbox.partners.uatinter.co';
        }
    }
    return 'https://cdpj.partners.bancointer.com.br';
}

function obterTokenInterSuper() {
    $config = getConfigInterSuper();
    if (empty($config['super_inter_client_id']) || empty($config['super_inter_client_secret'])) {
        return ['erro' => 'Credenciais do Banco Inter não configuradas.'];
    }
    $certCrt = resolverCaminhoCertSuper($config['super_inter_cert_crt'] ?? '');
    $certKey = resolverCaminhoCertSuper($config['super_inter_cert_key'] ?? '');
    if (empty($certCrt) || empty($certKey) || !file_exists($certCrt) || !file_exists($certKey)) {
        return ['erro' => 'Certificados do Banco Inter não configurados ou não encontrados.'];
    }
    $baseUrl = getInterSuperBaseUrl();
    $cacheFile = sys_get_temp_dir() . '/inter_super_token_cache_' . md5($baseUrl) . '.json';
    if (file_exists($cacheFile)) {
        $cache = json_decode(file_get_contents($cacheFile), true);
        if ($cache && !empty($cache['access_token']) && $cache['expires_at'] > time() + 60) {
            return ['sucesso' => true, 'access_token' => $cache['access_token']];
        }
    }
    $params = http_build_query([
        'client_id' => $config['super_inter_client_id'],
        'client_secret' => $config['super_inter_client_secret'],
        'grant_type' => 'client_credentials',
        'scope' => 'cob.read cob.write boleto-cobranca.read boleto-cobranca.write pagamento-pix.read pagamento-pix.write',
    ]);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => getInterSuperBaseUrl() . '/oauth/v2/token',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $params,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_SSLCERT => $certCrt,
        CURLOPT_SSLKEY => $certKey,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    if ($curlError) {
        error_log("[INTER-SUPER] Erro token: " . $curlError);
        return ['erro' => 'Erro de conexão com Banco Inter: ' . $curlError];
    }
    $result = json_decode($response, true);
    error_log("[INTER-SUPER] Token HTTP {$httpCode}");
    if ($httpCode >= 200 && $httpCode < 300 && !empty($result['access_token'])) {
        $cacheData = [
            'access_token' => $result['access_token'],
            'expires_at' => time() + ($result['expires_in'] ?? 3600),
        ];
        file_put_contents($cacheFile, json_encode($cacheData));
        return ['sucesso' => true, 'access_token' => $result['access_token']];
    }
    return ['erro' => 'Erro ao obter token do Banco Inter: ' . ($result['error_description'] ?? ($result['message'] ?? 'HTTP ' . $httpCode))];
}

function criarCobrancaInterSuper($dados) {
    $config = getConfigInterSuper();
    $token = obterTokenInterSuper();
    if (isset($token['erro'])) {
        return $token;
    }
    $certCrt = resolverCaminhoCertSuper($config['super_inter_cert_crt'] ?? '');
    $certKey = resolverCaminhoCertSuper($config['super_inter_cert_key'] ?? '');
    $contaDigitos = preg_replace('/[^0-9]/', '', $config['super_inter_conta'] ?? '');
    $conta = ltrim(substr($contaDigitos, 4), '0');
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => getInterSuperBaseUrl() . '/cobranca/v3/cobrancas',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($dados),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token['access_token'],
            'x-conta-corrente: ' . $conta,
        ],
        CURLOPT_SSLCERT => $certCrt,
        CURLOPT_SSLKEY => $certKey,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    if ($curlError) {
        error_log("[INTER-SUPER] Erro de conexão criarCobranca: " . $curlError);
        return ['erro' => 'Erro de conexão com Banco Inter: ' . $curlError];
    }
    $result = json_decode($response, true);
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['sucesso' => true, 'dados' => $result];
    }
    $erroMsg = $result['title'] ?? 'Erro ao criar cobrança';
    $detalhes = $result['detail'] ?? '';
    $violacoes = '';
    if (!empty($result['violacoes'])) {
        foreach ($result['violacoes'] as $v) {
            $violacoes .= '[' . ($v['campo'] ?? 'campo?') . '] ' . ($v['razao'] ?? '') . '  ';
        }
    }
    return ['erro' => trim($erroMsg . ' ' . $detalhes . ' ' . $violacoes)];
}

function consultarCobrancaInterSuper($codigoSolicitacao) {
    $config = getConfigInterSuper();
    $token = obterTokenInterSuper();
    if (isset($token['erro'])) {
        return ['erro' => $token['erro']];
    }
    $certCrt = resolverCaminhoCertSuper($config['super_inter_cert_crt'] ?? '');
    $certKey = resolverCaminhoCertSuper($config['super_inter_cert_key'] ?? '');
    $contaDigitos = preg_replace('/[^0-9]/', '', $config['super_inter_conta'] ?? '');
    $conta = ltrim(substr($contaDigitos, 4), '0');
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => getInterSuperBaseUrl() . '/cobranca/v3/cobrancas/' . $codigoSolicitacao,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token['access_token'],
            'x-conta-corrente: ' . $conta,
        ],
        CURLOPT_SSLCERT => $certCrt,
        CURLOPT_SSLKEY => $certKey,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    if ($curlError) {
        error_log("[INTER-SUPER] Erro consultarCobranca: " . $curlError);
        return ['erro' => 'Erro de conexão com Banco Inter: ' . $curlError];
    }
    $result = json_decode($response, true);
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['sucesso' => true, 'dados' => $result];
    }
    return ['erro' => 'Erro ao consultar cobrança: HTTP ' . $httpCode];
}

// Cancela uma cobrança já gerada no Banco Inter (PATCH situação=CANCELADA).
// Retorna ['sucesso'=>true] se o Inter confirmar o cancelamento.
function cancelarCobrancaInterSuper($codigoSolicitacao) {
    $config = getConfigInterSuper();
    $token = obterTokenInterSuper();
    if (isset($token['erro'])) {
        return ['erro' => $token['erro']];
    }
    $certCrt = resolverCaminhoCertSuper($config['super_inter_cert_crt'] ?? '');
    $certKey = resolverCaminhoCertSuper($config['super_inter_cert_key'] ?? '');
    $contaDigitos = preg_replace('/[^0-9]/', '', $config['super_inter_conta'] ?? '');
    $conta = ltrim(substr($contaDigitos, 4), '0');
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => getInterSuperBaseUrl() . '/cobranca/v3/cobrancas/' . $codigoSolicitacao,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_POSTFIELDS => json_encode(['situacao' => 'CANCELADA']),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token['access_token'],
            'x-conta-corrente: ' . $conta,
        ],
        CURLOPT_SSLCERT => $certCrt,
        CURLOPT_SSLKEY => $certKey,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    if ($curlError) {
        error_log("[INTER-SUPER] Erro cancelarCobranca: " . $curlError);
        return ['erro' => 'Erro de conexão com Banco Inter: ' . $curlError];
    }
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['sucesso' => true];
    }
    $result = json_decode($response, true);
    $erroMsg = $result['detail'] ?? ($result['message'] ?? ('HTTP ' . $httpCode));
    return ['erro' => 'Erro ao cancelar cobrança no Inter: ' . $erroMsg];
}

// Monta o pagador (admin) para a cobrança PIX
function montarPagadorPlano($adminDados) {
    $nome = mb_substr(trim($adminDados['nome'] ?? 'Pagador'), 0, 60);
    $cpfCnpj = preg_replace('/[^0-9]/', '', ($adminDados['cnpj'] ?? '') ?: ($adminDados['cpf'] ?? ''));
    $cpfCnpj = $cpfCnpj ?: '00000000000';
    $tipoPessoa = strlen($cpfCnpj) === 11 ? 'FISICA' : 'JURIDICA';

    return [
        'cpfCnpj' => $cpfCnpj,
        'tipoPessoa' => $tipoPessoa,
        'nome' => $nome,
        'email' => mb_substr($adminDados['email'] ?? '', 0, 100),
        'endereco' => mb_substr($adminDados['logradouro'] ?? 'NAO INFORMADO', 0, 100),
        'numero' => $adminDados['numero'] ?? '0',
        'complemento' => $adminDados['complemento'] ?? '',
        'bairro' => mb_substr($adminDados['bairro'] ?? 'NAO INFORMADO', 0, 50),
        'cidade' => mb_substr($adminDados['cidade'] ?? 'SAO PAULO', 0, 60),
        'uf' => substr(preg_replace('/[^A-Za-z]/', '', $adminDados['estado'] ?? 'SP'), 0, 2) ?: 'SP',
        'cep' => str_pad(preg_replace('/[^0-9]/', '', $adminDados['cep'] ?? ''), 8, '0', STR_PAD_LEFT) ?: '01000000',
    ];
}

// Cria a cobrança PIX no Banco Inter e retorna codigoSolicitacao + QR + copia e cola
function criarCobrancaPixPlano($descricao, $valor, $pagador) {
    $dados = [
        'seuNumero' => substr('PLAN' . date('ymd') . rand(1000, 9999), 0, 15),
        'valorNominal' => (float) $valor,
        'dataVencimento' => date('Y-m-d'),
        'numDiasAgenda' => 7,
        'pagador' => $pagador,
        'mensagem' => ['linha1' => substr($descricao, 0, 40)],
    ];

    $resultado = criarCobrancaInterSuper($dados);
    if (empty($resultado['sucesso'])) {
        return $resultado;
    }

    $resp = $resultado['dados'];
    $codigo = $resp['codigoSolicitacao'] ?? ($resp['cobranca']['codigoSolicitacao'] ?? '');
    if (empty($codigo)) {
        return ['erro' => 'Não foi possível obter o código de solicitação.'];
    }

    $pixCopiaECola = '';
    $qrCode = '';
    $tentativas = 0;
    while (empty($pixCopiaECola) && $tentativas < 8) {
        $tentativas++;
        if ($tentativas > 1) sleep(2);
        $detalhe = consultarCobrancaInterSuper($codigo);
        if ($detalhe && !isset($detalhe['erro'])) {
            $pix = $detalhe['dados']['pix'] ?? ($detalhe['dados']['cobranca']['pix'] ?? []);
            if (!empty($pix)) {
                $pixCopiaECola = $pix['pixCopiaECola'] ?? '';
                $qrCode = $pix['qrcode'] ?? '';
            }
        }
    }

    // Se o Inter não retornar imagem QR, gerar localmente a partir do copia e cola
    if (empty($qrCode) && !empty($pixCopiaECola)) {
        if (file_exists(__DIR__ . '/phpqrcode.php')) {
            $erroReport = error_reporting(0);
            try {
                require_once __DIR__ . '/phpqrcode.php';
                $pixLimpo = str_replace(["\r\n", "\r", "\n"], '', $pixCopiaECola);
                ob_start();
                QRcode::png($pixLimpo, false, QR_ECLEVEL_L, 5, 2);
                $img = ob_get_clean();
                if ($img !== false && !empty($img)) {
                    $qrCode = base64_encode($img);
                }
            } catch (Exception $e) {
                error_log("[INTER-SUPER] Erro gerar QRCode local: " . $e->getMessage());
            }
            error_reporting($erroReport);
        }
    }

    return [
        'sucesso' => true,
        'codigo_solicitacao' => $codigo,
        'qr_code' => $qrCode,
        'pix_copia_cola' => $pixCopiaECola,
    ];
}

// Cria o PIX da cobrança de plano e retorna QR + copia e cola
// $duracaoMeses: 1 (mensal), 3 (trimestral), 6 (semestral), 12 (anual)
function criarPixPlano($adminId, $planoId, $valor, $descricao, $adminDados, $duracaoMeses = 1) {
    $pagador = montarPagadorPlano($adminDados);
    $cobranca = criarCobrancaPixPlano($descricao, $valor, $pagador);
    if (empty($cobranca['sucesso'])) {
        return $cobranca;
    }

    $pdo = getConnection();
    $stmt = $pdo->prepare("INSERT INTO planos_pagamentos (admin_id, plano_id, valor, duracao_meses, descricao, codigo_solicitacao, qr_code, pix_copia_cola, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pendente')");
    $stmt->execute([$adminId, $planoId, $valor, $duracaoMeses, $descricao, $cobranca['codigo_solicitacao'], $cobranca['qr_code'], $cobranca['pix_copia_cola']]);

    return [
        'sucesso' => true,
        'pagamento_id' => (int)$pdo->lastInsertId(),
        'codigo_solicitacao' => $cobranca['codigo_solicitacao'],
        'qr_code' => $cobranca['qr_code'],
        'pix_copia_cola' => $cobranca['pix_copia_cola'],
        'valor' => $valor,
    ];
}

// Gera (ou reutiliza) o PIX de uma fatura de plano já existente (pendente).
// Mantém o mesmo registro em planos_pagamentos, atualizando QR + copia e cola.
function gerarPixFaturaPlano($pagamentoId) {
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT * FROM planos_pagamentos WHERE id = ?");
    $stmt->execute([$pagamentoId]);
    $pg = $stmt->fetch();
    if (!$pg) return ['erro' => 'Fatura não encontrada.'];

    if ($pg['status'] !== 'pendente') {
        return ['erro' => 'Esta fatura não está pendente.'];
    }

    $adminId = (int)$pg['admin_id'];
    $stmt = $pdo->prepare("SELECT * FROM administradores WHERE id = ?");
    $stmt->execute([$adminId]);
    $admin = $stmt->fetch();
    if (!$admin) return ['erro' => 'Administrador não encontrado.'];

    $descricao = $pg['descricao'] ?: 'Renovação de plano';
    $pagador = montarPagadorPlano($admin);
    $cobranca = criarCobrancaPixPlano($descricao, (float)$pg['valor'], $pagador);
    if (empty($cobranca['sucesso'])) {
        return $cobranca;
    }

    $pdo->prepare("UPDATE planos_pagamentos SET codigo_solicitacao=?, qr_code=?, pix_copia_cola=? WHERE id=?")
        ->execute([$cobranca['codigo_solicitacao'], $cobranca['qr_code'], $cobranca['pix_copia_cola'], $pg['id']]);

    return [
        'sucesso' => true,
        'pagamento_id' => (int)$pg['id'],
        'codigo_solicitacao' => $cobranca['codigo_solicitacao'],
        'qr_code' => $cobranca['qr_code'],
        'pix_copia_cola' => $cobranca['pix_copia_cola'],
        'valor' => (float)$pg['valor'],
    ];
}

// Verifica se o plano do admin vence em até $dias e, caso não exista fatura
// pendente de renovação, cria automaticamente a fatura (mensal) para pagamento.
function gerarFaturaRenovacaoAuto($adminId) {
    $adminId = (int)$adminId;
    if ($adminId <= 0) return null;
    $pdo = getConnection();
    if (!$pdo) return null;

    $stmt = $pdo->prepare("
        SELECT p.*, ap.data_inicio, ap.data_fim
        FROM admin_planos ap
        JOIN planos p ON p.id = ap.plano_id
        WHERE ap.admin_id = ? ORDER BY ap.id DESC LIMIT 1
    ");
    $stmt->execute([$adminId]);
    $plano = $stmt->fetch();
    if (!$plano || empty($plano['data_fim'])) return null;

    $dias = (int)((strtotime($plano['data_fim']) - strtotime(date('Y-m-d'))) / 86400);
    if ($dias > 7) return null; // ainda não chegou a janela de 7 dias

    // Já existe fatura pendente de renovação? (sem código de solicitação = fatura aguardando pagamento)
    $stmt = $pdo->prepare("SELECT id FROM planos_pagamentos WHERE admin_id = ? AND plano_id = ? AND status = 'pendente' AND criado_em > DATE_SUB(NOW(), INTERVAL 45 DAY) ORDER BY id DESC LIMIT 1");
    $stmt->execute([$adminId, $plano['id']]);
    $existente = $stmt->fetchColumn();
    if ($existente) return (int)$existente;

    $descricao = 'Renovação Plano ' . $plano['nome'] . ' - Mensal';
    $pdo->prepare("INSERT INTO planos_pagamentos (admin_id, plano_id, valor, duracao_meses, descricao, status) VALUES (?, ?, ?, 1, ?, 'pendente')")
        ->execute([$adminId, $plano['id'], $plano['preco'], $descricao]);
    return (int)$pdo->lastInsertId();
}

// Verifica o status de um pagamento de plano. Retorna 'pago' ao confirmar e ativa o plano.
function verificarPixPlano($pagamentoId) {
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT * FROM planos_pagamentos WHERE id = ?");
    $stmt->execute([$pagamentoId]);
    $pg = $stmt->fetch();
    if (!$pg) return ['erro' => 'Pagamento não encontrado.'];

    if ($pg['status'] === 'pago') {
        return ['sucesso' => true, 'status' => 'pago', 'plano' => $pg['plano_id']];
    }
    if ($pg['status'] === 'expirado' || $pg['status'] === 'cancelado') {
        return ['sucesso' => true, 'status' => $pg['status']];
    }

    $resultado = consultarCobrancaInterSuper($pg['codigo_solicitacao']);
    if (empty($resultado['sucesso'])) {
        return ['erro' => $resultado['erro'] ?? 'Erro ao consultar pagamento.'];
    }

    $dados = $resultado['dados'];
    $situacao = strtoupper($dados['situacao'] ?? '');

    if (in_array($situacao, ['PAGA', 'RECEBIDO', 'EXPIRADO', 'CANCELADA', 'VENCIDA'])) {
        $novoStatus = in_array($situacao, ['PAGA', 'RECEBIDO']) ? 'pago' : strtolower($situacao);
        if ($novoStatus === 'pago') {
            $dataPagamento = null;
            $pix = $dados['pix'] ?? [];
            $horarioPag = $pix['horarioPagamento'] ?? null;
            if ($horarioPag) {
                $dataPagamento = date('Y-m-d H:i:s', strtotime($horarioPag));
            }
            $pdo->prepare("UPDATE planos_pagamentos SET status='pago', pago_em=? WHERE id=?")
                ->execute([$dataPagamento, $pg['id']]);

            // Ativa o plano do admin imediatamente
            ativarPlanoAdmin($pg['admin_id'], $pg['plano_id'], (int)($pg['duracao_meses'] ?? 1));

            return ['sucesso' => true, 'status' => 'pago', 'plano' => $pg['plano_id']];
        }
        $pdo->prepare("UPDATE planos_pagamentos SET status=? WHERE id=?")->execute([$novoStatus, $pg['id']]);
        return ['sucesso' => true, 'status' => $novoStatus];
    }

    return ['sucesso' => true, 'status' => 'pendente'];
}

// Ativa (ou troca) o plano do admin
function ativarPlanoAdmin($adminId, $planoId, $duracaoMeses = 1) {
    $pdo = getConnection();
    $login = 0;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM administradores WHERE id = ?");
    $stmt->execute([$adminId]);
    if ($stmt->fetchColumn() == 0) return false;

    $duracaoMeses = max(1, (int)$duracaoMeses);
    $pdo->prepare("INSERT INTO admin_planos (admin_id, plano_id, data_inicio, data_fim) VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? MONTH))
        ON DUPLICATE KEY UPDATE plano_id=VALUES(plano_id), data_inicio=VALUES(data_inicio), data_fim=VALUES(data_fim)")
        ->execute([$adminId, $planoId, $duracaoMeses]);
    return true;
}
