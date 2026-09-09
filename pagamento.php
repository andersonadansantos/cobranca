<?php
// =====================================================
// PAGAMENTO DO PLANO (pós-cadastro no site)
// Métodos por país: Brasil = PIX, Boleto e Cartão.
// Fora do Brasil = somente Cartão (crédito/débito internacional).
// =====================================================
require_once __DIR__ . '/_site.php';
require_once __DIR__ . '/config/mercadopago.php';
require_once __DIR__ . '/config/inter_pix.php';

// Requer cadastro/sessão ativa
if (!siteLogado()) {
    header('Location: /cobranca/cadastro.php' . (isset($_GET['plano']) ? '?plano=' . (int)$_GET['plano'] : ''));
    exit;
}

$adminId       = (int)$_SESSION['admin_id'];
$adminNome     = $_SESSION['admin_nome'] ?? 'Cliente';
$planoId       = (int)($_GET['plano'] ?? 0);

$plano = null;
if ($planoId > 0 && $pdo = getConnection()) {
    $stmt = $pdo->prepare("SELECT * FROM planos WHERE id = ? AND ativo = 1 AND slug <> 'demo'");
    $stmt->execute([$planoId]);
    $plano = $stmt->fetch() ?: null;
}
if (!$plano) {
    header('Location: /cobranca/planos.php');
    exit;
}

// === Métodos liberados conforme o país do visitante ===
$metodos    = sitePlanosMetodos();
$pixOk      = in_array('pix', $metodos, true);
$boletoOk   = in_array('boleto', $metodos, true);
$cartaoOk   = in_array('cartao', $metodos, true);
$maxParcelas = $cartaoOk ? siteCartaoParcelasMax() : 0;

// Public key do Mercado Pago do Super Admin (frontend do cartão)
$mpPublicKey = '';
if ($cartaoOk) {
    $superMp = getMPConfigSuper();
    $mpPublicKey = $superMp['super_mp_public_key'] ?? '';
}

// CPF/CNPJ do pagador (dono da conta) para tokenizar o cartão
$adminCpfCnpj = '';
if ($pdo = getConnection()) {
    $stmt = $pdo->prepare("SELECT cpf, cnpj FROM administradores WHERE id = ?");
    $stmt->execute([$adminId]);
    $row = $stmt->fetch();
    $adminCpfCnpj = $row ? preg_replace('/[^0-9]/', '', (($row['cnpj'] ?: '') ?: ($row['cpf'] ?? ''))) : '';
}

// Tenta reutilizar uma fatura PIX pendente recente deste admin/plano
$pagamentoPendente = null;
if ($pixOk && ($pdo = getConnection())) {
    $stmt = $pdo->prepare("SELECT * FROM planos_pagamentos WHERE admin_id = ? AND plano_id = ? AND status = 'pendente' AND metodo = 'pix' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$adminId, $planoId]);
    $pagamentoPendente = $stmt->fetch() ?: null;
}

siteHeader();
?>

<section class="py-16 min-h-screen bg-gradient-to-b from-brand-50/50 to-white">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center">
            <span class="inline-flex items-center gap-2 rounded-full bg-brand-100 text-brand-800 text-xs font-bold px-4 py-1.5">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                <?= siteT('pag_plano') ?> <?= htmlspecialchars($plano['nome']) ?>
            </span>
            <h1 class="mt-5 text-3xl sm:text-4xl font-black text-slate-900 tracking-tight"><?= siteT('pag_quase') ?>, <?= htmlspecialchars(explode(' ', $adminNome)[0]) ?>!</h1>
            <p class="mt-3 text-slate-600"><?= siteT('pag_sub') ?></p>
        </div>

        <!-- Resumo -->
        <div class="mt-10 rounded-3xl border border-slate-100 bg-white p-6 sm:p-8 shadow-xl shadow-slate-100/70 flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-slate-400"><?= siteT('pag_plano_rot') ?> <?= htmlspecialchars($plano['nome']) ?> · <?= siteT('pag_mensal') ?></p>
                <p class="mt-1 text-4xl font-black text-slate-900">R$ <?= sitePreco($plano['preco']) ?></p>
            </div>
            <span class="rounded-full bg-amber-100 text-amber-800 text-xs font-bold px-4 py-2"><?= siteT('pag_unico') ?></span>
        </div>

        <?php if (!siteEhBrasil() && siteNoMetodoPlano('cartao')): ?>
        <div class="mt-4 flex items-start gap-3 rounded-2xl border border-slate-200 bg-sky-50 px-4 py-3 text-sm text-slate-600">
            <svg class="w-5 h-5 text-sky-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span><?= siteT('pag_note_int') ?></span>
        </div>
        <?php endif; ?>

        <!-- Estágio de carregamento -->
        <div id="estagio-carregando" class="mt-8 rounded-3xl border border-slate-100 bg-white p-10 text-center shadow-sm">
            <svg class="w-10 h-10 mx-auto text-brand-600 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>
            <p class="mt-4 text-sm font-semibold text-slate-600"><?= siteT('pag_gerando') ?></p>
        </div>

        <!-- Estágio: escolha do método -->
        <div id="estagio-metodo" class="hidden mt-8 rounded-3xl border border-slate-100 bg-white p-6 sm:p-8 shadow-xl shadow-slate-100/70">
            <p class="text-sm font-black text-slate-900 uppercase tracking-wider"><?= siteT('pag_metodo') ?></p>
            <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
                <button type="button" data-metodo="pix" onclick="escolherMetodo('pix')" id="mt-pix"
                    class="opcao-metodo <?= $pixOk ? '' : 'hidden' ?> text-left rounded-2xl border-2 border-slate-100 bg-white p-4 hover:border-brand-400 hover:bg-brand-50/40 transition">
                    <span class="inline-flex w-10 h-10 rounded-full bg-green-100 text-green-700 items-center justify-center">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    </span>
                    <span class="mt-3 block text-sm font-bold text-slate-800"><?= siteT('pag_pix') ?></span>
                    <span class="mt-0.5 block text-xs text-slate-500"><?= siteT('pag_pix_d') ?></span>
                </button>
                <button type="button" data-metodo="boleto" onclick="escolherMetodo('boleto')" id="mt-boleto"
                    class="opcao-metodo <?= $boletoOk ? '' : 'hidden' ?> text-left rounded-2xl border-2 border-slate-100 bg-white p-4 hover:border-brand-400 hover:bg-brand-50/40 transition">
                    <span class="inline-flex w-10 h-10 rounded-full bg-amber-100 text-amber-700 items-center justify-center">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    </span>
                    <span class="mt-3 block text-sm font-bold text-slate-800"><?= siteT('pag_boleto') ?></span>
                    <span class="mt-0.5 block text-xs text-slate-500"><?= siteT('pag_boleto_d') ?></span>
                </button>
                <button type="button" data-metodo="cartao" onclick="escolherMetodo('cartao')" id="mt-cartao"
                    class="opcao-metodo <?= $cartaoOk ? '' : 'hidden' ?> text-left rounded-2xl border-2 border-slate-100 bg-white p-4 hover:border-brand-400 hover:bg-brand-50/40 transition">
                    <span class="inline-flex w-10 h-10 rounded-full bg-indigo-100 text-indigo-700 items-center justify-center">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1M7 7h10a2 2 0 012 2v6a2 2 0 01-2 2H7a2 2 0 01-2-2V9a2 2 0 012-2z"/></svg>
                    </span>
                    <span class="mt-3 block text-sm font-bold text-slate-800"><?= siteT('pag_cartao') ?></span>
                    <span class="mt-0.5 block text-xs text-slate-500"><?= siteT('pag_cartao_d') ?></span>
                </button>
            </div>
            <button type="button" onclick="voltarMetodos()" class="mt-5 text-sm font-bold text-brand-700 hover:text-brand-800" id="voltar-metodos">
                ← <?= siteT('pag_metodo') ?>
            </button>
        </div>

        <!-- Estágio do PIX -->
        <div id="estagio-pix" class="hidden mt-8 rounded-3xl border border-slate-100 bg-white p-8 shadow-xl shadow-slate-100/70">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-8 items-center">
                <div class="text-center">
                    <div id="pix-qr" class="mx-auto w-56 h-56 rounded-2xl bg-slate-50 grid place-items-center border border-slate-100"></div>
                    <p class="mt-3 text-xs text-slate-400"><?= siteT('pag_escan') ?></p>
                </div>
                <div>
                    <p class="text-sm font-bold text-slate-800"><?= siteT('pag_copia') ?></p>
                    <textarea id="pix-copia-cola" readonly rows="4"
                              class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs break-all focus:outline-none"></textarea>
                    <button id="btn-copiar" type="button" class="mt-3 w-full rounded-2xl py-3 text-sm font-bold text-white bg-slate-800 hover:bg-slate-900 transition"><?= siteT('pag_btn_copiar') ?></button>
                    <button id="btn-paguei" type="button" class="mt-2 w-full rounded-2xl py-3 text-sm font-bold text-brand-700 border border-brand-200 hover:bg-brand-50 transition"><?= siteT('pag_btn_paguei') ?></button>
                </div>
            </div>
            <p class="mt-6 text-center text-xs text-slate-400"><?= siteT('pag_auto') ?></p>
            <div class="mt-6 flex items-center gap-3 rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
                <svg class="w-5 h-5 text-brand-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <?= siteT('pag_pend') ?>
            </div>
        </div>

        <!-- Estágio do boleto -->
        <div id="estagio-boleto" class="hidden mt-8 rounded-3xl border border-slate-100 bg-white p-8 shadow-xl shadow-slate-100/70">
            <div class="flex items-center gap-3 rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span><?= siteT('pag_pend') ?></span>
            </div>
            <div class="mt-6 text-center">
                <a id="boleto-link" href="#" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-2 rounded-2xl px-8 py-4 text-sm font-black text-white bg-gradient-to-r from-brand-600 to-brand-700 hover:from-brand-700 hover:to-brand-800 shadow-xl shadow-brand-200 transition">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                    <?= siteT('pag_b_abrir') ?>
                </a>
            </div>
            <div class="mt-6">
                <p class="text-sm font-bold text-slate-800"><?= siteT('pag_b_linha') ?></p>
                <div id="boleto-linha-digitavel"
                     class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs font-mono break-all text-slate-700"></div>
                <button id="btn-copiar-boleto" type="button" class="mt-3 w-full rounded-2xl py-3 text-sm font-bold text-white bg-slate-800 hover:bg-slate-900 transition"><?= siteT('pag_btn_copiar') ?></button>
                <button id="btn-boleto-paguei" type="button" class="mt-2 w-full rounded-2xl py-3 text-sm font-bold text-brand-700 border border-brand-200 hover:bg-brand-50 transition"><?= siteT('pag_b_paguei') ?></button>
            </div>
        </div>

        <!-- Estágio do cartão -->
        <div id="estagio-cartao" class="hidden mt-8 rounded-3xl border border-slate-100 bg-white p-6 sm:p-8 shadow-xl shadow-slate-100/70">
            <div class="flex rounded-2xl bg-slate-100 p-1.5 text-sm font-bold">
                <button type="button" id="tabCredito" class="flex-1 rounded-xl py-2.5 text-brand-700 bg-white shadow-sm"><?= siteT('pag_cc_credito') ?></button>
                <button type="button" id="tabDebito" class="flex-1 rounded-xl py-2.5 text-slate-500"><?= siteT('pag_cc_debito') ?></button>
            </div>
            <p id="ccMsg" class="small mb-2 mt-4 text-sm text-slate-600"></p>
            <form id="ccForm" class="mt-2 space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700"><?= siteT('pag_cc_num') ?></label>
                    <input id="ccNumero" inputmode="numeric" autocomplete="cc-number" placeholder="1234 5678 9012 3456"
                           class="mt-1.5 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-100 outline-none transition">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700"><?= siteT('pag_cc_nome') ?></label>
                    <input id="ccNome" autocomplete="cc-name"
                           class="mt-1.5 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-100 outline-none transition">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700"><?= siteT('pag_cc_val') ?></label>
                        <input id="ccValidade" inputmode="numeric" placeholder="MM/AA" maxlength="5"
                               class="mt-1.5 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-100 outline-none transition">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700"><?= siteT('pag_cc_cvv') ?></label>
                        <input id="ccCvv" inputmode="numeric" maxlength="4"
                               class="mt-1.5 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-100 outline-none transition">
                    </div>
                </div>
                <div id="ccParcelasWrap">
                    <label class="block text-sm font-semibold text-slate-700"><?= siteT('pag_cc_parc') ?></label>
                    <select id="ccParcelas"
                            class="mt-1.5 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm bg-white focus:border-brand-500 focus:ring-2 focus:ring-brand-100 outline-none transition"></select>
                </div>
                <button type="submit" id="ccBtn"
                        class="w-full rounded-2xl py-4 text-sm font-black text-white bg-slate-800 hover:bg-slate-900 transition"></button>
            </form>
            <p class="mt-4 text-center text-xs text-slate-400"><?= siteT('pag_cc_note') ?></p>
        </div>

        <!-- Estágio do erro -->
        <div id="estagio-erro" class="hidden mt-8 rounded-3xl border border-rose-200 bg-rose-50 p-8 text-center">
            <svg class="w-10 h-10 mx-auto text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <p id="erro-mensagem" class="mt-4 text-sm font-semibold text-rose-700"></p>
            <button onclick="window.location.reload()" class="mt-5 rounded-2xl px-6 py-3 text-sm font-bold text-white bg-rose-600 hover:bg-rose-700 transition"><?= siteT('pag_tentar') ?></button>
        </div>

        <!-- Estágio do sucesso -->
        <div id="estagio-sucesso" class="hidden mt-8 rounded-3xl border border-brand-200 bg-brand-50 p-10 text-center">
            <span class="inline-flex w-16 h-16 rounded-full bg-brand-600 text-white items-center justify-center shadow-xl shadow-brand-200">
                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            </span>
            <h2 class="mt-5 text-2xl font-black text-slate-900"><?= siteT('pag_confirmado') ?></h2>
            <p class="mt-2 text-sm text-slate-600"><?= siteT('pag_seu_plano') ?> <strong><?= htmlspecialchars($plano['nome']) ?></strong> <?= siteT('pag_ativo') ?></p>
            <a href="/cobranca/admin/index.php" class="mt-6 inline-flex items-center gap-2 rounded-2xl px-8 py-4 font-bold text-white bg-gradient-to-r from-brand-600 to-brand-700 hover:from-brand-700 hover:to-brand-800 shadow-xl shadow-brand-200 transition">
                <?= siteT('pag_abrir') ?>
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
            </a>
        </div>

        <p class="mt-8 text-center text-xs text-slate-400"><?= siteT('pag_problemas') ?> <a href="/cobranca/planos.php" class="font-bold text-brand-700 hover:underline"><?= siteT('pag_outro') ?></a> <?= siteT('pag_ou') ?> <a href="/cobranca/admin/login.php" class="font-bold text-brand-700 hover:underline"><?= siteT('pag_entrar') ?></a>.</p>
    </div>
</section>

<?php if ($cartaoOk && !empty($mpPublicKey)): ?>
<script src="https://sdk.mercadopago.com/js/v2"></script>
<?php endif; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const TXT = {
        qrIndisp: <?= json_encode(siteT('pag_qr_indisp')) ?>,
        ePix: <?= json_encode(siteT('pag_e_pix')) ?>,
        eNoCode: <?= json_encode(siteT('pag_e_no_code')) ?>,
        eConexao: <?= json_encode(siteT('pag_e_conexao')) ?>,
        eBoleto: <?= json_encode('Não foi possível gerar o boleto. Tente novamente.') ?>,
        copiado: <?= json_encode(siteT('pag_copiado')) ?>,
        copiar: <?= json_encode(siteT('pag_btn_copiar')) ?>,
        paguei: <?= json_encode(siteT('pag_btn_paguei')) ?>,
        pagueiBoleto: <?= json_encode(siteT('pag_b_paguei')) ?>,
        consultando: <?= json_encode(siteT('pag_consultando')) ?>,
        avista: <?= json_encode(siteT('pag_cc_avista')) ?>,
        parcelado: <?= json_encode(siteT('pag_cc_parcelado')) ?>,
        processando: <?= json_encode('Processando...') ?>,
        aguardando: <?= json_encode('Aguardando confirmação...') ?>
    };

    const planoId = <?= (int)$planoId ?>;
    const maxParcelas = <?= (int)$maxParcelas ?>;
    const cpfCliente = <?= json_encode($adminCpfCnpj) ?>;
    const metodos = <?= json_encode($metodos) ?>;
    const publicKey = <?= json_encode($mpPublicKey) ?>;
    let pagamentoId = <?= $pagamentoPendente ? (int)$pagamentoPendente['id'] : '0' ?>;
    let pixCopiaCola = <?= $pagamentoPendente && !empty($pagamentoPendente['pix_copia_cola']) ? json_encode($pagamentoPendente['pix_copia_cola']) : "''" ?>;
    let qrDados = <?= $pagamentoPendente && !empty($pagamentoPendente['qr_code']) ? json_encode($pagamentoPendente['qr_code']) : "''" ?>;
    let pollTimer = null;
    let criando = false;
    let metodoAtual = '';

    const $carregando = document.getElementById('estagio-carregando');
    const $metodo = document.getElementById('estagio-metodo');
    const $pix = document.getElementById('estagio-pix');
    const $boleto = document.getElementById('estagio-boleto');
    const $cartao = document.getElementById('estagio-cartao');
    const $erro = document.getElementById('estagio-erro');
    const $sucesso = document.getElementById('estagio-sucesso');

    function mostrar(id) {
        [$carregando, $metodo, $pix, $boleto, $cartao, $erro, $sucesso].forEach(function (el) { el.classList.add('hidden'); });
        document.getElementById(id).classList.remove('hidden');
    }

    function mostrarErro(msg) {
        document.getElementById('erro-mensagem').textContent = msg;
        mostrar('estagio-erro');
    }

    // ---------- SELETOR DE MÉTODO ----------
    window.escolherMetodo = function (m) {
        if (metodoAtual === m) return;
        metodoAtual = m;
        cancelarPolling();
        if (m === 'pix') criar();
        else if (m === 'boleto') criarBoleto();
        else { montarCartao(); mostrar('estagio-cartao'); }
    };

    window.voltarMetodos = function () {
        cancelarPolling();
        metodoAtual = '';
        mostrar('estagio-metodo');
    };

    function iniciarFluxo() {
        if (pagamentoId && pixCopiaCola && metodos.indexOf('pix') !== -1) {
            document.getElementById('pix-copia-cola').value = pixCopiaCola;
            renderizaQR();
            metodoAtual = 'pix';
            mostrar('estagio-pix');
            iniciarPolling();
        } else if (metodos.length === 1) {
            window.escolherMetodo(metodos[0]);
        } else {
            mostrar('estagio-metodo');
        }
    }

    // ---------- PIX ----------
    function cancelarPolling() {
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    }

    function iniciarPolling() {
        cancelarPolling();
        pollTimer = setInterval(verificar, 8000);
    }

    function verificar() {
        if (!pagamentoId) return;
        fetch('/cobranca/api/verificar_pix_plano.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'pagamento_id=' + encodeURIComponent(pagamentoId)
        }).then(function (r) { return r.json(); }).then(function (res) {
            if (res.erro) {
                cancelarPolling();
                mostrarErro(res.erro);
                return;
            }
            if (res.status === 'pago') {
                cancelarPolling();
                mostrar('estagio-sucesso');
            } else if (res.status === 'cancelado' || res.status === 'expirado') {
                cancelarPolling();
                window.voltarMetodos();
            }
        }).catch(function () {});
    }

    function renderizaQR() {
        const box = document.getElementById('pix-qr');
        box.innerHTML = '';
        if (qrDados.length) {
            const img = document.createElement('img');
            img.src = 'data:image/png;base64,' + qrDados;
            img.className = 'w-44 h-44';
            box.appendChild(img);
        } else if (pixCopiaCola.length) {
            const url = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' + encodeURIComponent(pixCopiaCola);
            const img = document.createElement('img');
            img.src = url;
            img.className = 'w-48 h-48 rounded-xl';
            box.appendChild(img);
        } else {
            box.textContent = TXT.qrIndisp;
        }
    }

    function criar() {
        if (criando) return;
        criando = true;
        mostrar('estagio-carregando');
        fetch('/cobranca/api/criar_pix_plano.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'plano_id=' + encodeURIComponent(planoId) + '&duracao_meses=1'
        }).then(function (r) { return r.json(); }).then(function (res) {
            criando = false;
            if (res.erro || !res.sucesso) { mostrarErro(res.erro || TXT.ePix); return; }
            pagamentoId = res.pagamento_id;
            if (res.qr_code) qrDados = res.qr_code;
            if (res.pix_copia_cola) pixCopiaCola = res.pix_copia_cola;
            if (pixCopiaCola) {
                document.getElementById('pix-copia-cola').value = pixCopiaCola;
                renderizaQR();
                mostrar('estagio-pix');
                iniciarPolling();
            } else {
                mostrarErro(TXT.eNoCode);
                window.voltarMetodos();
            }
        }).catch(function () { criando = false; mostrarErro(TXT.eConexao); });
    }

    // ---------- BOLETO ----------
    function criarBoleto() {
        if (criando) return;
        criando = true;
        mostrar('estagio-carregando');
        fetch('/cobranca/api/criar_boleto_plano.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'plano_id=' + encodeURIComponent(planoId) + '&duracao_meses=1'
        }).then(function (r) { return r.json(); }).then(function (res) {
            criando = false;
            if (res.erro || !res.sucesso) { mostrarErro(res.erro || TXT.eBoleto); return; }
            pagamentoId = res.pagamento_id;
            const link = document.getElementById('boleto-link');
            if (res.boleto_url) {
                link.href = res.boleto_url;
                link.classList.remove('pointer-events-none', 'opacity-50');
            } else {
                link.classList.add('pointer-events-none', 'opacity-50');
            }
            const linha = res.boleto_linha_digitavel || res.boleto_codigo_barras || '';
            document.getElementById('boleto-linha-digitavel').textContent = linha;
            mostrar('estagio-boleto');
            iniciarPolling();
        }).catch(function () { criando = false; mostrarErro(TXT.eConexao); });
    }

    // ---------- CARTÃO ----------
    let mp = null;
    let tipoCartao = 'credito';
    let parcelaSelect = null;

    function txtMoeda(v) {
        return 'R$ ' + v.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
    }

    function montarCartao() {
        const btn = document.getElementById('ccBtn');
        const sel = document.getElementById('ccParcelas');
        if (!parcelaSelect) {
            parcelaSelect = true;
            for (let i = 1; i <= maxParcelas; i++) {
                const opt = document.createElement('option');
                opt.value = i;
                opt.textContent = i === 1 ? '<?= siteT('pag_cc_avista') ?> (1x)' : i + 'x';
                sel.appendChild(opt);
            }
        }
        atualizarBtnCartao();
    }

    function valorPlanoNum() {
        return <?= (float)$plano['preco'] ?>;
    }

    function parcelasAtuais() {
        const sel = document.getElementById('ccParcelas');
        if (tipoCartao === 'debito' || !sel) return 1;
        return parseInt(sel.value, 10) || 1;
    }

    function atualizarBtnCartao() {
        const btn = document.getElementById('ccBtn');
        const p = parcelasAtuais();
        if (tipoCartao === 'debito') {
            btn.textContent = TXT.avista + ' — ' + txtMoeda(valorPlanoNum());
        } else if (p <= 1) {
            btn.textContent = TXT.avista + ' — ' + txtMoeda(valorPlanoNum());
        } else {
            btn.textContent = TXT.parcelado.replace('%s', String(p)).replace('%s', txtMoeda(valorPlanoNum() / p));
        }
    }

    function detectarBandeira(num) {
        num = num.replace(/\s+/g, '');
        if (!/^\d{6,}$/.test(num)) return null;
        if (/^4/.test(num)) return { b: 'visa', label: 'Visa' };
        if (/^3[47]/.test(num)) return { b: 'amex', label: 'Amex' };
        if (/(^5[1-5]|^2[2-7])/.test(num)) return { b: 'master', label: 'Mastercard' };
        if (/^(4011|4312|4389|4514|4573|4576|5041|5066|5090|6277|6362|6363|6504|6505|6507|6509|6516|6550)/.test(num)) return { b: 'elo', label: 'Elo' };
        if (/^(6062|3841)/.test(num)) return { b: 'hipercard', label: 'Hipercard' };
        return null;
    }

    function metodoDebito(numero) {
        const b = detectarBandeira(numero.replace(/\s+/g, ''));
        if (!b) return '';
        switch (b.b) {
            case 'visa': return 'debvisa';
            case 'master': return 'debmaster';
            case 'elo': return 'debelo';
            case 'hipercard': return 'debhipercard';
            default: return '';
        }
    }

    function formatarNumero(val) {
        const d = val.replace(/\D+/g, '');
        const b = detectarBandeira(d);
        if (b && b.b === 'amex') return d.slice(0, 15).replace(/(\d{4})(\d{6})(\d+)/, '$1 $2 $3');
        return d.slice(0, 16).replace(/(\d{4})(?=\d)/g, '$1 ');
    }

    function mensagensErro(err) {
        const msgs = [];
        function add(m) { if (m && typeof m === 'string' && msgs.indexOf(m) === -1) msgs.push(m); }
        if (Array.isArray(err)) err.forEach(function (i) { add(i && (i.message || i.description)); });
        else if (err && typeof err === 'object') { add(err.message); add(err.error); if (Array.isArray(err.cause)) err.cause.forEach(function (c) { add(c && c.description); }); }
        else add(err);
        return msgs.join(' | ') || 'Não foi possível validar o cartão. Verifique os dados e tente novamente.';
    }

    function msgCartao(texto, tipo) {
        const m = document.getElementById('ccMsg');
        m.className = 'mt-4 text-sm ' + (tipo === 'ok' ? 'text-green-700' : tipo === 'warn' ? 'text-amber-700' : 'text-rose-700');
        m.textContent = texto || '';
    }

    function tokenErro(err) {
        const btn = document.getElementById('ccBtn');
        btn.disabled = false;
        atualizarBtnCartao();
        msgCartao(mensagensErro(err), 'erro');
    }

    function tokenSucesso(resp) {
        if (!resp || typeof resp.id !== 'string' || resp.id === '') { tokenErro(resp); return; }
        fetch('/cobranca/api/cartao_plano.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'plano_id=' + encodeURIComponent(planoId) +
                '&duracao_meses=1' +
                '&card_token=' + encodeURIComponent(resp.id) +
                '&installments=' + encodeURIComponent(parcelasAtuais()) +
                '&tipo=' + encodeURIComponent(tipoCartao) +
                '&method=' + encodeURIComponent(tipoCartao === 'debito' ? metodoDebito(document.getElementById('ccNumero').value) : '')
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.sucesso && d.status === 'pago') {
                cancelarPolling();
                mostrar('estagio-sucesso');
            } else if (d.sucesso && d.mp_status === 'approved') {
                cancelarPolling();
                mostrar('estagio-sucesso');
            } else if (d.sucesso) {
                pagamentoId = d.pagamento_id;
                msgCartao('<?= siteT('pag_pend') ?>', 'warn');
                iniciarPolling();
            } else {
                const btn = document.getElementById('ccBtn');
                btn.disabled = false;
                atualizarBtnCartao();
                msgCartao(d.erro || 'Não foi possível processar o pagamento.', 'erro');
            }
        })
        .catch(function () {
            const btn = document.getElementById('ccBtn');
            btn.disabled = false;
            atualizarBtnCartao();
            msgCartao('Erro de conexão. Tente novamente.', 'erro');
        });
    }

    function iniciarCartao() {
        if (publicKey) {
            try { mp = new MercadoPago(publicKey, { locale: 'pt-BR' }); } catch (e) { mp = null; }
        }
        document.getElementById('tabCredito').addEventListener('click', function () {
            tipoCartao = 'credito';
            document.getElementById('tabCredito').className = 'flex-1 rounded-xl py-2.5 text-brand-700 bg-white shadow-sm';
            document.getElementById('tabDebito').className = 'flex-1 rounded-xl py-2.5 text-slate-500';
            document.getElementById('ccParcelasWrap').style.opacity = '1';
            document.getElementById('ccParcelasWrap').style.pointerEvents = 'auto';
            atualizarBtnCartao();
        });
        document.getElementById('tabDebito').addEventListener('click', function () {
            tipoCartao = 'debito';
            document.getElementById('tabDebito').className = 'flex-1 rounded-xl py-2.5 text-brand-700 bg-white shadow-sm';
            document.getElementById('tabCredito').className = 'flex-1 rounded-xl py-2.5 text-slate-500';
            document.getElementById('ccParcelasWrap').style.opacity = '0.35';
            document.getElementById('ccParcelasWrap').style.pointerEvents = 'none';
            atualizarBtnCartao();
        });
        document.getElementById('ccParcelas').addEventListener('change', atualizarBtnCartao);
        document.getElementById('ccNumero').addEventListener('input', function (e) { e.target.value = formatarNumero(e.target.value); });
        document.getElementById('ccValidade').addEventListener('input', function (e) {
            let v = e.target.value.replace(/\D+/g, '').slice(0, 4);
            if (v.length > 2) v = v.slice(0, 2) + '/' + v.slice(2);
            e.target.value = v;
        });

        document.getElementById('ccForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const numero = document.getElementById('ccNumero').value.replace(/\s+/g, '');
            const nome = document.getElementById('ccNome').value.trim();
            const validade = document.getElementById('ccValidade').value.trim();
            const cvv = document.getElementById('ccCvv').value.trim();
            const parcelas = parcelasAtuais();

            if (!cpfCliente) {
                msgCartao('Cadastre um CPF ou CNPJ no seu cadastro para pagar com cartão.', 'erro');
                return;
            }
            if (!/^\d{13,16}$/.test(numero)) { msgCartao('Número de cartão inválido.', 'erro'); return; }
            const m = validade.match(/^(\d{2})\s*\/\s*(\d{2})$/);
            if (!m) { msgCartao('Validade inválida. Use o formato MM/AA.', 'erro'); return; }
            const mes = parseInt(m[1], 10), ano = 2000 + parseInt(m[2], 10);
            if (mes < 1 || mes > 12) { msgCartao('Mês da validade inválido.', 'erro'); return; }
            if (cvv.length < 3) { msgCartao('CVV inválido.', 'erro'); return; }
            if (!nome) { msgCartao('Informe o nome impresso no cartão.', 'erro'); return; }
            const hoje = new Date();
            if (ano < hoje.getFullYear() || (ano === hoje.getFullYear() && mes < hoje.getMonth() + 1)) {
                msgCartao('Este cartão está vencido.', 'erro');
                return;
            }

            const btn = document.getElementById('ccBtn');
            btn.disabled = true;
            btn.textContent = TXT.processando;
            msgCartao('', 'ok');

            if (!mp) { tokenErro('SDK de pagamento não carregado. Recarregue a página.'); return; }

            const payload = {
                cardNumber: numero,
                cardholderName: nome,
                cardExpirationMonth: (mes < 10 ? '0' : '') + mes,
                cardExpirationYear: String(ano),
                securityCode: cvv,
                installments: parcelas,
                identificationType: cpfCliente.length === 14 ? 'CNPJ' : 'CPF',
                identificationNumber: cpfCliente,
                locale: 'pt-BR'
            };

            try {
                const prom = mp.createCardToken(payload);
                if (prom && typeof prom.then === 'function') {
                    prom.then(tokenSucesso).catch(function (err) { tokenErro(err); });
                } else {
                    mp.createCardToken(payload, function (resp, err) {
                        if (err && (err.length || err.message)) tokenErro(err);
                        else tokenSucesso(resp);
                    });
                }
            } catch (ex) {
                tokenErro(ex);
            }
        });

        atualizarBtnCartao();
    }

    // ---------- BOTÕES ----------
    document.getElementById('btn-copiar').addEventListener('click', function () {
        const ta = document.getElementById('pix-copia-cola');
        ta.select();
        document.execCommand('copy');
        if (navigator.clipboard) { navigator.clipboard.writeText(ta.value).catch(function () {}); }
        const b = this;
        b.textContent = TXT.copiado;
        setTimeout(function () { b.textContent = TXT.copiar; }, 2000);
    });

    document.getElementById('btn-paguei').addEventListener('click', function () {
        const b = this;
        b.textContent = TXT.consultando;
        verificar();
        setTimeout(function () { b.textContent = TXT.paguei; }, 9000);
    });

    document.getElementById('btn-copiar-boleto').addEventListener('click', function () {
        const el = document.getElementById('boleto-linha-digitavel');
        const texto = el.textContent || '';
        if (navigator.clipboard) { navigator.clipboard.writeText(texto).catch(function () {}); }
        const b = this;
        b.textContent = TXT.copiado;
        setTimeout(function () { b.textContent = TXT.copiar; }, 2000);
    });

    document.getElementById('btn-boleto-paguei').addEventListener('click', function () {
        const b = this;
        b.textContent = TXT.consultando;
        verificar();
        setTimeout(function () { b.textContent = TXT.pagueiBoleto; }, 9000);
    });

    // ---------- INICIALIZA ----------
    if (<?= $cartaoOk ? 'true' : 'false' ?>) iniciarCartao();
    if (<?= !empty($mpPublicKey) || true ? 'true' : 'false' ?>) {
        if (<?= $cartaoOk ? 'true' : 'false' ?>) {
            try { if (!window.MercadoPago) { /* SDK carregado via script tag */ } } catch (e) {}
        }
    }
    iniciarFluxo();
});
</script>

<?php siteFooter(); ?>
</body>
</html>