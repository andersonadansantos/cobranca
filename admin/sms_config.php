<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
requirePlanoAtivo();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../includes/sms_textbee.php';
require_once __DIR__ . '/../includes/csrf.php';

$adminId = (int)($_SESSION['admin_id'] ?? 0);
$pdo = getConnection();
smsGarantirTabelas($pdo);

$mensagem = '';
$tipo = '';
$dispositivos = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_exigir();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'salvar_config') {
        $apiKey = trim((string)($_POST['api_key'] ?? ''));
        $phone = trim((string)($_POST['phone_number'] ?? ''));
        $deviceId = trim((string)($_POST['device_id'] ?? ''));

        if ($phone !== '' && !smsTelefoneValidoBr($phone)) {
            $mensagem = 'Informe um número de celular válido com DDD (ex.: (91) 99999-9999).';
            $tipo = 'danger';
        } elseif ($deviceId !== '' && !preg_match('/^[A-Za-z0-9_-]{5,100}$/', $deviceId)) {
            $mensagem = 'Device ID inválido. Verifique o identificador informado pelo TextBee.';
            $tipo = 'danger';
        } else {
            $cfgAtual = smsObterConfig($adminId, $pdo);
            $dadosSalvar = [
                'phone_number' => $phone,
                'device_id' => $deviceId,
                'connection_status' => 'desconectado',
                'last_error' => null,
                'last_connection_check' => null,
            ];
            if ($apiKey !== '') {
                $dadosSalvar['api_key'] = $apiKey;
            } elseif (!$cfgAtual || empty($cfgAtual['api_key_encrypted'])) {
                $mensagem = 'Informe a API Key do TextBee para salvar a configuração.';
                $tipo = 'danger';
            }

            if ($mensagem === '') {
                smsSalvarConfig($adminId, $dadosSalvar, $pdo);
                $mensagem = 'Configurações SMS salvas com sucesso. A conexão ainda não foi verificada; clique em "VERIFICAR CONEXÃO".';
                $tipo = 'success';
            }
        }
    }

    if ($acao === 'salvar_template') {
        $template = trim((string)($_POST['template_cobranca'] ?? ''));
        $valid = smsValidarTemplate($template);
        if (!$valid['ok']) {
            $mensagem = $valid['erro'];
            $tipo = 'danger';
        } else {
            smsSalvarConfig($adminId, ['template_cobranca' => $template], $pdo);
            $mensagem = 'Template de SMS salvo com sucesso. Ele será usado nos envios automáticos e manuais de cobrança por SMS.';
            $tipo = 'success';
        }
    }

    if ($acao === 'verificar_conexao') {
        $resultado = smsVerificarConexao($adminId, $pdo);
        $status = $resultado['status'] ?? 'erro_comunicacao';
        $mensagem = $resultado['mensagem'] ?? 'Não foi possível verificar a conexão.';
        $tipo = $status === 'conectado' ? 'success' : 'warning';
        if (in_array($status, ['api_key_invalida', 'erro_comunicacao'], true)) $tipo = 'danger';
    }

    if ($acao === 'buscar_dispositivos') {
        $cfg = smsObterConfig($adminId, $pdo);
        $apiKey = smsDecifrar($cfg['api_key_encrypted'] ?? '');
        if ($apiKey === '') {
            $mensagem = 'Salve uma API Key válida antes de buscar dispositivos.';
            $tipo = 'danger';
        } else {
            $res = smsListarDispositivos($apiKey);
            if (!empty($res['sucesso'])) {
                $dispositivos = $res['dispositivos'];
                $mensagem = count($dispositivos) > 0 ? 'Dispositivos carregados com sucesso. Selecione o dispositivo correto.' : 'Nenhum dispositivo encontrado na conta TextBee.';
                $tipo = count($dispositivos) > 0 ? 'success' : 'warning';
            } else {
                $mensagem = $res['mensagem'] ?? 'Não foi possível buscar dispositivos.';
                $tipo = 'danger';
            }
        }
    }

    if ($acao === 'enviar_teste') {
        $destino = trim((string)($_POST['destino'] ?? ''));
        $msgTeste = trim((string)($_POST['mensagem_teste'] ?? ''));
        if ($destino === '' || !smsTelefoneValidoBr($destino)) {
            $mensagem = 'Informe um número de destino válido com DDD.';
            $tipo = 'danger';
        } elseif ($msgTeste === '') {
            $mensagem = 'Informe a mensagem do teste.';
            $tipo = 'danger';
        } else {
            $res = smsEnviarTeste($adminId, $destino, $msgTeste, $pdo);
            $mensagem = $res['mensagem'] ?? 'Resultado desconhecido.';
            $tipo = !empty($res['sucesso']) ? 'success' : 'danger';
            if (($res['status'] ?? '') === 'limite') $tipo = 'warning';
        }
    }
}

$cfg = smsObterConfig($adminId, $pdo);
$apiKeySalva = smsDecifrar($cfg['api_key_encrypted'] ?? '');
$apiKeyMascarada = smsMascararApiKey($apiKeySalva);
$temApiKey = $apiKeySalva !== '';
$connectionStatus = $cfg['connection_status'] ?? 'desconectado';
$lastError = $cfg['last_error'] ?? '';
$lastCheck = $cfg['last_connection_check'] ?? '';
$phoneNumber = smsFormatarTelefoneBr($cfg['phone_number'] ?? '');
$deviceId = $cfg['device_id'] ?? '';
$templateCobranca = smsObterTemplateCobranca($adminId, $pdo);
$templateVarsExemplo = [
    'nome_cliente' => 'João da Silva',
    'valor' => '150,00',
    'vencimento' => '30/04/2026',
    'codigo_fatura' => 'FAT-2026-001',
    'link_pagamento' => 'https://seusite.com/fatura/123',
    'nome_empresa' => getNomeSistema($adminId),
];

$filtroStatus = trim((string)($_GET['status'] ?? ''));
$filtroDe = trim((string)($_GET['de'] ?? ''));
$filtroAte = trim((string)($_GET['ate'] ?? ''));
$logs = smsBuscarLogs($adminId, ['status' => $filtroStatus, 'de' => $filtroDe, 'ate' => $filtroAte], $pdo);

$statusMap = [
    'conectado' => ['label' => 'Conectado', 'class' => 'success', 'icon' => 'fa-check-circle'],
    'desconectado' => ['label' => 'Desconectado', 'class' => 'secondary', 'icon' => 'fa-plug-circle-xmark'],
    'api_key_invalida' => ['label' => 'API Key inválida', 'class' => 'danger', 'icon' => 'fa-key'],
    'dispositivo_nao_encontrado' => ['label' => 'Dispositivo não encontrado', 'class' => 'warning', 'icon' => 'fa-mobile-screen-button'],
    'dispositivo_indisponivel' => ['label' => 'Dispositivo indisponível', 'class' => 'warning', 'icon' => 'fa-triangle-exclamation'],
    'erro_comunicacao' => ['label' => 'Erro de comunicação', 'class' => 'danger', 'icon' => 'fa-wifi'],
];
$statusInfo = $statusMap[$connectionStatus] ?? ['label' => ucfirst(str_replace('_', ' ', $connectionStatus)), 'class' => 'secondary', 'icon' => 'fa-circle-question'];

$logStatusLabels = [
    'solicitacao_aceita' => ['Solicitação aceita pela API', 'info'],
    'enviado_dispositivo' => ['Mensagem enviada pelo dispositivo', 'success'],
    'falha_envio' => ['Falha no envio', 'danger'],
    'dispositivo_indisponivel' => ['Dispositivo indisponível', 'warning'],
    'erro_api' => ['Erro na API', 'danger'],
    'pendente' => ['Pendente', 'secondary'],
];

$podeTestar = $temApiKey && $deviceId !== '' && $connectionStatus === 'conectado';

$pageTitle = 'API SMS Config';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>
<style>
.sms-card { border:1px solid #e5e7eb; border-radius:12px; background:#fff; box-shadow:0 1px 2px rgba(0,0,0,.03); }
.sms-card .card-header-lite { padding:16px 20px; border-bottom:1px solid #eef2f7; font-weight:600; }
.sms-card .card-body-lite { padding:20px; }
.sms-status-badge { display:inline-flex; align-items:center; gap:8px; padding:10px 14px; border-radius:999px; font-weight:600; }
.sms-muted { color:#64748b; font-size:.92rem; }
.sms-code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size:.9rem; }
.sms-history-table td, .sms-history-table th { vertical-align: middle; font-size:.9rem; }
.sms-msg-preview { max-width:280px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
</style>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5><i class="fas fa-sms me-2" style="color:#0ea5e9;"></i>Configuração da API SMS</h5>
            <div class="text-muted" style="font-size:.85rem;">Configure sua conta TextBee SMS Gateway para utilizar seu próprio celular e enviar mensagens SMS através do chip e plano da sua operadora.</div>
        </div>
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                <img src="<?= htmlspecialchars(function_exists('getAdminAvatarUrl') ? getAdminAvatarUrl() : ($_SESSION['admin_avatar'] ?? '/cobranca/assets/img/avatars/admin.svg')) ?>" alt="Avatar" class="rounded-circle me-2" width="32" height="32" style="object-fit:cover;" onerror="this.onerror=null;this.src='/cobranca/assets/img/avatars/admin.svg';">
                <span class="text-muted d-none d-md-inline"><?= htmlspecialchars($_SESSION['admin_nome'] ?? 'Admin') ?></span>
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
            <div class="alert alert-<?= htmlspecialchars($tipo) ?> alert-dismissible fade show">
                <?= htmlspecialchars($mensagem) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="sms-card mb-4">
                    <div class="card-header-lite d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span><i class="fas fa-plug me-2 text-primary"></i>Status da integração</span>
                        <span class="sms-status-badge bg-<?= $statusInfo['class'] ?> bg-opacity-10 text-<?= $statusInfo['class'] ?>">
                            <i class="fas <?= $statusInfo['icon'] ?>"></i> <?= htmlspecialchars($statusInfo['label']) ?>
                        </span>
                    </div>
                    <div class="card-body-lite">
                        <div class="sms-muted mb-2">O status reflete a última verificação real realizada pelo backend na API oficial do TextBee.</div>
                        <?php if ($lastCheck): ?>
                            <div class="small text-muted"><strong>Última verificação:</strong> <?= htmlspecialchars(date('d/m/Y H:i:s', strtotime($lastCheck))) ?></div>
                        <?php endif; ?>
                        <?php if ($lastError): ?>
                            <div class="small text-danger mt-1"><strong>Último erro:</strong> <?= htmlspecialchars($lastError) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="sms-card mb-4">
                    <div class="card-header-lite"><i class="fas fa-cog me-2"></i>Configuração da conta TextBee</div>
                    <div class="card-body-lite">
                        <form method="POST" class="row g-3">
                            <?= csrf_field() ?>
                            <input type="hidden" name="acao" value="salvar_config">
                            <div class="col-12">
                                <label class="form-label">API Key <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control sms-code" id="apiKeyInput" name="api_key" autocomplete="off" placeholder="<?= $temApiKey ? 'Deixe em branco para manter a chave salva' : 'Cole sua API Key do TextBee' ?>">
                                    <button class="btn btn-outline-secondary" type="button" id="toggleApiKey" title="Mostrar/ocultar"><i class="fas fa-eye"></i></button>
                                </div>
                                <div class="form-text">
                                    <?php if ($temApiKey): ?>
                                        Chave salva: <span class="sms-code"><?= htmlspecialchars($apiKeyMascarada) ?></span>. Por segurança, a chave não é exibida no frontend.
                                    <?php else: ?>
                                        A chave será armazenada de forma criptografada no servidor.
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Número do celular</label>
                                <input type="text" class="form-control" name="phone_number" id="phoneNumber" maxlength="15" placeholder="(91) 99999-9999" value="<?= htmlspecialchars($phoneNumber) ?>">
                                <div class="form-text">Número com DDD do celular vinculado ao dispositivo Android.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Device ID</label>
                                <input type="text" class="form-control sms-code" name="device_id" id="deviceIdInput" maxlength="100" placeholder="ID do dispositivo no TextBee" value="<?= htmlspecialchars($deviceId) ?>">
                                <div class="form-text">Identificador do dispositivo Android no TextBee. Não é o número de celular.</div>
                            </div>
                            <div class="col-12 d-flex gap-2 flex-wrap">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Salvar configuração</button>
                            </div>
                        </form>

                        <hr class="my-4">
                        <form method="POST" class="d-flex gap-2 flex-wrap align-items-end">
                            <?= csrf_field() ?>
                            <input type="hidden" name="acao" value="buscar_dispositivos">
                            <div>
                                <label class="form-label mb-1">Dispositivos da conta</label>
                                <div class="sms-muted">Quando suportado pela API, busque os dispositivos disponíveis e selecione o Device ID correto.</div>
                            </div>
                            <button type="submit" class="btn btn-outline-primary"><i class="fas fa-mobile-screen-button me-1"></i>Buscar dispositivos</button>
                        </form>
                        <?php if (!empty($dispositivos)): ?>
                            <div class="table-responsive mt-3">
                                <table class="table table-sm align-middle">
                                    <thead><tr><th>Dispositivo</th><th>Device ID</th><th>Status</th><th></th></tr></thead>
                                    <tbody>
                                    <?php foreach ($dispositivos as $d): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($d['name'] ?: 'Dispositivo') ?></td>
                                            <td class="sms-code"><?= htmlspecialchars($d['id']) ?></td>
                                            <td><?= !empty($d['enabled']) ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-secondary">Inativo</span>' ?></td>
                                            <td><button type="button" class="btn btn-sm btn-outline-secondary" onclick="selecionarDevice('<?= htmlspecialchars($d['id'], ENT_QUOTES) ?>')">Usar este</button></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <hr class="my-4">
                        <form method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="acao" value="verificar_conexao">
                            <button type="submit" class="btn btn-success btn-lg"><i class="fas fa-signal me-2"></i>VERIFICAR CONEXÃO</button>
                            <div class="sms-muted mt-2">A verificação testa a API Key, a autorização da conta, o Device ID e a disponibilidade/comunicação informada pela API.</div>
                        </form>
                    </div>
                </div>

                <div class="sms-card mb-4">
                    <div class="card-header-lite"><i class="fas fa-file-lines me-2"></i>Template de Mensagem de Cobrança</div>
                    <div class="card-body-lite">
                        <form method="POST" class="row g-3">
                            <?= csrf_field() ?>
                            <input type="hidden" name="acao" value="salvar_template">
                            <div class="col-12">
                                <label class="form-label">Mensagem SMS de cobrança</label>
                                <textarea class="form-control" name="template_cobranca" id="templateCobranca" rows="5" maxlength="<?= (int)SMS_MAX_CHARS ?>" required><?= htmlspecialchars($templateCobranca) ?></textarea>
                                <div class="d-flex justify-content-between form-text">
                                    <span>Use variáveis dinâmicas para personalizar a cobrança.</span>
                                    <span><span id="tplCharCount">0</span>/<?= (int)SMS_MAX_CHARS ?> | <span id="tplSmsCount">0</span> SMS</span>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="small text-muted mb-1"><strong>Variáveis disponíveis:</strong></div>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach (['{nome_cliente}','{valor}','{vencimento}','{codigo_fatura}','{link_pagamento}','{nome_empresa}'] as $v): ?>
                                        <code class="px-2 py-1 bg-light border rounded"><?= htmlspecialchars($v) ?></code>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Prévia da mensagem</label>
                                <div class="border rounded p-3 bg-light" id="tplPreview" style="white-space:pre-wrap; min-height:72px;"></div>
                                <div class="form-text">A prévia usa dados de exemplo; no envio real, as variáveis serão substituídas pelos dados do cliente/fatura.</div>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Salvar template</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="sms-card mb-4">
                    <div class="card-header-lite"><i class="fas fa-paper-plane me-2"></i>Teste de envio SMS</div>
                    <div class="card-body-lite">
                        <div class="alert alert-info py-2 mb-3">
                            <i class="fas fa-triangle-exclamation me-1"></i> <strong>Limites de envio:</strong> 50 envios diários e 300 envios mensais.
                        </div>
                        <?php if (!$podeTestar): ?>
                            <div class="alert alert-warning mb-0">
                                <i class="fas fa-circle-info me-1"></i> O envio de teste fica disponível somente quando a API e o dispositivo estiverem configurados e a conexão estiver <strong>Conectada</strong>.
                            </div>
                        <?php else: ?>
                            <form method="POST" class="row g-3">
                                <?= csrf_field() ?>
                                <input type="hidden" name="acao" value="enviar_teste">
                                <div class="col-md-5">
                                    <label class="form-label">Número de destino</label>
                                    <input type="text" class="form-control" name="destino" id="destinoTeste" maxlength="15" placeholder="(91) 99999-9999" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Mensagem</label>
                                    <textarea class="form-control" name="mensagem_teste" id="mensagemTeste" rows="4" maxlength="480" required>Olá! Este é um SMS de teste. Sua integração com o TextBee SMS Gateway está funcionando.</textarea>
                                    <div class="d-flex justify-content-between form-text">
                                        <span>O envio é realizado pelo seu dispositivo Android vinculado, usando o chip/plano da operadora.</span>
                                        <span><span id="charCount">0</span>/480</span>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Pré-visualização</label>
                                    <div class="border rounded p-3 bg-light" id="previewMsg" style="white-space:pre-wrap;"></div>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i>ENVIAR SMS DE TESTE</button>
                                </div>
                            </form>
                            <div class="small text-muted mt-3">
                                <strong>Observação:</strong> "Solicitação aceita pela API" significa que o TextBee recebeu o pedido. A entrega final depende do dispositivo, do sinal, do chip/plano da operadora e da confirmação disponível pela API.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="sms-card mb-4">
                    <div class="card-header-lite"><i class="fas fa-circle-info me-2 text-info"></i>Ainda não possui uma conta no TextBee?</div>
                    <div class="card-body-lite">
                        <p class="sms-muted mb-3">Para utilizar o envio de SMS, você precisa criar sua conta no TextBee, instalar o aplicativo Gateway no Android, vincular seu celular e obter sua API Key.</p>
                        <p class="sms-muted mb-3">Depois, volte a esta página e configure sua integração.</p>
                        <a href="https://textbee.dev/" target="_blank" rel="noopener noreferrer" class="btn btn-info text-white w-100 mb-2"><i class="fas fa-external-link-alt me-1"></i>CRIAR MINHA CONTA NO TEXTBEE</a>
                        <a href="https://textbee.dev/docs" target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary w-100"><i class="fas fa-book me-1"></i>Consultar documentação oficial</a>
                    </div>
                </div>
                <div class="sms-card mb-4">
                    <div class="card-header-lite"><i class="fas fa-shield-halved me-2 text-success"></i>Segurança e independência</div>
                    <div class="card-body-lite sms-muted">
                        <ul class="mb-0 ps-3">
                            <li>Cada administrador usa sua própria API Key, número e dispositivo.</li>
                            <li>As credenciais são criptografadas no backend e não são expostas no frontend.</li>
                            <li>Este módulo é independente e não altera cobranças, boletos, financeiro ou e-mails.</li>
                            <li>Os testes possuem limite de frequência para evitar uso abusivo.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="sms-card">
            <div class="card-header-lite d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><i class="fas fa-clock-rotate-left me-2"></i>Histórico de envios SMS</span>
            </div>
            <div class="card-body-lite">
                <form method="GET" class="row g-2 mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">Todos</option>
                            <?php foreach ($logStatusLabels as $k => $v): ?>
                                <option value="<?= htmlspecialchars($k) ?>" <?= $filtroStatus === $k ? 'selected' : '' ?>><?= htmlspecialchars($v[0]) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">De</label>
                        <input type="date" name="de" class="form-control" value="<?= htmlspecialchars($filtroDe) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Até</label>
                        <input type="date" name="ate" class="form-control" value="<?= htmlspecialchars($filtroAte) ?>">
                    </div>
                    <div class="col-md-3 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-outline-primary"><i class="fas fa-filter me-1"></i>Filtrar</button>
                        <a href="sms_config.php" class="btn btn-outline-secondary">Limpar</a>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-hover sms-history-table">
                        <thead><tr><th>Data/hora</th><th>Administrador</th><th>Destino</th><th>Dispositivo</th><th>Mensagem</th><th>Status</th><th>Detalhes</th></tr></thead>
                        <tbody>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">Nenhum teste de SMS encontrado.</td></tr>
                        <?php else: foreach ($logs as $log): ?>
                            <?php $st = $logStatusLabels[$log['status']] ?? [ucfirst(str_replace('_',' ', $log['status'])), 'secondary']; $adminNome = $_SESSION['admin_nome'] ?? 'Admin'; ?>
                            <tr>
                                <td><?= htmlspecialchars(date('d/m/Y H:i:s', strtotime($log['created_at']))) ?></td>
                                <td><?= htmlspecialchars($adminNome) ?></td>
                                <td><?= htmlspecialchars(smsFormatarTelefoneBr($log['recipient_phone'] ?? '')) ?></td>
                                <td class="sms-code"><?= htmlspecialchars($log['device_id'] ?: '-') ?></td>
                                <td><div class="sms-msg-preview" title="<?= htmlspecialchars($log['message'] ?? '') ?>"><?= htmlspecialchars($log['message'] ?? '') ?></div></td>
                                <td><span class="badge bg-<?= $st[1] ?>"><?= htmlspecialchars($st[0]) ?></span></td>
                                <td style="max-width:260px;">
                                    <?php if (!empty($log['provider_message_id'])): ?><div class="small text-muted">ID: <span class="sms-code"><?= htmlspecialchars($log['provider_message_id']) ?></span></div><?php endif; ?>
                                    <?php if (!empty($log['error_message'])): ?><div class="small text-danger"><?= htmlspecialchars($log['error_message']) ?></div><?php elseif (empty($log['provider_message_id'])): ?><span class="text-muted">-</span><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function mascaraTelefoneSms(el) {
    let v = (el.value || '').replace(/\D/g, '').slice(0, 11);
    if (v.length > 10) v = v.replace(/^(\d{2})(\d{5})(\d{0,4}).*/, '($1) $2-$3');
    else if (v.length > 6) v = v.replace(/^(\d{2})(\d{4})(\d{0,4}).*/, '($1) $2-$3');
    else if (v.length > 2) v = v.replace(/^(\d{2})(\d{0,5}).*/, '($1) $2');
    else if (v.length > 0) v = v.replace(/^(\d{0,2}).*/, '($1');
    el.value = v.trim();
}
['phoneNumber','destinoTeste'].forEach(function(id){ var el=document.getElementById(id); if(!el) return; el.addEventListener('input', function(){ mascaraTelefoneSms(el); }); });
var toggle=document.getElementById('toggleApiKey');
if(toggle){ toggle.addEventListener('click', function(){ var input=document.getElementById('apiKeyInput'); if(!input) return; var isPwd=input.type==='password'; input.type=isPwd?'text':'password'; toggle.innerHTML=isPwd?'<i class="fas fa-eye-slash"></i>':'<i class="fas fa-eye"></i>'; }); }
function selecionarDevice(id){ var input=document.getElementById('deviceIdInput'); if(input){ input.value=id; input.focus(); } }
var msg=document.getElementById('mensagemTeste'), preview=document.getElementById('previewMsg'), counter=document.getElementById('charCount');
function atualizaPreview(){ if(!msg) return; if(counter) counter.textContent=msg.value.length; if(preview) preview.textContent=msg.value; }
if(msg){ msg.addEventListener('input', atualizaPreview); atualizaPreview(); }

var tpl=document.getElementById('templateCobranca'), tplPreview=document.getElementById('tplPreview'), tplChar=document.getElementById('tplCharCount'), tplSms=document.getElementById('tplSmsCount');
var tplVars=<?= json_encode($templateVarsExemplo, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
function smsSegmentos(len){ if(len<=0) return 0; return len<=160 ? 1 : Math.ceil(len/153); }
function atualizaTplPreview(){
    if(!tpl) return;
    var txt=tpl.value||'';
    var prev=txt.replace(/\{(\w+)\}/g, function(m,k){ return Object.prototype.hasOwnProperty.call(tplVars,k)?tplVars[k]:m; });
    if(tplChar) tplChar.textContent=txt.length;
    if(tplSms) tplSms.textContent=smsSegmentos(txt.length);
    if(tplPreview) tplPreview.textContent=prev;
}
if(tpl){ tpl.addEventListener('input', atualizaTplPreview); atualizaTplPreview(); }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
