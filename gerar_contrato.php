<?php
// =====================================================
// SCRIPT: Gerar contrato para um cliente
// Execute via browser ou linha de comando
// Exemplo: php gerar_contrato.php [cliente_id]
// =====================================================

require_once __DIR__ . '/config/database.php';

$clienteId = isset($argv[1]) ? intval($argv[1]) : intval($_GET['id'] ?? 0);

if ($clienteId <= 0) {
    echo "Uso: php gerar_contrato.php [cliente_id]\n";
    echo "Ou acesse: gerar_contrato.php?id=1\n";
    exit;
}

$pdo = getConnection();
if (!$pdo) {
    echo "Erro ao conectar ao banco de dados.\n";
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt->execute([$clienteId]);
$cliente = $stmt->fetch();

if (!$cliente) {
    echo "Cliente não encontrado.\n";
    exit;
}

// Verificar se já tem contrato
$stmt = $pdo->prepare("SELECT id FROM contratos WHERE cliente_id = ?");
$stmt->execute([$clienteId]);
if ($stmt->fetch()) {
    echo "Este cliente já possui contrato.\n";
    exit;
}

// Gerar número do contrato
$ano = date('Y');
$seq = str_pad($clienteId, 4, '0', STR_PAD_LEFT);
$numero = "CONT-{$ano}-{$seq}";

$conteudo = "CLÁUSULA PRIMEIRA - DO OBJETO
O presente contrato tem por objeto a prestação de serviços de cobrança e gestão de faturamento, conforme condições estabelecidas neste instrumento.

CLÁUSULA SEGUNDA - DAS OBRIGAÇÕES DA CONTRATADA
A CONTRATADA se obriga a:
a) Prestar os serviços de cobrança e gestão de faturas conforme solicitado;
b) Emitir as faturas no prazo estipulado;
c) Disponibilizar plataforma online para acompanhamento;
d) Manter sigilo sobre todas as informações do CONTRATANTE.

CLÁUSULA TERCEIRA - DAS OBRIGAÇÕES DO CONTRATANTE
O CONTRATANTE se obriga a:
a) Efetuar os pagamentos nas datas de vencimento estipuladas;
b) Manter seus dados cadastrais atualizados;
c) Comunicar qualquer alteração que possa afetar a prestação dos serviços.

CLÁUSULA QUARTA - DOS PAGAMENTOS
Os pagamentos deverão ser realizados até a data de vencimento indicada em cada fatura, mediante PIX ou outro meio de pagamento disponível na plataforma.

CLÁUSULA QUINTA - DA VIGÊNCIA
Este contrato vigorará pelo período de 12 (doze) meses, contados a partir de sua assinatura, sendo renovado automaticamente por períodos iguais, salvo manifestação contrária de qualquer das partes com antecedência mínima de 30 (trinta) dias.

CLÁUSULA SEXTA - DA RESCISÃO
O presente contrato poderá ser rescindido por qualquer das partes, mediante notificação por escrito com antecedência mínima de 30 (trinta) dias.

CLÁUSULA SÉTIMA - DO FORO
Fica eleito o foro da Comarca de São Paulo/SP para dirimir quaisquer questões oriundas do presente contrato.";

$stmt = $pdo->prepare("INSERT INTO contratos (cliente_id, numero, titulo, conteudo, data_inicio, valor_mensal, status) VALUES (?, ?, ?, ?, CURDATE(), ?, 'ativo')");

// Tentar pegar valor de fatura recorrente do cliente
$stmtVal = $pdo->prepare("SELECT valor FROM faturas_recorrentes WHERE cliente_id = ? AND ativo = 1 LIMIT 1");
$stmtVal->execute([$clienteId]);
$val = $stmtVal->fetch();
$valorMensal = $val ? $val['valor'] : null;

$stmt->execute([$clienteId, $numero, 'Contrato de Prestação de Serviços', $conteudo, $valorMensal]);

echo "Contrato {$numero} criado com sucesso para o cliente {$cliente['nome_razao']}!\n";
echo "Acesse: /cobranca/usuario/contrato.php\n";
