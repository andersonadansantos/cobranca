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

// Assinatura padrão no rodapé de todos os e-mails de cobrança
// (vale para todo admin e para qualquer template de e-mail).
if (!defined('ASSINATURA_CENTRAL_HTML')) {
    define('ASSINATURA_CENTRAL_HTML', '<div style="margin-top:16px;padding-top:12px;border-top:1px solid #eef0f3;text-align:center;font-size:10px;font-family:\'Inter\',Arial,sans-serif;color:#9ca3af;line-height:1.5;">Sistema de cobrança CENTRAL DE FATURAS.<br>Acesse: <a href="https://www.centraldefaturas.com.br" style="color:#9ca3af;text-decoration:none;">www.centraldefaturas.com.br</a></div>');
    define('ASSINATURA_CENTRAL_TXT', "\n\n--------------------\nSistema de cobrança CENTRAL DE FATURAS.\nAcesse: www.centraldefaturas.com.br");
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

if (!function_exists('getLogoEmailSrc')) {
    // Retorna a URL ABSOLUTA da "Logo da Empresa" do admin para uso em e-mails
    // (a imagem precisa ser acessível pelo cliente de e-mail). Cai para a marca
    // global se o admin não tiver logo própria. Retorna '' se não houver logo.
    function getLogoEmailSrc($adminId = null) {
        $adminId = (int) $adminId;
        if ($adminId <= 0) $adminId = getConfigAdminId();

        $logo = getLogoEmpresaFatura($adminId > 0 ? $adminId : 0);
        if (empty($logo)) return '';

        if (strpos($logo, 'http') === 0 || strpos($logo, 'data:') === 0) {
            return $logo;
        }
        $protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === '' && function_exists('getConfigGlobal')) {
            $host = trim((string)getConfigGlobal('base_domain', ''), " \t\n\r/");
        }
        if ($host === '') $host = 'centraldefaturas.com.br';
        return "{$protocolo}://{$host}{$logo}";
    }
}

if (!function_exists('getLogoImgTagEmail')) {
    // Apenas o <img> da logo (sem o wrapper <div>), usado para substituir a logo
    // antiga embutida em templates já salvos. Retorna '' se não houver logo.
    function getLogoImgTagEmail($adminId = null) {
        $src = getLogoEmailSrc($adminId);
        if ($src === '') return '';
        $nome = getNomeSistema();
        return '<img src="' . htmlspecialchars($src) . '" alt="' . htmlspecialchars($nome) . '" style="max-width:250px;max-height:120px;width:auto;height:auto;display:block;margin:0 auto;border:0;outline:none;text-decoration:none;">';
    }
}

if (!function_exists('getLogoTagEmail')) {
    function getLogoTagEmail($adminId = null) {
        $adminId = (int) $adminId;
        if ($adminId <= 0) $adminId = getConfigAdminId();
        $pdo = getConnection();
        $nome = getNomeSistema();
        $cnpj = '';
        if ($pdo && $adminId > 0) {
            $stmt = $pdo->prepare("SELECT nome_fantasia, nome, cnpj FROM administradores WHERE id = ?");
            $stmt->execute([$adminId]);
            $admin = $stmt->fetch();
            if ($admin) {
                $nome = $admin['nome_fantasia'] ?: ($admin['nome'] ?: $nome);
                $cnpj = $admin['cnpj'] ?? '';
            }
        }

        // Logo da empresa do admin (cai para a marca global se não houver).
        $img = getLogoImgTagEmail($adminId > 0 ? $adminId : 0);
        if ($img !== '') {
            return '<div style="text-align:center;margin-bottom:14px;">' . $img . '</div>';
        }

        $html = '<h2 style="margin:0 0 5px 0;color:#333;font-weight:bold;font-size:22px;">' . htmlspecialchars($nome) . '</h2>';
        if ($cnpj) {
            $html .= '<p style="margin:0;color:#666;font-size:13px;">CNPJ: ' . htmlspecialchars($cnpj) . '</p>';
        }
        return $html;
    }
}

if (!function_exists('aplicarLogoEmail')) {
    // Garante que a "Logo da Empresa" do admin apareça no cabeçalho do e-mail
    // mesmo quando o template salvo tem uma logo antiga embutida (URL fixa) ou
    // nenhuma imagem. Ordem: {{LOGO}} -> primeira imagem de logo -> inserir.
    function aplicarLogoEmail($html, $adminId = null) {
        $img = getLogoImgTagEmail($adminId);
        if ($img === '') return $html;

        if (strpos($html, '{{LOGO}}') !== false) {
            return str_replace('{{LOGO}}', $img, $html);
        }

        $padrao = '#<img\b[^>]*(?:class=["\']email-logo["\']|src=["\'][^"\']*(?:/assets/img/logo|/assets/uploads/)[^"\']*["\'])[^>]*>#i';
        $novo = preg_replace($padrao, $img, $html, 1, $count);
        if ($count > 0) return $novo;

        // Nenhuma imagem de logo reconhecida: se não houver NENHUMA imagem,
        // insere o bloco no topo do <body>.
        if (stripos($html, '<img') === false) {
            $ins = preg_replace('#(<body[^>]*>)#i', '$1<div style="text-align:center;padding:24px 24px 0 24px;">' . $img . '</div>', $html, 1, $c2);
            if ($c2 > 0) return $ins;
            return '<div style="text-align:center;padding:24px 24px 0 24px;">' . $img . '</div>' . $html;
        }
        return $html;
    }
}

if (!function_exists('getLinkFatura')) {
    function getLinkFatura($faturaId) {
        // Link sempre no domínio público configurado (base_domain) e no subdomínio
        // do administrador dono da fatura. Só cai para o host da requisição quando
        // não há domínio base: o CRON pode rodar sem host público (ex.: localhost).
        $token = '';
        $adminId = 0;
        $pdo = getConnection();
        if ($pdo) {
            $stmt = $pdo->prepare("SELECT acesso_token, admin_id FROM faturas WHERE id = ?");
            $stmt->execute([$faturaId]);
            $row = $stmt->fetch();
            if ($row !== false) {
                $adminId = (int)($row['admin_id'] ?? 0);
                $token = $row['acesso_token'];
                if (empty($token)) {
                    $token = bin2hex(random_bytes(32));
                    $upd = $pdo->prepare("UPDATE faturas SET acesso_token = ? WHERE id = ?");
                    $upd->execute([$token, $faturaId]);
                }
            }
        }
        if (!function_exists('getBaseDomain')) {
            require_once __DIR__ . '/tenant.php';
        }
        $baseDomain = strtolower(trim(ltrim((string)getBaseDomain(), '.')));
        if ($baseDomain !== '') {
            $sub = '';
            if ($adminId > 0 && $pdo) {
                $stSub = $pdo->prepare("SELECT subdominio FROM administradores WHERE id = ?");
                $stSub->execute([$adminId]);
                $sub = trim((string)$stSub->fetchColumn());
            }
            $base = 'https://' . ($sub !== '' ? $sub . '.' : '') . $baseDomain;
        } else {
            $protocolo = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') ? 'https' : 'http';
            $base = $protocolo . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        }
        if ($token) {
            return $base . APP_BASE . "/usuario/fatura.php?id={$faturaId}&token={$token}";
        }
        return $base . APP_BASE . "/usuario/fatura.php?id={$faturaId}";
    }
}

if (!function_exists('montarMensagemHtml')) {
    function montarMensagemHtml($fatura, $tipo, $dias = 0) {
        $nomeSistema = getNomeSistema();
        $linkFatura = getLinkFatura($fatura['id']);
        $linkPag = $fatura['link_pagamento'] ?? '';
        $logoTag = getLogoTagEmail($fatura['admin_id'] ?? null);

        $isAntes = in_array($tipo, ['antes', 'geracao', 'lembrete1', 'lembrete2', 'vencimento'], true);
        $chaveTemplate = $isAntes ? 'template_email_corpo_antes' : 'template_email_corpo_depois';
        $templateHtml = getConfig($chaveTemplate, '');
        $corPrimaria = getCorPrimaria();

        if (!empty($templateHtml)) {
            $mensagemHtml = aplicarLogoEmail($templateHtml, $fatura['admin_id'] ?? null);
        } else {
            $mensagemHtml = '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"></head><body style="margin:0;padding:0;background:#f4f6f9;font-family:\'Inter\',Arial,sans-serif;"><div style="max-width:600px;margin:30px auto;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.06);"><div style="background:' . $corPrimaria . ';height:50px;"></div><div style="padding:30px 30px 0 30px;text-align:center;">' . $logoTag . '</div><div style="padding:20px 30px 30px 30px;">{{CONTEUDO}}</div></div></body></html>';
        }

        if ($isAntes) {
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
            $conteudo .= '<div style="background:#f8f9fa;border:1px solid #e8edf5;border-radius:12px;padding:20px;margin-bottom:24px;">';
            $conteudo .= '<h4 style="margin:0 0 12px 0;font-size:14px;font-weight:600;color:#333;text-align:center;">Dados de Acesso</h4>';
            $cpfCnpjFmt = $fatura['cpf_cnpj'] ?? '';
            $conteudo .= '<p style="margin:0 0 6px 0;font-size:13px;color:#666;">CPF/CNPJ: <strong style="color:#333;">' . htmlspecialchars($cpfCnpjFmt) . '</strong></p>';
            $conteudo .= '<p style="margin:0 0 6px 0;font-size:13px;color:#666;">Senha padrão: <strong style="color:#333;">' . htmlspecialchars($cpfCnpjFmt) . '</strong> (ou a senha que você cadastrou)</p>';
            $conteudo .= '<p style="margin:8px 0 0 0;font-size:12px;color:#999;text-align:center;">Esqueceu sua senha? Use a opção de recuperação de senha na área do cliente.</p>';
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
            $conteudo .= '<div style="background:#f8f9fa;border:1px solid #e8edf5;border-radius:12px;padding:20px;margin-bottom:24px;">';
            $conteudo .= '<h4 style="margin:0 0 12px 0;font-size:14px;font-weight:600;color:#333;text-align:center;">Dados de Acesso</h4>';
            $cpfCnpjFmt = $fatura['cpf_cnpj'] ?? '';
            $conteudo .= '<p style="margin:0 0 6px 0;font-size:13px;color:#666;">CPF/CNPJ: <strong style="color:#333;">' . htmlspecialchars($cpfCnpjFmt) . '</strong></p>';
            $conteudo .= '<p style="margin:0 0 6px 0;font-size:13px;color:#666;">Senha padrão: <strong style="color:#333;">' . htmlspecialchars($cpfCnpjFmt) . '</strong> (ou a senha que você cadastrou)</p>';
            $conteudo .= '<p style="margin:8px 0 0 0;font-size:12px;color:#999;text-align:center;">Esqueceu sua senha? Use a opção de recuperação de senha na área do cliente.</p>';
            $conteudo .= '</div>';
            if ($linkPag) {
                $conteudo .= '<div style="text-align:center;margin-bottom:24px;"><a href="' . htmlspecialchars($linkPag) . '" style="color:' . $corPrimaria . ';font-weight:600;text-decoration:none;">Pagar agora via Mercado Pago →</a></div>';
            }
            $conteudo .= '<p style="color:#999;font-size:12px;text-align:center;margin:0;">Atenciosamente,<br><strong>' . htmlspecialchars($nomeSistema) . '</strong></p>';
        }

        $mensagemHtml = str_replace('{{CONTEUDO}}', $conteudo . ASSINATURA_CENTRAL_HTML, $mensagemHtml);
        return $mensagemHtml;
    }
}

if (!function_exists('montarMensagemTxt')) {
    function montarMensagemTxt($fatura, $tipo, $dias = 0) {
        $nomeSistema = getNomeSistema();
        $linkFatura = getLinkFatura($fatura['id']);
        $linkPag = $fatura['link_pagamento'] ?? '';

        $isAntes = in_array($tipo, ['antes', 'geracao', 'lembrete1', 'lembrete2', 'vencimento'], true);
        if ($isAntes) {
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
            $msg .= "Senha padrão: {$cpfCnpjFmt} (ou a senha que você cadastrou)\n";
            $msg .= "(Esqueceu sua senha? Use a recuperação de senha na área do cliente.)\n\n";
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
            $msg .= "Senha padrão: {$cpfCnpjFmt} (ou a senha que você cadastrou)\n";
            $msg .= "(Esqueceu sua senha? Use a recuperação de senha na área do cliente.)\n\n";
            if ($linkPag) {
                $msg .= "Pagar agora: {$linkPag}\n\n";
            }
            $msg .= "Atenciosamente,\n{$nomeSistema}";
        }

        return $msg . ASSINATURA_CENTRAL_TXT;
    }
}

if (!function_exists('smtpLerLinha')) {
    function smtpLerLinha($connexion) {
        $r = @fgets($connexion, 1024);
        if ($r === false) {
            return '';
        }
        if (strlen($r) >= 4 && $r[3] === '-') {
            $code = substr($r, 0, 3);
            for ($i = 0; $i < 20; $i++) {
                $n = @fgets($connexion, 1024);
                if ($n === false) {
                    break;
                }
                $r .= $n;
                if (substr($n, 0, 3) === $code && strlen($n) >= 4 && $n[3] === ' ') {
                    break;
                }
            }
        }
        return $r;
    }
}

if (!function_exists('smtpConectar')) {
    function smtpConectar($host, $port, $ssl) {
        $proto = ($ssl === 'ssl') ? 'ssl://' : '';
        $errno = 0;
        $errstr = '';
        $connexion = @fsockopen($proto . $host, intval($port), $errno, $errstr, 15);
        if (!$connexion) {
            $dica = '';
            if (intval($port) == 465) {
                $dica = ' A porta 465 usa TLS implícito; se ela não responder, use 587 com tls.';
            }
            return [null, '', "Falha ao conectar em {$host}:" . intval($port) . " - {$errstr} (código {$errno}).{$dica}"];
        }
        stream_set_timeout($connexion, 15);
        smtpLerLinha($connexion);
        @fputs($connexion, "EHLO " . gethostname() . "\r\n");
        $ehlo = smtpLerLinha($connexion);

        if ($ssl === 'tls') {
            @fputs($connexion, "STARTTLS\r\n");
            $r = smtpLerLinha($connexion);
            if (substr($r, 0, 3) === '220') {
                @stream_socket_enable_crypto($connexion, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                @fputs($connexion, "EHLO " . gethostname() . "\r\n");
                $ehlo = smtpLerLinha($connexion);
            } else {
                @fclose($connexion);
                return [null, '', "O servidor recusou STARTTLS: " . trim($r)];
            }
        }
        return [$connexion, $ehlo, ''];
    }
}

if (!function_exists('smtpAutenticar')) {
    function smtpAutenticar($connexion, $ehlo, $user, $pass, &$erro = null) {
        $erro = '';
        $suportaPlain = (stripos($ehlo, 'PLAIN') !== false);
        $suportaLogin = (stripos($ehlo, 'LOGIN') !== false);

        if (!$suportaPlain && !$suportaLogin) {
            $erro = 'O servidor nao anuncia AUTH PLAIN nem AUTH LOGIN. Resposta EHLO: ' . trim($ehlo);
            return false;
        }
        if (trim((string) $user) === '' || trim((string) $pass) === '') {
            $erro = 'Usuario ou senha SMTP vazios.';
            return false;
        }

        $ultima = '';
        if ($suportaPlain) {
            @fputs($connexion, "AUTH PLAIN " . base64_encode("\0" . $user . "\0" . $pass) . "\r\n");
            $r = smtpLerLinha($connexion);
            if (trim($r) !== '') {
                $ultima = $r;
            }
            if (substr($r, 0, 3) === '235') {
                $erro = '';
                return true;
            }
        }
        if ($suportaLogin) {
            @fputs($connexion, "AUTH LOGIN\r\n");
            $r = smtpLerLinha($connexion);
            if (trim($r) !== '') {
                $ultima = $r;
            }
            if (substr($r, 0, 3) === '334') {
                @fputs($connexion, base64_encode($user) . "\r\n");
                $r = smtpLerLinha($connexion);
                if (trim($r) !== '') {
                    $ultima = $r;
                }
                if (substr($r, 0, 3) === '334') {
                    @fputs($connexion, base64_encode($pass) . "\r\n");
                    $r = smtpLerLinha($connexion);
                    if (trim($r) !== '') {
                        $ultima = $r;
                    }
                    if (substr($r, 0, 3) === '235') {
                        $erro = '';
                        return true;
                    }
                }
            }
        }

        $codigo = substr(trim($ultima), 0, 3);
        $resposta = trim($ultima);
        if ($codigo === '534') {
            $erro = 'SMTP exige senha de aplicativo (' . $codigo . '): ' . $resposta
                . ' A conta tem verificacao em 2 etapas; gere uma senha de aplicativo'
                . ' (Google: Conta > Seguranca > Verificacao em 2 etapas > Senhas de app) e grave em smtp_senha.';
        } elseif ($codigo === '535') {
            $erro = 'SMTP recusou as credenciais (' . $codigo . '): ' . $resposta
                . ' Verifique se smtp_usuario e o e-mail completo da conta e se smtp_senha esta correta.';
        } elseif ($codigo === '530') {
            $erro = 'SMTP exigiu autenticacao nao suportada (' . $codigo . '): ' . $resposta;
        } else {
            $erro = 'Falha na autenticacao SMTP (' . $codigo . '): ' . $resposta;
        }
        return false;
    }
}

if (!function_exists('testarConexaoSmtp')) {
    /**
     * Testa conexão, autenticação e ENTREGA de uma mensagem de teste.
     *
     * Diferente da versão antiga, só retorna sucesso quando o servidor confirma
     * o fim do DATA com 250. Antes qualquer resposta contendo "2" (e até uma
     * resposta de erro) era tratada como sucesso, o que escondia credenciais e
     * remetentes recusados pelo provedor.
     *
     * @return array{sucesso: bool, mensagem: string}
     */
    function testarConexaoSmtp($host, $port, $user, $pass, $fromEmail, $fromNome, $ssl, $testEmail) {
        $conexao = null;
        $ehlo = '';
        $erro = '';
        list($conexao, $ehlo, $erro) = smtpConectar($host, $port, $ssl);
        if (!$conexao) {
            return ['sucesso' => false, 'mensagem' => $erro];
        }

        $erroAuth = '';
        if (!smtpAutenticar($conexao, $ehlo, $user, $pass, $erroAuth)) {
            @fclose($conexao);
            return ['sucesso' => false, 'mensagem' => $erroAuth];
        }

        // Fecha a conexão sempre pelo mesmo caminho, inclusive nas falhas.
        $falhar = function ($mensagem) use (&$conexao) {
            if ($conexao) {
                @fputs($conexao, "QUIT\r\n");
                @fclose($conexao);
                $conexao = null;
            }
            return ['sucesso' => false, 'mensagem' => $mensagem];
        };

        @fputs($conexao, "MAIL FROM:<{$fromEmail}>\r\n");
        $resposta = smtpLerLinha($conexao);
        if (substr($resposta, 0, 3) !== '250') {
            return $falhar("O servidor recusou o remetente {$fromEmail}: " . trim($resposta));
        }

        @fputs($conexao, "RCPT TO:<{$testEmail}>\r\n");
        $resposta = smtpLerLinha($conexao);
        if (substr($resposta, 0, 3) !== '250') {
            return $falhar("O servidor recusou o destinatário {$testEmail}: " . trim($resposta));
        }

        @fputs($conexao, "DATA\r\n");
        $resposta = smtpLerLinha($conexao);
        if (substr($resposta, 0, 3) !== '354') {
            return $falhar("O servidor recusou iniciar a mensagem: " . trim($resposta));
        }

        $nomeSistema = function_exists('getNomeSistema') ? getNomeSistema() : 'Sistema de Cobrança';
        $boundary = md5(uniqid(time()));
        $msgDate = date('r');
        $body  = "From: =?UTF-8?B?" . base64_encode($fromNome) . "?= <{$fromEmail}>\r\n";
        $body .= "To: <{$testEmail}>\r\n";
        $body .= "Date: {$msgDate}\r\n";
        $body .= "Subject: =?UTF-8?B?" . base64_encode("Teste SMTP - " . $nomeSistema) . "?=\r\n";
        $body .= "MIME-Version: 1.0\r\n";
        $body .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
        $body .= "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= "Este e um e-mail de teste do Sistema de Cobranca.\r\nSe voce recebeu esta mensagem, a configuracao SMTP esta funcionando corretamente.\r\n\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:30px;background:#f4f6f9;font-family:Arial,sans-serif;"><div style="max-width:500px;margin:0 auto;background:#fff;border-radius:8px;padding:30px;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,0.08);"><h2 style="color:#198754;">Teste SMTP</h2><p style="color:#555;">Este e um e-mail de teste do <strong>Sistema de Cobranca</strong>.</p><p style="color:#555;">Se voce recebeu esta mensagem, a configuracao SMTP esta funcionando corretamente.</p><hr style="border:none;border-top:1px solid #eee;margin:20px 0;"><small style="color:#999;">Enviado em ' . date('d/m/Y H:i:s') . '</small></div></body></html>';
        $body .= "\r\n\r\n";
        $body .= "--{$boundary}--\r\n";
        $body .= ".\r\n";

        @fputs($conexao, $body);
        $resposta = smtpLerLinha($conexao);
        $codigo = substr(trim($resposta), 0, 3);

        @fputs($conexao, "QUIT\r\n");
        @fclose($conexao);
        $conexao = null;

        if ($codigo === '250') {
            return [
                'sucesso' => true,
                'mensagem' => "E-mail de teste enviado com sucesso para {$testEmail}! Verifique a caixa de entrada e o spam."
            ];
        }

        return [
            'sucesso' => false,
            'mensagem' => "O servidor SMTP nao confirmou o envio (resposta: " . trim($resposta) . ")."
        ];
    }
}

if (!function_exists('enviarEmail')) {
    function enviarEmail($host, $port, $user, $pass, $fromEmail, $fromNome, $ssl, $paraEmail, $paraNome, $assunto, $mensagemHtml, $mensagemTxt) {
        $connexion = null;
        $ehloResponse = '';
        $erro = '';
        list($connexion, $ehloResponse, $erro) = smtpConectar($host, $port, $ssl);
        if (!$connexion) {
            error_log('[SMTP] ' . $erro);
            return false;
        }

        $erroAuth = '';
        if (!smtpAutenticar($connexion, $ehloResponse, $user, $pass, $erroAuth)) {
            error_log('[SMTP] ' . $erroAuth);
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

        // Só é envio concluído se o servidor aceitou a mensagem (250 após o DATA).
        return substr((string)$r, 0, 3) === '250';
    }
}

if (!function_exists('enviarEmailComAnexo')) {
    function enviarEmailComAnexo($host, $port, $user, $pass, $fromEmail, $fromNome, $ssl, $paraEmail, $paraNome, $assunto, $mensagemHtml, $mensagemTxt, $anexoCaminho = '', $anexoNome = '') {
        $anexoCaminho = trim((string) $anexoCaminho);
        if ($anexoCaminho === '' || !is_file($anexoCaminho)) {
            return enviarEmail($host, $port, $user, $pass, $fromEmail, $fromNome, $ssl, $paraEmail, $paraNome, $assunto, $mensagemHtml, $mensagemTxt);
        }
        $connexion = null;
        $ehloResponse = '';
        $erro = '';
        list($connexion, $ehloResponse, $erro) = smtpConectar($host, $port, $ssl);
        if (!$connexion) {
            error_log('[SMTP] ' . $erro);
            return false;
        }

        $erroAuth = '';
        if (!smtpAutenticar($connexion, $ehloResponse, $user, $pass, $erroAuth)) {
            error_log('[SMTP] ' . $erroAuth);
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

        $boundary = "=?utf-8?B?" . base64_encode("b" . uniqid()) . "?=";
        $boundary = str_replace('=', '', $boundary);
        $boundaryMix = "mixed_" . $boundary;
        $anexoNome = $anexoNome !== '' ? $anexoNome : basename($anexoCaminho);
        $anexoNome = preg_replace('/["\r\n]/', '', $anexoNome);

        $body = "From: =?UTF-8?B?" . base64_encode($fromNome) . "?= <{$fromEmail}>\r\n";
        $body .= "Reply-To: {$fromEmail}\r\n";
        $body .= "Date: " . date('r') . "\r\n";
        $body .= "To: =?UTF-8?B?" . base64_encode($paraNome) . "?= <{$paraEmail}>\r\n";
        $body .= "Subject: =?UTF-8?B?" . base64_encode($assunto) . "?=\r\n";
        $body .= "MIME-Version: 1.0\r\n";
        $body .= "Content-Type: multipart/mixed; boundary=\"{$boundaryMix}\"\r\n";
        $body .= "\r\n";
        $body .= "--{$boundaryMix}\r\n";
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
        $body .= "--{$boundary}--\r\n";
        $body .= "\r\n";

        $pdfData = @file_get_contents($anexoCaminho);
        if ($pdfData !== false) {
            $body .= "--{$boundaryMix}\r\n";
            $body .= "Content-Type: application/pdf; name=\"{$anexoNome}\"\r\n";
            $body .= "Content-Disposition: attachment; filename=\"{$anexoNome}\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($pdfData));
            $body .= "\r\n";
        }
        $body .= "--{$boundaryMix}--\r\n.\r\n";

        @fputs($connexion, $body);
        $r = @fgets($connexion, 512);
        @fputs($connexion, "QUIT\r\n");
        @fclose($connexion);

        // Só é envio concluído se o servidor aceitou a mensagem (250 após o DATA).
        return substr((string)$r, 0, 3) === '250';
    }
}

if (!function_exists('enviarEmailFatura')) {
    function enviarEmailFatura($fatura, $tipo = 'antes') {
        $adminId = (int)($fatura['admin_id'] ?? 0);
        if (!planoPermiteEnvio('email', $adminId)) {
            return false;
        }

        // Fixa o contexto do admin DONO da fatura durante todo o envio. Assim o
        // SMTP, os templates, o remetente e a logo saem sempre com as
        // configurações desse admin, mesmo que a requisição tenha sessão de
        // outro admin (ex.: cron/teste com cookie) ou nenhuma sessão.
        $overrideAntesDefinido = array_key_exists('_config_admin_override', $GLOBALS);
        $overrideAntes = $GLOBALS['_config_admin_override'] ?? null;
        $tenantAntes = $_SESSION['tenant_admin_id'] ?? null;
        if ($adminId > 0) {
            if (function_exists('setConfigAdminOverride')) {
                setConfigAdminOverride($adminId);
            }
            if (function_exists('getTenantAdminId')) {
                $_SESSION['tenant_admin_id'] = $adminId;
            }
        }

        try {
            return enviarEmailFaturaComContexto($fatura, $tipo);
        } finally {
            if (function_exists('setConfigAdminOverride')) {
                if ($overrideAntesDefinido) {
                    setConfigAdminOverride($overrideAntes);
                } else {
                    limparConfigAdminOverride();
                }
            }
            if ($tenantAntes === null) {
                unset($_SESSION['tenant_admin_id']);
            } else {
                $_SESSION['tenant_admin_id'] = $tenantAntes;
            }
        }
    }
}

if (!function_exists('enviarEmailFaturaComContexto')) {
    function enviarEmailFaturaComContexto($fatura, $tipo = 'antes') {
        $smtpHost = getConfig('smtp_host', '');
        $smtpPort = getConfig('smtp_port', '587');
        $smtpUser = getConfig('smtp_usuario', '');
        $smtpPass = getConfig('smtp_senha', '');
        $smtpFrom = getConfig('smtp_from_email', '');
        $smtpNome = getConfig('smtp_from_nome', 'Sistema de Cobrança');
        $smtpSsl  = getConfig('smtp_ssl', 'tls');

        // Admin sem SMTP próprio (campos vazios, inclusive os semeados em
        // branco no cadastro) usa o SMTP global, igual aos e-mails de
        // pagamento. Antes a fatura simplesmente não saía nesse caso.
        if (empty($smtpHost) || empty($smtpUser) || empty($smtpFrom)) {
            if (function_exists('getConfigGlobal')) {
                $smtpHost = getConfigGlobal('smtp_host', '');
                $smtpPort = getConfigGlobal('smtp_port', '587');
                $smtpUser = getConfigGlobal('smtp_usuario', '');
                $smtpPass = getConfigGlobal('smtp_senha', '');
                $smtpFrom = getConfigGlobal('smtp_from_email', '');
                $smtpNome = getConfigGlobal('smtp_from_nome', 'Sistema de Cobrança');
                $smtpSsl  = getConfigGlobal('smtp_ssl', 'tls');
            }
        }

        if (empty($smtpHost) || empty($smtpUser) || empty($smtpFrom) || empty($fatura['email'])) {
            return false;
        }

        if (empty($fatura['pix_copia_cola']) && !empty($fatura['id']) && ($fatura['status'] ?? '') !== 'pago') {
            if (!function_exists('criarPagamento')) {
                require_once __DIR__ . '/mercadopago.php';
            }
            $resultado = criarPagamento($fatura['descricao'], $fatura['valor_final'], $fatura['email'], $fatura['nome_razao'], $fatura['data_vencimento'] ?? null);
            if (isset($resultado['sucesso']) && $resultado['sucesso']) {
                $fatura['pix_copia_cola'] = $resultado['qr_code_copia_cola'] ?? '';
                $fatura['pix_qrcode'] = $resultado['qr_code'] ?? '';
                $fatura['link_pagamento'] = $resultado['link_pagamento'] ?? '';
                $pdo = getConnection();
                $apiAtiva = $resultado['api'] ?? getApiAtiva();
                if ($apiAtiva === 'inter' || $apiAtiva === 'bb') {
                    $stmt = $pdo->prepare("UPDATE faturas SET pix_qrcode = ?, pix_copia_cola = ?, link_pagamento = ?, mp_payment_id = ?, inter_codigo_solicitacao = ?, api_pagamento = ? WHERE id = ?");
                    $stmt->execute([$fatura['pix_qrcode'], $fatura['pix_copia_cola'], $fatura['link_pagamento'], null, $resultado['payment_id'], $apiAtiva, $fatura['id']]);
                    sincronizarNumeroFaturaBanco($fatura['id'], $resultado['payment_id'] ?? '', $pdo);
                } else {
                    $stmt = $pdo->prepare("UPDATE faturas SET pix_qrcode = ?, pix_copia_cola = ?, link_pagamento = ?, mp_payment_id = ?, api_pagamento = ? WHERE id = ?");
                    $stmt->execute([$fatura['pix_qrcode'], $fatura['pix_copia_cola'], $fatura['link_pagamento'], $resultado['payment_id'], $apiAtiva, $fatura['id']]);
                }
            }
        }

        $isAntes = in_array($tipo, ['antes', 'geracao', 'lembrete1', 'lembrete2', 'vencimento'], true);
        $assunto = trim((string)getConfig($isAntes ? 'template_email_assunto_antes' : 'template_email_assunto_depois', ''));
        // Assunto vazio salvo pelo admin não pode gerar e-mail sem assunto.
        if ($assunto === '') {
            $assunto = 'Fatura ' . $fatura['numero'];
        }
        $assunto = str_replace(['{numero}', '{data_vencimento}', '{valor}'], [
            $fatura['numero'],
            date('d/m/Y', strtotime($fatura['data_vencimento'])),
            number_format($fatura['valor_final'], 2, ',', '.')
        ], $assunto);

        $msgHtml = montarMensagemHtml($fatura, $tipo);
        $msgTxt  = montarMensagemTxt($fatura, $tipo);

        $anexoPdf = '';
        if (!empty($fatura['pix_copia_cola']) && ($fatura['status'] ?? '') !== 'pago' && ($fatura['status'] ?? '') !== 'cancelado') {
            if (!function_exists('gerarPixPdfFatura')) {
                require_once __DIR__ . '/pix_pdf.php';
            }
            try {
                $anexoPdf = gerarPixPdfFatura($fatura);
            } catch (Throwable $e) {
                $anexoPdf = '';
            }
        }

        $ret = false;
        if ($anexoPdf && is_file($anexoPdf)) {
            $ret = enviarEmailComAnexo($smtpHost, $smtpPort, $smtpUser, $smtpPass, $smtpFrom, $smtpNome, $smtpSsl, $fatura['email'], $fatura['nome_razao'], $assunto, $msgHtml, $msgTxt, $anexoPdf, 'Fatura_' . $fatura['numero'] . '.pdf');
            @unlink($anexoPdf);
        } else {
            $ret = enviarEmail($smtpHost, $smtpPort, $smtpUser, $smtpPass, $smtpFrom, $smtpNome, $smtpSsl, $fatura['email'], $fatura['nome_razao'], $assunto, $msgHtml, $msgTxt);
        }

        if (!empty($fatura['email2'])) {
            if ($anexoPdf && is_file($anexoPdf)) {
                enviarEmailComAnexo($smtpHost, $smtpPort, $smtpUser, $smtpPass, $smtpFrom, $smtpNome, $smtpSsl, $fatura['email2'], $fatura['nome_razao'], $assunto, $msgHtml, $msgTxt, $anexoPdf, 'Fatura_' . $fatura['numero'] . '.pdf');
            } else {
                enviarEmail($smtpHost, $smtpPort, $smtpUser, $smtpPass, $smtpFrom, $smtpNome, $smtpSsl, $fatura['email2'], $fatura['nome_razao'], $assunto, $msgHtml, $msgTxt);
            }
        }

        registrarEnvioLog($fatura['admin_id'] ?? 0, $fatura['id'] ?? 0, 'email', $tipo, $ret ? 'enviado' : 'erro', $fatura['email'] ?? '',
            $ret ? '' : 'SMTP nao confirmou o envio');
        return $ret;
    }
}

if (!function_exists('montarMensagemPagamentoHtml')) {
    /**
     * Monta o template HTML profissional do e-mail de confirmação de pagamento enviado ao CLIENTE.
     * Atende aos requisitos: confirmação expressa de quitação, sem pendências, design responsivo e claro.
     */
    function montarMensagemPagamentoHtml($fatura, array $detalhesExtras = []) {
        $nomeSistema = getNomeSistema();
        $linkFatura = getLinkFatura($fatura['id']);
        $logoTag = getLogoTagEmail($fatura['admin_id'] ?? null);

        $valorOriginal = (float)($fatura['valor'] ?? $fatura['valor_final']);
        $valorPago = isset($detalhesExtras['valor_pago']) && (float)$detalhesExtras['valor_pago'] > 0
            ? (float)$detalhesExtras['valor_pago']
            : (float)($fatura['valor_final'] ?? $fatura['valor']);

        $tipoPgtoRaw = (string)($detalhesExtras['tipo_pagamento'] ?? ($fatura['api_pagamento'] ?? 'pix'));
        $mapForma = [
            'pix' => 'PIX',
            'pix_manual' => 'PIX Manual',
            'credit_card' => 'Cartão de Crédito',
            'cartao' => 'Cartão de Crédito',
            'ticket' => 'Boleto Bancário',
            'boleto' => 'Boleto Bancário',
            'bank_transfer' => 'Transferência Bancária'
        ];
        $formaPgto = $mapForma[strtolower($tipoPgtoRaw)] ?? ($tipoPgtoRaw !== '' ? ucfirst($tipoPgtoRaw) : 'PIX');

        $dataHoraConfirmacao = $detalhesExtras['data_confirmacao'] ?? date('d/m/Y \à\s H:i:s');
        $dataVenc = !empty($fatura['data_vencimento']) ? date('d/m/Y', strtotime($fatura['data_vencimento'])) : '-';
        $numero = (string)($fatura['numero'] ?? ('FAT-' . $fatura['id']));
        $clienteNome = (string)($fatura['nome_razao'] ?? 'Cliente');
        $descricao = (string)($fatura['descricao'] ?? 'Serviço Contratado');

        $html = '<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pagamento Confirmado</title>
<style>
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f1f5f9; margin: 0; padding: 24px; color: #1e293b; }
.card { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; }
.header { background: linear-gradient(135deg, #059669 0%, #10b981 100%); color: #ffffff; padding: 32px 28px; text-align: center; }
.header-icon { display: inline-flex; align-items: center; justify-content: center; width: 56px; height: 56px; background: rgba(255,255,255,0.2); border-radius: 50%; font-size: 28px; margin-bottom: 12px; }
.header h1 { margin: 0 0 6px 0; font-size: 24px; font-weight: 800; color: #ffffff; letter-spacing: -0.02em; }
.header p { margin: 0; font-size: 15px; color: #ecfdf5; font-weight: 500; }
.content { padding: 32px 28px; }
.notice-box { background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 10px; padding: 16px 20px; margin-bottom: 24px; color: #065f46; font-size: 14px; line-height: 1.5; }
.notice-box strong { color: #047857; }
.amount-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; text-align: center; margin-bottom: 24px; }
.amount-card .label { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin-bottom: 4px; }
.amount-card .val { font-size: 32px; font-weight: 800; color: #059669; }
.details-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
.details-table tr { border-bottom: 1px solid #f1f5f9; }
.details-table tr:last-child { border-bottom: none; }
.details-table td { padding: 11px 0; font-size: 14px; }
.details-table td.lbl { color: #64748b; font-weight: 500; width: 45%; }
.details-table td.vlr { color: #0f172a; font-weight: 600; text-align: right; width: 55%; }
.badge-pago { display: inline-block; background: #dcfce7; color: #166534; font-size: 12px; font-weight: 700; padding: 4px 12px; border-radius: 6px; border: 1px solid #bbf7d0; }
.btn-access { display: inline-block; background: #059669; color: #ffffff; text-decoration: none; font-size: 15px; font-weight: 700; padding: 14px 32px; border-radius: 8px; text-align: center; width: 85%; box-sizing: border-box; }
.footer { background: #f8fafc; padding: 20px 28px; font-size: 12px; color: #94a3b8; text-align: center; border-top: 1px solid #e2e8f0; }
</style>
</head>
<body>
<div class="card">
    <div class="header">
        <div class="header-icon">&#10003;</div>
        <h1>Pagamento Confirmado!</h1>
        <p>Sua fatura foi quitada com sucesso</p>
    </div>
    <div class="content">
        <p style="font-size:16px;margin:0 0 16px 0;color:#1e293b;">Olá, <strong>' . htmlspecialchars($clienteNome) . '</strong>!</p>
        
        <div class="notice-box">
            <strong>Tudo certo!</strong> Confirmamos que o seu pagamento foi processado e recebido com sucesso. <strong>Não existe mais nenhuma pendência financeira</strong> referente a esta fatura.
        </div>

        <div class="amount-card">
            <div class="label">Valor Pago</div>
            <div class="val">R$ ' . number_format($valorPago, 2, ',', '.') . '</div>
        </div>

        <table class="details-table">
            <tr>
                <td class="lbl">Número da Fatura</td>
                <td class="vlr">' . htmlspecialchars($numero) . '</td>
            </tr>
            <tr>
                <td class="lbl">Descrição do Serviço</td>
                <td class="vlr">' . htmlspecialchars($descricao) . '</td>
            </tr>
            <tr>
                <td class="lbl">Forma de Pagamento</td>
                <td class="vlr">' . htmlspecialchars($formaPgto) . '</td>
            </tr>
            <tr>
                <td class="lbl">Data e Hora da Confirmação</td>
                <td class="vlr">' . htmlspecialchars($dataHoraConfirmacao) . '</td>
            </tr>
            <tr>
                <td class="lbl">Status da Fatura</td>
                <td class="vlr"><span class="badge-pago">PAGA</span></td>
            </tr>
        </table>

        <div style="text-align:center;margin-top:20px;">
            <a href="' . htmlspecialchars($linkFatura) . '" class="btn-access">Visualizar Comprovante / Painel &rarr;</a>
        </div>
    </div>
    <div class="footer">
        ' . htmlspecialchars($nomeSistema) . ' &bull; Comprovante transacional gerado automaticamente.
    </div>
</div>
</body>
</html>';

        return $html;
    }
}

if (!function_exists('montarMensagemPagamentoTxt')) {
    function montarMensagemPagamentoTxt($fatura, array $detalhesExtras = []) {
        $nomeSistema = getNomeSistema();
        $linkFatura = getLinkFatura($fatura['id']);
        $valorPago = isset($detalhesExtras['valor_pago']) && (float)$detalhesExtras['valor_pago'] > 0
            ? (float)$detalhesExtras['valor_pago']
            : (float)($fatura['valor_final'] ?? $fatura['valor']);
        $dataHoraConfirmacao = $detalhesExtras['data_confirmacao'] ?? date('d/m/Y \à\s H:i:s');
        $numero = (string)($fatura['numero'] ?? ('FAT-' . $fatura['id']));
        $clienteNome = (string)($fatura['nome_razao'] ?? 'Cliente');
        $descricao = (string)($fatura['descricao'] ?? 'Serviço Contratado');

        $tipoPgtoRaw = (string)($detalhesExtras['tipo_pagamento'] ?? ($fatura['api_pagamento'] ?? 'pix'));
        $mapForma = [
            'pix' => 'PIX',
            'pix_manual' => 'PIX Manual',
            'credit_card' => 'Cartão de Crédito',
            'cartao' => 'Cartão de Crédito',
            'ticket' => 'Boleto Bancário',
            'boleto' => 'Boleto Bancário'
        ];
        $formaPgto = $mapForma[strtolower($tipoPgtoRaw)] ?? ($tipoPgtoRaw !== '' ? ucfirst($tipoPgtoRaw) : 'PIX');

        $msg = "PAGAMENTO CONFIRMADO – SUA FATURA ESTÁ PAGA!

";
        $msg .= "Olá, {$clienteNome}!

";
        $msg .= "Confirmamos que o seu pagamento foi recebido com sucesso. Não existe mais nenhuma pendência financeira referente a esta fatura.

";
        $msg .= "--- DETALHES DO PAGAMENTO ---
";
        $msg .= "Fatura: Nº {$numero}
";
        $msg .= "Descrição: {$descricao}
";
        $msg .= "Valor Pago: R$ " . number_format($valorPago, 2, ',', '.') . "
";
        $msg .= "Forma de Pagamento: {$formaPgto}
";
        $msg .= "Data e Horário: {$dataHoraConfirmacao}
";
        $msg .= "Status: PAGA

";
        $msg .= "Acesse seu comprovante e painel: {$linkFatura}

";
        $msg .= "Atenciosamente,
{$nomeSistema}";

        return $msg;
    }
}

if (!function_exists('enviarEmailPagamento')) {
    /**
     * Envia o e-mail de confirmação de pagamento para o CLIENTE.
     * Assunto obrigatório: PAGAMENTO CONFIRMADO – SUA FATURA ESTÁ PAGA!
     *
     * @param array $fatura
     * @param array $detalhesExtras
     * @return bool
     */
    function enviarEmailPagamento($fatura, array $detalhesExtras = []) {
        $paraEmail = trim((string)($fatura['email'] ?? ($fatura['cliente_email'] ?? '')));
        if (empty($paraEmail)) {
            return false;
        }

        $adminId = (int)($fatura['admin_id'] ?? 0);
        $tenantAnterior = $_SESSION['tenant_admin_id'] ?? null;
        $overrideAntesDefinido = array_key_exists('_config_admin_override', $GLOBALS);
        $overrideAntes = $GLOBALS['_config_admin_override'] ?? null;
        if ($adminId > 0) {
            $_SESSION['tenant_admin_id'] = $adminId;
            // Garante SMTP/remetente/templates do admin dono da fatura, mesmo
            // que a requisição tenha sessão de outro admin (webhook/cron/painel).
            if (function_exists('setConfigAdminOverride')) {
                setConfigAdminOverride($adminId);
            }
        }

        $ok = false;
        try {
            // Configurações SMTP: Admin com fallback para Global
            $smtpHost = getConfig('smtp_host', '');
            $smtpPort = getConfig('smtp_port', '587');
            $smtpUser = getConfig('smtp_usuario', '');
            $smtpPass = getConfig('smtp_senha', '');
            $smtpFrom = getConfig('smtp_from_email', '');
            $smtpNome = getConfig('smtp_from_nome', getNomeSistema());
            $smtpSsl  = getConfig('smtp_ssl', 'tls');

            if (empty($smtpHost) || empty($smtpUser) || empty($smtpFrom)) {
                $smtpHost = getConfigGlobal('smtp_host', '');
                $smtpPort = getConfigGlobal('smtp_port', '587');
                $smtpUser = getConfigGlobal('smtp_usuario', '');
                $smtpPass = getConfigGlobal('smtp_senha', '');
                $smtpFrom = getConfigGlobal('smtp_from_email', '');
                $smtpNome = getConfigGlobal('smtp_from_nome', getNomeSistema());
                $smtpSsl  = getConfigGlobal('smtp_ssl', 'tls');
            }

            // Assunto obrigatório do cliente
            $assunto = "PAGAMENTO CONFIRMADO – SUA FATURA ESTÁ PAGA!";

            $msgHtml = montarMensagemPagamentoHtml($fatura, $detalhesExtras);
            $msgTxt  = montarMensagemPagamentoTxt($fatura, $detalhesExtras);

            // Anexa o recibo em PDF se disponível
            $anexoPdf = '';
            $anexoNome = '';
            if (!function_exists('gerarReciboPdfFatura')) {
                @require_once __DIR__ . '/recibo_pdf.php';
            }
            if (function_exists('gerarReciboPdfFatura')) {
                try {
                    $anexoPdf = gerarReciboPdfFatura($fatura);
                    if ($anexoPdf && is_file($anexoPdf)) {
                        $anexoNome = 'Recibo_' . ($fatura['numero'] ?? $fatura['id']) . '.pdf';
                    } else {
                        $anexoPdf = '';
                    }
                } catch (Throwable $e) {
                    $anexoPdf = '';
                }
            }

            $nomeDestinatario = $fatura['nome_razao'] ?? ($fatura['cliente_nome'] ?? $paraEmail);

            if (!empty($smtpHost) && !empty($smtpUser) && !empty($smtpFrom)) {
                if ($anexoPdf) {
                    $ok = enviarEmailComAnexo($smtpHost, $smtpPort, $smtpUser, $smtpPass, $smtpFrom, $smtpNome, $smtpSsl, $paraEmail, $nomeDestinatario, $assunto, $msgHtml, $msgTxt, $anexoPdf, $anexoNome);
                    @unlink($anexoPdf);
                } else {
                    $ok = enviarEmail($smtpHost, $smtpPort, $smtpUser, $smtpPass, $smtpFrom, $smtpNome, $smtpSsl, $paraEmail, $nomeDestinatario, $assunto, $msgHtml, $msgTxt);
                }
            } else {
                // Simulação local sem SMTP
                $ok = true;
            }

            // Envio para e-mail secundário se existir
            $email2 = trim((string)($fatura['email2'] ?? ($fatura['cliente_email2'] ?? '')));
            if ($email2 !== '' && !empty($smtpHost) && !empty($smtpUser) && !empty($smtpFrom)) {
                @enviarEmail($smtpHost, $smtpPort, $smtpUser, $smtpPass, $smtpFrom, $smtpNome, $smtpSsl, $email2, $nomeDestinatario, $assunto, $msgHtml, $msgTxt);
            }
        } catch (Throwable $e) {
            error_log('[ENVIAR_EMAIL_CLIENTE_ERRO] ' . $e->getMessage());
            $ok = false;
        } finally {
            if ($tenantAnterior === null) {
                unset($_SESSION['tenant_admin_id']);
            } else {
                $_SESSION['tenant_admin_id'] = $tenantAnterior;
            }
            if (function_exists('setConfigAdminOverride')) {
                if ($overrideAntesDefinido) {
                    setConfigAdminOverride($overrideAntes);
                } else {
                    limparConfigAdminOverride();
                }
            }
        }

        // Registrar no log de envios
        registrarEnvioLog(
            $fatura['admin_id'] ?? 0,
            $fatura['id'] ?? 0,
            'email',
            'pagamento_cliente',
            $ok ? 'enviado' : 'erro',
            $paraEmail,
            "Assunto: {$assunto} | Fatura: " . ($fatura['numero'] ?? $fatura['id'])
        );

        return $ok;
    }
}

if (!function_exists('notificarPagamentoRecebidoAdmin')) {
    /**
     * Envia notificação por e-mail ao administrador responsável após a confirmação e baixa do pagamento.
     * Assunto obrigatório: PAGAMENTO RECEBIDO – FATURA Nº [NÚMERO]
     *
     * @param int $faturaId
     * @param array $detalhesExtras
     * @return bool
     */
    function notificarPagamentoRecebidoAdmin($faturaId, array $detalhesExtras = []) {
        $faturaId = (int)$faturaId;
        if ($faturaId <= 0) return false;

        $pdo = getConnection();
        if (!$pdo) return false;

        $stmt = $pdo->prepare("SELECT f.*, c.nome_razao AS cliente_nome, c.email AS cliente_email FROM faturas f LEFT JOIN clientes c ON c.id = f.cliente_id WHERE f.id = ?");
        $stmt->execute([$faturaId]);
        $f = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$f || ($f['status'] ?? '') !== 'pago' || empty($f['admin_id'])) {
            return false;
        }

        // Idempotência: se já foi notificado anteriormente com sucesso, evita duplicidade
        if (!empty($f['admin_notificado_pagamento_em'])) {
            return true;
        }

        // Reserva o envio de forma atômica
        $reserva = $pdo->prepare("UPDATE faturas SET admin_notificado_pagamento_em = NOW() WHERE id = ? AND admin_notificado_pagamento_em IS NULL");
        $reserva->execute([$faturaId]);
        if ($reserva->rowCount() === 0) {
            return true;
        }

        $adminId = (int)$f['admin_id'];
        $stmtAdm = $pdo->prepare("SELECT id, nome, email, email_comercial, nome_fantasia, razao_social FROM administradores WHERE id = ?");
        $stmtAdm->execute([$adminId]);
        $adm = $stmtAdm->fetch(PDO::FETCH_ASSOC);

        $paraEmail = trim((string)($adm['email'] ?? ''));
        if (empty($paraEmail)) {
            $paraEmail = trim((string)($adm['email_comercial'] ?? ''));
        }
        $paraNome = (string)(($adm['nome_fantasia'] ?? '') ?: ($adm['nome'] ?? 'Administrador'));

        if (empty($paraEmail)) {
            $pdo->prepare("UPDATE faturas SET admin_notificado_pagamento_em = NULL WHERE id = ?")->execute([$faturaId]);
            return false;
        }

        $tenantAnterior = $_SESSION['tenant_admin_id'] ?? null;
        $overrideAntesDefinido = array_key_exists('_config_admin_override', $GLOBALS);
        $overrideAntes = $GLOBALS['_config_admin_override'] ?? null;
        $_SESSION['tenant_admin_id'] = $adminId;
        if (function_exists('setConfigAdminOverride')) {
            setConfigAdminOverride($adminId);
        }

        $ok = false;
        $detalhesErro = '';

        try {
            $smtpHost = getConfig('smtp_host', '');
            $smtpPort = getConfig('smtp_port', '587');
            $smtpUser = getConfig('smtp_usuario', '');
            $smtpPass = getConfig('smtp_senha', '');
            $smtpFrom = getConfig('smtp_from_email', '');
            $smtpNome = getConfig('smtp_from_nome', getNomeSistema());
            $smtpSsl  = getConfig('smtp_ssl', 'tls');

            if (empty($smtpHost) || empty($smtpUser) || empty($smtpFrom)) {
                $smtpHost = getConfigGlobal('smtp_host', '');
                $smtpPort = getConfigGlobal('smtp_port', '587');
                $smtpUser = getConfigGlobal('smtp_usuario', '');
                $smtpPass = getConfigGlobal('smtp_senha', '');
                $smtpFrom = getConfigGlobal('smtp_from_email', '');
                $smtpNome = getConfigGlobal('smtp_from_nome', getNomeSistema());
                $smtpSsl  = getConfigGlobal('smtp_ssl', 'tls');
            }

            // Preparação dos dados da fatura e pagamento
            $numero = (string)($f['numero'] ?? ('FAT-' . $faturaId));
            $cliente = (string)($f['cliente_nome'] ?? 'Cliente não informado');
            $descricao = (string)($f['descricao'] ?? 'Serviço/Cobrança');
            $valorOriginal = (float)($f['valor'] ?? 0);
            $valorFinal = (float)($f['valor_final'] ?? $valorOriginal);
            $desconto = (float)($f['desconto'] ?? 0);
            $multa = (float)($f['multa'] ?? 0);
            $juros = (float)($f['juros'] ?? 0);
            $acrescimos = $multa + $juros;
            $valorPago = isset($detalhesExtras['valor_pago']) && (float)$detalhesExtras['valor_pago'] > 0
                ? (float)$detalhesExtras['valor_pago']
                : $valorFinal;

            $tipoPgtoRaw = (string)($detalhesExtras['tipo_pagamento'] ?? ($f['api_pagamento'] ?? ''));
            $mapForma = [
                'pix' => 'PIX',
                'pix_manual' => 'PIX Manual',
                'credit_card' => 'Cartão de Crédito',
                'cartao' => 'Cartão de Crédito',
                'ticket' => 'Boleto Bancário',
                'boleto' => 'Boleto Bancário',
                'bank_transfer' => 'Transferência Bancária'
            ];
            $formaPgto = $mapForma[strtolower($tipoPgtoRaw)] ?? ($tipoPgtoRaw !== '' ? ucfirst($tipoPgtoRaw) : 'PIX');

            $gatewayRaw = (string)($detalhesExtras['gateway'] ?? ($f['api_pagamento'] ?? ''));
            $mapGateway = [
                'mercadopago' => 'Mercado Pago',
                'inter' => 'Banco Inter',
                'asaas' => 'Asaas',
                'pagbank' => 'PagBank',
                'pix_manual' => 'PIX Manual'
            ];
            $gatewayNome = $mapGateway[strtolower($gatewayRaw)] ?? ($gatewayRaw !== '' ? ucfirst($gatewayRaw) : 'Gateway de Pagamento');

            $dataHoraConfirmacao = $detalhesExtras['data_confirmacao'] ?? date('d/m/Y \à\s H:i:s');
            $dataVenc = !empty($f['data_vencimento']) ? date('d/m/Y', strtotime($f['data_vencimento'])) : '-';

            $transacao = (string)($detalhesExtras['payment_id'] ?? (($f['mp_payment_id'] ?? '') ?: ($f['inter_codigo_solicitacao'] ?? '')));
            if ($transacao === '') {
                $transacao = 'FAT-' . $faturaId;
            }

            // Assunto obrigatório do administrador
            $assunto = "PAGAMENTO RECEBIDO – FATURA Nº " . $numero;

            // Linhas de desconto e acrescimo exibidas somente quando existem.
            $linhasAjusteHtml = '';
            if ($desconto > 0) {
                $linhasAjusteHtml .= '<tr><td class="label">Desconto</td><td class="value">- R$ ' . number_format($desconto, 2, ',', '.') . '</td></tr>';
            }
            if ($acrescimos > 0) {
                $rotuloAcrescimo = $multa > 0 && $juros > 0 ? 'Acréscimos (multa e juros)' : ($multa > 0 ? 'Multa' : 'Juros');
                $linhasAjusteHtml .= '<tr><td class="label">' . $rotuloAcrescimo . '</td><td class="value">+ R$ ' . number_format($acrescimos, 2, ',', '.') . '</td></tr>';
            }

            $linhasAjusteTxt = '';
            if ($desconto > 0) {
                $linhasAjusteTxt .= "Desconto: - R$ " . number_format($desconto, 2, ',', '.') . "\n";
            }
            if ($acrescimos > 0) {
                $rotuloAcrescimoTxt = $multa > 0 && $juros > 0 ? 'Acréscimos (multa e juros)' : ($multa > 0 ? 'Multa' : 'Juros');
                $linhasAjusteTxt .= $rotuloAcrescimoTxt . ": + R$ " . number_format($acrescimos, 2, ',', '.') . "\n";
            }

            // Template HTML moderno, responsivo e com identidade Fintech
            $html = '<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . htmlspecialchars($assunto) . '</title>
<style>
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f1f5f9; margin: 0; padding: 24px; color: #1e293b; }
.card { max-width: 620px; margin: 0 auto; background: #ffffff; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 18px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; }
.header { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #ffffff; padding: 28px 32px; text-align: left; }
.header-tag { display: inline-block; background: rgba(16, 185, 129, 0.2); color: #34d399; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; padding: 4px 10px; border-radius: 20px; margin-bottom: 10px; border: 1px solid rgba(52, 211, 153, 0.4); }
.header h1 { margin: 0 0 6px 0; font-size: 22px; font-weight: 700; color: #ffffff; }
.header p { margin: 0; font-size: 14px; color: #94a3b8; }
.content { padding: 32px; }
.amount-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px 24px; margin-bottom: 24px; text-align: center; }
.amount-label { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin-bottom: 4px; }
.amount-value { font-size: 28px; font-weight: 800; color: #059669; }
.table-details { width: 100%; border-collapse: collapse; margin-top: 10px; }
.table-details tr { border-bottom: 1px solid #f1f5f9; }
.table-details tr:last-child { border-bottom: none; }
.table-details td { padding: 12px 0; font-size: 14px; vertical-align: top; }
.table-details td.label { color: #64748b; font-weight: 500; width: 45%; }
.table-details td.value { color: #0f172a; font-weight: 600; text-align: right; width: 55%; }
.badge-paga { display: inline-block; background: #dcfce7; color: #166534; font-size: 12px; font-weight: 700; padding: 3px 10px; border-radius: 6px; border: 1px solid #bbf7d0; }
.footer { background: #f8fafc; padding: 20px 32px; font-size: 12px; color: #94a3b8; text-align: center; border-top: 1px solid #e2e8f0; }
</style>
</head>
<body>
<div class="card">
    <div class="header">
        <span class="header-tag">Notificação ao Administrador</span>
        <h1>Pagamento Recebido!</h1>
        <p>Olá, <strong>' . htmlspecialchars($paraNome) . '</strong>. Um novo pagamento foi confirmado e a baixa automática foi concluída com sucesso.</p>
    </div>
    <div class="content">
        <div class="amount-box">
            <div class="amount-label">Valor Efetivamente Recebido</div>
            <div class="amount-value">R$ ' . number_format($valorPago, 2, ',', '.') . '</div>
        </div>
        <table class="table-details">
            <tr>
                <td class="label">Nome Completo do Cliente</td>
                <td class="value">' . htmlspecialchars($cliente) . '</td>
            </tr>
            <tr>
                <td class="label">Número da Fatura</td>
                <td class="value">' . htmlspecialchars($numero) . '</td>
            </tr>
            <tr>
                <td class="label">Identificação da Cobrança</td>
                <td class="value">#' . $faturaId . '</td>
            </tr>
            <tr>
                <td class="label">Descrição do Serviço</td>
                <td class="value">' . htmlspecialchars($descricao) . '</td>
            </tr>
            <tr>
                <td class="label">Valor Original</td>
                <td class="value">R$ ' . number_format($valorOriginal, 2, ',', '.') . '</td>
            </tr>
            ' . $linhasAjusteHtml . '
            <tr>
                <td class="label">Valor Efetivamente Recebido</td>
                <td class="value" style="color:#059669;">R$ ' . number_format($valorPago, 2, ',', '.') . '</td>
            </tr>
            <tr>
                <td class="label">Forma de Pagamento</td>
                <td class="value">' . htmlspecialchars($formaPgto) . '</td>
            </tr>
            <tr>
                <td class="label">Gateway Utilizado</td>
                <td class="value">' . htmlspecialchars($gatewayNome) . '</td>
            </tr>
            <tr>
                <td class="label">Data de Vencimento</td>
                <td class="value">' . htmlspecialchars($dataVenc) . '</td>
            </tr>
            <tr>
                <td class="label">Data e Horário do Pagamento</td>
                <td class="value">' . htmlspecialchars($dataHoraConfirmacao) . '</td>
            </tr>
            <tr>
                <td class="label">Status Atualizado</td>
                <td class="value"><span class="badge-paga">PAGA</span></td>
            </tr>
            <tr>
                <td class="label">Identificação da Transação</td>
                <td class="value" style="word-break:break-all;font-family:monospace;font-size:13px;">' . htmlspecialchars($transacao) . '</td>
            </tr>
        </table>
    </div>
    <div class="footer">
        Notificação automática gerada em ' . date('d/m/Y \à\s H:i:s') . ' &bull; ' . htmlspecialchars(getNomeSistema()) . '
    </div>
</div>
</body>
</html>';

            // Versão em texto puro
            $txt = "PAGAMENTO RECEBIDO – FATURA Nº {$numero}

";
            $txt .= "Olá, {$paraNome}!
";
            $txt .= "Um pagamento foi recebido e a cobrança foi baixada com sucesso no sistema.

";
            $txt .= "--- DETALHES DA TRANSAÇÃO ---
";
            $txt .= "Cliente: {$cliente}
";
            $txt .= "Número da Fatura: {$numero}
";
            $txt .= "Identificação da Cobrança: #{$faturaId}
";
            $txt .= "Descrição: {$descricao}
";
            $txt .= "Valor Original: R$ " . number_format($valorOriginal, 2, ',', '.') . "
";
            $txt .= $linhasAjusteTxt;
            $txt .= "Valor Recebido: R$ " . number_format($valorPago, 2, ',', '.') . "
";
            $txt .= "Forma de Pagamento: {$formaPgto}
";
            $txt .= "Gateway: {$gatewayNome}
";
            $txt .= "Data de Vencimento: {$dataVenc}
";
            $txt .= "Data e Horário do Pagamento: {$dataHoraConfirmacao}
";
            $txt .= "Status Atualizado: PAGA
";
            $txt .= "Código da Transação: {$transacao}

";
            $txt .= "Atenciosamente,
" . getNomeSistema();

            if (!empty($smtpHost) && !empty($smtpUser) && !empty($smtpFrom)) {
                $ok = enviarEmail($smtpHost, $smtpPort, $smtpUser, $smtpPass, $smtpFrom, $smtpNome, $smtpSsl, $paraEmail, $paraNome, $assunto, $html, $txt);
                if (!$ok) {
                    $detalhesErro = 'Falha no envio SMTP';
                }
            } else {
                // Sem SMTP nao ha envio. Manter como sucesso marcaria a fatura
                // como notificada e impediria a nova tentativa quando o SMTP
                // fosse configurado depois.
                $ok = false;
                $detalhesErro = 'SMTP não configurado para este administrador';
            }
        } catch (Throwable $e) {
            error_log('[NOTIFICAR_ADMIN_PAGAMENTO] ' . $e->getMessage());
            $ok = false;
            $detalhesErro = $e->getMessage();
        } finally {
            if ($tenantAnterior === null) {
                unset($_SESSION['tenant_admin_id']);
            } else {
                $_SESSION['tenant_admin_id'] = $tenantAnterior;
            }
            if (function_exists('setConfigAdminOverride')) {
                if ($overrideAntesDefinido) {
                    setConfigAdminOverride($overrideAntes);
                } else {
                    limparConfigAdminOverride();
                }
            }
        }

        try {
            $stmtLog = $pdo->prepare("INSERT INTO envios_log (admin_id, fatura_id, canal, tipo, status, destino, mensagem, criado_em) VALUES (?, ?, 'email', 'pagamento_recebido_admin', ?, ?, ?, NOW())");
            $logMsg = "Assunto: {$assunto} | Transação: {$transacao}" . ($detalhesErro ? " ({$detalhesErro})" : '');
            $stmtLog->execute([
                $adminId,
                $faturaId,
                $ok ? 'enviado' : 'erro',
                $paraEmail,
                $logMsg
            ]);
        } catch (Throwable $eLog) {
            error_log('[ENVIOS_LOG_ERRO] ' . $eLog->getMessage());
        }

        if (!$ok) {
            $pdo->prepare("UPDATE faturas SET admin_notificado_pagamento_em = NULL WHERE id = ?")->execute([$faturaId]);
        }

        return $ok;
    }
}

if (!function_exists('confirmarPagamentoEBaixarFatura')) {
    /**
     * Função definitiva e centralizada para baixa automática imediata de faturas em qualquer gateway.
     * Funciona para qualquer status anterior da fatura ('pendente', 'atrasado', 'vencido', 'cancelado').
     * Realiza a baixa financeira, gera o recibo, envia e-mail ao CLIENTE e e-mail ao ADMINISTRADOR.
     *
     * @param int $faturaId
     * @param array $dados [gateway, payment_id, valor_pago, tipo_pagamento, status_gateway, status_detail, data_pagamento, dados_raw]
     * @return array
     */
    function confirmarPagamentoEBaixarFatura($faturaId, array $dados = []) {
        $faturaId = (int)$faturaId;
        if ($faturaId <= 0) {
            return ['sucesso' => false, 'erro' => 'ID de fatura inválido'];
        }

        $pdo = getConnection();
        if (!$pdo) {
            return ['sucesso' => false, 'erro' => 'Sem conexão com banco de dados'];
        }

        $stmt = $pdo->prepare("SELECT f.*, c.nome_razao, c.email as cliente_email, c.email2 as cliente_email2 FROM faturas f LEFT JOIN clientes c ON c.id = f.cliente_id WHERE f.id = ?");
        $stmt->execute([$faturaId]);
        $fatura = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fatura) {
            return ['sucesso' => false, 'erro' => 'Fatura não encontrada'];
        }

        $adminId = (int)($fatura['admin_id'] ?? 0);
        $jaEstavaPaga = (($fatura['status'] ?? '') === 'pago');

        // Garante que SMTP/templates/links sejam resolvidos no tenant dono da
        // fatura, mesmo quando o chamador nao preparou a sessao (callback, cron).
        $tenantAnterior = $_SESSION['tenant_admin_id'] ?? null;
        $overrideAntesDefinido = array_key_exists('_config_admin_override', $GLOBALS);
        $overrideAntes = $GLOBALS['_config_admin_override'] ?? null;
        if ($adminId > 0) {
            $_SESSION['tenant_admin_id'] = $adminId;
            if (function_exists('setConfigAdminOverride')) {
                setConfigAdminOverride($adminId);
            }
        }

        try {

        $gateway = trim((string)($dados['gateway'] ?? ($fatura['api_pagamento'] ?? '')));
        $paymentId = trim((string)($dados['payment_id'] ?? ($dados['mp_payment_id'] ?? ($fatura['mp_payment_id'] ?? ($fatura['inter_codigo_solicitacao'] ?? '')))));
        $statusGateway = trim((string)($dados['status_gateway'] ?? ($dados['mp_status'] ?? 'approved')));
        $statusDetail = trim((string)($dados['status_detail'] ?? ($dados['mp_status_detail'] ?? 'Pagamento confirmado')));
        $tipoPgto = trim((string)($dados['tipo_pagamento'] ?? ''));
        $valorPago = isset($dados['valor_pago']) && (float)$dados['valor_pago'] > 0
            ? (float)$dados['valor_pago']
            : (float)($fatura['valor_final'] ?? $fatura['valor']);
        $dataPagamento = !empty($dados['data_pagamento']) ? $dados['data_pagamento'] : date('Y-m-d');
        $dadosRaw = isset($dados['dados_raw']) ? (is_string($dados['dados_raw']) ? $dados['dados_raw'] : json_encode($dados['dados_raw'])) : null;

        $emailClienteEnviado = false;

        // Se a fatura ainda NÃO está com status 'pago' (seja pendente, atrasado, vencido ou outro):
        if (!$jaEstavaPaga) {
            $sqlUp = "UPDATE faturas SET status = 'pago', data_pagamento = ?";
            $paramsUp = [$dataPagamento];

            if ($gateway !== '' && (empty($fatura['api_pagamento']) || in_array($fatura['api_pagamento'], ['mercadopago', 'inter', 'asaas', 'pagbank'], true))) {
                $sqlUp .= ", api_pagamento = ?";
                $paramsUp[] = $gateway;
            }
            if ($paymentId !== '') {
                if ($gateway === 'mercadopago' || empty($fatura['mp_payment_id'])) {
                    $sqlUp .= ", mp_payment_id = ?";
                    $paramsUp[] = $paymentId;
                }
                if ($gateway === 'inter') {
                    $sqlUp .= ", inter_codigo_solicitacao = ?, inter_situacao = 'PAGO', inter_data_situacao = NOW()";
                    $paramsUp[] = $paymentId;
                }
            }
            $sqlUp .= " WHERE id = ? AND status != 'pago'";
            $paramsUp[] = $faturaId;

            $stmtUp = $pdo->prepare($sqlUp);
            $stmtUp->execute($paramsUp);
            if ($gateway === 'inter' && $paymentId !== '') {
                sincronizarNumeroFaturaBanco($faturaId, $paymentId, $pdo);
            }

            // Idempotencia financeira: se outra execucao (webhook duplicado ou
            // polling concorrente) baixou a fatura primeiro, nao repete log,
            // recibo nem e-mail ao cliente. O e-mail ao admin segue abaixo e
            // tem sua propria reserva atomica.
            if ($stmtUp->rowCount() > 0) {

            // Registro no log de pagamentos
            try {
                $stmtLog = $pdo->prepare("INSERT INTO pagamentos_log (admin_id, fatura_id, mp_payment_id, mp_status, mp_status_detail, valor_pago, tipo_pagamento, dados_raw, criado_em) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmtLog->execute([
                    $adminId,
                    $faturaId,
                    $paymentId,
                    $statusGateway,
                    $statusDetail,
                    $valorPago,
                    $tipoPgto !== '' ? $tipoPgto : ($gateway !== '' ? $gateway : 'pagamento'),
                    $dadosRaw
                ]);
            } catch (Throwable $eLog) {
                error_log('[PAGAMENTOS_LOG_ERRO] ' . $eLog->getMessage());
            }

            // Geração garantida do recibo financeiro
            try {
                if (file_exists(__DIR__ . '/recibo_pdf.php')) {
                    @require_once __DIR__ . '/recibo_pdf.php';
                }
                if (function_exists('garantirReciboFatura')) {
                    $faturaAtualizada = $fatura;
                    $faturaAtualizada['status'] = 'pago';
                    $faturaAtualizada['data_pagamento'] = $dataPagamento;
                    garantirReciboFatura($faturaAtualizada);
                }
            } catch (Throwable $eRecibo) {
                error_log('[RECIBO_GERACAO_ERRO] ' . $eRecibo->getMessage());
            }

            // 1. ENVIO AUTOMÁTICO DE E-MAIL AO CLIENTE
            try {
                if (function_exists('enviarEmailPagamento')) {
                    $faturaCli = $fatura;
                    $faturaCli['status'] = 'pago';
                    $faturaCli['data_pagamento'] = $dataPagamento;
                    if (!empty($fatura['cliente_email'])) {
                        $faturaCli['email'] = $fatura['cliente_email'];
                    }
                    $detalhesCliente = [
                        'valor_pago' => $valorPago,
                        'tipo_pagamento' => $tipoPgto,
                        'gateway' => $gateway,
                        'data_confirmacao' => date('d/m/Y \à\s H:i:s')
                    ];
                    $emailClienteEnviado = enviarEmailPagamento($faturaCli, $detalhesCliente);
                }
            } catch (Throwable $eCli) {
                error_log('[EMAIL_CLIENTE_ERRO] ' . $eCli->getMessage());
            }
            } else {
                $jaEstavaPaga = true;
            }
        }

        // 2. ENVIO AUTOMÁTICO DE E-MAIL AO ADMINISTRADOR
        $detalhesAdmin = [
            'gateway' => $gateway,
            'payment_id' => $paymentId,
            'valor_pago' => $valorPago,
            'tipo_pagamento' => $tipoPgto,
            'data_confirmacao' => date('d/m/Y \à\s H:i:s'),
            'data_pagamento' => $dataPagamento
        ];

        $emailAdminEnviado = notificarPagamentoRecebidoAdmin($faturaId, $detalhesAdmin);

        return [
            'sucesso' => true,
            'ja_estava_paga' => $jaEstavaPaga,
            'fatura_id' => $faturaId,
            'admin_id' => $adminId,
            'valor_pago' => $valorPago,
            'email_cliente_enviado' => $emailClienteEnviado,
            'email_admin_enviado' => $emailAdminEnviado
        ];
        } finally {
            if ($tenantAnterior === null) {
                unset($_SESSION['tenant_admin_id']);
            } else {
                $_SESSION['tenant_admin_id'] = $tenantAnterior;
            }
            if (function_exists('setConfigAdminOverride')) {
                if ($overrideAntesDefinido) {
                    setConfigAdminOverride($overrideAntes);
                } else {
                    limparConfigAdminOverride();
                }
            }
        }
    }
}


if (!function_exists('enviarEmailRecuperacaoSenha')) {
    function enviarEmailRecuperacaoSenha($paraEmail, $paraNome, $link, $contexto = 'usuario') {
        $smtpHost = getConfig('smtp_host', '');
        $smtpPort = getConfig('smtp_port', '587');
        $smtpUser = getConfig('smtp_usuario', '');
        $smtpPass = getConfig('smtp_senha', '');
        $smtpFrom = getConfig('smtp_from_email', '');
        $smtpNome = getConfig('smtp_from_nome', getNomeSistema());
        $smtpSsl  = getConfig('smtp_ssl', 'tls');

        if (empty($smtpHost) || empty($smtpUser) || empty($smtpFrom) || empty($paraEmail)) {
            return false;
        }

        $nomeSistema = getNomeSistema();
        $logoTag = getLogoTagEmail();
        $corPrimaria = getCorPrimaria();
        $assunto = "Recuperação de senha - " . $nomeSistema;

        $conteudo = '<div style="background:#ffffff;border:1px solid #e8edf5;border-radius:12px;padding:24px;margin-bottom:24px;">';
        $conteudo .= '<h2 style="margin:0 0 16px 0;font-size:22px;font-weight:700;color:#333;">Recuperação de Senha</h2>';
        $conteudo .= '<p style="margin:0 0 12px 0;font-size:15px;color:#333;line-height:1.6;">Olá, <strong>' . htmlspecialchars($paraNome) . '</strong>,</p>';
        $conteudo .= '<p style="margin:0 0 12px 0;font-size:15px;color:#333;line-height:1.6;">Recebemos uma solicitação para redefinir a senha do seu acesso ao <strong>' . htmlspecialchars($nomeSistema) . '</strong>.</p>';
        $conteudo .= '<p style="margin:0 0 12px 0;font-size:15px;color:#333;line-height:1.6;">Para continuar, clique no botão abaixo. O link é válido por <strong>1 hora</strong>.</p>';
        $conteudo .= '</div>';
        $conteudo .= '<div style="text-align:center;margin-bottom:24px;">';
        $conteudo .= '<a href="' . htmlspecialchars($link) . '" style="display:inline-block;background:' . $corPrimaria . ';color:#fff;padding:14px 40px;border-radius:8px;text-decoration:none;font-weight:600;font-size:15px;">Redefinir Senha →</a>';
        $conteudo .= '</div>';
        $conteudo .= '<p style="margin:0 0 12px 0;font-size:13px;color:#999;line-height:1.6;text-align:center;">Se o botão não funcionar, copie e cole o link abaixo no seu navegador:</p>';
        $conteudo .= '<p style="margin:0 0 24px 0;font-size:12px;color:#6b7280;line-height:1.6;word-break:break-all;text-align:center;">' . htmlspecialchars($link) . '</p>';
        $conteudo .= '<p style="color:#999;font-size:12px;text-align:center;margin:0;">Se você não solicitou essa redefinição, ignore este e-mail.<br><strong>' . htmlspecialchars($nomeSistema) . '</strong></p>';

        $mensagemHtml = '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"></head><body style="margin:0;padding:0;background:#f4f6f9;font-family:\'Inter\',Arial,sans-serif;"><div style="max-width:600px;margin:30px auto;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.06);"><div style="background:' . $corPrimaria . ';height:50px;"></div><div style="padding:30px 30px 0 30px;text-align:center;">' . $logoTag . '</div><div style="padding:20px 30px 30px 30px;">' . $conteudo . '</div></div></body></html>';

        $mensagemTxt = "Olá, {$paraNome}!\n\n";
        $mensagemTxt .= "Recebemos uma solicitação para redefinir a senha do seu acesso ao {$nomeSistema}.\n\n";
        $mensagemTxt .= "Para continuar, acesse o link abaixo (válido por 1 hora):\n";
        $mensagemTxt .= "{$link}\n\n";
        $mensagemTxt .= "Se você não solicitou essa redefinição, ignore este e-mail.\n\n";
        $mensagemTxt .= "Atenciosamente,\n{$nomeSistema}";

        return enviarEmail($smtpHost, $smtpPort, $smtpUser, $smtpPass, $smtpFrom, $smtpNome, $smtpSsl, $paraEmail, $paraNome, $assunto, $mensagemHtml, $mensagemTxt);
    }
}

// =====================================================
// E-MAIL DE BOAS-VINDAS PARA NOVO ADMIN (Super Admin)
// Usa SMTP GLOBAL (admin_id NULL) e o template global
// template_email_corpo_boasvindas com placeholders.
// =====================================================

if (!function_exists('montarMensagemBoasVindasHtml')) {
    function montarMensagemBoasVindasHtml($dados) {
        $dados = is_array($dados) ? $dados : [];
        $nomeSistema = getNomeSistema();
        $corPrimaria = getCorPrimaria();
        $logo = getLogoEmail();
        $logoHtml = '';
        if ($logo) {
            $logoHtml = '<div style="text-align:center;margin:0 0 14px;"><img src="' . htmlspecialchars($logo) . '" alt="' . htmlspecialchars($nomeSistema) . '" style="max-width:250px;max-height:120px;width:auto;height:auto;display:block;margin:0 auto;border:0;outline:none;text-decoration:none;"></div>';
        }

        $templateHtml = getConfigGlobal('template_email_corpo_boasvindas', '');
        if (empty($templateHtml)) {
            $templateHtml = '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:\'Inter\',Arial,sans-serif;">
  <div style="max-width:600px;margin:30px auto;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.06);">
    <div style="background:' . $corPrimaria . ';height:50px;"></div>
    <div style="padding:30px 30px 0 30px;text-align:center;">
      {{LOGO}}
    </div>
    <div style="padding:20px 30px 30px 30px;">
      {{CONTEUDO}}
    </div>
  </div>
</body>
</html>';
        }

        $conteudo = '<div style="background:#ffffff;border:1px solid #e8edf5;border-radius:12px;padding:24px;margin-bottom:24px;">';
        $conteudo .= '<h2 style="margin:0 0 16px 0;font-size:22px;font-weight:700;color:#1a1a2e;">Seja bem-vindo(a)!</h2>';
        $conteudo .= '<p style="margin:0 0 12px 0;font-size:15px;color:#333;line-height:1.6;">Olá, <strong>' . htmlspecialchars($dados['nome'] ?? '') . '</strong>,</p>';
        $conteudo .= '<p style="margin:0 0 12px 0;font-size:15px;color:#333;line-height:1.6;">Sua conta no <strong>' . htmlspecialchars($nomeSistema) . '</strong> foi criada com sucesso. Veja seus dados de acesso abaixo:</p>';
        $conteudo .= '</div>';
        $conteudo .= '<div style="background:#f8f9fa;border:1px solid #e8edf5;border-radius:12px;padding:20px;margin-bottom:24px;">';
        $conteudo .= '<h4 style="margin:0 0 12px 0;font-size:14px;font-weight:600;color:#333;text-align:center;">Dados de Acesso</h4>';
        $conteudo .= '<p style="margin:0 0 6px 0;font-size:13px;color:#666;">Usuário: <strong style="color:#333;">' . htmlspecialchars($dados['usuario'] ?? '') . '</strong></p>';
        $conteudo .= '<p style="margin:0 0 6px 0;font-size:13px;color:#666;">Senha: <strong style="color:#333;">' . htmlspecialchars($dados['senha'] ?? '') . '</strong></p>';
        if (!empty($dados['link_acesso'])) {
            $conteudo .= '<div style="text-align:center;margin-top:16px;"><a href="' . htmlspecialchars($dados['link_acesso']) . '" style="display:inline-block;background:' . $corPrimaria . ';color:#fff;padding:12px 32px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;">Acessar Painel →</a></div>';
        }
        $conteudo .= '<p style="margin:10px 0 0 0;font-size:12px;color:#999;text-align:center;">Por segurança, altere sua senha após o primeiro acesso.</p>';
        $conteudo .= '</div>';
        $conteudo .= '<p style="color:#999;font-size:12px;text-align:center;margin:0;">Atenciosamente,<br><strong>' . htmlspecialchars($nomeSistema) . '</strong></p>';

        $mensagemHtml = $templateHtml;
        if (strpos($mensagemHtml, '{{CONTEUDO}}') !== false) {
            $mensagemHtml = str_replace('{{CONTEUDO}}', $conteudo, $mensagemHtml);
        }

        $placeholders = [
            '{nome}'        => $dados['nome'] ?? '',
            '{usuario}'     => $dados['usuario'] ?? '',
            '{senha}'       => $dados['senha'] ?? '',
            '{email}'       => $dados['email'] ?? '',
            '{subdominio}'  => $dados['subdominio'] ?? '',
            '{link_acesso}' => $dados['link_acesso'] ?? '',
        ];
        $mensagemHtml = str_replace(array_keys($placeholders), array_values($placeholders), $mensagemHtml);

        if (strpos($mensagemHtml, '{{LOGO}}') !== false) {
            $mensagemHtml = str_replace('{{LOGO}}', $logoHtml, $mensagemHtml);
        } elseif ($logoHtml !== '' && stripos($mensagemHtml, '<img') === false) {
            $mensagemHtml = preg_replace('#(<body[^>]*>)#i', '$1<div style="text-align:center;padding:24px 24px 0 24px;">' . $logoHtml . '</div>', $mensagemHtml, 1);
        }

        return $mensagemHtml;
    }
}

if (!function_exists('montarMensagemBoasVindasTxt')) {
    function montarMensagemBoasVindasTxt($dados) {
        $dados = is_array($dados) ? $dados : [];
        $nomeSistema = getNomeSistema();
        $msg = "Olá, " . ($dados['nome'] ?? '') . "!\n\n";
        $msg .= "Sua conta no {$nomeSistema} foi criada com sucesso.\n\n";
        $msg .= "--- Dados de Acesso ---\n";
        $msg .= "Usuário: " . ($dados['usuario'] ?? '') . "\n";
        $msg .= "Senha: " . ($dados['senha'] ?? '') . "\n";
        if (!empty($dados['link_acesso'])) {
            $msg .= "Acesso ao painel: {$dados['link_acesso']}\n";
        }
        $msg .= "\n(Por segurança, altere sua senha após o primeiro acesso.)\n\n";
        $msg .= "Atenciosamente,\n{$nomeSistema}";
        return $msg;
    }
}

if (!function_exists('enviarEmailBoasVindasAdmin')) {
    function enviarEmailBoasVindasAdmin($dados) {
        $dados = is_array($dados) ? $dados : [];
        $paraEmail = trim($dados['email'] ?? '');
        if (empty($paraEmail)) return false;

        $smtpHost = getConfigGlobal('smtp_host', '');
        $smtpPort = getConfigGlobal('smtp_port', '587');
        $smtpUser = getConfigGlobal('smtp_usuario', '');
        $smtpPass = getConfigGlobal('smtp_senha', '');
        $smtpFrom = getConfigGlobal('smtp_from_email', '');
        $smtpNome = getConfigGlobal('smtp_from_nome', getNomeSistema());
        $smtpSsl  = getConfigGlobal('smtp_ssl', 'tls');

        if (empty($smtpHost) || empty($smtpUser) || empty($smtpFrom)) return false;

        if (empty($dados['link_acesso'])) {
            $protocolo = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') ? 'https' : 'http';
            $host = preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? ''));
            $sub = trim($dados['subdominio'] ?? '');
            if ($sub !== '') {
                $dados['link_acesso'] = $protocolo . '://' . $sub . '.' . $host . '/admin/';
            } else {
                $dados['link_acesso'] = $protocolo . '://' . $host . APP_BASE . '/admin/';
            }
        }

        $subject = getConfigGlobal('template_email_assunto_boasvindas', '');
        if (empty($subject)) $subject = 'Seja bem-vindo(a) ao ' . getNomeSistema() . '!';
        $subject = str_replace(['{nome}', '{usuario}', '{senha}', '{email}', '{subdominio}', '{link_acesso}'], [
            $dados['nome'] ?? '', $dados['usuario'] ?? '', $dados['senha'] ?? '', $dados['email'] ?? '', $dados['subdominio'] ?? '', $dados['link_acesso'] ?? ''
        ], $subject);

        $msgHtml = montarMensagemBoasVindasHtml($dados);
        $msgTxt  = montarMensagemBoasVindasTxt($dados);

        return enviarEmail($smtpHost, $smtpPort, $smtpUser, $smtpPass, $smtpFrom, $smtpNome, $smtpSsl, $paraEmail, $dados['nome'] ?? $paraEmail, $subject, $msgHtml, $msgTxt);
    }
}
