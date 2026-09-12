<?php
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../includes/auth.php';
requireUser();
require_once __DIR__ . '/../config/database.php';

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$reciboId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT r.fatura_id, r.id, r.numero, r.html_gerado, f.numero AS fatura_numero
    FROM recibos r
    JOIN faturas f ON r.fatura_id = f.id
    WHERE r.id = ? AND f.cliente_id = ?
");
$stmt->execute([$reciboId, $userId]);
$recibo = $stmt->fetch();

if (!$recibo) {
    http_response_code(404);
    die('Recibo não encontrado.');
}

// Garante que a logo do recibo seja a da empresa responsável pela fatura.
if (!function_exists('garantirReciboFatura')) {
    require_once __DIR__ . '/../config/recibo_pdf.php';
}
$refreshed = garantirReciboFatura(['id' => (int)$recibo['fatura_id']]);
if ($refreshed && !empty($refreshed['html_gerado'])) {
    $recibo['html_gerado'] = $refreshed['html_gerado'];
}

$nomeSistema = function_exists('getNomeSistema') ? getNomeSistema() : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo <?= htmlspecialchars($recibo['numero']) ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', 'Roboto', Arial, sans-serif;
            background: #eef0f4;
            color: #1a1a2e;
            -webkit-font-smoothing: antialiased;
        }
        .recibo-topbar {
            position: sticky;
            top: 0;
            z-index: 20;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            background: #6C5CE7;
            color: #fff;
        }
        .recibo-topbar a {
            color: #fff;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .recibo-topbar .recibo-title {
            flex: 1;
            font-size: 0.92rem;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .recibo-print-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border: none;
            border-radius: 8px;
            background: rgba(255,255,255,0.2);
            color: #fff;
            font-size: 0.82rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
        }
        .recibo-wrap {
            padding: 16px;
        }
        .recibo-sheet {
            width: 794px;
            max-width: 100%;
            min-height: 1123px;
            margin: 0 auto;
            background: #fff;
            box-shadow: 0 2px 12px rgba(0,0,0,0.12);
            border-radius: 4px;
            overflow: hidden;
        }
        .recibo-note {
            max-width: 794px;
            margin: 12px auto 0;
            text-align: center;
            font-size: 0.7rem;
            color: #64748b;
        }
        @media print {
            .recibo-topbar { display: none !important; }
            body { background: #fff; }
            .recibo-wrap { padding: 0; }
            .recibo-sheet { box-shadow: none; border-radius: 0; min-height: auto; width: 100%; }
            .recibo-note { display: none !important; }
            @page { size: A4; margin: 15mm; }
        }
    </style>
</head>
<body>
    <div class="recibo-topbar">
        <a href="index.php"><i class="fas fa-arrow-left"></i> Faturas</a>
        <div class="recibo-title"><i class="fas fa-file-invoice me-1"></i>Recibo <?= htmlspecialchars($recibo['numero']) ?></div>
        <button type="button" class="recibo-print-btn" onclick="window.print()"><i class="fas fa-print"></i> Baixar PDF</button>
    </div>

    <div class="recibo-wrap">
        <div class="recibo-sheet">
            <?= $recibo['html_gerado'] ?>
        </div>
        <div class="recibo-note">
            Fatura <?= htmlspecialchars($recibo['fatura_numero']) ?> &middot; Para salvar o PDF, clique em "Baixar PDF" e escolha "Salvar como PDF".
        </div>
    </div>
</body>
</html>