<?php
// =====================================================
// RECIBO AUTOMÁTICO + GERADOR DE PDF DE RECIBO
// Garante um recibo (linha em recibos) para faturas pagas
// e gera o comprovante em PDF (logo, dados do cliente,
// dados do emitente, valor por extenso e assinatura).
// =====================================================

require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/recibo_template.php';
require_once __DIR__ . '/valor_extenso.php';
require_once __DIR__ . '/fpdf/fpdf.php';

if (!function_exists('mascaraReciboCpfCnpj')) {
    function mascaraReciboCpfCnpj($valor) {
        $v = preg_replace('/[^0-9]/', '', (string) $valor);
        if (strlen($v) === 11) return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $v);
        if (strlen($v) === 14) return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $v);
        return $valor;
    }
}

if (!function_exists('formatarMoedaRecibo')) {
    function formatarMoedaRecibo($valor) {
        return 'R$ ' . number_format((float) $valor, 2, ',', '.');
    }
}

if (!function_exists('formatarDataRecibo')) {
    function formatarDataRecibo($data) {
        if (empty($data) || $data === '0000-00-00') return '';
        return date('d/m/Y', strtotime($data));
    }
}

// Busca a fatura com dados do cliente completos (padrão dos emails/webhooks).
if (!function_exists('buscarFaturaComCliente')) {
    function buscarFaturaComCliente($fatura) {
        $pdo = getConnection();
        if (!$pdo || empty($fatura['id'])) return $fatura;
        try {
            $stmt = $pdo->prepare("SELECT f.*, c.nome_razao, c.cpf_cnpj, c.email, c.email2, c.celular, c.telefone,
                c.cep, c.logradouro, c.numero AS cliente_numero, c.complemento, c.bairro, c.cidade, c.estado
                FROM faturas f JOIN clientes c ON f.cliente_id = c.id WHERE f.id = ?");
            $stmt->execute([(int)$fatura['id']]);
            $row = $stmt->fetch();
            if ($row) return array_merge($fatura, $row);
        } catch (Exception $e) {}
        return $fatura;
    }
}

// Garante que exista uma linha de recibo para a fatura (auto-gera se faltar).
// Retorna a linha do recibo (array) ou null. Chamado no envio de e-mail de
// pagamento e na página de visualização de recibo do painel do cliente.
if (!function_exists('garantirReciboFatura')) {
    function garantirReciboFatura($fatura) {
        $pdo = getConnection();
        if (!$pdo || empty($fatura['id'])) return null;
        $faturaId = (int)$fatura['id'];
        $adminId = (int)($fatura['admin_id'] ?? 0);
        if ($adminId <= 0) {
            try {
                $st = $pdo->prepare("SELECT admin_id FROM faturas WHERE id = ?");
                $st->execute([$faturaId]);
                $adminId = (int)$st->fetchColumn();
            } catch (Exception $e) {}
        }

        // Logo do recibo = logo da empresa responsável pela fatura (qualquer recibo).
        $empresaLogo = getLogoEmpresaFatura($adminId);
        if (!logoPathValido($empresaLogo)) {
            $empresaLogo = '/cobranca/assets/img/logo_color.png';
        }
        $logoAbs = $empresaLogo;
        if (strpos($empresaLogo, 'http') !== 0 && strpos($empresaLogo, 'data:') !== 0) {
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $logoAbs = $proto . '://' . $host . $empresaLogo;
        }
        $logoImgHtml = '<img src="' . htmlspecialchars($logoAbs) . '" style="max-width:250px;">';

        // Já existe recibo para esta fatura?
        $stmt = $pdo->prepare("SELECT * FROM recibos WHERE fatura_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$faturaId]);
        $recibo = $stmt->fetch();
        if ($recibo) {
            // Atualiza a logo em recibos já gravados (gerados com logo antiga/global).
            if (strpos($recibo['html_gerado'], $logoImgHtml) === false) {
                $novoHtml = preg_replace('#<img[^>]*(?:max-height:102px|max-width:250px)[^>]*>#i', $logoImgHtml, $recibo['html_gerado'], 1);
                if ($novoHtml !== null && $novoHtml !== $recibo['html_gerado']) {
                    try {
                        $upd = $pdo->prepare("UPDATE recibos SET html_gerado = ? WHERE id = ?");
                        $upd->execute([$novoHtml, $recibo['id']]);
                        $recibo['html_gerado'] = $novoHtml;
                    } catch (Exception $e) {}
                }
            }
            return $recibo;
        }
        if ($adminId <= 0) return null;

        $fat = buscarFaturaComCliente($fatura);

        // Admin (emitente)
        $admin = null;
        try {
            $st = $pdo->prepare("SELECT * FROM administradores WHERE id = ?");
            $st->execute([$adminId]);
            $admin = $st->fetch();
        } catch (Exception $e) {}
        if (!$admin) return null;

        $config = getAllConfigForAdmin($adminId);
        $templateHtml = getTemplateReciboHtml($adminId);
        $exibirAssinatura = ($config['template_recibo_assinatura'] ?? '1') === '1';

        $descPadrao = $config['template_recibo_descricao_padrao'] ?? 'referente à prestação de serviços conforme acordado entre as partes.';
        $cidadeEmissao = $config['template_recibo_cidade_emissao'] ?? ($admin['cidade'] ?? 'São Paulo');
        $dataEmissao = !empty($fat['data_pagamento']) ? $fat['data_pagamento'] : date('Y-m-d');

        // Número sequencial por admin
        $st = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(numero, 11) AS UNSIGNED)) FROM recibos WHERE admin_id = ?");
        $st->execute([$adminId]);
        $proximo = ((int)$st->fetchColumn()) + 1;
        $reciboNumero = 'REC-' . date('Y') . '-' . str_pad($proximo, 6, '0', STR_PAD_LEFT);

        $valorExt = '';
        if (function_exists('valorPorExtenso')) {
            try { $valorExt = valorPorExtenso((float)$fat['valor_final']); } catch (Exception $e) {}
        }

        $enderecoEmpresa = trim(($admin['logradouro'] ?? '') . ', ' . ($admin['numero'] ?? '') . ' ' . ($admin['complemento'] ?? ''));
        $enderecoCliente = trim(($fat['logradouro'] ?? '') . ', ' . ($fat['cliente_numero'] ?? '') . ' ' . ($fat['complemento'] ?? ''));

        $assinaturaBloco = '';
        if ($exibirAssinatura) {
            $assinaturaBloco = '<div style="text-align:center;margin-top:40px;">
                <div style="width:250px;border-bottom:1px solid #1a1a2e;margin:0 auto 6px auto;">&nbsp;</div>
                <div style="font-size:12px;font-weight:bold;">' . htmlspecialchars($admin['nome_fantasia'] ?: $admin['nome']) . '</div>
                <div style="font-size:11px;color:#555;">CNPJ: ' . htmlspecialchars($admin['cnpj'] ?? '') . '</div>
            </div>';
        }

        $vars = [
            '{{empresa_logo}}'    => $logoImgHtml,
            '{{empresa_nome}}'    => htmlspecialchars($admin['nome_fantasia'] ?: $admin['nome']),
            '{{empresa_cnpj}}'    => htmlspecialchars($admin['cnpj'] ?? ''),
            '{{empresa_inscricao_municipal}}' => htmlspecialchars($admin['inscricao_municipal'] ?? ''),
            '{{empresa_endereco}}' => htmlspecialchars($enderecoEmpresa),
            '{{empresa_cidade}}'  => htmlspecialchars($admin['cidade'] ?? ''),
            '{{empresa_estado}}'  => htmlspecialchars($admin['estado'] ?? ''),
            '{{empresa_cep}}'     => htmlspecialchars($admin['cep'] ?? ''),
            '{{empresa_telefone}}' => htmlspecialchars($admin['telefone_comercial'] ?? ''),
            '{{empresa_email}}'   => htmlspecialchars($admin['email_comercial'] ?? $admin['email']),
            '{{cliente_nome}}'    => htmlspecialchars($fat['nome_razao'] ?? ''),
            '{{cliente_cnpj_cpf}}' => htmlspecialchars(mascaraReciboCpfCnpj($fat['cpf_cnpj'] ?? '')),
            '{{cliente_endereco}}' => htmlspecialchars($enderecoCliente),
            '{{cliente_cidade}}'  => htmlspecialchars($fat['cidade'] ?? ''),
            '{{cliente_estado}}'  => htmlspecialchars($fat['estado'] ?? ''),
            '{{cliente_cep}}'     => htmlspecialchars($fat['cep'] ?? ''),
            '{{cliente_telefone}}' => htmlspecialchars($fat['telefone'] ?? $fat['celular'] ?? ''),
            '{{cliente_email}}'   => htmlspecialchars($fat['email'] ?? ''),
            '{{fatura_numero}}'   => htmlspecialchars($fat['numero'] ?? ''),
            '{{recibo_numero}}'   => htmlspecialchars($reciboNumero),
            '{{valor_recebido}}'  => formatarMoedaRecibo($fat['valor_final']),
            '{{valor_extenso}}'   => htmlspecialchars($valorExt),
            '{{descricao_servico}}' => htmlspecialchars($descPadrao),
            '{{data_emissao}}'    => formatarDataRecibo($dataEmissao),
            '{{cidade_emissao}}'  => htmlspecialchars($cidadeEmissao),
            '{{assinatura_bloco}}' => $assinaturaBloco,
        ];
        $htmlGerado = str_replace(array_keys($vars), array_values($vars), $templateHtml);

        $stmt = $pdo->prepare("INSERT INTO recibos (admin_id, cliente_id, fatura_id, numero, descricao_servico, cidade_emissao, data_emissao, valor_recebido, valor_extenso, html_gerado)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $ok = $stmt->execute([
            $adminId, $fat['cliente_id'] ?? 0, $faturaId,
            $reciboNumero, $descPadrao, $cidadeEmissao, $dataEmissao,
            $fat['valor_final'], $valorExt, $htmlGerado
        ]);
        if (!$ok) return null;

        $stmt = $pdo->prepare("SELECT * FROM recibos WHERE fatura_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$faturaId]);
        return $stmt->fetch();
    }
}

// Gera o PDF do recibo de uma fatura paga em um arquivo temporário.
// Retorna o caminho do arquivo (para anexo de e-mail) ou '' em erro.
if (!function_exists('gerarReciboPdfFatura')) {
    function gerarReciboPdfFatura($fatura) {
        $recibo = garantirReciboFatura($fatura);
        if (!$recibo) return '';
        $fat = buscarFaturaComCliente($fatura);
        $adminId = (int)($fat['admin_id'] ?? 0);
        $pdo = getConnection();

        $admin = null;
        if ($pdo && $adminId > 0) {
            try {
                $st = $pdo->prepare("SELECT * FROM administradores WHERE id = ?");
                $st->execute([$adminId]);
                $admin = $st->fetch();
            } catch (Exception $e) {}
        }
        if (!$admin) return '';

        $config = getAllConfigForAdmin($adminId);
        $empresaLogo = getLogoEmpresaFatura($adminId);
        if (!logoPathValido($empresaLogo)) $empresaLogo = '/cobranca/assets/img/logo_color.png';

        $corPrimaria = $config['cor_primaria'] ?? getCorPrimaria();
        $nomeSistema = $config['nome_sistema'] ?? getNomeSistema();
        $pdf = new ReciboFaturaPdf('P', 'mm', 'A4');
        $pdf->SetAutoPageBreak(false);
        $pdf->nomeSistema = $nomeSistema;
        $pdf->corPrimaria = [31, 26, 46];
        if ($corPrimaria) $pdf->setCorPrimariaHex($corPrimaria);
        $pdf->AddPage();

        $logoLocal = resolverCaminhoRecibo($empresaLogo);
        $pdf->desenharCabecalho($logoLocal);
        $pdf->desenharCorpo($recibo, $fat, $admin);

        $dir = sys_get_temp_dir();
        $arq = $dir . '/recibo_' . preg_replace('/[^A-Za-z0-9_-]/', '', $recibo['numero'] ?? 'fat' . $fat['id']) . '_' . uniqid() . '.pdf';
        $pdf->Output('F', $arq);
        return $arq;
    }
}

if (!function_exists('resolverCaminhoRecibo')) {
    function resolverCaminhoRecibo($url) {
        if (empty($url)) return '';
        if (preg_match('#^[A-Za-z]:[\\\\/]#', $url)) return file_exists($url) ? $url : '';
        if (strpos($url, 'http') === 0 || strpos($url, 'data:') === 0) return '';
        $urlSemBarra = ltrim($url, '/');
        $candidatos = [];
        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            $candidatos[] = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $urlSemBarra);
        }
        $candidatos[] = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . basename($url);
        $candidatos[] = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $urlSemBarra);
        foreach ($candidatos as $c) {
            if (!empty($c) && file_exists($c)) return $c;
        }
        return '';
    }
}

class ReciboFaturaPdf extends FPDF
{
    public $corPrimaria = [31, 26, 46];
    public $corEscura = [31, 26, 46];
    public $corCinza = [107, 114, 128];
    public $corFundo = [245, 245, 249];
    public $corBorda = [224, 226, 231];
    public $nomeSistema = 'Sistema de Cobrança';
    public $mx = 16;
    public $cw = 178;

    public function t($s) {
        $s = (string) $s;
        if ($s === '') return '';
        $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s);
        return $converted !== false ? $converted : $s;
    }

    public function setCorPrimariaHex($hex) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 6 && ctype_xdigit($hex)) {
            $this->corPrimaria = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
        }
    }

    public function RoundedRect($x, $y, $w, $h, $r, $style = 'F') {
        $r = max(0, min((float) $r, $w / 2, $h / 2));
        $k = $this->k;
        $hp = $this->h;
        $seg = 16;
        $pts = [];
        $pts[] = [$x + $r, $y];
        $pts[] = [$x + $w - $r, $y];
        $cx = $x + $w - $r; $cy = $y + $r;
        for ($i = 1; $i <= $seg; $i++) {
            $a = -M_PI / 2 + ($i / $seg) * (M_PI / 2);
            $pts[] = [$cx + $r * cos($a), $cy + $r * sin($a)];
        }
        $pts[] = [$x + $w, $y + $h - $r];
        $cx = $x + $w - $r; $cy = $y + $h - $r;
        for ($i = 1; $i <= $seg; $i++) {
            $a = ($i / $seg) * (M_PI / 2);
            $pts[] = [$cx + $r * cos($a), $cy + $r * sin($a)];
        }
        $pts[] = [$x + $r, $y + $h];
        $cx = $x + $r; $cy = $y + $h - $r;
        for ($i = 1; $i <= $seg; $i++) {
            $a = M_PI / 2 + ($i / $seg) * (M_PI / 2);
            $pts[] = [$cx + $r * cos($a), $cy + $r * sin($a)];
        }
        $pts[] = [$x, $y + $r];
        $cx = $x + $r; $cy = $y + $r;
        for ($i = 1; $i <= $seg; $i++) {
            $a = M_PI + ($i / $seg) * (M_PI / 2);
            $pts[] = [$cx + $r * cos($a), $cy + $r * sin($a)];
        }
        $s = '';
        $first = true;
        foreach ($pts as $p) {
            $s .= sprintf('%.2F %.2F %s ', $p[0] * $k, ($hp - $p[1]) * $k, $first ? 'm' : 'l');
            $first = false;
        }
        $s .= 'h ';
        if ($style === 'F') {
            $s .= 'f';
        } elseif ($style === 'FD' || $style === 'DF') {
            $s .= 'B';
        } else {
            $s .= 'S';
        }
        $this->_out($s);
    }

    public function desenharCabecalho($logoLocal) {
        $primaria = $this->corPrimaria;
        $this->SetDrawColor($primaria[0], $primaria[1], $primaria[2]);
        $this->SetLineWidth(0.6);
        $this->Line(0, 26, 210, 26);

        if ($logoLocal && file_exists($logoLocal)) {
            $info = @getimagesize($logoLocal);
            if ($info) {
                $lw = $info[0];
                $lh = $info[1];
                $maxW = 70;
                $maxH = 22;
                $ratio = min($maxW / max(1, $lw), $maxH / max(1, $lh));
                $drawW = $lw * $ratio;
                $drawH = $lh * $ratio;
                $this->Image($logoLocal, 14, 4, $drawW, $drawH);
            } else {
                $this->Image($logoLocal, 14, 4, 40, 18);
            }
        } else {
            $this->SetFont('Helvetica', 'B', 13);
            $this->SetTextColor($this->corEscura[0], $this->corEscura[1], $this->corEscura[2]);
            $this->SetXY(14, 6);
            $this->Cell(90, 8, $this->t($this->nomeSistema), 0, 1, 'L');
        }

        $this->SetY(31);
        $this->SetFont('Helvetica', 'B', 16);
        $this->SetTextColor($this->corEscura[0], $this->corEscura[1], $this->corEscura[2]);
        $this->Cell($this->cw, 8, $this->t('RECIBO DE PAGAMENTO'), 0, 1, 'C');
        $this->Ln(2);
    }

    public function desenharCorpo($recibo, $fat, $admin) {
        $primaria = $this->corPrimaria;
        $y = $this->GetY() + 4;

        // Dados do recibo (numero, fatura, data)
        $this->SetFont('Helvetica', '', 10);
        $this->SetTextColor($this->corCinza[0], $this->corCinza[1], $this->corCinza[2]);
        $this->SetXY($this->mx, $y);
        $this->Cell($this->cw, 6, $this->t('Nº ' . ($recibo['numero'] ?? '') . '   |   Fatura ' . ($fat['numero'] ?? '') . '   |   Emissão ' . formatarDataRecibo($recibo['data_emissao'] ?? '')), 0, 1, 'C');
        $y = $this->GetY() + 6;

        // Box emitente
        $this->boxDados($this->t('EMITENTE'), [
            ['Empresa', $admin['nome_fantasia'] ?: $admin['nome']],
            ['CNPJ', mascaraReciboCpfCnpj($admin['cnpj'] ?? '')],
            ['Endereço', trim(($admin['logradouro'] ?? '') . ', ' . ($admin['numero'] ?? '') . ' ' . ($admin['complemento'] ?? ''))],
            ['Cidade/UF', trim(($admin['cidade'] ?? '') . ' - ' . ($admin['estado'] ?? ''))],
            ['Telefone', $admin['telefone_comercial'] ?? ''],
            ['E-mail', $admin['email_comercial'] ?? $admin['email'] ?? ''],
        ]);
        $this->Ln(4);

        // Box tomador
        $this->boxDados($this->t('TOMADOR'), [
            ['Cliente', $fat['nome_razao'] ?? ''],
            ['CPF/CNPJ', mascaraReciboCpfCnpj($fat['cpf_cnpj'] ?? '')],
            ['Endereço', trim(($fat['logradouro'] ?? '') . ', ' . ($fat['cliente_numero'] ?? '') . ' ' . ($fat['complemento'] ?? ''))],
            ['Cidade/UF', trim(($fat['cidade'] ?? '') . ' - ' . ($fat['estado'] ?? ''))],
            ['Telefone', $fat['telefone'] ?? $fat['celular'] ?? ''],
            ['E-mail', $fat['email'] ?? ''],
        ]);
        $this->Ln(4);

        // Valor recebido
        $this->boxValor($recibo, $fat);

        // Assinatura
        $configVars = getAllConfigForAdmin((int)($fat['admin_id'] ?? 0));
        if ((($configVars['template_recibo_assinatura'] ?? '1') === '1')) {
            $y = $this->GetY() + 18;
            $this->SetFont('Helvetica', '', 9);
            $this->SetTextColor($this->corCinza[0], $this->corCinza[1], $this->corCinza[2]);
            $this->SetY($y);
            $this->Cell($this->cw, 5, $this->t(($recibo['cidade_emissao'] ?? '') . ', ' . formatarDataRecibo($recibo['data_emissao'] ?? '')), 0, 1, 'C');
            $this->SetY($y + 16);
            $this->SetLineWidth(0.3);
            $this->SetDrawColor($this->corEscura[0], $this->corEscura[1], $this->corEscura[2]);
            $this->Line(105 - 40, $this->GetY(), 105 + 40, $this->GetY());
            $this->SetY($this->GetY() + 2);
            $this->SetFont('Helvetica', 'B', 9);
            $this->SetTextColor($this->corEscura[0], $this->corEscura[1], $this->corEscura[2]);
            $this->Cell($this->cw, 5, $this->t($admin['nome_fantasia'] ?: $admin['nome']), 0, 1, 'C');
            $this->SetFont('Helvetica', '', 8);
            $this->SetTextColor($this->corCinza[0], $this->corCinza[1], $this->corCinza[2]);
            $this->Cell($this->cw, 4, $this->t('CNPJ: ' . mascaraReciboCpfCnpj($admin['cnpj'] ?? '')), 0, 1, 'C');
        }

        // Rodapé
        $this->SetAutoPageBreak(true, 18);
        $this->SetY(-18);
        $this->SetFont('Helvetica', '', 7.5);
        $this->SetTextColor($this->corCinza[0], $this->corCinza[1], $this->corCinza[2]);
        $this->Cell(0, 4, $this->t('Documento gerado automaticamente por ' . $this->nomeSistema), 0, 1, 'C');
        $this->Cell(0, 4, $this->t('Este recibo comprova o recebimento do valor pela fatura mencionada.'), 0, 1, 'C');
    }

    private function boxDados($titulo, $linhas) {
        $primaria = $this->corPrimaria;
        $lineH = 6;
        $labelW = 42;
        $pad = 6;
        $cardX = $this->mx;
        $cardW = $this->cw;
        $conteudoW = $cardW - $pad * 2 - $labelW - 4;
        $y0 = $this->GetY();

        if ($this->GetY() > 250) {
            $this->AddPage();
            $this->SetY(20);
            $y0 = $this->GetY();
        }

        $h = 7 + $pad;
        $this->SetFont('Helvetica', '', 9);
        foreach ($linhas as $l) {
            $n = max(1, ceil($this->GetStringWidth($this->t($l[1])) / max(1, $conteudoW)));
            $h += $n * $lineH;
        }
        $h += $pad;

        $this->SetFillColor($this->corFundo[0], $this->corFundo[1], $this->corFundo[2]);
        $this->RoundedRect($cardX, $y0, $cardW, $h, 4, 'F');
        $this->SetFillColor($primaria[0], $primaria[1], $primaria[2]);
        $this->Rect($cardX, $y0 + 1, 2.5, $h - 2, 'F');

        $this->SetXY($cardX + $pad + 5, $y0 + $pad);
        $this->SetFont('Helvetica', 'B', 8);
        $this->SetTextColor($primaria[0], $primaria[1], $primaria[2]);
        $this->Cell($cardW - $pad * 2 - 5, 5, $titulo, 0, 1, 'L');

        $yy = $this->GetY() + 1;
        foreach ($linhas as $l) {
            $label = $l[0];
            $valor = $l[1];
            $this->SetFont('Helvetica', '', 9);
            $n = max(1, ceil($this->GetStringWidth($this->t($valor)) / max(1, $conteudoW)));
            $rowH = $n * $lineH;
            $this->SetXY($cardX + $pad + 5, $yy);
            $this->SetFont('Helvetica', '', 8);
            $this->SetTextColor($this->corCinza[0], $this->corCinza[1], $this->corCinza[2]);
            $this->Cell($labelW, $rowH, $this->t($label), 0, 0, 'L');
            $this->SetXY($cardX + $pad + 5 + $labelW, $yy);
            $this->SetFont('Helvetica', '', 9.5);
            $this->SetTextColor($this->corEscura[0], $this->corEscura[1], $this->corEscura[2]);
            $this->MultiCell($conteudoW, $lineH, $this->t($valor), 0, 'L');
            $yy = $this->GetY();
        }
        $this->SetY($y0 + $h + 4);
    }

    private function boxValor($recibo, $fat) {
        $primaria = $this->corPrimaria;
        $y0 = $this->GetY();
        if ($this->GetY() > 250) {
            $this->AddPage();
            $this->SetY(20);
            $y0 = $this->GetY();
        }

        $pad = 8;
        $boxW = $this->cw;
        $boxX = $this->mx;
        $h = 34;

        $this->SetFillColor($this->corFundo[0], $this->corFundo[1], $this->corFundo[2]);
        $this->RoundedRect($boxX, $y0, $boxW, $h, 5, 'F');
        $this->SetDrawColor($primaria[0], $primaria[1], $primaria[2]);
        $this->SetLineWidth(0.8);
        $this->RoundedRect($boxX, $y0, $boxW, $h, 5, 'D');

        $this->SetXY($boxX + $pad, $y0 + $pad);
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor($this->corCinza[0], $this->corCinza[1], $this->corCinza[2]);
        $this->Cell($boxW - $pad * 2, 5, $this->t('VALOR RECEBIDO'), 0, 1, 'L');

        $this->SetX($boxX + $pad);
        $this->SetFont('Helvetica', 'B', 17);
        $this->SetTextColor($primaria[0], $primaria[1], $primaria[2]);
        $this->Cell($boxW - $pad * 2, 9, formatarMoedaRecibo($fat['valor_final']), 0, 1, 'L');

        $this->SetX($boxX + $pad);
        $this->SetFont('Helvetica', '', 8.5);
        $this->SetTextColor($this->corCinza[0], $this->corCinza[1], $this->corCinza[2]);
        $this->MultiCell($boxW - $pad * 2, 4.6, $this->t('(' . ($recibo['valor_extenso'] ?? '') . ')'));

        $this->SetY($y0 + $h + 6);
    }
}