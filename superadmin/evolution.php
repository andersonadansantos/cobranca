<?php
require_once __DIR__ . '/auth.php';
requireSuper();
require_once __DIR__ . '/../config/marketing_helpers.php';

$pageTitle = 'API Evolution';
$csrf = mkt_csrf_token();
$evo = mkt_evolution_config();
$status = mkt_evolution_status($evo);
$mascara = mkt_mascarar($evo['key']);

$rotulos = [
    'desconectado' => ['Desconectado', 'secondary', 'fa-plug-circle-xmark'],
    'conectando'   => ['Conectando / aguardando leitura', 'warning', 'fa-spinner'],
    'conectado'    => ['Conectado', 'success', 'fa-circle-check'],
    'erro'         => ['Erro de conexao', 'danger', 'fa-triangle-exclamation'],
];
$rotulo = $rotulos[$status['estado']] ?? $rotulos['desconectado'];

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
<style>
.evo-qr-box{display:flex;align-items:center;justify-content:center;min-height:260px;border:1px dashed var(--app-card-border);border-radius:12px;background:var(--slate-50);padding:18px;text-align:center}
.evo-qr-box img{width:240px;height:240px;object-fit:contain;border-radius:10px;background:#fff;padding:8px;box-shadow:var(--shadow-md)}
.evo-status-dot{width:10px;height:10px;border-radius:50%;display:inline-block;margin-right:7px;background:currentColor}
.evo-badge{display:inline-flex;align-items:center;font-size:.78rem;padding:.38rem .7rem;border-radius:999px;font-weight:600}
.evo-badge.desconectado{background:#eef1f4;color:#5b6472}
.evo-badge.conectando{background:#fff6e5;color:#a86a00}
.evo-badge.conectado{background:#e8f7ef;color:#128a53}
.evo-badge.erro{background:#fdecec;color:#c62828}
.evo-passos{font-size:.82rem;color:var(--app-text-muted);line-height:1.7;margin:0;padding-left:18px}
.spin{animation:evo-spin 1s linear infinite}
@keyframes evo-spin{to{transform:rotate(360deg)}}
</style>

<div class="main-content">
    <div class="topbar">
        <div>
            <button class="btn d-md-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <h5><i class="fab fa-whatsapp me-1" style="color:#25D366;"></i> API Evolution</h5>
        </div>
        <div class="dropdown d-none d-md-block">
            <span class="text-muted"><i class="fas fa-crown me-1"></i>Super Admin</span>
        </div>
    </div>

    <div class="content-area fade-in">
        <div id="evoAlerta" class="alert d-none alert-dismissible fade show py-2" role="alert"></div>

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="form-card h-100">
                    <h6 class="mb-3"><i class="fas fa-gear me-2"></i>Configuracoes da API</h6>
                    <form id="evoForm" autocomplete="off">
                        <div class="mb-3">
                            <label class="form-label">URL da API Evolution *</label>
                            <input type="url" class="form-control" name="url" placeholder="https://evolution.suaempresa.com" value="<?= htmlspecialchars($evo['url']) ?>" required>
                            <small class="text-muted">Sem barra no final. Ex.: https://evolution.exemplo.com</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">API Key *</label>
                            <input type="password" class="form-control" name="api_key" placeholder="<?= $mascara !== '' ? 'Salva: ' . htmlspecialchars($mascara) . ' (deixe em branco para manter)' : 'Informe a API Key' ?>" autocomplete="new-password">
                            <small class="text-muted">A chave e cifrada no banco e nunca e exibida por completo.
                                <?php if ($mascara !== ''): ?><span class="text-success ms-1"><i class="fas fa-lock me-1"></i>Chave salva: <code><?= htmlspecialchars($mascara) ?></code></span><?php endif; ?>
                            </small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nome da instancia *</label>
                            <input type="text" class="form-control" name="instance" placeholder="minha-instancia" value="<?= htmlspecialchars($evo['instance']) ?>" required pattern="[A-Za-z0-9_-]{1,100}">
                            <small class="text-muted">Identificador da instancia na Evolution (letras, numeros, hifen ou underline).</small>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary" id="btnSalvarEvo"><i class="fas fa-floppy-disk me-1"></i>Salvar configuracoes</button>
                            <button type="button" class="btn btn-outline-secondary" id="btnTestarEvo"><i class="fas fa-satellite-dish me-1"></i>Testar conexao</button>
                        </div>
                    </form>

                    <hr class="my-4">
                    <h6 class="mb-2"><i class="fas fa-circle-info me-2"></i>Endpoints utilizados</h6>
                    <ul class="evo-passos">
                        <li><code>GET /instance/fetchInstances</code></li>
                        <li><code>POST /instance/create</code></li>
                        <li><code>GET /instance/connect/{instance}</code></li>
                        <li><code>GET /instance/connectionState/{instance}</code></li>
                        <li><code>DELETE /instance/logout/{instance}</code></li>
                        <li><code>POST /message/sendText/{instance}</code></li>
                    </ul>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="form-card h-100">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <h6 class="mb-0"><i class="fas fa-qrcode me-2"></i>Conexao do WhatsApp</h6>
                        <span class="evo-badge <?= htmlspecialchars($status['estado']) ?>" id="evoBadge">
                            <span class="evo-status-dot"></span><span id="evoBadgeTexto"><?= htmlspecialchars($rotulo[0]) ?></span>
                        </span>
                    </div>

                    <div class="evo-qr-box" id="evoQrBox">
                        <?php if ($status['estado'] === 'conectado'): ?>
                            <div class="text-center">
                                <i class="fas fa-circle-check text-success" style="font-size:3.4rem;"></i>
                                <h6 class="mt-3 mb-1">WhatsApp conectado</h6>
                                <p class="text-muted small mb-0">A instancia esta pronta para os disparos do Marketing.</p>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted">
                                <i class="fas fa-qrcode mb-2" style="font-size:3rem;opacity:.35;"></i>
                                <p class="mb-1">Clique em <strong>Conectar WhatsApp</strong> para gerar o QR Code.</p>
                                <small>O QR Code e atualizado automaticamente quando necessario.</small>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <button type="button" class="btn btn-success" id="btnConectarEvo"><i class="fab fa-whatsapp me-1"></i>Conectar WhatsApp</button>
                        <button type="button" class="btn btn-outline-danger" id="btnDesconectarEvo"><i class="fas fa-power-off me-1"></i>Desconectar WhatsApp</button>
                        <button type="button" class="btn btn-outline-secondary" id="btnAtualizarEvo"><i class="fas fa-rotate me-1"></i>Atualizar status</button>
                    </div>

                    <div class="mt-4 small text-muted" id="evoDetalhes">
                        <?php if ($status['telefone'] !== ''): ?>
                            <div><i class="fas fa-phone me-1"></i>Numero identificado: <strong><?= htmlspecialchars(mkt_formatar_whatsapp($status['telefone'])) ?></strong><?= $status['perfil'] !== '' ? ' - ' . htmlspecialchars($status['perfil']) : '' ?></div>
                        <?php endif; ?>
                        <?php if ($status['mensagem'] !== ''): ?>
                            <div class="mt-1"><?= htmlspecialchars($status['mensagem']) ?></div>
                        <?php endif; ?>
                        <div class="mt-2">Atualizacao automatica a cada 6 segundos. O QR e renovado a cada 30 segundos enquanto a conexao nao for concluida.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var API = 'api/evolution.php';
    var CSRF = <?= json_encode($csrf) ?>;
    var alerta = document.getElementById('evoAlerta');
    var form = document.getElementById('evoForm');
    var badge = document.getElementById('evoBadge');
    var badgeTexto = document.getElementById('evoBadgeTexto');
    var qrBox = document.getElementById('evoQrBox');
    var detalhes = document.getElementById('evoDetalhes');
    var conectado = <?= $status['estado'] === 'conectado' ? 'true' : 'false' ?>;
    var intervaloQr = null;

    function mostrar(tipo, msg) {
        alerta.className = 'alert alert-' + tipo + ' alert-dismissible fade show py-2';
        alerta.innerHTML = msg + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        clearTimeout(mostrar._t);
        mostrar._t = setTimeout(function () { alerta.classList.add('d-none'); }, 9000);
    }

    function carregando(botao, ativo) {
        if (!botao) return;
        if (ativo) {
            botao.dataset.html = botao.innerHTML;
            botao.disabled = true;
            botao.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Aguarde...';
        } else {
            botao.disabled = false;
            if (botao.dataset.html) botao.innerHTML = botao.dataset.html;
        }
    }

    function chamar(acao, extras, botao) {
        var fd = new FormData();
        fd.append('acao', acao);
        fd.append('csrf', CSRF);
        Object.keys(extras || {}).forEach(function (k) { fd.append(k, extras[k]); });
        carregando(botao, true);
        return fetch(API, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json().catch(function () { return { ok: false, mensagem: 'Resposta invalida do servidor.' }; }); })
            .then(function (d) {
                if (!d.ok) { mostrar('danger', d.mensagem || 'Falha na operacao.'); return d; }
                return d;
            })
            .catch(function (e) { mostrar('danger', 'Erro de rede: ' + e.message); return { ok: false }; })
            .finally(function () { carregando(botao, false); });
    }

    function renderStatus(st) {
        if (!st) return;
        conectado = st.estado === 'conectado';
        var textos = { desconectado: 'Desconectado', conectando: 'Conectando / aguardando leitura', conectado: 'Conectado', erro: 'Erro de conexao' };
        badge.className = 'evo-badge ' + st.estado;
        badgeTexto.textContent = textos[st.estado] || st.estado;
        var extra = '';
        if (st.telefone) extra += '<div><i class="fas fa-phone me-1"></i>Numero identificado: <strong>' + (st.telefone_formatado || st.telefone) + '</strong>' + (st.perfil ? ' - ' + st.perfil : '') + '</div>';
        if (st.mensagem) extra += '<div class="mt-1">' + st.mensagem + '</div>';
        extra += '<div class="mt-2">Atualizacao automatica a cada 6 segundos.</div>';
        detalhes.innerHTML = extra;
        if (conectado) {
            qrBox.innerHTML = '<div class="text-center"><i class="fas fa-circle-check text-success" style="font-size:3.4rem;"></i><h6 class="mt-3 mb-1">WhatsApp conectado</h6><p class="text-muted small mb-0">A instancia esta pronta para os disparos do Marketing.</p></div>';
            if (intervaloQr) { clearInterval(intervaloQr); intervaloQr = null; }
        }
    }

    function renderQr(base64) {
        qrBox.innerHTML = '<div class="text-center"><img src="data:image/png;base64,' + base64 + '" alt="QR Code WhatsApp"><p class="text-muted small mt-2 mb-0">Abra o WhatsApp > Dispositivos conectados > Conectar dispositivo e leia o codigo.</p></div>';
    }

    function status() {
        return chamar('status', {}, null).then(function (d) {
            if (d.ok) renderStatus(d.status);
            return d;
        });
    }

    function qr() {
        var btn = document.getElementById('btnConectarEvo');
        return chamar('conectar', {}, btn).then(function (d) {
            if (d.ok) {
                renderStatus(d.status);
                if (d.conectado) {
                    mostrar('success', 'WhatsApp ja estava conectado.');
                } else if (d.base64) {
                    renderQr(d.base64);
                    if (!intervaloQr) {
                        intervaloQr = setInterval(function () {
                            if (document.hidden || conectado) return;
                            chamar('conectar', {}, null).then(function (r) {
                                if (r.ok) {
                                    renderStatus(r.status);
                                    if (r.base64) renderQr(r.base64);
                                }
                            });
                        }, 30000);
                    }
                }
            }
            return d;
        });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = document.getElementById('btnSalvarEvo');
        var fd = new FormData(form);
        var extras = {
            url: fd.get('url') || '',
            api_key: fd.get('api_key') || '',
            instance: fd.get('instance') || ''
        };
        chamar('salvar', extras, btn).then(function (d) {
            if (d.ok) {
                mostrar('success', d.mensagem);
                form.querySelector('[name="api_key"]').value = '';
                if (d.mascara) form.querySelector('[name="api_key"]').placeholder = 'Salva: ' + d.mascara + ' (deixe em branco para manter)';
            }
        });
    });

    document.getElementById('btnTestarEvo').addEventListener('click', function () {
        chamar('testar', {}, this).then(function (d) {
            if (d.ok) { mostrar('success', d.mensagem); renderStatus(d.status); }
        });
    });

    document.getElementById('btnConectarEvo').addEventListener('click', function () { qr(); });

    document.getElementById('btnDesconectarEvo').addEventListener('click', function () {
        if (!confirm('Desconectar o WhatsApp desta instancia?')) return;
        chamar('desconectar', {}, this).then(function (d) {
            if (d.ok) {
                mostrar('success', d.mensagem);
                renderStatus(d.status);
                qrBox.innerHTML = '<div class="text-center text-muted"><i class="fas fa-plug-circle-xmark mb-2" style="font-size:3rem;opacity:.35;"></i><p class="mb-1">WhatsApp desconectado.</p><small>Clique em Conectar WhatsApp para gerar um novo QR Code.</small></div>';
            }
        });
    });

    document.getElementById('btnAtualizarEvo').addEventListener('click', function () {
        chamar('status', {}, this).then(function (d) { if (d.ok) mostrar('success', 'Status atualizado.'); });
    });

    setInterval(function () { if (!document.hidden) status(); }, 6000);
})();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
