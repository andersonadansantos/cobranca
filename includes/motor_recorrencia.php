<?php
// =====================================================
// MOTOR DE PROCESSAMENTO DE RECORRENCIAS
// =====================================================
// Executado pelo cron. Aqui mora o ciclo de trabalho: o motor NAO decide
// a periodicidade (isso e de includes/recorrencia.php) e NAO redefine datas. Ele
// apenas pergunta "o que esta pendente?" e emite o que estiver.
//
// Garantias implementadas:
//   - Idempotencia: UNIQUE(fatura_recorrente_id, competencia) no banco. Se duas
//     execucoes tentarem a mesma competencia, uma perde na constraint e nao
//     duplica a fatura.
//   - Concorrencia: GET_LOCK do MySQL impede duas execucoes simultaneas. A
//     segunda sai imediatamente em vez de competir pelos mesmos ciclos.
//   - Isolamento: cada recorrencia e processada em try/catch. Um erro no
//     cliente B nao interrompe o cliente C.
//   - Auditoria: cron_execucoes (a execucao) e cron_log (cada decisao).
//   - Nada se perde em falha: se a emissao falhar, data_proxima_fatura NAO
//     avanca e a proxima execucao tenta de novo.
//
// As datas de competencia, emissao e vencimento sao gravadas separadas:
//   competencia     = a data-base do ciclo (regra da frequencia)
//   data_emissao    = quando a fatura foi emitida (hoje)
//   data_vencimento = competencia + dias_vencimento
// =====================================================

require_once __DIR__ . '/recorrencia.php';

const MOTOR_LOCK_NOME       = 'cobranca_motor_recorrencia';
const MOTOR_MAX_CICLOS_RUN  = 500;
const MOTOR_MAX_TENTATIVAS  = 5;

/** Identificador da execucao, usado para amarrar todos os logs. */
function motorGerarExecucaoId() {
    return date('Ymd-His') . '-' . bin2hex(random_bytes(6));
}

/**
 * Trava de execucao exclusiva.
 *
 * @return bool true se esta execucao-it pegou a trava.
 */
function motorAdquirirLock($pdo, $nome = MOTOR_LOCK_NOME) {
    try {
        $stmt = $pdo->query("SELECT GET_LOCK(" . $pdo->quote($nome) . ", 0)");
        return ((int)$stmt->fetchColumn()) === 1;
    } catch (Throwable $e) {
        // Sem GET_LOCK (ou sem permissao) o sistema ainda roda: a UNIQUE de
        // competencia continua impedindo duplicidade. Perde-se apenas a
        // protecao contra execucoes simultaneas.
        error_log('[MOTOR] GET_LOCK indisponivel: ' . $e->getMessage());
        return true;
    }
}

function motorLiberarLock($pdo, $nome = MOTOR_LOCK_NOME) {
    try { $pdo->query("SELECT RELEASE_LOCK(" . $pdo->quote($nome) . ")"); } catch (Throwable $e) {}
}

/** Abre o registro da execucao. Devolve o execucao_id. */
function motorIniciarExecucao($pdo, $origem = 'cron') {
    $id = motorGerarExecucaoId();
    try {
        $pdo->prepare("INSERT INTO cron_execucoes (execucao_id, inicio, origem) VALUES (?, NOW(), ?)")
            ->execute([$id, substr((string)$origem, 0, 40)]);
    } catch (Throwable $e) {
        error_log('[MOTOR] nao foi possivel registrar inicio da execucao: ' . $e->getMessage());
    }
    return $id;
}

/** Fecha o registro da execucao com o resultado consolidado. */
function motorFinalizarExecucao($pdo, $execucaoId, array $stats, $resultado = 'ok', $mensagem = null) {
    try {
        $pdo->prepare("UPDATE cron_execucoes
            SET fim = NOW(), recorrencias_processadas = ?, faturas_emitidas = ?,
                ignoradas = ?, erros = ?, resultado = ?, mensagem = ?
            WHERE execucao_id = ?")
            ->execute([
                (int)($stats['processadas'] ?? 0),
                (int)($stats['emitidas'] ?? 0),
                (int)($stats['ignoradas'] ?? 0),
                (int)($stats['erros'] ?? 0),
                substr((string)$resultado, 0, 20),
                $mensagem !== null ? substr((string)$mensagem, 0, 2000) : null,
                $execucaoId,
            ]);
    } catch (Throwable $e) {
        error_log('[MOTOR] nao foi possivel finalizar execucao: ' . $e->getMessage());
    }
}

/**
 * Grava uma linha de auditoria.
 *
 * @param string $resultado emitida|duplicada|excluida|ignorada|descartada|
 *                        limite|encerrada|erro|pagamento_ok|pagamento_erro
 */
function motorLog($pdo, $execucaoId, array $d) {
    if (empty($d['resultado'])) return;
    try {
        $pdo->prepare("INSERT INTO cron_log
            (execucao_id, admin_id, fatura_recorrente_id, cliente_id, frequencia, competencia,
             data_vencimento, fatura_id, numero, valor, proxima_competencia, resultado, mensagem, criado_em)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())")
            ->execute([
                $execucaoId,
                $d['admin_id'] ?? null,
                $d['fatura_recorrente_id'] ?? null,
                $d['cliente_id'] ?? null,
                isset($d['frequencia']) ? substr((string)$d['frequencia'], 0, 20) : null,
                !empty($d['competencia']) ? substr((string)$d['competencia'], 0, 10) : null,
                !empty($d['data_vencimento']) ? substr((string)$d['data_vencimento'], 0, 10) : null,
                !empty($d['fatura_id']) ? (int)$d['fatura_id'] : null,
                !empty($d['numero']) ? substr((string)$d['numero'], 0, 20) : null,
                isset($d['valor']) ? $d['valor'] : null,
                !empty($d['proxima_competencia']) ? substr((string)$d['proxima_competencia'], 0, 10) : null,
                substr((string)$d['resultado'], 0, 30),
                isset($d['mensagem']) && $d['mensagem'] !== null ? substr((string)$d['mensagem'], 0, 2000) : null,
            ]);
    } catch (Throwable $e) {
        error_log('[MOTOR] falha ao gravar log: ' . $e->getMessage());
    }
}

/**
 * Competencias ja faturadas de uma recorrencia (mapa competencia => fatura_id).
 */
function motorCompetenciasExistentes($pdo, $recorrenciaId) {
    $mapa = [];
    try {
        $campo = recorrenciaColunaExiste($pdo, 'faturas', 'competencia') ? 'competencia' : 'data_vencimento';
        $stmt = $pdo->prepare("SELECT id, {$campo} AS c FROM faturas WHERE fatura_recorrente_id = ?");
        $stmt->execute([(int)$recorrenciaId]);
        foreach ($stmt->fetchAll() as $linha) {
            if (!empty($linha['c'])) $mapa[substr((string)$linha['c'], 0, 10)] = (int)$linha['id'];
        }
    } catch (Throwable $e) {
        error_log('[MOTOR] nao foi possivel ler competencias existentes: ' . $e->getMessage());
    }
    return $mapa;
}

/**
 * Competencia a processar em seguida.
 *
 * Usa data_proxima_fatura quando existe. Quando ainda nao existe (recorrencia
 * antiga, anterior a esta mudanca) deriva caminhando a partir de data_inicio e
 * para na primeira competencia que ainda NAO tem fatura. Isso e autocorrigente:
 * uma recorrencia recem-migrada nao emite nada que ja foi emitido, e uma
 * recorrencia parada ha meses recupera a fila pendente sem pular competencia.
 */
function motorProximaCompetencia($pdo, array $rec) {
    if (!empty($rec['data_proxima_fatura'])) {
        return substr((string)$rec['data_proxima_fatura'], 0, 10);
    }

    $frequencia = recorrenciaNormalizar($rec['frequencia'] ?? '');
    if (recorrenciaSemCiclo($frequencia)) return null;

    $ancora = recorrenciaAncoraDoRegistro($rec);
    $atual = primeiroVencimentoRecorrencia(
        $frequencia,
        $rec['data_inicio'] ?? date('Y-m-d'),
        $rec['dia_vencimento'] ?? 1,
        $ancora
    );
    if ($atual === null) return null;

    $existentes = motorCompetenciasExistentes($pdo, $rec['id']);
    $guard = 0;
    while ($guard++ < MOTOR_MAX_CICLOS_RUN) {
        if (!isset($existentes[$atual])) return $atual;
        $prox = proximoVencimentoRecorrencia($frequencia, $atual, $ancora);
        if ($prox === null) return null;
        $atual = $prox;
    }
    return null;
}

/**
 * Valor da fatura para uma dada competencia.
 *
 * Nao copia a fatura anterior: aplica a regra que estava vigente na data da
 * competencia. Uma recorrencia sem nenhum ajuste reproduz exatamente o valor
 * cadastrado, que e o comportamento de sempre.
 */
function motorValorCompetencia($pdo, array $rec, $competencia) {
    $valor = (float)($rec['valor'] ?? 0);
    $desconto = 0.0;
    $multa = 0.0;
    $jurosPct = 0.0;

    try {
        if (recorrenciaColunaExiste($pdo, 'recorrencia_ajustes', 'id')) {
            // Vigente: a mais recente com data_vigencia <= competencia.
            $stmt = $pdo->prepare("SELECT percentual, valor, desconto, multa, juros
                FROM recorrencia_ajustes
                WHERE fatura_recorrente_id = ? AND ativo = 1 AND data_vigencia <= ?
                ORDER BY data_vigencia DESC, id DESC LIMIT 1");
            $stmt->execute([(int)$rec['id'], $competencia]);
            $ajuste = $stmt->fetch();

            if ($ajuste) {
                // Reajuste percentual tem precedencia sobre o valor fechado:
                // quem cadastrou os dois quis dizer "reajuste em cima do valor".
                if ($ajuste['percentual'] !== null && $ajuste['percentual'] != 0) {
                    $valor = $valor * (1 + ((float)$ajuste['percentual'] / 100));
                } elseif ($ajuste['valor'] !== null && $ajuste['valor'] != 0) {
                    $valor = (float)$ajuste['valor'];
                }
                if ($ajuste['desconto'] !== null) $desconto = (float)$ajuste['desconto'];
                if ($ajuste['multa'] !== null)     $multa = (float)$ajuste['multa'];
                if ($ajuste['juros'] !== null)     $jurosPct = (float)$ajuste['juros'];
            }
        }
    } catch (Throwable $e) {
        error_log('[MOTOR] falha ao calcular valor: ' . $e->getMessage());
    }

    // Juros entra como percentual do valor (a coluna e DECIMAL(5,2), o que
    // indica taxa e nao valor fechado). Documentado aqui para nao haver
    // duvida sobre o que a fatura vai cobrar.
    $jurosValor = $valor * ($jurosPct / 100);

    $valorFinal = $valor + $multa + $jurosValor - $desconto;
    if ($valorFinal < 0) $valorFinal = 0;

    return [
        'valor'       => round($valor, 2),
        'desconto'    => round($desconto, 2),
        'multa'       => round($multa, 2),
        'juros'       => $jurosPct,
        'valor_final' => round($valorFinal, 2),
    ];
}

/** Avanca data_proxima_fatura e zera o contador de erro. */
function motorAvancarProxima($pdo, $recId, $proxima, $ultimaEmitida = null) {
    try {
        $pdo->prepare("UPDATE faturas_recorrentes
            SET data_proxima_fatura = ?, data_ultima_fatura = COALESCE(?, data_ultima_fatura),
                tentativas_erro = 0, ultimo_erro = NULL
            WHERE id = ?")
            ->execute([$proxima, $ultimaEmitida, (int)$recId]);
    } catch (Throwable $e) {
        error_log('[MOTOR] falha ao avancar data_proxima_fatura: ' . $e->getMessage());
    }
}

/** Registra a falha sem mexer na data (para a proxima execucao tentar). */
function motorRegistrarErro($pdo, $recId, $mensagem) {
    try {
        $pdo->prepare("UPDATE faturas_recorrentes
            SET tentativas_erro = tentativas_erro + 1, ultimo_erro = ?
            WHERE id = ?")
            ->execute([substr((string)$mensagem, 0, 2000), (int)$recId]);
    } catch (Throwable $e) {}
}

/**
 * Emite a fatura de UMA competencia.
 *
 * A gravacao e atomica: INSERT da fatura + avanco de data_proxima_fatura na
 * mesma transacao. Se a UNIQUE de competencia for violada (outra execucao
 * ganhou a corrida), devolve 'duplicada' sem criar nada - e sem numero
 * consumido.
 *
 * @return array ['resultado' => 'emitida'|'duplicada'|'erro', ...]
 */
function motorEmitirCompetencia($pdo, array $rec, $competencia, $ctx) {
    $recId = (int)$rec['id'];
    $frequencia = recorrenciaNormalizar($rec['frequencia']);
    $ancora = recorrenciaAncoraDoRegistro($rec);
    $proxima = proximoVencimentoRecorrencia($frequencia, $competencia, $ancora);

    $vencimento = $competencia;
    $diasVenc = (int)($rec['dias_vencimento'] ?? 0);
    if ($diasVenc !== 0) {
        $vencimento = recorrenciaData($competencia)->add(new DateInterval('P' . $diasVenc . 'D'))->format('Y-m-d');
    }

    $valores = motorValorCompetencia($pdo, $rec, $competencia);

    // Limite de transacoes: conta o que ja existe, sem contar o que esta
    // sendo gerado agora.
    if (!empty($rec['quantidade_transacoes'])) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM faturas WHERE fatura_recorrente_id = ?");
        $stmt->execute([$recId]);
        $qtd = (int)$stmt->fetchColumn();
        if ($qtd >= (int)$rec['quantidade_transacoes']) {
            try {
                $pdo->prepare("UPDATE faturas_recorrentes SET ativo = 0, status = 'concluida' WHERE id = ?")->execute([$recId]);
            } catch (Throwable $e) {}
            return [
                'resultado' => 'limite',
                'mensagem'  => "limite de {$rec['quantidade_transacoes']} transacoes atingido ({$qtd}); recorrencia encerrada",
                'proxima'   => $proxima,
            ];
        }
    }

    if (!empty($rec['data_fim']) && $competencia > substr((string)$rec['data_fim'], 0, 10)) {
        try {
            $pdo->prepare("UPDATE faturas_recorrentes SET ativo = 0, status = 'cancelado' WHERE id = ?")->execute([$recId]);
        } catch (Throwable $e) {}
        return [
            'resultado' => 'encerrada',
            'mensagem'  => 'competencia apos a data de termino; recorrencia encerrada',
            'proxima'   => $proxima,
        ];
    }

    $numero = function_exists('generateInvoiceNumber') ? generateInvoiceNumber($rec['admin_id'] ?? null) : ('REC' . date('YmdHis'));
    $acesso = function_exists('generateAcessoToken') ? generateAcessoToken() : bin2hex(random_bytes(32));
    $temCompetencia = recorrenciaColunaExiste($pdo, 'faturas', 'competencia');
    $apiPagamento = function_exists('getApiAtiva') ? getApiAtiva() : null;

    $emTransacao = false;
    try {
        $pdo->beginTransaction();
        $emTransacao = true;

        if ($temCompetencia) {
            $sql = "INSERT INTO faturas
                    (admin_id, cliente_id, fatura_recorrente_id, numero, descricao,
                     valor, desconto, multa, juros, valor_final,
                     data_emissao, competencia, data_vencimento, status, acesso_token, api_pagamento)
                    VALUES (?,?,?,?,?,?,?,?,?,?, CURDATE(), ?, ?, 'pendente', ?, ?)";
        } else {
            // Banco ainda sem a coluna (instalacao em migracao): a competencia
            // nao e gravada, mas a UNIQUE ainda nao existe, entao a checagem
            // por competencia fica por conta da consulta previa.
            $sql = "INSERT INTO faturas
                    (admin_id, cliente_id, fatura_recorrente_id, numero, descricao,
                     valor, desconto, multa, juros, valor_final,
                     data_emissao, data_vencimento, status, acesso_token, api_pagamento)
                    VALUES (?,?,?,?,?,?,?,?,?,?, CURDATE(), ?, 'pendente', ?, ?)";
        }

        $params = [
            $rec['admin_id'] ?? null,
            $rec['cliente_id'] ?? null,
            $recId,
            $numero,
            $rec['descricao'] ?? 'Fatura recorrente',
            $valores['valor'],
            $valores['desconto'],
            $valores['multa'],
            $valores['juros'],
            $valores['valor_final'],
        ];
        if ($temCompetencia) $params[] = $competencia;
        $params[] = $vencimento;
        $params[] = $acesso;
        $params[] = $apiPagamento;

        $pdo->prepare($sql)->execute($params);
        $faturaId = (int)$pdo->lastInsertId();

        $pdo->prepare("UPDATE faturas_recorrentes
            SET data_proxima_fatura = ?, data_ultima_fatura = ?,
                tentativas_erro = 0, ultimo_erro = NULL
            WHERE id = ?")
            ->execute([$proxima, $competencia, $recId]);

        $pdo->commit();
        $emTransacao = false;

        return [
            'resultado'   => 'emitida',
            'fatura_id'   => $faturaId,
            'numero'      => $numero,
            'valor'       => $valores['valor_final'],
            'vencimento'  => $vencimento,
            'proxima'     => $proxima,
            'competencia' => $competencia,
        ];
    } catch (PDOException $e) {
        if ($emTransacao && $pdo->inTransaction()) $pdo->rollBack();
        // 23000 = integridade. Com a UNIQUE de competencia, e outra execucao
        // que ja emitted esta competencia. Nao e erro: e concorrencia sanada.
        if ($e->getCode() === '23000') {
            try {
                $pdo->prepare("UPDATE faturas_recorrentes
                    SET data_proxima_fatura = ?, data_ultima_fatura = ?, ultimo_erro = NULL
                    WHERE id = ?")->execute([$proxima, $competencia, $recId]);
            } catch (Throwable $e2) {}
            return [
                'resultado'   => 'duplicada',
                'vencimento'  => $vencimento,
                'proxima'     => $proxima,
                'competencia' => $competencia,
                'mensagem'    => 'competencia ja faturada por outra execucao',
            ];
        }
        throw $e;
    }
}

/**
 * Processa UMA recorrencia: classifica as competencias ate o limite de
 * emissao, aplica a politica de atraso e emite o que estiver pendente.
 *
 * @param array $ctx ['execucao_id'=>..., 'hoje'=>'Y-m-d', 'excluidas'=>[],
 *                    'gerar_pagamento'=>callable|null, 'stats'=>array ref]
 */
function motorProcessarRecorrencia($pdo, array $rec, array &$ctx) {
    $recId = (int)$rec['id'];
    $hoje = $ctx['hoje'];

    $logBase = [
        'execucao_id'        => $ctx['execucao_id'],
        'admin_id'           => $rec['admin_id'] ?? null,
        'fatura_recorrente_id'=> $recId,
        'cliente_id'         => $rec['cliente_id'] ?? null,
        'frequencia'         => $rec['frequencia'] ?? null,
    ];

    $frequencia = recorrenciaNormalizar($rec['frequencia'] ?? '');
    $ancora = recorrenciaAncoraDoRegistro($rec);

    // ---- 1. Pode gerar? (status, ativo, data_fim, cliente bloqueado) ----
    $cliente = null;
    if (!empty($rec['cliente_id'])) {
        try {
            $s = $pdo->prepare("SELECT id, ativo, nome_razao, email, email2, celular, telefone, cpf_cnpj FROM clientes WHERE id = ?");
            $s->execute([(int)$rec['cliente_id']]);
            $cliente = $s->fetch() ?: null;
        } catch (Throwable $e) {}
    }
    if (!$cliente) {
        $ctx['stats']['ignoradas']++;
        motorLog($pdo, $ctx['execucao_id'], $logBase + ['resultado' => 'ignorada', 'mensagem' => 'cliente inexistente']);
        return;
    }

    $pode = recorrenciaPodeGerar($rec, $cliente);
    if (!$pode['ok']) {
        $ctx['stats']['ignoradas']++;
        motorLog($pdo, $ctx['execucao_id'], $logBase + ['resultado' => 'ignorada', 'mensagem' => $pode['motivo']]);
        return;
    }

    if (recorrenciaSemCiclo($frequencia)) {
        $ctx['stats']['ignoradas']++;
        motorLog($pdo, $ctx['execucao_id'], $logBase + ['resultado' => 'ignorada', 'mensagem' => "frequencia '{$frequencia}' nao tem ciclo"]);
        return;
    }

    // ---- 2. Ate quando vale emitir (antecedencia e separada da periodicidade) ----
    $antecedencia = (int)($rec['antecedencia_emissao'] ?? 0);
    $limite = $hoje;
    if ($antecedencia > 0) {
        $limite = recorrenciaData($hoje)->add(new DateInterval('P' . $antecedencia . 'D'))->format('Y-m-d');
    }

    $atual = motorProximaCompetencia($pdo, $rec);
    if ($atual === null) {
        $ctx['stats']['ignoradas']++;
        motorLog($pdo, $ctx['execucao_id'], $logBase + ['resultado' => 'ignorada', 'mensagem' => 'sem competencia a processar']);
        return;
    }

    if ($atual > $limite) {
        // Nada vencido ainda: o cron so consultou. Nao grava log de decisao para
        // nao encher a tabela todo minuto.
        //
        // Persiste a competencia derivada quando ela ainda nao estava no
        // registro. Sem isso, uma recorrencia antiga (migrada, sem
        // data_proxima_fatura) rederivaria do zero a cada minuto, para sempre.
        if (empty($rec['data_proxima_fatura'])) {
            motorAvancarProxima($pdo, $recId, $atual);
        }
        return;
    }

    // ---- 3. Percorre as competencias ate o limite ----
    $existentes = motorCompetenciasExistentes($pdo, $recId);
    $emitir = [];
    $guard = 0;

    while ($atual !== null && $atual <= $limite && $guard++ < MOTOR_MAX_CICLOS_RUN) {
        $prox = proximoVencimentoRecorrencia($frequencia, $atual, $ancora);

        if (cicloFaturaExcluida($ctx['excluidas'], $recId, $atual)) {
            $ctx['stats']['ignoradas']++;
            motorLog($pdo, $ctx['execucao_id'], $logBase + [
                'resultado' => 'excluida', 'competencia' => $atual,
                'proxima_competencia' => $prox,
                'mensagem' => 'ciclo excluido no painel; nao regerado',
            ]);
            motorAvancarProxima($pdo, $recId, $prox);
            $atual = $prox;
            continue;
        }

        if (isset($existentes[$atual])) {
            // Ja emitida (por esta execucao ou por outra): apenas avanca.
            motorAvancarProxima($pdo, $recId, $prox);
            $atual = $prox;
            continue;
        }

        $emitir[] = $atual;
        $atual = $prox;
    }

    if (empty($emitir)) return;

    // ---- 4. Politica de atraso ----
    $politica = strtolower(trim((string)($rec['politica_atraso'] ?? 'todas')));
    if ($politica === 'mais_recente' && count($emitir) > 1) {
        $descartadas = array_slice($emitir, 0, -1);
        $emitir = [end($emitir)];
        foreach ($descartadas as $perdida) {
            $ctx['stats']['ignoradas']++;
            motorLog($pdo, $ctx['execucao_id'], $logBase + [
                'resultado' => 'descartada', 'competencia' => $perdida,
                'mensagem' => "politica 'mais_recente': competencia descartada, emitindo apenas a mais recente",
            ]);
        }
    }

    // ---- 5. Emite ----
    foreach ($emitir as $competencia) {
        try {
            $r = motorEmitirCompetencia($pdo, $rec, $competencia, $ctx);

            if ($r['resultado'] === 'emitida') {
                $ctx['stats']['emitidas']++;
                motorLog($pdo, $ctx['execucao_id'], $logBase + [
                    'resultado' => 'emitida',
                    'competencia' => $competencia,
                    'data_vencimento' => $r['vencimento'] ?? null,
                    'fatura_id' => $r['fatura_id'],
                    'numero' => $r['numero'],
                    'valor' => $r['valor'],
                    'proxima_competencia' => $r['proxima'],
                    'mensagem' => sprintf(
                        'Recorrencia %s. Fatura %s criada (venc. %s). Proxima: %s.',
                        strtoupper($frequencia), $r['numero'],
                        date('d/m/Y', strtotime($r['vencimento'] ?? $competencia)),
                        $r['proxima'] ? date('d/m/Y', strtotime($r['proxima'])) : 'nenhuma'
                    ),
                ]);

                // Pagamento e uma etapa separada: falhar no gateway nao
                // desfaz a fatura, que ja esta registrada e entra na regua.
                if (!empty($ctx['gerar_pagamento']) && is_callable($ctx['gerar_pagamento'])) {
                    try {
                        call_user_func($ctx['gerar_pagamento'], $pdo, $r['fatura_id'], $rec, $cliente);
                    } catch (Throwable $ePag) {
                        motorLog($pdo, $ctx['execucao_id'], $logBase + [
                            'resultado' => 'pagamento_erro',
                            'competencia' => $competencia,
                            'fatura_id' => $r['fatura_id'],
                            'numero' => $r['numero'],
                            'mensagem' => 'fatura criada, mas o pagamento nao foi gerado: ' . $ePag->getMessage(),
                        ]);
                    }
                }
                continue;
            }

            if ($r['resultado'] === 'duplicada') {
                $ctx['stats']['ignoradas']++;
                motorLog($pdo, $ctx['execucao_id'], $logBase + [
                    'resultado' => 'duplicada', 'competencia' => $competencia,
                    'proxima_competencia' => $r['proxima'],
                    'mensagem' => $r['mensagem'] ?? 'competencia ja faturada',
                ]);
                continue;
            }

            if ($r['resultado'] === 'limite' || $r['resultado'] === 'encerrada') {
                $ctx['stats']['ignoradas']++;
                motorLog($pdo, $ctx['execucao_id'], $logBase + [
                    'resultado' => $r['resultado'], 'competencia' => $competencia,
                    'mensagem' => $r['mensagem'],
                ]);
                break;
            }
        } catch (Throwable $e) {
            // Isolamento por competencia: um erro aqui nao pode derrubar o
            // processamento dos outros clientes nem travar esta recorrencia.
            $ctx['stats']['erros']++;
            motorRegistrarErro($pdo, $recId, $e->getMessage());
            motorLog($pdo, $ctx['execucao_id'], $logBase + [
                'resultado' => 'erro',
                'competencia' => $competencia,
                'mensagem' => get_class($e) . ': ' . $e->getMessage(),
            ]);
            break;
        }
    }
}

/**
 * Ponto de entrada do motor.
 *
 * @param array $opcoes ['origem'=>'cron', 'gerar_pagamento'=>callable|null,
 *                      'hoje'=>'Y-m-d', 'forcar_lock'=>bool]
 * @return array resumo da execucao
 */
function motorProcessar($pdo, array $opcoes = []) {
    $hoje = !empty($opcoes['hoje']) ? substr((string)$opcoes['hoje'], 0, 10) : date('Y-m-d');
    $origem = $opcoes['origem'] ?? 'cron';

    $resumo = ['execucao_id' => null, 'processadas' => 0, 'emitidas' => 0,
               'ignoradas' => 0, 'erros' => 0, 'resultado' => 'ok', 'lock' => true];

    $pegou = motorAdquirirLock($pdo);
    $resumo['lock'] = $pegou;
    if (!$pegou) {
        // Ja existe execucao em andamento. Sair e o comportamento correto:
        // mexer nos mesmos ciclos ao mesmo tempo so produziria concorrencia.
        $resumo['resultado'] = 'ignorado_lock';
        $resumo['execucao_id'] = motorGerarExecucaoId();
        return $resumo;
    }

    try {
        $execucaoId = motorIniciarExecucao($pdo, $origem);
        $resumo['execucao_id'] = $execucaoId;

        $ctx = [
            'execucao_id' => $execucaoId,
            'hoje'        => $hoje,
            'excluidas'   => carregarFaturasExcluidas($pdo),
            'gerar_pagamento' => $opcoes['gerar_pagamento'] ?? null,
            'stats'       => ['processadas' => 0, 'emitidas' => 0, 'ignoradas' => 0, 'erros' => 0],
        ];

        try {
            $stmt = $pdo->prepare("SELECT fr.*, c.ativo AS cliente_ativo
                FROM faturas_recorrentes fr
                JOIN clientes c ON c.id = fr.cliente_id
                WHERE fr.ativo = 1
                  AND fr.frequencia IS NOT NULL AND fr.frequencia <> ''
                  AND fr.frequencia <> 'unica'");
            $stmt->execute();
            $lista = $stmt->fetchAll();
        } catch (Throwable $e) {
            motorFinalizarExecucao($pdo, $execucaoId, $ctx['stats'], 'erro', 'falha ao listar recorrencias: ' . $e->getMessage());
            $resumo['resultado'] = 'erro';
            $resumo['erros'] = 1;
            return $resumo;
        }

        foreach ($lista as $rec) {
            $ctx['stats']['processadas']++;
            try {
                motorProcessarRecorrencia($pdo, $rec, $ctx);
            } catch (Throwable $e) {
                // Rede de seguranca: motorProcessarRecorrencia ja isola por
                // competencia, mas se algo escapar antes disso o processo
                // continua para a proxima recorrencia.
                $ctx['stats']['erros']++;
                motorRegistrarErro($pdo, (int)$rec['id'], $e->getMessage());
                motorLog($pdo, $execucaoId, [
                    'execucao_id' => $execucaoId,
                    'admin_id' => $rec['admin_id'] ?? null,
                    'fatura_recorrente_id' => (int)$rec['id'],
                    'cliente_id' => $rec['cliente_id'] ?? null,
                    'frequencia' => $rec['frequencia'] ?? null,
                    'resultado' => 'erro',
                    'mensagem' => get_class($e) . ': ' . $e->getMessage(),
                ]);
            }
        }

        $resultado = $ctx['stats']['erros'] > 0 ? 'parcial' : 'ok';
        motorFinalizarExecucao($pdo, $execucaoId, $ctx['stats'], $resultado);

        $resumo['processadas'] = $ctx['stats']['processadas'];
        $resumo['emitidas']    = $ctx['stats']['emitidas'];
        $resumo['ignoradas']   = $ctx['stats']['ignoradas'];
        $resumo['erros']       = $ctx['stats']['erros'];
        $resumo['resultado']   = $resultado;

        return $resumo;
    } finally {
        motorLiberarLock($pdo);
    }
}