<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

$pdo = getConnection();
$adminId = (int)$_SESSION['admin_id'];

$stmt = $pdo->prepare("SELECT r.*, c.nome_razao, c.cpf_cnpj, f.numero as fatura_numero
    FROM recibos r
    JOIN clientes c ON r.cliente_id = c.id
    JOIN faturas f ON r.fatura_id = f.id
    WHERE r.admin_id = ?
    ORDER BY r.criado_em DESC");
$stmt->execute([$adminId]);
$recibos = $stmt->fetchAll();

// Autoabre o recibo de uma fatura (ícone de recibo em "Recorrentes Ativas").
// Se a fatura está paga mas ainda não tem recibo, gera sob demanda.
$autoReciboId = 0;
$filtroFaturaId = (int)($_GET['fatura_id'] ?? 0);
if ($filtroFaturaId > 0) {
    $stFat = $pdo->prepare("SELECT id FROM faturas WHERE id = ? AND admin_id = ?");
    $stFat->execute([$filtroFaturaId, $adminId]);
    if ($stFat->fetchColumn() > 0) {
        if (!function_exists('garantirReciboFatura')) {
            require_once __DIR__ . '/../config/recibo_pdf.php';
        }
        $gar = garantirReciboFatura(['id' => $filtroFaturaId, 'admin_id' => $adminId]);
        if ($gar) {
            $stmt->execute([$adminId]);
            $recibos = $stmt->fetchAll();
            $autoReciboId = (int)$gar['id'];
        }
    }
}

$pageTitle = 'Recibos Emitidos';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5><i class="fas fa-file-invoice me-2"></i>Recibos Emitidos</h5>
        </div>
        <a href="/cobranca/admin/emitir_recibo.php" class="btn btn-primary btn-sm ms-auto me-2"><i class="fas fa-plus me-1"></i> Emitir Recibo</a>
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
        <div class="table-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0">Histórico de Recibos</h6>
                <span class="badge bg-secondary"><?= count($recibos) ?> recibo(s)</span>
            </div>
            <?php if (empty($recibos)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-receipt fa-3x mb-3 opacity-25"></i>
                    <p>Nenhum recibo emitido ainda.</p>
                    <a href="/cobranca/admin/emitir_recibo.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i> Emitir Primeiro Recibo</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Recibo</th>
                                <th>Fatura</th>
                                <th>Cliente</th>
                                <th>Valor</th>
                                <th>Emissão</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recibos as $r): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($r['numero']) ?></strong></td>
                                <td><?= htmlspecialchars($r['fatura_numero']) ?></td>
                                <td><?= htmlspecialchars($r['nome_razao']) ?></td>
                                <td>R$ <?= number_format((float)$r['valor_recebido'], 2, ',', '.') ?></td>
                                <td><?= date('d/m/Y', strtotime($r['data_emissao'])) ?></td>
                                <td>
                                    <button class="acao-btn acao-btn-primary" onclick="verRecibo(<?= $r['id'] ?>)" title="Visualizar">
                                        <i class="bi bi-file-earmark-text"></i>
                                    </button>
                                    <button class="acao-btn acao-btn-secondary" onclick="imprimirRecibo(<?= $r['id'] ?>)" title="Imprimir / PDF">
                                        <i class="bi bi-printer"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="reciboModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-file-invoice me-2"></i>Pré-visualização do Recibo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="background:#e9ecef;padding:20px;">
                <div style="width:794px;min-height:1123px;background:#fff;box-shadow:0 2px 12px rgba(0,0,0,0.15);margin:0 auto;" id="modalReciboA4">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-success" onclick="imprimirReciboModal()"><i class="fas fa-print me-1"></i> Imprimir / PDF</button>
            </div>
        </div>
    </div>
</div>

<script>
var recibosData = <?= json_encode(array_map(function($r) {
    return ['id' => (int)$r['id'], 'html' => $r['html_gerado'], 'numero' => $r['numero']];
}, $recibos)) ?>;

function verRecibo(id) {
    var recibo = recibosData.find(function(r) { return r.id === id; });
    if (recibo) {
        document.getElementById('modalReciboA4').innerHTML = recibo.html;
        new bootstrap.Modal(document.getElementById('reciboModal')).show();
    }
}

function imprimirRecibo(id) {
    var recibo = recibosData.find(function(r) { return r.id === id; });
    if (recibo) {
        var win = window.open('', '_blank');
        win.document.write('<!DOCTYPE html><html><head><title>Recibo ' + recibo.numero + '</title>');
        win.document.write('<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">');
        win.document.write('<style>@page{size:A4;margin:15mm;}body{margin:0;padding:0;font-family:"Inter","Segoe UI",Arial,sans-serif;}</style>');
        win.document.write('</head><body>');
        win.document.write(recibo.html);
        win.document.write('</body></html>');
        win.document.close();
        setTimeout(function() { win.print(); }, 500);
    }
}

function imprimirReciboModal() {
    var content = document.getElementById('modalReciboA4').innerHTML;
    var win = window.open('', '_blank');
    win.document.write('<!DOCTYPE html><html><head><title>Recibo</title>');
    win.document.write('<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">');
    win.document.write('<style>@page{size:A4;margin:15mm;}body{margin:0;padding:0;font-family:"Inter","Segoe UI",Arial,sans-serif;}</style>');
    win.document.write('</head><body>');
    win.document.write(content);
    win.document.write('</body></html>');
    win.document.close();
    setTimeout(function() { win.print(); }, 500);
}
</script>
<?php if ($autoReciboId > 0): ?>
<script>
document.addEventListener('DOMContentLoaded', function () { verRecibo(<?= (int)$autoReciboId ?>); });
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
