<?php
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/mercadopago.php';
require_once __DIR__ . '/../config/email_helpers.php';
require_once __DIR__ . '/../api/whatsapp_send.php';

$pdo = getConnection();
$mensagem = '';
$tipo = '';

// Ações na fatura recorrente
if (isset($_GET['pago'])) {
    $id = intval($_GET['pago']);
    $stmt = $pdo->prepare("UPDATE faturas SET status = 'pago', data_pagamento = CURDATE() WHERE fatura_recorrente_id = ? AND status != 'pago' ORDER BY data_vencimento DESC LIMIT 1");
    $stmt->execute([$id]);
    header('Location: emissao.php?msg=pago');
    exit;
}
if (isset($_GET['cancelar'])) {
    $id = intval($_GET['cancelar']);

    $stFats = $pdo->prepare("SELECT * FROM faturas WHERE fatura_recorrente_id = ? AND status IN ('pendente','vencido','atrasado')");
    $stFats->execute([$id]);
    while ($fat = $stFats->fetch()) {
        cancelarCobrancaFatura($fat);
    }

    $stmt = $pdo->prepare("UPDATE faturas_recorrentes SET status = 'cancelado' WHERE id = ?");
    $stmt->execute([$id]);
    $stmt = $pdo->prepare("UPDATE faturas SET status = 'cancelado' WHERE fatura_recorrente_id = ? AND status IN ('pendente','vencido','atrasado')");
    $stmt->execute([$id]);
    header('Location: emissao.php?msg=cancelado');
    exit;
}
if (isset($_GET['excluir'])) {
    $id = intval($_GET['excluir']);
    $stmt = $pdo->prepare("DELETE FROM faturas WHERE fatura_recorrente_id = ?");
    $stmt->execute([$id]);
    $stmt = $pdo->prepare("DELETE FROM faturas_recorrentes WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: emissao.php?msg=excluido');
    exit;
}
if (isset($_GET['enviar'])) {
    $frId = intval($_GET['enviar']);
    $stmt = $pdo->prepare("
        SELECT f.id, f.numero, f.descricao, f.valor_final, f.data_vencimento, f.link_pagamento,
               f.pix_copia_cola, f.pix_qrcode,
               c.nome_razao, c.email, c.cpf_cnpj
        FROM faturas f
        JOIN clientes c ON f.cliente_id = c.id
        WHERE f.fatura_recorrente_id = ? AND f.status IN ('pendente','vencido','atrasado')
        ORDER BY f.data_vencimento DESC LIMIT 1
    ");
    $stmt->execute([$frId]);
    $fatura = $stmt->fetch();
    if ($fatura && !empty($fatura['email'])) {
        $ok = enviarEmailFatura($fatura, 'antes');
        header('Location: emissao.php?msg=' . ($ok ? 'enviado' : 'erro_envio'));
    } else {
        header('Location: emissao.php?msg=sem_email');
    }
    exit;
}
if (isset($_GET['whatsapp'])) {
    $frId = intval($_GET['whatsapp']);
    $stmt = $pdo->prepare("
        SELECT f.id, f.numero, f.descricao, f.valor_final, f.data_vencimento, f.link_pagamento,
               f.pix_copia_cola, f.pix_qrcode,
               c.nome_razao, c.email, c.cpf_cnpj, c.celular, c.telefone
        FROM faturas f
        JOIN clientes c ON f.cliente_id = c.id
        WHERE f.fatura_recorrente_id = ? AND f.status IN ('pendente','vencido','atrasado')
        ORDER BY f.data_vencimento DESC LIMIT 1
    ");
    $stmt->execute([$frId]);
    $fatura = $stmt->fetch();
    if ($fatura) {
        $ok = enviarWhatsAppFatura($fatura, 'antes');
        header('Location: emissao.php?msg=' . ($ok ? 'whatsapp_enviado' : 'whatsapp_erro'));
    } else {
        header('Location: emissao.php?msg=sem_fatura');
    }
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete']) && !empty($_POST['ids'])) {
    $ids = array_map('intval', $_POST['ids']);
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("DELETE FROM faturas WHERE fatura_recorrente_id IN ($ph)");
    $stmt->execute($ids);
    $stmt = $pdo->prepare("DELETE FROM faturas_recorrentes WHERE id IN ($ph)");
    $stmt->execute($ids);
    header('Location: emissao.php?msg=excluido');
    exit;
}
if (isset($_GET['msg'])) {
    $msgs = [
        'salvo' => ['Fatura criada com sucesso!', 'success'],
        'pago' => ['Fatura marcada como paga!', 'success'],
        'cancelado' => ['Fatura recorrente cancelada!', 'warning'],
        'excluido' => ['Fatura recorrente excluída!', 'warning'],
        'enviado' => ['E-mail de cobrança enviado com sucesso!', 'success'],
        'erro_envio' => ['Erro ao enviar e-mail. Verifique as configurações SMTP.', 'danger'],
        'sem_email' => ['Cliente não possui e-mail cadastrado.', 'warning'],
        'whatsapp_enviado' => ['Fatura enviada via WhatsApp com sucesso!', 'success'],
        'whatsapp_erro' => ['Erro ao enviar WhatsApp. Verifique as configurações.', 'danger'],
        'sem_fatura' => ['Nenhuma fatura pendente encontrada para esta recorrência.', 'warning'],
        'erro' => ['Erro ao salvar.', 'danger'],
    ];
    if (isset($msgs[$_GET['msg']])) {
        $mensagem = $msgs[$_GET['msg']][0];
        $tipo = $msgs[$_GET['msg']][1];
    }
}

// Salvar fatura recorrente
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = intval($_POST['cliente_id'] ?? 0);
    $descricao = trim($_POST['descricao'] ?? '');
    $valor = floatval($_POST['valor'] ?? 0);
    $frequencia = $_POST['frequencia'] ?? 'mensal';
    $dia_vencimento = max(1, min(31, intval($_POST['dia_vencimento'] ?? 1)));
    $data_inicio = date('Y-m-d');
    $data_fim = !empty($_POST['data_fim']) ? $_POST['data_fim'] : null;

    if ($cliente_id <= 0 || empty($descricao) || $valor <= 0) {
        $mensagem = 'Preencha todos os campos obrigatórios.';
        $tipo = 'danger';
    } else {
        try {
            $numero = generateInvoiceNumber();
            $stmt = $pdo->prepare("INSERT INTO faturas_recorrentes (cliente_id, descricao, valor, frequencia, dia_vencimento, data_inicio, data_fim, numero) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$cliente_id, $descricao, $valor, $frequencia, $dia_vencimento, $data_inicio, $data_fim, $numero]);
            $faturaRecorrenteId = $pdo->lastInsertId();

            $dataVenc = date('Y-m-' . str_pad($dia_vencimento, 2, '0', STR_PAD_LEFT));
            if ($dataVenc < date('Y-m-d')) {
                $dataVenc = date('Y-m-' . str_pad($dia_vencimento, 2, '0', STR_PAD_LEFT), strtotime('+1 month'));
            }

            $stmt = $pdo->prepare("INSERT INTO faturas (cliente_id, fatura_recorrente_id, numero, descricao, valor, valor_final, data_emissao, data_vencimento, status, acesso_token, api_pagamento) VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?, 'pendente', ?, ?)");
            $stmt->execute([$cliente_id, $faturaRecorrenteId, $numero, $descricao, $valor, $valor, $dataVenc, generateAcessoToken(), getApiAtiva()]);
            $faturaId = $pdo->lastInsertId();

            $stmtCliente = $pdo->prepare("SELECT nome_razao, email, celular, telefone FROM clientes WHERE id = ?");
            $stmtCliente->execute([$cliente_id]);
            $cliente = $stmtCliente->fetch();

            if ($cliente && !empty($cliente['email'])) {
                $faturaDados = [
                    'id' => $faturaId,
                    'numero' => $numero,
                    'descricao' => $descricao,
                    'valor_final' => $valor,
                    'data_vencimento' => $dataVenc,
                    'link_pagamento' => '',
                    'pix_copia_cola' => '',
                    'pix_qrcode' => '',
                    'nome_razao' => $cliente['nome_razao'],
                    'email' => $cliente['email'],
                    'cpf_cnpj' => $cliente['cpf_cnpj'],
                    'celular' => $cliente['celular'] ?? '',
                    'telefone' => $cliente['telefone'] ?? '',
                ];
                enviarEmailFatura($faturaDados, 'antes');
            }

            $stmtFat = $pdo->prepare("SELECT f.*, c.nome_razao, c.celular, c.telefone, c.email, c.cpf_cnpj FROM faturas f JOIN clientes c ON f.cliente_id = c.id WHERE f.id = ?");
            $stmtFat->execute([$faturaId]);
            $faturaCompleta = $stmtFat->fetch();
            if (!empty($cliente['celular']) || !empty($cliente['telefone'])) {
                enviarWhatsAppFatura($faturaCompleta, 'antes');
            }

            $stmtUp = $pdo->prepare("UPDATE faturas SET ultimo_envio = CURDATE(), ultimo_envio_tipo = 'geracao' WHERE id = ?");
            $stmtUp->execute([$faturaId]);

            header('Location: emissao.php?msg=salvo');
            exit;
        } catch (PDOException $e) {
            $mensagem = 'Erro: ' . $e->getMessage();
            $tipo = 'danger';
        }
    }
}

$clientes = $pdo->query("SELECT id, nome_razao, cpf_cnpj FROM clientes WHERE ativo = 1 ORDER BY nome_razao")->fetchAll();

$filtro_status = $_GET['filtro_status'] ?? '';
$filtro_busca = trim($_GET['filtro_busca'] ?? '');

$sql = "
    SELECT base.* FROM (
        SELECT fr.*, c.nome_razao, c.cpf_cnpj, c.celular, c.telefone,
        (SELECT f.link_pagamento FROM faturas f WHERE f.fatura_recorrente_id = fr.id AND f.status IN ('pendente','vencido','atrasado') ORDER BY f.data_vencimento DESC, f.id DESC LIMIT 1) AS ultimo_link,
        (SELECT f.numero FROM faturas f WHERE f.fatura_recorrente_id = fr.id ORDER BY f.data_vencimento DESC, f.id DESC LIMIT 1) AS ultimo_numero,
        (SELECT f.status FROM faturas f WHERE f.fatura_recorrente_id = fr.id ORDER BY f.data_vencimento DESC, f.id DESC LIMIT 1) AS ultimo_status
        FROM faturas_recorrentes fr 
        JOIN clientes c ON fr.cliente_id = c.id 
        WHERE (fr.ativo = 1 OR fr.status = 'cancelado')
    ) AS base
    WHERE 1=1
";
$params = [];

if ($filtro_status !== '') {
    $sql .= " AND base.ultimo_status = ?";
    $params[] = $filtro_status;
}
if ($filtro_busca !== '') {
    $sql .= " AND (base.nome_razao LIKE ? OR base.cpf_cnpj LIKE ? OR base.ultimo_numero LIKE ? OR base.descricao LIKE ?)";
    $params[] = '%' . $filtro_busca . '%';
    $params[] = '%' . $filtro_busca . '%';
    $params[] = '%' . $filtro_busca . '%';
    $params[] = '%' . $filtro_busca . '%';
}

$sql .= " ORDER BY base.criado_em DESC";

$countSql = "SELECT COUNT(*) FROM (
    SELECT fr.id FROM faturas_recorrentes fr 
    JOIN clientes c ON fr.cliente_id = c.id 
    WHERE (fr.ativo = 1 OR fr.status = 'cancelado')
    AND (SELECT f.status FROM faturas f WHERE f.fatura_recorrente_id = fr.id ORDER BY f.data_vencimento DESC LIMIT 1) <=> ?
) AS cnt";
$countParams = [$filtro_status !== '' ? $filtro_status : null];
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($countParams);
$totalFaturasRecorrentes = $countStmt->fetchColumn();

$perPage = intval($_GET['per_page'] ?? 10);
if (!in_array($perPage, [10, 20, 50, 100])) $perPage = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$totalPages = max(1, ceil($totalFaturasRecorrentes / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$sql .= " LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$faturasRecorrentes = $stmt->fetchAll();

$pageTitle = 'Emissão de Faturas';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5>Emissão de Faturas</h5>
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

        <div class="form-card mb-4">
            <h6 class="mb-3"><i class="fas fa-plus-circle me-2"></i>Nova Fatura</h6>
            <form method="POST" id="formNovaFatura">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Cliente *</label>
                        <select name="cliente_id" class="form-select" required>
                            <option value="">Selecione o cliente...</option>
                            <?php foreach ($clientes as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nome_razao']) ?> (<?= htmlspecialchars($c['cpf_cnpj']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Descrição *</label>
                        <input type="text" name="descricao" class="form-control" placeholder="Ex: Mensalidade Janeiro" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Valor (R$) *</label>
                        <input type="number" name="valor" class="form-control" step="0.01" min="0.01" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Frequência</label>
<select name="frequencia" class="form-select">
                            <option value="unica">Fatura Única</option>
                            <option value="diaria">Diária</option>
                            <option value="semanal">Semanal</option>
                            <option value="quinzenal">Quinzenal</option>
                            <option value="mensal">Mensal</option>
                            <option value="bimestral">Bimestral</option>
                            <option value="trimestral">Trimestral</option>
                            <option value="semestral">Semestral</option>
                            <option value="anual">Anual</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Dia Vencimento</label>
                        <select name="dia_vencimento" class="form-select">
                            <?php for ($d = 1; $d <= 31; $d++): ?>
                                <option value="<?= $d ?>" <?= $d === 1 ? 'selected' : '' ?>><?= $d ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Criar Fatura
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="table-card">
            <div class="p-3 border-bottom">
                <h6 class="mb-0"><i class="fas fa-sync-alt me-2"></i>Faturas Recorrentes Ativas</h6>
            </div>
            <div class="p-3 border-bottom">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label small">Status</label>
                        <div class="d-flex gap-1 flex-wrap">
                            <a href="?filtro_status=&filtro_busca=<?= urlencode($filtro_busca) ?>" class="btn btn-sm <?= $filtro_status === '' ? 'btn-dark' : 'btn-outline-dark' ?>"><i class="fas fa-square me-1" style="color:#6c757d;font-size:.6rem"></i> Todos</a>
                            <a href="?filtro_status=pendente&filtro_busca=<?= urlencode($filtro_busca) ?>" class="btn btn-sm <?= $filtro_status === 'pendente' ? 'btn-warning' : 'btn-outline-warning' ?>"><i class="fas fa-square me-1" style="color:#fd7e14;font-size:.6rem"></i> Pendente</a>
                            <a href="?filtro_status=pago&filtro_busca=<?= urlencode($filtro_busca) ?>" class="btn btn-sm <?= $filtro_status === 'pago' ? 'btn-success' : 'btn-outline-success' ?>"><i class="fas fa-square me-1" style="color:#198754;font-size:.6rem"></i> Pago</a>
                            <a href="?filtro_status=vencido&filtro_busca=<?= urlencode($filtro_busca) ?>" class="btn btn-sm <?= $filtro_status === 'vencido' ? 'btn-danger' : 'btn-outline-danger' ?>"><i class="fas fa-square me-1" style="color:#dc3545;font-size:.6rem"></i> Atrasado</a>
                            <a href="?filtro_status=cancelado&filtro_busca=<?= urlencode($filtro_busca) ?>" class="btn btn-sm <?= $filtro_status === 'cancelado' ? 'btn-secondary' : 'btn-outline-secondary' ?>"><i class="fas fa-square me-1" style="color:#6c757d;font-size:.6rem"></i> Cancelado</a>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small"><i class="fas fa-square me-1" style="color:#6f42c1;font-size:.6rem"></i>Nome <i class="fas fa-square ms-2 me-1" style="color:#b19cd9;font-size:.6rem"></i>CPF/CNPJ <i class="fas fa-square ms-2 me-1" style="color:#d4a0e8;font-size:.6rem"></i>Nº Fatura</label>
                        <input type="text" name="filtro_busca" class="form-control form-control-sm" placeholder="Buscar por nome, CPF/CNPJ ou Nº Fatura..." value="<?= htmlspecialchars($filtro_busca) ?>">
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fas fa-search"></i></button>
                    </div>
                    <div class="col-md-1">
                        <a href="emissao.php" class="btn btn-sm btn-outline-secondary w-100"><i class="fas fa-times"></i></a>
                    </div>
                </form>
            </div>
            <?php if (empty($faturasRecorrentes)): ?>
                <div class="text-center text-muted py-4">Nenhuma fatura recorrente criada</div>
            <?php else: ?>
                <form method="POST" id="bulkForm">
                <div class="p-3 border-bottom d-flex align-items-center gap-2 flex-wrap">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="selectAll">
                        <label class="form-check-label small" for="selectAll">Selecionar todos</label>
                    </div>
                    <button type="submit" name="bulk_delete" class="btn btn-sm btn-outline-danger" onclick="return confirm('Excluir permanentemente os selecionados?')"><i class="fas fa-trash me-1"></i>Excluir Selecionados</button>
                </div>
                <div class="p-3">
                    <?php foreach ($faturasRecorrentes as $fr): ?>
                        <?php
                        $statusClasses = [
                            'pendente' => 'bg-warning text-dark',
                            'pago' => 'bg-success',
                            'vencido' => 'bg-danger',
                            'atrasado' => 'bg-danger',
                            'cancelado' => 'bg-secondary'
                        ];
                        $statusAtual = $fr['ultimo_status'] ?? 'pendente';
                        $statusClass = $statusClasses[$statusAtual] ?? 'bg-secondary';

                        $telWhatsApp = preg_replace('/[^0-9]/', '', $fr['celular'] ?: $fr['telefone'] ?? '');
                        $linkPagamento = $fr['ultimo_link'] ?? '';
                        $msgWhatsApp = "Olá, Tudo bem? identificamos uma fatura pendente, segue o link para fazer o pagamento:\n\n" . ($linkPagamento ? $linkPagamento : 'Link não disponível');
                        $urlWhatsApp = 'https://wa.me/55' . $telWhatsApp . '?text=' . urlencode($msgWhatsApp);
                        ?>
                        <div class="fr-row mb-2 <?= ($fr['status'] ?? 'ativa') === 'cancelado' ? 'opacity-50' : '' ?>">
                            <div class="form-check mb-0" style="min-width:60px;">
                                <input class="form-check-input bulk-check" type="checkbox" name="ids[]" value="<?= $fr['id'] ?>" id="ck<?= $fr['id'] ?>">
                                <label class="small text-muted" for="ck<?= $fr['id'] ?>" style="cursor:pointer;"><strong><?= htmlspecialchars($fr['ultimo_numero'] ?? '#'.$fr['id']) ?></strong></label>
                            </div>
                            <div class="fr-cliente">
                                <strong><?= htmlspecialchars($fr['nome_razao']) ?></strong>
                                <small class="d-block text-muted"><?= htmlspecialchars($fr['descricao']) ?></small>
                            </div>
                            <div class="fr-dado">
                                <span class="fr-dado-label">Valor</span>
                                <strong>R$ <?= number_format($fr['valor'], 2, ',', '.') ?></strong>
                            </div>
                            <div class="fr-dado">
                                <span class="fr-dado-label">Frequência</span>
                                <span class="badge bg-info"><?= ucfirst($fr['frequencia']) ?></span>
                            </div>
                            <div class="fr-dado">
                                <span class="fr-dado-label">Venc.</span>
                                <strong><?= $fr['dia_vencimento'] ?></strong>
                            </div>
                            <div class="fr-dado">
                                <span class="fr-dado-label">Período</span>
                                <strong class="fr-periodo"><?= date('d/m/Y', strtotime($fr['data_inicio'])) ?><?= $fr['data_fim'] ? '<br>até ' . date('d/m/Y', strtotime($fr['data_fim'])) : '' ?></strong>
                            </div>
                            <span class="badge <?= $statusClass ?> fr-badge"><?= ucfirst($statusAtual) ?></span>
                            <div class="fr-acoes">
                                <?php if ($telWhatsApp): ?>
                                    <a href="#" class="btn btn-sm btn-outline-success" title="Enviar fatura via WhatsApp" data-bs-toggle="modal" data-bs-target="#modalEnviarWhatsApp" data-id="<?= $fr['id'] ?>"><i class="fab fa-whatsapp"></i></a>
                                <?php endif; ?>
                                <?php if ($statusAtual !== 'pago' && ($fr['status'] ?? 'ativa') !== 'cancelado'): ?>
                                    <a href="#" class="btn btn-sm btn-outline-primary" title="Enviar e-mail de cobrança" data-bs-toggle="modal" data-bs-target="#modalEnviarEmail" data-id="<?= $fr['id'] ?>"><i class="fas fa-envelope"></i></a>
                                    <a href="#" class="btn btn-sm btn-outline-success" title="Pago" data-bs-toggle="modal" data-bs-target="#modalMarcarPago" data-id="<?= $fr['id'] ?>"><i class="fas fa-check"></i></a>
                                <?php endif; ?>
                                <?php if (($fr['status'] ?? 'ativa') !== 'cancelado'): ?>
                                    <a href="#" class="btn btn-sm btn-outline-warning" title="Cancelar" data-bs-toggle="modal" data-bs-target="#modalCancelar" data-id="<?= $fr['id'] ?>"><i class="fas fa-ban"></i></a>
                                <?php endif; ?>
                                <a href="#" class="btn btn-sm btn-outline-danger" title="Excluir" data-bs-toggle="modal" data-bs-target="#modalExcluir" data-id="<?= $fr['id'] ?>"><i class="fas fa-trash"></i></a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                </form>
            <?php endif; ?>
            <?php if ($totalFaturasRecorrentes > 0): ?>
            <div class="p-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <small class="text-muted">Mostrando <?= count($faturasRecorrentes) ?> de <?= $totalFaturasRecorrentes ?> registros</small>
                    <select class="form-select form-select-sm" style="width:auto;" onchange="window.location.href='?page=1&per_page='+this.value+'&filtro_status=<?= urlencode($filtro_status) ?>&filtro_busca=<?= urlencode($filtro_busca) ?>'">
                        <?php foreach ([10,20,50,100] as $opt): ?>
                            <option value="<?= $opt ?>" <?= $perPage === $opt ? 'selected' : '' ?>><?= $opt ?>/página</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php $baseUrl = '?per_page=' . $perPage . '&filtro_status=' . urlencode($filtro_status) . '&filtro_busca=' . urlencode($filtro_busca); ?>
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $baseUrl ?>&page=<?= $page - 1 ?>">«</a>
                        </li>
                        <?php
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        if ($start > 1): ?>
                            <li class="page-item"><a class="page-link" href="<?= $baseUrl ?>&page=1">1</a></li>
                            <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                        <?php endif;
                        for ($i = $start; $i <= $end; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="<?= $baseUrl ?>&page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor;
                        if ($end < $totalPages): ?>
                            <?php if ($end < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                            <li class="page-item"><a class="page-link" href="<?= $baseUrl ?>&page=<?= $totalPages ?>"><?= $totalPages ?></a></li>
                        <?php endif; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $baseUrl ?>&page=<?= $page + 1 ?>">»</a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEnviarEmail" tabindex="-1" aria-labelledby="modalEnviarEmailLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="modalEnviarEmailLabel"><i class="fas fa-envelope me-2"></i>Enviar Cobrança</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                Deseja reenviar a cobrança por e-mail?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <a href="#" id="btnConfirmarEnviar" class="btn btn-primary btn-sm">Sim, enviar</a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEnviarWhatsApp" tabindex="-1" aria-labelledby="modalEnviarWhatsAppLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="modalEnviarWhatsAppLabel"><i class="fab fa-whatsapp me-2"></i>Enviar Fatura via WhatsApp</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                Deseja enviar a cobrança via WhatsApp?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <a href="#" id="btnConfirmarEnviarWhatsApp" class="btn btn-success btn-sm">Sim, enviar</a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalMarcarPago" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fas fa-check-circle me-2 text-success"></i>Marcar como Paga</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                Deseja marcar esta fatura como paga?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <a href="#" id="btnConfirmarPago" class="btn btn-success btn-sm">Sim, marcar como paga</a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCancelar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fas fa-ban me-2 text-warning"></i>Cancelar Recorrência</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                Deseja cancelar esta recorrência? As faturas pendentes também serão canceladas.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Voltar</button>
                <a href="#" id="btnConfirmarCancelar" class="btn btn-warning btn-sm">Sim, cancelar</a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalExcluir" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fas fa-trash me-2 text-danger"></i>Excluir Fatura</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                Deseja excluir esta fatura? Esta ação não pode ser desfeita.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <a href="#" id="btnConfirmarExcluir" class="btn btn-danger btn-sm">Sim, excluir</a>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('modalEnviarEmail').addEventListener('show.bs.modal', function(event) {
    var button = event.relatedTarget;
    var id = button.getAttribute('data-id');
    document.getElementById('btnConfirmarEnviar').href = '?enviar=' + id;
});
document.getElementById('modalEnviarWhatsApp').addEventListener('show.bs.modal', function(event) {
    var button = event.relatedTarget;
    var id = button.getAttribute('data-id');
    document.getElementById('btnConfirmarEnviarWhatsApp').href = '?whatsapp=' + id;
});
document.getElementById('modalMarcarPago').addEventListener('show.bs.modal', function(event) {
    var button = event.relatedTarget;
    var id = button.getAttribute('data-id');
    document.getElementById('btnConfirmarPago').href = '?pago=' + id;
});
document.getElementById('modalCancelar').addEventListener('show.bs.modal', function(event) {
    var button = event.relatedTarget;
    var id = button.getAttribute('data-id');
    document.getElementById('btnConfirmarCancelar').href = '?cancelar=' + id;
});
document.getElementById('modalExcluir').addEventListener('show.bs.modal', function(event) {
    var button = event.relatedTarget;
    var id = button.getAttribute('data-id');
    document.getElementById('btnConfirmarExcluir').href = '?excluir=' + id;
});
document.getElementById('selectAll').addEventListener('change', function() {
    var checks = document.querySelectorAll('.bulk-check');
    for (var i = 0; i < checks.length; i++) { checks[i].checked = this.checked; }
});
document.getElementById('formNovaFatura').addEventListener('submit', function() {
    var modal = new bootstrap.Modal(document.getElementById('modalCriando'));
    modal.show();
});
</script>

<div class="modal fade" id="modalCriando" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content text-center">
            <div class="modal-body py-4">
                <div class="spinner-border text-primary mb-3" role="status" style="width:3rem;height:3rem;"></div>
                <h6>Criando fatura...</h6>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
