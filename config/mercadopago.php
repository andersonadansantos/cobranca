<?php
// =====================================================
// CONFIGURAÇÃO DA API DO MERCADO PAGO
// =====================================================

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/settings.php';

function getMPConfig() {
    $pdo = getConnection();
    if (!$pdo) return [];
    
    $stmt = $pdo->query("SELECT chave, valor FROM configuracoes WHERE chave LIKE 'mp_%'");
    $config = [];
    while ($row = $stmt->fetch()) {
        $config[$row['chave']] = $row['valor'];
    }
    return $config;
}

function saveMPConfig($accessToken, $publicKey, $webhookUrl) {
    $pdo = getConnection();
    if (!$pdo) return false;
    
    try {
        $stmt = $pdo->prepare("UPDATE configuracoes SET valor = ? WHERE chave = ?");
        
        $stmt->execute([$accessToken, 'mp_access_token']);
        $stmt->execute([$publicKey, 'mp_public_key']);
        $stmt->execute([$webhookUrl, 'mp_webhook_url']);
        
        return true;
    } catch (Exception $e) {
        error_log("Erro ao salvar config MP: " . $e->getMessage());
        return false;
    }
}

function criarPagamentoMercadoPago($descricao, $valor, $clienteEmail, $clienteNome) {
    $config = getMPConfig();
    
    if (empty($config['mp_access_token'])) {
        return ['erro' => 'Token de acesso do Mercado Pago não configurado'];
    }
    
    $mpAccess = $config['mp_access_token'];
    
    $dados = [
        "transaction_amount" => (float) $valor,
        "description" => $descricao,
        "payment_method_id" => "pix",
        "payer" => [
            "email" => $clienteEmail ?: "cliente@email.com",
            "first_name" => explode(' ', $clienteNome)[0] ?? $clienteNome,
            "last_name" => implode(' ', array_slice(explode(' ', $clienteNome), 1)) ?: '',
        ],
    ];
    
    $webhookUrl = $config['mp_webhook_url'] ?? '';
    if (!empty($webhookUrl) && strpos($webhookUrl, 'localhost') === false && strpos($webhookUrl, '127.0.0.1') === false) {
        $dados["notification_url"] = $webhookUrl;
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.mercadopago.com/v1/payments",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($dados),
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: Bearer " . $mpAccess,
            "X-Idempotency-Key: " . uniqid(),
        ],
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        $pixData = $result['point_of_interaction']['transaction_data'] ?? [];
        return [
            'sucesso' => true,
            'payment_id' => $result['id'],
            'status' => $result['status'],
            'qr_code' => $pixData['qr_code_base64'] ?? '',
            'qr_code_copia_cola' => $pixData['qr_code'] ?? '',
            'link_pagamento' => $pixData['ticket_url'] ?? '',
        ];
    } else {
        return [
            'erro' => $result['message'] ?? 'Erro ao criar pagamento',
            'detalhes' => $result,
        ];
    }
}

function criarBoletoMercadoPago($descricao, $valor, $clienteNome, $clienteCpfCnpj, $clienteEmail, $clienteCep, $clienteLogradouro, $clienteNumero, $clienteBairro, $clienteCidade, $clienteEstado) {
    $config = getMPConfig();

    if (empty($config['mp_access_token'])) {
        return ['erro' => 'Token de acesso do Mercado Pago não configurado'];
    }

    $mpAccess = $config['mp_access_token'];
    $cpfCnpj = preg_replace('/[^0-9]/', '', $clienteCpfCnpj);

    $dados = [
        "transaction_amount" => (float) $valor,
        "description" => $descricao,
        "payment_method_id" => "bolbradesco",
        "payer" => [
            "email" => $clienteEmail ?: "cliente@email.com",
            "first_name" => explode(' ', $clienteNome)[0] ?? $clienteNome,
            "last_name" => implode(' ', array_slice(explode(' ', $clienteNome), 1)) ?: '',
            "identification" => [
                "type" => strlen($cpfCnpj) === 11 ? "CPF" : "CNPJ",
                "number" => $cpfCnpj,
            ],
            "address" => [
                "zip_code" => preg_replace('/[^0-9]/', '', $clienteCep ?: ''),
                "street_name" => $clienteLogradouro ?: '',
                "street_number" => $clienteNumero ?: '0',
                "neighborhood" => $clienteBairro ?: '',
                "city" => $clienteCidade ?: '',
                "federal_unit" => $clienteEstado ?: '',
            ],
        ],
    ];

    $webhookUrl = $config['mp_webhook_url'] ?? '';
    if (!empty($webhookUrl) && strpos($webhookUrl, 'localhost') === false && strpos($webhookUrl, '127.0.0.1') === false) {
        $dados["notification_url"] = $webhookUrl;
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.mercadopago.com/v1/payments",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($dados),
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: Bearer " . $mpAccess,
            "X-Idempotency-Key: " . uniqid(),
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300) {
        return [
            'sucesso' => true,
            'payment_id' => $result['id'],
            'status' => $result['status'],
            'boleto_url' => $result['transaction_details']['external_resource_url'] ?? '',
            'boleto_codigo_barras' => is_array($result['barcode'] ?? null) ? ($result['barcode']['content'] ?? '') : ($result['barcode'] ?? ''),
            'boleto_linha_digitavel' => $result['transaction_details']['digitable_line'] ?? '',
        ];
    } else {
        return [
            'erro' => $result['message'] ?? 'Erro ao gerar boleto',
            'detalhes' => $result,
        ];
    }
}

function consultarPagamento($paymentId) {
    $config = getMPConfig();
    
    if (empty($config['mp_access_token'])) {
        return null;
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.mercadopago.com/v1/payments/" . $paymentId,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . $config['mp_access_token'],
        ],
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

function getApiAtiva() {
    return getConfig('api_pagamento_ativa', 'mercadopago');
}

function criarPagamento($descricao, $valor, $clienteEmail, $clienteNome) {
    $api = getApiAtiva();
    if ($api === 'inter') {
        return criarPagamentoInter($descricao, $valor, $clienteEmail, $clienteNome);
    }
    if ($api === 'bb') {
        return criarPagamentoBB($descricao, $valor, $clienteEmail, $clienteNome);
    }
    if ($api === 'pix_manual') {
        return criarPagamentoPixManual($descricao, $valor, $clienteEmail, $clienteNome);
    }
    if ($api === 'pagbank') {
        if (!function_exists('criarPedidoPixPagBank')) {
            require_once __DIR__ . '/pagbank.php';
        }
        $fatura = ['descricao' => $descricao, 'valor_final' => $valor, 'email' => $clienteEmail, 'nome_razao' => $clienteNome];
        $cli = null;
        $pdo = getConnection();
        if ($pdo && !empty($clienteEmail)) {
            $stmt = $pdo->prepare("SELECT * FROM clientes WHERE email = ? LIMIT 1");
            $stmt->execute([$clienteEmail]);
            $cli = $stmt->fetch();
        }
        if ($cli && empty($clienteNome)) {
            $clienteNome = $cli['nome_razao'] ?? '';
        }
        $cpfCnpj = $cli ? ($cli['cpf_cnpj'] ?? '') : '';
        return criarPedidoPixPagBank($descricao, $valor, $clienteEmail, $clienteNome, $cpfCnpj);
    }
    return criarPagamentoMercadoPago($descricao, $valor, $clienteEmail, $clienteNome);
}

function criarBoleto($descricao, $valor, $clienteNome, $clienteCpfCnpj, $clienteEmail, $clienteCep, $clienteLogradouro, $clienteNumero, $clienteBairro, $clienteCidade, $clienteEstado) {
    $api = getApiAtiva();
    if ($api === 'inter') {
        return criarBoletoInter($descricao, $valor, $clienteNome, $clienteCpfCnpj, $clienteEmail, $clienteCep, $clienteLogradouro, $clienteNumero, $clienteBairro, $clienteCidade, $clienteEstado);
    }
    if ($api === 'bb') {
        return criarBoletoBB($descricao, $valor, $clienteNome, $clienteCpfCnpj, $clienteEmail, $clienteCep, $clienteLogradouro, $clienteNumero, $clienteBairro, $clienteCidade, $clienteEstado);
    }
    if ($api === 'pagbank') {
        if (!function_exists('criarPedidoBoletoPagBank')) {
            require_once __DIR__ . '/pagbank.php';
        }
        return criarPedidoBoletoPagBank($descricao, $valor, $clienteNome, $clienteCpfCnpj, $clienteEmail, $clienteCep, $clienteLogradouro, $clienteNumero, $clienteBairro, $clienteCidade, $clienteEstado);
    }
    return criarBoletoMercadoPago($descricao, $valor, $clienteNome, $clienteCpfCnpj, $clienteEmail, $clienteCep, $clienteLogradouro, $clienteNumero, $clienteBairro, $clienteCidade, $clienteEstado);
}

function getConfigInter() {
    $chaves = ['inter_client_id', 'inter_client_secret', 'inter_conta', 'inter_webhook_url', 'inter_cert_crt', 'inter_cert_key', 'inter_cert_webhook'];
    $config = [];
    foreach ($chaves as $chave) {
        $config[$chave] = getConfig($chave, '');
    }
    return $config;
}

function getInterBaseUrl() {
    $config = getConfigInter();
    $certCrt = $config['inter_cert_crt'] ?? '';
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

function obterTokenInter() {
    $config = getConfigInter();
    if (empty($config['inter_client_id']) || empty($config['inter_client_secret'])) {
        return ['erro' => 'Credenciais do Banco Inter não configuradas.'];
    }
    $certCrt = $config['inter_cert_crt'] ?? '';
    $certKey = $config['inter_cert_key'] ?? '';
    if (empty($certCrt) || empty($certKey) || !file_exists($certCrt) || !file_exists($certKey)) {
        return ['erro' => 'Certificados do Banco Inter não configurados ou não encontrados.'];
    }
    $baseUrl = getInterBaseUrl();
    $cacheFile = sys_get_temp_dir() . '/inter_token_cache_' . md5($baseUrl) . '.json';
    if (file_exists($cacheFile)) {
        $cache = json_decode(file_get_contents($cacheFile), true);
        if ($cache && !empty($cache['access_token']) && $cache['expires_at'] > time() + 60) {
            return ['sucesso' => true, 'access_token' => $cache['access_token']];
        }
    }
    $params = http_build_query([
        'client_id' => $config['inter_client_id'],
        'client_secret' => $config['inter_client_secret'],
        'grant_type' => 'client_credentials',
        'scope' => 'boleto-cobranca.read boleto-cobranca.write',
    ]);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => getInterBaseUrl() . '/oauth/v2/token',
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
        error_log("[INTER] Erro token: " . $curlError);
        return ['erro' => 'Erro de conexão com Banco Inter: ' . $curlError];
    }
    $result = json_decode($response, true);
    error_log("[INTER] Token HTTP {$httpCode} | Response: " . substr($response, 0, 300));
    if ($httpCode >= 200 && $httpCode < 300 && !empty($result['access_token'])) {
        $cacheData = [
            'access_token' => $result['access_token'],
            'expires_at' => time() + ($result['expires_in'] ?? 3600),
        ];
        file_put_contents($cacheFile, json_encode($cacheData));
        return ['sucesso' => true, 'access_token' => $result['access_token']];
    }
    return ['erro' => 'Erro ao obter token do Banco Inter: ' . ($result['message'] ?? 'HTTP ' . $httpCode)];
}

function criarCobrancaInter($dados) {
    $config = getConfigInter();
    $token = obterTokenInter();
    if (isset($token['erro'])) {
        return $token;
    }
    $certCrt = $config['inter_cert_crt'] ?? '';
    $certKey = $config['inter_cert_key'] ?? '';
    $contaDigitos = preg_replace('/[^0-9]/', '', $config['inter_conta'] ?? '');
    $conta = ltrim(substr($contaDigitos, 4), '0');
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => getInterBaseUrl() . '/cobranca/v3/cobrancas',
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
        error_log("[INTER] Erro de conexão criarCobranca: " . $curlError);
        return ['erro' => 'Erro de conexão com Banco Inter: ' . $curlError];
    }
    $result = json_decode($response, true);
    error_log("[INTER] criarCobranca HTTP {$httpCode} | Response: " . substr($response, 0, 500));
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['sucesso' => true, 'dados' => $result];
    }
    $erroMsg = $result['title'] ?? 'Erro ao criar cobrança';
    $detalhes = $result['detail'] ?? '';
    $violacoes = '';
    if (!empty($result['violacoes'])) {
        foreach ($result['violacoes'] as $v) {
            $violacoes .= ($v['razao'] ?? '') . ' ';
        }
    }
    return ['erro' => trim($erroMsg . ' ' . $detalhes . ' ' . $violacoes)];
}

function consultarCobrancaInter($codigoSolicitacao) {
    $config = getConfigInter();
    $token = obterTokenInter();
    if (isset($token['erro'])) {
        return ['erro' => $token['erro']];
    }
    $certCrt = $config['inter_cert_crt'] ?? '';
    $certKey = $config['inter_cert_key'] ?? '';
    $contaDigitos = preg_replace('/[^0-9]/', '', $config['inter_conta'] ?? '');
    $conta = ltrim(substr($contaDigitos, 4), '0');
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => getInterBaseUrl() . '/cobranca/v3/cobrancas/' . $codigoSolicitacao,
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
        error_log("[INTER] Erro consultar {$codigoSolicitacao}: " . $curlError);
        return ['erro' => 'Erro de conexão Inter: ' . $curlError];
    }
    $result = json_decode($response, true);
    error_log("[INTER] Consultar {$codigoSolicitacao} HTTP {$httpCode} | Response: " . substr($response, 0, 500));
    if (!$result) {
        return ['erro' => 'Resposta inválida do Banco Inter'];
    }
    if ($httpCode >= 200 && $httpCode < 300) {
        return $result;
    }
    $erroMsg = $result['title'] ?? 'Erro ao consultar cobrança';
    $detalhes = $result['detail'] ?? '';
    return ['erro' => trim($erroMsg . ' ' . $detalhes)];
}

function obterPdfInter($codigoSolicitacao) {
    $config = getConfigInter();
    $token = obterTokenInter();
    if (isset($token['erro'])) {
        return null;
    }
    $certCrt = $config['inter_cert_crt'] ?? '';
    $certKey = $config['inter_cert_key'] ?? '';
    $contaDigitos = preg_replace('/[^0-9]/', '', $config['inter_conta'] ?? '');
    $conta = ltrim(substr($contaDigitos, 4), '0');
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => getInterBaseUrl() . '/cobranca/v3/cobrancas/' . $codigoSolicitacao . '/pdf',
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
    curl_close($ch);
    if ($httpCode >= 200 && $httpCode < 300) {
        $result = json_decode($response, true);
        return $result['pdf'] ?? null;
    }
    return null;
}

function criarPagamentoInter($descricao, $valor, $clienteEmail, $clienteNome) {
    $config = getConfigInter();
    $pdo = getConnection();
    $cli = null;
    if (!empty($clienteEmail)) {
        $stmt = $pdo->prepare("SELECT * FROM clientes WHERE email = ? LIMIT 1");
        $stmt->execute([$clienteEmail]);
        $cli = $stmt->fetch();
    }
    if (!$cli && !empty($clienteNome)) {
        $stmt = $pdo->prepare("SELECT * FROM clientes WHERE nome_razao = ? LIMIT 1");
        $stmt->execute([$clienteNome]);
        $cli = $stmt->fetch();
    }
    $cpfCnpj = $cli ? preg_replace('/[^0-9]/', '', $cli['cpf_cnpj'] ?? '') : '00000000000';
    $tipoPessoa = strlen($cpfCnpj) === 11 ? 'FISICA' : 'JURIDICA';
    $pagador = [
        'cpfCnpj' => $cpfCnpj ?: '00000000000',
        'tipoPessoa' => $tipoPessoa,
        'nome' => $cli['nome_razao'] ?? ($clienteNome ?: 'Pagador'),
        'email' => $cli['email'] ?? ($clienteEmail ?: ''),
        'endereco' => $cli['logradouro'] ?? 'NAO INFORMADO',
        'numero' => $cli['numero'] ?? '0',
        'complemento' => $cli['complemento'] ?? '',
        'bairro' => $cli['bairro'] ?? 'NAO INFORMADO',
        'cidade' => $cli['cidade'] ?? 'SAO PAULO',
        'uf' => $cli['estado'] ?? 'SP',
        'cep' => str_pad(preg_replace('/[^0-9]/', '', $cli['cep'] ?? ''), 8, '0', STR_PAD_LEFT) ?: '01000000',
    ];
    $dados = [
        'seuNumero' => substr('PIX' . date('ymd') . rand(100, 999), 0, 15),
        'valorNominal' => (float) $valor,
        'dataVencimento' => date('Y-m-d'),
        'numDiasAgenda' => 1,
        'pagador' => $pagador,
        'mensagem' => [
            'linha1' => $descricao,
        ],
    ];
    $resultado = criarCobrancaInter($dados);
    if (isset($resultado['sucesso']) && $resultado['sucesso']) {
        $resp = $resultado['dados'];
        $codigo = $resp['codigoSolicitacao'] ?? ($resp['cobranca']['codigoSolicitacao'] ?? '');
        if ($codigo) {
            $pixCopiaECola = '';
            $qrCode = '';
            $tentativas = 0;
            $detalhe = null;
            while (empty($pixCopiaECola) && $tentativas < 6) {
                $tentativas++;
                if ($tentativas > 1) sleep(2);
                $detalhe = consultarCobrancaInter($codigo);
                if ($detalhe && !isset($detalhe['erro'])) {
                    $pix = $detalhe['pix'] ?? ($detalhe['cobranca']['pix'] ?? []);
                    if (!empty($pix)) {
                        $pixCopiaECola = $pix['pixCopiaECola'] ?? '';
                        $qrCode = $pix['qrcode'] ?? '';
                    }
                }
            }
            file_put_contents(__DIR__ . '/../inter_debug.log', date('Y-m-d H:i:s') . " | CODIGO={$codigo} | PIX=" . ($pixCopiaECola ?: 'VAZIO') . " | TENTATIVAS={$tentativas} | DETALHE=" . json_encode($detalhe) . "\n", FILE_APPEND);
            return [
                'sucesso' => true,
                'payment_id' => $codigo,
                'qr_code' => $qrCode,
                'qr_code_copia_cola' => $pixCopiaECola,
                'link_pagamento' => '',
            ];
        }
    }
    return $resultado;
}

function criarBoletoInter($descricao, $valor, $clienteNome, $clienteCpfCnpj, $clienteEmail, $clienteCep, $clienteLogradouro, $clienteNumero, $clienteBairro, $clienteCidade, $clienteEstado) {
    $cpfCnpj = preg_replace('/[^0-9]/', '', $clienteCpfCnpj);
    $tipoPessoa = strlen($cpfCnpj) === 11 ? 'FISICA' : 'JURIDICA';
    $dados = [
        'seuNumero' => substr('BOL' . date('ymd') . rand(100, 999), 0, 15),
        'valorNominal' => (float) $valor,
        'dataVencimento' => date('Y-m-d'),
        'numDiasAgenda' => 30,
        'pagador' => [
            'cpfCnpj' => $cpfCnpj,
            'tipoPessoa' => $tipoPessoa,
            'nome' => $clienteNome ?: '',
            'email' => $clienteEmail ?: '',
            'endereco' => $clienteLogradouro ?: '',
            'numero' => $clienteNumero ?: '0',
            'complemento' => '',
            'bairro' => $clienteBairro ?: '',
            'cidade' => $clienteCidade ?: '',
            'uf' => $clienteEstado ?: '',
            'cep' => str_pad(preg_replace('/[^0-9]/', '', $clienteCep ?: ''), 8, '0', STR_PAD_LEFT) ?: '01000000',
        ],
        'mensagem' => [
            'linha1' => $descricao,
        ],
    ];
    $resultado = criarCobrancaInter($dados);
    if (isset($resultado['sucesso']) && $resultado['sucesso']) {
        $resp = $resultado['dados'];
        $codigo = $resp['codigoSolicitacao'] ?? ($resp['cobranca']['codigoSolicitacao'] ?? '');
        if ($codigo) {
            $linhaDigitavel = '';
            $detalhe = consultarCobrancaInter($codigo);
            if ($detalhe && !isset($detalhe['erro'])) {
                $linhaDigitavel = $detalhe['boleto']['linhaDigitavel'] ?? ($detalhe['cobranca']['linhaDigitavel'] ?? '');
            }
            $pdfUrl = '';
            $pdfBase64 = obterPdfInter($codigo);
            if ($pdfBase64) {
                $pdfDir = __DIR__ . '/../assets/boletos_inter';
                if (!is_dir($pdfDir)) {
                    mkdir($pdfDir, 0755, true);
                }
                $pdfFile = $pdfDir . '/' . $codigo . '.pdf';
                file_put_contents($pdfFile, base64_decode($pdfBase64));
                $pdfUrl = '/cobranca/assets/boletos_inter/' . $codigo . '.pdf';
            }
            file_put_contents(__DIR__ . '/../inter_debug.log', date('Y-m-d H:i:s') . " | BOL CODIGO={$codigo} | LINHA=" . ($linhaDigitavel ?: 'VAZIO') . " | PDF=" . ($pdfUrl ?: 'VAZIO') . "\n", FILE_APPEND);
            return [
                'sucesso' => true,
                'payment_id' => $codigo,
                'boleto_url' => $pdfUrl,
                'boleto_codigo_barras' => $linhaDigitavel,
                'boleto_linha_digitavel' => $linhaDigitavel,
            ];
        }
    }
    return $resultado;
}

function getConfigBB() {
    $chaves = ['bb_client_id', 'bb_client_secret', 'bb_conta', 'bb_agencia', 'bb_convenio', 'bb_carteira', 'bb_variacao', 'bb_webhook_url', 'bb_chave_pix', 'bb_ambiente'];
    $config = [];
    foreach ($chaves as $chave) {
        $config[$chave] = getConfig($chave, '');
    }
    return $config;
}

function getBBBaseUrl() {
    $config = getConfigBB();
    return ($config['bb_ambiente'] ?? 'producao') === 'sandbox'
        ? 'https://api.hm.bb.com.br'
        : 'https://api.hm.bb.com.br';
}

function obterTokenBB() {
    $config = getConfigBB();
    if (empty($config['bb_client_id']) || empty($config['bb_client_secret'])) {
        return ['erro' => 'Credenciais do Banco do Brasil não configuradas.'];
    }
    $baseUrl = getBBBaseUrl();
    $cacheFile = sys_get_temp_dir() . '/bb_token_cache_' . md5($baseUrl) . '.json';
    if (file_exists($cacheFile)) {
        $cache = json_decode(file_get_contents($cacheFile), true);
        if ($cache && !empty($cache['access_token']) && $cache['expires_at'] > time() + 60) {
            return ['sucesso' => true, 'access_token' => $cache['access_token']];
        }
    }
    $credentials = base64_encode($config['bb_client_id'] . ':' . $config['bb_client_secret']);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://oauth.bb.com.br/oauth/token',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . $credentials,
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    if ($curlError) {
        error_log("[BB] Erro token: " . $curlError);
        return ['erro' => 'Erro de conexão com Banco do Brasil: ' . $curlError];
    }
    $result = json_decode($response, true);
    error_log("[BB] Token HTTP {$httpCode} | Response: " . substr($response, 0, 300));
    if ($httpCode >= 200 && $httpCode < 300 && !empty($result['access_token'])) {
        $cacheData = [
            'access_token' => $result['access_token'],
            'expires_at' => time() + ($result['expires_in'] ?? 1800),
        ];
        file_put_contents($cacheFile, json_encode($cacheData));
        return ['sucesso' => true, 'access_token' => $result['access_token']];
    }
    return ['erro' => 'Erro ao obter token do BB: ' . ($result['error_description'] ?? $result['error'] ?? 'HTTP ' . $httpCode)];
}

function criarCobrancaBB($dados) {
    $config = getConfigBB();
    $token = obterTokenBB();
    if (isset($token['erro'])) {
        return $token;
    }
    $convenio = $config['bb_convenio'] ?? '';
    if (empty($convenio)) {
        return ['erro' => 'Convênio do Banco do Brasil não configurado.'];
    }
    $baseUrl = getBBBaseUrl();
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $baseUrl . '/cobrancas/v2/boletos/' . $convenio,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($dados),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token['access_token'],
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    if ($curlError) {
        error_log("[BB] Erro criarCobranca: " . $curlError);
        return ['erro' => 'Erro de conexão com BB: ' . $curlError];
    }
    $result = json_decode($response, true);
    error_log("[BB] criarCobranca HTTP {$httpCode} | Response: " . substr($response, 0, 500));
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['sucesso' => true, 'dados' => $result];
    }
    $erroMsg = $result['message'] ?? $result['title'] ?? 'Erro ao criar cobrança BB';
    $detalhes = $result['detail'] ?? '';
    return ['erro' => trim($erroMsg . ' ' . $detalhes)];
}

function consultarBoletoBB($seuNumero) {
    $config = getConfigBB();
    $token = obterTokenBB();
    if (isset($token['erro'])) {
        return ['erro' => $token['erro']];
    }
    $convenio = $config['bb_convenio'] ?? '';
    $baseUrl = getBBBaseUrl();
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $baseUrl . '/cobrancas/v2/boletos/' . $convenio . '/' . $seuNumero,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token['access_token'],
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    if ($curlError) {
        error_log("[BB] Erro consultar {$seuNumero}: " . $curlError);
        return ['erro' => 'Erro de conexão BB: ' . $curlError];
    }
    $result = json_decode($response, true);
    error_log("[BB] Consultar {$seuNumero} HTTP {$httpCode} | Response: " . substr($response, 0, 500));
    if ($httpCode >= 200 && $httpCode < 300) {
        return $result;
    }
    return ['erro' => 'Erro ao consultar boleto BB: ' . ($result['message'] ?? 'HTTP ' . $httpCode)];
}

function obterPdfBB($seuNumero) {
    $config = getConfigBB();
    $token = obterTokenBB();
    if (isset($token['erro'])) {
        return null;
    }
    $convenio = $config['bb_convenio'] ?? '';
    $baseUrl = getBBBaseUrl();
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $baseUrl . '/cobrancas/v2/boletos/' . $convenio . '/' . $seuNumero . '/imagem',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token['access_token'],
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode >= 200 && $httpCode < 300) {
        $result = json_decode($response, true);
        return $result['mensagem'] ?? $result['pdf'] ?? null;
    }
    return null;
}

function cancelarCobrancaInter($codigoSolicitacao) {
    $config = getConfigInter();
    $token = obterTokenInter();
    if (isset($token['erro'])) {
        return ['erro' => $token['erro']];
    }
    $certCrt = $config['inter_cert_crt'] ?? '';
    $certKey = $config['inter_cert_key'] ?? '';
    $contaDigitos = preg_replace('/[^0-9]/', '', $config['inter_conta'] ?? '');
    $conta = ltrim(substr($contaDigitos, 4), '0');
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => getInterBaseUrl() . '/cobranca/v3/cobrancas/' . $codigoSolicitacao . '/cancelar',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['motivoCancelamento' => 'Cancelado pelo sistema de cobranca']),
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
        error_log("[INTER] Erro cancelar {$codigoSolicitacao}: " . $curlError);
        return ['erro' => 'Erro de conexão com Banco Inter: ' . $curlError];
    }
    $result = json_decode($response, true);
    error_log("[INTER] Cancelar {$codigoSolicitacao} HTTP {$httpCode} | Response: " . substr($response, 0, 500));
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['sucesso' => true, 'dados' => $result];
    }
    $erroMsg = $result['title'] ?? $result['detail'] ?? 'Erro ao cancelar cobrança no Banco Inter';
    return ['erro' => trim($erroMsg . ' ' . ($result['detail'] ?? ''))];
}

function cancelarPagamentoMercadoPago($paymentId) {
    $config = getMPConfig();
    if (empty($config['mp_access_token'])) {
        return ['erro' => 'Token do Mercado Pago não configurado.'];
    }
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.mercadopago.com/v1/payments/' . $paymentId,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_POSTFIELDS => json_encode(['status' => 'cancelled']),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $config['mp_access_token'],
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    if ($curlError) {
        error_log("[MP] Cancelar {$paymentId}: " . $curlError);
        return ['erro' => 'Erro de conexão Mercado Pago: ' . $curlError];
    }
    $result = json_decode($response, true);
    error_log("[MP] Cancelar {$paymentId} HTTP {$httpCode} | Response: " . substr($response, 0, 500));
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['sucesso' => true, 'dados' => $result];
    }
    return ['erro' => $result['message'] ?? ($result['status_detail'] ?? 'Erro ao cancelar no Mercado Pago')];
}

function cancelarCobrancaFatura($fatura) {
    if (!$fatura) {
        return ['sucesso' => true];
    }
    $api = $fatura['api_pagamento'] ?? '';
    if (empty($api)) {
        if (!empty($fatura['inter_codigo_solicitacao'])) {
            $api = 'inter';
        } elseif (!empty($fatura['mp_payment_id'])) {
            $api = getApiAtiva();
        }
    }

    if ($api === 'inter' && !empty($fatura['inter_codigo_solicitacao'])) {
        return cancelarCobrancaInter($fatura['inter_codigo_solicitacao']);
    }

    if ($api === 'mercadopago' && !empty($fatura['mp_payment_id'])) {
        return cancelarPagamentoMercadoPago($fatura['mp_payment_id']);
    }

    if ($api === 'bb' && !empty($fatura['inter_codigo_solicitacao'])) {
        if (!empty($fatura['boleto_url'])) {
            return cancelarBoletoBB($fatura['inter_codigo_solicitacao']);
        }
        return cancelarCobrancaPixBB($fatura['inter_codigo_solicitacao']);
    }

    if ($api === 'pagbank' && !empty($fatura['mp_payment_id'])) {
        if (!function_exists('cancelarPedidoPagBank')) {
            require_once __DIR__ . '/pagbank.php';
        }
        return cancelarPedidoPagBank($fatura['mp_payment_id']);
    }

    return ['sucesso' => true, 'sem_cobranca' => true];
}

function cancelarBoletoBB($nossoNumero) {
    $config = getConfigBB();
    $token = obterTokenBB();
    if (isset($token['erro'])) {
        return $token;
    }
    $convenio = $config['bb_convenio'] ?? '';
    if (empty($convenio)) {
        return ['erro' => 'Convênio do Banco do Brasil não configurado.'];
    }
    $baseUrl = getBBBaseUrl();
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $baseUrl . '/cobrancas/v2/boletos/' . $nossoNumero . '/baixar',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['numeroConvenio' => $convenio]),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token['access_token'],
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    if ($curlError) {
        error_log("[BB] Erro baixa boleto {$nossoNumero}: " . $curlError);
        return ['erro' => 'Erro de conexão com Banco do Brasil: ' . $curlError];
    }
    $result = json_decode($response, true);
    error_log("[BB] Baixa boleto {$nossoNumero} HTTP {$httpCode} | Response: " . substr($response, 0, 500));
    if ($httpCode >= 200 && $httpCode < 300) {
        $codErro = $result['codigoErroRegistro'] ?? 0;
        if ((int) $codErro === 0) {
            return ['sucesso' => true, 'dados' => $result];
        }
        return ['erro' => $result['mensagem'] ?? 'Erro ao dar baixa no boleto (código ' . $codErro . ')'];
    }
    return ['erro' => $result['message'] ?? ($result['title'] ?? 'Erro ao dar baixa no boleto BB (HTTP ' . $httpCode . ')')];
}

function cancelarCobrancaPixBB($txid) {
    $token = obterTokenBB();
    if (isset($token['erro'])) {
        return $token;
    }
    $baseUrl = getBBBaseUrl();
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $baseUrl . '/pix/v2/cobr/' . $txid,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_POSTFIELDS => json_encode(['status' => 'REMOVIDA_PELO_USUARIO_RECEBEDOR']),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token['access_token'],
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    if ($curlError) {
        error_log("[BB] Erro revogar PIX {$txid}: " . $curlError);
        return ['erro' => 'Erro de conexão com Banco do Brasil: ' . $curlError];
    }
    $result = json_decode($response, true);
    error_log("[BB] Revogar PIX {$txid} HTTP {$httpCode} | Response: " . substr($response, 0, 500));
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['sucesso' => true, 'dados' => $result];
    }
    return ['erro' => $result['title'] ?? ($result['message'] ?? 'Erro ao revogar cobrança PIX BB (HTTP ' . $httpCode . ')')];
}

function criarCobrancaPixBB($txid, $dados) {
    $config = getConfigBB();
    $token = obterTokenBB();
    if (isset($token['erro'])) {
        return $token;
    }
    $baseUrl = getBBBaseUrl();
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $baseUrl . '/pix/v2/cobr/' . $txid,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_POSTFIELDS => json_encode($dados),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token['access_token'],
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    if ($curlError) {
        error_log("[BB] Erro criarCobrancaPix: " . $curlError);
        return ['erro' => 'Erro de conexão BB: ' . $curlError];
    }
    $result = json_decode($response, true);
    error_log("[BB] criarCobrancaPix HTTP {$httpCode} | Response: " . substr($response, 0, 500));
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['sucesso' => true, 'dados' => $result];
    }
    $erroMsg = $result['message'] ?? $result['title'] ?? 'Erro ao criar cobrança PIX BB';
    $detalhes = $result['detail'] ?? '';
    return ['erro' => trim($erroMsg . ' ' . $detalhes)];
}

function criarPagamentoBB($descricao, $valor, $clienteEmail, $clienteNome) {
    $config = getConfigBB();
    $pdo = getConnection();
    $cli = null;
    if (!empty($clienteEmail)) {
        $stmt = $pdo->prepare("SELECT * FROM clientes WHERE email = ? LIMIT 1");
        $stmt->execute([$clienteEmail]);
        $cli = $stmt->fetch();
    }
    if (!$cli && !empty($clienteNome)) {
        $stmt = $pdo->prepare("SELECT * FROM clientes WHERE nome_razao = ? LIMIT 1");
        $stmt->execute([$clienteNome]);
        $cli = $stmt->fetch();
    }
    $cpfCnpj = $cli ? preg_replace('/[^0-9]/', '', $cli['cpf_cnpj'] ?? '') : '';
    $tipoPessoa = strlen($cpfCnpj) === 11 ? 'FISICA' : 'JURIDICA';
    $chavePix = $config['bb_chave_pix'] ?? '';
    if (empty($chavePix)) {
        return ['erro' => 'Chave PIX do Banco do Brasil não configurada.'];
    }
    $txid = substr('pix' . date('ymd') . rand(100, 999), 0, 25);
    $devedor = [
        'nome' => $cli['nome_razao'] ?? ($clienteNome ?: 'Pagador'),
    ];
    if ($tipoPessoa === 'FISICA') {
        $devedor['cpf'] = $cpfCnpj;
    } else {
        $devedor['cnpj'] = $cpfCnpj;
    }
    $dados = [
        'calendario' => [
            'dataDeVencimento' => date('Y-m-d'),
            'validadeAposVencimento' => 1,
        ],
        'valor' => [
            'original' => number_format((float) $valor, 2, '.', ''),
        ],
        'chave' => $chavePix,
        'solicitacaoPagador' => $descricao,
    ];
    if (!empty($cli['logradouro'])) {
        $devedor['logradouro'] = $cli['logradouro'] . ', ' . ($cli['numero'] ?? '');
        $devedor['cidade'] = $cli['cidade'] ?? '';
        $devedor['uf'] = $cli['estado'] ?? '';
        $devedor['cep'] = str_pad(preg_replace('/[^0-9]/', '', $cli['cep'] ?? ''), 8, '0', STR_PAD_LEFT);
    }
    $dados['devedor'] = $devedor;
    $resultado = criarCobrancaPixBB($txid, $dados);
    if (isset($resultado['sucesso']) && $resultado['sucesso']) {
        $resp = $resultado['dados'];
        $pixCopiaECola = $resp['pixCopiaECola'] ?? '';
        $locId = $resp['loc']['id'] ?? '';
        $qrCode = '';
        if ($locId) {
            $qrCode = getBBQrCode($locId);
        }
        return [
            'sucesso' => true,
            'payment_id' => $txid,
            'qr_code' => $qrCode,
            'qr_code_copia_cola' => $pixCopiaECola,
            'link_pagamento' => '',
        ];
    }
    return $resultado;
}

function getBBQrCode($locId) {
    $token = obterTokenBB();
    if (isset($token['erro'])) {
        return '';
    }
    $baseUrl = getBBBaseUrl();
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $baseUrl . '/pix/v2/loc/' . $locId . '/qrcode',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token['access_token'],
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode >= 200 && $httpCode < 300) {
        $result = json_decode($response, true);
        return $result['imagem'] ?? '';
    }
    return '';
}

function criarBoletoBB($descricao, $valor, $clienteNome, $clienteCpfCnpj, $clienteEmail, $clienteCep, $clienteLogradouro, $clienteNumero, $clienteBairro, $clienteCidade, $clienteEstado) {
    $config = getConfigBB();
    $cpfCnpj = preg_replace('/[^0-9]/', '', $clienteCpfCnpj);
    $seuNumero = substr('BOL' . date('ymd') . rand(100, 999), 0, 15);
    $dados = [
        'dataVencimento' => date('Y-m-d', strtotime('+30 days')),
        'valorNominal' => (float) $valor,
        'codigoDeCobranca' => 0,
        'modalidadeDesconto' => 0,
        'valorAbatimento' => 0,
        'quantidadeParcelas' => 1,
        'seuNumero' => $seuNumero,
    ];
    $resultado = criarCobrancaBB($dados);
    if (isset($resultado['sucesso']) && $resultado['sucesso']) {
        $resp = $resultado['dados'];
        $nossoNumero = $resp['nossoNumero'] ?? ($resp['boleto']['nossoNumero'] ?? $seuNumero);
        if ($nossoNumero) {
            $detalhe = consultarBoletoBB($seuNumero);
            $linhaDigitavel = '';
            $codigoBarras = '';
            if (isset($detalhe['linhaDigitavel'])) {
                $linhaDigitavel = $detalhe['linhaDigitavel'];
                $codigoBarras = $detalhe['codigoBarras'] ?? '';
            } elseif (isset($detalhe['boleto'])) {
                $linhaDigitavel = $detalhe['boleto']['linhaDigitavel'] ?? '';
                $codigoBarras = $detalhe['boleto']['codigoBarras'] ?? '';
            }
            $pdfBase64 = obterPdfBB($seuNumero);
            $pdfUrl = '';
            if ($pdfBase64) {
                $pdfDir = __DIR__ . '/../assets/boletos_bb';
                if (!is_dir($pdfDir)) {
                    mkdir($pdfDir, 0755, true);
                }
                $pdfFile = $pdfDir . '/' . $nossoNumero . '.pdf';
                file_put_contents($pdfFile, base64_decode($pdfBase64));
                $pdfUrl = '/cobranca/assets/boletos_bb/' . $nossoNumero . '.pdf';
            }
            return [
                'sucesso' => true,
                'payment_id' => $nossoNumero,
                'boleto_url' => $pdfUrl,
                'boleto_codigo_barras' => $codigoBarras,
                'boleto_linha_digitavel' => $linhaDigitavel,
            ];
        }
    }
    return $resultado;
}

function getConfigCora() {
    $chaves = ['cora_client_id', 'cora_cert_path', 'cora_key_path', 'cora_webhook_url', 'cora_ambiente'];
    $config = [];
    foreach ($chaves as $chave) {
        $config[$chave] = getConfig($chave, '');
    }
    return $config;
}

function getCoraBaseUrl() {
    $config = getConfigCora();
    return ($config['cora_ambiente'] ?? 'producao') === 'stage'
        ? 'https://matls-clients.api.stage.cora.com.br'
        : 'https://matls-clients.api.cora.com.br';
}

function getConfigC6() {
    $chaves = ['c6_client_id', 'c6_client_secret', 'c6_cert_path', 'c6_cert_senha', 'c6_agencia', 'c6_conta', 'c6_beneficiario', 'c6_empresa', 'c6_convenio', 'c6_carteira', 'c6_webhook_url', 'c6_ambiente'];
    $config = [];
    foreach ($chaves as $chave) {
        $config[$chave] = getConfig($chave, '');
    }
    return $config;
}

function getC6BaseUrl() {
    $config = getConfigC6();
    return ($config['c6_ambiente'] ?? 'homologacao') === 'producao'
        ? 'https://api.c6bank.com.br'
        : 'https://api-hmg.c6bank.com.br';
}

// =====================================================
// PIX MANUAL — Geração estática de QR Code PIX
// =====================================================

function getConfigPixManual() {
    $campos = ['pix_manual_chave', 'pix_manual_banco', 'pix_manual_favorecido', 'pix_manual_cnpj', 'pix_manual_whatsapp'];
    $config = [];
    foreach ($campos as $chave) {
        $config[$chave] = getConfig($chave, '');
    }
    return $config;
}

function emvField($id, $value) {
    $len = strlen($value);
    return $id . str_pad($len, 2, '0', STR_PAD_LEFT) . $value;
}

function crc16Ccitt($str) {
    $polynomial = 0x1021;
    $crc = 0xFFFF;
    for ($i = 0; $i < strlen($str); $i++) {
        $crc ^= (ord($str[$i]) << 8);
        for ($j = 0; $j < 8; $j++) {
            if ($crc & 0x8000) {
                $crc = (($crc << 1) ^ $polynomial) & 0xFFFF;
            } else {
                $crc = ($crc << 1) & 0xFFFF;
            }
        }
    }
    return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
}

function gerarPixManualString($chave, $valor, $favorecido, $cidade = 'SAO PAULO') {
    $chave = trim($chave);
    $favorecido = trim($favorecido);
    $cidade = trim($cidade) ?: 'SAO PAULO';

    $payload = '';
    $payload .= emvField('00', '01');
    $payload .= emvField('01', strlen($chave) === 11 ? '12' : '14');
    $payload .= emvField('26', emvField('00', 'br.gov.bcb.pix') . emvField('01', $chave));
    $payload .= emvField('52', '0000');
    $payload .= emvField('53', '986');
    $payload .= emvField('54', number_format((float) $valor, 2, '.', ''));
    $payload .= emvField('58', 'BR');
    $payload .= emvField('59', mb_strtoupper(mb_substr($favorecido, 0, 25)));
    $payload .= emvField('60', mb_strtoupper(mb_substr($cidade, 0, 15)));
    $payload .= emvField('62', emvField('05', '***'));

    $crcPayload = $payload . '6304';
    $crc = crc16Ccitt($crcPayload);

    return $payload . '63' . $crc;
}

function criarPagamentoPixManual($descricao, $valor, $clienteEmail, $clienteNome) {
    $config = getConfigPixManual();

    if (empty($config['pix_manual_chave'])) {
        return ['erro' => 'Chave PIX não configurada no PIX Manual.'];
    }

    $pixString = gerarPixManualString(
        $config['pix_manual_chave'],
        $valor,
        $config['pix_manual_favorecido'],
        'SAO PAULO'
    );

    return [
        'sucesso' => true,
        'qr_code' => '',
        'qr_code_copia_cola' => $pixString,
        'link_pagamento' => '',
        'payment_id' => 'pix_manual_' . time()
    ];
}
