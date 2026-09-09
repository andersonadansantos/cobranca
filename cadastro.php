<?php
// =====================================================
// CADASTRO NO SITE (cliente final) -> pós-cadastro: PIX
// =====================================================
require_once __DIR__ . '/_site.php';

$pdo = getConnection();
$erros = [];
$p = ['id' => 0, 'nome' => '', 'preco' => '0', 'descricao' => ''];

// --- Plano escolhido (por GET ou POST) ---
$planoId = (int)($_POST['plano'] ?? $_GET['plano'] ?? 0);
if ($planoId > 0 && $pdo) {
    $stmt = $pdo->prepare("SELECT * FROM planos WHERE id = ? AND ativo = 1 AND slug <> 'demo'");
    $stmt->execute([$planoId]);
    $p = $stmt->fetch() ?: $p;
}

if ($p['id'] == 0) {
    header('Location: /cobranca/planos.php');
    exit;
}

// --- Processa cadastro ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d = [
        'nome'             => trim((string)($_POST['nome'] ?? '')),
        'usuario'          => strtolower(trim((string)($_POST['usuario'] ?? ''))),
        'email'            => strtolower(trim((string)($_POST['email'] ?? ''))),
        'senha'            => (string)($_POST['senha'] ?? ''),
        'confirma'         => (string)($_POST['confirma_senha'] ?? ''),
        'nome_fantasia'    => trim((string)($_POST['nome_fantasia'] ?? '')),
        'razao_social'     => trim((string)($_POST['razao_social'] ?? '')),
        'documento'        => preg_replace('/\D/', '', (string)($_POST['cpfcnpj'] ?? '')),
        'cep'              => preg_replace('/\D/', '', (string)($_POST['cep'] ?? '')),
        'logradouro'       => trim((string)($_POST['logradouro'] ?? '')),
        'numero'           => trim((string)($_POST['numero'] ?? '')),
        'bairro'           => trim((string)($_POST['bairro'] ?? '')),
        'telefone'         => trim((string)($_POST['telefone'] ?? '')),
        'cidade'           => trim((string)($_POST['cidade'] ?? '')),
        'estado'           => strtoupper(trim((string)($_POST['estado'] ?? ''))),
    ];

    if ($d['nome'] === '')                          $erros[] = siteT('cad_e_nome');
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $erros[] = siteT('cad_e_email');
    if (!preg_match('/^[a-z0-9._-]{3,30}$/', $d['usuario'])) $erros[] = siteT('cad_e_usuario');
    if (strlen($d['senha']) < 6)                    $erros[] = siteT('cad_e_senha');
    if ($d['senha'] !== $d['confirma'])             $erros[] = siteT('cad_e_confirma');
    if (!in_array(strlen($d['documento']), [11, 14])) $erros[] = siteT('cad_e_doc');
    if (strlen($d['cep']) !== 8)                    $erros[] = siteT('cad_e_cep');

    if (!$erros) {
        $e_u = $pdo->prepare("SELECT COUNT(*) FROM administradores WHERE usuario = ?");
        $e_u->execute([$d['usuario']]);
        if ((int)$e_u->fetchColumn() > 0)    $erros[] = siteT('cad_e_usuario_uso');

        $e_em = $pdo->prepare("SELECT COUNT(*) FROM administradores WHERE email = ?");
        $e_em->execute([$d['email']]);
        if ((int)$e_em->fetchColumn() > 0)   $erros[] = siteT('cad_e_email_uso');
    }

    if (!$erros) {
        try {
            $sql = "INSERT INTO administradores
                    (usuario, senha, nome, email, razao_social, nome_fantasia, cnpj, cpf,
                     telefone_comercial, cidade, estado, cep, logradouro, numero, bairro, ativo, origem)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'site')";
            $ins = $pdo->prepare($sql);
            $ins->execute([
                $d['usuario'],
                password_hash($d['senha'], PASSWORD_DEFAULT),
                $d['nome'],
                $d['email'],
                $d['razao_social'] ?: null,
                $d['nome_fantasia'] ?: null,
                strlen($d['documento']) === 14 ? $d['documento'] : null,
                strlen($d['documento']) === 11 ? $d['documento'] : null,
                $d['telefone'] ?: null,
                $d['cidade'] ?: null,
                $d['estado'] ?: null,
                $d['cep'] ?: null,
                $d['logradouro'] ?: null,
                $d['numero'] ?: null,
                $d['bairro'] ?: null,
            ]);
            $novoId = (int)$pdo->lastInsertId();

            // Auto-login e redirect direto ao pagamento
            session_regenerate_id(true);
            $_SESSION['admin_id']      = $novoId;
            $_SESSION['admin_usuario'] = $d['usuario'];
            $_SESSION['admin_nome']    = $d['nome'];
            $_SESSION['admin_nivel']   = 'admin';
            $_SESSION['admin_origem']  = 'site';

            notificarSuperCadastro($d['nome'], $d['usuario'], $d['email'], $p['nome']);

            header('Location: /cobranca/pagamento.php?plano=' . $planoId);
            exit;
        } catch (Throwable $ex) {
            $erros[] = siteT('cad_e_falha') . $ex->getMessage();
        }
    }
}

siteHeader();
?>

<section class="py-16 min-h-screen bg-gradient-to-b from-brand-50/50 to-white">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-5 gap-10">
        <!-- Formulário -->
        <div class="lg:col-span-3">
            <a href="/cobranca/planos.php" class="inline-flex items-center gap-1 text-sm font-bold text-brand-700 hover:text-brand-800">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                <?= siteT('cad_voltar') ?>
            </a>
            <h1 class="mt-4 text-3xl sm:text-4xl font-black text-slate-900 tracking-tight"><?= siteT('cad_titulo') ?></h1>
            <p class="mt-2 text-slate-600"><?= siteT('cad_sub1') ?> <strong class="text-slate-900"><?= htmlspecialchars($p['nome']) ?></strong>.</p>

            <?php if (!empty($erros)): ?>
            <div class="mt-6 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-700">
                <p class="font-bold mb-1.5"><?= siteT('cad_erros_t') ?></p>
                <ul class="list-disc pl-5 space-y-0.5"><?php foreach ($erros as $er) echo '<li>' . htmlspecialchars($er) . '</li>'; ?></ul>
            </div>
            <?php endif; ?>

            <form method="post" class="mt-8 space-y-8">
                <input type="hidden" name="plano" value="<?= (int)$p['id'] ?>">

                <!-- Dados de acesso -->
                <fieldset class="rounded-3xl border border-slate-100 bg-white p-6 sm:p-8 shadow-sm">
                    <legend class="px-2 text-sm font-black text-slate-900 uppercase tracking-wider"><?= siteT('cad_passo1') ?></legend>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-semibold text-slate-700"><?= siteT('cad_nome') ?></label>
                            <input name="nome" value="<?= htmlspecialchars($d['nome'] ?? '') ?>" required
                                   class="mt-1.5 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-100 outline-none transition" placeholder="<?= siteT('cad_nome_ph') ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700"><?= siteT('cad_email') ?></label>
                            <input type="email" name="email" value="<?= htmlspecialchars($d['email'] ?? '') ?>" required
                                   class="mt-1.5 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-100 outline-none transition" placeholder="<?= siteT('cad_email_ph') ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700"><?= siteT('cad_usuario') ?></label>
                            <input name="usuario" value="<?= htmlspecialchars($d['usuario'] ?? '') ?>" required
                                   class="mt-1.5 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-100 outline-none transition" placeholder="<?= siteT('cad_usuario_ph') ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700"><?= siteT('cad_senha') ?></label>
                            <input type="password" name="senha" required minlength="6"
                                   class="mt-1.5 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-100 outline-none transition" placeholder="<?= siteT('cad_senha_ph') ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700"><?= siteT('cad_confirma') ?></label>
                            <input type="password" name="confirma_senha" required minlength="6"
                                   class="mt-1.5 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-100 outline-none transition" placeholder="<?= siteT('cad_confirma_ph') ?>">
                        </div>
                    </div>
                </fieldset>

                <!-- Dados da empresa / pagamento -->
                <fieldset class="rounded-3xl border border-slate-100 bg-white p-6 sm:p-8 shadow-sm">
                    <legend class="px-2 text-sm font-black text-slate-900 uppercase tracking-wider"><?= siteT('cad_passo2') ?></legend>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700"><?= siteT('cad_cpfcnpj') ?></label>
                            <input name="cpfcnpj" value="<?= htmlspecialchars($d['documento'] ?? '') ?>" required inputmode="numeric" placeholder="<?= siteT('cad_cpfcnpj_ph') ?>"
                                   class="mt-1.5 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-100 outline-none transition">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700"><?= siteT('cad_cep') ?></label>
                            <input name="cep" value="<?= htmlspecialchars($d['cep'] ?? '') ?>" required inputmode="numeric" placeholder="<?= siteT('cad_cep_ph') ?>"
                                   class="mt-1.5 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-100 outline-none transition">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700"><?= siteT('cad_end') ?></label>
                            <input name="logradouro" value="<?= htmlspecialchars($d['logradouro'] ?? '') ?>"
                                   class="mt-1.5 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-100 outline-none transition">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700"><?= siteT('cad_num') ?></label>
                            <input name="numero" value="<?= htmlspecialchars($d['numero'] ?? '') ?>"
                                   class="mt-1.5 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-100 outline-none transition">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700"><?= siteT('cad_fantasia') ?></label>
                            <input name="nome_fantasia" value="<?= htmlspecialchars($d['nome_fantasia'] ?? '') ?>"
                                   class="mt-1.5 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-100 outline-none transition" placeholder="<?= siteT('cad_fantasia_ph') ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700"><?= siteT('cad_tel') ?></label>
                            <input name="telefone" value="<?= htmlspecialchars($d['telefone'] ?? '') ?>" placeholder="<?= siteT('cad_tel_ph') ?>"
                                   class="mt-1.5 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-100 outline-none transition">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700"><?= siteT('cad_cidade') ?></label>
                            <input name="cidade" value="<?= htmlspecialchars($d['cidade'] ?? '') ?>"
                                   class="mt-1.5 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-100 outline-none transition">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700"><?= siteT('cad_uf') ?></label>
                            <select name="estado" class="mt-1.5 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm bg-white focus:border-brand-500 focus:ring-2 focus:ring-brand-100 outline-none transition">
                                <option value=""><?= siteT('cad_uf_sel') ?></option>
                                <?php foreach (['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $uf): ?>
                                <option value="<?= $uf ?>" <?= (($d['estado'] ?? '') === $uf) ? 'selected' : '' ?>><?= $uf ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </fieldset>

                <button type="submit" class="w-full rounded-2xl py-4 text-lg font-black text-white bg-gradient-to-r from-brand-600 to-brand-700 hover:from-brand-700 hover:to-brand-800 shadow-xl shadow-brand-200 hover:-translate-y-0.5 transition">
                    <?= siteT('cad_btn') ?>
                </button>
                <p class="text-center text-xs text-slate-400"><?= siteT('cad_termos') ?></p>
            </form>
        </div>

        <!-- Resumo do plano -->
        <aside class="lg:col-span-2">
            <div class="sticky top-24 rounded-3xl border border-slate-100 bg-white p-7 shadow-xl shadow-slate-100/70">
                <h3 class="text-sm font-black uppercase tracking-wider text-slate-900"><?= siteT('cad_resumo') ?></h3>
                <div class="mt-4 flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-3">
                    <span class="font-bold text-slate-800"><?= htmlspecialchars($p['nome']) ?> <span class="text-xs font-semibold text-slate-400 block"><?= htmlspecialchars(trim((string)$p['descricao'])) ?></span></span>
                    <span class="text-lg font-black text-slate-900">R$ <?= sitePreco($p['preco']) ?><span class="text-xs text-slate-400 font-semibold"><?= siteT('plan_mes') ?></span></span>
                </div>
                <ul class="mt-4 space-y-2 text-sm text-slate-600">
                    <?php foreach (siteParseBeneficios($p['beneficios']) as $b): if (siteBeneficioStatus($b) !== 'ok') continue; ?>
                    <li class="flex gap-2"><span class="w-5 h-5 rounded-full bg-brand-50 text-brand-600 grid place-items-center shrink-0"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></span><?= htmlspecialchars($b) ?></li>
                    <?php endforeach; ?>
                </ul>
                <div class="mt-6 rounded-2xl bg-gradient-to-r from-brand-600 to-brand-800 text-white p-4 flex items-center gap-3">
                    <svg class="w-8 h-8 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    <p class="text-sm font-semibold"><?= siteT('cad_pix_nota') ?></p>
                </div>
                <p class="mt-5 text-center text-sm text-slate-500"><?= siteT('cad_conta') ?> <a href="/cobranca/admin/login.php" class="font-bold text-brand-700 hover:underline"><?= siteT('cad_login') ?></a></p>
            </div>
        </aside>
    </div>
</section>

<?php siteFooter(); ?>
</body>
</html>