<?php
// =====================================================
// PLANOS - VITRINE COMPLETA
// =====================================================
require_once __DIR__ . '/_site.php';
$planos = sitePlanos();
siteHeader();
?>

<section class="py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl mx-auto text-center">
            <span class="text-xs font-black uppercase tracking-widest text-brand-600"><?= siteT('plan_badge') ?></span>
            <h1 class="mt-3 text-4xl sm:text-5xl font-black text-slate-900 tracking-tight"><?= siteT('pl_titulo') ?></h1>
            <p class="mt-4 text-lg text-slate-600"><?= siteT('pl_sub') ?></p>
        </div>

        <div class="mt-16 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 items-stretch">
            <?php foreach ($planos as $p):
                $cor = siteCorHex($p['cor'] ?? '');
                $destacado = ($p['slug'] ?? '') === 'ouro' || (count($planos) > 1 && ($p === end($planos)));
            ?>
            <div class="relative flex flex-col rounded-3xl border p-8 transition hover:-translate-y-1.5
                <?= $destacado ? 'border-brand-500 shadow-2xl shadow-brand-100 ring-1 ring-brand-500 bg-gradient-to-b from-brand-50/50 to-white' : 'border-slate-100 bg-white shadow-lg shadow-slate-100/60' ?>">
                <?php if ($destacado): ?>
                <span class="absolute -top-3.5 left-1/2 -translate-x-1/2 rounded-full bg-gradient-to-r from-brand-600 to-brand-700 text-white text-xs font-black px-4 py-1.5 shadow-lg"><?= siteT('pl_popular') ?></span>
                <?php endif; ?>

                <div class="flex items-center justify-between">
                    <span class="rounded-full px-3.5 py-1 text-sm font-bold text-white <?= siteCorCls($cor) ?>"><?= htmlspecialchars($p['nome']) ?></span>
                    <?php if (($p['whatsapp_cobranca'] ?? 0) == 0 && ($p['email_cobranca'] ?? 0) == 0): ?>
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wide"><?= siteT('pl_sem_envios') ?></span>
                    <?php endif; ?>
                </div>

                <div class="mt-6 flex items-baseline gap-1.5">
                    <span class="text-5xl font-black text-slate-900">R$ <?= sitePreco($p['preco']) ?></span>
                    <span class="text-slate-400 font-semibold"><?= siteT('plan_mes') ?></span>
                </div>
                <p class="mt-3 text-sm text-slate-500 leading-relaxed"><?= htmlspecialchars(trim((string)$p['descricao'])) ?></p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-6">
                    <div class="rounded-2xl bg-slate-50 px-4 py-3 text-center">
                        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wide"><?= siteT('pl_wa') ?></p>
                        <p class="mt-0.5 text-sm font-bold <?= ($p['whatsapp_cobranca'] ?? 0) ? 'text-brand-700' : 'text-slate-400' ?>"><?= ($p['whatsapp_cobranca'] ?? 0) ? '✓ ' . siteT('pl_lib') : '— ' . siteT('pl_bloq') ?></p>
                    </div>
                    <div class="rounded-2xl bg-slate-50 px-4 py-3 text-center">
                        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wide"><?= siteT('pl_email') ?></p>
                        <p class="mt-0.5 text-sm font-bold <?= ($p['email_cobranca'] ?? 0) ? 'text-brand-700' : 'text-slate-400' ?>"><?= ($p['email_cobranca'] ?? 0) ? '✓ ' . siteT('pl_lib') : '— ' . siteT('pl_bloq') ?></p>
                    </div>
                </div>

                <div class="mt-6 rounded-2xl border border-slate-100 bg-white p-4">
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wide mb-3 flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                        <?= siteT('pl_gateways') ?>
                    </p>
                    <div class="flex flex-wrap items-center gap-3">
                        <?php foreach (siteGateways() as $g): ?>
                        <img src="<?= htmlspecialchars($g['log']) ?>" alt="<?= htmlspecialchars($g['alt']) ?>" title="<?= htmlspecialchars($g['alt']) ?>"
                             class="<?= $g['cls'] ?> object-contain mix-blend-multiply max-w-[92px]">
                        <?php endforeach; ?>
                    </div>
                </div>

                <a href="/cobranca/cadastro.php?plano=<?= (int)$p['id'] ?>"
                   class="mt-7 block rounded-2xl py-4 text-center font-bold transition
                   <?= $destacado ? 'text-white bg-gradient-to-r from-brand-600 to-brand-700 hover:from-brand-700 hover:to-brand-800 shadow-xl shadow-brand-200' : 'text-white hover:opacity-90 ' . siteCorCls($cor) ?>"><?= siteT('plan_assinar') ?></a>

                <ul class="mt-7 space-y-2.5 text-sm text-slate-600 grow">
                    <?php foreach (siteParseBeneficios($p['beneficios']) as $b):
                        $st = siteBeneficioStatus($b); ?>
                    <li class="flex gap-2.5">
                        <?php if ($st === 'gw'): ?>
                        <span class="w-5 h-5 rounded-full bg-amber-100 text-amber-700 grid place-items-center shrink-0 mt-0.5"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg></span>
                        <?php elseif ($st === 'no'): ?>
                        <span class="w-5 h-5 rounded-full bg-rose-100 text-rose-600 grid place-items-center shrink-0 mt-0.5"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></span>
                        <?php else: ?>
                        <span class="w-5 h-5 rounded-full bg-brand-50 text-brand-600 grid place-items-center shrink-0 mt-0.5"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></span>
                        <?php endif; ?>
                        <span><?= htmlspecialchars($b) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Card demo -->
        <div class="mt-14 rounded-3xl border border-dashed border-slate-300 bg-slate-50 p-8 sm:p-10 flex flex-col sm:flex-row items-center justify-between gap-6">
            <div>
                <h3 class="text-xl font-black text-slate-900"><?= siteT('pl_demo_t') ?></h3>
                <p class="mt-1.5 text-sm text-slate-600"><?= siteT('pl_demo_d') ?></p>
            </div>
            <a href="/cobranca/demo.php" class="shrink-0 inline-flex items-center gap-2 rounded-2xl px-7 py-4 font-bold text-white bg-slate-800 hover:bg-slate-900 transition">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664zM21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <?= siteT('pl_ver_demo') ?>
            </a>
        </div>
    </div>
</section>

<?php siteFooter(); ?>
</body>
</html>