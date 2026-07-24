<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';

$pdo = getConnection();
$tipo = $_GET['tipo'] ?? '';

if ($tipo === 'clientes') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=clientes_' . date('Y-m-d_H-i') . '.csv');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, ['ID', 'Nome/Razão', 'CPF/CNPJ', 'Tipo', 'E-mail', 'Telefone', 'Celular', 'Cidade', 'UF', 'Status', 'Data Cadastro'], ';');
    $stmt = $pdo->query("SELECT id, nome_razao, cpf_cnpj, tipo_pessoa, email, telefone, celular, cidade, estado, ativo, criado_em FROM clientes ORDER BY nome_razao");
    while ($r = $stmt->fetch()) {
        fputcsv($output, [
            $r['id'], $r['nome_razao'], $r['cpf_cnpj'], $r['tipo_pessoa'],
            $r['email'], $r['telefone'], $r['celular'], $r['cidade'], $r['estado'],
            $r['ativo'] ? 'Ativo' : 'Inativo',
            date('d/m/Y', strtotime($r['criado_em']))
        ], ';');
    }
    fclose($output);
    exit;
}

if ($tipo === 'faturas') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=faturas_' . date('Y-m-d_H-i') . '.csv');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, ['ID', 'Número', 'Cliente', 'CPF/CNPJ', 'Descrição', 'Valor', 'Desconto', 'Multa', 'Valor Final', 'Data Emissão', 'Data Vencimento', 'Data Pagamento', 'Status'], ';');
    $stmt = $pdo->query("
        SELECT f.*, c.nome_razao, c.cpf_cnpj
        FROM faturas f JOIN clientes c ON f.cliente_id = c.id
        ORDER BY f.criado_em DESC
    ");
    while ($r = $stmt->fetch()) {
        fputcsv($output, [
            $r['id'], $r['numero'], $r['nome_razao'], $r['cpf_cnpj'], $r['descricao'],
            $r['valor'], $r['desconto'], $r['multa'], $r['valor_final'],
            $r['data_emissao'], $r['data_vencimento'], $r['data_pagamento'] ?? '',
            $r['status']
        ], ';');
    }
    fclose($output);
    exit;
}

header('Location: cadastro.php');