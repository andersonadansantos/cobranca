<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

function enviarWhatsApp($telefone, $mensagem) {
    $apiUrl = rtrim(getConfig('whatsapp_api_url', ''), '/');
    $apiKey = getConfig('whatsapp_api_key', '');
    $instance = getConfig('whatsapp_instance', '');
    $whatsappAtivo = getConfig('whatsapp_ativo', '0');

    if ($whatsappAtivo !== '1') return false;
    if (empty($apiUrl) || empty($apiKey) || empty($instance)) return false;
    if (empty($telefone)) return false;

    $telefone = preg_replace('/[^0-9]/', '', $telefone);
    if (strlen($telefone) < 10) return false;
    if (strlen($telefone) === 10) $telefone = '55' . $telefone;
    if (strlen($telefone) === 11) $telefone = '55' . $telefone;
    if (strlen($telefone) === 12 && substr($telefone, 0, 2) !== '55') $telefone = '55' . $telefone;

    $endpoint = "{$apiUrl}/message/sendText/{$instance}";
    $payload = [
        'number' => $telefone,
        'text' => $mensagem,
    ];

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'apikey: ' . $apiKey,
        ],
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) return false;
    $data = json_decode($resp, true);
    return ($data && isset($data['key']['id'])) ? true : false;
}

function enviarWhatsAppFatura($fatura, $tipo = 'antes', $dias = 0) {
    $telefone = $fatura['celular'] ?? $fatura['telefone'] ?? '';
    if (empty($telefone)) return false;

    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT nome_fantasia FROM administradores WHERE id = 1");
    $stmt->execute();
    $admin = $stmt->fetch();
    $nomeEmpresa = $admin['nome_fantasia'] ?: getNomeSistema();
    $linkFatura = getLinkFatura($fatura['id']);

    $pixTexto = '';
    if (!empty($fatura['pix_copia_cola'])) {
        $pixTexto = "\n💳 *Pague via PIX*\n";
        $pixTexto .= "Código Pix: {$fatura['pix_copia_cola']}\n\n";
    }

    $cpfCnpj = $fatura['cpf_cnpj'] ?? '';
    $acessoTexto = '';
    if (!empty($cpfCnpj)) {
        $acessoTexto = "\n🔐 *Acesso ao Painel de faturas:*\n";
        $acessoTexto .= "CPF/CNPJ: {$cpfCnpj}\n";
        $acessoTexto .= "SENHA: {$cpfCnpj}\n";
        $acessoTexto .= "_Sua senha padrão é seu CPF/CNPJ_\n\n";
    }

    if ($tipo === 'pagamento') {
        $msg = "*{$nomeEmpresa}*\n\n";
        $msg .= "Olá, {$fatura['nome_razao']}!\n\n";
        $msg .= "Recebemos o pagamento da sua fatura *{$fatura['numero']}*.\n\n";
        $msg .= "📋 Descrição: {$fatura['descricao']}\n";
        $msg .= "💰 Valor: *R$ " . number_format($fatura['valor_final'], 2, ',', '.') . "*\n\n";
        $msg .= "✅ Pagamento confirmado com sucesso!\n\n";
        $msg .= "Atenciosamente,\n*{$nomeEmpresa}*";
    } elseif ($tipo === 'antes') {
        $diasTexto = $dias > 0 ? " em {$dias} dia(s)" : "";
        $msg = "*{$nomeEmpresa}*\n\n";
        $msg .= "Olá, {$fatura['nome_razao']}!\n\n";
        $msg .= "Sua fatura *{$fatura['numero']}* vence{$diasTexto} em *" . date('d/m/Y', strtotime($fatura['data_vencimento'])) . "*.\n\n";
        $msg .= "📋 Descrição: {$fatura['descricao']}\n";
        $msg .= "💰 Valor: *R$ " . number_format($fatura['valor_final'], 2, ',', '.') . "*\n\n";
        $msg .= $pixTexto;
        $msg .= "🔗 Acesse sua fatura: {$linkFatura}\n\n";
        $msg .= $acessoTexto;
        $msg .= "Atenciosamente,\n*{$nomeEmpresa}*";
    } else {
        $diasAtraso = $dias > 0 ? $dias : max(1, round((time() - strtotime($fatura['data_vencimento'])) / 86400));
        $msg = "*{$nomeEmpresa}*\n\n";
        $msg .= "Olá, {$fatura['nome_razao']}!\n\n";
        $msg .= "Sua fatura *{$fatura['numero']}* está *vencida há {$diasAtraso} dia(s)*.\n\n";
        $msg .= "📋 Descrição: {$fatura['descricao']}\n";
        $msg .= "💰 Valor: *R$ " . number_format($fatura['valor_final'], 2, ',', '.') . "*\n";
        $msg .= "📅 Vencimento: " . date('d/m/Y', strtotime($fatura['data_vencimento'])) . "\n\n";
        $msg .= $pixTexto;
        $msg .= "Por favor, regularize sua situação.\n";
        $msg .= "🔗 Acesse sua fatura: {$linkFatura}\n\n";
        $msg .= $acessoTexto;
        $msg .= "Atenciosamente,\n*{$nomeEmpresa}*";
    }

    return enviarWhatsApp($telefone, $msg);
}