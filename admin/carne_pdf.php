<?php
// =====================================================
// CARNE EM LOTE (PDF)
// Gera um unico PDF com a capa do carne e uma pagina por
// parcela do "Boleto em Lote". Cada parcela mostra os dados
// do cliente, valor, vencimento, parcela i/N e a linha
// digitavel/codigo de barras do boleto ja emitido.
// =====================================================

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/fpdf/fpdf.php';

$pdo = getConnection();
$adminIdE = (int)$_SESSION['admin_id'];
$frId = (int)($_GET['fr'] ?? 0);

if ($frId <= 0) {
    http_response_code(404);
    exit('Carnê inválido.');
}

$stmt = $pdo->prepare("SELECT * FROM faturas_recorrentes WHERE id = ? AND admin_id = ?");
$stmt->execute([$frId, $adminIdE]);
$rec = $stmt->fetch();
if (!$rec || ($rec['frequencia'] ?? '') !== 'lote') {
    http_response_code(404);
    exit('Carnê não encontrado.');
}

$stmt = $pdo->prepare("
    SELECT f.*, c.nome_razao, c.cpf_cnpj, c.email, c.celular, c.telefone,
           c.cep, c.logradouro, c.numero AS cliente_numero, c.complemento, c.bairro, c.cidade, c.estado
    FROM faturas f
    JOIN clientes c ON f.cliente_id = c.id
    WHERE f.fatura_recorrente_id = ? AND f.admin_id = ? AND f.status != 'cancelado'
    ORDER BY f.parcela_atual ASC, f.data_vencimento ASC
");
$stmt->execute([$frId, $adminIdE]);
$faturas = $stmt->fetchAll();
if (empty($faturas)) {
    http_response_code(404);
    exit('Nenhuma parcela encontrada.');
}

// Emitente (admin logado)
$stAdmin = $pdo->prepare("SELECT * FROM administradores WHERE id = ?");
$stAdmin->execute([$adminIdE]);
$admin = $stAdmin->fetch() ?: [];
$config = getAllConfigForAdmin($adminIdE);
$nomeSistema = $config['nome_sistema'] ?? getNomeSistema();

$empresaNome = $admin['nome_fantasia'] ?? $admin['nome'] ?? $nomeSistema;
$cliente = $faturas[0];
$totalParcelas = (int)($rec['quantidade_transacoes'] ?? count($faturas));

class CarneLotePdf extends FPDF
{
    public $corPrimaria = [31, 26, 46];
    public $mx = 16;
    public $cw = 178;
    public $empresaNome = '';
    public $nomeSistema = 'Sistema de Cobrança';

    public function t($s) {
        $s = (string) $s;
        if ($s === '') return '';
        $c = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s);
        return $c !== false ? $c : $s;
    }

    public function cabecalho() {
        $p = $this->corPrimaria;
        $this->SetDrawColor($p[0], $p[1], $p[2]);
        $this->SetLineWidth(0.6);
        $this->Line(0, 18, 210, 18);
        $this->SetFont('Helvetica', 'B', 13);
        $this->SetTextColor($p[0], $p[1], $p[2]);
        $this->SetXY($this->mx, 5);
        $this->Cell($this->cw, 9, $this->t($this->empresaNome), 0, 1, 'L');
        $this->SetY(22);
    }

    public function rodape() {
        $this->SetY(-14);
        $this->SetFont('Helvetica', '', 7.5);
        $this->SetTextColor(107, 114, 128);
        $this->Cell(0, 4, $this->t('Documento gerado automaticamente por ' . $this->nomeSistema), 0, 0, 'C');
    }

    // Capa do carne.
    public function capa($admin, $fat, $rec, $totalParcelas, $qtdEmitidas) {
        $p = $this->corPrimaria;
        $this->cabecalho();

        $this->SetFont('Helvetica', 'B', 20);
        $this->SetTextColor($p[0], $p[1], $p[2]);
        $this->SetY(30);
        $this->Cell($this->cw, 12, $this->t('CARNÊ DE PAGAMENTO'), 0, 1, 'C');
        $this->SetFont('Helvetica', '', 11);
        $this->SetTextColor(80, 80, 90);
        $this->Cell($this->cw, 7, $this->t('Boleto em Lote — ' . $totalParcelas . ' parcelas'), 0, 1, 'C');
        $this->Ln(6);

        $this->boxDados('BENEFICIÁRIO', [
            ['Empresa', $admin['nome_fantasia'] ?? $admin['nome'] ?? ''],
            ['CNPJ', $admin['cnpj'] ?? ''],
            ['Cidade/UF', trim(($admin['cidade'] ?? '') . ' - ' . ($admin['estado'] ?? ''))],
            ['Telefone', $admin['telefone_comercial'] ?? ''],
        ]);
        $this->Ln(2);

        $this->boxDados('PAGADOR', [
            ['Cliente', $fat['nome_razao'] ?? ''],
            ['CPF/CNPJ', $fat['cpf_cnpj'] ?? ''],
            ['Endereço', trim(($fat['logradouro'] ?? '') . ', ' . ($fat['cliente_numero'] ?? '') . ' ' . ($fat['complemento'] ?? ''))],
            ['Cidade/UF', trim(($fat['cidade'] ?? '') . ' - ' . ($fat['estado'] ?? ''))],
        ]);
        $this->Ln(2);

        $this->boxDados('RESUMO', [
            ['Descrição', $rec['descricao'] ?? ''],
            ['Valor por parcela', 'R$ ' . number_format((float)($rec['valor'] ?? 0), 2, ',', '.')],
            ['Parcelas', $qtdEmitidas . ' de ' . $totalParcelas],
            ['Emissão', date('d/m/Y')],
        ]);
    }

    // Uma pagina por parcela.
    public function parcela($fat, $totalParcelas) {
        $p = $this->corPrimaria;
        $this->cabecalho();

        $parc = (int)($fat['parcela_atual'] ?? 0);
        $this->SetFont('Helvetica', 'B', 14);
        $this->SetTextColor($p[0], $p[1], $p[2]);
        $this->SetY(24);
        $this->Cell($this->cw, 8, $this->t('PARCELA ' . $parc . ' / ' . $totalParcelas), 0, 1, 'C');
        $this->Ln(2);

        $this->boxDados('PAGADOR', [
            ['Cliente', $fat['nome_razao'] ?? ''],
            ['CPF/CNPJ', $fat['cpf_cnpj'] ?? ''],
        ]);
        $this->Ln(2);

        $this->boxDados('PARCELA', [
            ['Fatura', $fat['numero'] ?? ''],
            ['Descrição', $fat['descricao'] ?? ''],
            ['Vencimento', !empty($fat['data_vencimento']) ? date('d/m/Y', strtotime($fat['data_vencimento'])) : ''],
            ['Valor', 'R$ ' . number_format((float)($fat['valor_final'] ?? 0), 2, ',', '.')],
            ['Situação', ucfirst($fat['status'] ?? '')],
        ]);
        $this->Ln(2);

        $linha = trim((string)($fat['boleto_linha_digitavel'] ?? ''));
        $this->boxDados('BOLETO', $linha !== ''
            ? [['Linha digitável', $linha], ['Pague até', !empty($fat['data_vencimento']) ? date('d/m/Y', strtotime($fat['data_vencimento'])) : '']]
            : [['Boleto', 'Boleto ainda não emitido para esta parcela.']]
        );
        $this->Ln(2);

        // PIX: QR Code valido + copia e cola por parcela.
        $pix = str_replace(["\r\n", "\r", "\n"], '', (string)($fat['pix_copia_cola'] ?? ''));
        if ($pix !== '') {
            $this->pixQr($pix, (string)($fat['pix_qrcode'] ?? ''));
        } else {
            $this->boxDados('PIX', [['PIX', 'PIX ainda não emitido para esta parcela.']]);
        }
    }

    // QR Code PIX + copia e cola. Usa o QR ja retornado pela API quando
    // disponivel; caso contrario gera localmente a partir do copia-e-cola.
    private function pixQr($pix, $qrBase64) {
        $p = $this->corPrimaria;
        $qrData = '';
        $qrApi = trim((string) $qrBase64);
        if ($qrApi !== '') {
            $qrApi = preg_replace('/\s+/', '', $qrApi);
            $qrApi = preg_replace('#^data:image/[a-z]+;base64,#i', '', $qrApi);
            $dec = @base64_decode($qrApi, true);
            if ($dec !== false && $dec !== '' && (substr($dec, 1, 3) === 'PNG' || substr($dec, 0, 2) === "\xff\xd8")) {
                $qrData = $qrApi;
            }
        }
        if ($qrData === '') {
            require_once __DIR__ . '/../config/phpqrcode.php';
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
        }

        $this->boxDados('PIX — PAGAMENTO', [
            ['Código PIX (copia e cola)', $pix],
        ]);

        if ($qrData !== '') {
            $qrTmp = sys_get_temp_dir() . '/carne_qr_' . uniqid() . '.png';
            file_put_contents($qrTmp, base64_decode($qrData));
            $qrSize = 42;
            if ($this->GetY() > 235) {
                $this->AddPage();
                $this->cabecalho();
            }
            $this->SetFillColor(245, 245, 249);
            $this->RoundedRectCarne(105 - $qrSize / 2 - 3, $this->GetY(), $qrSize + 6, $qrSize + 6, 5, 'F');
            $this->Image($qrTmp, 105 - $qrSize / 2, $this->GetY() + 3, $qrSize, $qrSize);
            @unlink($qrTmp);
            $this->SetY($this->GetY() + $qrSize + 6);
        }
    }

    private function boxDados($titulo, $linhas) {
        $p = $this->corPrimaria;
        $lineH = 6;
        $labelW = 40;
        $pad = 6;
        $cardW = $this->cw;
        $conteudoW = $cardW - $pad * 2 - $labelW - 4;
        $y0 = $this->GetY();

        if ($y0 > 250) {
            $this->AddPage();
            $this->cabecalho();
            $y0 = $this->GetY();
        }

        $h = 7 + $pad;
        $this->SetFont('Helvetica', '', 9);
        foreach ($linhas as $l) {
            $n = max(1, (int)ceil($this->GetStringWidth($this->t($l[1])) / max(1, $conteudoW)));
            $h += $n * $lineH;
        }
        $h += $pad;

        $this->SetFillColor(245, 245, 249);
        $this->RoundedRectCarne($this->mx, $y0, $cardW, $h, 4, 'F');
        $this->SetFillColor($p[0], $p[1], $p[2]);
        $this->Rect($this->mx + 1, $y0 + 1, 2.5, $h - 2, 'F');

        $this->SetXY($this->mx + $pad + 5, $y0 + $pad);
        $this->SetFont('Helvetica', 'B', 8);
        $this->SetTextColor($p[0], $p[1], $p[2]);
        $this->Cell($cardW - $pad * 2 - 5, 5, $titulo, 0, 1, 'L');

        $yy = $this->GetY() + 1;
        foreach ($linhas as $l) {
            $valor = $l[1];
            $this->SetFont('Helvetica', '', 9);
            $n = max(1, (int)ceil($this->GetStringWidth($this->t($valor)) / max(1, $conteudoW)));
            $rowH = $n * $lineH;
            $this->SetXY($this->mx + $pad + 5, $yy);
            $this->SetFont('Helvetica', '', 8);
            $this->SetTextColor(107, 114, 128);
            $this->Cell($labelW, $rowH, $this->t($l[0]), 0, 0, 'L');
            $this->SetXY($this->mx + $pad + 5 + $labelW, $yy);
            $this->SetFont('Helvetica', '', 9.5);
            $this->SetTextColor(31, 26, 46);
            $this->MultiCell($conteudoW, $lineH, $this->t($valor), 0, 'L');
            $yy = $this->GetY();
        }
        $this->SetY($y0 + $h + 4);
    }

    private function RoundedRectCarne($x, $y, $w, $h, $r, $style = 'F') {
        $r = max(0, min((float) $r, $w / 2, $h / 2));
        $k = $this->k;
        $hp = $this->h;
        $seg = 12;
        $pts = [];
        $pts[] = [$x + $r, $y];
        $pts[] = [$x + $w - $r, $y];
        $cx = $x + $w - $r; $cy = $y + $r;
        for ($i = 1; $i <= $seg; $i++) { $a = -M_PI / 2 + ($i / $seg) * (M_PI / 2); $pts[] = [$cx + $r * cos($a), $cy + $r * sin($a)]; }
        $pts[] = [$x + $w, $y + $h - $r];
        $cx = $x + $w - $r; $cy = $y + $h - $r;
        for ($i = 1; $i <= $seg; $i++) { $a = ($i / $seg) * (M_PI / 2); $pts[] = [$cx + $r * cos($a), $cy + $r * sin($a)]; }
        $pts[] = [$x + $r, $y + $h];
        $cx = $x + $r; $cy = $y + $h - $r;
        for ($i = 1; $i <= $seg; $i++) { $a = M_PI / 2 + ($i / $seg) * (M_PI / 2); $pts[] = [$cx + $r * cos($a), $cy + $r * sin($a)]; }
        $pts[] = [$x, $y + $r];
        $cx = $x + $r; $cy = $y + $r;
        for ($i = 1; $i <= $seg; $i++) { $a = M_PI + ($i / $seg) * (M_PI / 2); $pts[] = [$cx + $r * cos($a), $cy + $r * sin($a)]; }
        $s = '';
        $first = true;
        foreach ($pts as $pt) {
            $s .= sprintf('%.2F %.2F %s ', $pt[0] * $k, ($hp - $pt[1]) * $k, $first ? 'm' : 'l');
            $first = false;
        }
        $s .= 'h ';
        $s .= ($style === 'F') ? 'f' : ($style === 'FD' || $style === 'DF' ? 'B' : 'S');
        $this->_out($s);
    }
}

$pdf = new CarneLotePdf('P', 'mm', 'A4');
$pdf->SetAutoPageBreak(false);
$pdf->empresaNome = $empresaNome;
$pdf->nomeSistema = $nomeSistema;
$corPrimaria = $config['cor_primaria'] ?? getCorPrimaria();
if ($corPrimaria && preg_match('/^#?[0-9a-fA-F]{6}$/', $corPrimaria)) {
    $hex = ltrim($corPrimaria, '#');
    $pdf->corPrimaria = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}

$pdf->AddPage();
$pdf->capa($admin, $cliente, $rec, $totalParcelas, count($faturas));
$pdf->rodape();

foreach ($faturas as $fat) {
    $pdf->AddPage();
    $pdf->parcela($fat, $totalParcelas);
    $pdf->rodape();
}

$nomeArq = 'carne_' . preg_replace('/[^A-Za-z0-9_-]/', '', (string)($cliente['nome_razao'] ?? 'lote' . $frId)) . '.pdf';
$pdf->Output('D', $nomeArq);
exit;
