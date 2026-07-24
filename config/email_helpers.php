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
    function montarMensagemHtml($fatura, $tipo, $dias = 0) {
        $nomeSistema = getNomeSistema();
        $linkFatura = getLinkFatura($fatura['id']);
        $linkPag = $fatura['link_pagamento'] ?? '';
        $logoTag = '<h2 style="margin:0 0 20px 0;color:#fff;">' . htmlspecialchars($nomeSistema) . '</h2>';

        $chaveTemplate = ($tipo === 'antes') ? 'template_email_corpo_antes' : 'template_email_corpo_depois';
        $templateHtml = getConfig($chaveTemplate, '');

        if (!empty($templateHtml)) {
            $mensagemHtml = $templateHtml;
        } else {
            $corPrimaria = getCorPrimaria();
            $mensagemHtml = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;"><div style="max-width:600px;margin:30px auto;background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);"><div style="background:' . $corPrimaria . ';padding:24px 30px;text-align:center;">' . $logoTag . '</div><div style="padding:30px;">{{CONTEUDO}}</div></div></body></html>';
        }

        if ($tipo === 'antes') {
            $conteudo = '<h3 style="color:#333;margin-top:0;">Lembrete de Fatura</h3>';
            $conteudo .= '<p>Olá, <strong>' . htmlspecialchars($fatura['nome_razao']) . '</strong>,</p>';
            $conteudo .= '<p>Identificamos que sua fatura <strong>' . htmlspecialchars($fatura['numero']) . '</strong> vence em <strong>' . date('d/m/Y', strtotime($fatura['data_vencimento'])) . '</strong>.</p>';
            $conteudo .= '<table style="width:100%;border-collapse:collapse;margin:15px 0;"><tr><td style="padding:8px 0;color:#666;">Descrição</td><td style="padding:8px 0;">' . htmlspecialchars($fatura['descricao']) . '</td></tr><tr><td style="padding:8px 0;color:#666;border-top:1px solid #eee;">Valor</td><td style="padding:8px 0;border-top:1px solid #eee;font-weight:bold;">R$ ' . number_format($fatura['valor_final'], 2, ',', '.') . '</td></tr></table>';
            $conteudo .= '<p>Acesse sua fatura para mais detalhes e realizar o pagamento:</p>';
            $conteudo .= '<div style="text-align:center;margin:25px 0;"><a href="' . htmlspecialchars($linkFatura) . '" style="display:inline-block;background:' . $corPrimaria . ';color:#fff;padding:12px 30px;border-radius:6px;text-decoration:none;font-weight:bold;">Ver Fatura</a></div>';
            if ($linkPag) {
                $conteudo .= '<p style="text-align:center;"><a href="' . htmlspecialchars($linkPag) . '" style="color:' . $corPrimaria . ';">Pagar agora via Mercado Pago</a></p>';
            }
            $conteudo .= '<p style="color:#999;font-size:12px;margin-top:30px;">Atenciosamente,<br>' . htmlspecialchars($nomeSistema) . '</p>';
        } else {
            $diasAtraso = $dias > 0 ? $dias : ((strtotime(date('Y-m-d')) - strtotime($fatura['data_vencimento'])) / 86400);
            $conteudo = '<h3 style="color:#c0392b;margin-top:0;">Fatura Vencida</h3>';
            $conteudo .= '<p>Olá, <strong>' . htmlspecialchars($fatura['nome_razao']) . '</strong>,</p>';
            $conteudo .= '<p>Sua fatura <strong>' . htmlspecialchars($fatura['numero']) . '</strong> encontra-se vencida há <strong>' . intval($diasAtraso) . ' dia(s)</strong>.</p>';
            $conteudo .= '<table style="width:100%;border-collapse:collapse;margin:15px 0;"><tr><td style="padding:8px 0;color:#666;">Descrição</td><td style="padding:8px 0;">' . htmlspecialchars($fatura['descricao']) . '</td></tr><tr><td style="padding:8px 0;color:#666;border-top:1px solid #eee;">Valor</td><td style="padding:8px 0;border-top:1px solid #eee;font-weight:bold;">R$ ' . number_format($fatura['valor_final'], 2, ',', '.') . '</td></tr><tr><td style="padding:8px 0;color:#666;border-top:1px solid #eee;">Vencimento</td><td style="padding:8px 0;border-top:1px solid #eee;">' . date('d/m/Y', strtotime($fatura['data_vencimento'])) . '</td></tr></table>';
            $conteudo .= '<p>Por favor, regularize sua situação o mais rápido possível:</p>';
            $conteudo .= '<div style="text-align:center;margin:25px 0;"><a href="' . htmlspecialchars($linkFatura) . '" style="display:inline-block;background:#c0392b;color:#fff;padding:12px 30px;border-radius:6px;text-decoration:none;font-weight:bold;">Ver Fatura</a></div>';
            if ($linkPag) {
                $conteudo .= '<p style="text-align:center;"><a href="' . htmlspecialchars($linkPag) . '" style="color:' . $corPrimaria . ';">Pagar agora via Mercado Pago</a></p>';
            }
            $conteudo .= '<p style="color:#999;font-size:12px;margin-top:30px;">Atenciosamente,<br>' . htmlspecialchars($nomeSistema) . '</p>';
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
            $msg .= "Acesse sua fatura: {$linkFatura}\n\n";
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
            $msg .= "Acesse sua fatura: {$linkFatura}\n\n";
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
        $logoTag = '<h2 style="margin:0 0 20px 0;color:#fff;">' . htmlspecialchars($nomeSistema) . '</h2>';

        $templateHtml = getConfig('template_email_corpo_pagamento', '');

        if (!empty($templateHtml)) {
            $mensagemHtml = $templateHtml;
        } else {
            $corPrimaria = getCorPrimaria();
            $mensagemHtml = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;"><div style="max-width:600px;margin:30px auto;background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);"><div style="background:' . $corPrimaria . ';padding:24px 30px;text-align:center;">' . $logoTag . '</div><div style="padding:30px;">{{CONTEUDO}}</div></div></body></html>';
        }

        $dataPagamento = !empty($fatura['data_pagamento']) ? date('d/m/Y', strtotime($fatura['data_pagamento'])) : date('d/m/Y');

        $conteudo = '<h3 style="color:#27ae60;margin-top:0;">Pagamento Confirmado!</h3>';
        $conteudo .= '<p>Olá, <strong>' . htmlspecialchars($fatura['nome_razao']) . '</strong>,</p>';
        $conteudo .= '<p>Confirmamos o recebimento do pagamento da sua fatura <strong>' . htmlspecialchars($fatura['numero']) . '</strong>.</p>';
        $conteudo .= '<table style="width:100%;border-collapse:collapse;margin:15px 0;">';
        $conteudo .= '<tr><td style="padding:8px 0;color:#666;">Descrição</td><td style="padding:8px 0;">' . htmlspecialchars($fatura['descricao']) . '</td></tr>';
        $conteudo .= '<tr><td style="padding:8px 0;color:#666;border-top:1px solid #eee;">Valor Pago</td><td style="padding:8px 0;border-top:1px solid #eee;font-weight:bold;color:#27ae60;">R$ ' . number_format($fatura['valor_final'], 2, ',', '.') . '</td></tr>';
        $conteudo .= '<tr><td style="padding:8px 0;color:#666;border-top:1px solid #eee;">Data do Pagamento</td><td style="padding:8px 0;border-top:1px solid #eee;">' . $dataPagamento . '</td></tr>';
        $conteudo .= '</table>';
        $conteudo .= '<p>Sua fatura está quitada. Acesse sua conta para acompanhar suas faturas:</p>';
        $conteudo .= '<div style="text-align:center;margin:25px 0;"><a href="' . htmlspecialchars($linkFatura) . '" style="display:inline-block;background:#27ae60;color:#fff;padding:12px 30px;border-radius:6px;text-decoration:none;font-weight:bold;">Acessar Painel</a></div>';
        $conteudo .= '<p style="color:#999;font-size:12px;margin-top:30px;">Atenciosamente,<br>' . htmlspecialchars($nomeSistema) . '</p>';

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
