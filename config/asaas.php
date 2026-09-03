<?php
// =====================================================
// CONFIGURAÇÃO DA API DO ASAAS
// Emissão de cobranças PIX e Boleto
// =====================================================

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/settings.php';

function getAsaasConfig() {
    $chaves = ['asaas_api_key', 'asaas_ambiente', 'asaas_webhook_url', 'asaas_webhook_token'];
    $config = [];
    foreach ($chaves as $chave) {
        $config[$chave] = getConfig($chave, '');
    }
    return $config;
}

function getAsaasBaseUrl() {
    $config = getAsaasConfig();
    return ($config['asaas_ambiente'] ?? 'producao') === 'sandbox'
        ? 'https://api-sandbox.asaas.com/v3'
        : 'https://api.asaas.com/v3';
}

function asaasRequest($metodo, $caminho, $dados = null) {
    $config = getAsaasConfig();
    if (empty($config['asaas_api_key'])) {
        return ['erro' => 'API Key do Asaas não configurada.'];
    }
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => getAsaasBaseUrl() . $caminho,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $metodo,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'access_token: ' . $config['asaas_api_key'],
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    if ($dados !== null && in_array($metodo, ['POST', 'PUT'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados));
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    if ($curlError) {
        error_log("[ASAAS] Erro {$metodo} {$caminho}: " . $curlError);
        return ['erro' => 'Erro de conexão com Asaas: ' . $curlError];
    }
    $result = json_decode($response, true);
    error_log("[ASAAS] {$metodo} {$caminho} HTTP {$httpCode} | Response: " . substr((string)$response, 0, 500));
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['sucesso' => true, 'dados' => $result];
    }
    $erros = '';
    if (!empty($result['errors']) && is_array($result['errors'])) {
        $lista = [];
        foreach ($result['errors'] as $e) {
            $lista[] = ($e['code'] ?? '') !== '' ? '[' . $e['code'] . '] ' . ($e['description'] ?? '') : ($e['description'] ?? '');
        }
        $erros = implode(' | ', array_filter($lista));
    }
    return ['erro' => trim($erros) ?: ('Erro Asaas (HTTP ' . $httpCode . ')'), 'detalhes' => $result];
}

function ensureClienteAsaas($clienteNome, $clienteEmail, $cpfCnpj, $celular = '', $telefone = '') {
    $cpfCnpj = preg_replace('/[^0-9]/', '', $cpfCnpj);
    if (empty($cpfCnpj)) {
        return ['erro' => 'Cliente sem CPF/CNPJ cadastrado. O Asaas exige CPF/CNPJ para emitir cobranças.'];
    }

    $busca = asaasRequest('GET', '/customers?cpfCnpj=' . $cpfCnpj);
    if (isset($busca['sucesso'])) {
        $lista = $busca['dados']['data'] ?? [];
        if (!empty($lista)) {
            return ['sucesso' => true, 'customer_id' => $lista[0]['id']];
        }
    } elseif (isset($busca['erro'])) {
        return $busca;
    }

    $novoCliente = [
        'name' => mb_substr(trim($clienteNome) ?: 'Cliente', 0, 60),
        'cpfCnpj' => $cpfCnpj,
    ];
    $clienteEmail = trim((string) $clienteEmail);
    if ($clienteEmail !== '') {
        $novoCliente['email'] = mb_substr($clienteEmail, 0, 100);
    }
    if ($celular) {
        $novoCliente['mobilePhone'] = substr(preg_replace('/[^0-9]/', '', $celular), 0, 20);
    } elseif ($telefone) {
        $novoCliente['phone'] = substr(preg_replace('/[^0-9]/', '', $telefone), 0, 20);
    }
    $criado = asaasRequest('POST', '/customers', $novoCliente);
    if (isset($criado['sucesso'])) {
        return ['sucesso' => true, 'customer_id' => $criado['dados']['id'] ?? ''];
    }
    return isset($criado['erro']) ? $criado : ['erro' => 'Erro ao cadastrar cliente no Asaas'];
}

function criarCobrancaAsaas($billingType, $descricao, $valor, $vencimento, $customerData) {
    $clienteId = ensureClienteAsaas(
        $customerData['nome'],
        $customerData['email'],
        $customerData['cpf_cnpj'],
        $customerData['celular'] ?? '',
        $customerData['telefone'] ?? ''
    );
    if (isset($clienteId['erro'])) {
        return $clienteId;
    }
    if (empty($clienteId['customer_id'])) {
        return ['erro' => 'Não foi possível identificar o cliente no Asaas.'];
    }

    $dados = [
        'customer' => $clienteId['customer_id'],
        'billingType' => $billingType,
        'value' => round((float) $valor, 2),
        'dueDate' => $vencimento ?: date('Y-m-d'),
    ];
    $descricao = trim((string) $descricao);
    if ($descricao !== '') {
        $dados['description'] = mb_substr($descricao, 0, 100);
    }

    $resultado = asaasRequest('POST', '/payments', $dados);
    if (!isset($resultado['sucesso'])) {
        return $resultado;
    }
    return ['sucesso' => true, 'payment' => $resultado['dados'], 'customer_id' => $clienteId['customer_id']];
}

function criarPagamentoAsaas($descricao, $valor, $clienteEmail, $clienteNome) {
    $pdo = getConnection();
    $cli = null;
    $adminCtx = getConfigAdminId();
    if (!empty($clienteEmail)) {
        if ($adminCtx > 0) {
            $stmt = $pdo->prepare("SELECT * FROM clientes WHERE email = ? AND admin_id = ? LIMIT 1");
            $stmt->execute([$clienteEmail, $adminCtx]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM clientes WHERE email = ? LIMIT 1");
            $stmt->execute([$clienteEmail]);
        }
        $cli = $stmt->fetch();
    }
    if (!$cli && !empty($clienteNome)) {
        if ($adminCtx > 0) {
            $stmt = $pdo->prepare("SELECT * FROM clientes WHERE nome_razao = ? AND admin_id = ? LIMIT 1");
            $stmt->execute([$clienteNome, $adminCtx]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM clientes WHERE nome_razao = ? LIMIT 1");
            $stmt->execute([$clienteNome]);
        }
        $cli = $stmt->fetch();
    }

    $cobranca = criarCobrancaAsaas('PIX', $descricao, $valor, date('Y-m-d'), [
        'nome' => $cli['nome_razao'] ?? ($clienteNome ?: ''),
        'email' => $cli['email'] ?? ($clienteEmail ?: ''),
        'cpf_cnpj' => $cli['cpf_cnpj'] ?? '',
        'celular' => $cli['celular'] ?? '',
        'telefone' => $cli['telefone'] ?? '',
    ]);
    if (isset($cobranca['erro'])) {
        return $cobranca;
    }

    $payment = $cobranca['payment'];
    $paymentId = $payment['id'] ?? '';

    $qrCode = '';
    $pixCopiaECola = '';
    if ($paymentId && in_array($payment['status'] ?? '', ['PENDING', 'AWAITING_RISK_ANALYSIS'], true)) {
        $qrcodeResp = asaasRequest('GET', '/payments/' . $paymentId . '/pixQrCode');
        if (isset($qrcodeResp['sucesso'])) {
            $qrCode = $qrcodeResp['dados']['encodedImage'] ?? '';
            $pixCopiaECola = $qrcodeResp['dados']['payload'] ?? '';
        }
    }

    return [
        'sucesso' => true,
        'payment_id' => $paymentId,
        'status' => $payment['status'] ?? '',
        'qr_code' => $qrCode,
        'qr_code_copia_cola' => $pixCopiaECola,
        'link_pagamento' => $payment['invoiceUrl'] ?? '',
    ];
}

function criarBoletoAsaas($descricao, $valor, $clienteNome, $clienteCpfCnpj, $clienteEmail, $clienteCep, $clienteLogradouro, $clienteNumero, $clienteBairro, $clienteCidade, $clienteEstado) {
    $cobranca = criarCobrancaAsaas('BOLETO', $descricao, $valor, date('Y-m-d'), [
        'nome' => $clienteNome ?: '',
        'email' => $clienteEmail ?: '',
        'cpf_cnpj' => $clienteCpfCnpj ?: '',
    ]);
    if (isset($cobranca['erro'])) {
        return $cobranca;
    }

    $payment = $cobranca['payment'];
    $paymentId = $payment['id'] ?? '';

    $linhaDigitavel = $payment['identificationField'] ?? '';
    $boletoUrl = $payment['bankSlipUrl'] ?? '';
    if ($paymentId && (empty($linhaDigitavel) || empty($boletoUrl))) {
        $detalhe = consultarPagamentoAsaas($paymentId);
        if ($detalhe && !isset($detalhe['erro'])) {
            $linhaDigitavel = $linhaDigitavel ?: ($detalhe['identificationField'] ?? '');
            $boletoUrl = $boletoUrl ?: ($detalhe['bankSlipUrl'] ?? '');
        }
    }

    return [
        'sucesso' => true,
        'payment_id' => $paymentId,
        'boleto_url' => $boletoUrl,
        'boleto_codigo_barras' => $linhaDigitavel,
        'boleto_linha_digitavel' => $linhaDigitavel,
    ];
}

function consultarPagamentoAsaas($paymentId) {
    $resultado = asaasRequest('GET', '/payments/' . $paymentId);
    if (isset($resultado['sucesso'])) {
        return $resultado['dados'];
    }
    return isset($resultado['erro']) ? ['erro' => $resultado['erro']] : null;
}

function cancelarCobrancaAsaas($paymentId) {
    $detalhe = consultarPagamentoAsaas($paymentId);
    if (!$detalhe || isset($detalhe['erro'])) {
        return ['sucesso' => true, 'sem_cobranca' => true];
    }
    $status = strtoupper($detalhe['status'] ?? '');
    if (in_array($status, ['RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH'])) {
        return ['erro' => 'Cobrança Asaas já foi paga e não pode ser cancelada.'];
    }
    $resultado = asaasRequest('DELETE', '/payments/' . $paymentId);
    if (isset($resultado['sucesso'])) {
        return ['sucesso' => true, 'dados' => $resultado['dados']];
    }
    return isset($resultado['erro']) ? $resultado : ['erro' => 'Erro ao cancelar cobrança no Asaas'];
}
