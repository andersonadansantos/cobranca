<?php
// =====================================================
// OPT-OUT PUBLICO DO MARKETING
// =====================================================
// Link assinado (HMAC) enviado no rodape dos e-mails. Permite que o contato
// saia da lista sem login. Tambem cancela envios pendentes daquele contato.

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/marketing_helpers.php';

$id = (int) ($_GET['id'] ?? 0);
$token = (string) ($_GET['token'] ?? '');

header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$ok = $id > 0 && mkt_optout_verificar($id, $token);
$mensagem = 'Link invalido ou expirado.';
$detalhe = 'Se voce nao solicitou a remocao, ignore esta pagina.';
$cor = '#c62828';
$icone = '!';

if ($ok) {
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("UPDATE marketing_contatos SET opt_out = 1, status = 'bloqueado' WHERE id = ?");
        $stmt->execute([$id]);
        if ($stmt->rowCount() > 0) {
            $pdo->prepare("UPDATE marketing_envios SET status='cancelado', erro='Opt-out solicitado pelo contato' WHERE contato_id = ? AND status IN ('aguardando','falha')")
                ->execute([$id]);
            $mensagem = 'Inscricao removida com sucesso.';
            $detalhe = 'Voce nao recebera mais comunicacoes comerciais deste remetente.';
            $cor = '#128a53';
            $icone = 'OK';
        } else {
            $mensagem = 'Contato nao encontrado.';
        }
    } catch (Throwable $e) {
        $mensagem = 'Nao foi possivel concluir a solicitacao agora.';
        $detalhe = 'Tente novamente mais tarde.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Opt-out - Marketing</title>
    <style>
        body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f4f6f8;font-family:Arial,Helvetica,sans-serif;color:#23272e}
        .card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;box-shadow:0 8px 28px rgba(0,0,0,.06);max-width:460px;width:calc(100% - 32px);padding:32px 28px;text-align:center}
        .ico{width:54px;height:54px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;color:#fff;font-weight:700;font-size:1.1rem;background:<?= $cor ?>}
        h1{font-size:1.15rem;margin:0 0 8px}
        p{font-size:.9rem;line-height:1.55;color:#6b7280;margin:0}
        .foot{margin-top:20px;font-size:.72rem;color:#9ca3af}
    </style>
</head>
<body>
    <div class="card">
        <div class="ico"><?= htmlspecialchars($icone) ?></div>
        <h1><?= htmlspecialchars($mensagem) ?></h1>
        <p><?= htmlspecialchars($detalhe) ?></p>
        <div class="foot">Central de Faturas - Controle de comunicacoes (LGPD)</div>
    </div>
</body>
</html>
