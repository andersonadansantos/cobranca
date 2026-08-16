<?php
// =====================================================
// CONFIGURAÇÃO DA API DO PAGBANK (PagSeguro)
// =====================================================

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/settings.php';

function getConfigPagBank() {
    $chaves = ['pagbank_token', 'pagbank_ambiente', 'pagbank_webhook_url'];
    $config = [];
    foreach ($chaves as $chave) {
        $config[$chave] = getConfig($chave, '');
    }
    return $config;
}

function getPagBankBaseUrl() {
    $config = getConfigPagBank();
    return ($config['pagbank_ambiente'] ?? 'sandbox') === 'producao'
        ? 'https://api.pagseguro.com'
        : 'https://sandbox.api.pagseguro.com';
}

function savePagBankConfig($token, $ambiente, $webhookUrl) {
    $pdo = getConnection();
    if (!$pdo) return false;

    try {
        $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
        $stmt->execute(['pagbank_token', $token, $token]);
        $stmt->execute(['pagbank_ambiente', $ambiente, $ambiente]);
        $stmt->execute(['pagbank_webhook_url', $webhookUrl, $webhookUrl]);
        return true;
    } catch (Exception $e) {
        error_log("Erro ao salvar config PagBank: " . $e->getMessage());
        return false;
    }
}

function criarPedidoPagBank($dados) {
    $config = getConfigPagBank();
    if (empty($config['pagbank_token'])) {
        return ['erro' => 'Token do PagBank não configurado.'];
    }

    $baseUrl = getPagBankBaseUrl();
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $baseUrl . '/orders',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($dados),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $config['pagbank_token'],
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log("[PAGBANK] Erro de conexão: " . $curlError);
        return ['erro' => 'Erro de conexão com PagBank: ' . $curlError];
    }

    $result = json_decode($response, true);
    error_log("[PAGBANK] HTTP {$httpCode} | Response: " . substr($response, 0, 500));

    if ($httpCode >= 200 && $httpCode < 300) {
        return ['sucesso' => true, 'dados' => $result];
    }

    $erroMsg = $result['error_messages'] ?? $result['message'] ?? 'Erro ao criar pedido PagBank';
    if (is_array($erroMsg)) {
        $msgs = [];
        foreach ($erroMsg as $e) {
            $msgs[] = ($e['description'] ?? '') . ' (' . ($e['code'] ?? '') . ')';
        }
        $erroMsg = implode('; ', $msgs);
    }
    return ['erro' => $erroMsg];
}

function criarPedidoPixPagBank($descricao, $valor, $clienteEmail, $clienteNome, $clienteCpfCnpj) {
    $config = getConfigPagBank();
    $cpfCnpj = preg_replace('/[^0-9]/', '', $clienteCpfCnpj);

    $orderRef = 'PIX' . date('ymd') . rand(100, 999);

    $dados = [
        'reference_id' => $orderRef,
        'customer' => [
            'name' => $clienteNome ?: 'Pagador',
            'email' => $clienteEmail ?: 'cliente@email.com',
            'tax_id' => $cpfCnpj ?: '00000000000',
        ],
        'items' => [
            [
                'reference_id' => $orderRef,
                'name' => $descricao,
                'quantity' => 1,
                'unit_amount' => (int) round($valor * 100),
            ],
        ],
        'qr_codes' => [
            [
                'amount' => [
                    'value' => (int) round($valor * 100),
                ],
            ],
        ],
    ];

    $webhookUrl = $config['pagbank_webhook_url'] ?? '';
    if (!empty($webhookUrl) && strpos($webhookUrl, 'localhost') === false && strpos($webhookUrl, '127.0.0.1') === false) {
        $dados['notification_urls'] = [$webhookUrl];
    }

    $resultado = criarPedidoPagBank($dados);
    if (isset($resultado['sucesso']) && $resultado['sucesso']) {
        $resp = $resultado['dados'];
        $orderId = $resp['id'] ?? '';
        $pixText = '';
        $pixQrPng = '';
        $pixQrBase64 = '';

        if (!empty($resp['qr_codes'])) {
            $qr = $resp['qr_codes'][0];
            $pixText = $qr['text'] ?? '';
            foreach (($qr['links'] ?? []) as $link) {
                if (($link['rel'] ?? '') === 'QRCODE.PNG' && ($link['media'] ?? '') === 'image/png') {
                    $pixQrPng = $link['href'] ?? '';
                }
                if (($link['rel'] ?? '') === 'QRCODE.BASE64') {
                    $pixQrBase64 = $link['href'] ?? '';
                }
            }
        }

        $qrCodeBase64 = '';
        if (!empty($pixQrPng)) {
            $qrCodeBase64 = downloadPagBankQrCode($pixQrPng);
        }

        return [
            'sucesso' => true,
            'payment_id' => $orderId,
            'reference_id' => $orderRef,
            'qr_code' => $qrCodeBase64 ?: $pixQrPng,
            'qr_code_copia_cola' => $pixText,
            'link_pagamento' => '',
        ];
    }

    return $resultado;
}

function criarPedidoBoletoPagBank($descricao, $valor, $clienteNome, $clienteCpfCnpj, $clienteEmail, $clienteCep, $clienteLogradouro, $clienteNumero, $clienteBairro, $clienteCidade, $clienteEstado) {
    $config = getConfigPagBank();
    $cpfCnpj = preg_replace('/[^0-9]/', '', $clienteCpfCnpj);

    $orderRef = 'BOL' . date('ymd') . rand(100, 999);

    $dados = [
        'reference_id' => $orderRef,
        'customer' => [
            'name' => $clienteNome ?: 'Pagador',
            'email' => $clienteEmail ?: 'cliente@email.com',
            'tax_id' => $cpfCnpj ?: '00000000000',
        ],
        'items' => [
            [
                'reference_id' => $orderRef,
                'name' => $descricao,
                'quantity' => 1,
                'unit_amount' => (int) round($valor * 100),
            ],
        ],
        'charges' => [
            [
                'reference_id' => $orderRef,
                'description' => $descricao,
                'amount' => [
                    'value' => (int) round($valor * 100),
                    'currency' => 'BRL',
                ],
                'payment_method' => [
                    'type' => 'BOLETO',
                    'boleto' => [
                        'template' => 'cobranca',
                        'due_date' => date('Y-m-d', strtotime('+30 days')),
                        'days_until_expiration' => '30',
                        'holder' => [
                            'name' => $clienteNome ?: '',
                            'tax_id' => $cpfCnpj ?: '',
                            'email' => $clienteEmail ?: '',
                            'address' => [
                                'street' => $clienteLogradouro ?: '',
                                'number' => $clienteNumero ?: '0',
                                'locality' => $clienteBairro ?: '',
                                'city' => $clienteCidade ?: '',
                                'region' => $clienteEstado ?: '',
                                'region_code' => $clienteEstado ?: '',
                                'country' => 'BRA',
                                'postal_code' => preg_replace('/[^0-9]/', '', $clienteCep ?: ''),
                            ],
                        ],
                        'instruction_lines' => [
                            'line_1' => 'Pagamento até a data de vencimento.',
                            'line_2' => $descricao,
                        ],
                    ],
                ],
            ],
        ],
    ];

    $webhookUrl = $config['pagbank_webhook_url'] ?? '';
    if (!empty($webhookUrl) && strpos($webhookUrl, 'localhost') === false && strpos($webhookUrl, '127.0.0.1') === false) {
        $dados['notification_urls'] = [$webhookUrl];
    }

    $resultado = criarPedidoPagBank($dados);
    if (isset($resultado['sucesso']) && $resultado['sucesso']) {
        $resp = $resultado['dados'];
        $orderId = $resp['id'] ?? '';
        $chargeId = '';
        $barcode = '';
        $formattedBarcode = '';
        $boletoPdfUrl = '';

        if (!empty($resp['charges'])) {
            $charge = $resp['charges'][0];
            $chargeId = $charge['id'] ?? '';
            $status = $charge['status'] ?? '';
            $boleto = $charge['payment_method']['boleto'] ?? [];
            $barcode = $boleto['barcode'] ?? '';
            $formattedBarcode = $boleto['formatted_barcode'] ?? '';

            if ($status === 'DECLINED') {
                $msg = $charge['payment_response']['message'] ?? 'Boleto não autorizado pelo PagBank';
                return ['erro' => $msg];
            }

            foreach (($charge['links'] ?? []) as $link) {
                if (stripos(($link['media'] ?? ''), 'pdf') !== false || stripos(($link['href'] ?? ''), '.pdf') !== false) {
                    $boletoPdfUrl = $link['href'] ?? '';
                }
            }
        }

        $boletoLocalUrl = '';
        if (!empty($boletoPdfUrl)) {
            $pdfDir = __DIR__ . '/../assets/boletos_pagbank';
            if (!is_dir($pdfDir)) {
                mkdir($pdfDir, 0755, true);
            }
            $pdfContent = @file_get_contents($boletoPdfUrl);
            if ($pdfContent !== false) {
                $pdfFile = $pdfDir . '/' . $orderRef . '.pdf';
                file_put_contents($pdfFile, $pdfContent);
                $boletoLocalUrl = '/cobranca/assets/boletos_pagbank/' . $orderRef . '.pdf';
            }
        }

        return [
            'sucesso' => true,
            'payment_id' => $orderId,
            'reference_id' => $orderRef,
            'boleto_url' => $boletoLocalUrl ?: $boletoPdfUrl,
            'boleto_codigo_barras' => $formattedBarcode ?: $barcode,
            'boleto_linha_digitavel' => $formattedBarcode ?: $barcode,
        ];
    }

    return $resultado;
}

function consultarPedidoPagBank($orderId) {
    $config = getConfigPagBank();
    if (empty($config['pagbank_token'])) {
        return null;
    }

    $baseUrl = getPagBankBaseUrl();
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $baseUrl . '/orders/' . $orderId,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $config['pagbank_token'],
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log("[PAGBANK] Erro consultar {$orderId}: " . $curlError);
        return null;
    }

    $result = json_decode($response, true);
    if ($httpCode >= 200 && $httpCode < 300) {
        return $result;
    }

    error_log("[PAGBANK] Consultar {$orderId} HTTP {$httpCode} | Response: " . substr($response, 0, 300));
    return null;
}

function cancelarPedidoPagBank($orderId) {
    $config = getConfigPagBank();
    if (empty($config['pagbank_token'])) {
        return ['erro' => 'Token do PagBank não configurado.'];
    }

    $pedido = consultarPedidoPagBank($orderId);
    if (!$pedido) {
        return ['erro' => 'Não foi possível consultar o pedido no PagBank para cancelamento.'];
    }

    $charges = $pedido['charges'] ?? [];
    if (empty($charges)) {
        return ['sucesso' => true, 'sem_cobranca' => true];
    }

    $baseUrl = getPagBankBaseUrl();
    $erros = [];

    foreach ($charges as $charge) {
        $chargeId = $charge['id'] ?? '';
        $status = strtoupper($charge['status'] ?? '');
        if (empty($chargeId) || in_array($status, ['PAID', 'CANCELED', 'DECLINED', 'REFUNDED'])) {
            continue;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $baseUrl . '/orders/' . $orderId . '/charges/' . $chargeId . '/cancel',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['amount' => ['value' => $charge['amount']['value'] ?? 0]]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $config['pagbank_token'],
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            $erros[] = 'Erro de conexão PagBank: ' . $curlError;
            continue;
        }

        $result = json_decode($response, true);
        error_log("[PAGBANK] Cancelar {$orderId}/{$chargeId} HTTP {$httpCode} | Response: " . substr($response, 0, 300));

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['sucesso' => true, 'dados' => $result];
        }

        $erros[] = $result['error_messages'][0]['description'] ?? ($result['message'] ?? 'HTTP ' . $httpCode);
    }

    if ($erros) {
        return ['erro' => implode('; ', array_unique($erros))];
    }

    return ['sucesso' => true, 'sem_cobranca' => true];
}

function downloadPagBankQrCode($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $img = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300 && !empty($img)) {
        return 'data:image/png;base64,' . base64_encode($img);
    }
    return '';
}
