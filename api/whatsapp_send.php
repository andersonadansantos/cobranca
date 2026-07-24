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

    $ch = curl_init("{$apiUrl}/message/sendText/{$instance}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'number' => $telefone,
            'text' => $mensagem,
        ]),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'apikey: ' . $apiKey,
        ],
        CURLOPT_TIMEOUT => 15,
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

    $nomeSistema = getNomeSistema();
    $linkFatura = getLinkFatura($fatura['id']);

    if ($tipo === 'antes') {
        $diasTexto = $dias > 0 ? " em {$dias} dia(s)" : "";
        $msg = "*{$nomeSistema}*\n\n";
        $msg .= "Olá, {$fatura['nome_razao']}!\n\n";
        $msg .= "Sua fatura *{$fatura['numero']}* vence{$diasTexto} em *" . date('d/m/Y', strtotime($fatura['data_vencimento'])) . "*.\n\n";
        $msg .= "📋 Descrição: {$fatura['descricao']}\n";
        $msg .= "💰 Valor: *R$ " . number_format($fatura['valor_final'], 2, ',', '.') . "*\n\n";
        $msg .= "🔗 Acesse sua fatura: {$linkFatura}\n\n";
        $msg .= "Atenciosamente,\n*{$nomeSistema}*";
    } else {
        $diasAtraso = $dias > 0 ? $dias : max(1, round((time() - strtotime($fatura['data_vencimento'])) / 86400));
        $msg = "*{$nomeSistema}*\n\n";
        $msg .= "Olá, {$fatura['nome_razao']}!\n\n";
        $msg .= "Sua fatura *{$fatura['numero']}* está *vencida há {$diasAtraso} dia(s)*.\n\n";
        $msg .= "📋 Descrição: {$fatura['descricao']}\n";
        $msg .= "💰 Valor: *R$ " . number_format($fatura['valor_final'], 2, ',', '.') . "*\n";
        $msg .= "📅 Vencimento: " . date('d/m/Y', strtotime($fatura['data_vencimento'])) . "\n\n";
        $msg .= "Por favor, regularize sua situação.\n";
        $msg .= "🔗 Acesse sua fatura: {$linkFatura}\n\n";
        $msg .= "Atenciosamente,\n*{$nomeSistema}*";
    }

    return enviarWhatsApp($telefone, $msg);
}