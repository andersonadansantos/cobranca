<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gerar_backup'])) {
    $host = '127.0.0.1';
    $user = 'root';
    $pass = '';
    $dbname = 'cobranca';
    $mysqldump = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';

    if (!file_exists($mysqldump)) {
        $error = 'mysqldump.exe não encontrado em: ' . htmlspecialchars($mysqldump);
    } else {
        $tmpFile = tempnam(sys_get_temp_dir(), 'backup_');
        $cmd = "\"{$mysqldump}\" --host={$host} --user={$user} --password=\"{$pass}\" --single-transaction {$dbname} > \"{$tmpFile}\" 2>&1";
        exec($cmd, $output, $returnCode);

        if ($returnCode === 0 && filesize($tmpFile) > 0) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename=cobranca_backup_' . date('Y-m-d_H-i') . '.sql');
            header('Content-Length: ' . filesize($tmpFile));
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            readfile($tmpFile);
            unlink($tmpFile);
            exit;
        } else {
            $error = 'Erro ao gerar backup: ' . implode("\n", $output);
            if (file_exists($tmpFile)) unlink($tmpFile);
        }
    }
}

$pageTitle = 'Backup';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5>Backup do Banco</h5>
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
        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><?= nl2br(htmlspecialchars($error)) ?></div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-primary text-white">
                        <i class="fas fa-database me-2"></i>Gerar Backup
                    </div>
                    <div class="card-body">
                        <p class="mb-3">Clique no botão abaixo para gerar e baixar um arquivo <code>.sql</code> com todos os dados do banco <strong>cobranca</strong>.</p>
                        <p class="text-muted small mb-3"><i class="fas fa-info-circle me-1"></i>O arquivo conterá todas as tabelas, dados, triggers e routines do banco.</p>
                        <form method="POST">
                            <button type="submit" name="gerar_backup" class="btn btn-primary btn-lg">
                                <i class="fas fa-download me-2"></i>Gerar e Baixar Backup (.sql)
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-info text-white">
                        <i class="fas fa-info-circle me-2"></i>Informações
                    </div>
                    <div class="card-body">
                        <ul class="mb-0">
                            <li>Banco: <strong>cobranca</strong></li>
                            <li>Host: <strong>127.0.0.1</strong></li>
                            <li>Formato: <strong>SQL (mysqldump)</strong></li>
                            <li>Data/Hora: <strong><?= date('d/m/Y H:i') ?></strong></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>