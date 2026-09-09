<?php
// =====================================================
// DEMO - Acesso à conta de demonstração
// =====================================================
require_once __DIR__ . '/_site.php';

$pdo = getConnection();

if (isset($_GET['entrar'])) {
    if ($pdo) {
        $stmt = $pdo->prepare("
            SELECT a.*, ap.data_fim AS plano_fim
            FROM administradores a
            LEFT JOIN admin_planos ap ON ap.admin_id = a.id
            WHERE a.usuario = 'demo' AND a.ativo = 1
            ORDER BY ap.id DESC LIMIT 1
        ");
        $stmt->execute();
        $demo = $stmt->fetch();

        if ($demo && (!empty($demo['plano_fim']) && strtotime($demo['plano_fim']) > strtotime(date('Y-m-d')))) {
            session_regenerate_id(true);
            $_SESSION['admin_id']      = (int)$demo['id'];
            $_SESSION['admin_usuario'] = 'demo';
            $_SESSION['admin_nome']    = $demo['nome'] ?: 'Conta Demo';
            $_SESSION['admin_nivel']   = 'admin';
            $_SESSION['admin_origem']  = 'demo';
            header('Location: /cobranca/admin/index.php');
            exit;
        }
    }
    $falhaDemo = siteT('demo_e_falha');
}

siteHeader();
?>

<section class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-20 text-center">
    <span class="inline-flex items-center gap-2 rounded-full bg-brand-100 text-brand-800 text-xs font-bold px-4 py-1.5">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664zM21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <?= siteT('demo_badge') ?>
    </span>
    <h1 class="mt-6 text-4xl sm:text-5xl font-black text-slate-900 tracking-tight"><?= siteT('demo_t1') ?><br><?= siteT('demo_t2') ?></h1>
    <p class="mt-5 text-lg text-slate-600 max-w-xl mx-auto leading-relaxed">
        <?= siteT('demo_sub') ?>
    </p>

    <div class="mt-8 max-w-md mx-auto rounded-3xl border border-amber-200 bg-amber-50 p-5 text-left text-sm text-amber-800">
        <p class="font-bold flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <?= siteT('demo_nota_t') ?>
        </p>
        <ul class="mt-2 space-y-1 list-disc pl-5">
            <li><?= siteT('demo_li1') ?></li>
            <li><?= siteT('demo_li2') ?></li>
            <li><?= siteT('demo_li3') ?></li>
        </ul>
    </div>

    <?php if (!empty($falhaDemo)): ?>
    <div class="mt-6 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700"><?= htmlspecialchars($falhaDemo) ?></div>
    <?php endif; ?>

    <a href="/cobranca/demo.php?entrar=1" class="mt-10 inline-flex items-center gap-2 rounded-2xl px-10 py-5 text-lg font-black text-white bg-gradient-to-r from-brand-600 to-brand-700 hover:from-brand-700 hover:to-brand-800 shadow-2xl shadow-brand-200 hover:-translate-y-0.5 transition">
        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664zM21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <?= siteT('demo_btn') ?>
    </a>
    <p class="mt-4 text-xs text-slate-400"><?= siteT('demo_pe') ?></p>
</section>

<?php siteFooter(); ?>
</body>
</html>