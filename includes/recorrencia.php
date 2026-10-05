<?php
require_once __DIR__ . '/../config/settings.php';
if (!defined('IN_COBRANCA')) { define('IN_COBRANCA', true); }
// MOTOR DE RECORRENCIA (faturas)
// =====================================================
// Fonte unica de verdade sobre periodicidade e calendario. Todo gerador de
// fatura (painel em admin/emissao.php e o cron em api/cron_envio.php, que
// delega para includes/motor_recorrencia.php) passa por aqui.
//
// PERIODICIDADES ACEITAS (exatamente estas, nada mais):
//   diaria, semanal, quinzenal, mensal, trimestral, semestral, anual
//   "unica" nao e recorrencia: gera uma fatura e nunca mais.
//
// REGRA CENTRAL: a proxima competencia e calculada a partir da COMPETENCIA
// ANTERIOR (data-base), nunca a partir da data em que o cron rodou. Se o cron
// ficou fora do ar e voltou depois, as competencias que passaram continuam
// calculadas a partir da data-base original: 10/10 + 1 mes = 10/11, mesmo que
// o cron tenha rodado em 12/10.
//
// 1) Curto prazo - soma de dias de CALENDARIO (nunca +24 horas):
//      diaria     -> +1 dia
//      semanal    -> +7 dias
//      quinzenal  -> +15 dias corridos (NAO e "duas vezes por mes")
//
// 2) Longo prazo - mesmo dia da ANCORA, de N em N meses:
//      mensal     -> dia <ancora> de cada mes
//      trimestral -> dia <ancora> a cada 3 meses
//      semestral  -> dia <ancora> a cada 6 meses
//      anual      -> dia <ancora> do mesmo mes, no ano seguinte
//
// A ANCORA (dia_ancora) e o dia do mes originalmente escolhido no cadastro e
// NUNCA e sobrescrita pelo ciclo. Isso e o que impede a data de escorregar:
//
//      dia 31, mensal:  31/01 -> 28/02 -> 31/03 -> 30/04 (OK)
//      sem ancora:      31/01 -> 28/02 -> 28/03 -> 28/04 (ERRADO)
//
// Meses curtos e ano bissexto usam o ultimo dia do mes de destino, mas a
// intencao original fica guardada na ancora e volta a valer assim que o mes
// destino tem o dia de novo:
//      29/02/2028 -> 28/02/2029 -> 28/02/2030 -> 28/02/2031 -> 29/02/2032
//
// TUDO aqui usa DateTimeImmutable com o fuso da aplicacao, porque a soma de
// dias de calendario em UTC daria dia errado na virada de horario de verao.
// =====================================================

// Curto prazo: soma de dias de calendario.
define('RECORRENCIA_DIAS', [
    'diaria'    => 1,
    'semanal'   => 7,
    'quinzenal' => 15,
]);

// Longo prazo: mesmo dia da ancora, de N em N meses.
define('RECORRENCIA_MESES', [
    'mensal'     => 1,
    'trimestral' => 3,
    'semestral'  => 6,
    'anual'      => 12,
]);

// Periodicidades aceitas, em ordem de exibicao. Fonte da verdade para validar
// o que vem do formulario e para montar as opcoes da interface.
define('RECORRENCIAS_VALIDAS', ['diaria', 'semanal', 'quinzenal', 'mensal', 'trimestral', 'semestral', 'anual']);

/** Rotulos para a interface. */
define('RECORRENCIA_ROTULOS', [
    'diaria'     => 'Diária',
    'semanal'    => 'Semanal',
    'quinzenal'  => 'Quinzenal',
    'mensal'     => 'Mensal',
    'trimestral' => 'Trimestral',
    'semestral'  => 'Semestral',
    'anual'      => 'Anual',
    'unica'      => 'Fatura Única',
]);

/** Normaliza a frequencia gravada no banco (espaco/case nao quebram a regra). */
function recorrenciaNormalizar($frequencia) {
    return strtolower(trim((string)$frequencia));
}

/** true quando a frequencia pertence a lista aceita. */
function recorrenciaValida($frequencia) {
    return in_array(recorrenciaNormalizar($frequencia), RECORRENCIAS_VALIDAS, true);
}

/** true quando a frequencia NAO gera recorrencia (unica, vazia ou invalida). */
function recorrenciaSemCiclo($frequencia) {
    $f = recorrenciaNormalizar($frequencia);
    if ($f === 'unica' || $f === '') return true;
    return !isset(RECORRENCIA_DIAS[$f]) && !isset(RECORRENCIA_MESES[$f]);
}

// Curto prazo: soma de dias.
function recorrenciaDiasIntervalo($frequencia) {
    $f = recorrenciaNormalizar($frequencia);
    return isset(RECORRENCIA_DIAS[$f]) ? RECORRENCIA_DIAS[$f] : null;
}

// Longo prazo: mesmo dia do mes, de N em N meses.
function recorrenciaMesesIntervalo($frequencia) {
    $f = recorrenciaNormalizar($frequencia);
    return isset(RECORRENCIA_MESES[$f]) ? RECORRENCIA_MESES[$f] : null;
}

/**
 * Objeto de data no fuso da aplicacao, sempre no meio-dia.
 *
 * O meio-dia remove a classe de bug "virada de horario de verao": um dia de
 * calendario pode ter 23h ou 25h, e somar horas sobre um instante as joga para
 * o dia anterior ou seguinte. Meio-dia +- N dias e sempre o dia N.
 */
function recorrenciaData($data) {
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', substr(trim((string)$data), 0, 10));
    if ($d === false) {
        $d = new DateTimeImmutable('today');
    }
    return $d->setTime(12, 0, 0);
}

/**
 * Dia da ancora: o dia do mes que a recorrencia DEVE cobrar.
 *
 * Cai no dia da propria data quando a ancora nao foi informada, para as
 * chamadas antigas continuarem produzindo exatamente o mesmo resultado.
 */
function recorrenciaDiaAncora($data, $diaAncora = null) {
    $dia = (int)$diaAncora;
    if ($dia >= 1 && $dia <= 31) return $dia;
    return (int)recorrenciaData($data)->format('j');
}

/**
 * Soma N meses a uma competencia mantendo o dia da ANCORA.
 *
 * Sem ancora (ou com ancora vazia) o dia vem da propria data, que e o
 * comportamento legado. Com ancora, o dia e sempre o escolhido no cadastro:
 * e o que impede o escorregamento 31/01 -> 28/02 -> 28/03.
 */
function recorrenciaSomarMeses($data, $meses, $diaAncora = null) {
    $base = recorrenciaData($data);
    if ($base === null) return null;

    $dia = recorrenciaDiaAncora($data, $diaAncora);

    // Aritmetica de ano/mes sem depender do dia: 12/2026 + 1 mes = 01/2027.
    $total = ((int)$base->format('Y') * 12) + ((int)$base->format('n') - 1) + (int)$meses;
    $ano  = intdiv($total, 12);
    $mes  = ($total % 12) + 1;

    // Meses curtos e ano bissexto: usa o ultimo dia, sem perder a ancora.
    // "last day of this month" e core do DateTime e nao depende de extensao
    // opcional (cal_last_day exige a extensao calendar, que nem toda
    // instalacao do PHP tem carregada).
    $ultimoDia = (int)(new DateTimeImmutable(sprintf('%04d-%02d-01', $ano, $mes)))
        ->modify('last day of this month')->format('j');
    return sprintf('%04d-%02d-%02d', $ano, $mes, min($dia, $ultimoDia));
}

/**
 * Proxima competencia a partir da competencia atual.
 *
 * Este e o unico lugar do sistema que decide "quando e a proxima cobranca".
 * O cron apenas pergunta se ja passou; ele nunca redefine a periodicidade.
 *
 * @param string     $frequencia  periodicidade da recorrencia
 * @param string     $dataBase    competencia atual (NUNCA a data do cron)
 * @param int|null   $diaAncora   dia do mes originalmente escolhido
 * @return string|null 'Y-m-d' ou null quando a frequencia nao tem ciclo
 */
function proximoVencimentoRecorrencia($frequencia, $dataBase, $diaAncora = null) {
    $f = recorrenciaNormalizar($frequencia);
    $dataBase = substr(trim((string)$dataBase), 0, 10);
    if ($dataBase === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataBase)) return null;

    if (isset(RECORRENCIA_DIAS[$f])) {
        // Calendario, nao aritmetica de segundos: "P1D" respeita o fuso e a
        // virada de horario de verao.
        return recorrenciaData($dataBase)->add(new DateInterval('P' . RECORRENCIA_DIAS[$f] . 'D'))->format('Y-m-d');
    }

    if (isset(RECORRENCIA_MESES[$f])) {
        return recorrenciaSomarMeses($dataBase, RECORRENCIA_MESES[$f], $diaAncora);
    }

    return null;
}

/**
 * Competencia de referencia ao criar a recorrencia.
 *
 * A recorrencia nao gera a fatura do dia em que foi cadastrada: a primeira
 * competencia e a primeira que JA passou a regra da frequencia. Ex.: criada
 * hoje (05/10) como mensal, a primeira fatura e de 05/11.
 *
 * Para "Fatura unica" nao ha ciclo: respeita o dia do mes escolhido.
 */
function primeiroVencimentoRecorrencia($frequencia, $dataInicio, $diaVencimento = 1, $diaAncora = null) {
    $dataInicio = substr(trim((string)$dataInicio), 0, 10);
    if ($dataInicio === '') $dataInicio = date('Y-m-d');

    $proximo = proximoVencimentoRecorrencia($frequencia, $dataInicio, $diaAncora);
    if ($proximo !== null) return $proximo;

    // Fatura unica: sem ciclo, respeita o dia do mes informado.
    $dia = max(1, min(31, intval($diaVencimento ?: 1)));
    $diaFmt = date('Y-m-' . str_pad($dia, 2, '0', STR_PAD_LEFT), strtotime($dataInicio));
    if ($diaFmt < $dataInicio) {
        $diaFmt = date('Y-m-' . str_pad($dia, 2, '0', STR_PAD_LEFT), strtotime($dataInicio . ' +1 month'));
    }
    return $diaFmt;
}

/**
 * Ancora de uma recorrencia ja gravada.
 *
 * Para recorrencia de mes (mensal/trimestral/semestral/anual) a ancora e o
 * dia_vencimento escolhido no cadastro. Para as de dia (diaria/semanal/
 * quinzenal) a ancora nao tem efeito no calculo, mas continua gravada para
 * auditoria e para o caso de a frequencia mudar depois.
 */
function recorrenciaAncoraDoRegistro(array $rec) {
    $diaAncora = isset($rec['dia_ancora']) ? (int)$rec['dia_ancora'] : 0;
    if ($diaAncora >= 1 && $diaAncora <= 31) return $diaAncora;

    if (isset($rec['dia_vencimento']) && (int)$rec['dia_vencimento'] >= 1 && (int)$rec['dia_vencimento'] <= 31) {
        return (int)$rec['dia_vencimento'];
    }

    return (int)recorrenciaData($rec['data_inicio'] ?? date('Y-m-d'))->format('j');
}

// =====================================================
// VIGENCIA DE STATUS (cancelamento/inativacao)
// =====================================================
define('RECORRENCIA_STATUS_GERAM', ['ativa', 'ativo', '']);
define('RECORRENCIA_STATUS_NAO_GERAM', ['cancelado', 'cancelada', 'inativa', 'inativo', 'concluida', 'concluido', 'suspenso', 'pausada', 'encerrada']);

/**
 * true quando a recorrencia PODE gerar faturas.
 *
 * Um status desconhecido nao bloqueia: o objetivo do filtro e nunca cobrar de
 * quem cancelou, e nao impedir cobranca legitima por causa de um rotulo novo.
 */
function recorrenciaPodeGerar(array $rec, array $cliente = null) {
    $status = strtolower(trim((string)($rec['status'] ?? '')));
    if ($status !== '' && !in_array($status, RECORRENCIA_STATUS_GERAM, true)) {
        return ['ok' => false, 'motivo' => "recorrencia com status '{$status}'"];
    }
    if (empty($rec['ativo'])) {
        return ['ok' => false, 'motivo' => 'recorrencia inativa (ativo = 0)'];
    }
    if (!empty($rec['data_fim']) && substr($rec['data_fim'], 0, 10) < date('Y-m-d')) {
        return ['ok' => false, 'motivo' => 'data de termino ultrapassada'];
    }
    // Cliente bloqueado: configuravel, porque nem todo mundo usa o bloqueio
    // como sinal de "nao cobrar".
    if ($cliente && array_key_exists('ativo', $cliente) && (int)$cliente['ativo'] !== 1) {
        if (getConfig('cron_bloquear_cliente_inativo', '1') === '1') {
            return ['ok' => false, 'motivo' => 'cliente bloqueado/inativo'];
        }
    }
    return ['ok' => true, 'motivo' => ''];
}

// =====================================================
// LAPIDE DE FATURAS EXCLUIDAS (tabela faturas_excluidas)
// =====================================================
// A exclusao de uma fatura no painel e definitiva (DELETE na tabela faturas),
// mas a recorrencia continua ativa. Sem nenhum registro, o cron juntava
// "recorrencia ativa" + "nao existe fatura com este vencimento" e concluia
// que a fatura nunca tinha sido emitida: criava uma NOVA, com outro numero, e
// reenviava a cobranca. Era o comportamento relatado: o cron continuava
// mandando fatura de coisas que o admin ja tinha apagado.
//
// A lapide guarda a identidade do CICLO (fatura_recorrente_id +
// competencia) e nao da fatura (que deixa de existir). E o que permite ao
// gerador pular aquela competencia para sempre, sem impedir os proximos ciclos.

function carregarFaturasExcluidas($pdo) {
    $mapa = [];
    try {
        $colunas = recorrenciaColunasDisponiveis($pdo, 'faturas_excluidas');
        $campo = in_array('competencia', $colunas, true) ? 'competencia' : 'data_vencimento';
        $linhas = $pdo->query("SELECT fatura_recorrente_id, {$campo} FROM faturas_excluidas WHERE fatura_recorrente_id IS NOT NULL AND {$campo} IS NOT NULL")->fetchAll();
        foreach ($linhas as $linha) {
            $mapa[faturaExcluidaChave($linha['fatura_recorrente_id'], $linha[$campo])] = true;
        }
    } catch (PDOException $e) {
        // Sem a tabela (instalacao antiga) o cron continua funcionando: nada e
        // considerado excluido e o comportamento e o de antes da mudanca.
        return [];
    }
    return $mapa;
}

/** Chave unica de um ciclo de recorrencia. */
function faturaExcluidaChave($recorrenciaId, $data) {
    return ((int)$recorrenciaId) . '|' . substr(trim((string)$data), 0, 10);
}

/** true quando a fatura deste ciclo foi excluida pelo admin. */
function cicloFaturaExcluida($excluidas, $recorrenciaId, $data) {
    if (empty($excluidas) || empty($recorrenciaId)) return false;
    return isset($excluidas[faturaExcluidaChave($recorrenciaId, $data)]);
}

/** Nomes das colunas de uma tabela (lista vazia se ela nao existir). */
function recorrenciaColunasDisponiveis($pdo, $tabela) {
    static $cache = [];
    $chave = $tabela;
    if (isset($cache[$chave])) return $cache[$chave];

    $cache[$chave] = [];
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$tabela}`");
        $stmt->execute();
        foreach ($stmt->fetchAll() as $col) {
            $cache[$chave][] = $col['Field'];
        }
    } catch (Throwable $e) {
        $cache[$chave] = [];
    }
    return $cache[$chave];
}

/** Colunas ja existentes, para o codigo funcionar antes da migracao rodar. */
function recorrenciaColunaExiste($pdo, $tabela, $coluna) {
    return in_array($coluna, recorrenciaColunasDisponiveis($pdo, $tabela), true);
}

/**
 * Exclui faturas de forma definitiva gravando antes a lapide de cada ciclo.
 *
 * Grava e apaga na mesma transacao: ou os dois acontecem, ou nada acontece.
 * Sem isso, uma queda no meio do DELETE deixaria o ciclo sem fatura e sem
 * registro da exclusao - que e exatamente o estado que faz o cron recriar a
 * fatura. O cancelamento no gateway NAO entra aqui: e chamada HTTP longa e
 * ficaria segurando a transacao aberta; quem chama faz isso antes.
 *
 * @return int quantidade de faturas efetivamente removidas
 */
function excluirFaturasComLapide($pdo, array $faturas, $motivo = 'excluir') {
    if (empty($faturas)) return 0;

    try {
        $temCompetencia = recorrenciaColunaExiste($pdo, 'faturas', 'competencia');

        $insLapide = $pdo->prepare("INSERT INTO faturas_excluidas (admin_id, fatura_recorrente_id, cliente_id, numero, competencia, data_vencimento, motivo) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $delFatura = $pdo->prepare("DELETE FROM faturas WHERE id = ?");

        $pdo->beginTransaction();
        $removidas = 0;
        foreach ($faturas as $fat) {
            if (empty($fat['id'])) continue;
            $competencia = $temCompetencia && !empty($fat['competencia'])
                ? substr(trim((string)$fat['competencia']), 0, 10)
                : substr(trim((string)($fat['data_vencimento'] ?? '')), 0, 10);
            $insLapide->execute([
                $fat['admin_id'] ?? null,
                $fat['fatura_recorrente_id'] ?? null,
                $fat['cliente_id'] ?? null,
                $fat['numero'] ?? null,
                $competencia ?: null,
                $competencia ?: null,
                $motivo,
            ]);
            $delFatura->execute([$fat['id']]);
            $removidas += $delFatura->rowCount();
        }
        $pdo->commit();
        return $removidas;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[LAPIDE] falha ao excluir faturas: ' . $e->getMessage());
        return 0;
    }
}

/** Rotulo curto da frequencia, usado em telas e logs. */
function recorrenciaRotulo($frequencia) {
    $f = recorrenciaNormalizar($frequencia);
    if ($f === 'unica') return 'Fatura Única';
    return RECORRENCIA_ROTULOS[$f] ?? ucfirst($f);
}

/** Opcoes de <select> de recorrencia, no formato aceito por emissao.php. */
function recorrenciaOpcoes($selecionada = '') {
    $html = '<option value="unica">Fatura Única</option>';
    foreach (RECORRENCIAS_VALIDAS as $f) {
        $html .= '<option value="' . $f . '"' . ($f === $selecionada ? ' selected' : '') . '>' . RECORRENCIA_ROTULOS[$f] . '</option>';
    }
    return $html;
}