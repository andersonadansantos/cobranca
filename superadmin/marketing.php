<?php
require_once __DIR__ . '/auth.php';
requireSuper();
require_once __DIR__ . '/../config/marketing_helpers.php';

$pageTitle = 'Marketing';
$csrf = mkt_csrf_token();

$openai = mkt_openai_config();
$google = mkt_google_config();
$nicho = mkt_nicho_config();
$cron = mkt_cron_config();
$tplWa = mkt_template_obter('whatsapp');
$tplEm = mkt_template_obter('email');
$imagensWa = mkt_arquivo_listar('whatsapp');
$imagensEm = mkt_arquivo_listar('email');
$imgJs = function (array $lista): array {
    $out = [];
    foreach ($lista as $img) {
        $out[] = [
            'id' => (int) $img['id'],
            'nome' => $img['nome_original'],
            'mime' => $img['mime'],
            'modo' => $img['modo'],
            'tamanho' => (int) $img['tamanho'],
            'url' => mkt_arquivo_url($img['id']),
            'placeholder' => '{{imagem:' . (int) $img['id'] . '}}',
        ];
    }
    return $out;
};
$stats = mkt_stats();
$proxima = mkt_cron_proxima_execucao();
$ultima = mkt_ultima_execucao();
$execucoes = mkt_execucoes_listar(10);
$nichos = [];
try {
    $pdoTmp = getConnection();
    $nichos = $pdoTmp->query("SELECT DISTINCT nicho FROM marketing_contatos WHERE nicho IS NOT NULL AND nicho <> '' ORDER BY nicho")->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {}

$ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
<style>
.mkt-nav .nav-link{display:flex;align-items:center;gap:10px;text-align:left;color:var(--app-text-secondary);border-radius:10px;padding:.7rem .9rem;font-weight:500;font-size:.9rem;border:1px solid transparent}
.mkt-nav .nav-link i{width:20px;text-align:center;font-size:.95rem}
.mkt-nav .nav-link:hover{background:var(--slate-100)}
.mkt-nav .nav-link.active{background:var(--cor-primaria);color:#fff;box-shadow:var(--shadow-sm)}
.mkt-nav .nav-link.active i{color:#fff}
.mkt-nav .nav-link .badge{margin-left:auto}
.mkt-stat{display:flex;align-items:center;gap:12px;padding:14px 16px}
.mkt-stat .ico{width:42px;height:42px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:1.05rem}
.mkt-stat .val{font-size:1.35rem;font-weight:700;line-height:1.1}
.mkt-stat .lbl{font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:var(--app-text-muted);font-weight:600}
.mkt-card{border:1px solid var(--app-card-border);background:var(--app-card);border-radius:14px;padding:18px;box-shadow:var(--shadow-sm)}
.mkt-card h6{font-size:.95rem;font-weight:700}
.mkt-status{font-size:.7rem;font-weight:700;padding:.28rem .55rem;border-radius:999px;display:inline-block;white-space:nowrap}
.st-coletado{background:#eef1f4;color:#5b6472}
.st-na_fila{background:#e8f1ff;color:#1d4ed8}
.st-enviado{background:#e8f7ef;color:#128a53}
.st-bloqueado{background:#f1e9ff;color:#6d28d9}
.st-falha{background:#fdecec;color:#c62828}
.st-cancelado{background:#eef1f4;color:#5b6472}
.st-aguardando{background:#fff6e5;color:#a86a00}
.st-enviando{background:#e8f1ff;color:#1d4ed8}
.st-em_andamento{background:#fff6e5;color:#a86a00}
.st-concluido{background:#e8f7ef;color:#128a53}
.st-parcial{background:#e8f1ff;color:#1d4ed8}
.st-erro{background:#fdecec;color:#c62828}
.mkt-preview{background:#eef2f5;border-radius:12px;padding:14px;min-height:150px}
.mkt-preview-bolha{background:#dcf8c6;border-radius:10px;padding:12px 14px;font-size:.86rem;white-space:pre-wrap;line-height:1.5;max-height:320px;overflow:auto;color:#1f2a24}
.mkt-preview iframe{width:100%;min-height:280px;border:1px solid var(--app-card-border);border-radius:10px;background:#fff}
.mkt-tabela{font-size:.82rem}
.mkt-tabela th{font-size:.7rem;text-transform:uppercase;letter-spacing:.04em;color:var(--app-text-muted);white-space:nowrap}
.mkt-tabela td{vertical-align:middle}
.mkt-tabela .trunc{max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.mkt-var{font-family:ui-monospace,Consolas,monospace;font-size:.74rem;background:var(--slate-100);border:1px solid var(--app-card-border);border-radius:6px;padding:2px 6px;cursor:pointer;display:inline-block;margin:0 3px 4px 0}
.mkt-var:hover{background:var(--slate-200)}
.mkt-scroll{max-height:420px;overflow:auto}
.mkt-hint{font-size:.76rem;color:var(--app-text-muted)}
.form-switch .form-check-input{cursor:pointer}
@media(max-width:991.98px){.mkt-nav{flex-direction:row!important;overflow-x:auto;flex-wrap:nowrap;gap:6px;padding-bottom:6px}.mkt-nav .nav-link{white-space:nowrap;padding:.5rem .7rem;font-size:.8rem}.mkt-nav .nav-link .badge{display:none}}
</style>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5><i class="fas fa-bullhorn me-1"></i> Marketing</h5>
        </div>
        <div class="dropdown d-none d-md-block">
            <span class="text-muted"><i class="fas fa-crown me-1"></i>Super Admin</span>
        </div>
    </div>

    <div class="content-area fade-in">
        <div id="mktAlerta" class="alert d-none alert-dismissible fade show py-2" role="alert"></div>

        <div class="row g-3 mb-3" id="mktStats">
            <div class="col-6 col-lg-2">
                <div class="mkt-card mkt-stat">
                    <div class="ico" style="background:#eef1f4;color:#475569;"><i class="fas fa-address-book"></i></div>
                    <div><div class="val" data-stat="contatos"><?= (int) $stats['contatos'] ?></div><div class="lbl">Contatos</div></div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="mkt-card mkt-stat">
                    <div class="ico" style="background:#e8f7ef;color:#128a53;"><i class="fab fa-whatsapp"></i></div>
                    <div><div class="val" data-stat="whatsapp"><?= (int) $stats['whatsapp'] ?></div><div class="lbl">WhatsApp</div></div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="mkt-card mkt-stat">
                    <div class="ico" style="background:#e8f1ff;color:#1d4ed8;"><i class="fas fa-envelope"></i></div>
                    <div><div class="val" data-stat="emails"><?= (int) $stats['emails'] ?></div><div class="lbl">E-mails</div></div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="mkt-card mkt-stat">
                    <div class="ico" style="background:#fff6e5;color:#a86a00;"><i class="fas fa-hourglass-half"></i></div>
                    <div><div class="val"><span data-stat="fila_whatsapp"><?= (int) $stats['fila_whatsapp'] ?></span> / <span data-stat="fila_email"><?= (int) $stats['fila_email'] ?></span></div><div class="lbl">Fila WA / E-mail</div></div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="mkt-card mkt-stat">
                    <div class="ico" style="background:#e8f7ef;color:#128a53;"><i class="fas fa-paper-plane"></i></div>
                    <div><div class="val"><span data-stat="enviados_whatsapp_hoje"><?= (int) $stats['enviados_whatsapp_hoje'] ?></span> / <span data-stat="enviados_email_hoje"><?= (int) $stats['enviados_email_hoje'] ?></span></div><div class="lbl">Enviados hoje</div></div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="mkt-card mkt-stat">
                    <div class="ico" style="background:#fdecec;color:#c62828;"><i class="fas fa-triangle-exclamation"></i></div>
                    <div><div class="val" data-stat="falhas"><?= (int) $stats['falhas'] ?></div><div class="lbl">Falhas</div></div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-3">
                <div class="mkt-card">
                    <div class="nav flex-column nav-pills mkt-nav" id="mktTabs" role="tablist">
                        <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-chatgpt" type="button" role="tab"><i class="fas fa-robot"></i> Config. ChatGPT</button>
                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-nicho" type="button" role="tab"><i class="fas fa-magnifying-glass-location"></i> Config. Nicho</button>
                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-cron" type="button" role="tab"><i class="fas fa-clock"></i> Config. Cron Job</button>
                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-disparo" type="button" role="tab"><i class="fas fa-paper-plane"></i> Marketing Disparo</button>
                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-manual" type="button" role="tab"><i class="fas fa-upload"></i> Envios manuais</button>
                    </div>
                    <hr class="my-3">
                    <div class="mkt-hint">
                        <div class="d-flex justify-content-between mb-1"><span>Fuso horario</span><strong>America/Sao_Paulo</strong></div>
                        <div class="d-flex justify-content-between mb-1"><span>Opt-out</span><strong><span data-stat="opt_out"><?= (int) $stats['opt_out'] ?></span> contato(s)</strong></div>
                        <div class="d-flex justify-content-between"><span>Descartes</span><strong><span data-stat="descartes"><?= (int) $stats['descartes'] ?></span></strong></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-9">
                <div class="tab-content">

                    <!-- ================= CHATGPT ================= -->
                    <div class="tab-pane fade show active" id="tab-chatgpt" role="tabpanel">
                        <div class="mkt-card mb-4">
                            <h6 class="mb-1"><i class="fas fa-robot me-2"></i>OpenAI / ChatGPT</h6>
                            <p class="mkt-hint mb-3">A IA analisa os resultados da pesquisa e organiza os contatos. Ela nao navega sozinha na internet: a busca e feita pelo Google Programmable Search abaixo.</p>
                            <div class="mkt-hint mb-3 p-2 rounded-3" style="background:var(--slate-50);border:1px solid var(--app-card-border);">
                                <i class="fas fa-link me-1"></i> <strong>Onde obter a chave:</strong>
                                <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener noreferrer">Criar API Key na OpenAI</a>
                                <span class="text-muted mx-1">·</span>
                                <a href="https://platform.openai.com/docs/models" target="_blank" rel="noopener noreferrer">Modelos disponiveis</a>
                                <span class="text-muted mx-1">·</span>
                                <a href="https://platform.openai.com/account/billing" target="_blank" rel="noopener noreferrer">Faturamento / creditos</a>
                            </div>
                            <form id="formOpenai" autocomplete="off">
                                <div class="row g-3">
                                    <div class="col-md-7">
                                        <label class="form-label">API Key da OpenAI *</label>
                                        <input type="password" class="form-control" name="api_key" autocomplete="new-password" placeholder="<?= $openai['key'] !== '' ? 'Salva: ' . htmlspecialchars(mkt_mascarar($openai['key'])) . ' (deixe em branco para manter)' : 'sk-...' ?>">
                                        <small class="text-muted">Armazenada cifrada. <?= $openai['key'] !== '' ? 'Chave salva: <code>' . htmlspecialchars(mkt_mascarar($openai['key'])) . '</code>' : 'Nenhuma chave salva.' ?></small>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label">Modelo *</label>
                                        <input type="text" class="form-control" name="modelo" list="listaModelos" value="<?= htmlspecialchars($openai['modelo']) ?>" placeholder="gpt-4o-mini" required>
                                        <datalist id="listaModelos">
                                            <option value="gpt-4o-mini"></option>
                                            <option value="gpt-4o"></option>
                                            <option value="gpt-4.1-mini"></option>
                                            <option value="gpt-4.1"></option>
                                        </datalist>
                                    </div>
                                </div>
                                <div class="d-flex flex-wrap gap-2 mt-3">
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk me-1"></i>Salvar configuracoes</button>
                                    <button type="button" class="btn btn-outline-secondary" id="btnTestarOpenai"><i class="fas fa-satellite-dish me-1"></i>Testar conexao</button>
                                </div>
                            </form>
                        </div>

                        <div class="mkt-card">
                            <h6 class="mb-1"><i class="fas fa-magnifying-glass me-2"></i>Google Programmable Search</h6>
                            <p class="mkt-hint mb-3">Mecanismo externo de busca usado para localizar empresas publicamente. Crie a chave e o CX no Google Cloud / Programmable Search Engine.</p>
                            <div class="mkt-hint mb-3 p-2 rounded-3" style="background:var(--slate-50);border:1px solid var(--app-card-border);">
                                <i class="fas fa-link me-1"></i> <strong>Onde obter:</strong>
                                <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener noreferrer">Criar API Key no Google Cloud</a>
                                <span class="text-muted mx-1">·</span>
                                <a href="https://console.cloud.google.com/apis/library/customsearch.googleapis.com" target="_blank" rel="noopener noreferrer">Ativar a Custom Search API</a>
                                <span class="text-muted mx-1">·</span>
                                <a href="https://programmablesearchengine.google.com/controlpanel/create" target="_blank" rel="noopener noreferrer">Criar o mecanismo de busca (CX)</a>
                            </div>
                            <form id="formGoogle" autocomplete="off">
                                <div class="row g-3">
                                    <div class="col-md-7">
                                        <label class="form-label">API Key do Google *</label>
                                        <input type="password" class="form-control" name="google_key" autocomplete="new-password" placeholder="<?= $google['key'] !== '' ? 'Salva: ' . htmlspecialchars(mkt_mascarar($google['key'])) . ' (deixe em branco para manter)' : 'AIza...' ?>">
                                        <small class="text-muted"><?= $google['key'] !== '' ? 'Chave salva: <code>' . htmlspecialchars(mkt_mascarar($google['key'])) . '</code>' : 'Nenhuma chave salva.' ?></small>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label">ID do mecanismo (CX) *</label>
                                        <input type="text" class="form-control" name="google_cx" value="<?= htmlspecialchars($google['cx']) ?>" placeholder="a1b2c3d4e5f6g7h8i" required>
                                    </div>
                                </div>
                                <div class="d-flex flex-wrap gap-2 mt-3">
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk me-1"></i>Salvar configuracoes</button>
                                    <button type="button" class="btn btn-outline-secondary" id="btnTestarGoogle"><i class="fas fa-satellite-dish me-1"></i>Testar busca</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- ================= NICHO ================= -->
                    <div class="tab-pane fade" id="tab-nicho" role="tabpanel">
                        <div class="mkt-card mb-4">
                            <h6 class="mb-3"><i class="fas fa-magnifying-glass-location me-2"></i>Configuracao do nicho</h6>
                            <form id="formNicho">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Nome do nicho *</label>
                                        <input type="text" class="form-control" name="nome" value="<?= htmlspecialchars($nicho['nome']) ?>" placeholder="Ex.: clinicas odontologicas" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Regiao de abrangencia</label>
                                        <input type="text" class="form-control" name="regiao" value="<?= htmlspecialchars($nicho['regiao']) ?>" placeholder="Ex.: Grande Sao Paulo">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Descricao do segmento</label>
                                        <textarea class="form-control" name="descricao" rows="2" placeholder="Descreva o tipo de empresa desejada"><?= htmlspecialchars($nicho['descricao']) ?></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Cidade *</label>
                                        <input type="text" class="form-control" name="cidade" value="<?= htmlspecialchars($nicho['cidade']) ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Estado *</label>
                                        <select class="form-select" name="estado" required>
                                            <option value="">UF</option>
                                            <?php foreach ($ufs as $uf): ?>
                                                <option value="<?= $uf ?>" <?= $nicho['estado'] === $uf ? 'selected' : '' ?>><?= $uf ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3"></div>
                                    <div class="col-md-3">
                                        <label class="form-label">Max. WhatsApp / dia</label>
                                        <input type="number" class="form-control" name="limite_whatsapp" min="1" max="20" value="<?= (int) $nicho['limite_wa'] ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Max. e-mails / dia</label>
                                        <input type="number" class="form-control" name="limite_email" min="1" max="20" value="<?= (int) $nicho['limite_email'] ?>">
                                    </div>
                                </div>
                                <div class="d-flex flex-wrap gap-2 mt-3">
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk me-1"></i>Salvar configuracao</button>
                                    <button type="button" class="btn btn-outline-success" id="btnPesquisar"><i class="fas fa-magnifying-glass me-1"></i>Iniciar pesquisa manual</button>
                                </div>
                            </form>
                        </div>

                        <div class="mkt-card">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                <h6 class="mb-0"><i class="fas fa-address-book me-2"></i>Contatos coletados</h6>
                                <div class="d-flex gap-2">
                                    <input type="search" class="form-control form-control-sm" id="buscaContato" placeholder="Buscar empresa, telefone..." style="min-width:200px">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnAtualizarContatos"><i class="fas fa-rotate"></i></button>
                                </div>
                            </div>
                            <div class="table-responsive mkt-scroll">
                                <table class="table table-hover mkt-tabela align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Empresa</th><th>Nicho</th><th>Cidade/UF</th><th>WhatsApp</th><th>E-mail</th><th>Origem</th><th>Data</th><th>Status</th><th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="tabelaContatos"><tr><td colspan="9" class="text-center text-muted py-4">Carregando...</td></tr></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- ================= CRON ================= -->
                    <div class="tab-pane fade" id="tab-cron" role="tabpanel">
                        <div class="mkt-card mb-4">
                            <h6 class="mb-3"><i class="fas fa-clock me-2"></i>Agendamento diario</h6>
                            <div class="alert alert-info py-2 mb-3">
                                <i class="fas fa-circle-info me-1"></i>
                                A execucao e disparada pelo Cron Job da VPS no endpoint <code>api/marketing_cron.php?token=SEU_TOKEN</code>.
                                O endpoint respeita o horario abaixo e executa no maximo uma vez por dia, mesmo que seja chamado varias vezes.
                            </div>
                            <form id="formCron">
                                <div class="row g-3 align-items-end">
                                    <div class="col-md-4">
                                        <label class="form-label d-block">Automacao</label>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" role="switch" id="cronAtivo" name="ativo" value="1" <?= $cron['ativo'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="cronAtivo">Ativar execucao automatica</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Horario diario *</label>
                                        <input type="time" class="form-control" name="horario" value="<?= htmlspecialchars($cron['horario']) ?>" required>
                                    </div>
                                    <div class="col-md-5 d-flex flex-wrap gap-2">
                                        <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk me-1"></i>Salvar</button>
                                        <button type="button" class="btn btn-outline-success" id="btnExecutarAgora"><i class="fas fa-play me-1"></i>Executar agora</button>
                                    </div>
                                </div>
                            </form>

                            <div class="row g-3 mt-2">
                                <div class="col-md-4">
                                    <div class="border rounded-3 p-3 h-100" style="border-color:var(--app-card-border)!important;">
                                        <div class="mkt-hint text-uppercase fw-bold mb-1">Proxima execucao</div>
                                        <div class="fw-semibold" id="cronProxima"><?= $proxima ? $proxima->format('d/m/Y H:i') : 'Automacao desativada' ?></div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="border rounded-3 p-3 h-100" style="border-color:var(--app-card-border)!important;">
                                        <div class="mkt-hint text-uppercase fw-bold mb-1">Ultima execucao</div>
                                        <div class="fw-semibold" id="cronUltima"><?= $ultima ? date('d/m/Y H:i', strtotime($ultima['iniciado_em'])) . ' (' . htmlspecialchars($ultima['origem']) . ')' : 'Nenhuma execucao registrada' ?></div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="border rounded-3 p-3 h-100" style="border-color:var(--app-card-border)!important;">
                                        <div class="mkt-hint text-uppercase fw-bold mb-1">Status da ultima execucao</div>
                                        <div id="cronStatus"><?php if ($ultima): ?><span class="mkt-status st-<?= htmlspecialchars($ultima['status']) ?>"><?= htmlspecialchars($ultima['status']) ?></span><?php else: ?><span class="text-muted">-</span><?php endif; ?></div>
                                        <div class="mkt-hint mt-1" id="cronJaHoje"><?= mkt_cron_ja_executou_hoje() ? 'A rotina automatica de hoje ja foi executada.' : 'A rotina automatica de hoje ainda nao foi executada.' ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mkt-card">
                            <h6 class="mb-3"><i class="fas fa-list-check me-2"></i>Historico de execucoes</h6>
                            <div class="table-responsive mkt-scroll">
                                <table class="table table-hover mkt-tabela align-middle">
                                    <thead class="table-light"><tr>
                                        <th>Inicio</th><th>Origem</th><th>Status</th><th>Novos WA</th><th>Novos e-mails</th><th>Duplicados</th><th>Envios WA</th><th>Envios e-mail</th><th>Falhas</th><th>Mensagem</th>
                                    </tr></thead>
                                    <tbody id="tabelaExecucoes">
                                    <?php if (!$execucoes): ?>
                                        <tr><td colspan="10" class="text-center text-muted py-4">Nenhuma execucao registrada.</td></tr>
                                    <?php else: foreach ($execucoes as $e): ?>
                                        <tr>
                                            <td><?= date('d/m/Y H:i', strtotime($e['iniciado_em'])) ?></td>
                                            <td><?= htmlspecialchars($e['origem']) ?></td>
                                            <td><span class="mkt-status st-<?= htmlspecialchars($e['status']) ?>"><?= htmlspecialchars($e['status']) ?></span></td>
                                            <td><?= (int) $e['whatsapp_novos'] ?></td>
                                            <td><?= (int) $e['emails_novos'] ?></td>
                                            <td><?= (int) $e['duplicados'] ?></td>
                                            <td><?= (int) $e['whatsapp_enviados'] ?></td>
                                            <td><?= (int) $e['emails_enviados'] ?></td>
                                            <td><?= (int) $e['falhas'] ?></td>
                                            <td class="trunc" title="<?= htmlspecialchars((string) $e['mensagem']) ?>"><?= htmlspecialchars((string) $e['mensagem']) ?></td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- ================= DISPARO ================= -->
                    <div class="tab-pane fade" id="tab-disparo" role="tabpanel">

                        <div class="mkt-card mb-4">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                <h6 class="mb-0"><i class="fas fa-rectangle-list me-2"></i>A) Logs de envio</h6>
                                <button type="button" class="btn btn-sm btn-outline-success" id="btnProcessarFila"><i class="fas fa-play me-1"></i>Processar fila agora</button>
                            </div>
                            <form id="formFiltroLogs" class="row g-2 align-items-end mb-3">
                                <div class="col-6 col-md-2">
                                    <label class="form-label mkt-hint mb-1">Data inicial</label>
                                    <input type="date" class="form-control form-control-sm" name="data_ini">
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="form-label mkt-hint mb-1">Data final</label>
                                    <input type="date" class="form-control form-control-sm" name="data_fim">
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="form-label mkt-hint mb-1">Nicho</label>
                                    <select class="form-select form-select-sm" name="nicho">
                                        <option value="">Todos</option>
                                        <?php foreach ($nichos as $n): ?><option value="<?= htmlspecialchars($n) ?>"><?= htmlspecialchars($n) ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="form-label mkt-hint mb-1">Canal</label>
                                    <select class="form-select form-select-sm" name="canal">
                                        <option value="">Todos</option>
                                        <option value="whatsapp">WhatsApp</option>
                                        <option value="email">E-mail</option>
                                    </select>
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="form-label mkt-hint mb-1">Status</label>
                                    <select class="form-select form-select-sm" name="status">
                                        <option value="">Todos</option>
                                        <option value="aguardando">Aguardando envio</option>
                                        <option value="enviando">Enviando</option>
                                        <option value="enviado">Enviado</option>
                                        <option value="falha">Falha no envio</option>
                                        <option value="cancelado">Cancelado</option>
                                    </select>
                                </div>
                                <div class="col-6 col-md-2 d-flex gap-2">
                                    <button type="submit" class="btn btn-sm btn-primary flex-fill"><i class="fas fa-filter me-1"></i>Filtrar</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnLimparLogs"><i class="fas fa-eraser"></i></button>
                                </div>
                            </form>
                            <div class="table-responsive mkt-scroll">
                                <table class="table table-hover mkt-tabela align-middle">
                                    <thead class="table-light"><tr>
                                        <th>Empresa</th><th>WhatsApp</th><th>E-mail</th><th>Canal</th><th>Template</th><th>Data/hora</th><th>Status</th><th>Retorno da API</th><th>Erro</th>
                                    </tr></thead>
                                    <tbody id="tabelaLogs"><tr><td colspan="9" class="text-center text-muted py-4">Carregando...</td></tr></tbody>
                                </table>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-xl-6">
                                <div class="mkt-card h-100">
                                    <h6 class="mb-1"><i class="fab fa-whatsapp me-2" style="color:#25D366;"></i>B) Template de WhatsApp</h6>
                                    <p class="mkt-hint mb-2">
                                        Variaveis:
                                        <span class="mkt-var" data-var="{nome_empresa}">{nome_empresa}</span>
                                        <span class="mkt-var" data-var="{nome_contato}">{nome_contato}</span>
                                        <span class="mkt-var" data-var="{nicho}">{nicho}</span>
                                        <span class="mkt-var" data-var="{cidade}">{cidade}</span>
                                        <span class="mkt-var" data-var="{estado}">{estado}</span>
                                    </p>
                                    <form id="formTplWa">
                                        <textarea class="form-control" name="conteudo" id="tplWaTexto" rows="7"><?= htmlspecialchars($tplWa['conteudo']) ?></textarea>
                                        <div class="border rounded-3 p-2 mt-2">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="mkt-hint"><i class="fas fa-image me-1"></i>Imagens do WhatsApp (opcional)</span>
                                                <span class="badge bg-light text-dark" id="waImgContador"><?= count($imagensWa) ?>/<?= MKT_MAX_IMAGENS_TEMPLATE ?></span>
                                            </div>
                                            <div id="waImgLista" class="d-flex flex-wrap gap-2"></div>
                                            <div class="d-flex flex-wrap gap-2 mt-2">
                                                <input type="file" class="form-control form-control-sm" id="waImgInput" accept="image/jpeg,image/png,image/gif,image/webp" style="max-width:260px">
                                                <button type="button" class="btn btn-sm btn-outline-primary" id="waImgUpload"><i class="fas fa-upload me-1"></i>Enviar imagem</button>
                                            </div>
                                            <div class="mkt-hint mt-1">A legenda do template acompanha a primeira imagem; as demais sao enviadas na sequencia, sem repetir o texto.</div>
                                        </div>
                                        <div class="d-flex flex-wrap gap-2 mt-2">
                                            <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-floppy-disk me-1"></i>Salvar template</button>
                                            <input type="tel" class="form-control form-control-sm" id="testeWaNumero" placeholder="DDD + numero para teste" style="max-width:220px">
                                            <button type="button" class="btn btn-sm btn-outline-success" id="btnTesteWa"><i class="fas fa-paper-plane me-1"></i>Enviar teste</button>
                                        </div>
                                    </form>
                                    <div class="mkt-preview mt-3">
                                        <div class="mkt-hint mb-2"><i class="fas fa-eye me-1"></i>Pre-visualizacao</div>
                                        <div class="mkt-preview-bolha" id="tplWaPreview"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-6">
                                <div class="mkt-card h-100">
                                    <h6 class="mb-1"><i class="fas fa-envelope-open-text me-2"></i>C) Template de e-mail</h6>
                                    <p class="mkt-hint mb-2">Mesmas variaveis do WhatsApp. O rodape de opt-out e incluido automaticamente no envio.</p>
                                    <form id="formTplEmail">
                                        <div class="row g-2 mb-2">
                                            <div class="col-md-6">
                                                <input type="text" class="form-control form-control-sm" name="remetente_nome" placeholder="Nome do remetente" value="<?= htmlspecialchars($tplEm['remetente_nome']) ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <input type="email" class="form-control form-control-sm" name="remetente_email" placeholder="E-mail do remetente" value="<?= htmlspecialchars($tplEm['remetente_email']) ?>">
                                            </div>
                                            <div class="col-12">
                                                <input type="text" class="form-control form-control-sm" name="assunto" placeholder="Assunto" value="<?= htmlspecialchars($tplEm['assunto']) ?>" required>
                                            </div>
                                            <div class="col-12">
                                                <textarea class="form-control font-monospace" name="conteudo" id="tplEmTexto" rows="8" style="font-size:.8rem;"><?= htmlspecialchars($tplEm['conteudo']) ?></textarea>
                                                <div class="mkt-hint mt-1">Use <code>{{imagem:ID}}</code> para posicionar a imagem no corpo. Ex.: clique em <strong>Inserir no corpo</strong> na lista abaixo.</div>
                                            </div>
                                        </div>
                                        <div class="border rounded-3 p-2 mb-2">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="mkt-hint"><i class="fas fa-images me-1"></i>Imagens do e-mail (opcional)</span>
                                                <span class="badge bg-light text-dark" id="emImgContador"><?= count($imagensEm) ?>/<?= MKT_MAX_IMAGENS_TEMPLATE ?></span>
                                            </div>
                                            <div id="emImgLista" class="d-flex flex-wrap gap-2"></div>
                                            <div class="d-flex flex-wrap gap-2 mt-2 align-items-center">
                                                <input type="file" class="form-control form-control-sm" id="emImgInput" accept="image/jpeg,image/png,image/gif,image/webp" style="max-width:240px">
                                                <select class="form-select form-select-sm" id="emImgModo" style="max-width:200px">
                                                    <option value="inline">No corpo do e-mail</option>
                                                    <option value="anexo">Como anexo</option>
                                                </select>
                                                <button type="button" class="btn btn-sm btn-outline-primary" id="emImgUpload"><i class="fas fa-upload me-1"></i>Enviar imagem</button>
                                            </div>
                                            <div class="mkt-hint mt-1">No corpo: a imagem entra no ponto do <code>{{imagem:ID}}</code> (sem placeholder, vai ao final). Como anexo: segue anexada ao e-mail.</div>
                                        </div>
                                        <div class="d-flex flex-wrap gap-2">
                                            <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-floppy-disk me-1"></i>Salvar template</button>
                                            <input type="email" class="form-control form-control-sm" id="testeEmailDestino" placeholder="E-mail para teste" style="max-width:220px">
                                            <button type="button" class="btn btn-sm btn-outline-success" id="btnTesteEmail"><i class="fas fa-paper-plane me-1"></i>Enviar teste</button>
                                        </div>
                                    </form>
                                    <div class="mkt-preview mt-3">
                                        <div class="mkt-hint mb-2"><i class="fas fa-eye me-1"></i>Pre-visualizacao</div>
                                        <iframe id="tplEmailPreview" sandbox="" title="Pre-visualizacao do e-mail"></iframe>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- ================= ENVIOS MANUAIS ================= -->
                    <div class="tab-pane fade" id="tab-manual" role="tabpanel">
                        <div class="row g-3 mb-4">
                            <div class="col-6 col-lg-3">
                                <div class="mkt-card mkt-stat">
                                    <div class="ico" style="background:#eef1f4;color:#475569;"><i class="fas fa-list"></i></div>
                                    <div><div class="val" id="manualStatListas">0</div><div class="lbl">Listas importadas</div></div>
                                </div>
                            </div>
                            <div class="col-6 col-lg-3">
                                <div class="mkt-card mkt-stat">
                                    <div class="ico" style="background:#e8f1ff;color:#1d4ed8;"><i class="fas fa-address-book"></i></div>
                                    <div><div class="val" id="manualStatContatos">0</div><div class="lbl">Contatos manuais</div></div>
                                </div>
                            </div>
                            <div class="col-6 col-lg-3">
                                <div class="mkt-card mkt-stat">
                                    <div class="ico" style="background:#fff6e5;color:#a86a00;"><i class="fas fa-bullhorn"></i></div>
                                    <div><div class="val" id="manualStatCampanhas">0</div><div class="lbl">Campanhas</div></div>
                                </div>
                            </div>
                            <div class="col-6 col-lg-3">
                                <div class="mkt-card mkt-stat">
                                    <div class="ico" style="background:#e8f7ef;color:#128a53;"><i class="fas fa-play"></i></div>
                                    <div><div class="val" id="manualStatAtivas">0</div><div class="lbl">Campanhas ativas</div></div>
                                </div>
                            </div>
                        </div>

                        <div class="mkt-card mb-4">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                                <div>
                                    <h6 class="mb-1"><i class="fas fa-gauge-high me-2"></i>Limites diarios (exclusivos dos envios manuais)</h6>
                                    <p class="mkt-hint mb-0">Os limites somam todas as campanhas manuais. A coleta automatica continua usando os limites proprios dela.</p>
                                </div>
                                <span class="badge bg-light text-dark" id="manualLimiteResumo">WhatsApp 0/0 · E-mail 0/0</span>
                            </div>
                            <form id="formManualLimites" class="row g-3 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label">WhatsApp por dia</label>
                                    <input type="number" class="form-control" name="whatsapp" id="manualLimiteWa" min="1" max="<?= (int) MKT_MANUAL_MAX_WHATSAPP_DIA ?>" value="<?= (int) MKT_MANUAL_MAX_WHATSAPP_DIA ?>">
                                    <div class="mkt-hint">Teto: <?= (int) MKT_MANUAL_MAX_WHATSAPP_DIA ?>/dia</div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">E-mails por dia</label>
                                    <input type="number" class="form-control" name="email" id="manualLimiteEm" min="1" max="<?= (int) MKT_MANUAL_MAX_EMAIL_DIA ?>" value="<?= (int) MKT_MANUAL_MAX_EMAIL_DIA ?>">
                                    <div class="mkt-hint">Teto: <?= (int) MKT_MANUAL_MAX_EMAIL_DIA ?>/dia</div>
                                </div>
                                <div class="col-md-6 d-flex flex-wrap gap-2">
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk me-1"></i>Salvar limites</button>
                                    <button type="button" class="btn btn-outline-success" id="btnManualProcessar"><i class="fas fa-bolt me-1"></i>Processar fila agora</button>
                                    <button type="button" class="btn btn-outline-secondary" id="btnManualAtualizar"><i class="fas fa-rotate me-1"></i>Atualizar</button>
                                </div>
                            </form>
                            <div class="mkt-hint mt-2" id="manualUsoHoje"></div>
                        </div>

                        <div class="mkt-card mb-4">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                                <div>
                                    <h6 class="mb-1"><i class="fas fa-layer-group me-2"></i>Filas independentes por canal</h6>
                                    <p class="mkt-hint mb-0">WhatsApp e e-mail tem filas, contadores e controles proprios. Pausar, concluir ou atingir o limite em uma fila nao afeta a outra. Contatos com apenas um canal participam somente da fila correspondente.</p>
                                </div>
                                <span class="badge bg-light text-dark" id="manualFilasResumo">Carregando...</span>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="border rounded-3 p-3 h-100">
                                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                            <h6 class="mb-0 text-success"><i class="fab fa-whatsapp me-1"></i>Fila de WhatsApp</h6>
                                            <span class="badge bg-light text-dark" id="manualFilaWaDisp">0 disponivel(is) hoje</span>
                                        </div>
                                        <div class="d-flex flex-wrap gap-4">
                                            <div><div class="h5 mb-0" id="manualFilaWaPendentes">0</div><div class="mkt-hint">Pendentes</div></div>
                                            <div><div class="h5 mb-0" id="manualFilaWaHoje">0</div><div class="mkt-hint">Enviados hoje</div></div>
                                            <div><div class="h5 mb-0" id="manualFilaWaFalhas">0</div><div class="mkt-hint">Falhas</div></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border rounded-3 p-3 h-100">
                                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                            <h6 class="mb-0 text-primary"><i class="fas fa-envelope me-1"></i>Fila de e-mail</h6>
                                            <span class="badge bg-light text-dark" id="manualFilaEmDisp">0 disponivel(is) hoje</span>
                                        </div>
                                        <div class="d-flex flex-wrap gap-4">
                                            <div><div class="h5 mb-0" id="manualFilaEmPendentes">0</div><div class="mkt-hint">Pendentes</div></div>
                                            <div><div class="h5 mb-0" id="manualFilaEmHoje">0</div><div class="mkt-hint">Enviados hoje</div></div>
                                            <div><div class="h5 mb-0" id="manualFilaEmFalhas">0</div><div class="mkt-hint">Falhas</div></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mkt-card mb-4">
                            <h6 class="mb-1"><i class="fas fa-file-import me-2"></i>Importar contatos (CSV ou XLSX)</h6>
                            <p class="mkt-hint mb-3">Colunas reconhecidas automaticamente: empresa, contato, WhatsApp, e-mail, nicho, cidade e estado. O arquivo e analisado antes da importacao definitiva.</p>
                            <form id="formManualImport" class="row g-3" autocomplete="off">
                                <div class="col-md-4">
                                    <label class="form-label">Lista existente</label>
                                    <select class="form-select" name="lista_id" id="impLista">
                                        <option value="">Selecione...</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Ou nova lista</label>
                                    <input type="text" class="form-control" name="lista_nome" id="impListaNome" placeholder="Ex.: Clientes ABC - novembro">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Arquivo *</label>
                                    <input type="file" class="form-control" name="arquivo" id="impArquivo" accept=".csv,.xlsx" required>
                                </div>
                                <div class="col-12 d-flex flex-wrap gap-2">
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-magnifying-glass me-1"></i>Analisar e pre-visualizar</button>
                                    <span class="mkt-hint align-self-center">Limite: 10 MB e <?= (int) MKT_IMPORT_MAX_LINHAS ?> linhas.</span>
                                </div>
                            </form>
                        </div>

                        <div class="mkt-card mb-4">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                <h6 class="mb-0"><i class="fas fa-user-plus me-2"></i>Cadastrar contato individual</h6>
                                <span class="mkt-hint">Aceita somente WhatsApp, somente e-mail ou ambos.</span>
                            </div>
                            <form id="formManualContato" class="row g-3" autocomplete="off">
                                <div class="col-md-4">
                                    <label class="form-label">Lista</label>
                                    <select class="form-select" name="lista_id" id="contatoLista">
                                        <option value="">Selecione...</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Ou nova lista</label>
                                    <input type="text" class="form-control" name="lista_nome" placeholder="Nome da nova lista">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Empresa *</label>
                                    <input type="text" class="form-control" name="empresa" id="contatoEmpresa" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Nome do contato</label>
                                    <input type="text" class="form-control" name="nome_contato">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">WhatsApp</label>
                                    <input type="text" class="form-control" name="whatsapp" placeholder="(11) 99999-9999">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">E-mail</label>
                                    <input type="email" class="form-control" name="email">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Nicho</label>
                                    <input type="text" class="form-control" name="nicho">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Cidade</label>
                                    <input type="text" class="form-control" name="cidade">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Estado</label>
                                    <select class="form-select" name="estado">
                                        <option value="">UF</option>
                                        <?php foreach ($ufs as $uf): ?><option value="<?= $uf ?>"><?= $uf ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-plus me-1"></i>Cadastrar contato</button>
                                </div>
                            </form>
                        </div>

                        <div class="mkt-card mb-4">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                <h6 class="mb-0"><i class="fas fa-list me-2"></i>Listas importadas</h6>
                                <span class="mkt-hint" id="manualListasResumo"></span>
                            </div>
                            <div class="table-responsive mkt-scroll">
                                <table class="table table-sm mkt-tabela align-middle mb-0">
                                    <thead><tr><th>Lista</th><th>Contatos</th><th>Campanhas</th><th>Criada em</th><th class="text-end">Acoes</th></tr></thead>
                                    <tbody id="tabelaManualListas"><tr><td colspan="5" class="text-muted text-center py-3">Carregando...</td></tr></tbody>
                                </table>
                            </div>
                        </div>

                        <div class="mkt-card mb-4">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                <div>
                                    <h6 class="mb-1"><i class="fas fa-bullhorn me-2"></i>Campanhas manuais</h6>
                                    <p class="mkt-hint mb-0">Usam sempre os templates principais de WhatsApp e e-mail da aba Marketing Disparo.</p>
                                </div>
                                <button type="button" class="btn btn-primary btn-sm" id="btnNovaCampanha"><i class="fas fa-plus me-1"></i>Nova campanha</button>
                            </div>
                            <div class="table-responsive mkt-scroll">
                                <table class="table table-sm mkt-tabela align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Campanha</th><th>Inicio</th><th>Contatos</th>
                                            <th>WhatsApp</th><th>E-mail</th><th>Status geral</th><th class="text-end">Acoes</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tabelaManualCampanhas"><tr><td colspan="7" class="text-muted text-center py-3">Carregando...</td></tr></tbody>
                                </table>
                            </div>
                        </div>

                        <div class="mkt-card">
                            <h6 class="mb-3"><i class="fas fa-clipboard-list me-2"></i>Logs dos envios manuais</h6>
                            <form id="formManualLogs" class="row g-2 align-items-end mb-3">
                                <div class="col-md-3">
                                    <label class="form-label">Campanha</label>
                                    <select class="form-select form-select-sm" name="campanha_id" id="logCampanha"><option value="">Todas</option></select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Canal</label>
                                    <select class="form-select form-select-sm" name="canal">
                                        <option value="">Todos</option>
                                        <option value="whatsapp">WhatsApp</option>
                                        <option value="email">E-mail</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Status</label>
                                    <select class="form-select form-select-sm" name="status">
                                        <option value="">Todos</option>
                                        <option value="aguardando">Aguardando</option>
                                        <option value="enviando">Enviando</option>
                                        <option value="enviado">Enviado</option>
                                        <option value="falha">Falha</option>
                                        <option value="cancelado">Cancelado</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">De</label>
                                    <input type="date" class="form-control form-control-sm" name="data_ini">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Ate</label>
                                    <input type="date" class="form-control form-control-sm" name="data_fim">
                                </div>
                                <div class="col-md-1">
                                    <button type="submit" class="btn btn-sm btn-outline-primary w-100"><i class="fas fa-filter"></i></button>
                                </div>
                            </form>
                            <div class="table-responsive mkt-scroll">
                                <table class="table table-sm mkt-tabela align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Destinatario</th><th>WhatsApp</th><th>E-mail</th><th>Canal</th><th>Campanha</th>
                                            <th>Data/Hora</th><th>Status</th><th>Retorno da API</th><th>Motivo da falha</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tabelaManualLogs"><tr><td colspan="9" class="text-muted text-center py-3">Carregando...</td></tr></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- ================= MODAIS: ENVIOS MANUAIS ================= -->
<div class="modal fade" id="modalManualImport" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-file-import me-2"></i>Pre-visualizacao da importacao</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div id="manualImportResumo" class="d-flex flex-wrap gap-2 mb-3"></div>
                <div id="manualImportColunas" class="mkt-hint mb-2"></div>
                <div class="table-responsive mkt-scroll">
                    <table class="table table-sm mkt-tabela align-middle mb-0">
                        <thead>
                            <tr><th>Linha</th><th>Empresa</th><th>Contato</th><th>WhatsApp</th><th>E-mail</th><th>Nicho</th><th>Cidade</th><th>UF</th><th>Status</th><th>Observacao</th></tr>
                        </thead>
                        <tbody id="tabelaManualImport"></tbody>
                    </table>
                </div>
                <div class="mkt-hint mt-2" id="manualImportAviso"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="btnManualImportCancelar">Cancelar importacao</button>
                <button type="button" class="btn btn-primary" id="btnManualImportConfirmar"><i class="fas fa-check me-1"></i>Confirmar importacao</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalManualCampanha" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formManualCampanha" autocomplete="off">
                <div class="modal-header">
                    <h5 class="modal-title" id="manualCampanhaTitulo">Nova campanha</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="campanhaId" value="">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Nome da campanha *</label>
                            <input type="text" class="form-control" name="nome" id="campanhaNome" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Canal *</label>
                            <select class="form-select" name="canais" id="campanhaCanais" required>
                                <option value="whatsapp">WhatsApp</option>
                                <option value="email">E-mail</option>
                                <option value="ambos" selected>Ambos</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descricao (opcional)</label>
                            <textarea class="form-control" name="descricao" id="campanhaDescricao" rows="2"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Lista de contatos *</label>
                            <select class="form-select" name="lista_id" id="campanhaLista" required>
                                <option value="">Selecione...</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Data de inicio *</label>
                            <input type="date" class="form-control" name="data_inicio" id="campanhaData" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Horario *</label>
                            <input type="time" class="form-control" name="hora_inicio" id="campanhaHora" required>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" name="ativa" id="campanhaAtiva" value="1">
                                <label class="form-check-label" for="campanhaAtiva">Ativar campanha (agendada ou em andamento)</label>
                            </div>
                            <div class="mkt-hint mt-1">Sem ativar, a campanha fica como rascunho e nao envia nada.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="submit" class="btn btn-primary" id="btnSalvarCampanha"><i class="fas fa-floppy-disk me-1"></i>Salvar campanha</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalManualPreview" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-eye me-2"></i>Previa da campanha</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="mkt-hint mb-3" id="manualPreviewInfo"></div>
                <div class="row g-4">
                    <div class="col-lg-5">
                        <h6 class="mb-2"><i class="fab fa-whatsapp me-1 text-success"></i>WhatsApp</h6>
                        <div class="mkt-preview"><div id="manualPreviewWa" class="mkt-preview-bolha"></div></div>
                    </div>
                    <div class="col-lg-7">
                        <h6 class="mb-2"><i class="fas fa-envelope me-1 text-primary"></i>E-mail</h6>
                        <div class="mkt-hint mb-1">Assunto: <strong id="manualPreviewAssunto"></strong></div>
                        <div class="mkt-preview"><iframe id="manualPreviewEmail" sandbox="" title="Previa do e-mail"></iframe></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalManualDetalhe" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-circle-info me-2"></i>Detalhes da campanha</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body" id="manualDetalheCorpo"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalManualContatos" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="manualContatosTitulo"><i class="fas fa-address-book me-2"></i>Contatos da lista</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive mkt-scroll">
                    <table class="table table-sm mkt-tabela align-middle mb-0">
                        <thead><tr><th>Empresa</th><th>Contato</th><th>WhatsApp</th><th>E-mail</th><th>Nicho</th><th>Cidade</th><th>UF</th><th>Opt-out</th></tr></thead>
                        <tbody id="tabelaManualContatosLista"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var API = 'api/marketing.php';
    var CSRF = <?= json_encode($csrf) ?>;
    var UFS = <?= json_encode($ufs) ?>;
    var MAX_IMGS = <?= (int) MKT_MAX_IMAGENS_TEMPLATE ?>;
    var IMAGENS = {
        whatsapp: <?= json_encode($imgJs($imagensWa), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        email: <?= json_encode($imgJs($imagensEm), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
    };
    var alerta = document.getElementById('mktAlerta');

    function esc(v) {
        return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function mostrar(tipo, msg) {
        alerta.className = 'alert alert-' + tipo + ' alert-dismissible fade show py-2';
        alerta.innerHTML = esc(msg) + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        window.scrollTo({ top: 0, behavior: 'smooth' });
        clearTimeout(mostrar._t);
        mostrar._t = setTimeout(function () { alerta.classList.add('d-none'); }, 12000);
    }

    function carregando(botao, ativo) {
        if (!botao) return;
        if (ativo) {
            if (!botao.dataset.html) botao.dataset.html = botao.innerHTML;
            botao.disabled = true;
            botao.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Aguarde...';
        } else {
            botao.disabled = false;
            if (botao.dataset.html) botao.innerHTML = botao.dataset.html;
        }
    }

    function api(acao, dados, botao) {
        var fd = new FormData();
        fd.append('acao', acao);
        fd.append('csrf', CSRF);
        Object.keys(dados || {}).forEach(function (k) { fd.append(k, dados[k]); });
        carregando(botao, true);
        return fetch(API, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json().catch(function () { return { ok: false, mensagem: 'Resposta invalida do servidor.' }; }); })
            .then(function (d) {
                if (!d.ok) mostrar('danger', d.mensagem || 'Falha na operacao.');
                return d;
            })
            .catch(function (e) { mostrar('danger', 'Erro de rede: ' + e.message); return { ok: false }; })
            .finally(function () { carregando(botao, false); });
    }

    // ---------------- Amostra para pre-visualizacao ----------------
    var amostra = {
        '{nome_empresa}': 'Empresa Exemplo',
        '{nome_contato}': 'Contato',
        '{nicho}': <?= json_encode($nicho['nome'] ?: 'seu segmento') ?>,
        '{cidade}': <?= json_encode($nicho['cidade'] ?: 'Sua Cidade') ?>,
        '{estado}': <?= json_encode($nicho['estado'] ?: 'UF') ?>
    };
    function renderAmostra(txt) {
        return String(txt || '').replace(/\{[a-z_]+\}/g, function (m) { return amostra[m] !== undefined ? amostra[m] : m; });
    }

    function previewWa() {
        var txt = renderAmostra(document.getElementById('tplWaTexto').value);
        txt = txt.replace(/\{\{imagem:\d+\}\}/g, '');
        var html = '';
        IMAGENS.whatsapp.forEach(function (img) {
            html += '<img src="' + esc(img.url) + '" alt="' + esc(img.nome) + '" style="max-width:70%;max-height:140px;border-radius:8px;display:block;margin:0 0 6px 0;">';
        });
        html += '<div style="white-space:pre-wrap;">' + esc(txt) + '</div>';
        document.getElementById('tplWaPreview').innerHTML = html;
    }
    function imgEmailPorId(id) {
        var achou = null;
        IMAGENS.email.forEach(function (img) { if (String(img.id) === String(id)) achou = img; });
        return achou;
    }
    function previewEmail() {
        var f = document.getElementById('formTplEmail');
        var html = renderAmostra(f.querySelector('[name="conteudo"]').value);
        html = html.replace(/\{\{imagem:(\d+)\}\}/g, function (m, id) {
            var img = imgEmailPorId(id);
            if (!img || img.modo === 'anexo') return '';
            return '<img src="' + esc(img.url) + '" alt="' + esc(img.nome) + '" style="max-width:100%;height:auto;display:block;margin:12px auto;">';
        });
        var assunto = renderAmostra(f.querySelector('[name="assunto"]').value);
        var anexos = IMAGENS.email.filter(function (img) { return img.modo === 'anexo'; });
        var rodapeAnexos = anexos.length
            ? '<div style="margin-top:14px;padding-top:8px;border-top:1px dashed #ddd;font-size:11px;color:#666;"><strong>Anexos:</strong> '
                + anexos.map(function (img) { return esc(img.nome); }).join(', ') + '</div>'
            : '';
        document.getElementById('tplEmailPreview').srcdoc = '<div style="font-family:Arial,sans-serif;font-size:13px;color:#222;padding:10px;">'
            + '<div style="border-bottom:1px solid #eee;padding-bottom:8px;margin-bottom:10px;"><strong>Assunto:</strong> ' + esc(assunto) + '</div>'
            + html + rodapeAnexos + '</div>';
    }

    // ---------------- Templates: imagens ----------------
    function badgeModo(img, canal) {
        if (canal !== 'email') return '<span class="badge bg-success">Imagem</span>';
        return img.modo === 'anexo'
            ? '<span class="badge bg-secondary">Anexo</span>'
            : '<span class="badge bg-success">Corpo</span>';
    }

    function renderImagens(canal) {
        var alvo = document.getElementById(canal === 'email' ? 'emImgLista' : 'waImgLista');
        var cont = document.getElementById(canal === 'email' ? 'emImgContador' : 'waImgContador');
        if (!alvo) return;
        var lista = IMAGENS[canal] || [];
        if (cont) cont.textContent = lista.length + '/' + MAX_IMGS;
        if (!lista.length) {
            alvo.innerHTML = '<div class="mkt-hint">Nenhuma imagem enviada.</div>';
            return;
        }
        alvo.innerHTML = lista.map(function (img) {
            var botoes = '';
            if (canal === 'email') {
                if (img.modo === 'anexo') {
                    botoes += '<button type="button" class="btn btn-sm btn-outline-success py-0 px-2 img-acao" data-acao="corpo" title="Colocar no corpo"><i class="fas fa-align-left"></i></button>';
                } else {
                    botoes += '<button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 img-acao" data-acao="inserir" title="Inserir no corpo"><i class="fas fa-sign-in-alt"></i></button>';
                    botoes += '<button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 img-acao" data-acao="anexo" title="Marcar como anexo"><i class="fas fa-paperclip"></i></button>';
                }
            }
            botoes += '<button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 img-acao" data-acao="excluir" title="Excluir"><i class="fas fa-trash"></i></button>';
            return '<div class="img-item" data-id="' + img.id + '" style="width:140px;border:1px solid var(--app-card-border);border-radius:10px;padding:6px;background:var(--app-card);">'
                + '<img src="' + esc(img.url) + '" alt="' + esc(img.nome) + '" style="width:100%;height:76px;object-fit:cover;border-radius:7px;border:0;">'
                + '<div class="mkt-hint text-truncate mt-1" title="' + esc(img.nome) + '">' + esc(img.nome) + '</div>'
                + '<div class="d-flex justify-content-between align-items-center mt-1">' + badgeModo(img, canal)
                + '<span class="mkt-hint">' + Math.max(1, Math.round(img.tamanho / 1024)) + ' KB</span></div>'
                + '<div class="d-flex flex-wrap gap-1 mt-1">' + botoes + '</div>'
                + '</div>';
        }).join('');
    }

    function inserirPlaceholderImagem(img) {
        var campo = document.getElementById('tplEmTexto');
        if (!campo) return;
        var ph = img.placeholder || ('{{imagem:' + img.id + '}}');
        var ini = campo.selectionStart || 0, fim = campo.selectionEnd || 0;
        var t = campo.value;
        campo.value = t.slice(0, ini) + ph + t.slice(fim);
        campo.focus();
        campo.selectionStart = campo.selectionEnd = ini + ph.length;
        previewEmail();
    }

    function carregarImagens(canal) {
        return api('listar_imagens', { canal: canal }, null).then(function (d) {
            if (d.ok && d.imagens) {
                IMAGENS[canal] = d.imagens;
                renderImagens(canal);
                if (canal === 'email') previewEmail(); else previewWa();
            }
        });
    }

    function enviarImagem(canal) {
        var ehEmail = canal === 'email';
        var input = document.getElementById(ehEmail ? 'emImgInput' : 'waImgInput');
        var botao = document.getElementById(ehEmail ? 'emImgUpload' : 'waImgUpload');
        var modo = ehEmail ? document.getElementById('emImgModo').value : 'inline';
        if (!input || !input.files || !input.files.length) {
            mostrar('warning', 'Selecione uma imagem para enviar.');
            return;
        }
        var arquivo = input.files[0];
        if (arquivo.size > 5 * 1024 * 1024) {
            mostrar('danger', 'A imagem excede o limite de 5 MB.');
            return;
        }
        if (!/^image\/(jpeg|png|gif|webp)$/.test(arquivo.type)) {
            mostrar('danger', 'Formato invalido. Envie uma imagem JPG, PNG, GIF ou WEBP.');
            return;
        }
        var fd = new FormData();
        fd.append('acao', 'upload_imagem');
        fd.append('csrf', CSRF);
        fd.append('canal', canal);
        fd.append('modo', modo);
        fd.append('imagem', arquivo);
        carregando(botao, true);
        fetch(API, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json().catch(function () { return { ok: false, mensagem: 'Resposta invalida do servidor.' }; }); })
            .then(function (d) {
                if (!d.ok) { mostrar('danger', d.mensagem || 'Falha no envio da imagem.'); return; }
                input.value = '';
                mostrar('success', d.mensagem || 'Imagem enviada.');
                return carregarImagens(canal).then(function () {
                    if (ehEmail && modo === 'inline' && d.imagem) inserirPlaceholderImagem(d.imagem);
                });
            })
            .catch(function (e) { mostrar('danger', 'Erro de rede: ' + e.message); })
            .finally(function () { carregando(botao, false); });
    }

    function ligarAcoesImagens(canal) {
        var alvo = document.getElementById(canal === 'email' ? 'emImgLista' : 'waImgLista');
        if (!alvo) return;
        alvo.addEventListener('click', function (e) {
            var btn = e.target.closest('.img-acao');
            if (!btn) return;
            var item = btn.closest('.img-item');
            if (!item) return;
            var id = parseInt(item.dataset.id, 10);
            var acao = btn.dataset.acao;
            var img = (IMAGENS[canal] || []).filter(function (i) { return i.id === id; })[0];
            if (!img) return;

            if (acao === 'inserir') { inserirPlaceholderImagem(img); return; }
            if (acao === 'excluir') {
                if (!confirm('Excluir esta imagem? O placeholder correspondente tambem sera removido do template salvo na proxima gravacao.')) return;
                api('excluir_imagem', { id: id }, btn).then(function (d) {
                    if (d.ok) { mostrar('success', d.mensagem); carregarImagens(canal); }
                });
                return;
            }
            var novoModo = acao === 'anexo' ? 'anexo' : 'inline';
            api('alterar_modo_imagem', { id: id, modo: novoModo }, btn).then(function (d) {
                if (d.ok) { mostrar('success', d.mensagem); carregarImagens(canal); }
            });
        });
    }

    ligarAcoesImagens('whatsapp');
    ligarAcoesImagens('email');
    renderImagens('whatsapp');
    renderImagens('email');
    document.getElementById('waImgUpload').addEventListener('click', function () { enviarImagem('whatsapp'); });
    document.getElementById('emImgUpload').addEventListener('click', function () { enviarImagem('email'); });

    // ---------------- Templates: variaveis ----------------
    var ultimoCampoTpl = document.getElementById('tplWaTexto');
    [document.getElementById('tplWaTexto'), document.querySelector('#formTplEmail [name="conteudo"]')].forEach(function (campo) {
        campo.addEventListener('focus', function () { ultimoCampoTpl = campo; });
    });
    document.querySelectorAll('.mkt-var').forEach(function (el) {
        el.addEventListener('click', function () {
            var alvo = ultimoCampoTpl;
            if (!alvo) return;
            var ini = alvo.selectionStart || 0, fim = alvo.selectionEnd || 0;
            var t = alvo.value;
            alvo.value = t.slice(0, ini) + el.dataset.var + t.slice(fim);
            alvo.focus();
            alvo.selectionStart = alvo.selectionEnd = ini + el.dataset.var.length;
            if (alvo.id === 'tplWaTexto') previewWa(); else previewEmail();
        });
    });

    // ---------------- ChatGPT ----------------
    document.getElementById('formOpenai').addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = this.querySelector('button[type="submit"]');
        api('salvar_openai', {
            api_key: this.querySelector('[name="api_key"]').value,
            modelo: this.querySelector('[name="modelo"]').value
        }, btn).then(function (d) {
            if (d.ok) {
                mostrar('success', d.mensagem);
                this.querySelector('[name="api_key"]').value = '';
            }
        }.bind(this));
    });
    document.getElementById('btnTestarOpenai').addEventListener('click', function () {
        api('testar_openai', {}, this).then(function (d) { if (d.ok) mostrar('success', d.mensagem); });
    });

    document.getElementById('formGoogle').addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = this.querySelector('button[type="submit"]');
        api('salvar_google', {
            google_key: this.querySelector('[name="google_key"]').value,
            google_cx: this.querySelector('[name="google_cx"]').value
        }, btn).then(function (d) {
            if (d.ok) {
                mostrar('success', d.mensagem);
                this.querySelector('[name="google_key"]').value = '';
            }
        }.bind(this));
    });
    document.getElementById('btnTestarGoogle').addEventListener('click', function () {
        api('testar_google', {}, this).then(function (d) { if (d.ok) mostrar('success', d.mensagem); });
    });

    // ---------------- Nicho ----------------
    document.getElementById('formNicho').addEventListener('submit', function (e) {
        e.preventDefault();
        var f = this;
        var btn = f.querySelector('button[type="submit"]');
        api('salvar_nicho', {
            nome: f.querySelector('[name="nome"]').value,
            descricao: f.querySelector('[name="descricao"]').value,
            cidade: f.querySelector('[name="cidade"]').value,
            estado: f.querySelector('[name="estado"]').value,
            regiao: f.querySelector('[name="regiao"]').value,
            limite_whatsapp: f.querySelector('[name="limite_whatsapp"]').value,
            limite_email: f.querySelector('[name="limite_email"]').value
        }, btn).then(function (d) { if (d.ok) mostrar('success', d.mensagem); });
    });

    document.getElementById('btnPesquisar').addEventListener('click', function () {
        if (!confirm('Iniciar a pesquisa manual agora? A coleta respeita o limite diario e descarta duplicados.')) return;
        var btn = this;
        api('pesquisar', {}, btn).then(function (d) {
            if (d.ok) { mostrar('success', d.mensagem); carregarContatos(); atualizarStatus(); }
        });
    });

    // ---------------- Cron ----------------
    document.getElementById('formCron').addEventListener('submit', function (e) {
        e.preventDefault();
        var f = this;
        api('salvar_cron', {
            ativo: document.getElementById('cronAtivo').checked ? '1' : '0',
            horario: f.querySelector('[name="horario"]').value
        }, f.querySelector('button[type="submit"]')).then(function (d) {
            if (d.ok) { mostrar('success', d.mensagem); atualizarStatus(); }
        });
    });
    document.getElementById('btnExecutarAgora').addEventListener('click', function () {
        if (!confirm('Executar a rotina completa agora (pesquisa + fila + envios)? A execucao manual nao interfere no agendamento diario.')) return;
        api('executar_agora', {}, this).then(function (d) {
            if (d.ok) { mostrar('success', d.mensagem); carregarContatos(); carregarLogs(); atualizarStatus(); }
        });
    });

    // ---------------- Disparo: fila e logs ----------------
    document.getElementById('btnProcessarFila').addEventListener('click', function () {
        api('processar_fila', {}, this).then(function (d) {
            if (d.ok) { mostrar('success', d.mensagem); carregarLogs(); atualizarStatus(); }
        });
    });

    function linhasLogs(logs) {
        if (!logs.length) return '<tr><td colspan="9" class="text-center text-muted py-4">Nenhum envio registrado com os filtros atuais.</td></tr>';
        return logs.map(function (l) {
            var tpl = l.canal === 'email' ? (l.assunto || 'E-mail') : 'WhatsApp';
            return '<tr>'
                + '<td class="trunc" title="' + esc(l.empresa) + '">' + esc(l.empresa) + '</td>'
                + '<td>' + esc(l.whatsapp) + '</td>'
                + '<td class="trunc" title="' + esc(l.email) + '">' + esc(l.email) + '</td>'
                + '<td>' + (l.canal === 'email' ? '<span class="badge bg-primary">E-mail</span>' : '<span class="badge" style="background:#128a53">WhatsApp</span>') + '</td>'
                + '<td class="trunc" title="' + esc(tpl) + '">' + esc(tpl) + '</td>'
                + '<td>' + esc(l.enviado_em || l.criado_em) + '</td>'
                + '<td><span class="mkt-status st-' + esc(l.status) + '">' + esc(l.status) + '</span></td>'
                + '<td class="trunc" title="' + esc(l.resposta_api) + '">' + esc(l.resposta_api) + '</td>'
                + '<td class="trunc text-danger" title="' + esc(l.erro) + '">' + esc(l.erro) + '</td>'
                + '</tr>';
        }).join('');
    }

    function carregarLogs() {
        var f = document.getElementById('formFiltroLogs');
        var dados = {};
        f.querySelectorAll('[name]').forEach(function (el) { dados[el.name] = el.value; });
        api('logs', dados, null).then(function (d) {
            if (d.ok) document.getElementById('tabelaLogs').innerHTML = linhasLogs(d.logs);
        });
    }
    document.getElementById('formFiltroLogs').addEventListener('submit', function (e) { e.preventDefault(); carregarLogs(); });
    document.getElementById('btnLimparLogs').addEventListener('click', function () {
        document.getElementById('formFiltroLogs').reset();
        carregarLogs();
    });

    // ---------------- Contatos ----------------
    function linhasContatos(lista) {
        if (!lista.length) return '<tr><td colspan="9" class="text-center text-muted py-4">Nenhum contato coletado ainda. Configure o nicho e inicie uma pesquisa.</td></tr>';
        return lista.map(function (c) {
            var acao = c.opt_out
                ? '<button class="btn btn-sm btn-outline-secondary btn-optout" data-id="' + c.id + '" data-opt="0" title="Reativar"><i class="fas fa-rotate-left"></i></button>'
                : '<button class="btn btn-sm btn-outline-danger btn-optout" data-id="' + c.id + '" data-opt="1" title="Bloquear / opt-out"><i class="fas fa-ban"></i></button>';
            return '<tr>'
                + '<td><div class="fw-semibold">' + esc(c.empresa) + '</div>' + (c.nome_contato ? '<div class="mkt-hint">' + esc(c.nome_contato) + '</div>' : '') + '</td>'
                + '<td class="trunc" title="' + esc(c.nicho) + '">' + esc(c.nicho) + '</td>'
                + '<td>' + esc(c.cidade) + (c.estado ? '/' + esc(c.estado) : '') + '</td>'
                + '<td>' + esc(c.whatsapp) + '</td>'
                + '<td class="trunc" title="' + esc(c.email) + '">' + esc(c.email) + '</td>'
                + '<td class="trunc" title="' + esc(c.origem) + '">' + (c.origem ? '<a href="' + esc(c.origem) + '" target="_blank" rel="noopener noreferrer">' + esc(c.origem) + '</a>' : '') + '</td>'
                + '<td>' + esc(c.data) + '</td>'
                + '<td><span class="mkt-status st-' + esc(c.status) + '">' + esc(c.status) + '</span></td>'
                + '<td class="text-end">' + acao + '</td>'
                + '</tr>';
        }).join('');
    }

    function carregarContatos() {
        api('contatos', { busca: document.getElementById('buscaContato').value }, null).then(function (d) {
            if (d.ok) {
                document.getElementById('tabelaContatos').innerHTML = linhasContatos(d.contatos);
                document.querySelectorAll('.btn-optout').forEach(function (b) {
                    b.addEventListener('click', function () {
                        var opt = b.dataset.opt;
                        if (opt === '1' && !confirm('Bloquear este contato? Ele sai da fila e nao recebe novos disparos.')) return;
                        api('bloquear_contato', { id: b.dataset.id, opt_out: opt }, b).then(function (r) {
                            if (r.ok) { mostrar('success', r.mensagem); carregarContatos(); atualizarStatus(); }
                        });
                    });
                });
            }
        });
    }
    document.getElementById('btnAtualizarContatos').addEventListener('click', carregarContatos);
    var buscaT = null;
    document.getElementById('buscaContato').addEventListener('input', function () {
        clearTimeout(buscaT);
        buscaT = setTimeout(carregarContatos, 350);
    });

    // ---------------- Templates: salvar/testar ----------------
    document.getElementById('formTplWa').addEventListener('submit', function (e) {
        e.preventDefault();
        api('salvar_template', { canal: 'whatsapp', conteudo: document.getElementById('tplWaTexto').value }, this.querySelector('button[type="submit"]'))
            .then(function (d) { if (d.ok) mostrar('success', d.mensagem); });
    });
    document.getElementById('formTplEmail').addEventListener('submit', function (e) {
        e.preventDefault();
        var f = this;
        api('salvar_template', {
            canal: 'email',
            remetente_nome: f.querySelector('[name="remetente_nome"]').value,
            remetente_email: f.querySelector('[name="remetente_email"]').value,
            assunto: f.querySelector('[name="assunto"]').value,
            conteudo: f.querySelector('[name="conteudo"]').value
        }, f.querySelector('button[type="submit"]')).then(function (d) { if (d.ok) mostrar('success', d.mensagem); });
    });
    document.getElementById('btnTesteWa').addEventListener('click', function () {
        api('testar_whatsapp', { numero: document.getElementById('testeWaNumero').value }, this)
            .then(function (d) { if (d.ok) mostrar('success', d.mensagem); });
    });
    document.getElementById('btnTesteEmail').addEventListener('click', function () {
        api('testar_email', { email: document.getElementById('testeEmailDestino').value }, this)
            .then(function (d) { if (d.ok) mostrar('success', d.mensagem); });
    });

    // ---------------- Status geral ----------------
    function atualizarStatus() {
        api('status_geral', {}, null).then(function (d) {
            if (!d.ok) return;
            Object.keys(d.stats).forEach(function (k) {
                document.querySelectorAll('[data-stat="' + k + '"]').forEach(function (el) { el.textContent = d.stats[k]; });
            });
            document.getElementById('cronProxima').textContent = d.cron.proxima || 'Automacao desativada';
            document.getElementById('cronUltima').textContent = d.cron.ultima
                ? d.cron.ultima.iniciado_em + ' (' + d.cron.ultima.origem + ')'
                : 'Nenhuma execucao registrada';
            var st = document.getElementById('cronStatus');
            st.innerHTML = d.cron.ultima
                ? '<span class="mkt-status st-' + esc(d.cron.ultima.status) + '">' + esc(d.cron.ultima.status) + '</span>'
                : '<span class="text-muted">-</span>';
            document.getElementById('cronJaHoje').textContent = d.cron.ja_executou_hoje
                ? 'A rotina automatica de hoje ja foi executada.'
                : 'A rotina automatica de hoje ainda nao foi executada.';

            var tb = document.getElementById('tabelaExecucoes');
            if (d.execucoes && d.execucoes.length) {
                tb.innerHTML = d.execucoes.map(function (e) {
                    return '<tr>'
                        + '<td>' + esc(e.iniciado_em) + '</td>'
                        + '<td>' + esc(e.origem) + '</td>'
                        + '<td><span class="mkt-status st-' + esc(e.status) + '">' + esc(e.status) + '</span></td>'
                        + '<td>' + e.whatsapp_novos + '</td>'
                        + '<td>' + e.emails_novos + '</td>'
                        + '<td>' + e.duplicados + '</td>'
                        + '<td>' + e.whatsapp_enviados + '</td>'
                        + '<td>' + e.emails_enviados + '</td>'
                        + '<td>' + e.falhas + '</td>'
                        + '<td class="trunc" title="' + esc(e.mensagem) + '">' + esc(e.mensagem) + '</td>'
                        + '</tr>';
                }).join('');
            }
        });
    }

    // ================= ENVIOS MANUAIS =================
    var manualPainel = { listas: [], campanhas: [] };
    var manualImportToken = '';

    function manualStatus(status) {
        var mapa = {
            rascunho: 'Rascunho', agendada: 'Agendada', em_andamento: 'Em andamento',
            pausada: 'Pausada', concluida: 'Concluida', cancelada: 'Cancelada',
            limite: 'Limite diario', sem_contatos: 'Sem contatos',
            aguardando: 'Aguardando', enviando: 'Enviando', enviado: 'Enviado',
            falha: 'Falha', cancelado: 'Cancelado', novo: 'Aprovado',
            existente: 'Ja existente', rejeitado: 'Rejeitado'
        };
        return mapa[status] || status;
    }
    function manualBadge(status) {
        var classe = {
            rascunho: 'st-coletado', agendada: 'st-na_fila', em_andamento: 'st-em_andamento',
            pausada: 'st-aguardando', concluida: 'st-concluido', cancelada: 'st-cancelado',
            limite: 'st-falha', sem_contatos: 'st-coletado',
            aguardando: 'st-aguardando', enviando: 'st-enviando', enviado: 'st-enviado',
            falha: 'st-falha', cancelado: 'st-cancelado', novo: 'st-enviado',
            existente: 'st-na_fila', rejeitado: 'st-falha'
        }[status] || 'st-coletado';
        return '<span class="mkt-status ' + classe + '">' + esc(manualStatus(status)) + '</span>';
    }
    function manualCanal(canal) {
        if (canal === 'whatsapp') return 'WhatsApp';
        if (canal === 'email') return 'E-mail';
        return 'Ambos';
    }
    function manualPreencherSelect(el, itens, rotulo, manter) {
        if (!el) return;
        var atual = manter ? el.value : '';
        var html = '<option value="">' + esc(rotulo) + '</option>';
        (itens || []).forEach(function (l) {
            html += '<option value="' + l.id + '">' + esc(l.nome) + '</option>';
        });
        el.innerHTML = html;
        if (atual) el.value = atual;
    }
    function manualSincronizarSelects() {
        manualPreencherSelect(document.getElementById('impLista'), manualPainel.listas, 'Selecione...', true);
        manualPreencherSelect(document.getElementById('contatoLista'), manualPainel.listas, 'Selecione...', true);
        manualPreencherSelect(document.getElementById('campanhaLista'), manualPainel.listas, 'Selecione...', true);
        manualPreencherSelect(document.getElementById('logCampanha'), manualPainel.campanhas, 'Todas', true);
    }
    function manualRenderListas(listas) {
        var tb = document.getElementById('tabelaManualListas');
        document.getElementById('manualListasResumo').textContent = listas.length + ' lista(s)';
        if (!listas.length) {
            tb.innerHTML = '<tr><td colspan="5" class="text-muted text-center py-3">Nenhuma lista importada ainda.</td></tr>';
            return;
        }
        tb.innerHTML = listas.map(function (l) {
            return '<tr>'
                + '<td><strong>' + esc(l.nome) + '</strong>' + (l.descricao ? '<div class="mkt-hint">' + esc(l.descricao) + '</div>' : '') + '</td>'
                + '<td>' + l.total_contatos + '</td>'
                + '<td>' + l.total_campanhas + '</td>'
                + '<td>' + esc(l.criado_em) + '</td>'
                + '<td class="text-end">'
                + '<button type="button" class="btn btn-sm btn-outline-secondary me-1" data-manual="ver-contatos" data-id="' + l.id + '" title="Ver contatos"><i class="fas fa-address-book"></i></button>'
                + '<button type="button" class="btn btn-sm btn-outline-danger" data-manual="excluir-lista" data-id="' + l.id + '" data-nome="' + esc(l.nome) + '" title="Excluir lista"><i class="fas fa-trash"></i></button>'
                + '</td></tr>';
        }).join('');
    }
    function manualCanalIncluido(c, canal) {
        return c.canais === 'ambos' || c.canais === canal;
    }
    function manualCanalCelula(c, canal) {
        if (!manualCanalIncluido(c, canal)) {
            return '<span class="mkt-hint">Nao participa</span>';
        }
        var ehWa = canal === 'whatsapp';
        var enviados = ehWa ? c.wa_enviados : c.em_enviados;
        var total = ehWa ? c.wa_total : c.em_total;
        var pend = ehWa ? c.wa_pendentes : c.em_pendentes;
        var falhas = ehWa ? c.wa_falhas : c.em_falhas;
        var st = ehWa ? c.wa_status : c.em_status;
        var ativa = ehWa ? c.wa_ativa : c.em_ativa;
        // So faz sentido pausar/retomar filas que ainda tem envios pendentes.
        var podeControlar = ['agendada', 'em_andamento', 'pausada'].indexOf(c.status) !== -1 && pend > 0;
        var botao = '';
        if (podeControlar) {
            if (ativa) {
                botao = '<button type="button" class="btn btn-sm btn-outline-warning py-0 px-1" data-manual="pausar_canal" data-canal="' + canal + '" data-id="' + c.id + '" title="Pausar a fila de ' + (ehWa ? 'WhatsApp' : 'e-mail') + '"><i class="fas fa-pause"></i></button>';
            } else {
                botao = '<button type="button" class="btn btn-sm btn-outline-success py-0 px-1" data-manual="retomar_canal" data-canal="' + canal + '" data-id="' + c.id + '" title="Retomar a fila de ' + (ehWa ? 'WhatsApp' : 'e-mail') + '"><i class="fas fa-play"></i></button>';
            }
        }
        return '<div class="d-flex flex-column gap-1">'
            + '<div><strong>' + enviados + '/' + total + '</strong> <span class="mkt-hint">' + pend + ' pend. · ' + falhas + ' falha(s)</span></div>'
            + '<div class="d-flex align-items-center gap-1">' + manualBadge(st) + botao + '</div>'
            + '</div>';
    }
    function manualRenderCampanhas(campanhas) {
        var tb = document.getElementById('tabelaManualCampanhas');
        if (!campanhas.length) {
            tb.innerHTML = '<tr><td colspan="7" class="text-muted text-center py-3">Nenhuma campanha cadastrada.</td></tr>';
            return;
        }
        tb.innerHTML = campanhas.map(function (c) {
            var acoes = '<button type="button" class="btn btn-sm btn-outline-secondary me-1" data-manual="detalhe" data-id="' + c.id + '" title="Visualizar"><i class="fas fa-eye"></i></button>';
            acoes += '<button type="button" class="btn btn-sm btn-outline-secondary me-1" data-manual="preview" data-id="' + c.id + '" title="Previa"><i class="fas fa-image"></i></button>';
            if (c.status === 'rascunho' || c.status === 'agendada' || c.status === 'pausada') {
                acoes += '<button type="button" class="btn btn-sm btn-outline-primary me-1" data-manual="iniciar" data-id="' + c.id + '" title="Iniciar"><i class="fas fa-play"></i></button>';
            }
            if (c.status === 'agendada' || c.status === 'em_andamento') {
                acoes += '<button type="button" class="btn btn-sm btn-outline-warning me-1" data-manual="pausar" data-id="' + c.id + '" title="Pausar os dois canais"><i class="fas fa-pause"></i></button>';
            }
            if (c.status === 'pausada') {
                acoes += '<button type="button" class="btn btn-sm btn-outline-success me-1" data-manual="retomar" data-id="' + c.id + '" title="Retomar os dois canais"><i class="fas fa-rotate-right"></i></button>';
            }
            if (c.status === 'rascunho' || c.status === 'agendada' || c.status === 'em_andamento' || c.status === 'pausada') {
                acoes += '<button type="button" class="btn btn-sm btn-outline-secondary me-1" data-manual="editar" data-id="' + c.id + '" title="Editar"><i class="fas fa-pen"></i></button>';
                acoes += '<button type="button" class="btn btn-sm btn-outline-danger me-1" data-manual="cancelar" data-id="' + c.id + '" title="Cancelar"><i class="fas fa-ban"></i></button>';
            }
            acoes += '<button type="button" class="btn btn-sm btn-outline-dark" data-manual="logs" data-id="' + c.id + '" title="Logs"><i class="fas fa-clipboard-list"></i></button>';
            return '<tr>'
                + '<td><strong>' + esc(c.nome) + '</strong><div class="mkt-hint">' + esc(c.lista_nome) + ' · ' + esc(manualCanal(c.canais)) + '</div></td>'
                + '<td>' + esc(c.data_inicio) + ' ' + esc(c.hora_inicio) + '</td>'
                + '<td>' + c.total_contatos + '</td>'
                + '<td>' + manualCanalCelula(c, 'whatsapp') + '</td>'
                + '<td>' + manualCanalCelula(c, 'email') + '</td>'
                + '<td>' + manualBadge(c.status) + '</td>'
                + '<td class="text-end" style="min-width:230px;">' + acoes + '</td></tr>';
        }).join('');
    }
    function manualAplicarPainel(d) {
        manualPainel.listas = d.listas || [];
        manualPainel.campanhas = d.campanhas || [];
        document.getElementById('manualStatListas').textContent = d.stats.listas;
        document.getElementById('manualStatContatos').textContent = d.stats.contatos_manuais;
        document.getElementById('manualStatCampanhas').textContent = d.stats.campanhas;
        document.getElementById('manualStatAtivas').textContent = d.stats.campanhas_ativas;
        document.getElementById('manualLimiteWa').value = d.limites.whatsapp;
        document.getElementById('manualLimiteEm').value = d.limites.email;
        document.getElementById('manualLimiteResumo').textContent = 'WhatsApp ' + d.uso.whatsapp + '/' + d.limites.whatsapp + ' · E-mail ' + d.uso.email + '/' + d.limites.email;
        document.getElementById('manualUsoHoje').textContent = 'Hoje: ' + d.uso.whatsapp + ' WhatsApp e ' + d.uso.email
            + ' e-mail(s) enviado(s). Disponivel agora: ' + d.disponiveis.whatsapp + ' WhatsApp e ' + d.disponiveis.email + ' e-mail(s).';
        // Resumo independente de cada fila (pendentes, enviados hoje e falhas).
        var filas = d.filas || {};
        var filaWa = filas.whatsapp || { pendentes: 0, hoje: 0, falhas: 0 };
        var filaEm = filas.email || { pendentes: 0, hoje: 0, falhas: 0 };
        document.getElementById('manualFilaWaPendentes').textContent = filaWa.pendentes;
        document.getElementById('manualFilaWaHoje').textContent = filaWa.hoje;
        document.getElementById('manualFilaWaFalhas').textContent = filaWa.falhas;
        document.getElementById('manualFilaEmPendentes').textContent = filaEm.pendentes;
        document.getElementById('manualFilaEmHoje').textContent = filaEm.hoje;
        document.getElementById('manualFilaEmFalhas').textContent = filaEm.falhas;
        document.getElementById('manualFilaWaDisp').textContent = d.disponiveis.whatsapp + ' disponivel(is) hoje';
        document.getElementById('manualFilaEmDisp').textContent = d.disponiveis.email + ' disponivel(is) hoje';
        document.getElementById('manualFilasResumo').textContent = 'WhatsApp: ' + filaWa.pendentes + ' pendente(s) · E-mail: ' + filaEm.pendentes + ' pendente(s)';
        manualSincronizarSelects();
        manualRenderListas(manualPainel.listas);
        manualRenderCampanhas(manualPainel.campanhas);
    }
    function carregarManual() {
        return api('manual_painel', {}, null).then(function (d) {
            if (!d.ok) return;
            manualAplicarPainel(d);
        });
    }
    function carregarManualLogs() {
        var f = document.getElementById('formManualLogs');
        var dados = {};
        new FormData(f).forEach(function (v, k) { dados[k] = v; });
        api('manual_logs', dados, null).then(function (d) {
            if (!d.ok) return;
            var tb = document.getElementById('tabelaManualLogs');
            if (!d.logs.length) {
                tb.innerHTML = '<tr><td colspan="9" class="text-muted text-center py-3">Nenhum envio manual registrado com estes filtros.</td></tr>';
                return;
            }
            tb.innerHTML = d.logs.map(function (l) {
                return '<tr>'
                    + '<td><strong>' + esc(l.empresa) + '</strong>' + (l.nome_contato ? '<div class="mkt-hint">' + esc(l.nome_contato) + '</div>' : '') + '</td>'
                    + '<td>' + esc(l.whatsapp) + '</td>'
                    + '<td>' + esc(l.email) + '</td>'
                    + '<td>' + esc(manualCanal(l.canal)) + '</td>'
                    + '<td>' + esc(l.campanha) + '</td>'
                    + '<td>' + esc(l.enviado_em || l.criado_em) + '</td>'
                    + '<td>' + manualBadge(l.status) + '</td>'
                    + '<td class="trunc" title="' + esc(l.resposta_api) + '">' + esc(l.resposta_api) + '</td>'
                    + '<td class="trunc" title="' + esc(l.erro) + '">' + esc(l.erro) + '</td>'
                    + '</tr>';
            }).join('');
        });
    }

    document.getElementById('formManualLimites').addEventListener('submit', function (e) {
        e.preventDefault();
        var f = e.target;
        api('manual_limites_salvar', {
            whatsapp: f.querySelector('[name="whatsapp"]').value,
            email: f.querySelector('[name="email"]').value
        }, f.querySelector('button[type="submit"]')).then(function (d) {
            if (!d.ok) return;
            mostrar('success', d.mensagem);
            carregarManual();
        });
    });
    document.getElementById('btnManualProcessar').addEventListener('click', function () {
        api('manual_processar_agora', {}, this).then(function (d) {
            if (!d.ok) return;
            mostrar('success', d.mensagem);
            carregarManual();
            carregarManualLogs();
        });
    });
    document.getElementById('btnManualAtualizar').addEventListener('click', function () {
        carregarManual().then(function () { carregarManualLogs(); });
    });

    document.getElementById('formManualImport').addEventListener('submit', function (e) {
        e.preventDefault();
        var f = e.target;
        var botao = f.querySelector('button[type="submit"]');
        var fd = new FormData(f);
        fd.append('acao', 'manual_importar_previa');
        fd.append('csrf', CSRF);
        carregando(botao, true);
        fetch(API, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json().catch(function () { return { ok: false, mensagem: 'Resposta invalida do servidor.' }; }); })
            .then(function (d) {
                if (!d.ok) { mostrar('danger', d.mensagem || 'Falha ao analisar o arquivo.'); return; }
                manualImportToken = d.token;
                var resumo = d.resumo;
                document.getElementById('manualImportResumo').innerHTML = [
                    ['Total', resumo.total, 'bg-light text-dark'],
                    ['Aprovados', resumo.aprovados, 'text-bg-success'],
                    ['Ja existentes', resumo.ja_existentes, 'text-bg-primary'],
                    ['Rejeitados', resumo.rejeitados, 'text-bg-danger'],
                    ['Duplicados', resumo.duplicados, 'text-bg-warning'],
                    ['Incompletos', resumo.incompletos, 'text-bg-secondary'],
                    ['Invalidos', resumo.invalidos, 'text-bg-dark'],
                    ['Linhas ignoradas', resumo.ignorados, 'bg-light text-dark']
                ].map(function (b) {
                    return '<span class="badge ' + b[2] + '">' + b[0] + ': ' + b[1] + '</span>';
                }).join('');
                var colunas = Object.keys(d.colunas || {}).map(function (k) { return k + ' → "' + d.colunas[k] + '"'; });
                document.getElementById('manualImportColunas').innerHTML = '<i class="fas fa-table-columns me-1"></i>Colunas identificadas: ' + esc(colunas.join(' · '));
                document.getElementById('tabelaManualImport').innerHTML = (d.linhas || []).map(function (l) {
                    return '<tr>'
                        + '<td>' + l.numero + '</td>'
                        + '<td>' + esc(l.empresa) + '</td>'
                        + '<td>' + esc(l.nome_contato) + '</td>'
                        + '<td>' + esc(l.whatsapp) + '</td>'
                        + '<td>' + esc(l.email) + '</td>'
                        + '<td>' + esc(l.nicho) + '</td>'
                        + '<td>' + esc(l.cidade) + '</td>'
                        + '<td>' + esc(l.estado) + '</td>'
                        + '<td>' + manualBadge(l.status) + '</td>'
                        + '<td class="trunc" title="' + esc(l.motivo) + '">' + esc(l.motivo) + '</td>'
                        + '</tr>';
                }).join('') || '<tr><td colspan="10" class="text-muted text-center py-3">Nenhuma linha de dados encontrada.</td></tr>';
                document.getElementById('manualImportAviso').textContent = d.total_linhas > d.linhas_exibidas
                    ? 'Exibindo as primeiras ' + d.linhas_exibidas + ' de ' + d.total_linhas + ' linha(s). Todas serao processadas ao confirmar.'
                    : 'Todas as linhas foram exibidas.';
                new bootstrap.Modal(document.getElementById('modalManualImport')).show();
            })
            .catch(function (erro) { mostrar('danger', 'Erro de rede: ' + erro.message); })
            .finally(function () { carregando(botao, false); });
    });
    document.getElementById('btnManualImportConfirmar').addEventListener('click', function () {
        if (!manualImportToken) { mostrar('danger', 'Analise um arquivo antes de confirmar.'); return; }
        var listaId = document.getElementById('impLista').value;
        var listaNome = document.getElementById('impListaNome').value.trim();
        if (!listaId && !listaNome) {
            mostrar('danger', 'Selecione uma lista existente ou informe o nome de uma nova lista.');
            return;
        }
        api('manual_importar_confirmar', { token: manualImportToken, lista_id: listaId, lista_nome: listaNome }, this).then(function (d) {
            if (!d.ok) return;
            manualImportToken = '';
            bootstrap.Modal.getInstance(document.getElementById('modalManualImport')).hide();
            mostrar('success', d.mensagem);
            document.getElementById('formManualImport').reset();
            carregarManual();
        });
    });
    document.getElementById('btnManualImportCancelar').addEventListener('click', function () {
        var token = manualImportToken;
        manualImportToken = '';
        bootstrap.Modal.getInstance(document.getElementById('modalManualImport')).hide();
        if (token) api('manual_importar_cancelar', { token: token }, this);
        document.getElementById('formManualImport').reset();
    });

    document.getElementById('formManualContato').addEventListener('submit', function (e) {
        e.preventDefault();
        var f = e.target;
        var dados = {};
        new FormData(f).forEach(function (v, k) { dados[k] = v; });
        if (!dados.lista_id && !String(dados.lista_nome || '').trim()) {
            mostrar('danger', 'Selecione uma lista existente ou informe o nome de uma nova lista.');
            return;
        }
        api('manual_contato_criar', dados, f.querySelector('button[type="submit"]')).then(function (d) {
            if (!d.ok) return;
            mostrar('success', d.mensagem);
            f.reset();
            carregarManual();
        });
    });

    document.getElementById('btnNovaCampanha').addEventListener('click', function () {
        var f = document.getElementById('formManualCampanha');
        f.reset();
        document.getElementById('campanhaId').value = '';
        document.getElementById('manualCampanhaTitulo').textContent = 'Nova campanha';
        document.getElementById('campanhaData').value = new Date().toISOString().slice(0, 10);
        document.getElementById('campanhaHora').value = '09:00';
        document.getElementById('campanhaAtiva').checked = true;
        manualSincronizarSelects();
        new bootstrap.Modal(document.getElementById('modalManualCampanha')).show();
    });

    document.getElementById('formManualCampanha').addEventListener('submit', function (e) {
        e.preventDefault();
        var f = e.target;
        var dados = {};
        new FormData(f).forEach(function (v, k) { dados[k] = v; });
        dados.ativa = document.getElementById('campanhaAtiva').checked ? '1' : '0';
        var id = document.getElementById('campanhaId').value;
        var acao = id ? 'manual_campanha_editar' : 'manual_campanha_criar';
        api(acao, dados, document.getElementById('btnSalvarCampanha')).then(function (d) {
            if (!d.ok) return;
            mostrar('success', d.mensagem);
            bootstrap.Modal.getInstance(document.getElementById('modalManualCampanha')).hide();
            carregarManual();
        });
    });

    document.getElementById('tabelaManualListas').addEventListener('click', function (e) {
        var botao = e.target.closest('[data-manual]');
        if (!botao) return;
        var id = parseInt(botao.dataset.id, 10);
        if (botao.dataset.manual === 'excluir-lista') {
            if (!confirm('Excluir a lista "' + botao.dataset.nome + '"? Os contatos continuam na base geral.')) return;
            api('manual_lista_excluir', { id: id }, botao).then(function (d) {
                if (!d.ok) return;
                mostrar('success', d.mensagem);
                carregarManual();
            });
            return;
        }
        if (botao.dataset.manual === 'ver-contatos') {
            api('manual_lista_contatos', { lista_id: id }, botao).then(function (d) {
                if (!d.ok) return;
                document.getElementById('manualContatosTitulo').innerHTML = '<i class="fas fa-address-book me-2"></i>' + esc(d.lista.nome);
                document.getElementById('tabelaManualContatosLista').innerHTML = d.contatos.length
                    ? d.contatos.map(function (c) {
                        return '<tr><td>' + esc(c.empresa) + '</td><td>' + esc(c.nome_contato) + '</td><td>' + esc(c.whatsapp) + '</td><td>' + esc(c.email) + '</td><td>' + esc(c.nicho) + '</td><td>' + esc(c.cidade) + '</td><td>' + esc(c.estado) + '</td><td>' + (c.opt_out ? '<span class="mkt-status st-bloqueado">Sim</span>' : 'Nao') + '</td></tr>';
                    }).join('')
                    : '<tr><td colspan="8" class="text-muted text-center py-3">Lista sem contatos.</td></tr>';
                new bootstrap.Modal(document.getElementById('modalManualContatos')).show();
            });
        }
    });

    document.getElementById('tabelaManualCampanhas').addEventListener('click', function (e) {
        var botao = e.target.closest('[data-manual]');
        if (!botao) return;
        var id = parseInt(botao.dataset.id, 10);
        var acao = botao.dataset.manual;
        if (acao === 'logs') {
            document.getElementById('logCampanha').value = String(id);
            carregarManualLogs();
            document.getElementById('formManualLogs').scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        if (acao === 'detalhe') {
            api('manual_campanha_obter', { id: id }, botao).then(function (d) {
                if (!d.ok) return;
                var c = d.campanha, m = d.metricas;
                function blocoCanal(titulo, icone, status, enviados, total, pend, falhas) {
                    return '<div class="col-md-6"><div class="border rounded-3 p-3 h-100">'
                        + '<div class="d-flex flex-wrap justify-content-between align-items-center gap-1 mb-2"><strong><i class="' + icone + ' me-1"></i>' + titulo + '</strong>' + manualBadge(status) + '</div>'
                        + '<div class="d-flex flex-wrap gap-3">'
                        + '<div><div class="h5 mb-0">' + enviados + '/' + total + '</div><div class="mkt-hint">Enviados</div></div>'
                        + '<div><div class="h5 mb-0">' + pend + '</div><div class="mkt-hint">Pendentes</div></div>'
                        + '<div><div class="h5 mb-0">' + falhas + '</div><div class="mkt-hint">Falhas</div></div>'
                        + '</div></div></div>';
                }
                var canaisHtml = '';
                if (c.canais === 'ambos' || c.canais === 'whatsapp') {
                    canaisHtml += blocoCanal('WhatsApp', 'fab fa-whatsapp text-success', c.wa_status, m.wa_enviados, m.wa_total, m.wa_pendentes, m.wa_falhas);
                }
                if (c.canais === 'ambos' || c.canais === 'email') {
                    canaisHtml += blocoCanal('E-mail', 'fas fa-envelope text-primary', c.em_status, m.em_enviados, m.em_total, m.em_pendentes, m.em_falhas);
                }
                document.getElementById('manualDetalheCorpo').innerHTML = ''
                    + '<div class="row g-3">'
                    + '<div class="col-md-8"><strong>' + esc(c.nome) + '</strong><div class="mkt-hint">' + esc(c.descricao || 'Sem descricao') + '</div></div>'
                    + '<div class="col-md-4 text-md-end">' + manualBadge(c.status) + '</div>'
                    + '<div class="col-md-4"><span class="mkt-hint">Lista</span><div>' + esc(c.lista_nome) + '</div></div>'
                    + '<div class="col-md-4"><span class="mkt-hint">Canal</span><div>' + esc(manualCanal(c.canais)) + '</div></div>'
                    + '<div class="col-md-4"><span class="mkt-hint">Inicio</span><div>' + esc(c.data_inicio) + ' ' + esc(c.hora_inicio) + '</div></div>'
                    + '<div class="col-md-4"><span class="mkt-hint">Criada em</span><div>' + esc(c.criado_em) + '</div></div>'
                    + '<div class="col-md-4"><span class="mkt-hint">Iniciada em</span><div>' + esc(c.iniciada_em || '-') + '</div></div>'
                    + '<div class="col-md-4"><span class="mkt-hint">Concluida em</span><div>' + esc(c.concluida_em || '-') + '</div></div>'
                    + '</div><hr><div class="row g-3">' + canaisHtml + '</div>'
                    + '<hr><div class="row g-3 text-center">'
                    + '<div class="col-4 col-md-3"><div class="val h5 mb-0">' + m.total_contatos + '</div><div class="mkt-hint">Contatos na lista</div></div>'
                    + '<div class="col-4 col-md-3"><div class="val h5 mb-0">' + m.pendentes + '</div><div class="mkt-hint">Pendentes no total</div></div>'
                    + '<div class="col-4 col-md-3"><div class="val h5 mb-0">' + m.falhas + '</div><div class="mkt-hint">Falhas no total</div></div>'
                    + '<div class="col-4 col-md-3"><div class="val h5 mb-0">' + m.cancelados + '</div><div class="mkt-hint">Cancelados</div></div>'
                    + '</div>';
                new bootstrap.Modal(document.getElementById('modalManualDetalhe')).show();
            });
            return;
        }
        if (acao === 'preview') {
            api('manual_campanha_preview', { id: id }, botao).then(function (d) {
                if (!d.ok) return;
                document.getElementById('manualPreviewInfo').textContent = 'Campanha "' + d.campanha.nome + '" · canal: ' + manualCanal(d.campanha.canais)
                    + ' · exemplo com o contato: ' + (d.contato.empresa || 'sem contato') + '. As variaveis sao preenchidas com os dados de cada destinatario.';
                document.getElementById('manualPreviewWa').textContent = d.whatsapp.texto || 'Canal nao utilizado.';
                document.getElementById('manualPreviewAssunto').textContent = d.email.assunto || '-';
                document.getElementById('manualPreviewEmail').srcdoc = d.email.html || '';
                new bootstrap.Modal(document.getElementById('modalManualPreview')).show();
            });
            return;
        }
        if (acao === 'editar') {
            api('manual_campanha_obter', { id: id }, botao).then(function (d) {
                if (!d.ok) return;
                var c = d.campanha;
                document.getElementById('campanhaId').value = c.id;
                document.getElementById('manualCampanhaTitulo').textContent = 'Editar campanha';
                document.getElementById('campanhaNome').value = c.nome;
                document.getElementById('campanhaDescricao').value = c.descricao;
                document.getElementById('campanhaCanais').value = c.canais;
                manualSincronizarSelects();
                document.getElementById('campanhaLista').value = String(c.lista_id);
                document.getElementById('campanhaData').value = c.data_inicio_iso;
                document.getElementById('campanhaHora').value = c.hora_inicio;
                document.getElementById('campanhaAtiva').checked = !!c.ativa;
                new bootstrap.Modal(document.getElementById('modalManualCampanha')).show();
            });
            return;
        }
        if (acao === 'cancelar' && !confirm('Cancelar esta campanha? Os envios pendentes serao cancelados.')) return;
        if (acao === 'pausar_canal' || acao === 'retomar_canal') {
            // Controle independente por canal: pausa/retoma somente esta fila.
            api('manual_campanha_acao', { id: id, subacao: acao, canal: botao.dataset.canal || '' }, botao).then(function (d) {
                if (!d.ok) return;
                mostrar('success', d.mensagem);
                carregarManual();
            });
            return;
        }
        var rotulos = { iniciar: 'Campanha iniciada.', pausar: 'Campanha pausada.', retomar: 'Campanha retomada.', cancelar: 'Campanha cancelada.' };
        api('manual_campanha_acao', { id: id, subacao: acao }, botao).then(function (d) {
            if (!d.ok) return;
            mostrar('success', d.mensagem || rotulos[acao] || 'Acao concluida.');
            carregarManual();
            carregarManualLogs();
        });
    });

    document.getElementById('formManualLogs').addEventListener('submit', function (e) {
        e.preventDefault();
        carregarManualLogs();
    });

    // ---------------- Inicializacao ----------------
    var tabAtiva = sessionStorage.getItem('mktTab');
    if (tabAtiva) {
        var el = document.querySelector('[data-bs-target="' + tabAtiva + '"]');
        if (el) new bootstrap.Tab(el).show();
    }
    document.querySelectorAll('[data-bs-toggle="pill"]').forEach(function (el) {
        el.addEventListener('shown.bs.tab', function () {
            sessionStorage.setItem('mktTab', el.dataset.bsTarget);
        });
    });

    previewWa();
    previewEmail();
    document.getElementById('tplWaTexto').addEventListener('input', previewWa);
    document.getElementById('formTplEmail').addEventListener('input', previewEmail);
    carregarContatos();
    carregarLogs();
    carregarManual();
    carregarManualLogs();
    setInterval(function () { if (!document.hidden) atualizarStatus(); }, 20000);
});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
