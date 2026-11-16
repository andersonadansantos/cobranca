<?php
// =====================================================
// MODULO MARKETING - ENVIOS MANUAIS
// =====================================================
// Listas importadas (CSV/XLSX), campanhas proprias do Super Admin, limites
// diarios exclusivos do canal manual (20 WhatsApp / 150 e-mails), fila,
// processamento pelo Cron Job e logs.
//
// Regras de ouro:
// - usa SEMPRE os templates principais (WhatsApp/e-mail) da aba Marketing Disparo;
// - nao interfere na coleta automatica nem nos limites dela;
// - nunca envia para contato com opt-out ou sem base legal;
// - cada envio e registrado e nunca repetido com sucesso na mesma campanha.

if (!defined('MKT_MANUAL_MAX_WHATSAPP_DIA')) define('MKT_MANUAL_MAX_WHATSAPP_DIA', 20);
if (!defined('MKT_MANUAL_MAX_EMAIL_DIA'))    define('MKT_MANUAL_MAX_EMAIL_DIA', 150);
if (!defined('MKT_MANUAL_MAX_TENTATIVAS'))   define('MKT_MANUAL_MAX_TENTATIVAS', 3);
if (!defined('MKT_IMPORT_MAX_BYTES'))        define('MKT_IMPORT_MAX_BYTES', 10 * 1024 * 1024);
if (!defined('MKT_IMPORT_MAX_LINHAS'))       define('MKT_IMPORT_MAX_LINHAS', 5000);

// =====================================================
// Limites diarios (exclusivos das campanhas manuais)
// =====================================================

function mkt_manual_limites() {
    $wa = (int) mkt_config_get('mkt_manual_limite_wa', MKT_MANUAL_MAX_WHATSAPP_DIA);
    $em = (int) mkt_config_get('mkt_manual_limite_em', MKT_MANUAL_MAX_EMAIL_DIA);
    // Nunca acima do teto definido para o canal manual.
    $wa = max(1, min(MKT_MANUAL_MAX_WHATSAPP_DIA, $wa ?: MKT_MANUAL_MAX_WHATSAPP_DIA));
    $em = max(1, min(MKT_MANUAL_MAX_EMAIL_DIA, $em ?: MKT_MANUAL_MAX_EMAIL_DIA));
    return ['whatsapp' => $wa, 'email' => $em];
}

function mkt_manual_limites_salvar($wa, $em) {
    $wa = max(1, min(MKT_MANUAL_MAX_WHATSAPP_DIA, (int) $wa));
    $em = max(1, min(MKT_MANUAL_MAX_EMAIL_DIA, (int) $em));
    $ok1 = mkt_config_set('mkt_manual_limite_wa', (string) $wa);
    $ok2 = mkt_config_set('mkt_manual_limite_em', (string) $em);
    return $ok1 && $ok2;
}

// Intervalo do dia (00:00 ate 23:59:59) no fuso de Brasilia. Calculado sempre
// em PHP para que o limite diario nao dependa do fuso do servidor MySQL.
function mkt_manual_dia_intervalo() {
    $tz = new DateTimeZone('America/Sao_Paulo');
    $inicio = new DateTimeImmutable('today', $tz);
    $fim = $inicio->modify('+1 day');
    return [$inicio->format('Y-m-d H:i:s'), $fim->format('Y-m-d H:i:s')];
}

// Envios manuais bem-sucedidos hoje (por canal). O intervalo e calculado em
// PHP (America/Sao_Paulo) para nao depender do fuso do MySQL.
function mkt_manual_uso_hoje() {
    [$inicio, $fim] = mkt_manual_dia_intervalo();
    $uso = ['whatsapp' => 0, 'email' => 0];
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT canal, COUNT(*) AS total
            FROM marketing_campanha_envios
            WHERE status = 'enviado' AND enviado_em >= ? AND enviado_em < ?
            GROUP BY canal");
        $stmt->execute([$inicio, $fim]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (isset($uso[$row['canal']])) $uso[$row['canal']] = (int) $row['total'];
        }
    } catch (Throwable $e) {
        error_log('[Marketing manual] uso hoje: ' . $e->getMessage());
    }
    return $uso;
}

function mkt_manual_disponiveis() {
    $limites = mkt_manual_limites();
    $uso = mkt_manual_uso_hoje();
    return [
        'limites' => $limites,
        'uso' => $uso,
        'disponiveis' => [
            'whatsapp' => max(0, $limites['whatsapp'] - $uso['whatsapp']),
            'email' => max(0, $limites['email'] - $uso['email']),
        ],
    ];
}

// Resumo independente da fila manual por canal (pendentes, enviados hoje,
// falhas). Usado no painel para exibir os dois contadores separadamente.
function mkt_manual_filas_resumo() {
    [$inicio, $fim] = mkt_manual_dia_intervalo();
    $resumo = [
        'whatsapp' => ['pendentes' => 0, 'hoje' => 0, 'falhas' => 0],
        'email' => ['pendentes' => 0, 'hoje' => 0, 'falhas' => 0],
    ];
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT canal,
                SUM(CASE WHEN status IN ('aguardando','enviando') OR (status='falha' AND tentativas < ?) THEN 1 ELSE 0 END) pendentes,
                SUM(CASE WHEN status='enviado' AND enviado_em >= ? AND enviado_em < ? THEN 1 ELSE 0 END) hoje,
                SUM(CASE WHEN status='falha' THEN 1 ELSE 0 END) falhas
            FROM marketing_campanha_envios
            GROUP BY canal");
        $stmt->execute([MKT_MANUAL_MAX_TENTATIVAS, $inicio, $fim]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $canal = (string) $row['canal'];
            if (!isset($resumo[$canal])) continue;
            $resumo[$canal] = [
                'pendentes' => (int) $row['pendentes'],
                'hoje' => (int) $row['hoje'],
                'falhas' => (int) $row['falhas'],
            ];
        }
    } catch (Throwable $e) {
        error_log('[Marketing manual] resumo filas: ' . $e->getMessage());
    }
    return $resumo;
}

// =====================================================
// Listas de contatos
// =====================================================

function mkt_lista_obter($id) {
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT * FROM marketing_listas WHERE id = ? LIMIT 1");
        $stmt->execute([(int) $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function mkt_lista_por_nome($nome) {
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT * FROM marketing_listas WHERE nome = ? LIMIT 1");
        $stmt->execute([mb_substr(trim((string) $nome), 0, 160, 'UTF-8')]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function mkt_lista_criar($nome, $descricao = '') {
    $nome = trim((string) $nome);
    if (mb_strlen($nome, 'UTF-8') < 2) {
        return ['ok' => false, 'mensagem' => 'Informe um nome para a lista (minimo 2 caracteres).', 'lista' => null];
    }
    $nome = mb_substr($nome, 0, 160, 'UTF-8');
    $existente = mkt_lista_por_nome($nome);
    if ($existente) return ['ok' => true, 'mensagem' => 'Lista ja existente reaproveitada.', 'lista' => $existente];
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("INSERT INTO marketing_listas (nome, descricao) VALUES (?, ?)");
        $stmt->execute([$nome, mb_substr(trim((string) $descricao), 0, 255, 'UTF-8')]);
        return ['ok' => true, 'mensagem' => 'Lista criada.', 'lista' => mkt_lista_obter((int) $pdo->lastInsertId())];
    } catch (Throwable $e) {
        if ((string) $e->getCode() === '23000') {
            $existente = mkt_lista_por_nome($nome);
            if ($existente) return ['ok' => true, 'mensagem' => 'Lista ja existente reaproveitada.', 'lista' => $existente];
        }
        error_log('[Marketing manual] criar lista: ' . $e->getMessage());
        return ['ok' => false, 'mensagem' => 'Nao foi possivel criar a lista.', 'lista' => null];
    }
}

// Obtem a lista por ID ou cria/reaproveita pelo nome informado.
function mkt_lista_resolver($listaId = 0, $listaNome = '') {
    $listaId = (int) $listaId;
    if ($listaId > 0) {
        $lista = mkt_lista_obter($listaId);
        if ($lista) return ['ok' => true, 'lista' => $lista, 'mensagem' => ''];
        return ['ok' => false, 'lista' => null, 'mensagem' => 'Lista nao encontrada.'];
    }
    $listaNome = trim((string) $listaNome);
    if ($listaNome === '') {
        return ['ok' => false, 'lista' => null, 'mensagem' => 'Selecione uma lista ou informe o nome de uma nova.'];
    }
    $r = mkt_lista_criar($listaNome);
    return ['ok' => $r['ok'], 'lista' => $r['lista'], 'mensagem' => $r['mensagem']];
}

function mkt_listas_listar() {
    try {
        $pdo = getConnection();
        $sql = "SELECT l.*, (SELECT COUNT(*) FROM marketing_lista_contatos lc WHERE lc.lista_id = l.id) AS total_contatos,
                (SELECT COUNT(*) FROM marketing_campanhas cp WHERE cp.lista_id = l.id) AS total_campanhas
            FROM marketing_listas l ORDER BY l.id DESC";
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function mkt_lista_excluir($id) {
    $lista = mkt_lista_obter($id);
    if (!$lista) return ['ok' => false, 'mensagem' => 'Lista nao encontrada.'];
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM marketing_campanhas WHERE lista_id = ?");
        $stmt->execute([(int) $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            return ['ok' => false, 'mensagem' => 'Esta lista possui campanhas vinculadas. Exclua as campanhas antes.'];
        }
        $pdo->prepare("DELETE FROM marketing_listas WHERE id = ?")->execute([(int) $id]);
        return ['ok' => true, 'mensagem' => 'Lista excluida.'];
    } catch (Throwable $e) {
        error_log('[Marketing manual] excluir lista: ' . $e->getMessage());
        return ['ok' => false, 'mensagem' => 'Nao foi possivel excluir a lista.'];
    }
}

function mkt_lista_vincular($listaId, $contatoId) {
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("INSERT IGNORE INTO marketing_lista_contatos (lista_id, contato_id) VALUES (?, ?)");
        $stmt->execute([(int) $listaId, (int) $contatoId]);
        return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function mkt_lista_contatos_ids($listaId) {
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT contato_id FROM marketing_lista_contatos WHERE lista_id = ?");
        $stmt->execute([(int) $listaId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $e) {
        return [];
    }
}

// =====================================================
// Diretorio e arquivos de importacao
// =====================================================

function mkt_import_dir() {
    $dir = __DIR__ . '/marketing_imports';
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    $ht = $dir . '/.htaccess';
    if (is_dir($dir) && !is_file($ht)) {
        @file_put_contents($ht, "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n", LOCK_EX);
    }
    // Limpeza de arquivos temporarios com mais de 24h.
    if (is_dir($dir)) {
        foreach ((array) @glob($dir . '/imp_*') as $antigo) {
            $mtime = @filemtime($antigo);
            if ($mtime && $mtime < time() - 86400) @unlink($antigo);
        }
    }
    return $dir;
}

function mkt_import_caminho($token) {
    $token = (string) $token;
    if (!preg_match('/^imp_[a-f0-9]{32}\.(csv|xlsx)$/', $token)) return '';
    $caminho = mkt_import_dir() . DIRECTORY_SEPARATOR . $token;
    return is_file($caminho) ? $caminho : '';
}

function mkt_import_salvar_arquivo($file) {
    if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $codigo = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        $motivos = [
            UPLOAD_ERR_INI_SIZE   => 'O arquivo excede o limite do servidor.',
            UPLOAD_ERR_FORM_SIZE  => 'O arquivo excede o limite permitido.',
            UPLOAD_ERR_PARTIAL    => 'O envio do arquivo foi interrompido. Tente novamente.',
            UPLOAD_ERR_NO_FILE    => 'Selecione um arquivo CSV ou XLSX.',
            UPLOAD_ERR_NO_TMP_DIR => 'Diretorio temporario do servidor indisponivel.',
            UPLOAD_ERR_CANT_WRITE => 'O servidor nao conseguiu gravar o arquivo.',
            UPLOAD_ERR_EXTENSION  => 'O envio do arquivo foi bloqueado pelo servidor.',
        ];
        return ['ok' => false, 'mensagem' => $motivos[$codigo] ?? 'Falha no envio do arquivo.', 'token' => ''];
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_file($tmp)) {
        return ['ok' => false, 'mensagem' => 'Arquivo temporario nao encontrado.', 'token' => ''];
    }
    $tamanho = (int) (@filesize($tmp) ?: 0);
    if ($tamanho <= 0) return ['ok' => false, 'mensagem' => 'O arquivo esta vazio.', 'token' => ''];
    if ($tamanho > MKT_IMPORT_MAX_BYTES) return ['ok' => false, 'mensagem' => 'O arquivo excede o limite de 10 MB.', 'token' => ''];

    $nome = (string) ($file['name'] ?? '');
    $ext = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'xlsx'], true)) {
        return ['ok' => false, 'mensagem' => 'Formato invalido. Envie um arquivo .csv ou .xlsx.', 'token' => ''];
    }

    // Confere a assinatura real do arquivo (nao confia apenas na extensao).
    $assinatura = (string) @file_get_contents($tmp, false, null, 0, 4);
    if ($ext === 'xlsx' && substr($assinatura, 0, 2) !== 'PK') {
        return ['ok' => false, 'mensagem' => 'O arquivo nao e um XLSX valido.', 'token' => ''];
    }
    if ($ext === 'csv' && strpos($assinatura, "\x00") !== false) {
        return ['ok' => false, 'mensagem' => 'O arquivo nao parece ser um CSV de texto.', 'token' => ''];
    }

    $dir = mkt_import_dir();
    if (!is_dir($dir) || !is_writable($dir)) {
        return ['ok' => false, 'mensagem' => 'Diretorio de importacao sem permissao de escrita.', 'token' => ''];
    }
    $token = 'imp_' . bin2hex(random_bytes(16)) . '.' . $ext;
    $destino = $dir . DIRECTORY_SEPARATOR . $token;
    $movido = is_uploaded_file($tmp) ? @move_uploaded_file($tmp, $destino) : @rename($tmp, $destino);
    if (!$movido || !is_file($destino)) {
        return ['ok' => false, 'mensagem' => 'Nao foi possivel gravar o arquivo no servidor.', 'token' => ''];
    }
    @chmod($destino, 0640);
    return ['ok' => true, 'mensagem' => 'Arquivo recebido.', 'token' => $token, 'tamanho' => $tamanho];
}

function mkt_import_excluir($token) {
    $caminho = mkt_import_caminho($token);
    if ($caminho !== '' && is_file($caminho)) return @unlink($caminho);
    return false;
}

// =====================================================
// Leitura de CSV e XLSX
// =====================================================

function mkt_import_ler($caminho) {
    $ext = strtolower(pathinfo((string) $caminho, PATHINFO_EXTENSION));
    if ($ext === 'csv') return mkt_import_ler_csv($caminho);
    if ($ext === 'xlsx') return mkt_import_ler_xlsx($caminho);
    return ['ok' => false, 'erro' => 'Formato nao suportado.', 'linhas' => []];
}

function mkt_import_ler_csv($caminho) {
    $conteudo = @file_get_contents($caminho);
    if ($conteudo === false || $conteudo === '') {
        return ['ok' => false, 'erro' => 'Nao foi possivel ler o CSV.', 'linhas' => []];
    }
    if (substr($conteudo, 0, 3) === "\xEF\xBB\xBF") $conteudo = substr($conteudo, 3);
    if (!mb_check_encoding($conteudo, 'UTF-8')) {
        $conteudo = mb_convert_encoding($conteudo, 'UTF-8', 'Windows-1252');
    }
    $primeira = strtok($conteudo, "\r\n");
    $delim = ',';
    $melhor = -1;
    foreach ([';', ',', "\t"] as $candidato) {
        $qtd = substr_count((string) $primeira, $candidato);
        if ($qtd > $melhor) { $melhor = $qtd; $delim = $candidato; }
    }

    $fh = fopen('php://temp', 'r+');
    fwrite($fh, $conteudo);
    rewind($fh);
    $linhas = [];
    $total = 0;
    while (($row = fgetcsv($fh, 0, $delim, '"', '')) !== false) {
        if ($row === [null] || $row === false) continue;
        $linhas[] = array_map(function ($v) { return trim((string) $v); }, $row);
        $total++;
        if ($total > MKT_IMPORT_MAX_LINHAS + 1) break;
    }
    fclose($fh);
    return ['ok' => true, 'erro' => '', 'linhas' => $linhas];
}

function mkt_import_ler_xlsx($caminho) {
    if (!class_exists('ZipArchive')) {
        return ['ok' => false, 'erro' => 'A extensao ZIP do PHP nao esta disponivel para ler XLSX.', 'linhas' => []];
    }
    $zip = new ZipArchive();
    if ($zip->open($caminho) !== true) {
        return ['ok' => false, 'erro' => 'Nao foi possivel abrir o arquivo XLSX.', 'linhas' => []];
    }

    // Strings compartilhadas.
    $compartilhadas = [];
    $xmlSst = $zip->getFromName('xl/sharedStrings.xml');
    if ($xmlSst !== false && $xmlSst !== '') {
        $sst = @simplexml_load_string($xmlSst, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);
        if ($sst !== false) {
            foreach ($sst->si as $si) {
                $texto = '';
                $tags = $si->xpath('.//*[local-name()="t"]');
                if (is_array($tags)) {
                    foreach ($tags as $t) $texto .= (string) $t;
                }
                $compartilhadas[] = $texto;
            }
        }
    }

    // Primeira planilha disponivel.
    $planilha = '';
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $nome = (string) $zip->getNameIndex($i);
        if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $nome)) { $planilha = $nome; break; }
    }
    if ($planilha === '') {
        $zip->close();
        return ['ok' => false, 'erro' => 'Nenhuma planilha encontrada no XLSX.', 'linhas' => []];
    }
    $xmlSheet = $zip->getFromName($planilha);
    $zip->close();
    if ($xmlSheet === false || $xmlSheet === '') {
        return ['ok' => false, 'erro' => 'Nao foi possivel ler a planilha do XLSX.', 'linhas' => []];
    }
    $sheet = @simplexml_load_string($xmlSheet, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);
    if ($sheet === false || !isset($sheet->sheetData)) {
        return ['ok' => false, 'erro' => 'Planilha XLSX invalida.', 'linhas' => []];
    }

    $linhas = [];
    $total = 0;
    foreach ($sheet->sheetData->row as $row) {
        $celulas = [];
        $maior = -1;
        foreach ($row->c as $c) {
            $ref = (string) ($c['r'] ?? '');
            $indice = 0;
            if (preg_match('/^([A-Z]+)\d+$/', $ref, $m)) {
                $indice = 0;
                foreach (str_split($m[1]) as $letra) $indice = $indice * 26 + (ord($letra) - 64);
                $indice -= 1;
            }
            $tipo = (string) ($c['t'] ?? '');
            $valor = '';
            if ($tipo === 's') {
                $idx = (int) ($c->v ?? -1);
                $valor = $compartilhadas[$idx] ?? '';
            } elseif ($tipo === 'inlineStr') {
                $valor = isset($c->is->t) ? (string) $c->is->t : '';
            } elseif ($tipo === 'b') {
                $valor = ((string) ($c->v ?? '0')) === '1' ? '1' : '0';
            } else {
                $valor = isset($c->v) ? (string) $c->v : '';
                if ($valor !== '' && is_numeric($valor)) {
                    $f = (float) $valor;
                    if (floor($f) == $f && abs($f) < 1e18) $valor = number_format($f, 0, '', '');
                }
            }
            $celulas[$indice] = trim($valor);
            if ($indice > $maior) $maior = $indice;
        }
        if ($maior < 0) { $linhas[] = []; continue; }
        $linha = [];
        for ($i = 0; $i <= $maior; $i++) $linha[] = $celulas[$i] ?? '';
        $linhas[] = $linha;
        $total++;
        if ($total > MKT_IMPORT_MAX_LINHAS + 1) break;
    }
    return ['ok' => true, 'erro' => '', 'linhas' => $linhas];
}

// =====================================================
// Mapeamento automatico das colunas
// =====================================================

function mkt_import_normalizar_texto($texto) {
    $texto = mb_strtolower(trim((string) $texto), 'UTF-8');
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
    if ($ascii !== false) $texto = $ascii;
    $texto = preg_replace('/[^a-z0-9]+/', ' ', $texto);
    return trim($texto);
}

function mkt_import_mapear_colunas(array $cabecalhos) {
    $norm = array_map('mkt_import_normalizar_texto', $cabecalhos);
    $sinonimos = [
        'empresa' => ['empresa', 'nome empresa', 'nome da empresa', 'razao social', 'razao', 'company', 'organizacao', 'negocio', 'loja'],
        'nome_contato' => ['nome contato', 'nome do contato', 'contato', 'responsavel', 'nome', 'cliente', 'person', 'contact'],
        'whatsapp' => ['whatsapp', 'whats app', 'numero whatsapp', 'numero do whatsapp', 'telefone', 'celular', 'numero', 'fone', 'phone', 'mobile', 'telemovel'],
        'email' => ['email', 'e mail', 'correio eletronico', 'mail', 'email do contato', 'e mail do contato'],
        'nicho' => ['nicho', 'segmento', 'ramo', 'ramo de atividade', 'categoria', 'area', 'setor'],
        'cidade' => ['cidade', 'municipio', 'city', 'localidade'],
        'estado' => ['estado', 'uf', 'state', 'sigla estado', 'sigla uf'],
    ];
    $mapa = [];
    $usados = [];
    foreach ($sinonimos as $campo => $lista) {
        foreach ($lista as $sin) {
            $i = array_search($sin, $norm, true);
            if ($i !== false && !in_array($i, $usados, true)) {
                $mapa[$campo] = $i;
                $usados[] = $i;
                continue 2;
            }
        }
    }
    foreach ($sinonimos as $campo => $lista) {
        if (array_key_exists($campo, $mapa)) continue;
        foreach ($norm as $i => $h) {
            if (in_array($i, $usados, true) || $h === '') continue;
            foreach ($lista as $sin) {
                if ($sin !== '' && strpos($h, $sin) !== false) {
                    $mapa[$campo] = $i;
                    $usados[] = $i;
                    continue 3;
                }
            }
        }
    }
    return $mapa;
}

function mkt_import_preparar($caminho) {
    $leitura = mkt_import_ler($caminho);
    if (!$leitura['ok']) {
        return ['ok' => false, 'erro' => $leitura['erro'], 'cabecalhos' => [], 'mapa' => [], 'linhas' => []];
    }
    $linhas = $leitura['linhas'];
    if (count($linhas) < 2) {
        return ['ok' => false, 'erro' => 'O arquivo precisa conter o cabecalho e ao menos uma linha de dados.', 'cabecalhos' => [], 'mapa' => [], 'linhas' => []];
    }
    // Primeira linha nao vazia = cabecalho.
    $indiceCabecalho = 0;
    foreach ($linhas as $i => $linha) {
        if (trim(implode('', (array) $linha)) !== '') { $indiceCabecalho = $i; break; }
    }
    $cabecalhos = array_map('strval', (array) $linhas[$indiceCabecalho]);
    $mapa = mkt_import_mapear_colunas($cabecalhos);
    if (!array_key_exists('whatsapp', $mapa) && !array_key_exists('email', $mapa)) {
        return ['ok' => false, 'erro' => 'Nao foi possivel identificar colunas de WhatsApp ou e-mail. Inclua um cabecalho como "WhatsApp" ou "E-mail".', 'cabecalhos' => $cabecalhos, 'mapa' => $mapa, 'linhas' => []];
    }
    $dados = [];
    $numero = $indiceCabecalho + 2;
    foreach ($linhas as $i => $linha) {
        if ($i <= $indiceCabecalho) continue;
        $dados[] = ['numero' => $numero, 'valores' => $linha];
        $numero++;
    }
    return ['ok' => true, 'erro' => '', 'cabecalhos' => $cabecalhos, 'mapa' => $mapa, 'linhas' => $dados];
}

function mkt_import_valor(array $valores, $indice) {
    if ($indice === null || $indice === false) return '';
    return trim((string) ($valores[$indice] ?? ''));
}

// =====================================================
// Analise, deduplicacao e importacao
// =====================================================

// Analisa as linhas e devolve relatorio completo (aprovados, existentes,
// duplicados, incompletos e invalidos). Nada e gravado aqui.
function mkt_import_analisar(array $linhas, array $mapa, $listaId = 0) {
    $resumo = [
        'total' => 0, 'aprovados' => 0, 'ja_existentes' => 0, 'rejeitados' => 0,
        'duplicados' => 0, 'incompletos' => 0, 'invalidos' => 0, 'ignorados' => 0,
    ];
    $listaId = (int) $listaId;
    $preparadas = [];

    foreach ($linhas as $item) {
        $valores = (array) ($item['valores'] ?? []);
        $numero = (int) ($item['numero'] ?? 0);
        $empresa = mb_substr(mkt_import_valor($valores, $mapa['empresa'] ?? null), 0, 200, 'UTF-8');
        $nome = mb_substr(mkt_import_valor($valores, $mapa['nome_contato'] ?? null), 0, 160, 'UTF-8');
        $waRaw = mkt_import_valor($valores, $mapa['whatsapp'] ?? null);
        $emRaw = mkt_import_valor($valores, $mapa['email'] ?? null);
        $nicho = mb_substr(mkt_import_valor($valores, $mapa['nicho'] ?? null), 0, 120, 'UTF-8');
        $cidade = mb_substr(mkt_import_valor($valores, $mapa['cidade'] ?? null), 0, 120, 'UTF-8');
        $estado = mkt_estado_valido(mkt_import_valor($valores, $mapa['estado'] ?? null));

        if ($empresa === '' && $nome === '' && $waRaw === '' && $emRaw === '' && $nicho === '' && $cidade === '' && $estado === '') {
            $resumo['ignorados']++;
            continue;
        }
        $resumo['total']++;

        if ($empresa === '') $empresa = $nome;
        $waNorm = mkt_normalizar_whatsapp($waRaw);
        $emNorm = mkt_normalizar_email($emRaw);
        $motivo = '';
        if ($empresa === '') {
            $motivo = 'Registro incompleto: informe ao menos o nome da empresa ou do contato.';
        } elseif ($waRaw !== '' && $waNorm === '') {
            $motivo = 'WhatsApp invalido: ' . $waRaw . '.';
        } elseif ($emRaw !== '' && $emNorm === '') {
            $motivo = 'E-mail invalido: ' . $emRaw . '.';
        } elseif ($waNorm === '' && $emNorm === '') {
            $motivo = 'Registro incompleto: informe WhatsApp e/ou e-mail.';
        }

        $preparadas[] = [
            'numero' => $numero,
            'empresa' => $empresa,
            'nome_contato' => $nome,
            'whatsapp_raw' => $waRaw,
            'email_raw' => $emRaw,
            'whatsapp_norm' => $waNorm,
            'email_norm' => $emNorm,
            'whatsapp' => $waNorm !== '' ? mkt_formatar_whatsapp($waNorm) : '',
            'email' => $emNorm,
            'nicho' => $nicho,
            'cidade' => $cidade,
            'estado' => $estado,
            'status' => $motivo === '' ? 'novo' : 'rejeitado',
            'motivo' => $motivo,
            'contato_id' => 0,
        ];
        if ($motivo !== '') {
            $resumo['rejeitados']++;
            if ($waNorm === '' && $emNorm === '' && $motivo !== '') $resumo['incompletos']++;
            else $resumo['invalidos']++;
        }
    }

    // Duplicidade dentro do proprio arquivo (WhatsApp ou e-mail repetido).
    $vistosWa = [];
    $vistosEm = [];
    foreach ($preparadas as &$p) {
        if ($p['status'] !== 'novo') continue;
        $dup = false;
        if ($p['whatsapp_norm'] !== '' && isset($vistosWa[$p['whatsapp_norm']])) $dup = true;
        if ($p['email_norm'] !== '' && isset($vistosEm[$p['email_norm']])) $dup = true;
        if ($dup) {
            $p['status'] = 'rejeitado';
            $p['motivo'] = 'Duplicado no arquivo: WhatsApp/e-mail ja aparece em outra linha.';
            $resumo['rejeitados']++;
            $resumo['duplicados']++;
            continue;
        }
        if ($p['whatsapp_norm'] !== '') $vistosWa[$p['whatsapp_norm']] = true;
        if ($p['email_norm'] !== '') $vistosEm[$p['email_norm']] = true;
    }
    unset($p);

    // Busca em lote na base geral.
    $waNorms = [];
    $emNorms = [];
    foreach ($preparadas as $p) {
        if ($p['status'] !== 'novo') continue;
        if ($p['whatsapp_norm'] !== '') $waNorms[$p['whatsapp_norm']] = true;
        if ($p['email_norm'] !== '') $emNorms[$p['email_norm']] = true;
    }
    $mapWa = [];
    $mapEm = [];
    try {
        $pdo = getConnection();
        foreach (array_chunk(array_keys($waNorms), 300) as $chunk) {
            $ph = implode(',', array_fill(0, count($chunk), '?'));
            $stmt = $pdo->prepare("SELECT id, whatsapp_norm FROM marketing_contatos WHERE whatsapp_norm IN ($ph)");
            $stmt->execute($chunk);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $mapWa[$row['whatsapp_norm']] = (int) $row['id'];
        }
        foreach (array_chunk(array_keys($emNorms), 300) as $chunk) {
            $ph = implode(',', array_fill(0, count($chunk), '?'));
            $stmt = $pdo->prepare("SELECT id, email_norm FROM marketing_contatos WHERE email_norm IN ($ph)");
            $stmt->execute($chunk);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $mapEm[$row['email_norm']] = (int) $row['id'];
        }
    } catch (Throwable $e) {
        error_log('[Marketing manual] analisar importacao: ' . $e->getMessage());
    }

    $naLista = [];
    if ($listaId > 0) {
        foreach (mkt_lista_contatos_ids($listaId) as $cid) $naLista[$cid] = true;
    }

    $relatorio = [];
    foreach ($preparadas as $p) {
        if ($p['status'] === 'novo') {
            $porWa = $p['whatsapp_norm'] !== '' ? ($mapWa[$p['whatsapp_norm']] ?? 0) : 0;
            $porEm = $p['email_norm'] !== '' ? ($mapEm[$p['email_norm']] ?? 0) : 0;
            if ($porWa > 0 && $porEm > 0 && $porWa !== $porEm) {
                $p['status'] = 'rejeitado';
                $p['motivo'] = 'WhatsApp e e-mail pertencem a contatos diferentes na base.';
                $resumo['rejeitados']++;
                $resumo['invalidos']++;
            } else {
                $cid = $porWa > 0 ? $porWa : $porEm;
                if ($cid > 0) {
                    if (isset($naLista[$cid])) {
                        $p['status'] = 'rejeitado';
                        $p['motivo'] = 'Duplicado: contato ja esta nesta lista.';
                        $resumo['rejeitados']++;
                        $resumo['duplicados']++;
                    } else {
                        $p['status'] = 'existente';
                        $p['contato_id'] = $cid;
                        $resumo['ja_existentes']++;
                    }
                } else {
                    $resumo['aprovados']++;
                }
            }
        }
        $relatorio[] = $p;
    }

    return ['ok' => true, 'resumo' => $resumo, 'linhas' => $relatorio, 'mapa' => $mapa];
}

function mkt_import_confirmar($token, $listaId) {
    $lista = mkt_lista_obter($listaId);
    if (!$lista) return ['ok' => false, 'mensagem' => 'Lista nao encontrada.', 'resumo' => null];
    $caminho = mkt_import_caminho($token);
    if ($caminho === '') return ['ok' => false, 'mensagem' => 'Arquivo de importacao expirado ou invalido. Envie novamente.', 'resumo' => null];

    $prep = mkt_import_preparar($caminho);
    if (!$prep['ok']) return ['ok' => false, 'mensagem' => $prep['erro'], 'resumo' => null];

    $analise = mkt_import_analisar($prep['linhas'], $prep['mapa'], (int) $listaId);
    if (!$analise['ok']) return ['ok' => false, 'mensagem' => 'Nao foi possivel analisar o arquivo.', 'resumo' => null];

    $pdo = getConnection();
    $inseridos = 0;
    $vinculados = 0;
    $falhas = 0;
    $origem = 'Importacao manual - lista ' . $lista['nome'];

    try {
        $pdo->beginTransaction();
        foreach ($analise['linhas'] as $linha) {
            if ($linha['status'] === 'rejeitado') continue;
            $cid = (int) ($linha['contato_id'] ?? 0);
            if ($linha['status'] === 'novo') {
                $r = mkt_contato_inserir([
                    'empresa' => $linha['empresa'],
                    'nome_contato' => $linha['nome_contato'],
                    'nicho' => $linha['nicho'],
                    'cidade' => $linha['cidade'],
                    'estado' => $linha['estado'],
                    'whatsapp' => $linha['whatsapp_norm'],
                    'email' => $linha['email_norm'],
                    'origem' => $origem,
                    'origem_tipo' => 'manual',
                    'base_legal' => 'consentimento',
                    'observacoes' => 'Importado manualmente pelo Super Admin em ' . date('d/m/Y H:i') . '.',
                ]);
                if ($r['ok']) {
                    $cid = (int) $r['id'];
                    $inseridos++;
                    // Contatos manuais nao entram na fila automatica.
                    $pdo->prepare("UPDATE marketing_contatos SET status = 'manual' WHERE id = ?")->execute([$cid]);
                } elseif ($r['motivo'] === 'duplicado' && (int) $r['id'] > 0) {
                    $cid = (int) $r['id'];
                } else {
                    $falhas++;
                    continue;
                }
            }
            if ($cid > 0 && mkt_lista_vincular((int) $listaId, $cid)) $vinculados++;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[Marketing manual] confirmar importacao: ' . $e->getMessage());
        return ['ok' => false, 'mensagem' => 'Erro ao importar os contatos: ' . $e->getMessage(), 'resumo' => null];
    }

    mkt_import_excluir($token);

    return [
        'ok' => true,
        'mensagem' => 'Importacao concluida: ' . $inseridos . ' contato(s) novo(s), ' . $vinculados . ' vinculado(s) a lista, '
            . $analise['resumo']['rejeitados'] . ' rejeitado(s)' . ($falhas > 0 ? ', ' . $falhas . ' falha(s) de gravacao' : '') . '.',
        'resumo' => $analise['resumo'],
        'inseridos' => $inseridos,
        'vinculados' => $vinculados,
        'falhas' => $falhas,
    ];
}

// Cadastro individual (mesmas validacoes da importacao).
function mkt_manual_contato_criar($listaId, $dados) {
    $linha = [
        trim((string) ($dados['empresa'] ?? '')),
        trim((string) ($dados['nome_contato'] ?? '')),
        trim((string) ($dados['whatsapp'] ?? '')),
        trim((string) ($dados['email'] ?? '')),
        trim((string) ($dados['nicho'] ?? '')),
        trim((string) ($dados['cidade'] ?? '')),
        trim((string) ($dados['estado'] ?? '')),
    ];
    $mapa = ['empresa' => 0, 'nome_contato' => 1, 'whatsapp' => 2, 'email' => 3, 'nicho' => 4, 'cidade' => 5, 'estado' => 6];
    $analise = mkt_import_analisar([['numero' => 1, 'valores' => $linha]], $mapa, (int) $listaId);
    $item = $analise['linhas'][0] ?? null;
    if (!$item || $item['status'] === 'rejeitado') {
        return ['ok' => false, 'mensagem' => $item['motivo'] ?? 'Contato invalido.', 'contato_id' => 0];
    }
    $lista = mkt_lista_obter($listaId);
    if (!$lista) return ['ok' => false, 'mensagem' => 'Lista nao encontrada.', 'contato_id' => 0];

    $cid = (int) ($item['contato_id'] ?? 0);
    if ($item['status'] === 'novo') {
        $r = mkt_contato_inserir([
            'empresa' => $item['empresa'], 'nome_contato' => $item['nome_contato'], 'nicho' => $item['nicho'],
            'cidade' => $item['cidade'], 'estado' => $item['estado'], 'whatsapp' => $item['whatsapp_norm'],
            'email' => $item['email_norm'], 'origem' => 'Cadastro manual - lista ' . $lista['nome'],
            'origem_tipo' => 'manual', 'base_legal' => 'consentimento',
            'observacoes' => 'Cadastrado manualmente pelo Super Admin em ' . date('d/m/Y H:i') . '.',
        ]);
        if (!$r['ok'] && ($r['motivo'] !== 'duplicado' || (int) $r['id'] <= 0)) {
            return ['ok' => false, 'mensagem' => 'Nao foi possivel cadastrar o contato: ' . ($r['detalhe'] ?: $r['motivo']), 'contato_id' => 0];
        }
        $cid = (int) $r['id'];
        if ($r['ok']) getConnection()->prepare("UPDATE marketing_contatos SET status = 'manual' WHERE id = ?")->execute([$cid]);
    }
    mkt_lista_vincular((int) $listaId, $cid);
    return ['ok' => true, 'mensagem' => $item['status'] === 'existente' ? 'Contato ja existente vinculado a lista.' : 'Contato cadastrado e vinculado a lista.', 'contato_id' => $cid];
}

// =====================================================
// Campanhas
// =====================================================

function mkt_campanha_obter($id) {
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT cp.*, l.nome AS lista_nome
            FROM marketing_campanhas cp LEFT JOIN marketing_listas l ON l.id = cp.lista_id
            WHERE cp.id = ? LIMIT 1");
        $stmt->execute([(int) $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function mkt_campanha_validar_dados($dados) {
    $nome = trim((string) ($dados['nome'] ?? ''));
    if (mb_strlen($nome, 'UTF-8') < 3) return ['ok' => false, 'mensagem' => 'Informe o nome da campanha (minimo 3 caracteres).'];
    $listaId = (int) ($dados['lista_id'] ?? 0);
    if (!mkt_lista_obter($listaId)) return ['ok' => false, 'mensagem' => 'Selecione uma lista de contatos valida.'];
    $canais = (string) ($dados['canais'] ?? '');
    if (!in_array($canais, ['whatsapp', 'email', 'ambos'], true)) return ['ok' => false, 'mensagem' => 'Selecione o canal de envio.'];
    $data = (string) ($dados['data_inicio'] ?? '');
    $dt = DateTime::createFromFormat('Y-m-d', $data);
    if (!$dt || $dt->format('Y-m-d') !== $data) return ['ok' => false, 'mensagem' => 'Informe uma data de inicio valida.'];
    $hora = (string) ($dados['hora_inicio'] ?? '');
    if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $hora)) return ['ok' => false, 'mensagem' => 'Informe um horario de inicio valido (HH:MM).'];
    return ['ok' => true, 'mensagem' => '', 'dados' => [
        'nome' => mb_substr($nome, 0, 160, 'UTF-8'),
        'descricao' => mb_substr(trim((string) ($dados['descricao'] ?? '')), 0, 500, 'UTF-8'),
        'lista_id' => $listaId,
        'canais' => $canais,
        'data_inicio' => $data,
        'hora_inicio' => $hora . ':00',
        'ativa' => !empty($dados['ativa']) ? 1 : 0,
    ]];
}

function mkt_campanha_inicio_no_passado($campanha) {
    $tz = new DateTimeZone('America/Sao_Paulo');
    $texto = (string) $campanha['data_inicio'] . ' ' . (string) $campanha['hora_inicio'];
    $alvo = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $texto, $tz);
    if ($alvo instanceof DateTimeImmutable) {
        return $alvo->getTimestamp() <= time();
    }
    $ts = strtotime($texto);
    return $ts !== false && $ts <= time();
}

// Canais efetivamente usados pela campanha.
function mkt_campanha_canais($campanha) {
    return ((string) $campanha['canais'] === 'ambos') ? ['whatsapp', 'email'] : [(string) $campanha['canais']];
}

function mkt_campanha_canal_incluido($campanha, $canal) {
    return in_array((string) $canal, mkt_campanha_canais($campanha), true);
}

function mkt_campanha_canal_prefixo($canal) {
    return $canal === 'whatsapp' ? 'wa' : 'em';
}

// Status individual de cada canal. O status geral da campanha continua sendo
// a visao consolidada, mas cada fila tem o proprio estado e pode estar
// pausada, concluida ou aguardando o limite diario sem afetar a outra.
function mkt_campanha_canal_status($campanha, $canal, $disp = null) {
    if (!mkt_campanha_canal_incluido($campanha, $canal)) return null;
    $prefixo = mkt_campanha_canal_prefixo($canal);
    $status = (string) $campanha['status'];
    if ($status === 'rascunho') return 'rascunho';
    if ($status === 'cancelada') return 'cancelada';
    $total = (int) ($campanha[$prefixo . '_total'] ?? 0);
    $pendentes = (int) ($campanha[$prefixo . '_pendentes'] ?? 0);
    $ativa = (int) ($campanha[$prefixo . '_ativa'] ?? 1);
    if ($total === 0) return 'sem_contatos';
    if ($pendentes === 0) return 'concluida';
    if ($ativa === 0) return 'pausada';
    if ($status === 'pausada') return 'pausada';
    if ($status === 'agendada' && !mkt_campanha_inicio_no_passado($campanha)) return 'agendada';
    if (is_array($disp) && isset($disp['disponiveis'][$canal]) && (int) $disp['disponiveis'][$canal] <= 0) return 'limite';
    return 'em_andamento';
}

// Recalcula o status geral a partir das filas independentes. Uma campanha so
// e concluida quando TODOS os canais participantes terminaram; se um canal
// termina e o outro segue, ela continua em andamento.
function mkt_campanha_recalcular_status($id) {
    $campanha = mkt_campanha_obter($id);
    if (!$campanha) return null;
    $statusAtual = (string) $campanha['status'];
    if (in_array($statusAtual, ['rascunho', 'cancelada'], true)) return $statusAtual;
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT canal, COUNT(*) AS total
            FROM marketing_campanha_envios
            WHERE campanha_id = ?
              AND (status IN ('aguardando','enviando') OR (status='falha' AND tentativas < ?))
            GROUP BY canal");
        $stmt->execute([(int) $id, MKT_MANUAL_MAX_TENTATIVAS]);
        $pendentes = ['whatsapp' => 0, 'email' => 0];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (isset($pendentes[$row['canal']])) $pendentes[$row['canal']] = (int) $row['total'];
        }

        $algumPendente = false;
        $algumAtivoPendente = false;
        foreach (mkt_campanha_canais($campanha) as $canal) {
            $prefixo = mkt_campanha_canal_prefixo($canal);
            if ($pendentes[$canal] <= 0) continue;
            $algumPendente = true;
            if ((int) ($campanha[$prefixo . '_ativa'] ?? 1) === 1) $algumAtivoPendente = true;
        }

        $inicioPassado = mkt_campanha_inicio_no_passado($campanha);
        if (!$inicioPassado) {
            $novo = ($algumPendente && !$algumAtivoPendente) ? 'pausada' : 'agendada';
        } elseif (!$algumPendente) {
            $novo = 'concluida';
        } elseif (!$algumAtivoPendente) {
            $novo = 'pausada';
        } else {
            $novo = 'em_andamento';
        }

        if ($novo === 'concluida') {
            $pdo->prepare("UPDATE marketing_campanhas SET status='concluida', ativa=0,
                concluida_em=COALESCE(concluida_em, NOW()), pausada_em=NULL WHERE id=?")->execute([(int) $id]);
        } elseif ($novo === 'pausada') {
            $pdo->prepare("UPDATE marketing_campanhas SET status='pausada', ativa=0,
                pausada_em=COALESCE(pausada_em, NOW()) WHERE id=?")->execute([(int) $id]);
        } else {
            $pdo->prepare("UPDATE marketing_campanhas SET status=?, ativa=1,
                pausada_em=NULL, concluida_em=NULL WHERE id=?")->execute([$novo, (int) $id]);
        }
        return $novo;
    } catch (Throwable $e) {
        error_log('[Marketing manual] recalcular status: ' . $e->getMessage());
        return $statusAtual;
    }
}

function mkt_campanha_criar($dados) {
    $v = mkt_campanha_validar_dados($dados);
    if (!$v['ok']) return $v;
    $d = $v['dados'];
    $inicioPassado = mkt_campanha_inicio_no_passado($d);
    $status = $d['ativa'] ? ($inicioPassado ? 'em_andamento' : 'agendada') : 'rascunho';
    $flags = $d['ativa'] ? 1 : 0;

    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("INSERT INTO marketing_campanhas
            (nome, descricao, lista_id, canais, data_inicio, hora_inicio, status, ativa, wa_ativa, em_ativa, iniciada_em)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $d['nome'], $d['descricao'], $d['lista_id'], $d['canais'], $d['data_inicio'], $d['hora_inicio'],
            $status, $flags, $flags, $flags, ($d['ativa'] && $inicioPassado) ? date('Y-m-d H:i:s') : null,
        ]);
        $id = (int) $pdo->lastInsertId();
        if ($status !== 'rascunho') {
            mkt_campanha_enfileirar($id);
            mkt_campanha_recalcular_status($id);
        }
        return ['ok' => true, 'mensagem' => 'Campanha criada.', 'id' => $id];
    } catch (Throwable $e) {
        error_log('[Marketing manual] criar campanha: ' . $e->getMessage());
        return ['ok' => false, 'mensagem' => 'Nao foi possivel criar a campanha.'];
    }
}

function mkt_campanha_atualizar($id, $dados) {
    $campanha = mkt_campanha_obter($id);
    if (!$campanha) return ['ok' => false, 'mensagem' => 'Campanha nao encontrada.'];
    if ($campanha['status'] === 'concluida' || $campanha['status'] === 'cancelada') {
        return ['ok' => false, 'mensagem' => 'Campanhas concluidas ou canceladas nao podem ser editadas.'];
    }
    $v = mkt_campanha_validar_dados($dados);
    if (!$v['ok']) return $v;
    $d = $v['dados'];

    $pdo = getConnection();
    try {
        // Se ja houve envio bem-sucedido, nao permite trocar lista/canais.
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM marketing_campanha_envios WHERE campanha_id = ? AND status = 'enviado'");
        $stmt->execute([(int) $id]);
        $jaEnviou = (int) $stmt->fetchColumn() > 0;
        if ($jaEnviou && ((int) $campanha['lista_id'] !== $d['lista_id'] || $campanha['canais'] !== $d['canais'])) {
            return ['ok' => false, 'mensagem' => 'A campanha ja possui envios. Lista e canais nao podem mais ser alterados.'];
        }

        $inicioPassado = mkt_campanha_inicio_no_passado($d);
        $novaAtiva = $d['ativa'] ? 1 : 0;
        $waAtiva = (int) ($campanha['wa_ativa'] ?? 1);
        $emAtiva = (int) ($campanha['em_ativa'] ?? 1);
        // O switch "Ativar campanha" funciona como controle geral: ao ligar,
        // reativa os dois canais; ao desligar, pausa os dois. Sem mudanca no
        // switch, preserva o estado individual de cada fila.
        if ((int) $campanha['ativa'] !== $novaAtiva) {
            $waAtiva = $novaAtiva;
            $emAtiva = $novaAtiva;
        }

        if ($novaAtiva) {
            $status = $inicioPassado ? 'em_andamento' : 'agendada';
        } else {
            $status = in_array($campanha['status'], ['em_andamento', 'agendada'], true) ? 'pausada' : $campanha['status'];
            if ($status === 'agendada') $status = 'pausada';
        }
        $pdo->prepare("UPDATE marketing_campanhas SET nome=?, descricao=?, lista_id=?, canais=?, data_inicio=?, hora_inicio=?,
            status=?, ativa=?, wa_ativa=?, em_ativa=?,
            iniciada_em = CASE WHEN iniciada_em IS NULL AND ? = 1 THEN NOW() ELSE iniciada_em END
            WHERE id=?")
            ->execute([$d['nome'], $d['descricao'], $d['lista_id'], $d['canais'], $d['data_inicio'], $d['hora_inicio'],
                $status, $novaAtiva, $waAtiva, $emAtiva, $novaAtiva, (int) $id]);

        // Reconstroi a fila apenas de itens nunca tentados.
        $pdo->prepare("DELETE FROM marketing_campanha_envios WHERE campanha_id = ? AND status = 'aguardando' AND tentativas = 0")->execute([(int) $id]);
        if (in_array($status, ['agendada', 'em_andamento'], true)) {
            mkt_campanha_enfileirar((int) $id);
            mkt_campanha_recalcular_status((int) $id);
        }
        return ['ok' => true, 'mensagem' => 'Campanha atualizada.'];
    } catch (Throwable $e) {
        error_log('[Marketing manual] atualizar campanha: ' . $e->getMessage());
        return ['ok' => false, 'mensagem' => 'Nao foi possivel atualizar a campanha.'];
    }
}

// Cria a fila da campanha para todos os contatos da lista, respeitando
// opt-out, base legal e a disponibilidade de cada canal no contato.
function mkt_campanha_enfileirar($id) {
    $campanha = mkt_campanha_obter($id);
    if (!$campanha) return 0;
    $canais = mkt_campanha_canais($campanha);

    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT c.id, c.whatsapp_norm, c.email_norm
            FROM marketing_lista_contatos lc
            JOIN marketing_contatos c ON c.id = lc.contato_id
            WHERE lc.lista_id = ? AND c.opt_out = 0
              AND c.base_legal IS NOT NULL AND c.base_legal <> ''");
        $stmt->execute([(int) $campanha['lista_id']]);
        $contatos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $ins = $pdo->prepare("INSERT IGNORE INTO marketing_campanha_envios (campanha_id, contato_id, canal, status) VALUES (?,?,?,'aguardando')");
        $total = 0;
        foreach ($contatos as $c) {
            foreach ($canais as $canal) {
                if ($canal === 'whatsapp' && trim((string) $c['whatsapp_norm']) === '') continue;
                if ($canal === 'email' && trim((string) $c['email_norm']) === '') continue;
                $ins->execute([(int) $campanha['id'], (int) $c['id'], $canal]);
                $total += $ins->rowCount();
            }
        }
        return $total;
    } catch (Throwable $e) {
        error_log('[Marketing manual] enfileirar campanha: ' . $e->getMessage());
        return 0;
    }
}

function mkt_campanha_metricas($id) {
    $m = [
        'total_contatos' => 0, 'wa_total' => 0, 'em_total' => 0,
        'wa_enviados' => 0, 'em_enviados' => 0, 'wa_pendentes' => 0, 'em_pendentes' => 0,
        'wa_falhas' => 0, 'em_falhas' => 0,
        'pendentes' => 0, 'falhas' => 0, 'cancelados' => 0,
    ];
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT lista_id FROM marketing_campanhas WHERE id = ?");
        $stmt->execute([(int) $id]);
        $listaId = (int) $stmt->fetchColumn();
        if ($listaId > 0) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM marketing_lista_contatos WHERE lista_id = ?");
            $stmt->execute([$listaId]);
            $m['total_contatos'] = (int) $stmt->fetchColumn();
        }
        $stmt = $pdo->prepare("SELECT
                SUM(CASE WHEN canal='whatsapp' THEN 1 ELSE 0 END) wa_total,
                SUM(CASE WHEN canal='email' THEN 1 ELSE 0 END) em_total,
                SUM(CASE WHEN canal='whatsapp' AND status='enviado' THEN 1 ELSE 0 END) wa_env,
                SUM(CASE WHEN canal='email' AND status='enviado' THEN 1 ELSE 0 END) em_env,
                SUM(CASE WHEN canal='whatsapp' AND (status IN ('aguardando','enviando') OR (status='falha' AND tentativas < ?)) THEN 1 ELSE 0 END) wa_pend,
                SUM(CASE WHEN canal='email' AND (status IN ('aguardando','enviando') OR (status='falha' AND tentativas < ?)) THEN 1 ELSE 0 END) em_pend,
                SUM(CASE WHEN canal='whatsapp' AND status='falha' THEN 1 ELSE 0 END) wa_fal,
                SUM(CASE WHEN canal='email' AND status='falha' THEN 1 ELSE 0 END) em_fal,
                SUM(CASE WHEN status IN ('aguardando','enviando') OR (status='falha' AND tentativas < ?) THEN 1 ELSE 0 END) pend,
                SUM(CASE WHEN status='falha' THEN 1 ELSE 0 END) fal,
                SUM(CASE WHEN status='cancelado' THEN 1 ELSE 0 END) canc
            FROM marketing_campanha_envios WHERE campanha_id = ?");
        $stmt->execute([MKT_MANUAL_MAX_TENTATIVAS, MKT_MANUAL_MAX_TENTATIVAS, MKT_MANUAL_MAX_TENTATIVAS, (int) $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $m['wa_total'] = (int) ($row['wa_total'] ?? 0);
        $m['em_total'] = (int) ($row['em_total'] ?? 0);
        $m['wa_enviados'] = (int) ($row['wa_env'] ?? 0);
        $m['em_enviados'] = (int) ($row['em_env'] ?? 0);
        $m['wa_pendentes'] = (int) ($row['wa_pend'] ?? 0);
        $m['em_pendentes'] = (int) ($row['em_pend'] ?? 0);
        $m['wa_falhas'] = (int) ($row['wa_fal'] ?? 0);
        $m['em_falhas'] = (int) ($row['em_fal'] ?? 0);
        $m['pendentes'] = (int) ($row['pend'] ?? 0);
        $m['falhas'] = (int) ($row['fal'] ?? 0);
        $m['cancelados'] = (int) ($row['canc'] ?? 0);
    } catch (Throwable $e) {
        error_log('[Marketing manual] metricas: ' . $e->getMessage());
    }
    return $m;
}

function mkt_campanhas_listar($limite = 100) {
    $limite = max(1, min(500, (int) $limite));
    $max = (int) MKT_MANUAL_MAX_TENTATIVAS;
    try {
        $pdo = getConnection();
        $sql = "SELECT cp.*, l.nome AS lista_nome,
                (SELECT COUNT(*) FROM marketing_lista_contatos lc WHERE lc.lista_id = cp.lista_id) AS total_contatos,
                (SELECT COUNT(*) FROM marketing_campanha_envios ce WHERE ce.campanha_id = cp.id AND ce.canal='whatsapp') AS wa_total,
                (SELECT COUNT(*) FROM marketing_campanha_envios ce WHERE ce.campanha_id = cp.id AND ce.canal='email') AS em_total,
                (SELECT COUNT(*) FROM marketing_campanha_envios ce WHERE ce.campanha_id = cp.id AND ce.canal='whatsapp' AND ce.status='enviado') AS wa_enviados,
                (SELECT COUNT(*) FROM marketing_campanha_envios ce WHERE ce.campanha_id = cp.id AND ce.canal='email' AND ce.status='enviado') AS em_enviados,
                (SELECT COUNT(*) FROM marketing_campanha_envios ce WHERE ce.campanha_id = cp.id AND ce.canal='whatsapp' AND (ce.status IN ('aguardando','enviando') OR (ce.status='falha' AND ce.tentativas < $max))) AS wa_pendentes,
                (SELECT COUNT(*) FROM marketing_campanha_envios ce WHERE ce.campanha_id = cp.id AND ce.canal='email' AND (ce.status IN ('aguardando','enviando') OR (ce.status='falha' AND ce.tentativas < $max))) AS em_pendentes,
                (SELECT COUNT(*) FROM marketing_campanha_envios ce WHERE ce.campanha_id = cp.id AND ce.canal='whatsapp' AND ce.status='falha') AS wa_falhas,
                (SELECT COUNT(*) FROM marketing_campanha_envios ce WHERE ce.campanha_id = cp.id AND ce.canal='email' AND ce.status='falha') AS em_falhas,
                (SELECT COUNT(*) FROM marketing_campanha_envios ce WHERE ce.campanha_id = cp.id AND (ce.status IN ('aguardando','enviando') OR (ce.status='falha' AND ce.tentativas < $max))) AS pendentes,
                (SELECT COUNT(*) FROM marketing_campanha_envios ce WHERE ce.campanha_id = cp.id AND ce.status='falha') AS falhas
            FROM marketing_campanhas cp LEFT JOIN marketing_listas l ON l.id = cp.lista_id
            ORDER BY cp.id DESC LIMIT " . $limite;
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('[Marketing manual] listar campanhas: ' . $e->getMessage());
        return [];
    }
}

function mkt_campanha_acao($id, $acao, $canal = '') {
    $campanha = mkt_campanha_obter($id);
    if (!$campanha) return ['ok' => false, 'mensagem' => 'Campanha nao encontrada.'];
    $acao = (string) $acao;
    $status = (string) $campanha['status'];
    $pdo = getConnection();

    try {
        if ($acao === 'iniciar') {
            if (!in_array($status, ['rascunho', 'agendada', 'pausada'], true)) {
                return ['ok' => false, 'mensagem' => 'Esta campanha nao pode ser iniciada no status atual.'];
            }
            $novo = mkt_campanha_inicio_no_passado($campanha) ? 'em_andamento' : 'agendada';
            $pdo->prepare("UPDATE marketing_campanhas SET status=?, ativa=1, wa_ativa=1, em_ativa=1, pausada_em=NULL,
                iniciada_em=COALESCE(iniciada_em, NOW()), concluida_em=NULL WHERE id=?")->execute([$novo, (int) $id]);
            $qtd = mkt_campanha_enfileirar((int) $id);
            $novo = mkt_campanha_recalcular_status((int) $id) ?: $novo;
            return ['ok' => true, 'mensagem' => 'Campanha iniciada. ' . $qtd . ' envio(s) enfileirado(s).', 'status' => $novo];
        }
        if ($acao === 'pausar') {
            if (!in_array($status, ['agendada', 'em_andamento'], true)) {
                return ['ok' => false, 'mensagem' => 'Somente campanhas agendadas ou em andamento podem ser pausadas.'];
            }
            $pdo->prepare("UPDATE marketing_campanhas SET status='pausada', ativa=0, wa_ativa=0, em_ativa=0, pausada_em=NOW() WHERE id=?")->execute([(int) $id]);
            return ['ok' => true, 'mensagem' => 'Campanha pausada nos dois canais. Os envios pendentes ficam na fila.', 'status' => 'pausada'];
        }
        if ($acao === 'retomar') {
            if ($status !== 'pausada') return ['ok' => false, 'mensagem' => 'Somente campanhas pausadas podem ser retomadas.'];
            $novo = mkt_campanha_inicio_no_passado($campanha) ? 'em_andamento' : 'agendada';
            $pdo->prepare("UPDATE marketing_campanhas SET status=?, ativa=1, wa_ativa=1, em_ativa=1, pausada_em=NULL,
                concluida_em=NULL WHERE id=?")->execute([$novo, (int) $id]);
            $qtd = mkt_campanha_enfileirar((int) $id);
            $novo = mkt_campanha_recalcular_status((int) $id) ?: $novo;
            return ['ok' => true, 'mensagem' => 'Campanha retomada. ' . $qtd . ' novo(s) envio(s) enfileirado(s).', 'status' => $novo];
        }
        if ($acao === 'pausar_canal' || $acao === 'retomar_canal') {
            $canal = (string) $canal;
            if (!in_array($canal, ['whatsapp', 'email'], true)) {
                return ['ok' => false, 'mensagem' => 'Canal invalido.'];
            }
            if (!mkt_campanha_canal_incluido($campanha, $canal)) {
                return ['ok' => false, 'mensagem' => 'Este canal nao participa da campanha.'];
            }
            if (in_array($status, ['rascunho', 'concluida', 'cancelada'], true)) {
                return ['ok' => false, 'mensagem' => 'Este canal nao pode ser alterado no status atual.'];
            }
            $coluna = mkt_campanha_canal_prefixo($canal) . '_ativa';
            if ($acao === 'pausar_canal') {
                $pdo->prepare("UPDATE marketing_campanhas SET `$coluna` = 0 WHERE id=?")->execute([(int) $id]);
                $novo = mkt_campanha_recalcular_status((int) $id) ?: $status;
                return ['ok' => true, 'mensagem' => 'Fila de ' . ($canal === 'whatsapp' ? 'WhatsApp' : 'e-mail') . ' pausada. O outro canal continua normalmente.', 'status' => $novo];
            }
            $pdo->prepare("UPDATE marketing_campanhas SET `$coluna` = 1, ativa=1, concluida_em=NULL WHERE id=?")->execute([(int) $id]);
            $qtd = mkt_campanha_enfileirar((int) $id);
            $novo = mkt_campanha_recalcular_status((int) $id) ?: $status;
            return ['ok' => true, 'mensagem' => 'Fila de ' . ($canal === 'whatsapp' ? 'WhatsApp' : 'e-mail') . ' retomada. ' . $qtd . ' novo(s) envio(s) enfileirado(s).', 'status' => $novo];
        }
        if ($acao === 'cancelar') {
            if (!in_array($status, ['rascunho', 'agendada', 'em_andamento', 'pausada'], true)) {
                return ['ok' => false, 'mensagem' => 'Esta campanha nao pode ser cancelada no status atual.'];
            }
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE marketing_campanhas SET status='cancelada', ativa=0, wa_ativa=0, em_ativa=0, cancelada_em=NOW() WHERE id=?")->execute([(int) $id]);
            $pdo->prepare("UPDATE marketing_campanha_envios SET status='cancelado' WHERE campanha_id=? AND status IN ('aguardando','enviando')")->execute([(int) $id]);
            $pdo->commit();
            return ['ok' => true, 'mensagem' => 'Campanha cancelada. Os envios pendentes foram cancelados.', 'status' => 'cancelada'];
        }
        return ['ok' => false, 'mensagem' => 'Acao invalida.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[Marketing manual] acao campanha: ' . $e->getMessage());
        return ['ok' => false, 'mensagem' => 'Nao foi possivel concluir a acao.'];
    }
}

// Previa com o primeiro contato apto da lista (ou exemplo generico).
function mkt_campanha_preview($id) {
    $campanha = mkt_campanha_obter($id);
    if (!$campanha) return ['ok' => false, 'mensagem' => 'Campanha nao encontrada.'];
    $contato = null;
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT c.* FROM marketing_lista_contatos lc
            JOIN marketing_contatos c ON c.id = lc.contato_id
            WHERE lc.lista_id = ? AND c.opt_out = 0 ORDER BY c.id ASC LIMIT 1");
        $stmt->execute([(int) $campanha['lista_id']]);
        $contato = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {}
    if (!$contato) {
        $contato = ['empresa' => 'Empresa Exemplo', 'nome_contato' => 'Contato', 'nicho' => 'seu segmento', 'cidade' => 'Sua Cidade', 'estado' => 'UF'];
    }

    $tplWa = mkt_template_obter('whatsapp');
    $tplEm = mkt_template_obter('email');

    $textoWa = trim(mkt_texto_sem_placeholders_imagem(mkt_template_render($tplWa['conteudo'], $contato)));
    if (stripos($textoWa, 'SAIR') === false) $textoWa .= "\n\nSe nao quiser receber mensagens, responda SAIR.";

    $html = mkt_template_render($tplEm['conteudo'], $contato);
    $aplicado = mkt_email_aplicar_imagens($html, mkt_arquivo_listar('email'));
    $html = $aplicado['html'] . mkt_rodape_optout((int) ($contato['id'] ?? 0));
    $assunto = mkt_template_render($tplEm['assunto'], $contato);

    return [
        'ok' => true,
        'campanha' => ['id' => (int) $campanha['id'], 'nome' => $campanha['nome'], 'canais' => $campanha['canais'], 'status' => $campanha['status']],
        'contato' => [
            'empresa' => (string) ($contato['empresa'] ?? ''),
            'nome_contato' => (string) ($contato['nome_contato'] ?? ''),
            'whatsapp' => (string) ($contato['whatsapp'] ?? ''),
            'email' => (string) ($contato['email'] ?? ''),
        ],
        'whatsapp' => ['texto' => $textoWa, 'imagens' => array_map('mkt_arquivo_url', array_column(mkt_arquivo_listar('whatsapp'), 'id'))],
        'email' => ['assunto' => $assunto, 'html' => $html, 'imagens' => array_map('mkt_arquivo_url', array_column(mkt_arquivo_listar('email'), 'id'))],
    ];
}

// =====================================================
// Processamento da fila manual (Cron Job / botao "processar agora")
// =====================================================

function mkt_manual_processar() {
    $resultado = [
        'ok' => true, 'mensagens' => [], 'campanhas' => 0,
        'whatsapp_enviados' => 0, 'emails_enviados' => 0, 'falhas' => 0, 'pendencias' => [],
    ];
    $pdo = getConnection();
    $lock = (int) $pdo->query("SELECT GET_LOCK('mkt_manual_lock', 0)")->fetchColumn();
    if ($lock !== 1) {
        return array_merge($resultado, ['ok' => false, 'mensagens' => ['Ja existe um processamento manual em andamento.']]);
    }

    try {
        $disp = mkt_manual_disponiveis();
        // Cada canal tem o proprio contador. O limite e global para as
        // campanhas manuais (compartilhado entre elas), mas nunca entre canais.
        $restante = [
            'whatsapp' => (int) $disp['disponiveis']['whatsapp'],
            'email' => (int) $disp['disponiveis']['email'],
        ];
        if ($restante['whatsapp'] === 0 && $restante['email'] === 0) {
            $resultado['mensagens'][] = 'Limite diario manual ja atingido nos dois canais.';
            return $resultado;
        }

        // Como o processamento roda sob GET_LOCK, todo item 'enviando' que
        // restou e de uma execucao anterior interrompida (queda do processo,
        // reinicio do servidor etc.). Ele NAO e reenviado para nao duplicar a
        // mensagem: vira falha definitiva e fica registrado no log.
        try {
            $limiteRecuperacao = (new DateTimeImmutable('-10 minutes', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s');
            $rec = $pdo->prepare("UPDATE marketing_campanha_envios
                SET status='falha', tentativas=GREATEST(tentativas, ?),
                    erro=CONCAT(COALESCE(erro, ''), ' [processamento interrompido: nao reenviado para evitar duplicidade]')
                WHERE status='enviando' AND atualizado_em < ?");
            $rec->execute([MKT_MANUAL_MAX_TENTATIVAS, $limiteRecuperacao]);
            $recuperados = (int) $rec->rowCount();
            if ($recuperados > 0) {
                $resultado['mensagens'][] = $recuperados . ' envio(s) interrompido(s) anteriormente foram marcados como falha para evitar duplicidade.';
            }
        } catch (Throwable $e) {
            error_log('[Marketing manual] recuperar enviando: ' . $e->getMessage());
        }

        $evo = mkt_evolution_config();
        $smtp = mkt_smtp_global_config();
        $tplWa = mkt_template_obter('whatsapp');
        $tplEm = mkt_template_obter('email');
        $evoPronta = mkt_evolution_configurada($evo);
        $smtpPronta = $smtp['host'] !== '' && $smtp['user'] !== '' && $smtp['pass'] !== '' && $smtp['from_email'] !== '';

        $midiasWa = [];
        foreach (mkt_arquivo_listar('whatsapp') as $img) {
            $caminhoImg = mkt_arquivo_caminho($img['arquivo'] ?? '');
            if ($caminhoImg !== '') $midiasWa[] = $img + ['caminho' => $caminhoImg];
        }
        $imagensEm = mkt_arquivo_listar('email');

        $campanhas = $pdo->query("SELECT * FROM marketing_campanhas
            WHERE status IN ('agendada','em_andamento') ORDER BY data_inicio ASC, hora_inicio ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($campanhas as $campanha) {
            if (!mkt_campanha_inicio_no_passado($campanha)) continue;

            // Promove a campanha agendada para em andamento somente quando a
            // data/hora de inicio ja passou (fuso de Brasilia).
            if ($campanha['status'] === 'agendada') {
                $pdo->prepare("UPDATE marketing_campanhas SET status='em_andamento', ativa=1, iniciada_em=COALESCE(iniciada_em, NOW()) WHERE id=?")
                    ->execute([(int) $campanha['id']]);
                $campanha['status'] = 'em_andamento';
            }
            $resultado['campanhas']++;

            // Processa cada canal de forma independente: um pode concluir,
            // pausar ou bater no limite sem interferir no outro.
            foreach (mkt_campanha_canais($campanha) as $canal) {
                $prefixo = mkt_campanha_canal_prefixo($canal);
                $rotulo = $canal === 'whatsapp' ? 'WhatsApp' : 'e-mail';

                // Canal pausado individualmente: pula sem mexer no outro.
                if ((int) ($campanha[$prefixo . '_ativa'] ?? 0) !== 1) continue;

                if ($restante[$canal] <= 0) {
                    $resultado['pendencias'][] = 'Campanha #' . $campanha['id'] . ' (' . $rotulo . '): limite diario atingido; a fila retoma amanha pelo primeiro contato nao enviado.';
                    continue;
                }
                if ($canal === 'whatsapp' && !$evoPronta) {
                    $resultado['pendencias'][] = 'Campanha #' . $campanha['id'] . ' (WhatsApp): API Evolution nao configurada.';
                    continue;
                }
                if ($canal === 'email' && !$smtpPronta) {
                    $resultado['pendencias'][] = 'Campanha #' . $campanha['id'] . ' (e-mail): SMTP global incompleto.';
                    continue;
                }

                $stmt = $pdo->prepare("SELECT ce.id AS envio_id, ce.tentativas, c.*
                    FROM marketing_campanha_envios ce
                    JOIN marketing_contatos c ON c.id = ce.contato_id
                    WHERE ce.campanha_id = ? AND ce.canal = ?
                      AND (ce.status = 'aguardando' OR (ce.status = 'falha' AND ce.tentativas < ?))
                      AND c.opt_out = 0
                      AND c.base_legal IS NOT NULL AND c.base_legal <> ''
                    ORDER BY ce.id ASC
                    LIMIT " . (int) max(1, $restante[$canal]));
                $stmt->execute([(int) $campanha['id'], $canal, MKT_MANUAL_MAX_TENTATIVAS]);
                $fila = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($fila as $item) {
                    if ($restante[$canal] <= 0) break;

                    // Revalida imediatamente antes de enviar: pausa geral,
                    // pausa do canal ou cancelamento interrompem o laco.
                    $colunaCanal = $prefixo . '_ativa';
                    $st = $pdo->prepare("SELECT status, `$colunaCanal` AS canal_ativo FROM marketing_campanhas WHERE id = ?");
                    $st->execute([(int) $campanha['id']]);
                    $atual = $st->fetch(PDO::FETCH_ASSOC);
                    if (!$atual || $atual['status'] !== 'em_andamento' || (int) $atual['canal_ativo'] !== 1) break;

                    // Claim atomico: so um processo consegue assumir o item.
                    // Mesmo apos uma atualizacao de pagina ou execucao
                    // concorrente, quem nao ganhar o UPDATE nao envia nada.
                    $claim = $pdo->prepare("UPDATE marketing_campanha_envios
                        SET status='enviando', tentativas=tentativas+1
                        WHERE id=? AND status IN ('aguardando','falha') AND tentativas < ?");
                    $claim->execute([(int) $item['envio_id'], MKT_MANUAL_MAX_TENTATIVAS]);
                    if ($claim->rowCount() === 0) continue;

                    if ($canal === 'whatsapp') {
                        if (trim((string) $item['whatsapp_norm']) === '') {
                            $pdo->prepare("UPDATE marketing_campanha_envios SET status='falha', erro='Contato sem WhatsApp valido.' WHERE id=?")
                                ->execute([(int) $item['envio_id']]);
                            $resultado['falhas']++;
                            continue;
                        }
                        $texto = trim(mkt_texto_sem_placeholders_imagem(mkt_template_render($tplWa['conteudo'], $item)));
                        if (stripos($texto, 'SAIR') === false) $texto .= "\n\nSe nao quiser receber mensagens, responda SAIR.";
                        $ok = false;
                        $erro = '';
                        $obs = '';
                        if ($midiasWa) {
                            $enviadas = 0;
                            $erros = [];
                            $raws = [];
                            foreach ($midiasWa as $indice => $img) {
                                $r = mkt_evolution_enviar_midia($evo, $item['whatsapp_norm'], $img['caminho'], $img['mime'], $img['nome_original'], $indice === 0 ? $texto : '');
                                $raws[] = $r['raw'];
                                if ($r['ok']) $enviadas++;
                                else $erros[] = ($r['error'] ?: ('HTTP ' . $r['http']));
                            }
                            $ok = $enviadas > 0;
                            $erro = implode(' | ', $erros);
                            $obs = 'Evolution aceitou ' . $enviadas . ' de ' . count($midiasWa) . ' imagem(ns). ' . implode(' ', $raws);
                        } else {
                            $r = mkt_evolution_enviar($evo, $item['whatsapp_norm'], $texto);
                            $ok = $r['ok'];
                            $erro = $r['error'] ?: ('HTTP ' . $r['http']);
                            $obs = $r['raw'];
                        }
                        if ($ok) {
                            $pdo->prepare("UPDATE marketing_campanha_envios SET status='enviado', enviado_em=NOW(), resposta_api=?, erro=NULL WHERE id=?")
                                ->execute([mb_substr($obs, 0, 4000, 'UTF-8'), (int) $item['envio_id']]);
                            $restante['whatsapp']--;
                            $resultado['whatsapp_enviados']++;
                        } else {
                            $pdo->prepare("UPDATE marketing_campanha_envios SET status='falha', erro=?, resposta_api=? WHERE id=?")
                                ->execute([mb_substr($erro, 0, 1500, 'UTF-8'), mb_substr($obs, 0, 4000, 'UTF-8'), (int) $item['envio_id']]);
                            $resultado['falhas']++;
                        }
                    } else {
                        if (trim((string) $item['email_norm']) === '') {
                            $pdo->prepare("UPDATE marketing_campanha_envios SET status='falha', erro='Contato sem e-mail valido.' WHERE id=?")
                                ->execute([(int) $item['envio_id']]);
                            $resultado['falhas']++;
                            continue;
                        }
                        require_once __DIR__ . '/email_helpers.php';
                        $html = mkt_template_render($tplEm['conteudo'], $item);
                        $aplicado = mkt_email_aplicar_imagens($html, $imagensEm);
                        $html = $aplicado['html'] . mkt_rodape_optout((int) $item['id']);
                        $assunto = mkt_template_render($tplEm['assunto'], $item);
                        $textoPlano = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $html)));
                        $fromEmail = $tplEm['remetente_email'] !== '' ? $tplEm['remetente_email'] : $smtp['from_email'];
                        $fromNome = $tplEm['remetente_nome'] !== '' ? $tplEm['remetente_nome'] : $smtp['from_nome'];
                        $ok = enviarEmailMarketing($smtp['host'], $smtp['port'], $smtp['user'], $smtp['pass'], $fromEmail, $fromNome, $smtp['ssl'],
                            $item['email_norm'], $item['empresa'], $assunto, $html, $textoPlano, $aplicado['inline'], $aplicado['anexos']);
                        if ($ok) {
                            $obs = 'SMTP aceitou a mensagem';
                            $qtdInline = count($aplicado['inline']);
                            $qtdAnexo = count($aplicado['anexos']);
                            if ($qtdInline > 0 || $qtdAnexo > 0) $obs .= ' (' . $qtdInline . ' imagem(ns) no corpo, ' . $qtdAnexo . ' em anexo)';
                            $pdo->prepare("UPDATE marketing_campanha_envios SET status='enviado', enviado_em=NOW(), resposta_api=?, erro=NULL WHERE id=?")
                                ->execute([$obs, (int) $item['envio_id']]);
                            $restante['email']--;
                            $resultado['emails_enviados']++;
                        } else {
                            $pdo->prepare("UPDATE marketing_campanha_envios SET status='falha', erro='Falha no envio SMTP (ver log do servidor).' WHERE id=?")
                                ->execute([(int) $item['envio_id']]);
                            $resultado['falhas']++;
                        }
                    }
                }
            }

            // Recalcula o status geral considerando os dois canais. A campanha
            // so e concluida quando nenhum canal tem mais pendencia.
            $novoStatus = mkt_campanha_recalcular_status((int) $campanha['id']);
            if ($novoStatus === 'concluida') {
                $resultado['mensagens'][] = 'Campanha #' . $campanha['id'] . ' concluida.';
            }
        }

        $resultado['mensagens'][] = 'Manual: ' . $resultado['whatsapp_enviados'] . ' WhatsApp e '
            . $resultado['emails_enviados'] . ' e-mail(s) enviado(s) agora.';
        $resultado['mensagens'][] = 'Limites restantes hoje - WhatsApp: ' . $restante['whatsapp'] . ', e-mail: ' . $restante['email'] . '.';
        return $resultado;
    } catch (Throwable $e) {
        error_log('[Marketing manual] processar: ' . $e->getMessage());
        return array_merge($resultado, ['ok' => false, 'mensagens' => ['Erro ao processar a fila manual: ' . $e->getMessage()]]);
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('mkt_manual_lock')");
    }
}

// =====================================================
// Logs e indicadores
// =====================================================

function mkt_manual_logs($filtros = []) {
    $where = [];
    $params = [];
    if (!empty($filtros['campanha_id'])) { $where[] = 'ce.campanha_id = ?'; $params[] = (int) $filtros['campanha_id']; }
    if (!empty($filtros['canal']) && in_array($filtros['canal'], ['whatsapp', 'email'], true)) { $where[] = 'ce.canal = ?'; $params[] = $filtros['canal']; }
    if (!empty($filtros['status'])) {
        $permitidos = ['aguardando', 'enviando', 'enviado', 'falha', 'cancelado'];
        if (in_array($filtros['status'], $permitidos, true)) { $where[] = 'ce.status = ?'; $params[] = $filtros['status']; }
    }
    if (!empty($filtros['data_ini'])) { $where[] = 'COALESCE(ce.enviado_em, ce.criado_em) >= ?'; $params[] = $filtros['data_ini'] . ' 00:00:00'; }
    if (!empty($filtros['data_fim'])) { $where[] = 'COALESCE(ce.enviado_em, ce.criado_em) < ?'; $params[] = date('Y-m-d 00:00:00', strtotime($filtros['data_fim'] . ' +1 day')); }
    $sql = "SELECT ce.*, c.empresa, c.nome_contato, c.whatsapp, c.email, cp.nome AS campanha
        FROM marketing_campanha_envios ce
        JOIN marketing_contatos c ON c.id = ce.contato_id
        JOIN marketing_campanhas cp ON cp.id = ce.campanha_id";
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY ce.id DESC LIMIT 500';
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('[Marketing manual] logs: ' . $e->getMessage());
        return [];
    }
}

function mkt_manual_stats() {
    $stats = ['listas' => 0, 'contatos_manuais' => 0, 'campanhas' => 0, 'campanhas_ativas' => 0];
    try {
        $pdo = getConnection();
        $stats['listas'] = (int) $pdo->query("SELECT COUNT(*) FROM marketing_listas")->fetchColumn();
        $stats['contatos_manuais'] = (int) $pdo->query("SELECT COUNT(*) FROM marketing_contatos WHERE origem_tipo = 'manual'")->fetchColumn();
        $stats['campanhas'] = (int) $pdo->query("SELECT COUNT(*) FROM marketing_campanhas")->fetchColumn();
        $stats['campanhas_ativas'] = (int) $pdo->query("SELECT COUNT(*) FROM marketing_campanhas WHERE status IN ('agendada','em_andamento')")->fetchColumn();
    } catch (Throwable $e) {}
    return $stats;
}
