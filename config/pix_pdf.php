<?php
// =====================================================
// GERADOR DE PDF DE COBRANÇA PIX
// Comprovante em PDF com: logo, dados do cliente,
// dados do beneficiário, QR Code e código PIX copia-e-cola.
// =====================================================

require_once __DIR__ . '/fpdf/fpdf.php';

if (!function_exists('resolverCaminhoArquivoLocal')) {
    function resolverCaminhoArquivoLocal($url) {
        if (empty($url)) return '';
        if (preg_match('#^[A-Za-z]:[\\\\/]#', $url)) {
            return file_exists($url) ? $url : '';
        }
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

if (!function_exists('mascaraCpfCnpj')) {
    function mascaraCpfCnpj($valor) {
        $v = preg_replace('/[^0-9]/', '', (string) $valor);
        if (strlen($v) === 11) return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $v);
        if (strlen($v) === 14) return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $v);
        return $valor;
    }
}

if (!function_exists('formatarMoedaPdf')) {
    function formatarMoedaPdf($valor) {
        return 'R$ ' . number_format((float) $valor, 2, ',', '.');
    }
}

if (!function_exists('formatarDataPdf')) {
    function formatarDataPdf($data) {
        if (empty($data) || $data === '0000-00-00') return '';
        return date('d/m/Y', strtotime($data));
    }
}

class CobrancaPixPdf extends FPDF
{
    public $corPrimaria = [188, 21, 21];
    public $corEscura = [31, 36, 48];
    public $corCinza = [107, 114, 128];
    public $corFundo = [244, 245, 247];
    public $corBorda = [224, 226, 231];
    public $nomeSistema = 'Sistema de Cobrança';
    public $mx = 14;
    public $cw = 182;

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

    public function desenharCabecalho($logoPath) {
        $primaria = $this->corPrimaria;
        $faixaAltura = 18.5;
        $this->SetFillColor($primaria[0], $primaria[1], $primaria[2]);
        $this->Rect(0, 0, 210, $faixaAltura, 'F');
        $logoLocal = resolverCaminhoArquivoLocal($logoPath);
        if ($logoLocal && file_exists($logoLocal)) {
            $info = @getimagesize($logoLocal);
            $centroY = ($faixaAltura + 58) / 2;
            if ($info) {
                $lw = $info[0];
                $lh = $info[1];
                $maxW = 80;
                $maxH = 22;
                $ratio = min($maxW / max(1, $lw), $maxH / max(1, $lh));
                $drawW = $lw * $ratio;
                $drawH = $lh * $ratio;
                $this->Image($logoLocal, 105 - $drawW / 2, $centroY - $drawH / 2, $drawW, $drawH);
            } else {
                $this->Image($logoLocal, 105 - 40, $centroY - 13, 80, 26);
            }
        } else {
            $this->SetFont('Helvetica', 'B', 14);
            $this->SetTextColor($this->corEscura[0], $this->corEscura[1], $this->corEscura[2]);
            $this->SetXY(65, $faixaAltura + 14);
            $this->Cell(80, 8, $this->t($this->nomeSistema), 0, 1, 'C');
        }
    }

    public function tituloPagina($numero) {
        $this->SetY(58);
        $this->SetFont('Helvetica', 'B', 17);
        $this->SetTextColor($this->corEscura[0], $this->corEscura[1], $this->corEscura[2]);
        $this->Cell($this->cw, 8, $this->t('Comprovante de Cobrança'), 0, 1, 'C');
        $this->SetFont('Helvetica', '', 10);
        $this->SetTextColor($this->corCinza[0], $this->corCinza[1], $this->corCinza[2]);
        $this->Cell($this->cw, 6, $this->t('Fatura ' . $numero), 0, 1, 'C');
        $this->SetDrawColor($this->corBorda[0], $this->corBorda[1], $this->corBorda[2]);
        $this->SetLineWidth(0.3);
        $this->Line($this->mx, $this->GetY() + 3, 210 - $this->mx, $this->GetY() + 3);
        $this->SetY($this->GetY() + 9);
    }

    public function sectionCard($titulo, $linhas) {
        $primaria = $this->corPrimaria;
        if ($this->GetY() > 255) {
            $this->AddPage();
            $this->SetY(18);
        }
        $pad = 5;
        $labelW = 40;
        $cardX = $this->mx;
        $cardW = $this->cw;
        $conteudoW = $cardW - $pad * 2 - $labelW - 4;
        $lineH = 6;
        $y0 = $this->GetY();

        $this->SetFont('Helvetica', '', 9);
        $h = 7 + $pad;
        foreach ($linhas as $linha) {
            $valor = $linha[1];
            $nLinhas = max(1, ceil($this->GetStringWidth($this->t($valor)) / max(1, $conteudoW)));
            $h += $nLinhas * $lineH;
        }
        $h += $pad + 2;

        $this->SetFillColor($this->corFundo[0], $this->corFundo[1], $this->corFundo[2]);
        $this->RoundedRect($cardX, $y0, $cardW, $h, 4, 'F');
        $this->SetFillColor($primaria[0], $primaria[1], $primaria[2]);
        $this->Rect($cardX, $y0 + 1, 2.5, $h - 2, 'F');

        $this->SetXY($cardX + $pad + 5, $y0 + $pad);
        $this->SetFont('Helvetica', 'B', 8);
        $this->SetTextColor($primaria[0], $primaria[1], $primaria[2]);
        $this->Cell($cardW - $pad * 2 - 5, 5, $this->t($titulo), 0, 1, 'L');

        $yy = $this->GetY() + 1;
        foreach ($linhas as $linha) {
            $label = $linha[0];
            $valor = $linha[1];
            $this->SetFont('Helvetica', '', 9);
            $nLinhas = max(1, ceil($this->GetStringWidth($this->t($valor)) / max(1, $conteudoW)));
            $rowH = $nLinhas * $lineH;
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
        $this->SetY($y0 + $h + 6);
    }

    public function desenharQrEPix($pix) {
        $primaria = $this->corPrimaria;
        if ($this->GetY() > 235) {
            $this->AddPage();
            $this->SetY(18);
        }

        $this->SetFont('Helvetica', 'B', 11);
        $this->SetTextColor($this->corEscura[0], $this->corEscura[1], $this->corEscura[2]);
        $this->Cell($this->cw, 7, $this->t('Pagamento via PIX'), 0, 1, 'C');
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor($this->corCinza[0], $this->corCinza[1], $this->corCinza[2]);
        $this->Cell($this->cw, 5, $this->t('Escaneie o QR Code ou utilize o código abaixo'), 0, 1, 'C');
        $this->Ln(3);

        $qrData = '';
        require_once __DIR__ . '/phpqrcode.php';
        try {
            ob_start();
            QRcode::png($pix, false, QR_ECLEVEL_M, 8, 2);
            $img = ob_get_clean();
            if ($img !== false && !empty($img)) {
                $qrData = base64_encode($img);
            }
        } catch (Exception $e) {
            $qrData = '';
        }

        if ($qrData) {
            $qrTmp = sys_get_temp_dir() . '/qr_' . uniqid() . '.png';
            file_put_contents($qrTmp, base64_decode($qrData));
            $qrSize = 46;
            $this->SetFillColor($this->corFundo[0], $this->corFundo[1], $this->corFundo[2]);
            $this->RoundedRect(105 - $qrSize / 2 - 3, $this->GetY(), $qrSize + 6, $qrSize + 6, 5, 'F');
            $this->Image($qrTmp, 105 - $qrSize / 2, $this->GetY() + 3, $qrSize, $qrSize);
            @unlink($qrTmp);
            $this->SetY($this->GetY() + $qrSize + 2);
        }

        if ($this->GetY() > 245) {
            $this->AddPage();
            $this->SetY(18);
        }

        $boxW = 165;
        $boxX = (210 - $boxW) / 2;
        $boxY = $this->GetY();
        $pad = 6;
        $this->SetFont('Helvetica', 'B', 8);
        $this->SetTextColor($primaria[0], $primaria[1], $primaria[2]);
        $this->SetXY($boxX + $pad, $boxY + $pad);
        $this->Cell($boxW - $pad * 2, 5, $this->t('CÓDIGO PIX — COPIA E COLA'), 0, 1, 'C');

        $this->SetFont('Courier', '', 8);
        $this->SetTextColor($this->corEscura[0], $this->corEscura[1], $this->corEscura[2]);
        $this->SetXY($boxX + $pad, $boxY + $pad + 6);
        $pixH = $this->MultiCell($boxW - $pad * 2, 4.6, $this->t($pix), 0, 'C');

        $this->SetY($boxY + $pixH + 10);
    }

    public function rodape() {
        $this->SetY(-18);
        $this->SetFont('Helvetica', '', 7.5);
        $this->SetTextColor($this->corCinza[0], $this->corCinza[1], $this->corCinza[2]);
        $this->Cell(0, 4, $this->t('Documento gerado automaticamente por ' . $this->nomeSistema), 0, 1, 'C');
        $this->Cell(0, 4, $this->t('Este comprovante não é um boleto bancário.'), 0, 1, 'C');
    }
}

function buscarClienteParaPdf($fatura) {
    $pdo = getConnection();
    $cliente = [
        'nome_razao' => $fatura['nome_razao'] ?? '',
        'cpf_cnpj' => $fatura['cpf_cnpj'] ?? '',
        'email' => $fatura['email'] ?? '',
        'celular' => $fatura['celular'] ?? '',
        'telefone' => $fatura['telefone'] ?? '',
        'logradouro' => $fatura['logradouro'] ?? '',
        'numero' => $fatura['numero_endereco'] ?? '',
        'bairro' => $fatura['bairro'] ?? '',
        'cidade' => $fatura['cidade'] ?? '',
        'estado' => $fatura['estado'] ?? '',
        'cep' => $fatura['cep'] ?? '',
    ];
    if ($pdo && !empty($fatura['cliente_id'])) {
        try {
            $stmt = $pdo->prepare("SELECT nome_razao, cpf_cnpj, email, celular, telefone, logradouro, numero, bairro, cidade, estado, cep FROM clientes WHERE id = ? LIMIT 1");
            $stmt->execute([$fatura['cliente_id']]);
            $row = $stmt->fetch();
            if ($row) {
                foreach ($row as $k => $v) {
                    $cliente[$k] = $v;
                }
            }
        } catch (Exception $e) {
        }
    }
    return $cliente;
}

function buscarBeneficiarioParaPdf() {
    $benef = [
        'favorecido' => getConfig('pix_manual_favorecido', ''),
        'cnpj' => getConfig('pix_manual_cnpj', ''),
        'banco' => getConfig('pix_manual_banco', ''),
        'chave' => getConfig('pix_manual_chave', ''),
    ];
    $pdo = getConnection();
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT nome_fantasia, cnpj FROM administradores WHERE id = 1");
            $stmt->execute();
            $admin = $stmt->fetch();
            if ($admin) {
                if (empty($benef['favorecido']) && !empty($admin['nome_fantasia'])) $benef['favorecido'] = $admin['nome_fantasia'];
                if (empty($benef['cnpj']) && !empty($admin['cnpj'])) $benef['cnpj'] = $admin['cnpj'];
            }
        } catch (Exception $e) {
        }
    }
    return $benef;
}

function gerarPixPdfFatura($fatura) {
    require_once __DIR__ . '/settings.php';
    require_once __DIR__ . '/fpdf/fpdf.php';

    $pix = str_replace(["\r\n", "\r", "\n"], '', $fatura['pix_copia_cola'] ?? '');
    if (empty($pix)) return null;

    $cliente = buscarClienteParaPdf($fatura);
    $benef = buscarBeneficiarioParaPdf();

    $pdf = new CobrancaPixPdf('P', 'mm', 'A4');
    $pdf->SetAutoPageBreak(false);
    $pdf->nomeSistema = getNomeSistema();
    $cor = getCorPrimaria();
    if ($cor) {
        $pdf->setCorPrimariaHex($cor);
    }
    $pdf->AddPage();

    $pdf->desenharCabecalho(getLogoLogin());
    $pdf->tituloPagina($fatura['numero'] ?? '');

    $pdf->sectionCard('DADOS DO CLIENTE', [
        ['Cliente', $cliente['nome_razao']],
        ['CPF / CNPJ', mascaraCpfCnpj($cliente['cpf_cnpj'])],
        ['E-mail', $cliente['email']],
    ]);

    $pdf->sectionCard('DADOS DO BENEFICIÁRIO', [
        ['Favorecido', $benef['favorecido']],
        ['CNPJ', mascaraCpfCnpj($benef['cnpj'])],
    ]);

    $pdf->desenharQrEPix($pix);
    $pdf->rodape();
    $dir = sys_get_temp_dir();
    $arq = $dir . '/pix_' . preg_replace('/[^A-Za-z0-9_-]/', '', $fatura['numero'] ?? '') . '_' . uniqid() . '.pdf';
    $pdf->Output('F', $arq);
    return $arq;
}
