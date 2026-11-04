<?php
// =====================================================
// GERENCIADOR CENTRALIZADO DE UPLOADS PERMANENTES
// Armazena e protege arquivos enviados em /uploads/
// =====================================================

if (!defined('UPLOADS_BASE_DIR')) {
    define('UPLOADS_BASE_DIR', dirname(__DIR__) . '/uploads');
}

/**
 * Retorna o caminho físico no disco para uma categoria de upload.
 * Cria o diretório com permissões corretas se ainda não existir.
 */
function getUploadDir($categoria = 'geral') {
    $categoria = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$categoria);
    if ($categoria === '') {
        $categoria = 'geral';
    }
    $dir = UPLOADS_BASE_DIR . '/' . $categoria;
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

/**
 * Retorna a URL pública para um arquivo armazenado em uploads.
 */
function getUploadUrl($categoria, $filename) {
    $categoria = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$categoria);
    $filename = basename($filename);
    $base = defined('APP_BASE') ? APP_BASE : '/cobranca';
    return rtrim($base, '/') . '/uploads/' . $categoria . '/' . $filename;
}

/**
 * Resolve o caminho físico absoluto no disco a partir de uma URL de upload ou asset.
 */
function uploadPathFromUrl($url) {
    $url = trim((string)$url);
    if ($url === '') return '';
    $rel = preg_replace('#^/cobranca#', '', $url);
    $path = dirname(__DIR__) . '/' . ltrim($rel, '/\\');
    $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    return $path;
}

/**
 * Salva um arquivo enviado via $_FILES com validação de tipo, tamanho e nome exclusivo.
 * Garante que arquivos existentes nunca sejam sobrescritos acidentalmente.
 *
 * @param array $file O item de $_FILES (ex: $_FILES['logo'])
 * @param string $categoria 'logos', 'banners', 'avatars', 'comprovantes', etc.
 * @param string $prefixo Prefixo para identificação do arquivo
 * @param array $extPermitidas Extensões válidas permitidas
 * @param int $maxBytes Tamanho máximo em bytes (padrão: 5MB)
 * @return array ['sucesso' => bool, 'url' => string, 'path' => string, 'nome' => string, 'erro' => string]
 */
function salvarUploadSeguro($file, $categoria = 'geral', $prefixo = 'file_', $extPermitidas = ['jpg','jpeg','png','gif','webp','svg'], $maxBytes = 5242880) {
    if (!isset($file) || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $codigoErro = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        $mensagens = [
            UPLOAD_ERR_INI_SIZE   => 'O arquivo excede o limite do servidor (upload_max_filesize).',
            UPLOAD_ERR_FORM_SIZE  => 'O arquivo excede o limite do formulário.',
            UPLOAD_ERR_PARTIAL    => 'O envio foi interrompido e o arquivo ficou incompleto.',
            UPLOAD_ERR_NO_FILE    => 'Nenhum arquivo foi selecionado.',
            UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária ausente no servidor.',
            UPLOAD_ERR_CANT_WRITE => 'Falha de permissão ao salvar no disco do servidor.',
        ];
        return ['sucesso' => false, 'erro' => $mensagens[$codigoErro] ?? 'Falha ao receber o arquivo (código: ' . $codigoErro . ').'];
    }

    if ($file['size'] > $maxBytes) {
        $limiteMb = round($maxBytes / 1048576, 1);
        return ['sucesso' => false, 'erro' => "O arquivo ultrapassa o tamanho máximo de {$limiteMb}MB."];
    }

    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    
    // Proteção estrita contra extensões executáveis
    $proibidos = ['php','phtml','php3','php4','php5','php7','php8','phps','cgi','pl','py','sh','bash','exe','bat','cmd','com','js','html','htm'];
    if (in_array($ext, $proibidos, true)) {
        return ['sucesso' => false, 'erro' => 'Extensão de arquivo não permitida por motivos de segurança.'];
    }

    if (!in_array($ext, $extPermitidas, true)) {
        return ['sucesso' => false, 'erro' => 'Formato não permitido. Formatos aceitos: ' . strtoupper(implode(', ', $extPermitidas))];
    }

    $destDir = getUploadDir($categoria);
    if (!is_writable($destDir)) {
        @chmod($destDir, 0775);
    }

    // Nome exclusivo (timestamp + random_bytes) para evitar colisões e sobrescritas
    $sufixoUnico = bin2hex(random_bytes(6));
    $prefixoLimpo = preg_replace('/[^a-zA-Z0-9_\-]/', '', $prefixo);
    if ($prefixoLimpo === '') $prefixoLimpo = 'upload';
    
    $nomeArquivo = $prefixoLimpo . '_' . date('Ymd_His') . '_' . $sufixoUnico . '.' . $ext;
    $destinoPath = $destDir . DIRECTORY_SEPARATOR . $nomeArquivo;

    if (!move_uploaded_file($file['tmp_name'], $destinoPath)) {
        return ['sucesso' => false, 'erro' => 'Não foi possível mover o arquivo para o diretório de destino. Verifique permissões.'];
    }

    @chmod($destinoPath, 0664);

    return [
        'sucesso' => true,
        'nome'    => $nomeArquivo,
        'path'    => $destinoPath,
        'url'     => getUploadUrl($categoria, $nomeArquivo),
        'erro'    => ''
    ];
}
