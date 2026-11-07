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

if (!function_exists('reciboAvulsoDoc')) {
    function reciboAvulsoDoc($v) {
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
if (!function_exists('reciboAvulsoCep')) {
    function reciboAvulsoCep($v) {
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
$emitCnpj = reciboAvulsoDoc($admin['cnpj'] ?? '');
$emitInscMun = trim($admin['inscricao_municipal'] ?? '');
$emitEnd = trim((trim($admin['logradouro'] ?? '') . ', ' . trim($admin['numero'] ?? '')), " ,");
$emitCidade = trim($admin['cidade'] ?? '');
$emitEstado = trim($admin['estado'] ?? '');
$emitCep = reciboAvulsoCep($admin['cep'] ?? '');
$emitTel = trim($admin['telefone_comercial'] ?? '');
$emitEmail = trim($admin['email_comercial'] ?? '') ?: trim($admin['email'] ?? '');

$exibirAssinatura = ($config['template_recibo_assinatura'] ?? '1') === '1';
$cidadeEmissaoPadrao = trim($config['template_recibo_cidade_emissao'] ?? '') ?: $emitCidade;
$descPadrao = trim($config['template_recibo_descricao_padrao'] ?? '') ?: 'referente à prestação de serviços conforme acordado entre as partes.';

$reciboNumeroGerado = '';
$reciboHtml = '';

$ctxRecibo = [
    'template'         => getTemplateReciboHtml(),
    'logo_abs'         => $logoAbs,
    'emit_nome'        => $emitNome,
    'emit_cnpj'        => $emitCnpj,
    'emit_insc_mun'    => $emitInscMun,
    'emit_end'         => $emitEnd,
    'emit_cidade'      => $emitCidade,
    'emit_estado'      => $emitEstado,
    'emit_cep'         => $emitCep,
    'emit_tel'         => $emitTel,
    'emit_email'       => $emitEmail,
    'exibir_assinatura'=> $exibirAssinatura,
    'cidade_padrao'    => $cidadeEmissaoPadrao,
    'desc_padrao'      => $descPadrao,
];

if (!function_exists('reciboAvulsoNormalizar')) {
    // Normaliza os campos do formulario (POST ou dados de exemplo) em um
    // array unico, usado tanto pela pre-visualizacao quanto pela geracao.
    function reciboAvulsoNormalizar(array $src, array $ctx): array
    {
        $valorBruto = trim((string)($src['valor'] ?? '0'));
        $valorBruto = str_replace('.', '', $valorBruto);
        $valorBruto = str_replace(',', '.', $valorBruto);
        $valorRec = max(0, (float)$valorBruto);

        $dataEmissao = trim((string)($src['data_emissao'] ?? ''));
        $tsEmissao = $dataEmissao !== '' ? strtotime($dataEmissao) : false;
        $dataFmt = $tsEmissao ? date('d/m/Y', $tsEmissao) : date('d/m/Y');

        $endereco = trim(
            trim((string)($src['cliente_logradouro'] ?? '')) . ', ' .
            trim((string)($src['cliente_numero'] ?? '')) . ' ' .
            trim((string)($src['cliente_complemento'] ?? '')),
            " ,"
        );

        return [
            'nome'           => trim((string)($src['cliente_nome'] ?? '')),
            'doc'            => reciboAvulsoDoc($src['cliente_doc'] ?? ''),
            'endereco'       => $endereco,
            'cidade'         => trim((string)($src['cliente_cidade'] ?? '')),
            'estado'         => trim((string)($src['cliente_estado'] ?? '')),
            'cep'            => reciboAvulsoCep($src['cliente_cep'] ?? ''),
            'telefone'       => trim((string)($src['cliente_telefone'] ?? '')),
            'email'          => trim((string)($src['cliente_email'] ?? '')),
            'descricao'      => trim((string)($src['descricao_servico'] ?? '')) ?: $ctx['desc_padrao'],
            'valor'          => $valorRec,
            'valor_fmt'      => 'R$ ' . number_format($valorRec, 2, ',', '.'),
            'valor_extenso'  => valorPorExtenso($valorRec),
            'data_fmt'       => $dataFmt,
            'cidade_emissao' => trim((string)($src['cidade_emissao'] ?? '')) ?: $ctx['cidade_padrao'],
        ];
    }
}

if (!function_exists('reciboAvulsoRender')) {
    // Aplica o template configurado em Template Recibo aos dados informados.
    function reciboAvulsoRender(array $d, array $ctx, string $numero): string
    {
        $assinaturaBloco = '';
        if (!empty($ctx['exibir_assinatura'])) {
            $assinaturaBloco = '<div style="text-align:center;margin-top:40px;">
                <div style="width:250px;border-bottom:1px solid #1a1a2e;margin:0 auto 6px auto;">&nbsp;</div>
                <div style="font-size:12px;font-weight:bold;">' . htmlspecialchars($ctx['emit_nome']) . '</div>
            </div>';
        }

        $vars = [
            '{{empresa_logo}}' => '<img src="' . htmlspecialchars($ctx['logo_abs']) . '" style="max-width:250px;">',
            '{{empresa_nome}}' => htmlspecialchars($ctx['emit_nome']),
            '{{empresa_cnpj}}' => htmlspecialchars($ctx['emit_cnpj']),
            '{{empresa_inscricao_municipal}}' => htmlspecialchars($ctx['emit_insc_mun']),
            '{{empresa_endereco}}' => htmlspecialchars($ctx['emit_end']),
            '{{empresa_cidade}}' => htmlspecialchars($ctx['emit_cidade']),
            '{{empresa_estado}}' => htmlspecialchars($ctx['emit_estado']),
            '{{empresa_cep}}' => htmlspecialchars($ctx['emit_cep']),
            '{{empresa_telefone}}' => htmlspecialchars($ctx['emit_tel']),
            '{{empresa_email}}' => htmlspecialchars($ctx['emit_email']),
            '{{cliente_nome}}' => htmlspecialchars($d['nome']),
            '{{cliente_cnpj_cpf}}' => htmlspecialchars($d['doc']),
            '{{cliente_endereco}}' => htmlspecialchars($d['endereco']),
            '{{cliente_cidade}}' => htmlspecialchars($d['cidade']),
            '{{cliente_estado}}' => htmlspecialchars($d['estado']),
            '{{cliente_cep}}' => htmlspecialchars($d['cep']),
            '{{cliente_telefone}}' => htmlspecialchars($d['telefone']),
            '{{cliente_email}}' => htmlspecialchars($d['email']),
            '{{fatura_numero}}' => '&#8212;',
            '{{recibo_numero}}' => htmlspecialchars($numero),
            '{{valor_recebido}}' => $d['valor_fmt'],
            '{{valor_extenso}}' => $d['valor_extenso'],
            '{{descricao_servico}}' => htmlspecialchars($d['descricao']),
            '{{data_emissao}}' => $d['data_fmt'],
            '{{cidade_emissao}}' => htmlspecialchars($d['cidade_emissao']),
            '{{assinatura_bloco}}' => $assinaturaBloco,
        ];

        return strtr($ctx['template'], $vars);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'preview') {
    $htmlPreview = reciboAvulsoRender(
        reciboAvulsoNormalizar($_POST, $ctxRecibo),
        $ctxRecibo,
        'PRÉVIA'
    );
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['html' => $htmlPreview], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $previewHtml = $htmlPreview;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'gerar') {
    $dadosRecibo = reciboAvulsoNormalizar($_POST, $ctxRecibo);

    $seq = (int)getConfig('recibo_avulso_seq', 0) + 1;
    saveConfig('recibo_avulso_seq', (string)$seq);
    $reciboNumero = 'AVU-' . date('Y') . '-' . str_pad($seq, 6, '0', STR_PAD_LEFT);

    $reciboHtml = reciboAvulsoRender($dadosRecibo, $ctxRecibo, $reciboNumero);
    $reciboNumeroGerado = $reciboNumero;
    $mensagem = 'Recibo ' . $reciboNumero . ' gerado com sucesso!';
    $tipo = 'success';
}

if (!isset($previewHtml) || $previewHtml === null) {
    $dadosPreview = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : [
        'cliente_nome'        => 'João da Silva',
        'cliente_doc'         => '123.456.789-00',
        'cliente_logradouro'  => 'Av. Cliente',
        'cliente_numero'      => '456',
        'cliente_complemento' => '',
        'cliente_cidade'      => 'Rio de Janeiro',
        'cliente_estado'      => 'RJ',
        'cliente_cep'         => '22000-000',
        'cliente_telefone'    => '(21) 0000-0000',
        'cliente_email'       => 'cliente@email.com',
        'descricao_servico'   => $descPadrao,
        'valor'               => '1.500,00',
        'data_emissao'        => date('Y-m-d'),
        'cidade_emissao'      => $cidadeEmissaoPadrao,
    ];
    $previewHtml = reciboAvulsoRender(
        reciboAvulsoNormalizar($dadosPreview, $ctxRecibo),
        $ctxRecibo,
        'PRÉVIA'
    );
}

$pageTitle = 'Recibo Avulso';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5><i class="fas fa-file-invoice me-2"></i>Recibo Avulso</h5>
        </div>
        <a href="https://wa.me/5591982675573" target="_blank" class="btn btn-light btn-sm ms-auto me-2" style="font-size:0.8rem;border:1px solid #dee2e6;"><i class="fas fa-headset"></i> Suporte</a>
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                <img src="<?= htmlspecialchars(function_exists('getAdminAvatarUrl') ? getAdminAvatarUrl() : ($_SESSION['admin_avatar'] ?? '/cobranca/assets/img/avatars/admin.svg')) ?>" alt="Avatar" class="rounded-circle me-2" width="32" height="32" style="object-fit:cover;" onerror="this.onerror=null;this.src='/cobranca/assets/img/avatars/admin.svg';">
                <span class="text-muted d-none d-md-inline"><?= htmlspecialchars($_SESSION['admin_nome']) ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="/cobranca/admin/perfil.php"><i class="fas fa-user-edit me-2"></i>Editar Perfil</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="/cobranca/admin/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Sair</a></li>
            </ul>
        </div>
    </div>

    <div class="content-area fade-in">
        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $tipo ?> alert-dismissible fade show">
                <?= $mensagem ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="acao" value="gerar">
            <div class="row g-4">

                <div class="col-lg-6">
                    <div class="form-card h-100">
                        <h6 class="mb-3"><i class="fas fa-building me-2"></i>Emitente <small class="text-muted" style="font-weight:400;font-size:0.75rem;">(padrão - Meu Perfil / Dados da Empresa)</small></h6>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Nome</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($emitNome) ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">CNPJ</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($emitCnpj) ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Inscrição Municipal</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($emitInscMun) ?>" disabled>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Endereço</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($emitEnd) ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Cidade</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($emitCidade) ?>" disabled>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">UF</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($emitEstado) ?>" disabled>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">CEP</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($emitCep) ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Telefone</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($emitTel) ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">E-mail</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($emitEmail) ?>" disabled>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="form-card mb-3">
                        <h6 class="mb-3"><i class="fas fa-user me-2"></i>Tomador <small class="text-muted" style="font-weight:400;font-size:0.75rem;">(dados do cliente do recibo)</small></h6>
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Nome / Razão Social</label>
                                <input type="text" name="cliente_nome" class="form-control" value="<?= htmlspecialchars($_POST['cliente_nome'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">CPF / CNPJ</label>
                                <input type="text" name="cliente_doc" class="form-control" maxlength="18" value="<?= htmlspecialchars($_POST['cliente_doc'] ?? '') ?>" placeholder="000.000.000-00">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Logradouro</label>
                                <input type="text" name="cliente_logradouro" class="form-control" value="<?= htmlspecialchars($_POST['cliente_logradouro'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Nº</label>
                                <input type="text" name="cliente_numero" class="form-control" value="<?= htmlspecialchars($_POST['cliente_numero'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Complemento</label>
                                <input type="text" name="cliente_complemento" class="form-control" value="<?= htmlspecialchars($_POST['cliente_complemento'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Cidade</label>
                                <input type="text" name="cliente_cidade" class="form-control" value="<?= htmlspecialchars($_POST['cliente_cidade'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">UF</label>
                                <input type="text" name="cliente_estado" class="form-control" maxlength="2" value="<?= htmlspecialchars($_POST['cliente_estado'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">CEP</label>
                                <input type="text" name="cliente_cep" class="form-control" maxlength="9" value="<?= htmlspecialchars($_POST['cliente_cep'] ?? '') ?>" placeholder="00000-000">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Telefone</label>
                                <input type="text" name="cliente_telefone" class="form-control" value="<?= htmlspecialchars($_POST['cliente_telefone'] ?? '') ?>">
                            </div>
                            <div class="col-md-7">
                                <label class="form-label">E-mail</label>
                                <input type="text" name="cliente_email" class="form-control" value="<?= htmlspecialchars($_POST['cliente_email'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-card">
                        <h6 class="mb-3"><i class="fas fa-wrench me-2"></i>Serviço Prestado</h6>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Descrição do Serviço</label>
                                <textarea name="descricao_servico" class="form-control" rows="3" placeholder="Descreva o serviço prestado..."><?= htmlspecialchars($_POST['descricao_servico'] ?? '') ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Valor do Serviço (R$)</label>
                                <input type="text" name="valor" class="form-control text-end" inputmode="decimal" oninput="mascaraMoedaAvulso(this)" value="<?= htmlspecialchars($_POST['valor'] ?? '') ?>" placeholder="0,00">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Data de Emissão</label>
                                <input type="date" name="data_emissao" class="form-control" value="<?= htmlspecialchars($_POST['data_emissao'] ?? date('Y-m-d')) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Cidade de Emissão</label>
                                <input type="text" name="cidade_emissao" class="form-control" value="<?= htmlspecialchars($_POST['cidade_emissao'] ?? $cidadeEmissaoPadrao) ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 text-center mb-4">
                    <button type="submit" class="btn btn-primary btn-lg px-5">
                        <i class="bi bi-file-earmark-text me-2"></i> Gerar Recibo
                    </button>
                </div>

            </div>
        </form>

        <div class="form-card mt-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h6 class="mb-0"><i class="fas fa-eye me-2"></i>Pré-visualização do Recibo (A4)</h6>
                <button type="button" class="btn btn-outline-info btn-sm" onclick="atualizarPreviewAvulso(this)">
                    <i class="fas fa-sync me-1"></i> Atualizar pré-visualização
                </button>
            </div>
            <div id="previewWrapperAvulso" style="border:1px solid #dee2e6;border-radius:8px;overflow:auto;background:#e9ecef;padding:20px;max-height:800px;">
                <div id="previewAvulsoA4" style="width:794px;min-height:1123px;background:#fff;box-shadow:0 2px 12px rgba(0,0,0,0.15);margin:0 auto;">
                    <?= $previewHtml ?>
                </div>
            </div>
            <div class="text-muted mt-2" style="font-size:0.75rem;">
                A pré-visualização usa o mesmo template configurado em
                <a href="/cobranca/admin/template_recibo.php">Template Recibo</a>
                e é atualizada com os dados preenchidos no formulário.
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalReciboAvulso" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-file-invoice text-success me-2"></i>Recibo <?= htmlspecialchars($reciboNumeroGerado) ?> gerado!</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div style="border:1px solid #dee2e6;border-radius:8px;overflow:auto;background:#e9ecef;padding:20px;max-height:70vh;">
                    <div id="reciboAvulsoA4" style="width:794px;min-height:1123px;background:#fff;box-shadow:0 2px 12px rgba(0,0,0,0.15);margin:0 auto;">
                    </div>
                </div>
                <div style="text-align:center;margin-top:10px;font-size:0.75rem;color:#64748b;">
                    Para salvar o PDF, clique em "Baixar PDF" e escolha "Salvar como PDF".
                </div>
            </div>
            <div class="modal-footer">
                <a href="/cobranca/admin/recibo_avulso.php" class="btn btn-outline-secondary"><i class="fas fa-plus me-1"></i> Novo Recibo</a>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><i class="fas fa-close me-1"></i> Fechar</button>
                <button type="button" class="btn btn-success" onclick="imprimirReciboAvulso()"><i class="fas fa-print me-1"></i> Baixar PDF</button>
            </div>
        </div>
    </div>
</div>

<script>
function mascaraMoedaAvulso(el) {
    var d = el.value.replace(/\D/g, '');
    while (d.length < 3) d = '0' + d;
    el.value = (parseInt(d.slice(0, -2), 10) || 0).toLocaleString('pt-BR') + ',' + d.slice(-2);
}

function atualizarPreviewAvulso(btn) {
    var form = document.querySelector('form');
    var alvo = document.getElementById('previewAvulsoA4');
    if (!form || !alvo) return;

    var rotuloOriginal = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Atualizando...';
    }

    var fd = new FormData(form);
    fd.set('acao', 'preview');

    fetch('/cobranca/admin/recibo_avulso.php', {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function(resp) { return resp.json(); })
    .then(function(data) {
        if (data && data.html) {
            alvo.innerHTML = data.html;
        }
    })
    .catch(function(err) {
        console.error('Falha ao atualizar a pré-visualização:', err);
        alert('Não foi possível atualizar a pré-visualização. Tente novamente.');
    })
    .finally(function() {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = rotuloOriginal;
        }
    });
}

function imprimirReciboAvulso() {
    var content = document.getElementById('reciboAvulsoA4').innerHTML;
    var win = window.open('', '_blank');
    win.document.write('<!DOCTYPE html><html><head><title>Recibo</title>');
    win.document.write('<link href="/cobranca/assets/vendor/fonts/fonts.css" rel="stylesheet">');
    win.document.write('<style>@page{size:A4;margin:15mm;}body{margin:0;padding:0;font-family:"Inter","Segoe UI",Arial,sans-serif;}@media print{.no-print{display:none!important;}}</style>');
    win.document.write('</head><body>');
    win.document.write(content);
    win.document.write('</body></html>');
    win.document.close();
    setTimeout(function() { win.print(); }, 500);
}
</script>

<?php if ($reciboHtml): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('reciboAvulsoA4').innerHTML = <?= json_encode($reciboHtml) ?>;
    new bootstrap.Modal(document.getElementById('modalReciboAvulso')).show();
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>