<?php
// =====================================================
// FUNÇÕES COMPARTILHADAS DE E-MAIL
// =====================================================

if (!function_exists('getLogoEmail')) {
    function getLogoEmail() {
        $logo = getLogo();
        if (!$logo) return '';
        if (strpos($logo, 'http') === 0) return $logo;
        $protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'agenciawd.com.br';
        return "{$protocolo}://{$host}{$logo}";
    }
}

if (!function_exists('getLogoBase64')) {
    function getLogoBase64() {
        $logo = getLogo();
        if (!$logo) return '';
        $candidatos = [
            $_SERVER['DOCUMENT_ROOT'] ?? '',
            dirname(__DIR__),
            dirname(dirname(__DIR__)),
        ];
        $path = '';
        foreach ($candidatos as $base) {
            if (empty($base)) continue;
            $teste = rtrim($base, '/\\') . str_replace('/', DIRECTORY_SEPARATOR, $logo);
            if (file_exists($teste)) { $path = $teste; break; }
            $teste2 = rtrim($base, '/\\') . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . basename($logo);
            if (file_exists($teste2)) { $path = $teste2; break; }
        }
        if (empty($path) || !file_exists($path)) return '';
        $mime = 'image/png';
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg'])) $mime = 'image/jpeg';
        elseif ($ext === 'gif') $mime = 'image/gif';
        $data = base64_encode(file_get_contents($path));
        return "data:{$mime};base64,{$data}";
    }
}

if (!function_exists('getLogoLoginImg')) {
    function getLogoLoginImg() {
        $logo = getLogoLogin();
        if (!$logo) return '';
        $candidatos = [
            $_SERVER['DOCUMENT_ROOT'] ?? '',
            dirname(__DIR__),
            dirname(dirname(__DIR__)),
        ];
        $path = '';
        foreach ($candidatos as $base) {
            if (empty($base)) continue;
            $teste = rtrim($base, '/\\') . str_replace('/', DIRECTORY_SEPARATOR, $logo);
            if (file_exists($teste)) { $path = $teste; break; }
            $teste2 = rtrim($base, '/\\') . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . basename($logo);
            if (file_exists($teste2)) { $path = $teste2; break; }
        }
        if (empty($path) || !file_exists($path)) return '';
        $mime = 'image/png';
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg'])) $mime = 'image/jpeg';
        elseif ($ext === 'gif') $mime = 'image/gif';
        $data = base64_encode(file_get_contents($path));
        $src = "data:{$mime};base64,{$data}";
        return '<img src="' . $src . '" alt="Logo" style="max-width:160px;height:auto;margin-bottom:10px;">';
    }
}

if (!function_exists('getLogoTagEmail')) {
    function getLogoTagEmail() {
        $pdo = getConnection();
        $nome = getNomeSistema();
        $cnpj = '';
        if ($pdo) {
            $stmt = $pdo->prepare("SELECT nome_fantasia, cnpj FROM administradores WHERE id = 1");
            $stmt->execute();
            $admin = $stmt->fetch();
            if ($admin) {
                $nome = $admin['nome_fantasia'] ?: $nome;
                $cnpj = $admin['cnpj'] ?? '';
            }
        }
        $html = '<h2 style="margin:0 0 5px 0;color:#333;font-weight:bold;font-size:22px;">' . htmlspecialchars($nome) . '</h2>';
        if ($cnpj) {
            $html .= '<p style="margin:0;color:#666;font-size:13px;">CNPJ: ' . htmlspecialchars($cnpj) . '</p>';
        }
        return $html;
    }
}

if (!function_exists('getLinkFatura')) {
    function getLinkFatura($faturaId) {
        $protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $token = '';
        $pdo = getConnection();
        if ($pdo) {
            $stmt = $pdo->prepare("SELECT acesso_token FROM faturas WHERE id = ?");
            $stmt->execute([$faturaId]);
            $row = $stmt->fetch();
            if ($row && !empty($row['acesso_token'])) {
                $token = $row['acesso_token'];
            }
        }
        if ($token) {
            return "{$protocolo}://{$host}/cobranca/usuario/fatura.php?id={$faturaId}&token={$token}";
        }
        return "{$protocolo}://{$host}/cobranca/usuario/fatura.php?id={$faturaId}";
    }
}

if (!function_exists('montarMensagemHtml')) {
    function gerarQrCodeEmail($pixCopiaCola) {
        require_once __DIR__ . '/phpqrcode.php';
        try {
            ob_start();
            QRcode::png($pixCopiaCola, false, QR_ECLEVEL_L, 5, 2);
            $img = ob_get_clean();
            return ($img !== false && !empty($img)) ? base64_encode($img) : '';
        } catch (Exception $e) {
            return '';
        }
    }

    function montarMensagemHtml($fatura, $tipo, $dias = 0) {
        $nomeSistema = getNomeSistema();
        $linkFatura = getLinkFatura($fatura['id']);
        $linkPag = $fatura['link_pagamento'] ?? '';
        $logoTag = getLogoTagEmail();

        $chaveTemplate = ($tipo === 'antes') ? 'template_email_corpo_antes' : 'template_email_corpo_depois';
        $templateHtml = getConfig($chaveTemplate, '');
        $corPrimaria = getCorPrimaria();

        if (!empty($templateHtml)) {
            $mensagemHtml = preg_replace('#<img[^>]*(?:alt=["\'](?:Logo|Logo do Sistema)["\']|class=["\']email-logo["\'])[^>]*>#i', $logoTag, $templateHtml);
        } else {
            $mensagemHtml = '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"></head><body style="margin:0;padding:0;background:#f4f6f9;font-family:\'Inter\',Arial,sans-serif;"><div style="max-width:600px;margin:30px auto;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.06);"><div style="background:' . $corPrimaria . ';height:50px;"></div><div style="padding:30px 30px 0 30px;text-align:center;">' . $logoTag . '</div><div style="padding:20px 30px 30px 30px;">{{CONTEUDO}}</div></div></body></html>';
        }

        if ($tipo === 'antes') {
            $conteudo = '<div style="background:#ffffff;border:1px solid #e8edf5;border-radius:12px;padding:24px;margin-bottom:24px;">';
            $conteudo .= '<h2 style="margin:0 0 16px 0;font-size:22px;font-weight:700;color:#1a1a2e;">Lembrete de Fatura</h2>';
            $conteudo .= '<p style="margin:0 0 12px 0;font-size:15px;color:#333;line-height:1.6;">Olá, <strong>' . htmlspecialchars($fatura['nome_razao']) . '</strong>,</p>';
            $conteudo .= '<p style="margin:0 0 12px 0;font-size:15px;color:#333;line-height:1.6;">Identificamos que sua fatura <strong>' . htmlspecialchars($fatura['numero']) . '</strong> vence em <strong>' . date('d/m/Y', strtotime($fatura['data_vencimento'])) . '</strong>.</p>';
            $conteudo .= '</div>';
            $conteudo .= '<div style="background:#f8f9fa;border:1px solid #e8edf5;border-radius:12px;padding:20px;margin-bottom:24px;">';
            $conteudo .= '<table style="width:100%;border-collapse:collapse;">';
            $conteudo .= '<tr><td style="padding:10px 0;color:#666;font-size:14px;">Descrição</td><td style="padding:10px 0;font-size:14px;font-weight:500;color:#333;text-align:right;">' . htmlspecialchars($fatura['descricao']) . '</td></tr>';
            $conteudo .= '<tr><td style="padding:10px 0;color:#666;font-size:14px;border-top:1px solid #e8edf5;">Valor</td><td style="padding:10px 0;font-size:18px;font-weight:700;color:' . $corPrimaria . ';text-align:right;border-top:1px solid #e8edf5;">R$ ' . number_format($fatura['valor_final'], 2, ',', '.') . '</td></tr>';
            $conteudo .= '</table>';
            $conteudo .= '</div>';
            if (!empty($fatura['pix_copia_cola'])) {
                $pixLimpo = str_replace(["\r\n", "\r", "\n"], '', $fatura['pix_copia_cola']);
                $conteudo .= '<div style="background:#ffffff;border:2px dashed ' . $corPrimaria . ';border-radius:12px;padding:24px;margin-bottom:24px;text-align:center;">';
                $conteudo .= '<h3 style="margin:0 0 4px 0;font-size:20px;font-weight:700;color:#1a1a2e;">Pague com PIX</h3>';
                $conteudo .= '<p style="margin:0 0 16px 0;font-size:13px;color:#666;">Copie o código PIX abaixo</p>';
                $conteudo .= '<div style="background:#f8f9fa;border:1px dashed ' . $corPrimaria . ';border-radius:8px;padding:12px;margin-bottom:12px;text-align:left;">';
                $conteudo .= '<p style="margin:0 0 6px 0;font-size:12px;color:#666;">Código PIX Copia e Cola:</p>';
                $conteudo .= '<div style="font-family:\'Courier New\',monospace;font-size:12px;word-break:break-all;color:#333;user-select:all;-webkit-user-select:all;">' . htmlspecialchars($pixLimpo) . '</div>';
                $conteudo .= '</div>';
                $conteudo .= '<div style="margin-top:16px;padding:12px;background:#fff8e1;border-radius:8px;display:flex;align-items:center;gap:8px;justify-content:center;">';
                $conteudo .= '<span style="font-size:16px;">🛡️</span>';
                $conteudo .= '<span style="font-size:12px;color:#666;">Pagamento 100% seguro via PIX</span>';
                $conteudo .= '</div>';
                $conteudo .= '</div>';
            }
            $conteudo .= '<a href="' . htmlspecialchars($linkFatura) . '" style="display:block;text-align:center;color:' . $corPrimaria . ';text-decoration:none;padding:14px;border:2px solid ' . $corPrimaria . ';border-radius:8px;font-weight:600;font-size:15px;margin-bottom:24px;">Ver Fatura Completa →</a>';
            $cpfCnpjFmt = $fatura['cpf_cnpj'] ?? '';
            $conteudo .= '<div style="background:#f8f9fa;border:1px solid #e8edf5;border-radius:12px;padding:20px;margin-bottom:24px;">';
            $conteudo .= '<h4 style="margin:0 0 12px 0;font-size:14px;font-weight:600;color:#333;text-align:center;">Dados de Acesso ao Painel</h4>';
            $conteudo .= '<table style="width:100%;">';
            $conteudo .= '<tr><td style="padding:6px 0;font-size:13px;color:#666;">Usuário:</td><td style="padding:6px 0;font-size:13px;font-weight:600;color:#333;text-align:right;">' . htmlspecialchars($cpfCnpjFmt) . '</td></tr>';
            $conteudo .= '<tr><td style="padding:6px 0;font-size:13px;color:#666;">Senha:</td><td style="padding:6px 0;font-size:13px;font-weight:600;color:#333;text-align:right;">' . htmlspecialchars($cpfCnpjFmt) . '</td></tr>';
            $conteudo .= '</table>';
            $conteudo .= '<p style="margin:8px 0 0 0;font-size:11px;color:#999;text-align:center;">Sua senha padrão é seu CPF/CNPJ. Altere após o primeiro acesso.</p>';
            $conteudo .= '</div>';
            if ($linkPag) {
                $conteudo .= '<div style="text-align:center;margin-bottom:24px;"><a href="' . htmlspecialchars($linkPag) . '" style="color:' . $corPrimaria . ';font-weight:600;text-decoration:none;">Pagar agora via Mercado Pago →</a></div>';
            }
            $conteudo .= '<p style="color:#999;font-size:12px;text-align:center;margin:0;">Atenciosamente,<br><strong>' . htmlspecialchars($nomeSistema) . '</strong></p>';
        } else {
            $diasAtraso = $dias > 0 ? $dias : ((strtotime(date('Y-m-d')) - strtotime($fatura['data_vencimento'])) / 86400);
            $conteudo = '<div style="background:#ffffff;border:1px solid #e8edf5;border-radius:12px;padding:24px;margin-bottom:24px;">';
            $conteudo .= '<h2 style="margin:0 0 16px 0;font-size:22px;font-weight:700;color:#991b1b;">Fatura Vencida</h2>';
            $conteudo .= '<p style="margin:0 0 12px 0;font-size:15px;color:#333;line-height:1.6;">Olá, <strong>' . htmlspecialchars($fatura['nome_razao']) . '</strong>,</p>';
            $conteudo .= '<p style="margin:0 0 12px 0;font-size:15px;color:#333;line-height:1.6;">Sua fatura <strong>' . htmlspecialchars($fatura['numero']) . '</strong> encontra-se vencida há <strong>' . intval($diasAtraso) . ' dia(s)</strong>.</p>';
            $conteudo .= '</div>';
            $conteudo .= '<div style="background:#f8f9fa;border:1px solid #e8edf5;border-radius:12px;padding:20px;margin-bottom:24px;">';
            $conteudo .= '<table style="width:100%;border-collapse:collapse;">';
            $conteudo .= '<tr><td style="padding:10px 0;color:#666;font-size:14px;">Descrição</td><td style="padding:10px 0;font-size:14px;font-weight:500;color:#333;text-align:right;">' . htmlspecialchars($fatura['descricao']) . '</td></tr>';
            $conteudo .= '<tr><td style="padding:10px 0;color:#666;font-size:14px;border-top:1px solid #e8edf5;">Valor</td><td style="padding:10px 0;font-size:18px;font-weight:700;color:#dc2626;text-align:right;border-top:1px solid #e8edf5;">R$ ' . number_format($fatura['valor_final'], 2, ',', '.') . '</td></tr>';
            $conteudo .= '<tr><td style="padding:10px 0;color:#666;font-size:14px;border-top:1px solid #e8edf5;">Vencimento</td><td style="padding:10px 0;font-size:14px;font-weight:500;color:#333;text-align:right;border-top:1px solid #e8edf5;">' . date('d/m/Y', strtotime($fatura['data_vencimento'])) . '</td></tr>';
            $conteudo .= '</table>';
            $conteudo .= '</div>';
            if (!empty($fatura['pix_copia_cola'])) {
                $pixLimpo = str_replace(["\r\n", "\r", "\n"], '', $fatura['pix_copia_cola']);
                $conteudo .= '<div style="background:#ffffff;border:2px dashed #dc2626;border-radius:12px;padding:24px;margin-bottom:24px;text-align:center;">';
                $conteudo .= '<h3 style="margin:0 0 4px 0;font-size:20px;font-weight:700;color:#991b1b;">Pague com PIX</h3>';
                $conteudo .= '<p style="margin:0 0 16px 0;font-size:13px;color:#666;">Copie o código PIX abaixo</p>';
                $conteudo .= '<div style="background:#f8f9fa;border:1px dashed #dc2626;border-radius:8px;padding:12px;margin-bottom:12px;text-align:left;">';
                $conteudo .= '<p style="margin:0 0 6px 0;font-size:12px;color:#666;">Código PIX Copia e Cola:</p>';
                $conteudo .= '<div style="font-family:\'Courier New\',monospace;font-size:12px;word-break:break-all;color:#333;user-select:all;-webkit-user-select:all;">' . htmlspecialchars($pixLimpo) . '</div>';
                $conteudo .= '</div>';
                $conteudo .= '<div style="margin-top:16px;padding:12px;background:#fff8e1;border-radius:8px;display:flex;align-items:center;gap:8px;justify-content:center;">';
                $conteudo .= '<span style="font-size:16px;">🛡️</span>';
                $conteudo .= '<span style="font-size:12px;color:#666;">Pagamento 100% seguro via PIX</span>';
                $conteudo .= '</div>';
                $conteudo .= '</div>';
            }
            $conteudo .= '<a href="' . htmlspecialchars($linkFatura) . '" style="display:block;text-align:center;color:#dc2626;text-decoration:none;padding:14px;border:2px solid #dc2626;border-radius:8px;font-weight:600;font-size:15px;margin-bottom:24px;">Regularizar Fatura →</a>';
            $cpfCnpjFmt = $fatura['cpf_cnpj'] ?? '';
            $conteudo .= '<div style="background:#f8f9fa;border:1px solid #e8edf5;border-radius:12px;padding:20px;margin-bottom:24px;">';
            $conteudo .= '<h4 style="margin:0 0 12px 0;font-size:14px;font-weight:600;color:#333;text-align:center;">Dados de Acesso ao Painel</h4>';
            $conteudo .= '<table style="width:100%;">';
            $conteudo .= '<tr><td style="padding:6px 0;font-size:13px;color:#666;">Usuário:</td><td style="padding:6px 0;font-size:13px;font-weight:600;color:#333;text-align:right;">' . htmlspecialchars($cpfCnpjFmt) . '</td></tr>';
            $conteudo .= '<tr><td style="padding:6px 0;font-size:13px;color:#666;">Senha:</td><td style="padding:6px 0;font-size:13px;font-weight:600;color:#333;text-align:right;">' . htmlspecialchars($cpfCnpjFmt) . '</td></tr>';
            $conteudo .= '</table>';
            $conteudo .= '<p style="margin:8px 0 0 0;font-size:11px;color:#999;text-align:center;">Sua senha padrão é seu CPF/CNPJ. Altere após o primeiro acesso.</p>';
            $conteudo .= '</div>';
            if ($linkPag) {
                $conteudo .= '<div style="text-align:center;margin-bottom:24px;"><a href="' . htmlspecialchars($linkPag) . '" style="color:' . $corPrimaria . ';font-weight:600;text-decoration:none;">Pagar agora via Mercado Pago →</a></div>';
            }
            $conteudo .= '<p style="color:#999;font-size:12px;text-align:center;margin:0;">Atenciosamente,<br><strong>' . htmlspecialchars($nomeSistema) . '</strong></p>';
        }

        $mensagemHtml = str_replace('{{CONTEUDO}}', $conteudo, $mensagemHtml);
        return $mensagemHtml;
    }
}

if (!function_exists('montarMensagemTxt')) {
    function montarMensagemTxt($fatura, $tipo, $dias = 0) {
        $nomeSistema = getNomeSistema();
        $linkFatura = getLinkFatura($fatura['id']);
        $linkPag = $fatura['link_pagamento'] ?? '';

        if ($tipo === 'antes') {
            $msg = "Olá, {$fatura['nome_razao']}!\n\n";
            $msg .= "Identificamos que sua fatura {$fatura['numero']} vence em " . date('d/m/Y', strtotime($fatura['data_vencimento'])) . ".\n\n";
            $msg .= "Descrição: {$fatura['descricao']}\n";
            $msg .= "Valor: R$ " . number_format($fatura['valor_final'], 2, ',', '.') . "\n\n";
            if (!empty($fatura['pix_copia_cola'])) {
                $msg .= "Pague via PIX:\n";
                $pixLimpo = str_replace(["\r\n", "\r", "\n"], '', $fatura['pix_copia_cola']);
                $msg .= "Código: {$pixLimpo}\n\n";
            }
            $msg .= "Acesse sua fatura: {$linkFatura}\n\n";
            $cpfCnpjFmt = $fatura['cpf_cnpj'] ?? '';
            $msg .= "--- Dados de Acesso ao Painel ---\n";
            $msg .= "Usuário: {$cpfCnpjFmt}\n";
            $msg .= "Senha: {$cpfCnpjFmt}\n";
            $msg .= "(Sua senha padrão é seu CPF/CNPJ. Altere após o primeiro acesso.)\n\n";
            if ($linkPag) {
                $msg .= "Pagar agora: {$linkPag}\n\n";
            }
            $msg .= "Atenciosamente,\n{$nomeSistema}";
        } else {
            $diasAtraso = $dias > 0 ? $dias : ((strtotime(date('Y-m-d')) - strtotime($fatura['data_vencimento'])) / 86400);
            $msg = "Olá, {$fatura['nome_razao']}!\n\n";
            $msg .= "Sua fatura {$fatura['numero']} encontra-se vencida há " . intval($diasAtraso) . " dia(s).\n\n";
            $msg .= "Descrição: {$fatura['descricao']}\n";
            $msg .= "Valor: R$ " . number_format($fatura['valor_final'], 2, ',', '.') . "\n";
            $msg .= "Vencimento: " . date('d/m/Y', strtotime($fatura['data_vencimento'])) . "\n\n";
            if (!empty($fatura['pix_copia_cola'])) {
                $msg .= "Pague via PIX:\n";
                $pixLimpo = str_replace(["\r\n", "\r", "\n"], '', $fatura['pix_copia_cola']);
                $msg .= "Código: {$pixLimpo}\n\n";
            }
            $msg .= "Acesse sua fatura: {$linkFatura}\n\n";
            $cpfCnpjFmt = $fatura['cpf_cnpj'] ?? '';
            $msg .= "--- Dados de Acesso ao Painel ---\n";
            $msg .= "Usuário: {$cpfCnpjFmt}\n";
            $msg .= "Senha: {$cpfCnpjFmt}\n";
            $msg .= "(Sua senha padrão é seu CPF/CNPJ. Altere após o primeiro acesso.)\n\n";
            if ($linkPag) {
                $msg .= "Pagar agora: {$linkPag}\n\n";
            }
            $msg .= "Atenciosamente,\n{$nomeSistema}";
        }

        return $msg;
    }
}

if (!function_exists('enviarEmail')) {
    function enviarEmail($host, $port, $user, $pass, $fromEmail, $fromNome, $ssl, $paraEmail, $paraNome, $assunto, $mensagemHtml, $mensagemTxt) {
        $proto = ($ssl === 'ssl') ? 'ssl://' : '';
        $errno = 0;
        $errstr = '';
        $connexion = @fsockopen($proto . $host, intval($port), $errno, $errstr, 15);
        if (!$connexion) {
            return false;
        }

        @fgets($connexion, 512);

        @fputs($connexion, "EHLO " . gethostname() . "\r\n");
        stream_set_timeout($connexion, 5);
        $ehloResponse = '';
        for ($i = 0; $i < 10; $i++) {
            $r = @fgets($connexion, 512);
            $ehloResponse .= $r;
            if (substr($r, 0, 3) === '250' && substr($r, 3, 1) === ' ') break;
        }

        if ($ssl === 'tls') {
            @fputs($connexion, "STARTTLS\r\n");
            $r = @fgets($connexion, 512);
            if (substr($r, 0, 3) === '220') {
                stream_context_set_option($connexion, 'ssl', 'verify_peer', false);
                stream_context_set_option($connexion, 'ssl', 'verify_peer_name', false);
                @stream_socket_enable_crypto($connexion, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                @fputs($connexion, "EHLO " . gethostname() . "\r\n");
                $ehloResponse = '';
                for ($i = 0; $i < 10; $i++) {
                    $r = @fgets($connexion, 512);
                    $ehloResponse .= $r;
                    if (substr($r, 0, 3) === '250' && substr($r, 3, 1) === ' ') break;
                }
            }
        }

        $authPlain = stripos($ehloResponse, 'AUTH') !== false && stripos($ehloResponse, 'PLAIN') !== false;
        $authLogin = stripos($ehloResponse, 'AUTH') !== false && stripos($ehloResponse, 'LOGIN') !== false;
        $authOk = false;

        if ($authPlain) {
            @fputs($connexion, "AUTH PLAIN\r\n");
            $r = @fgets($connexion, 512);
            if (substr($r, 0, 3) === '334') {
                @fputs($connexion, base64_encode("\0" . $user . "\0" . $pass) . "\r\n");
                $r = @fgets($connexion, 512);
                if (substr($r, 0, 3) === '235') {
                    $authOk = true;
                }
            }
        }

        if (!$authOk && $authLogin) {
            @fputs($connexion, "AUTH LOGIN\r\n");
            $r = @fgets($connexion, 512);
            if (substr($r, 0, 3) === '334') {
                @fputs($connexion, base64_encode($user) . "\r\n");
                $r = @fgets($connexion, 512);
                if (substr($r, 0, 3) === '334') {
                    @fputs($connexion, base64_encode($pass) . "\r\n");
                    $r = @fgets($connexion, 512);
                    if (substr($r, 0, 3) === '235') {
                        $authOk = true;
                    }
                }
            }
        }

        if (!$authOk) {
            @fclose($connexion);
            return false;
        }

        @fputs($connexion, "MAIL FROM:<{$fromEmail}>\r\n");
        @fgets($connexion, 512);
        @fputs($connexion, "RCPT TO:<{$paraEmail}>\r\n");
        $r = @fgets($connexion, 512);
        if (substr($r, 0, 3) !== '250') {
            @fclose($connexion);
            return false;
        }

        @fputs($connexion, "DATA\r\n");
        @fgets($connexion, 512);

        $boundary = md5(uniqid(time()));
        $body  = "From: =?UTF-8?B?" . base64_encode($fromNome) . "?= <{$fromEmail}>\r\n";
        $body .= "Reply-To: {$fromEmail}\r\n";
        $body .= "Date: " . date('r') . "\r\n";
        $body .= "To: =?UTF-8?B?" . base64_encode($paraNome) . "?= <{$paraEmail}>\r\n";
        $body .= "Subject: =?UTF-8?B?" . base64_encode($assunto) . "?=\r\n";
        $body .= "MIME-Version: 1.0\r\n";
        $body .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
        $body .= "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= $mensagemTxt . "\r\n\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= $mensagemHtml . "\r\n\r\n";
        $body .= "--{$boundary}--\r\n.\r\n";

        @fputs($connexion, $body);
        $r = @fgets($connexion, 512);
        @fputs($connexion, "QUIT\r\n");
        @fclose($connexion);

        return true;
    }
}

if (!function_exists('enviarEmailFatura')) {
    function enviarEmailFatura($fatura, $tipo = 'antes') {
        $smtpHost = getConfig('smtp_host', '');
        $smtpPort = getConfig('smtp_port', '587');
        $smtpUser = getConfig('smtp_usuario', '');
        $smtpPass = getConfig('smtp_senha', '');
        $smtpFrom = getConfig('smtp_from_email', '');
        $smtpNome = getConfig('smtp_from_nome', 'Sistema de Cobrança');
        $smtpSsl  = getConfig('smtp_ssl', 'tls');

        if (empty($smtpHost) || empty($smtpUser) || empty($smtpFrom) || empty($fatura['email'])) {
            return false;
        }

        if (empty($fatura['pix_copia_cola']) && !empty($fatura['id']) && ($fatura['status'] ?? '') !== 'pago') {
            if (!function_exists('criarPagamento')) {
                require_once __DIR__ . '/mercadopago.php';
            }
            $resultado = criarPagamento($fatura['descricao'], $fatura['valor_final'], $fatura['email'], $fatura['nome_razao']);
            if (isset($resultado['sucesso']) && $resultado['sucesso']) {
                $fatura['pix_copia_cola'] = $resultado['qr_code_copia_cola'] ?? '';
                $fatura['pix_qrcode'] = $resultado['qr_code'] ?? '';
                $fatura['link_pagamento'] = $resultado['link_pagamento'] ?? '';
                $pdo = getConnection();
                $apiAtiva = getApiAtiva();
                if ($apiAtiva === 'inter' || $apiAtiva === 'bb') {
                    $stmt = $pdo->prepare("UPDATE faturas SET pix_qrcode = ?, pix_copia_cola = ?, link_pagamento = ?, mp_payment_id = ?, inter_codigo_solicitacao = ? WHERE id = ?");
                    $stmt->execute([$fatura['pix_qrcode'], $fatura['pix_copia_cola'], $fatura['link_pagamento'], null, $resultado['payment_id'], $fatura['id']]);
                } else {
                    $stmt = $pdo->prepare("UPDATE faturas SET pix_qrcode = ?, pix_copia_cola = ?, link_pagamento = ?, mp_payment_id = ? WHERE id = ?");
                    $stmt->execute([$fatura['pix_qrcode'], $fatura['pix_copia_cola'], $fatura['link_pagamento'], $resultado['payment_id'], $fatura['id']]);
                }
            }
        }

        $assunto = getConfig(($tipo === 'antes') ? 'template_email_assunto_antes' : 'template_email_assunto_depois', 'Fatura ' . $fatura['numero']);
        $assunto = str_replace(['{numero}', '{data_vencimento}', '{valor}'], [
            $fatura['numero'],
            date('d/m/Y', strtotime($fatura['data_vencimento'])),
            number_format($fatura['valor_final'], 2, ',', '.')
        ], $assunto);

        $msgHtml = montarMensagemHtml($fatura, $tipo);
        $msgTxt  = montarMensagemTxt($fatura, $tipo);

        return enviarEmail($smtpHost, $smtpPort, $smtpUser, $smtpPass, $smtpFrom, $smtpNome, $smtpSsl, $fatura['email'], $fatura['nome_razao'], $assunto, $msgHtml, $msgTxt);
    }
}

if (!function_exists('montarMensagemPagamentoHtml')) {
    function montarMensagemPagamentoHtml($fatura) {
        $nomeSistema = getNomeSistema();
        $linkFatura = getLinkFatura($fatura['id']);
        $logoTag = getLogoTagEmail();

        $templateHtml = getConfig('template_email_corpo_pagamento', '');
        $corPrimaria = getCorPrimaria();

        if (!empty($templateHtml)) {
            $mensagemHtml = preg_replace('#<img[^>]*(?:alt=["\'](?:Logo|Logo do Sistema)["\']|class=["\']email-logo["\'])[^>]*>#i', $logoTag, $templateHtml);
        } else {
            $mensagemHtml = '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"></head><body style="margin:0;padding:0;background:#f4f6f9;font-family:\'Inter\',Arial,sans-serif;"><div style="max-width:600px;margin:30px auto;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.06);"><div style="background:' . $corPrimaria . ';height:50px;"></div><div style="padding:30px 30px 0 30px;text-align:center;">' . $logoTag . '</div><div style="padding:20px 30px 30px 30px;">{{CONTEUDO}}</div></div></body></html>';
        }

        $dataPagamento = !empty($fatura['data_pagamento']) ? date('d/m/Y', strtotime($fatura['data_pagamento'])) : date('d/m/Y');

        $conteudo = '<div style="background:#ffffff;border:1px solid #e8edf5;border-radius:12px;padding:24px;margin-bottom:24px;">';
        $conteudo .= '<h2 style="margin:0 0 16px 0;font-size:22px;font-weight:700;color:#166534;">Pagamento Confirmado</h2>';
        $conteudo .= '<p style="margin:0 0 12px 0;font-size:15px;color:#333;line-height:1.6;">Olá, <strong>' . htmlspecialchars($fatura['nome_razao']) . '</strong>,</p>';
        $conteudo .= '<p style="margin:0 0 12px 0;font-size:15px;color:#333;line-height:1.6;">Confirmamos o recebimento do pagamento da sua fatura <strong>' . htmlspecialchars($fatura['numero']) . '</strong>.</p>';
        $conteudo .= '</div>';
        $conteudo .= '<div style="background:#f8f9fa;border:1px solid #e8edf5;border-radius:12px;padding:20px;margin-bottom:24px;">';
        $conteudo .= '<table style="width:100%;border-collapse:collapse;">';
        $conteudo .= '<tr><td style="padding:10px 0;color:#666;font-size:14px;">Descrição</td><td style="padding:10px 0;font-size:14px;font-weight:500;color:#333;text-align:right;">' . htmlspecialchars($fatura['descricao']) . '</td></tr>';
        $conteudo .= '<tr><td style="padding:10px 0;color:#666;font-size:14px;border-top:1px solid #e8edf5;">Valor Pago</td><td style="padding:10px 0;font-size:18px;font-weight:700;color:#16a34a;text-align:right;border-top:1px solid #e8edf5;">R$ ' . number_format($fatura['valor_final'], 2, ',', '.') . '</td></tr>';
        $conteudo .= '<tr><td style="padding:10px 0;color:#666;font-size:14px;border-top:1px solid #e8edf5;">Data do Pagamento</td><td style="padding:10px 0;font-size:14px;font-weight:500;color:#333;text-align:right;border-top:1px solid #e8edf5;">' . $dataPagamento . '</td></tr>';
        $conteudo .= '</table>';
        $conteudo .= '</div>';
        $conteudo .= '<div style="text-align:center;margin-bottom:24px;">';
        $conteudo .= '<a href="' . htmlspecialchars($linkFatura) . '" style="display:inline-block;background:#16a34a;color:#fff;padding:14px 40px;border-radius:8px;text-decoration:none;font-weight:600;font-size:15px;">Acessar Painel →</a>';
        $conteudo .= '</div>';
        $conteudo .= '<p style="color:#999;font-size:12px;text-align:center;margin:0;">Atenciosamente,<br><strong>' . htmlspecialchars($nomeSistema) . '</strong></p>';

        $mensagemHtml = str_replace('{{CONTEUDO}}', $conteudo, $mensagemHtml);
        return $mensagemHtml;
    }
}

if (!function_exists('montarMensagemPagamentoTxt')) {
    function montarMensagemPagamentoTxt($fatura) {
        $nomeSistema = getNomeSistema();
        $linkFatura = getLinkFatura($fatura['id']);
        $dataPagamento = !empty($fatura['data_pagamento']) ? date('d/m/Y', strtotime($fatura['data_pagamento'])) : date('d/m/Y');

        $msg = "Olá, {$fatura['nome_razao']}!\n\n";
        $msg .= "Confirmamos o recebimento do pagamento da sua fatura {$fatura['numero']}.\n\n";
        $msg .= "Descrição: {$fatura['descricao']}\n";
        $msg .= "Valor Pago: R$ " . number_format($fatura['valor_final'], 2, ',', '.') . "\n";
        $msg .= "Data do Pagamento: {$dataPagamento}\n\n";
        $msg .= "Sua fatura está quitada.\n";
        $msg .= "Acesse sua conta: {$linkFatura}\n\n";
        $msg .= "Atenciosamente,\n{$nomeSistema}";

        return $msg;
    }
}

if (!function_exists('enviarEmailPagamento')) {
    function enviarEmailPagamento($fatura) {
        $smtpHost = getConfig('smtp_host', '');
        $smtpPort = getConfig('smtp_port', '587');
        $smtpUser = getConfig('smtp_usuario', '');
        $smtpPass = getConfig('smtp_senha', '');
        $smtpFrom = getConfig('smtp_from_email', '');
        $smtpNome = getConfig('smtp_from_nome', 'Sistema de Cobrança');
        $smtpSsl  = getConfig('smtp_ssl', 'tls');

        if (empty($smtpHost) || empty($smtpUser) || empty($smtpFrom) || empty($fatura['email'])) {
            return false;
        }

        $assunto = getConfig('template_email_assunto_pagamento', 'Pagamento Confirmado - Fatura {numero}');
        $assunto = str_replace(['{numero}', '{data_vencimento}', '{valor}', '{data_pagamento}'], [
            $fatura['numero'],
            date('d/m/Y', strtotime($fatura['data_vencimento'])),
            number_format($fatura['valor_final'], 2, ',', '.'),
            !empty($fatura['data_pagamento']) ? date('d/m/Y', strtotime($fatura['data_pagamento'])) : date('d/m/Y')
        ], $assunto);

        $msgHtml = montarMensagemPagamentoHtml($fatura);
        $msgTxt  = montarMensagemPagamentoTxt($fatura);

        return enviarEmail($smtpHost, $smtpPort, $smtpUser, $smtpPass, $smtpFrom, $smtpNome, $smtpSsl, $fatura['email'], $fatura['nome_razao'], $assunto, $msgHtml, $msgTxt);
    }
}
