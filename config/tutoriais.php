<?php
// =====================================================
// TUTORIAIS EM VÍDEO POR GATEWAY
// Configurados globalmente pelo Super Admin (admin_id NULL)
// e exibidos no painel admin (aba API de Pagamento).
// O clique no vídeo abre um modal com a reprodução.
// =====================================================

require_once __DIR__ . '/settings.php';

function tutorialChave($gateway) {
    return 'tutorial_' . $gateway;
}

function getTutorialUrl($gateway) {
    return getConfigGlobal(tutorialChave($gateway), '');
}

// Converte URLs de YouTube/Vimeo para URL de embed. Retorna '' se não for reconhecida.
function tutorialEmbedUrl($url) {
    $url = trim((string)$url);
    if ($url === '') return '';

    if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{6,20})~', $url, $m)) {
        return 'https://www.youtube.com/embed/' . $m[1] . '?rel=0';
    }
    if (preg_match('~vimeo\.com/(?:video/|channels/[^/]+/)?(\d+)~', $url, $m)) {
        return 'https://player.vimeo.com/video/' . $m[1];
    }
    return '';
}

function tutorialEhVideoDireto($url) {
    $path = (string)parse_url($url, PHP_URL_PATH);
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return in_array($ext, ['mp4', 'webm', 'ogg', 'ogv', 'm4v', 'mov']);
}

// Extrai o ID de um vídeo do YouTube para gerar a thumbnail do cover.
function tutorialYouTubeId($url) {
    $url = trim((string)$url);
    if ($url === '') return '';
    if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{6,20})~', $url, $m)) {
        return $m[1];
    }
    return '';
}

function tutorialModalId($gateway) {
    return 'modalTutorial' . ucfirst($gateway);
}

// Cover clicável que abre o modal (thumbnail do YouTube ou placeholder com play).
function tutorialCover($gateway, $url, $titulo) {
    $modalId = tutorialModalId($gateway);
    $ytId = tutorialYouTubeId($url);

    $html = '<a href="#" class="d-block position-relative rounded-3 overflow-hidden tutorial-thumb text-center" data-bs-toggle="modal" data-bs-target="#' . $modalId . '" title="' . htmlspecialchars($titulo) . '">';
    if ($ytId !== '') {
        $html .= '<img src="https://i.ytimg.com/vi/' . $ytId . '/hqdefault.jpg" alt="' . htmlspecialchars($titulo) . '" class="w-100" style="aspect-ratio:16/9;object-fit:cover;display:block;">';
    } else {
        $html .= '<div class="w-100 d-flex align-items-center justify-content-center" style="aspect-ratio:16/9;background:linear-gradient(135deg,var(--cor-primaria),rgba(30,41,59,.85));">';
        $html .= '<i class="fas fa-play-circle" style="font-size:3.2rem;color:#fff;text-shadow:0 2px 8px rgba(0,0,0,.35);"></i>';
        $html .= '</div>';
    }
    $html .= '</a>';
    return $html;
}

// Modal com o vídeo. O src é aplicado só quando o modal abre (lazy-load).
function renderTutorialModal($gateway, $url, $titulo) {
    if ($gateway === '' || trim((string)$url) === '') return;

    $modalId = tutorialModalId($gateway);
    $embed = tutorialEmbedUrl($url);
    $isDireto = tutorialEhVideoDireto($url);
    $escUrl = htmlspecialchars($url);

    echo '<div class="modal fade" id="' . $modalId . '" tabindex="-1" aria-hidden="true">';
    echo '<div class="modal-dialog modal-lg modal-dialog-centered">';
    echo '<div class="modal-content">';
    echo '<div class="modal-header py-2">';
    echo '<h6 class="modal-title"><i class="fas fa-video me-1"></i> ' . htmlspecialchars($titulo) . '</h6>';
    echo '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>';
    echo '</div>';
    echo '<div class="modal-body p-0">';

    if ($embed !== '') {
        echo '<div class="ratio ratio-16x9">';
        echo '<iframe id="' . $modalId . 'Frame" data-src="' . htmlspecialchars($embed)
            . '" title="' . htmlspecialchars($titulo)
            . '" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>';
        echo '</div>';
    } elseif ($isDireto) {
        echo '<video id="' . $modalId . 'Video" controls playsinline class="w-100" style="max-height:62vh;"></video>';
    } else {
        echo '<div class="p-5 text-center">';
        echo '<p class="text-muted mb-3">Este tutorial é um link externo:</p>';
        echo '<a href="' . $escUrl . '" target="_blank" rel="noopener" class="btn btn-primary"><i class="fas fa-external-link-alt me-1"></i> Abrir tutorial</a>';
        echo '</div>';
    }

    echo '</div>';
    echo '</div></div></div>';

    tutorialModalScriptOnce();
}

// Script global (uma única vez por página) que ativa o lazy-load dos vídeos nos modais.
function tutorialModalScriptOnce() {
    static $emitido = false;
    if ($emitido) return;
    $emitido = true;
    echo '<script>
(function(){
    function encontrarId(e){
        var root = e.target;
        if (!root || !root.id || root.id.indexOf("modalTutorial") !== 0) return "";
        return root.id;
    }
    document.addEventListener("shown.bs.modal", function(e){
        var id = encontrarId(e);
        if (!id) return;
        var f = document.getElementById(id + "Frame");
        if (f && !f.getAttribute("src")) { f.setAttribute("src", f.getAttribute("data-src")); }
        var v = document.getElementById(id + "Video");
        if (v && !v.getAttribute("src")) {
            v.setAttribute("src", v.getAttribute("data-src"));
            v.load();
            var p = v.play(); if (p && p.catch) p.catch(function(){});
        }
    });
    document.addEventListener("hidden.bs.modal", function(e){
        var id = encontrarId(e);
        if (!id) return;
        var f = document.getElementById(id + "Frame");
        if (f) f.removeAttribute("src");
        var v = document.getElementById(id + "Video");
        if (v) { v.pause(); v.removeAttribute("src"); }
    });
})();
</script>';
}

// Renderiza o bloco completo (cover clicável + modal) na lateral das abas de API de Pagamento.
function renderTutorialVideo($gateway, $titulo = 'Tutorial em vídeo') {
    $url = getTutorialUrl($gateway);
    if (trim((string)$url) === '') return;

    echo '<div class="form-card mt-3">';
    echo '<h6 class="mb-2"><i class="fas fa-video me-2"></i>' . htmlspecialchars($titulo) . '</h6>';
    echo tutorialCover($gateway, $url, $titulo);
    echo '<small class="text-muted d-block mt-2 text-center">Clique para assistir</small>';
    echo '</div>';

    renderTutorialModal($gateway, $url, $titulo);
}