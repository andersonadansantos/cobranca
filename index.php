<?php
// =====================================================
// HOME DO SITE PÚBLICO
// =====================================================
require_once __DIR__ . '/_site.php';
$planos = sitePlanos();
siteHeader();
?>

<!-- HERO -->
<section class="relative overflow-hidden bg-gradient-to-b from-brand-50/60 via-white to-white">
    <div class="absolute inset-0 -z-10">
        <div class="absolute -top-32 -left-32 w-96 h-96 rounded-full bg-brand-200/40 blur-3xl"></div>
        <div class="absolute top-10 -right-32 w-96 h-96 rounded-full bg-amber-200/40 blur-3xl"></div>
    </div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-16 pb-24 grid grid-cols-1 lg:grid-cols-2 gap-14 items-center">
        <div>
            <span class="inline-flex items-center gap-2 rounded-full bg-brand-100 text-brand-800 text-xs font-bold px-4 py-1.5">
                <span class="w-2 h-2 rounded-full bg-brand-500 animate-pulse"></span>
                <?= siteT('hero_badge') ?>
            </span>
            <h1 class="anim-entrada mt-6 text-4xl sm:text-5xl lg:text-6xl font-black tracking-tight text-slate-900 leading-[1.08]">
                <?= siteT('hero_t1') ?><br>
                <span class="anim-shimmer"><?= siteT('hero_t2') ?></span>
            </h1>
            <p class="anim-entrada-d mt-6 text-lg text-slate-600 leading-relaxed max-w-xl"><?= siteT('hero_sub') ?></p>
            <div class="mt-8 flex flex-wrap items-center gap-3">
                <a href="/cobranca/cadastro.php" class="inline-flex items-center gap-2 rounded-2xl px-7 py-4 text-base font-bold text-white bg-gradient-to-r from-brand-600 to-brand-700 hover:from-brand-700 hover:to-brand-800 shadow-xl shadow-brand-200 hover:-translate-y-0.5 transition">
                    <?= siteT('hero_btn1') ?>
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </a>
                <a href="/cobranca/planos.php" class="inline-flex items-center gap-2 rounded-2xl px-7 py-4 text-base font-bold text-slate-700 bg-white border border-slate-200 hover:border-brand-300 hover:text-brand-700 transition">
                    <?= siteT('hero_btn2') ?>
                </a>
                <a href="/cobranca/demo.php" class="inline-flex items-center gap-2 rounded-2xl px-4 py-4 text-sm font-bold text-brand-700 hover:text-brand-800 transition">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664zM21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <?= siteT('hero_btn3') ?>
                </a>
            </div>
            <div class="mt-8 flex flex-wrap gap-x-6 gap-y-2 text-sm text-slate-500">
                <span class="inline-flex items-center gap-1.5"><svg class="w-4 h-4 text-brand-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 111.4-1.4L8 12.6l7.3-7.3a1 1 0 011.4 0z" clip-rule="evenodd"/></svg><?= siteT('hero_p1') ?></span>
                <span class="inline-flex items-center gap-1.5"><svg class="w-4 h-4 text-brand-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 111.4-1.4L8 12.6l7.3-7.3a1 1 0 011.4 0z" clip-rule="evenodd"/></svg><?= siteT('hero_p2') ?></span>
                <span class="inline-flex items-center gap-1.5"><svg class="w-4 h-4 text-brand-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 111.4-1.4L8 12.6l7.3-7.3a1 1 0 011.4 0z" clip-rule="evenodd"/></svg><?= siteT('hero_p3') ?></span>
            </div>
        </div>
        <!-- Mockup do painel -->
        <div class="relative">
            <div class="absolute inset-0 bg-gradient-to-br from-brand-400/30 to-amber-300/30 rounded-3xl blur-2xl translate-y-6"></div>
            <div class="relative bg-white rounded-3xl border border-slate-100 shadow-2xl shadow-slate-200/60 p-6 rotate-1 overflow-hidden">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-bold text-slate-800"><?= siteT('mock_titulo') ?></div>
                    <span class="text-xs font-bold text-brand-700 bg-brand-50 rounded-full px-3 py-1"><?= siteT('mock_recebidos') ?> <span id="mock-recebidos-n">128</span></span>
                </div>
                <div class="mt-5" id="mock-feed">
                    <div class="mock-track" id="mock-track">
                        <div class="mock-slide rounded-2xl border border-slate-100 p-3.5 bg-slate-50/60">
                            <div class="flex items-center gap-3">
                                <span class="w-9 h-9 rounded-full bg-brand-100 text-brand-700 grid place-items-center text-sm font-bold">JM</span>
                                <div>
                                    <p class="text-sm font-semibold text-slate-800">João Martins</p>
                                    <p class="text-xs text-slate-400"><?= siteT('mock_fatura') ?>1024</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-sm font-bold text-slate-800">R$ 490,00</span>
                                <span class="text-[10px] font-bold text-green-700 bg-green-100 rounded-full px-2.5 py-1"><?= siteT('mock_pago') ?></span>
                            </div>
                        </div>
                        <div class="mock-slide rounded-2xl border border-slate-100 p-3.5">
                            <div class="flex items-center gap-3">
                                <span class="w-9 h-9 rounded-full bg-amber-100 text-amber-700 grid place-items-center text-sm font-bold">AL</span>
                                <div>
                                    <p class="text-sm font-semibold text-slate-800">Ana Lima</p>
                                    <p class="text-xs text-slate-400"><?= siteT('mock_fatura') ?>1023</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-sm font-bold text-slate-800">R$ 1.250,00</span>
                                <span class="text-[10px] font-bold text-amber-700 bg-amber-100 rounded-full px-2.5 py-1"><?= siteT('mock_lembrete') ?></span>
                            </div>
                        </div>
                        <div class="mock-slide rounded-2xl border border-slate-100 p-3.5">
                            <div class="flex items-center gap-3">
                                <span class="w-9 h-9 rounded-full bg-rose-100 text-rose-700 grid place-items-center text-sm font-bold">RS</span>
                                <div>
                                    <p class="text-sm font-semibold text-slate-800">Rafael Souza</p>
                                    <p class="text-xs text-slate-400"><?= siteT('mock_fatura') ?>1022<?= siteT('mock_venc') ?></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-sm font-bold text-slate-800">R$ 320,00</span>
                                <span class="text-[10px] font-bold text-rose-700 bg-rose-100 rounded-full px-2.5 py-1"><?= siteT('mock_cobranca') ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-5 flex items-center justify-between rounded-2xl bg-gradient-to-r from-brand-600 to-brand-800 text-white p-4">
                    <span class="text-sm font-semibold"><?= siteT('mock_auto') ?></span>
                    <span class="text-xs font-bold bg-white/20 rounded-full px-3 py-1"><?= siteT('mock_wa') ?> ✓</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Rodapé do herói: bancos aceitos -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-12">
        <div class="border-t border-slate-200/70 pt-8 flex flex-col items-center gap-6">
            <p class="text-xs font-bold uppercase tracking-widest text-slate-400 flex items-center gap-2">
                <svg class="w-4 h-4 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                <?= siteT('bancos_titulo') ?>
            </p>
            <div class="flex flex-wrap justify-center items-center gap-3">
                <?php $bancos = [
                    ['log' => '/cobranca/assets/img/pix-logo.svg',            'alt' => 'PIX',          'cls' => 'h-9 w-auto'],
                    ['log' => '/cobranca/assets/img/mercado-pago-logo.png',   'alt' => 'Mercado Pago', 'cls' => 'h-9 w-auto'],
                    ['log' => '/cobranca/assets/img/banco-inter-logo-0-1.png','alt' => 'Banco Inter',   'cls' => 'h-9 w-auto'],
                    ['log' => '/cobranca/assets/img/asaas-logo.svg',          'alt' => 'Asaas',        'cls' => 'h-8 w-auto'],
                ]; ?>
                <?php foreach ($bancos as $b): ?>
                <span class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 py-2.5 shadow-sm hover:shadow-md hover:-translate-y-0.5 hover:border-brand-200 transition">
                    <img src="<?= htmlspecialchars($b['log']) ?>" alt="<?= htmlspecialchars($b['alt']) ?>" title="<?= htmlspecialchars($b['alt']) ?>"
                         class="<?= $b['cls'] ?> object-contain max-w-[170px] mix-blend-multiply">
                </span>
                <?php endforeach; ?>
                <span class="inline-flex items-center gap-2 rounded-full text-xs font-semibold text-slate-400 px-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-brand-500"></span> <?= siteT('bancos_apps') ?>
                </span>
            </div>
        </div>
    </div>
</section>

<!-- PROVA / NÚMEROS -->
<section class="bg-slate-950 text-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 grid grid-cols-2 lg:grid-cols-4 gap-8 text-center reveal-init">
        <div><p class="text-3xl font-black text-brand-400">+2.400</p><p class="mt-1 text-sm text-slate-400"><?= siteT('st1') ?></p></div>
        <div><p class="text-3xl font-black text-brand-400">R$ 38 mi</p><p class="mt-1 text-sm text-slate-400"><?= siteT('st2') ?></p></div>
        <div><p class="text-3xl font-black text-brand-400">73%</p><p class="mt-1 text-sm text-slate-400"><?= siteT('st3') ?></p></div>
        <div><p class="text-3xl font-black text-brand-400">24h</p><p class="mt-1 text-sm text-slate-400"><?= siteT('st4') ?></p></div>
    </div>
</section>

<!-- COMO FUNCIONA -->
<section id="como-funciona" class="py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl mx-auto text-center reveal-init">
            <span class="text-xs font-black uppercase tracking-widest text-brand-600"><?= siteT('cf_badge') ?></span>
            <h2 class="mt-3 text-3xl sm:text-4xl font-black text-slate-900"><?= siteT('cf_titulo') ?></h2>
        </div>
        <div class="mt-14 grid grid-cols-1 md:grid-cols-3 gap-8 reveal-init">
            <div class="relative rounded-3xl border border-slate-100 p-8 bg-gradient-to-b from-white to-slate-50 hover:shadow-xl hover:shadow-slate-100 transition">
                <span class="inline-flex w-12 h-12 items-center justify-center rounded-2xl bg-brand-600 text-white text-lg font-black shadow-lg shadow-brand-200">1</span>
                <h3 class="mt-5 text-xl font-bold text-slate-900"><?= siteT('cf_n1') ?></h3>
                <p class="mt-2 text-sm text-slate-600 leading-relaxed"><?= siteT('cf_d1') ?></p>
            </div>
            <div class="relative rounded-3xl border border-slate-100 p-8 bg-gradient-to-b from-white to-slate-50 hover:shadow-xl hover:shadow-slate-100 transition">
                <span class="inline-flex w-12 h-12 items-center justify-center rounded-2xl bg-amber-500 text-white text-lg font-black shadow-lg shadow-amber-200">2</span>
                <h3 class="mt-5 text-xl font-bold text-slate-900"><?= siteT('cf_n2') ?></h3>
                <p class="mt-2 text-sm text-slate-600 leading-relaxed"><?= siteT('cf_d2') ?></p>
            </div>
            <div class="relative rounded-3xl border border-slate-100 p-8 bg-gradient-to-b from-white to-slate-50 hover:shadow-xl hover:shadow-slate-100 transition">
                <span class="inline-flex w-12 h-12 items-center justify-center rounded-2xl bg-brand-700 text-white text-lg font-black shadow-lg shadow-brand-200">3</span>
                <h3 class="mt-5 text-xl font-bold text-slate-900"><?= siteT('cf_n3') ?></h3>
                <p class="mt-2 text-sm text-slate-600 leading-relaxed"><?= siteT('cf_d3') ?></p>
            </div>
        </div>
    </div>
</section>

<!-- RECURSOS -->
<section id="recursos" class="py-24 bg-slate-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl reveal-init">
            <span class="text-xs font-black uppercase tracking-widest text-brand-600"><?= siteT('rec_badge') ?></span>
            <h2 class="mt-3 text-3xl sm:text-4xl font-black text-slate-900"><?= siteT('rec_titulo') ?></h2>
        </div>
        <div class="mt-14 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 reveal-init">
            <div class="rounded-3xl bg-white border border-slate-100 p-7">
                <span class="inline-flex w-11 h-11 rounded-xl bg-brand-100 text-brand-700 items-center justify-center"><svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg></span>
                <h3 class="mt-4 font-bold text-slate-900"><?= siteT('r1_t') ?></h3>
                <p class="mt-2 text-sm text-slate-600"><?= siteT('r1_d') ?></p>
            </div>
            <div class="rounded-3xl bg-white border border-slate-100 p-7">
                <span class="inline-flex w-11 h-11 rounded-xl bg-green-100 text-green-700 items-center justify-center"><svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg></span>
                <h3 class="mt-4 font-bold text-slate-900"><?= siteT('r2_t') ?></h3>
                <p class="mt-2 text-sm text-slate-600"><?= siteT('r2_d') ?></p>
            </div>
            <div class="rounded-3xl bg-white border border-slate-100 p-7">
                <span class="inline-flex w-11 h-11 rounded-xl bg-amber-100 text-amber-700 items-center justify-center"><svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg></span>
                <h3 class="mt-4 font-bold text-slate-900"><?= siteT('r3_t') ?></h3>
                <p class="mt-2 text-sm text-slate-600"><?= siteT('r3_d') ?></p>
            </div>
            <div class="rounded-3xl bg-white border border-slate-100 p-7">
                <span class="inline-flex w-11 h-11 rounded-xl bg-brand-100 text-brand-700 items-center justify-center"><svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg></span>
                <h3 class="mt-4 font-bold text-slate-900"><?= siteT('r4_t') ?></h3>
                <p class="mt-2 text-sm text-slate-600"><?= siteT('r4_d') ?></p>
            </div>
            <div class="rounded-3xl bg-white border border-slate-100 p-7">
                <span class="inline-flex w-11 h-11 rounded-xl bg-rose-100 text-rose-700 items-center justify-center"><svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg></span>
                <h3 class="mt-4 font-bold text-slate-900"><?= siteT('r5_t') ?></h3>
                <p class="mt-2 text-sm text-slate-600"><?= siteT('r5_d') ?></p>
            </div>
            <div class="rounded-3xl bg-white border border-slate-100 p-7">
                <span class="inline-flex w-11 h-11 rounded-xl bg-brand-100 text-brand-700 items-center justify-center"><svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg></span>
                <h3 class="mt-4 font-bold text-slate-900"><?= siteT('r6_t') ?></h3>
                <p class="mt-2 text-sm text-slate-600"><?= siteT('r6_d') ?></p>
            </div>
        </div>
    </div>
</section>

<!-- PLANOS (vitrine) -->
<?php if (count($planos) > 0): ?>
<section id="planos" class="py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl mx-auto text-center reveal-init">
            <span class="text-xs font-black uppercase tracking-widest text-brand-600"><?= siteT('plan_badge') ?></span>
            <h2 class="mt-3 text-3xl sm:text-4xl font-black text-slate-900"><?= siteT('plan_titulo') ?></h2>
            <p class="mt-3 text-slate-600"><?= siteT('plan_sub') ?></p>
        </div>
        <div class="mt-14 grid grid-cols-1 md:grid-cols-3 gap-6 reveal-init">
            <?php foreach ($planos as $p): $cor = siteCorHex($p['cor'] ?? ''); ?>
            <div class="relative rounded-3xl border border-slate-100 bg-white p-8 shadow-lg shadow-slate-100/60 flex flex-col hover:-translate-y-1 transition">
                <span class="inline-flex self-start rounded-full px-3 py-1 text-xs font-bold text-white <?= siteCorCls($cor) ?>"><?= htmlspecialchars($p['nome']) ?></span>
                <div class="mt-5">
                    <div class="flex items-baseline gap-1">
                        <span class="text-4xl font-black text-slate-900">R$ <?= sitePreco($p['preco']) ?></span>
                        <span class="text-sm text-slate-400"><?= siteT('plan_mes') ?></span>
                    </div>
                    <p class="mt-2 text-xs text-slate-500"><?= htmlspecialchars(trim((string)$p['descricao'])) ?></p>
                </div>
                <div class="mt-5 rounded-2xl border border-slate-100 bg-white p-4">
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
                <a href="/cobranca/cadastro.php?plano=<?= (int)$p['id'] ?>" class="mt-6 rounded-2xl py-3 text-center font-bold text-white <?= siteCorCls($cor) ?> hover:opacity-90 transition"><?= siteT('plan_assinar') ?></a>
                <ul class="mt-6 space-y-2.5 text-sm text-slate-600">
                    <?php foreach (siteParseBeneficios($p['beneficios']) as $b):
                        if (siteBeneficioStatus($b) !== 'ok') continue; ?>
                    <li class="flex gap-2.5"><span class="w-5 h-5 rounded-full bg-brand-50 text-brand-600 grid place-items-center shrink-0 mt-0.5"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></span><?= htmlspecialchars($b) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="mt-10 text-center">
            <a href="/cobranca/demo.php" class="inline-flex items-center gap-2 font-bold text-brand-700 hover:text-brand-800">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664zM21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <?= siteT('plan_demo_link') ?>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- DEPOIMENTOS -->
<section class="py-24 bg-slate-950 text-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl mx-auto text-center reveal-init">
            <span class="text-xs font-black uppercase tracking-widest text-brand-400"><?= siteT('dep_badge') ?></span>
            <h2 class="mt-3 text-3xl sm:text-4xl font-black"><?= siteT('dep_titulo') ?></h2>
        </div>
        <div class="mt-14 grid grid-cols-1 md:grid-cols-3 gap-6 reveal-init">
            <div class="rounded-3xl border border-white/10 bg-white/5 p-7">
                <div class="flex text-amber-400 gap-0.5"><?= str_repeat('★', 5) ?></div>
                <p class="mt-4 text-sm leading-relaxed text-slate-300">"<?= siteT('dep1_q') ?>"</p>
                <div class="mt-5 flex items-center gap-3">
                    <span class="w-10 h-10 rounded-full bg-brand-500 grid place-items-center font-bold">CM</span>
                    <div><p class="text-sm font-bold">Carla Mendes</p><p class="text-xs text-slate-400"><?= siteT('dep1_c') ?></p></div>
                </div>
            </div>
            <div class="rounded-3xl border border-white/10 bg-white/5 p-7">
                <div class="flex text-amber-400 gap-0.5"><?= str_repeat('★', 5) ?></div>
                <p class="mt-4 text-sm leading-relaxed text-slate-300">"<?= siteT('dep2_q') ?>"</p>
                <div class="mt-5 flex items-center gap-3">
                    <span class="w-10 h-10 rounded-full bg-amber-500 grid place-items-center font-bold">RT</span>
                    <div><p class="text-sm font-bold">Rafael Torres</p><p class="text-xs text-slate-400"><?= siteT('dep2_c') ?></p></div>
                </div>
            </div>
            <div class="rounded-3xl border border-white/10 bg-white/5 p-7">
                <div class="flex text-amber-400 gap-0.5"><?= str_repeat('★', 5) ?></div>
                <p class="mt-4 text-sm leading-relaxed text-slate-300">"<?= siteT('dep3_q') ?>"</p>
                <div class="mt-5 flex items-center gap-3">
                    <span class="w-10 h-10 rounded-full bg-brand-500 grid place-items-center font-bold">PS</span>
                    <div><p class="text-sm font-bold">Patrícia Salles</p><p class="text-xs text-slate-400"><?= siteT('dep3_c') ?></p></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FAQ -->
<section id="faq" class="py-24">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center reveal-init">
            <span class="text-xs font-black uppercase tracking-widest text-brand-600"><?= siteT('faq_badge') ?></span>
            <h2 class="mt-3 text-3xl sm:text-4xl font-black text-slate-900"><?= siteT('faq_titulo') ?></h2>
        </div>
        <div class="mt-12 space-y-4 reveal-init">
            <?php $faq = [
                [siteT('faq1_q'), siteT('faq1_a')],
                [siteT('faq2_q'), siteT('faq2_a')],
                [siteT('faq3_q'), siteT('faq3_a')],
                [siteT('faq4_q'), siteT('faq4_a')],
                [siteT('faq5_q'), siteT('faq5_a')],
            ]; ?>
            <?php foreach ($faq as $i => $f): ?>
            <details class="group rounded-2xl border border-slate-200 bg-white open:bg-slate-50 px-6 py-5 transition">
                <summary class="flex items-center justify-between cursor-pointer list-none font-bold text-slate-900">
                    <?= htmlspecialchars($f[0]) ?>
                    <span class="ml-4 shrink-0 w-6 h-6 rounded-full bg-slate-100 group-open:bg-brand-600 group-open:text-white grid place-items-center text-slate-600 transition"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg></span>
                </summary>
                <p class="mt-4 text-sm leading-relaxed text-slate-600"><?= htmlspecialchars($f[1]) ?></p>
            </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CTA FINAL -->
<section class="pb-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="relative overflow-hidden rounded-[2.5rem] bg-gradient-to-br from-brand-700 via-brand-800 to-slate-900 text-white px-8 py-16 sm:px-16 text-center reveal-init">
            <div class="absolute -top-20 -right-20 w-72 h-72 rounded-full bg-brand-400/20 blur-3xl"></div>
            <div class="absolute -bottom-24 -left-10 w-72 h-72 rounded-full bg-amber-400/20 blur-3xl"></div>
            <h2 class="relative text-3xl sm:text-5xl font-black tracking-tight"><?= siteT('cta_titulo') ?></h2>
            <p class="relative mt-5 text-slate-200 max-w-xl mx-auto"><?= siteT('cta_sub') ?></p>
            <div class="relative mt-8 flex flex-wrap justify-center gap-3">
                <a href="/cobranca/cadastro.php" class="inline-flex items-center gap-2 rounded-2xl px-8 py-4 font-bold text-brand-900 bg-white hover:bg-brand-50 shadow-xl transition"><?= siteT('cta_btn1') ?>
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </a>
                <a href="/cobranca/demo.php" class="inline-flex items-center gap-2 rounded-2xl px-8 py-4 font-bold text-white border border-white/30 hover:bg-white/10 transition"><?= siteT('cta_btn2') ?></a>
            </div>
        </div>
    </div>
</section>

<?php
$mockStLabel = ['pago' => 'mock_pago', 'lembrete' => 'mock_lembrete', 'cobranca' => 'mock_cobranca'];
$mockStCls    = ['pago' => 'text-green-700 bg-green-100', 'lembrete' => 'text-amber-700 bg-amber-100', 'cobranca' => 'text-rose-700 bg-rose-100'];
$mockPool = [
    ['ini' => 'JM', 'cor' => 'bg-brand-100 text-brand-700', 'nome' => 'João Martins',    'num' => '1024', 'val' => '490,00',   'st' => 'pago'],
    ['ini' => 'AL', 'cor' => 'bg-amber-100 text-amber-700', 'nome' => 'Ana Lima',        'num' => '1023', 'val' => '1.250,00', 'st' => 'lembrete'],
    ['ini' => 'RS', 'cor' => 'bg-rose-100 text-rose-700',   'nome' => 'Rafael Souza',    'num' => '1022', 'val' => '320,00',   'st' => 'cobranca'],
];
$mockExtra = [
    ['ini' => 'CM', 'cor' => 'bg-brand-100 text-brand-700', 'nome' => 'Carla Mendes',    'num' => '1025', 'val' => '1.890,00', 'st' => 'pago'],
    ['ini' => 'RT', 'cor' => 'bg-amber-100 text-amber-700', 'nome' => 'Rafael Torres',   'num' => '1021', 'val' => '750,00',   'st' => 'lembrete'],
    ['ini' => 'PS', 'cor' => 'bg-rose-100 text-rose-700',   'nome' => 'Patrícia Salles', 'num' => '1020', 'val' => '2.100,00', 'st' => 'cobranca'],
    ['ini' => 'BS', 'cor' => 'bg-brand-100 text-brand-700', 'nome' => 'Bruno Santana',   'num' => '1026', 'val' => '600,00',   'st' => 'pago'],
    ['ini' => 'FV', 'cor' => 'bg-amber-100 text-amber-700', 'nome' => 'Fernanda Vaz',    'num' => '1019', 'val' => '430,00',   'st' => 'lembrete'],
    ['ini' => 'LG', 'cor' => 'bg-rose-100 text-rose-700',   'nome' => 'Lucas Gomes',     'num' => '1018', 'val' => '980,00',   'st' => 'cobranca'],
];
$mockTodos = array_merge($mockPool, $mockExtra);
$mockLinhas = [];
foreach ($mockTodos as $m) {
    $num = siteT('mock_fatura') . $m['num'] . ($m['st'] === 'cobranca' ? siteT('mock_venc') : '');
    $mockLinhas[] = '<div class="flex items-center gap-3">'
        . '<span class="w-9 h-9 rounded-full ' . $m['cor'] . ' grid place-items-center text-sm font-bold">' . $m['ini'] . '</span>'
        . '<div><p class="text-sm font-semibold text-slate-800">' . $m['nome'] . '</p>'
        . '<p class="text-xs text-slate-400">' . $num . '</p></div></div>'
        . '<div class="flex items-center gap-3">'
        . '<span class="text-sm font-bold text-slate-800">R$ ' . $m['val'] . '</span>'
        . '<span class="text-[10px] font-bold ' . $mockStCls[$m['st']] . ' rounded-full px-2.5 py-1">' . siteT($mockStLabel[$m['st']]) . '</span></div>';
}
?>
<script>
(function () {
    var linhas = <?= json_encode($mockLinhas, JSON_UNESCAPED_UNICODE) ?>;
    var feed = document.getElementById('mock-feed');
    var track = document.getElementById('mock-track');
    var rec = document.getElementById('mock-recebidos-n');
    var n = parseInt(rec ? rec.textContent : 128, 10) || 128;
    if (!feed || !track || !linhas.length) return;

    var gap = 12;
    var idx = 0;
    var rolante = true;
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        rolante = false;
    }

    function criarSlide() {
        var d = document.createElement('div');
        d.className = 'mock-slide rounded-2xl border border-slate-100 p-3.5';
        return d;
    }

    function configurar() {
        while (track.children.length < 4) track.appendChild(criarSlide());
        for (var k = 0; k < track.children.length; k++) {
            track.children[k].innerHTML = linhas[idx % linhas.length];
            idx++;
        }
        medir();
    }

    function medir() {
        var h = track.children[0].offsetHeight;
        feed.style.height = (h * 3 + gap * 2) + 'px';
    }

    function passo() {
        var h = track.children[0].offsetHeight + gap;
        track.style.transition = 'transform .65s ease';
        track.style.transform = 'translateY(-' + h + 'px)';
        setTimeout(function () {
            track.removeChild(track.children[0]);
            var d = criarSlide();
            d.innerHTML = linhas[idx % linhas.length];
            idx++;
            track.appendChild(d);
            track.style.transition = 'none';
            track.style.transform = 'translateY(0)';
            void track.offsetWidth;
            track.style.transition = '';
        }, 680);
    }

    function troca() {
        track.style.transition = '';
        track.style.transform = 'translateY(0)';
        track.removeChild(track.children[0]);
        var d = criarSlide();
        d.innerHTML = linhas[idx % linhas.length];
        idx++;
        track.appendChild(d);
        void track.offsetWidth;
    }

    configurar();
    window.addEventListener('resize', medir);
    if (rolante) {
        setInterval(passo, 3600);
    } else {
        setInterval(troca, 4200);
    }
    setInterval(function () {
        if (Math.random() < 0.7) { n++; if (rec) rec.textContent = n; }
    }, 6000);
})();
</script>

<?php siteFooter(); ?>
</body>
</html>