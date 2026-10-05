<?php
// =====================================================
// REGRA UNICA DE RECORRENCIA (faturas)
// =====================================================
// Fonte unica de verdade compartilhada por TODOS os lugares que
// geram faturas recorrentes (admin e superadmin):
//   - admin/emissao.php  (1a fatura ao criar a recorrencia)
//   - api/cron_envio.php (geracao automatica a cada ciclo)
//
// REGRA: a proxima fatura e calculada a partir da DATA DE EMISSAO
// da fatura atual. Existem dois grupos:
//
// 1) Curto prazo - soma fixa de dias a partir da emissao:
//      diaria     -> +1 dia
//      semanal    -> +7 dias
//      quinzenal  -> +15 dias
//
// 2) Longo prazo - continua no MESMO DIA do mes, de N em N meses:
//      mensal     -> todo dia <D> de cada mes
//      bimestral  -> todo dia <D> a cada 2 meses
//      trimestral -> todo dia <D> a cada 3 meses
//      semestral  -> todo dia <D> a cada 6 meses
//      anual      -> todo dia <D> do mesmo mes, no ano seguinte
//
//    Exemplo: fatura emitida no dia 26.
//      mensal     -> dia 26 do proximo mes
//      bimestral  -> dia 26 de 2 meses depois
//      anual      -> dia 26 do mesmo mes, no ano seguinte
//    Se o mes de destino for mais curto (ex.: dia 31 em fevereiro),
//    o vencimento cai no ultimo dia do mes.
//
// 3) "Fatura unica" (e qualquer valor vazio/desconhecido) NAO tem
//    recorrencia: nunca gera uma segunda fatura.
// =====================================================

// Curto prazo: soma fixa de dias.
define('RECORRENCIA_DIAS', [
    'diaria'    => 1,
    'semanal'   => 7,
    'quinzenal' => 15,
]);

// Longo prazo: mesmo dia do mes, de N em N meses.
define('RECORRENCIA_MESES', [
    'mensal'     => 1,
    'bimestral'  => 2,
    'trimestral' => 3,
    'semestral'  => 6,
    'anual'      => 12,
]);

/** Normaliza a frequencia gravada no banco (espaco/case nao quebram a regra). */
function recorrenciaNormalizar($frequencia) {
    return strtolower(trim((string)$frequencia));
}

/** true quando a frequencia NAO gera recorrencia (unica, vazia ou desconhecida). */
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
 * Soma N meses mantendo o dia do mes (ex.: 26/01 +1 mes = 26/02).
 * Ajusta para o ultimo dia quando o mes de destino e mais curto.
 */
function recorrenciaSomarMeses($data, $meses) {
    $ts = strtotime($data);
    if ($ts === false) return null;
    $dia  = (int)date('j', $ts);
    $total = ((int)date('Y', $ts) * 12) + ((int)date('n', $ts) - 1) + (int)$meses;
    $ano  = intdiv($total, 12);
    $mes  = ($total % 12) + 1;
    $ultimoDia = (int)date('t', mktime(0, 0, 0, $mes, 1, $ano));
    return sprintf('%04d-%02d-%02d', $ano, $mes, min($dia, $ultimoDia));
}

/**
 * Proxima data de vencimento a partir da data de emissao da fatura atual.
 * Retorna null quando a frequencia nao tem recorrencia -> fim do ciclo.
 */
function proximoVencimentoRecorrencia($frequencia, $dataEmissao) {
    $f = recorrenciaNormalizar($frequencia);
    $dataEmissao = trim((string)$dataEmissao);
    if ($dataEmissao === '') return null;

    if (isset(RECORRENCIA_DIAS[$f])) {
        return date('Y-m-d', strtotime($dataEmissao . ' +' . RECORRENCIA_DIAS[$f] . ' days'));
    }
    if (isset(RECORRENCIA_MESES[$f])) {
        return recorrenciaSomarMeses($dataEmissao, RECORRENCIA_MESES[$f]);
    }
    return null;
}

/**
 * Primeiro vencimento ao criar a recorrencia.
 * - recorrencia: data de inicio + a regra da frequencia;
 * - fatura unica: sem ciclo, respeita o dia do mes escolhido.
 */
function primeiroVencimentoRecorrencia($frequencia, $dataInicio, $diaVencimento = 1) {
    $dataInicio = trim((string)$dataInicio);
    if ($dataInicio === '') $dataInicio = date('Y-m-d');

    $proximo = proximoVencimentoRecorrencia($frequencia, $dataInicio);
    if ($proximo !== null) return $proximo;

    // Fatura unica: sem ciclo, respeita o dia do mes informado.
    $dia = max(1, min(31, intval($diaVencimento ?: 1)));
    $diaFmt = date('Y-m-' . str_pad($dia, 2, '0', STR_PAD_LEFT), strtotime($dataInicio));
    if ($diaFmt < $dataInicio) {
        $diaFmt = date('Y-m-' . str_pad($dia, 2, '0', STR_PAD_LEFT), strtotime($dataInicio . ' +1 month'));
    }
    return $diaFmt;
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
// data_vencimento) e nao da fatura (que deixa de existir). E o que permite ao
// gerador pular aquele vencimento para sempre, sem impedir os proximos ciclos.

// Monta o indice "recorrencia|vencimento" de tudo que ja foi excluido.
// Carregar uma vez por execucao do cron evita uma consulta por ciclo/fatura.
function carregarFaturasExcluidas($pdo) {
    $mapa = [];
    try {
        $linhas = $pdo->query("SELECT fatura_recorrente_id, data_vencimento FROM faturas_excluidas WHERE fatura_recorrente_id IS NOT NULL AND data_vencimento IS NOT NULL")->fetchAll();
        foreach ($linhas as $linha) {
            $mapa[faturaExcluidaChave($linha['fatura_recorrente_id'], $linha['data_vencimento'])] = true;
        }
    } catch (PDOException $e) {
        // Sem a tabela (instalacao antiga) o cron continua funcionando: nada e
        // considerado excluido e o comportamento e o de antes da mudanca.
        return [];
    }
    return $mapa;
}

/** Chave unica de um ciclo de recorrencia. */
function faturaExcluidaChave($recorrenciaId, $dataVencimento) {
    return ((int)$recorrenciaId) . '|' . substr(trim((string)$dataVencimento), 0, 10);
}

/** true quando a fatura deste ciclo foi excluida pelo admin. */
function cicloFaturaExcluida($excluidas, $recorrenciaId, $dataVencimento) {
    if (empty($excluidas) || empty($recorrenciaId)) return false;
    return isset($excluidas[faturaExcluidaChave($recorrenciaId, $dataVencimento)]);
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
        $insLapide = $pdo->prepare("INSERT INTO faturas_excluidas (admin_id, fatura_recorrente_id, cliente_id, numero, data_vencimento, motivo) VALUES (?, ?, ?, ?, ?, ?)");
        $delFatura = $pdo->prepare("DELETE FROM faturas WHERE id = ?");

        $pdo->beginTransaction();
        $removidas = 0;
        foreach ($faturas as $fat) {
            if (empty($fat['id'])) continue;
            $insLapide->execute([
                $fat['admin_id'] ?? null,
                $fat['fatura_recorrente_id'] ?? null,
                $fat['cliente_id'] ?? null,
                $fat['numero'] ?? null,
                substr(trim((string)($fat['data_vencimento'] ?? '')), 0, 10) ?: null,
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
    $rotulos = [
        'unica'      => 'Unica',
        'diaria'     => 'Diaria',
        'semanal'    => 'Semanal',
        'quinzenal'  => 'Quinzenal',
        'mensal'     => 'Mensal',
        'bimestral'  => 'Bimestral',
        'trimestral' => 'Trimestral',
        'semestral'  => 'Semestral',
        'anual'      => 'Anual',
    ];
    $f = recorrenciaNormalizar($frequencia);
    return $rotulos[$f] ?? ucfirst($f);
}
