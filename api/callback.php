<?php
// =====================================================
// CALLBACK - Retorno após pagamento
// Baixa automática no status da fatura
// =====================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/mercadopago.php';
require_once __DIR__ . '/../config/email_helpers.php';

$paymentId = $_GET['payment_id'] ?? '';
$status = $_GET['status'] ?? '';

if ($paymentId && $status === 'approved') {
    $pdo = getConnection();
    if ($pdo) {
        $pagamento = consultarPagamento($paymentId);
        $mpStatus = $pagamento['status'] ?? '';

        if ($mpStatus === 'approved') {
            $stmt = $pdo->prepare("SELECT * FROM faturas WHERE mp_payment_id = ? AND status != 'pago'");
            $stmt->execute([$paymentId]);
            $fatura = $stmt->fetch();

            if ($fatura) {
                $stmt = $pdo->prepare("UPDATE faturas SET status = 'pago', data_pagamento = CURDATE() WHERE id = ?");
                $stmt->execute([$fatura['id']]);

                $valorPago = $pagamento['transaction_amount'] ?? $fatura['valor_final'];
                $tipoPgto = $pagamento['payment_type_id'] ?? '';
                $stmt = $pdo->prepare("INSERT INTO pagamentos_log (fatura_id, mp_payment_id, mp_status, mp_status_detail, valor_pago, tipo_pagamento, dados_raw) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$fatura['id'], $paymentId, 'approved', 'Pagamento via callback', $valorPago, $tipoPgto, json_encode($pagamento)]);

                $stmtCli = $pdo->prepare("SELECT c.nome_razao, c.email FROM clientes c WHERE c.id = ?");
                $stmtCli->execute([$fatura['cliente_id']]);
                $cli = $stmtCli->fetch();
                if ($cli) {
                    $fatura['nome_razao'] = $cli['nome_razao'];
                    $fatura['email'] = $cli['email'];
                    $fatura['data_pagamento'] = date('Y-m-d');
                    enviarEmailPagamento($fatura);
                }
            }
        } elseif ($status === 'approved') {
            $stmt = $pdo->prepare("SELECT * FROM faturas WHERE mp_payment_id = ? AND status != 'pago'");
            $stmt->execute([$paymentId]);
            $fatura = $stmt->fetch();

            if ($fatura) {
                $stmt = $pdo->prepare("UPDATE faturas SET status = 'pago', data_pagamento = CURDATE() WHERE id = ?");
                $stmt->execute([$fatura['id']]);

                $stmt = $pdo->prepare("INSERT INTO pagamentos_log (fatura_id, mp_payment_id, mp_status, mp_status_detail, valor_pago, tipo_pagamento, dados_raw) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$fatura['id'], $paymentId, 'approved', 'Pagamento via callback', $fatura['valor_final'], 'pending_review', json_encode($_GET)]);

                $stmtCli = $pdo->prepare("SELECT c.nome_razao, c.email FROM clientes c WHERE c.id = ?");
                $stmtCli->execute([$fatura['cliente_id']]);
                $cli = $stmtCli->fetch();
                if ($cli) {
                    $fatura['nome_razao'] = $cli['nome_razao'];
                    $fatura['email'] = $cli['email'];
                    $fatura['data_pagamento'] = date('Y-m-d');
                    enviarEmailPagamento($fatura);
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento - <?= $status === 'approved' ? 'Aprovado' : 'Processando' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow text-center">
                    <div class="card-body p-5">
                        <?php if ($status === 'approved'): ?>
                            <div class="text-success mb-3">
                                <i class="fas fa-check-circle fa-5x"></i>
                            </div>
                            <h3 class="text-success">Pagamento Aprovado!</h3>
                            <p class="text-muted">Seu pagamento foi processado com sucesso.</p>
                            <?php if ($paymentId): ?>
                                <p class="small text-muted">ID do Pagamento: <?= htmlspecialchars($paymentId) ?></p>
                            <?php endif; ?>
                        <?php elseif ($status === 'pending'): ?>
                            <div class="text-warning mb-3">
                                <i class="fas fa-clock fa-5x"></i>
                            </div>
                            <h3 class="text-warning">Pagamento Pendente</h3>
                            <p class="text-muted">Seu pagamento está sendo processado.</p>
                        <?php else: ?>
                            <div class="text-info mb-3">
                                <i class="fas fa-info-circle fa-5x"></i>
                            </div>
                            <h3>Processando Pagamento</h3>
                            <p class="text-muted">Aguarde a confirmação do pagamento.</p>
                        <?php endif; ?>
                        
                        <a href="/cobranca/usuario/index.php" class="btn btn-primary mt-3">
                            <i class="fas fa-home me-1"></i> Voltar ao Painel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
