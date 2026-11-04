<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/recibo_template.php';
require_once __DIR__ . '/../config/valor_extenso.php';

$pdo = getConnection();
$adminId = (int)$_SESSION['admin_id'];
$config = getAllConfig();

$mensagem = '';
$tipo = '';

$stAdmin = $pdo->prepare("SELECT * FROM administradores WHERE id = ?");
$stAdmin->execute([$adminId]);
$admin = $stAdmin->fetch() ?: [];

if (!function_exists('reciboAvulsoDocV2')) {
    function reciboAvulsoDocV2($v) {
        $v = preg_replace('/[^0-9]/', '', (string)$v);
        if (strlen($v) === 11) {
            return substr($v,0,3) . '.' . substr($v,3,3) . '.' . substr($v,6,3) . '-' . substr($v,9,2);
        }
        if (strlen($v) === 14) {
            return substr($v,0,2) . '.' . substr($v,2,3) . '.' . substr($v,5,3) . '/' . substr($v,8,4) . '-' . substr($v,12,2);
        }
        return $v;
    }
}
if (!function_exists('reciboAvulsoCepV2')) {
    function reciboAvulsoCepV2($v) {
        $v = preg_replace('/[^0-9]/', '', (string)$v);
        if (strlen($v) === 8) {
            return substr($v,0,5) . '-' . substr($v,5,3);
        }
        return $v;
    }
}

$empresaLogo = getConfig('logo_empresa_admin', '');
if (empty($empresaLogo) || !logoPathValido($empresaLogo)) {
    $empresaLogo = getLogoLogin();
}
if (empty($empresaLogo) || !logoPathValido($empresaLogo)) {
    $empresaLogo = APP_BASE . '/assets/img/logo_color.png';
}
$logoAbs = $empresaLogo;
if (strpos($empresaLogo, 'http') !== 0 && strpos($empresaLogo, 'data:') !== 0) {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $empresaLogoRel = '/' . ltrim(preg_replace('#^/cobranca#', '', $empresaLogo), '/');
    $logoAbs = $proto . '://' . $host . APP_BASE . $empresaLogoRel;
}

$emitNome = trim($admin['nome_fantasia'] ?? '') ?: (trim($admin['razao_social'] ?? '') ?: getNomeSistema());
$emitCnpj = reciboAvulsoDocV2($admin['cnpj'] ?? '');
$emitInscMun = trim($admin['inscricao_municipal'] ?? '');
$emitEnd = trim((trim($admin['logradouro'] ?? '') . ', ' . trim($admin['numero'] ?? '')), " ,");
$emitCidade = trim($admin['cidade'] ?? '');
$emitEstado = trim($admin['estado'] ?? '');
$emitCep = reciboAvulsoCepV2($admin['cep'] ?? '');
$emitTel = trim($admin['telefone_comercial'] ?? '');
$emitEmail = trim($admin['email_comercial'] ?? '') ?: trim($admin['email'] ?? '');

$exibirAssinatura = ($config['template_recibo_assinatura'] ?? '1') === '1';
$cidadeEmissaoPadrao = trim($config['template_recibo_cidade_emissao'] ?? '') ?: $emitCidade;
$descPadrao = trim($config['template_recibo_descricao_padrao'] ?? '') ?: 'referente à prestação de serviços conforme acordado entre as partes.';

$reciboNumeroGerado = '';
$reciboHtml = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'gerar') {
    $clNome = htmlspecialchars(trim($_POST['cliente_nome'] ?? ''));
    $clDoc = htmlspecialchars(reciboAvulsoDocV2($_POST['cliente_doc'] ?? ''));
    $clEnd = trim((trim($_POST['cliente_logradouro'] ?? '') . ', ' . trim($_POST['cliente_numero'] ?? '') . ' ' . trim($_POST['cliente_complemento'] ?? '')), " ,");
    $clEnd = htmlspecialchars($clEnd);
    $clCidade = htmlspecialchars(trim($_POST['cliente_cidade'] ?? ''));
    $clEstado = htmlspecialchars(trim($_POST['cliente_estado'] ?? ''));
    $clCep = htmlspecialchars(reciboAvulsoCepV2($_POST['cliente_cep'] ?? ''));
    $clTel = htmlspecialchars(trim($_POST['cliente_telefone'] ?? ''));
    $clEmail = htmlspecialchars(trim($_POST['cliente_email'] ?? ''));
    $descServico = htmlspecialchars(trim($_POST['descricao_servico'] ?? '') ?: $descPadrao);

    $dataEmissao = trim($_POST['data_emissao'] ?? '');
    $dataEmissaoFmt = $dataEmissao !== '' ? date('d/m/Y', strtotime($dataEmissao)) : date('d/m/Y');
    $cidadeEmissao = htmlspecialchars(trim($_POST['cidade_emissao'] ?? '') ?: $cidadeEmissaoPadrao);

    $valorBruto = trim($_POST['valor'] ?? '0');
    $valorBruto = str_replace('.', '', $valorBruto);
    $valorBruto = str_replace(',', '.', $valorBruto);
    $valorRec = max(0, (float)$valorBruto);
    $valorFmt = 'R$ ' . number_format($valorRec, 2, ',', '.');
    $valorExtenso = valorPorExtenso($valorRec);

    $seq = (int)getConfig('recibo_avulso_seq', 0) + 1;
    saveConfig('recibo_avulso_seq', (string)$seq);
    $reciboNumero = 'AVU-' . date('Y') . '-' . str_pad($seq, 6, '0', STR_PAD_LEFT);

    $assinaturaBloco = '';
    if ($exibirAssinatura) {
        $assinaturaBloco = '<div style="text-align:center;margin-top:40px;">
            <div style="width:250px;border-bottom:1px solid #1a1a2e;margin:0 auto 6px auto;">&nbsp;</div>
            <div style="font-size:12px;font-weight:bold;">' . htmlspecialchars($emitNome) . '</div>
        </div>';
    }

    $vars = [
        '{{empresa_logo}}' => '<img src="' . htmlspecialchars($logoAbs) . '" style="max-width:250px;">',
        '{{empresa_nome}}' => htmlspecialchars($emitNome),
        '{{empresa_cnpj}}' => htmlspecialchars($emitCnpj),
        '{{empresa_inscricao_municipal}}' => htmlspecialchars($emitInscMun),
        '{{empresa_endereco}}' => htmlspecialchars($emitEnd),
        '{{empresa_cidade}}' => htmlspecialchars($emitCidade),
        '{{empresa_estado}}' => htmlspecialchars($emitEstado),
        '{{empresa_cep}}' => htmlspecialchars($emitCep),
        '{{empresa_telefone}}' => htmlspecialchars($emitTel),
        '{{empresa_email}}' => htmlspecialchars($emitEmail),
        '{{cliente_nome}}' => $clNome,
        '{{cliente_cnpj_cpf}}' => $clDoc,
        '{{cliente_endereco}}' => $clEnd,
        '{{cliente_cidade}}' => $clCidade,
        '{{cliente_estado}}' => $clEstado,
        '{{cliente_cep}}' => $clCep,
        '{{cliente_telefone}}' => $clTel,
        '{{cliente_email}}' => $clEmail,
        '{{fatura_numero}}' => '&#8212;',
        '{{recibo_numero}}' => htmlspecialchars($reciboNumero),
        '{{valor_recebido}}' => $valorFmt,
        '{{valor_extenso}}' => $valorExtenso,
        '{{descricao_servico}}' => $descServico,
        '{{data_emissao}}' => $dataEmissaoFmt,
        '{{cidade_emissao}}' => $cidadeEmissao,
        '{{assinatura_bloco}}' => $assinaturaBloco,
    ];

    $reciboHtml = strtr(getTemplateReciboHtml(), $vars);
    $reciboNumeroGerado = $reciboNumero;
    $mensagem = 'Recibo ' . $reciboNumero . ' gerado com sucesso!';
    $tipo = 'success';
}

$pageTitle = 'Recibo Avulso';
$pageCategory = 'Documentos';
include __DIR__ . '/../includes_v2/header.php';
include __DIR__ . '/../includes_v2/sidebar_admin.php';
?>

<div class="container-fluid p-0">
    <!-- Top Action Bar -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h2 class="h4 fw-bold text-slate-900 mb-1" style="color:var(--fintech-primary);letter-spacing:-0.02em;">Recibo Avulso</h2>
            <p class="text-muted small mb-0">Emita recibos avulsos com formatação executiva para comprovação instantânea de pagamentos.</p>
        </div>
        <?php if (!empty($reciboHtml)): ?>
            <button class="btn btn-fintech-primary" data-bs-toggle="modal" data-bs-target="#modalReciboGerado">
                <i class="bi bi-printer me-1"></i> Visualizar Recibo Gerado
            </button>
        <?php endif; ?>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert alert-<?= $tipo === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show d-flex align-items-center gap-2 mb-4">
            <i class="bi bi-check-circle-fill"></i>
            <div><?= htmlspecialchars($mensagem) ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="acao" value="gerar">
        <div class="row g-4">
            <!-- Dados do Emitente -->
            <div class="col-lg-5">
                <div class="fintech-card h-100 p-4">
                    <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                        <i class="bi bi-building fs-5 text-primary"></i>
                        <div>
                            <h3 class="fintech-card-title mb-0">Dados do Emitente</h3>
                            <small class="text-muted">Configurado em Meu Perfil / Empresa</small>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label text-muted small fw-semibold">Razão Social / Nome Fantasia</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($emitNome) ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold">CNPJ / CPF</label>
                            <input type="text" class="form-control font-monospace" value="<?= htmlspecialchars($emitCnpj) ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold">Inscrição Municipal</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($emitInscMun) ?>" disabled>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small fw-semibold">Endereço Comercial</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($emitEnd) ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold">Cidade</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($emitCidade) ?>" disabled>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small fw-semibold">UF</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($emitEstado) ?>" disabled>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small fw-semibold">CEP</label>
                            <input type="text" class="form-control font-monospace" value="<?= htmlspecialchars($emitCep) ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold">Telefone</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($emitTel) ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold">E-mail</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($emitEmail) ?>" disabled>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dados do Tomador & Serviço -->
            <div class="col-lg-7">
                <div class="fintech-card p-4 mb-4">
                    <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                        <i class="bi bi-person-check fs-5 text-primary"></i>
                        <div>
                            <h3 class="fintech-card-title mb-0">Dados do Tomador (Cliente)</h3>
                            <small class="text-muted">Informe quem está efetuando o pagamento</small>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label text-muted small fw-semibold">Nome Completo / Razão Social *</label>
                            <input type="text" name="cliente_nome" class="form-control" required value="<?= htmlspecialchars($_POST['cliente_nome'] ?? '') ?>" placeholder="Nome do cliente">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small fw-semibold">CPF / CNPJ</label>
                            <input type="text" name="cliente_doc" class="form-control font-monospace" maxlength="18" value="<?= htmlspecialchars($_POST['cliente_doc'] ?? '') ?>" placeholder="000.000.000-00">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold">Logradouro / Rua</label>
                            <input type="text" name="cliente_logradouro" class="form-control" value="<?= htmlspecialchars($_POST['cliente_logradouro'] ?? '') ?>" placeholder="Ex: Av. Paulista">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small fw-semibold">Número</label>
                            <input type="text" name="cliente_numero" class="form-control" value="<?= htmlspecialchars($_POST['cliente_numero'] ?? '') ?>" placeholder="100">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small fw-semibold">Complemento</label>
                            <input type="text" name="cliente_complemento" class="form-control" value="<?= htmlspecialchars($_POST['cliente_complemento'] ?? '') ?>" placeholder="Sala 12">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold">Cidade</label>
                            <input type="text" name="cliente_cidade" class="form-control" value="<?= htmlspecialchars($_POST['cliente_cidade'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small fw-semibold">UF</label>
                            <input type="text" name="cliente_estado" class="form-control" maxlength="2" value="<?= htmlspecialchars($_POST['cliente_estado'] ?? '') ?>" placeholder="SP">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small fw-semibold">CEP</label>
                            <input type="text" name="cliente_cep" class="form-control font-monospace" maxlength="9" value="<?= htmlspecialchars($_POST['cliente_cep'] ?? '') ?>" placeholder="00000-000">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold">WhatsApp / Telefone</label>
                            <input type="text" name="cliente_telefone" class="form-control" value="<?= htmlspecialchars($_POST['cliente_telefone'] ?? '') ?>" placeholder="(00) 00000-0000">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold">E-mail</label>
                            <input type="email" name="cliente_email" class="form-control" value="<?= htmlspecialchars($_POST['cliente_email'] ?? '') ?>" placeholder="cliente@email.com">
                        </div>
                    </div>
                </div>

                <div class="fintech-card p-4">
                    <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                        <i class="bi bi-briefcase fs-5 text-primary"></i>
                        <div>
                            <h3 class="fintech-card-title mb-0">Serviço & Detalhes Financeiros</h3>
                            <small class="text-muted">Valores e especificação do recibo</small>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label text-muted small fw-semibold">Descrição do Serviço Prestado *</label>
                            <textarea name="descricao_servico" class="form-control" rows="3" required placeholder="Descreva os serviços prestados em detalhes..."><?= htmlspecialchars($_POST['descricao_servico'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold">Valor Recebido (R$) *</label>
                            <div class="input-group">
                                <span class="input-group-text fw-bold">R$</span>
                                <input type="text" name="valor" id="valorRecibo" class="form-control text-end fw-bold fs-5" required inputmode="decimal" placeholder="0,00" value="<?= htmlspecialchars($_POST['valor'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small fw-semibold">Data da Emissão</label>
                            <input type="date" name="data_emissao" class="form-control" value="<?= htmlspecialchars($_POST['data_emissao'] ?? date('Y-m-d')) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small fw-semibold">Cidade Emissão</label>
                            <input type="text" name="cidade_emissao" class="form-control" value="<?= htmlspecialchars($_POST['cidade_emissao'] ?? $cidadeEmissaoPadrao) ?>">
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                        <button type="submit" class="btn btn-fintech-primary px-4 py-2">
                            <i class="bi bi-file-earmark-check me-2"></i> Emitir Recibo Avulso
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Modal com o Recibo Gerado para Impressão -->
<?php if (!empty($reciboHtml)): ?>
<div class="modal fade" id="modalReciboGerado" tabindex="-1" aria-labelledby="modalReciboLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px; overflow:hidden;">
            <div class="modal-header bg-slate-900 text-white" style="background:var(--fintech-primary); color:#fff;">
                <h5 class="modal-title fw-bold" id="modalReciboLabel">
                    <i class="bi bi-file-earmark-text me-2"></i>Recibo Nº <?= htmlspecialchars($reciboNumeroGerado) ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light" id="areaImpressaoRecibo">
                <div class="bg-white p-4 shadow-sm border" style="border-radius:12px; font-family:'Plus Jakarta Sans', sans-serif;">
                    <?= $reciboHtml ?>
                </div>
            </div>
            <div class="modal-footer bg-white border-top">
                <button type="button" class="btn btn-fintech-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-fintech-primary" onclick="imprimirRecibo()">
                    <i class="bi bi-printer me-2"></i>Imprimir / Salvar PDF
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalEl = document.getElementById('modalReciboGerado');
    if (modalEl) {
        var modal = new bootstrap.Modal(modalEl);
        modal.show();
    }
});

function imprimirRecibo() {
    var conteudo = document.getElementById('areaImpressaoRecibo').innerHTML;
    var win = window.open('', '_blank');
    win.document.write('<html><head><title>Recibo <?= htmlspecialchars($reciboNumeroGerado) ?></title>');
    win.document.write('<style>body { font-family: sans-serif; margin: 20px; } @media print { body { margin: 0; } }</style>');
    win.document.write('</head><body>');
    win.document.write(conteudo);
    win.document.write('</body></html>');
    win.document.close();
    win.focus();
    setTimeout(function() { win.print(); win.close(); }, 400);
}
</script>
<?php endif; ?>

<script>
// Máscara monetária R$ 0.000,00
const campoValor = document.getElementById('valorRecibo');
if (campoValor) {
    campoValor.addEventListener('input', function (e) {
        let v = e.target.value.replace(/\D/g, '');
        if (!v) { e.target.value = ''; return; }
        v = (parseInt(v, 10) / 100).toFixed(2) + '';
        let partes = v.split('.');
        partes[0] = partes[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        e.target.value = partes.join(',');
    });
}
</script>

<?php include __DIR__ . '/../includes_v2/footer.php'; ?>

