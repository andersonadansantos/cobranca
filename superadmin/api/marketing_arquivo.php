<?php
// =====================================================
// ENDPOINT AUTENTICADO - IMAGENS DOS TEMPLATES (MARKETING)
// =====================================================
// Os arquivos ficam em config/marketing_uploads (bloqueado por .htaccess).
// Somente um Super Admin autenticado pode visualizar/baixar a imagem.
require_once __DIR__ . '/../auth.php';
requireSuper();
require_once __DIR__ . '/../../config/marketing_helpers.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Imagem nao encontrada.';
    exit;
}

$row = mkt_arquivo_obter($id);
if (!$row) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Imagem nao encontrada.';
    exit;
}

$caminho = mkt_arquivo_caminho($row['arquivo'] ?? '');
if ($caminho === '') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Arquivo da imagem indisponivel.';
    exit;
}

$tipos = mkt_imagem_tipos();
$mime = (string) ($row['mime'] ?? '');
if (!isset($tipos[$mime])) {
    $mime = 'application/octet-stream';
}

$nome = (string) ($row['nome_original'] ?? 'imagem');
$nome = preg_replace('/[\x00-\x1F\x7F"\\\\]+/', '', $nome);
$nome = basename(str_replace('\\', '/', $nome));
if ($nome === '') $nome = 'imagem';
$ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nome);
if ($ascii !== false && $ascii !== '') $nome = $ascii;
$nome = preg_replace('/[^A-Za-z0-9._-]+/', '_', $nome);
if ($nome === '' || $nome === '.') $nome = 'imagem';

$tamanho = (int) (filesize($caminho) ?: 0);
$mtime = (int) (filemtime($caminho) ?: time());
$etag = '"' . md5($row['arquivo'] . '|' . $mtime . '|' . $tamanho) . '"';

header('Content-Type: ' . $mime);
header('Content-Length: ' . $tamanho);
header('Content-Disposition: ' . (!empty($_GET['download']) ? 'attachment' : 'inline') . '; filename="' . $nome . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=86400');
header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
    exit;
}

$ifNoneMatch = trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
if ($ifNoneMatch !== '' && $ifNoneMatch === $etag) {
    http_response_code(304);
    exit;
}

if (ob_get_level()) @ob_end_clean();
readfile($caminho);
exit;
