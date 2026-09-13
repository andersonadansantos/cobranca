<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
requirePlanoAtivo();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/recibo_template.php';

$pdo = getConnection();
$adminId = (int)$_SESSION['admin_id'];
$mensagem = '';
$tipo = '';

$config = getAllConfig();

$defaultHtml = reciboTemplatePadrao();

$tituloRecibo = $config['template_recibo_titulo'] ?? 'RECIBO DE PRESTAÇÃO DE SERVIÇOS';
$htmlTemplate = $config['template_recibo_html'] ?? '';
$exibirAssinatura = ($config['template_recibo_assinatura'] ?? '1') === '1';
$descPadrao = $config['template_recibo_descricao_padrao'] ?? 'referente à prestação de serviços conforme acordado entre as partes.';
$cidadeEmissao = $config['template_recibo_cidade_emissao'] ?? '';
if (empty($htmlTemplate)) $htmlTemplate = $defaultHtml;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'salvar') {
        $tituloRecibo = trim($_POST['titulo'] ?? $tituloRecibo);
        $htmlTemplate = $_POST['template_html'] ?? $htmlTemplate;
        $exibirAssinatura = ($_POST['exibir_assinatura'] ?? '1') === '1';
        $descPadrao = trim($_POST['descricao_padrao'] ?? $descPadrao);
        $cidadeEmissao = trim($_POST['cidade_emissao'] ?? $cidadeEmissao);
        saveConfig('template_recibo_titulo', $tituloRecibo);
        saveConfig('template_recibo_html', $htmlTemplate);
        saveConfig('template_recibo_assinatura', $exibirAssinatura ? '1' : '0');
        saveConfig('template_recibo_descricao_padrao', $descPadrao);
        saveConfig('template_recibo_cidade_emissao', $cidadeEmissao);
        $mensagem = 'Template do recibo salvo com sucesso!';
        $tipo = 'success';
    }

    if ($acao === 'restaurar') {
        saveConfig('template_recibo_html', '');
        saveConfig('template_recibo_titulo', 'RECIBO DE PRESTAÇÃO DE SERVIÇOS');
        saveConfig('template_recibo_assinatura', '1');
        saveConfig('template_recibo_descricao_padrao', 'referente à prestação de serviços conforme acordado entre as partes.');
        saveConfig('template_recibo_cidade_emissao', '');
        $mensagem = 'Template restaurado para o padrão!';
        $tipo = 'info';
        $htmlTemplate = $defaultHtml;
        $tituloRecibo = 'RECIBO DE PRESTAÇÃO DE SERVIÇOS';
        $exibirAssinatura = true;
        $descPadrao = 'referente à prestação de serviços conforme acordado entre as partes.';
        $cidadeEmissao = '';
    }
}

$logoUrl = getConfig('logo_empresa_admin', '');
if (empty($logoUrl) || !logoPathValido($logoUrl)) {
    $logoUrl = getLogoLogin();
}
if (empty($logoUrl) || !logoPathValido($logoUrl)) {
    $logoUrl = '/cobranca/assets/img/logo_color.png';
}

$admin = [];
if (!empty($adminId)) {
    $stAdmin = $pdo->prepare("SELECT * FROM administradores WHERE id = ?");
    $stAdmin->execute([$adminId]);
    $admin = $stAdmin->fetch() ?: [];
}

if (!function_exists('reciboPreviewCnpj')) {
    function reciboPreviewCnpj($v) {
        $v = preg_replace('/[^0-9]/', '', (string)$v);
        if (strlen($v) === 14) {
            return substr($v,0,2) . '.' . substr($v,2,3) . '.' . substr($v,5,3) . '/' . substr($v,8,4) . '-' . substr($v,12,2);
        }
        return $v ?: '00.000.000/0001-00';
    }
}
if (!function_exists('reciboPreviewCep')) {
    function reciboPreviewCep($v) {
        $v = preg_replace('/[^0-9]/', '', (string)$v);
        if (strlen($v) === 8) {
            return substr($v,0,5) . '-' . substr($v,5,3);
        }
        return $v ?: '00000-000';
    }
}

$empresaNome = trim($admin['nome_fantasia'] ?? '') ?: (trim($admin['razao_social'] ?? '') ?: getNomeSistema());
$empresaCnpj = reciboPreviewCnpj($admin['cnpj'] ?? '');
$empresaInscMun = trim($admin['inscricao_municipal'] ?? '') ?: '000.000';
$empresaEnd = trim((trim($admin['logradouro'] ?? '') . ', ' . trim($admin['numero'] ?? '')), " ,");
if ($empresaEnd === '') $empresaEnd = 'Rua Exemplo, 123';
$empresaCidade = trim($admin['cidade'] ?? '') ?: 'São Paulo';
$empresaEstado = trim($admin['estado'] ?? '') ?: 'SP';
$empresaCep = reciboPreviewCep($admin['cep'] ?? '');
$empresaTel = trim($admin['telefone_comercial'] ?? '') ?: '(11) 0000-0000';
$empresaEmail = trim($admin['email_comercial'] ?? '') ?: (trim($admin['email'] ?? '') ?: 'contato@empresa.com');

$previewHtml = str_replace('{{empresa_logo}}', '<img src="' . htmlspecialchars($logoUrl) . '" style="max-width:250px;">', $htmlTemplate);
$previewHtml = str_replace('{{empresa_nome}}', htmlspecialchars($empresaNome), $previewHtml);
$previewHtml = str_replace('{{empresa_cnpj}}', htmlspecialchars($empresaCnpj), $previewHtml);
$previewHtml = str_replace('{{empresa_inscricao_municipal}}', htmlspecialchars($empresaInscMun), $previewHtml);
$previewHtml = str_replace('{{empresa_endereco}}', htmlspecialchars($empresaEnd), $previewHtml);
$previewHtml = str_replace('{{empresa_cidade}}', htmlspecialchars($empresaCidade), $previewHtml);
$previewHtml = str_replace('{{empresa_estado}}', htmlspecialchars($empresaEstado), $previewHtml);
$previewHtml = str_replace('{{empresa_cep}}', htmlspecialchars($empresaCep), $previewHtml);
$previewHtml = str_replace('{{empresa_telefone}}', htmlspecialchars($empresaTel), $previewHtml);
$previewHtml = str_replace('{{empresa_email}}', htmlspecialchars($empresaEmail), $previewHtml);
$previewHtml = str_replace('{{cliente_nome}}', 'João da Silva', $previewHtml);
$previewHtml = str_replace('{{cliente_cnpj_cpf}}', '123.456.789-00', $previewHtml);
$previewHtml = str_replace('{{cliente_endereco}}', 'Av. Cliente, 456', $previewHtml);
$previewHtml = str_replace('{{cliente_cidade}}', 'Rio de Janeiro', $previewHtml);
$previewHtml = str_replace('{{cliente_estado}}', 'RJ', $previewHtml);
$previewHtml = str_replace('{{cliente_cep}}', '22000-000', $previewHtml);
$previewHtml = str_replace('{{cliente_telefone}}', '(21) 0000-0000', $previewHtml);
$previewHtml = str_replace('{{cliente_email}}', 'cliente@email.com', $previewHtml);
$previewHtml = str_replace('{{fatura_numero}}', 'FAT-202609-0001', $previewHtml);
$previewHtml = str_replace('{{recibo_numero}}', 'REC-2026-000125', $previewHtml);
$previewHtml = str_replace('{{valor_recebido}}', 'R$ 1.500,00', $previewHtml);
$previewHtml = str_replace('{{valor_extenso}}', 'Mil e quinhentos reais', $previewHtml);
$previewHtml = str_replace('{{descricao_servico}}', htmlspecialchars($descPadrao), $previewHtml);
$previewHtml = str_replace('{{data_emissao}}', date('d/m/Y'), $previewHtml);
$previewHtml = str_replace('{{cidade_emissao}}', htmlspecialchars($cidadeEmissao ?: 'São Paulo'), $previewHtml);

$assinaturaBloco = '';
if ($exibirAssinatura) {
    $assinaturaBloco = '<div style="text-align:center;margin-top:20px;">
        <div style="width:250px;border-bottom:1px solid #1a1a2e;margin:0 auto 6px auto;">&nbsp;</div>
        <div style="font-size:12px;font-weight:bold;">' . htmlspecialchars($empresaNome) . '</div>
    </div>';
}
$previewHtml = str_replace('{{assinatura_bloco}}', $assinaturaBloco, $previewHtml);

$pageTitle = 'Template Recibo';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<link href="/cobranca/assets/vendor/summernote/summernote-bs5.min.css" rel="stylesheet">

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5><i class="fas fa-file-invoice me-2"></i>Template Recibo</h5>
        </div>
        <a href="https://wa.me/5591982675573" target="_blank" class="btn btn-light btn-sm ms-auto me-2" style="font-size:0.8rem;border:1px solid #dee2e6;"><i class="fas fa-headset"></i> Suporte</a>
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                <img src="<?= htmlspecialchars($_SESSION['admin_avatar'] ?? '/cobranca/assets/img/avatars/admin.svg') ?>" alt="Avatar" class="rounded-circle me-2" width="32" height="32" style="object-fit:cover;">
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

        <form method="POST" id="formTemplate">
            <input type="hidden" name="acao" value="salvar">
            <div class="row g-4">

                <div class="col-lg-5">
                    <div class="form-card mb-3">
                        <h6 class="mb-3"><i class="fas fa-cog me-2"></i>Configurações do Recibo</h6>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Título do Recibo</label>
                                <input type="text" name="titulo" class="form-control" value="<?= htmlspecialchars($tituloRecibo) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Descrição Padrão do Serviço</label>
                                <textarea name="descricao_padrao" class="form-control" rows="2"><?= htmlspecialchars($descPadrao) ?></textarea>
                                <small class="text-muted">Texto preenchido automaticamente no campo {{descricao_servico}}.</small>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Cidade de Emissão</label>
                                <input type="text" name="cidade_emissao" class="form-control" value="<?= htmlspecialchars($cidadeEmissao) ?>" placeholder="Ex: São Paulo">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Assinatura</label>
                                <select name="exibir_assinatura" class="form-select">
                                    <option value="1" <?= $exibirAssinatura ? 'selected' : '' ?>>Sim</option>
                                    <option value="0" <?= !$exibirAssinatura ? 'selected' : '' ?>>Não</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-card mb-3">
                        <h6 class="mb-3"><i class="fas fa-code me-2"></i>HTML do Template</h6>
                        <textarea name="template_html" id="templateHtml" class="form-control" rows="12" style="font-family:monospace;font-size:0.78rem;"><?= htmlspecialchars($htmlTemplate) ?></textarea>
                        <small class="text-muted">Edite o HTML diretamente ou use o editor visual abaixo.</small>
                    </div>

                    <div class="form-card mb-3">
                        <h6 class="mb-3"><i class="fas fa-puzzle-piece me-2"></i>Variáveis Disponíveis</h6>
                        <div class="accordion" id="varsAccordion">
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#varsEmpresa">
                                        <i class="fas fa-building me-2"></i>Empresa
                                    </button>
                                </h2>
                                <div id="varsEmpresa" class="accordion-collapse collapse" data-bs-parent="#varsAccordion">
                                    <div class="accordion-body py-2" style="font-size:0.78rem;">
                                        <?php
                                        $varsEmpresa = [
                                            'empresa_nome', 'empresa_cnpj', 'empresa_inscricao_municipal',
                                            'empresa_endereco', 'empresa_cidade', 'empresa_estado',
                                            'empresa_cep', 'empresa_telefone', 'empresa_email', 'empresa_logo'
                                        ];
                                        foreach ($varsEmpresa as $v): ?>
                                            <button type="button" class="btn btn-outline-secondary btn-sm me-1 mb-1 copy-var" data-var="<?= $v ?>"><?= $v ?></button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#varsCliente">
                                        <i class="fas fa-user me-2"></i>Cliente
                                    </button>
                                </h2>
                                <div id="varsCliente" class="accordion-collapse collapse" data-bs-parent="#varsAccordion">
                                    <div class="accordion-body py-2" style="font-size:0.78rem;">
                                        <?php
                                        $varsCliente = [
                                            'cliente_nome', 'cliente_cnpj_cpf', 'cliente_endereco',
                                            'cliente_cidade', 'cliente_estado', 'cliente_cep',
                                            'cliente_telefone', 'cliente_email'
                                        ];
                                        foreach ($varsCliente as $v): ?>
                                            <button type="button" class="btn btn-outline-secondary btn-sm me-1 mb-1 copy-var" data-var="<?= $v ?>"><?= $v ?></button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#varsFatura">
                                        <i class="fas fa-file-invoice-dollar me-2"></i>Fatura/Pagamento
                                    </button>
                                </h2>
                                <div id="varsFatura" class="accordion-collapse collapse" data-bs-parent="#varsAccordion">
                                    <div class="accordion-body py-2" style="font-size:0.78rem;">
                                        <?php
                                        $varsFatura = [
                                            'fatura_numero', 'recibo_numero', 'valor_recebido',
                                            'valor_extenso', 'descricao_servico', 'data_emissao', 'cidade_emissao'
                                        ];
                                        foreach ($varsFatura as $v): ?>
                                            <button type="button" class="btn btn-outline-secondary btn-sm me-1 mb-1 copy-var" data-var="<?= $v ?>"><?= $v ?></button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#varsAssinatura">
                                        <i class="fas fa-signature me-2"></i>Assinatura
                                    </button>
                                </h2>
                                <div id="varsAssinatura" class="accordion-collapse collapse" data-bs-parent="#varsAccordion">
                                    <div class="accordion-body py-2" style="font-size:0.78rem;">
                                        <button type="button" class="btn btn-outline-secondary btn-sm me-1 mb-1 copy-var" data-var="assinatura_empresa">assinatura_empresa</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <small class="text-muted d-block mt-2">Clique para copiar. Cole no template com <code>{{variavel}}</code>.</small>
                    </div>

                    <div class="d-flex gap-2 mb-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Salvar Template
                        </button>
                        <button type="submit" class="btn btn-outline-secondary" onclick="document.querySelector('[name=acao]').value='restaurar'">
                            <i class="fas fa-undo me-1"></i> Restaurar Padrão
                        </button>
                        <button type="button" class="btn btn-outline-info ms-auto" onclick="atualizarPreview()">
                            <i class="fas fa-sync me-1"></i> Atualizar Prévia
                        </button>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="form-card" style="position:sticky;top:20px;">
                        <h6 class="mb-3"><i class="fas fa-eye me-2"></i>Pré-visualização (A4)</h6>
                        <div id="previewWrapper" style="border:1px solid #dee2e6;border-radius:8px;overflow:auto;background:#e9ecef;padding:20px;max-height:700px;">
                            <div id="previewA4" style="width:794px;min-height:1123px;background:#fff;box-shadow:0 2px 12px rgba(0,0,0,0.15);margin:0 auto;transform-origin:top center;">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="/cobranca/assets/vendor/jquery/jquery-3.7.1.min.js"></script>
<script src="/cobranca/assets/vendor/summernote/summernote-bs5.min.js"></script>
<script>
var currentHtml = <?= json_encode($htmlTemplate) ?>;
var previewVars = <?= json_encode([
    'empresa_logo' => '<img src="' . htmlspecialchars($logoUrl) . '" style="max-width:250px;">',
    'empresa_nome' => $empresaNome,
    'empresa_cnpj' => $empresaCnpj,
    'empresa_inscricao_municipal' => $empresaInscMun,
    'empresa_endereco' => $empresaEnd,
    'empresa_cidade' => $empresaCidade,
    'empresa_estado' => $empresaEstado,
    'empresa_cep' => $empresaCep,
    'empresa_telefone' => $empresaTel,
    'empresa_email' => $empresaEmail,
    'cliente_nome' => 'João da Silva',
    'cliente_cnpj_cpf' => '123.456.789-00',
    'cliente_endereco' => 'Av. Cliente, 456',
    'cliente_cidade' => 'Rio de Janeiro',
    'cliente_estado' => 'RJ',
    'cliente_cep' => '22000-000',
    'cliente_telefone' => '(21) 0000-0000',
    'cliente_email' => 'cliente@email.com',
    'fatura_numero' => 'FAT-202609-0001',
    'recibo_numero' => 'REC-2026-000125',
    'valor_recebido' => 'R$ 1.500,00',
    'valor_extenso' => 'Mil e quinhentos reais',
    'descricao_servico' => htmlspecialchars($descPadrao),
    'data_emissao' => date('d/m/Y'),
    'cidade_emissao' => htmlspecialchars($cidadeEmissao ?: 'São Paulo'),
    'assinatura_bloco' => $exibirAssinatura ? '<div style="text-align:center;margin-top:20px;"><div style="width:250px;border-bottom:1px solid #1a1a2e;margin:0 auto 6px auto;">&nbsp;</div><div style="font-size:12px;font-weight:bold;">' . htmlspecialchars($empresaNome) . '</div></div>' : '',
    'assinatura_empresa' => '<img src="' . htmlspecialchars($logoUrl) . '" style="max-height:50px;">'
]) ?>;

$(document).ready(function() {
    $('#templateHtml').summernote({
        height: 250,
        placeholder: 'Cole o HTML do template aqui...',
        toolbar: [
            ['style', ['style']],
            ['font', ['bold', 'italic', 'underline', 'strikethrough']],
            ['fontname', ['fontname']],
            ['fontsize', ['fontsize']],
            ['color', ['color']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['table', ['table']],
            ['insert', ['link', 'hr']],
            ['view', ['fullscreen', 'codeview']]
        ],
        callbacks: {
            onChange: function(contents) {
                document.getElementById('templateHtml').value = contents;
            }
        }
    });

    atualizarPreview();

    $('.copy-var').on('click', function() {
        var variavel = '{{' + $(this).data('var') + '}}';
        navigator.clipboard.writeText(variavel).then(function() {
            var btn = this;
            $(btn).addClass('btn-success').removeClass('btn-outline-secondary');
            setTimeout(function() { $(btn).removeClass('btn-success').addClass('btn-outline-secondary'); }, 800);
        }.bind(this));
    });
});

function atualizarPreview() {
    var html = document.getElementById('templateHtml').value || currentHtml;
    for (var key in previewVars) {
        html = html.split('{{' + key + '}}').join(previewVars[key]);
    }
    var wrapper = document.getElementById('previewA4');
    wrapper.innerHTML = html;
}

$('form#formTemplate').on('submit', function() {
    var editor = $('#templateHtml');
    if (editor.length && editor.summernote) {
        document.getElementById('templateHtml').value = editor.summernote('code');
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
