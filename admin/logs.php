<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
requirePlanoAtivo();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

$pdo = getConnection();
$adminId = (int)($_SESSION['admin_id'] ?? 0);

// ---------------------------------------------------------------------
// Filtros da listagem
// ---------------------------------------------------------------------
$fCanal  = trim((string)($_GET['canal'] ?? ''));
$fStatus = trim((string)($_GET['status'] ?? ''));
$fTipo   = trim((string)($_GET['tipo'] ?? ''));
$fBusca  = trim((string)($_GET['busca'] ?? ''));
$fIni    = trim((string)($_GET['data_ini'] ?? ''));
$fFim    = trim((string)($_GET['data_fim'] ?? ''));
$pagina  = max(1, (int)($_GET['pagina'] ?? 1));
$porPagina = 50;

$fCanal  = in_array($fCanal, ['email', 'whatsapp'], true) ? $fCanal : '';
$fStatus = in_array($fStatus, ['enviado', 'erro', 'ignorado'], true) ? $fStatus : '';
$fIni    = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fIni) ? $fIni : '';
$fFim    = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fFim) ? $fFim : '';

$where  = ['e.admin_id = ?'];
$params = [$adminId];

if ($fCanal !== '') {
    $where[] = 'e.canal = ?';
    $params[] = $fCanal;
}
if ($fStatus !== '') {
    $where[] = 'e.status = ?';
    $params[] = $fStatus;
}
if ($fTipo !== '') {
    $where[] = 'e.tipo = ?';
    $params[] = $fTipo;
}
if ($fIni !== '') {
    $where[] = 'e.criado_em >= ?';
    $params[] = $fIni . ' 00:00:00';
}
if ($fFim !== '') {
    $where[] = 'e.criado_em <= ?';
    $params[] = $fFim . ' 23:59:59';
}
if ($fBusca !== '') {
    $where[] = '(e.destino LIKE ? OR e.mensagem LIKE ? OR f.numero LIKE ? OR c.nome_razao LIKE ?)';
    $like = '%' . $fBusca . '%';
    array_push($params, $like, $like, $like, $like);
}

$whereSql = implode(' AND ', $where);

$registros = [];
$totalRegistros = 0;
$resumo = ['total' => 0, 'enviado' => 0, 'erro' => 0, 'ignorado' => 0];
$tiposDisponiveis = [];

if ($pdo) {
    try {
        $tiposDisponiveis = $pdo->prepare("SELECT DISTINCT tipo FROM envios_log WHERE admin_id = ? AND tipo IS NOT NULL AND tipo <> '' ORDER BY tipo");
        $tiposDisponiveis->execute([$adminId]);
        $tiposDisponiveis = $tiposDisponiveis->fetchAll(PDO::FETCH_COLUMN);

        $sqlResumo = "SELECT COUNT(*) AS total,
                             SUM(e.status = 'enviado')  AS enviado,
                             SUM(e.status = 'erro')     AS erro,
                             SUM(e.status = 'ignorado') AS ignorado
                      FROM envios_log e
                      LEFT JOIN faturas f ON f.id = e.fatura_id
                      LEFT JOIN clientes c ON c.id = f.cliente_id
                      WHERE " . $whereSql;
        $stResumo = $pdo->prepare($sqlResumo);
        $stResumo->execute($params);
        $linha = $stResumo->fetch(PDO::FETCH_ASSOC) ?: [];
        $resumo = [
            'total'    => (int)($linha['total'] ?? 0),
            'enviado'  => (int)($linha['enviado'] ?? 0),
            'erro'     => (int)($linha['erro'] ?? 0),
            'ignorado' => (int)($linha['ignorado'] ?? 0),
        ];
        $totalRegistros = $resumo['total'];

        $offset = ($pagina - 1) * $porPagina;
        $sql = "SELECT e.*, f.numero AS fatura_numero, c.nome_razao AS cliente_nome
                FROM envios_log e
                LEFT JOIN faturas f ON f.id = e.fatura_id
                LEFT JOIN clientes c ON c.id = f.cliente_id
                WHERE " . $whereSql . "
                ORDER BY e.criado_em DESC, e.id DESC
                LIMIT " . (int)$porPagina . " OFFSET " . (int)$offset;
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $registros = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $registros = [];
        error_log('[LOGS_ADMIN] ' . $e->getMessage());
    }
}

$totalPaginas = max(1, (int)ceil($totalRegistros / $porPagina));

function logsRotuloTipo($tipo) {
    $mapa = [
        'geracao'                  => 'Geração',
        'antes'                    => 'Antes do vencimento',
        'lembrete1'                => 'Lembrete 1',
        'lembrete2'                => 'Lembrete 2',
        'vencimento'               => 'No vencimento',
        'atraso'                   => 'Em atraso',
        'depois'                   => 'Após o vencimento',
        'pagamento'                => 'Pagamento recebido',
        'pagamento_recebido_admin' => 'Pagamento recebido (admin)',
    ];
    $tipo = (string)$tipo;
    return $mapa[$tipo] ?? ($tipo !== '' ? ucfirst(str_replace('_', ' ', $tipo)) : '—');
}

function logsUrlPagina($pagina) {
    $q = $_GET;
    $q['pagina'] = $pagina;
    return '?' . http_build_query($q);
}

$pageTitle = 'Logs de Envio';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5><i class="fas fa-clipboard-list me-2"></i>Logs de Envio</h5>
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

        <p class="text-muted mb-3" style="font-size:0.85rem;">
            Histórico de todas as mensagens de cobrança enviadas aos clientes da sua conta (e-mail e WhatsApp).
        </p>

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-label">Total de envios</div>
                    <div class="stat-value"><?= (int)$resumo['total'] ?></div>
                    <div class="stat-icon"><i class="fas fa-paper-plane"></i></div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-label">Enviados</div>
                    <div class="stat-value"><?= (int)$resumo['enviado'] ?></div>
                    <div class="stat-icon"><i class="fas fa-circle-check"></i></div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-label">Erros</div>
                    <div class="stat-value"><?= (int)$resumo['erro'] ?></div>
                    <div class="stat-icon"><i class="fas fa-triangle-exclamation"></i></div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-label">Ignorados</div>
                    <div class="stat-value"><?= (int)$resumo['ignorado'] ?></div>
                    <div class="stat-icon"><i class="fas fa-ban"></i></div>
                </div>
            </div>
        </div>

        <div class="form-card mb-4">
            <h6 class="mb-3"><i class="fas fa-filter me-2"></i>Filtros</h6>
            <form method="GET" action="logs.php">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Canal</label>
                        <select name="canal" class="form-select">
                            <option value="">Todos</option>
                            <option value="email" <?= $fCanal === 'email' ? 'selected' : '' ?>>E-mail</option>
                            <option value="whatsapp" <?= $fCanal === 'whatsapp' ? 'selected' : '' ?>>WhatsApp</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">Todos</option>
                            <option value="enviado" <?= $fStatus === 'enviado' ? 'selected' : '' ?>>Enviado</option>
                            <option value="erro" <?= $fStatus === 'erro' ? 'selected' : '' ?>>Erro</option>
                            <option value="ignorado" <?= $fStatus === 'ignorado' ? 'selected' : '' ?>>Ignorado</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tipo</label>
                        <select name="tipo" class="form-select">
                            <option value="">Todos</option>
                            <?php foreach ($tiposDisponiveis as $tipoOpcao): ?>
                                <option value="<?= htmlspecialchars($tipoOpcao) ?>" <?= $fTipo === $tipoOpcao ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(logsRotuloTipo($tipoOpcao)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Buscar</label>
                        <input type="text" name="busca" class="form-control" value="<?= htmlspecialchars($fBusca) ?>" placeholder="Cliente, fatura ou destino">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">De</label>
                        <input type="date" name="data_ini" class="form-control" value="<?= htmlspecialchars($fIni) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Até</label>
                        <input type="date" name="data_fim" class="form-control" value="<?= htmlspecialchars($fFim) ?>">
                    </div>
                    <div class="col-md-6 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i> Filtrar</button>
                        <a href="logs.php" class="btn btn-outline-secondary"><i class="fas fa-eraser me-1"></i> Limpar</a>
                    </div>
                </div>
            </form>
        </div>

        <div class="table-card">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h6 class="mb-0"><i class="fas fa-list me-2"></i>Registros</h6>
                <span class="text-muted" style="font-size:0.8rem;">
                    <?= (int)$totalRegistros ?> registro(s)
                    <?php if ($totalPaginas > 1): ?>
                        &middot; página <?= (int)$pagina ?> de <?= (int)$totalPaginas ?>
                    <?php endif; ?>
                </span>
            </div>

            <?php if (!$registros): ?>
                <div class="text-center text-muted py-5">
                    <i class="fas fa-inbox d-block mb-2" style="font-size:2rem;opacity:.4;"></i>
                    Nenhum envio encontrado com os filtros atuais.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Data/Hora</th>
                                <th>Canal</th>
                                <th>Tipo</th>
                                <th>Cliente / Fatura</th>
                                <th>Destino</th>
                                <th>Status</th>
                                <th>Detalhes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registros as $r): ?>
                                <?php
                                $status = (string)($r['status'] ?? '');
                                $badge = 'bg-secondary';
                                $statusRotulo = ucfirst($status);
                                if ($status === 'enviado') {
                                    $badge = 'bg-success';
                                    $statusRotulo = 'Enviado';
                                } elseif ($status === 'erro') {
                                    $badge = 'bg-danger';
                                    $statusRotulo = 'Erro';
                                } elseif ($status === 'ignorado') {
                                    $badge = 'bg-secondary';
                                    $statusRotulo = 'Ignorado';
                                }
                                $canal = (string)($r['canal'] ?? '');
                                ?>
                                <tr>
                                    <td style="white-space:nowrap;">
                                        <?= $r['criado_em'] ? date('d/m/Y H:i', strtotime($r['criado_em'])) : '—' ?>
                                    </td>
                                    <td>
                                        <?php if ($canal === 'whatsapp'): ?>
                                            <span class="badge bg-success"><i class="fab fa-whatsapp me-1"></i>WhatsApp</span>
                                        <?php elseif ($canal === 'email'): ?>
                                            <span class="badge bg-info text-dark"><i class="fas fa-envelope me-1"></i>E-mail</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($canal ?: '—') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars(logsRotuloTipo($r['tipo'] ?? '')) ?></td>
                                    <td>
                                        <?php if (!empty($r['cliente_nome'])): ?>
                                            <div class="fw-semibold"><?= htmlspecialchars($r['cliente_nome']) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($r['fatura_numero'])): ?>
                                            <div class="text-muted" style="font-size:0.78rem;">Fatura <?= htmlspecialchars($r['fatura_numero']) ?></div>
                                        <?php elseif (!empty($r['fatura_id'])): ?>
                                            <div class="text-muted" style="font-size:0.78rem;">Fatura #<?= (int)$r['fatura_id'] ?></div>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($r['destino'] ?? '') ?: '<span class="text-muted">—</span>' ?></td>
                                    <td><span class="badge <?= $badge ?>"><?= htmlspecialchars($statusRotulo) ?></span></td>
                                    <td style="max-width:260px;"><?= htmlspecialchars($r['mensagem'] ?? '') ?: '<span class="text-muted">—</span>' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPaginas > 1): ?>
                    <nav class="mt-3">
                        <ul class="pagination mb-0">
                            <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= htmlspecialchars(logsUrlPagina(max(1, $pagina - 1))) ?>">Anterior</a>
                            </li>
                            <?php
                            $inicio = max(1, $pagina - 2);
                            $fim = min($totalPaginas, $pagina + 2);
                            for ($p = $inicio; $p <= $fim; $p++):
                            ?>
                                <li class="page-item <?= $p === $pagina ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= htmlspecialchars(logsUrlPagina($p)) ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= htmlspecialchars(logsUrlPagina(min($totalPaginas, $pagina + 1))) ?>">Próxima</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
