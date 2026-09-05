<?php
// =====================================================
// CONFIGURAÇÃO DO BANCO DE DADOS
// Criação automática do banco e tabelas
// =====================================================

define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'cobranca');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

function getConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `" . DB_NAME . "`");

        $tabelaTeste = $pdo->query("SHOW TABLES LIKE 'configuracoes'")->fetch();
        if (!$tabelaTeste) {
            criarTabelas($pdo);
        }

        try { $pdo->exec("ALTER TABLE `faturas` ADD COLUMN `boleto_url` VARCHAR(500) DEFAULT NULL AFTER `link_pagamento`"); } catch (PDOException $e) {}
        try { $pdo->exec("ALTER TABLE `faturas` ADD COLUMN `ultimo_envio` DATE DEFAULT NULL AFTER `observacoes`"); } catch (PDOException $e) {}
        try { $pdo->exec("ALTER TABLE `faturas` ADD COLUMN `ultimo_envio_tipo` VARCHAR(30) DEFAULT NULL AFTER `ultimo_envio`"); } catch (PDOException $e) {}
        try { $pdo->exec("ALTER TABLE `faturas` ADD COLUMN `inter_codigo_solicitacao` VARCHAR(100) DEFAULT NULL AFTER `mp_payment_id`"); } catch (PDOException $e) {}
        try { $pdo->exec("ALTER TABLE `faturas` ADD COLUMN `acesso_token` VARCHAR(64) DEFAULT NULL"); } catch (PDOException $e) {}
        try { $pdo->exec("UPDATE `faturas` SET `acesso_token` = SHA2(CONCAT(UUID(), RAND()), 256) WHERE `acesso_token` IS NULL"); } catch (PDOException $e) {}
        try { $pdo->exec("ALTER TABLE `faturas` ADD COLUMN `api_pagamento` VARCHAR(30) DEFAULT NULL AFTER `acesso_token`"); } catch (PDOException $e) {}

        try { $pdo->exec("ALTER TABLE `administradores` ADD COLUMN `avatar` VARCHAR(255) DEFAULT NULL AFTER `email`"); } catch (PDOException $e) {}
        try { $pdo->exec("ALTER TABLE `clientes` ADD COLUMN `avatar` VARCHAR(255) DEFAULT NULL AFTER `estado`"); } catch (PDOException $e) {}

        try { $pdo->exec("ALTER TABLE `administradores` ADD COLUMN `google_id` VARCHAR(100) DEFAULT NULL AFTER `avatar`"); } catch (PDOException $e) {}
        try { $pdo->exec("ALTER TABLE `clientes` ADD COLUMN `google_id` VARCHAR(100) DEFAULT NULL AFTER `avatar`"); } catch (PDOException $e) {}

        try { $pdo->exec("ALTER TABLE `administradores` ADD COLUMN `token_recuperacao` VARCHAR(64) DEFAULT NULL AFTER `google_id`"); } catch (PDOException $e) {}
        try { $pdo->exec("ALTER TABLE `administradores` ADD COLUMN `token_recuperacao_expira` DATETIME DEFAULT NULL AFTER `token_recuperacao`"); } catch (PDOException $e) {}
        try { $pdo->exec("ALTER TABLE `clientes` ADD COLUMN `token_recuperacao_expira` DATETIME DEFAULT NULL AFTER `token_recuperacao`"); } catch (PDOException $e) {}

        try { $pdo->exec("ALTER TABLE `clientes` ADD COLUMN `email2` VARCHAR(150) DEFAULT NULL AFTER `email`"); } catch (PDOException $e) {}

        try { $pdo->exec("ALTER TABLE `clientes` MODIFY COLUMN `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP"); } catch (PDOException $e) {}
        try { $pdo->exec("ALTER TABLE `clientes` MODIFY COLUMN `atualizado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"); } catch (PDOException $e) {}

        try { $pdo->exec("ALTER TABLE `faturas_recorrentes` ADD COLUMN `status` VARCHAR(20) DEFAULT 'ativa' AFTER `ativo`"); } catch (PDOException $e) {}
        try { $pdo->exec("ALTER TABLE `faturas_recorrentes` MODIFY COLUMN `frequencia` ENUM('unica', 'diaria', 'semanal', 'quinzenal', 'mensal', 'bimestral', 'trimestral', 'semestral', 'anual') NOT NULL DEFAULT 'mensal'"); } catch (PDOException $e) {}
        try { $pdo->exec("ALTER TABLE `faturas_recorrentes` MODIFY COLUMN `ativo` TINYINT(1) NOT NULL DEFAULT 1"); } catch (PDOException $e) {}
        try { $pdo->exec("ALTER TABLE `faturas_recorrentes` MODIFY COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'ativa'"); } catch (PDOException $e) {}
        try { $pdo->exec("UPDATE `faturas_recorrentes` SET `ativo` = 1, `status` = 'ativa' WHERE `ativo` IS NULL OR `status` IS NULL"); } catch (PDOException $e) {}

        // === MIGRAÇÃO: isolamento de dados por admin ===
        // Idempotente via marcador em configuracoes. Adiciona admin_id em
        // clientes/faturas/faturas_recorrentes, converte as chaves UNIQUE (CPF/CNPJ e
        // número da fatura) para serem por admin e, na primeira execução, limpa os dados
        // globais existentes para que cada admin comece com base vazia.
        try {
            $migrado = $pdo->query("SELECT COUNT(*) FROM configuracoes WHERE chave = 'isolamento_admin'")->fetchColumn();
            if ((int)$migrado === 0) {
                try { $pdo->exec("ALTER TABLE `clientes` ADD COLUMN `admin_id` INT DEFAULT NULL AFTER `id`"); } catch (PDOException $e) {}
                try { $pdo->exec("ALTER TABLE `faturas` ADD COLUMN `admin_id` INT DEFAULT NULL AFTER `id`"); } catch (PDOException $e) {}
                try { $pdo->exec("ALTER TABLE `faturas_recorrentes` ADD COLUMN `admin_id` INT DEFAULT NULL AFTER `id`"); } catch (PDOException $e) {}
                // UNIQUE de CPF/CNPJ do cliente passa a ser por admin
                try { $pdo->exec("ALTER TABLE `clientes` DROP INDEX `cpf_cnpj`"); } catch (PDOException $e) {}
                try { $pdo->exec("ALTER TABLE `clientes` DROP INDEX `idx_clientes_cpf_cnpj`"); } catch (PDOException $e) {}
                try { $pdo->exec("ALTER TABLE `clientes` ADD UNIQUE KEY `uq_admin_cpf_cnpj` (`admin_id`, `cpf_cnpj`)"); } catch (PDOException $e) {}
                // UNIQUE do número da fatura passa a ser por admin
                try { $pdo->exec("ALTER TABLE `faturas` DROP INDEX `numero`"); } catch (PDOException $e) {}
                try { $pdo->exec("ALTER TABLE `faturas` ADD UNIQUE KEY `uq_admin_numero` (`admin_id`, `numero`)"); } catch (PDOException $e) {}
                try { $pdo->exec("CREATE INDEX idx_clientes_admin ON `clientes`(`admin_id`)"); } catch (PDOException $e) {}
                try { $pdo->exec("CREATE INDEX idx_faturas_admin ON `faturas`(`admin_id`)"); } catch (PDOException $e) {}
                // começar do zero: limpa dados globais existentes
                try {
                    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
                    foreach (['pagamentos_log', 'contratos', 'faturas', 'faturas_recorrentes', 'clientes'] as $tbl) {
                        $existe = $pdo->query("SHOW TABLES LIKE '" . $tbl . "'")->fetch();
                        if ($existe) $pdo->exec("TRUNCATE TABLE `" . $tbl . "`");
                    }
                    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
                } catch (PDOException $e) {}
                try { $pdo->exec("INSERT INTO `configuracoes` (`chave`, `valor`) VALUES ('isolamento_admin', '1')"); } catch (PDOException $e) {}
            }
        } catch (PDOException $e) {}

        // === MIGRAÇÃO: multi-tenant (subdomínio por admin) ===
        // Idempotente via marcador. Adiciona o campo `subdominio` em administradores,
        // scoping `admin_id` em configuracoes e nas demais tabelas por admin, e adequa a
        // unicidade de `configuracoes` para (admin_id, chave).
        try {
            $tenant = $pdo->query("SELECT COUNT(*) FROM configuracoes WHERE chave = 'multitenant'")->fetchColumn();
            if ((int)$tenant === 0) {
                try { $pdo->exec("ALTER TABLE `administradores` ADD COLUMN `subdominio` VARCHAR(50) DEFAULT NULL AFTER `usuario`"); } catch (PDOException $e) {}
                try { $pdo->exec("ALTER TABLE `administradores` ADD UNIQUE KEY `uq_admin_subdominio` (`subdominio`)"); } catch (PDOException $e) {}
                try { $pdo->exec("ALTER TABLE `configuracoes` ADD COLUMN `admin_id` INT DEFAULT NULL AFTER `id`"); } catch (PDOException $e) {}
                try { $pdo->exec("ALTER TABLE `configuracoes` DROP INDEX `chave`"); } catch (PDOException $e) {}
                try { $pdo->exec("ALTER TABLE `configuracoes` ADD UNIQUE KEY `uq_admin_chave` (`admin_id`, `chave`)"); } catch (PDOException $e) {}
                try { $pdo->exec("CREATE INDEX idx_config_admin ON `configuracoes`(`admin_id`)"); } catch (PDOException $e) {}
                try { $pdo->exec("ALTER TABLE `livro_caixa_entradas` ADD COLUMN `admin_id` INT DEFAULT NULL AFTER `id`"); } catch (PDOException $e) {}
                try { $pdo->exec("ALTER TABLE `livro_caixa_saidas` ADD COLUMN `admin_id` INT DEFAULT NULL AFTER `id`"); } catch (PDOException $e) {}
                try { $pdo->exec("ALTER TABLE `livro_caixa_custos` ADD COLUMN `admin_id` INT DEFAULT NULL AFTER `id`"); } catch (PDOException $e) {}
                try { $pdo->exec("ALTER TABLE `contratos` ADD COLUMN `admin_id` INT DEFAULT NULL AFTER `id`"); } catch (PDOException $e) {}
                try { $pdo->exec("ALTER TABLE `pagamentos_log` ADD COLUMN `admin_id` INT DEFAULT NULL AFTER `id`"); } catch (PDOException $e) {}
                try { $pdo->exec("CREATE INDEX idx_lc_entradas_admin ON `livro_caixa_entradas`(`admin_id`)"); } catch (PDOException $e) {}
                try { $pdo->exec("CREATE INDEX idx_lc_saidas_admin ON `livro_caixa_saidas`(`admin_id`)"); } catch (PDOException $e) {}
                try { $pdo->exec("CREATE INDEX idx_lc_custos_admin ON `livro_caixa_custos`(`admin_id`)"); } catch (PDOException $e) {}
                try { $pdo->exec("CREATE INDEX idx_contratos_admin ON `contratos`(`admin_id`)"); } catch (PDOException $e) {}
                try { $pdo->exec("CREATE INDEX idx_pagamentos_admin ON `pagamentos_log`(`admin_id`)"); } catch (PDOException $e) {}
                try { $pdo->exec("INSERT INTO `configuracoes` (`chave`, `valor`) VALUES ('multitenant', '1')"); } catch (PDOException $e) {}
            }
        } catch (PDOException $e) {}

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `usuarios_admin` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `admin_id` INT DEFAULT NULL,
                `nome` VARCHAR(100) NOT NULL,
                `email` VARCHAR(100) NOT NULL,
                `usuario` VARCHAR(50) NOT NULL,
                `senha` VARCHAR(255) NOT NULL,
                `perfil` ENUM('admin','financeiro','atendimento') DEFAULT 'atendimento',
                `ativo` TINYINT(1) DEFAULT 1,
                `avatar` VARCHAR(255) DEFAULT NULL,
                `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `ultimo_login` TIMESTAMP NULL
            ) ENGINE=InnoDB");
        } catch (PDOException $e) {}

        // === MIGRAÇÃO: isolamento de usuários_admin por admin ===
        // Idempotente via marcador. Adiciona `admin_id` e converte a unicidade de
        // `email`/`usuario` para ser por admin (composta com admin_id).
        try {
            $ua = $pdo->query("SELECT COUNT(*) FROM configuracoes WHERE chave = 'usuarios_admin_isolado'")->fetchColumn();
            if ((int)$ua === 0) {
                try { $pdo->exec("ALTER TABLE `usuarios_admin` ADD COLUMN `admin_id` INT DEFAULT NULL AFTER `id`"); } catch (PDOException $e) {}
                try { $pdo->exec("ALTER TABLE `usuarios_admin` DROP INDEX `email`"); } catch (PDOException $e) {}
                try { $pdo->exec("ALTER TABLE `usuarios_admin` DROP INDEX `usuario`"); } catch (PDOException $e) {}
                try { $pdo->exec("ALTER TABLE `usuarios_admin` ADD UNIQUE KEY `uq_ua_admin_email` (`admin_id`, `email`)"); } catch (PDOException $e) {}
                try { $pdo->exec("ALTER TABLE `usuarios_admin` ADD UNIQUE KEY `uq_ua_admin_usuario` (`admin_id`, `usuario`)"); } catch (PDOException $e) {}
                try { $pdo->exec("CREATE INDEX idx_ua_admin ON `usuarios_admin`(`admin_id`)"); } catch (PDOException $e) {}
                try { $pdo->exec("INSERT INTO `configuracoes` (`chave`, `valor`) VALUES ('usuarios_admin_isolado', '1')"); } catch (PDOException $e) {}
            }
        } catch (PDOException $e) {}

        try { $pdo->exec("ALTER TABLE `administradores` ADD COLUMN `razao_social` VARCHAR(200) DEFAULT NULL AFTER `avatar`"); } catch (PDOException $e) {}
        try { $pdo->exec("ALTER TABLE `administradores` ADD COLUMN `nome_fantasia` VARCHAR(200) DEFAULT NULL AFTER `razao_social`"); } catch (PDOException $e) {}
        try { $pdo->exec("ALTER TABLE `administradores` ADD COLUMN `cnpj` VARCHAR(20) DEFAULT NULL AFTER `nome_fantasia`"); } catch (PDOException $e) {}
        try { $pdo->exec("ALTER TABLE `administradores` ADD COLUMN `cpf` VARCHAR(20) DEFAULT NULL AFTER `cnpj`"); } catch (PDOException $e) {}
        try { $pdo->exec("ALTER TABLE `administradores` ADD COLUMN `inscricao_estadual` VARCHAR(30) DEFAULT NULL AFTER `cpf`"); } catch (PDOException $e) {}
        try { $pdo->exec("ALTER TABLE `administradores` ADD COLUMN `inscricao_municipal` VARCHAR(30) DEFAULT NULL AFTER `inscricao_estadual`"); } catch (PDOException $e) {}
        try { $pdo->exec("ALTER TABLE `administradores` ADD COLUMN `telefone_comercial` VARCHAR(20) DEFAULT NULL AFTER `inscricao_municipal`"); } catch (PDOException $e) {}
        try { $pdo->exec("ALTER TABLE `administradores` ADD COLUMN `email_comercial` VARCHAR(100) DEFAULT NULL AFTER `telefone_comercial`"); } catch (PDOException $e) {}
        try { $pdo->exec("ALTER TABLE `administradores` ADD COLUMN `cep` VARCHAR(10) DEFAULT NULL AFTER `email_comercial`"); } catch (PDOException $e) {}
        try { $pdo->exec("ALTER TABLE `administradores` ADD COLUMN `logradouro` VARCHAR(200) DEFAULT NULL AFTER `cep`"); } catch (PDOException $e) {}
        try { $pdo->exec("ALTER TABLE `administradores` ADD COLUMN `numero` VARCHAR(10) DEFAULT NULL AFTER `logradouro`"); } catch (PDOException $e) {}
        try { $pdo->exec("ALTER TABLE `administradores` ADD COLUMN `complemento` VARCHAR(100) DEFAULT NULL AFTER `numero`"); } catch (PDOException $e) {}
        try { $pdo->exec("ALTER TABLE `administradores` ADD COLUMN `bairro` VARCHAR(100) DEFAULT NULL AFTER `complemento`"); } catch (PDOException $e) {}
        try { $pdo->exec("ALTER TABLE `administradores` ADD COLUMN `cidade` VARCHAR(100) DEFAULT NULL AFTER `bairro`"); } catch (PDOException $e) {}
        try { $pdo->exec("ALTER TABLE `administradores` ADD COLUMN `estado` CHAR(2) DEFAULT NULL AFTER `cidade`"); } catch (PDOException $e) {}

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `login_attempts` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `contexto` VARCHAR(20) NOT NULL,
                `identificador` VARCHAR(100) NOT NULL,
                `ip` VARCHAR(45) NOT NULL,
                `tentativas` INT DEFAULT 1,
                `bloqueado_ate` DATETIME DEFAULT NULL,
                `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `atualizado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_contexto_id` (`contexto`, `identificador`),
                INDEX `idx_ip` (`ip`)
            ) ENGINE=InnoDB");
        } catch (PDOException $e) {}

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `superadmin` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `usuario` VARCHAR(50) NOT NULL UNIQUE,
                `senha` VARCHAR(255) NOT NULL,
                `nome` VARCHAR(100) NOT NULL,
                `email` VARCHAR(150),
                `avatar` VARCHAR(255),
                `ultimo_login` TIMESTAMP NULL,
                `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB");
        } catch (PDOException $e) {}

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `admin_evolution` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `admin_id` INT NOT NULL,
                `url_api` VARCHAR(255) NOT NULL DEFAULT '',
                `api_key` VARCHAR(255) NOT NULL DEFAULT '',
                `instance` VARCHAR(100) NOT NULL DEFAULT '',
                `ativo` TINYINT(1) DEFAULT 1,
                `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `atualizado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_admin_evolution` (`admin_id`),
                FOREIGN KEY (`admin_id`) REFERENCES `administradores`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB");
        } catch (PDOException $e) {}

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `planos` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `nome` VARCHAR(50) NOT NULL,
                `slug` VARCHAR(50) NOT NULL UNIQUE,
                `preco` DECIMAL(10,2) DEFAULT 0.00,
                `descricao` VARCHAR(500),
                `beneficios` TEXT,
                `max_clientes` INT DEFAULT NULL,
                `max_usuarios` INT DEFAULT NULL,
                `max_faturas_mensais` INT DEFAULT NULL,
                `whatsapp_cobranca` TINYINT(1) DEFAULT 1,
                `email_cobranca` TINYINT(1) DEFAULT 1,
                `cor` VARCHAR(20) DEFAULT 'secondary',
                `icon` VARCHAR(100) DEFAULT NULL,
                `ativo` TINYINT(1) DEFAULT 1,
                `ordem` INT DEFAULT 0,
                `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB");
        } catch (PDOException $e) {}

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `admin_planos` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `admin_id` INT NOT NULL,
                `plano_id` INT NOT NULL,
                `data_inicio` DATE,
                `data_fim` DATE,
                `atualizado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_admin_plano` (`admin_id`),
                FOREIGN KEY (`admin_id`) REFERENCES `administradores`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`plano_id`) REFERENCES `planos`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB");
        } catch (PDOException $e) {}

        try { $pdo->exec("ALTER TABLE `planos` ADD COLUMN `icon` VARCHAR(100) DEFAULT NULL AFTER `cor`"); } catch (PDOException $e) {}

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `planos_pagamentos` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `admin_id` INT NOT NULL,
                `plano_id` INT NOT NULL,
                `valor` DECIMAL(10,2) NOT NULL,
                `codigo_solicitacao` VARCHAR(100),
                `qr_code` LONGTEXT,
                `pix_copia_cola` TEXT,
                `status` VARCHAR(30) DEFAULT 'pendente',
                `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `pago_em` TIMESTAMP NULL,
                KEY `idx_planos_pag_admin` (`admin_id`),
                KEY `idx_planos_pag_codigo` (`codigo_solicitacao`)
            ) ENGINE=InnoDB");
        } catch (PDOException $e) {}

        try { $pdo->exec("ALTER TABLE `planos_pagamentos` ADD COLUMN `duracao_meses` INT DEFAULT 1 AFTER `valor`"); } catch (PDOException $e) {}
        try { $pdo->exec("ALTER TABLE `planos_pagamentos` ADD COLUMN `descricao` VARCHAR(200) DEFAULT NULL AFTER `duracao_meses`"); } catch (PDOException $e) {}

        try {
            $pdo->exec("INSERT IGNORE INTO `planos` (`nome`, `slug`, `preco`, `descricao`, `cor`, `icon`, `ordem`, `ativo`, `beneficios`, `max_clientes`, `max_usuarios`, `max_faturas_mensais`) VALUES
                ('Bronze', 'bronze', 49.00, 'Autônomos/pequenos', 'bronze', 'fa-medal', 1, 1, 'até 100 clientes\n1 usuário\n500 faturas/mês\nCobranças por WhatsApp: Liberado\nCobranças por e-mail: Liberado\nGateways: Mercado Pago · Inter · Asaas · PIX Manual', 100, 1, 500),
                ('Prata', 'prata', 99.90, 'PMEs/microempresas', 'secondary', 'fa-circle-half-stroke', 2, 1, 'até 500 clientes\n3 usuários\nFaturas ilimitadas\nCobranças por WhatsApp: Liberado\nCobranças por e-mail: Liberado\nGateways: Mercado Pago · Inter · Asaas · PIX Manual', 500, 3, NULL),
                ('Ouro', 'ouro', 199.90, 'Empresas / recuperadoras', 'warning', 'fa-crown', 3, 1, 'Clientes ilimitados\n10 usuários\nFaturas ilimitadas\nCobranças por WhatsApp: Liberado\nCobranças por e-mail: Liberado\nGateways: Mercado Pago · Inter · Asaas · PIX Manual', NULL, 10, NULL)");
        } catch (PDOException $e) {}

        // === MIGRAÇÃO: limites dos planos (clientes, usuários, faturas/mês) ===
        // Idempotente via marcador. Adiciona as colunas de limite e ajusta os
        // planos padrão Bronze/Prata/Ouro com preço, descrição, benefícios e limites.
        try {
            $pdo->exec("ALTER TABLE `planos` ADD COLUMN `max_clientes` INT DEFAULT NULL AFTER `beneficios`");
        } catch (PDOException $e) {}
        try {
            $pdo->exec("ALTER TABLE `planos` ADD COLUMN `max_usuarios` INT DEFAULT NULL AFTER `max_clientes`");
        } catch (PDOException $e) {}
        try {
            $pdo->exec("ALTER TABLE `planos` ADD COLUMN `max_faturas_mensais` INT DEFAULT NULL AFTER `max_usuarios`");
        } catch (PDOException $e) {}
        try {
            $pdo->exec("ALTER TABLE `planos` ADD COLUMN `whatsapp_cobranca` TINYINT(1) DEFAULT 1 AFTER `max_faturas_mensais`");
        } catch (PDOException $e) {}
        try {
            $pdo->exec("ALTER TABLE `planos` ADD COLUMN `email_cobranca` TINYINT(1) DEFAULT 1 AFTER `whatsapp_cobranca`");
        } catch (PDOException $e) {}

        try {
            $planosLimites = $pdo->query("SELECT COUNT(*) FROM configuracoes WHERE chave = 'planos_limites'")->fetchColumn();
            if ((int)$planosLimites === 0) {
                $pdo->exec("INSERT INTO `planos` (`nome`, `slug`, `preco`, `descricao`, `cor`, `icon`, `ordem`, `ativo`, `beneficios`, `max_clientes`, `max_usuarios`, `max_faturas_mensais`) VALUES
                    ('Bronze', 'bronze', 49.00, 'Autônomos/pequenos', 'bronze', 'fa-medal', 1, 1, 'até 100 clientes\n1 usuário\n500 faturas/mês\nCobranças por WhatsApp: Liberado\nCobranças por e-mail: Liberado\nGateways: Mercado Pago · Inter · Asaas · PIX Manual', 100, 1, 500),
                    ('Prata', 'prata', 99.90, 'PMEs/microempresas', 'secondary', 'fa-circle-half-stroke', 2, 1, 'até 500 clientes\n3 usuários\nFaturas ilimitadas\nCobranças por WhatsApp: Liberado\nCobranças por e-mail: Liberado\nGateways: Mercado Pago · Inter · Asaas · PIX Manual', 500, 3, NULL),
                    ('Ouro', 'ouro', 199.90, 'Empresas / recuperadoras', 'warning', 'fa-crown', 3, 1, 'Clientes ilimitados\n10 usuários\nFaturas ilimitadas\nCobranças por WhatsApp: Liberado\nCobranças por e-mail: Liberado\nGateways: Mercado Pago · Inter · Asaas · PIX Manual', NULL, 10, NULL)
                    ON DUPLICATE KEY UPDATE
                    preco = VALUES(preco), descricao = VALUES(descricao), cor = VALUES(cor), icon = VALUES(icon),
                    ordem = VALUES(ordem), ativo = VALUES(ativo), beneficios = VALUES(beneficios),
                    max_clientes = VALUES(max_clientes), max_usuarios = VALUES(max_usuarios), max_faturas_mensais = VALUES(max_faturas_mensais)");
                $pdo->exec("INSERT INTO `configuracoes` (`chave`, `valor`) VALUES ('planos_limites', '1')");
            }
        } catch (PDOException $e) {}

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `admin_certificados` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `admin_id` INT NOT NULL,
                `nome` VARCHAR(100) NOT NULL,
                `subject_dn` TEXT NOT NULL,
                `issuer_dn` TEXT NOT NULL,
                `serial` VARCHAR(100),
                `thumbprint` VARCHAR(255) NOT NULL,
                `certificado_pem` TEXT NOT NULL,
                `validade_inicio` DATETIME,
                `validade_fim` DATETIME,
                `ativo` TINYINT(1) DEFAULT 1,
                `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `ultimo_uso` TIMESTAMP NULL,
                FOREIGN KEY (`admin_id`) REFERENCES `administradores`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB");
        } catch (PDOException $e) {}

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `livro_caixa_entradas` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `descricao` VARCHAR(200) NOT NULL,
                `valor` DECIMAL(10,2) NOT NULL,
                `fatura_id` INT DEFAULT NULL,
                `data` DATE NOT NULL,
                `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB");
        } catch (PDOException $e) {}

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `livro_caixa_saidas` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `descricao` VARCHAR(200) NOT NULL,
                `valor` DECIMAL(10,2) NOT NULL,
                `data` DATE NOT NULL,
                `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB");
        } catch (PDOException $e) {}

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `livro_caixa_custos` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `descricao` VARCHAR(200) NOT NULL,
                `valor` DECIMAL(10,2) NOT NULL,
                `data` DATE NOT NULL,
                `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB");
        } catch (PDOException $e) {}

        $dsn2 = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn2, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $pdo;

    } catch (PDOException $e) {
        error_log("Erro de conexão: " . $e->getMessage());
        return null;
    }
}

function criarTabelas($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `configuracoes` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `chave` VARCHAR(100) NOT NULL UNIQUE,
        `valor` TEXT,
        `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `administradores` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `usuario` VARCHAR(50) NOT NULL UNIQUE,
        `senha` VARCHAR(255) NOT NULL,
        `nome` VARCHAR(100) NOT NULL,
        `email` VARCHAR(100),
        `ativo` TINYINT(1) DEFAULT 1,
        `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `ultimo_login` TIMESTAMP NULL
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `clientes` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tipo_pessoa` ENUM('PF', 'PJ') NOT NULL DEFAULT 'PF',
        `nome_razao` VARCHAR(200) NOT NULL,
        `cpf_cnpj` VARCHAR(20) NOT NULL UNIQUE,
        `rg_ie` VARCHAR(20),
        `email` VARCHAR(150),
        `email2` VARCHAR(150),
        `telefone` VARCHAR(20),
        `celular` VARCHAR(20),
        `cep` VARCHAR(10),
        `logradouro` VARCHAR(200),
        `numero` VARCHAR(10),
        `complemento` VARCHAR(100),
        `bairro` VARCHAR(100),
        `cidade` VARCHAR(100),
        `estado` CHAR(2),
        `senha` VARCHAR(255),
        `token_recuperacao` VARCHAR(64),
        `ativo` TINYINT(1) DEFAULT 1,
        `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `atualizado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `faturas_recorrentes` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `cliente_id` INT NOT NULL,
        `descricao` VARCHAR(200) NOT NULL,
        `valor` DECIMAL(10,2) NOT NULL,
        `frequencia` ENUM('unica', 'diaria', 'semanal', 'quinzenal', 'mensal', 'bimestral', 'trimestral', 'semestral', 'anual') DEFAULT 'mensal',
        `dia_vencimento` INT DEFAULT 1,
        `data_inicio` DATE NOT NULL,
        `data_fim` DATE,
        `ativo` TINYINT(1) DEFAULT 1,
        `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`cliente_id`) REFERENCES `clientes`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `faturas` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `cliente_id` INT NOT NULL,
        `fatura_recorrente_id` INT,
        `numero` VARCHAR(20) NOT NULL UNIQUE,
        `descricao` VARCHAR(200) NOT NULL,
        `valor` DECIMAL(10,2) NOT NULL,
        `desconto` DECIMAL(10,2) DEFAULT 0.00,
        `multa` DECIMAL(10,2) DEFAULT 0.00,
        `juros` DECIMAL(5,2) DEFAULT 0.00,
        `valor_final` DECIMAL(10,2) NOT NULL,
        `data_emissao` DATE NOT NULL,
        `data_vencimento` DATE NOT NULL,
        `data_pagamento` DATE,
        `status` ENUM('pendente', 'pago', 'atrasado', 'cancelado', 'vencido') DEFAULT 'pendente',
        `pix_qrcode` TEXT,
        `pix_copia_cola` VARCHAR(500),
        `link_pagamento` VARCHAR(500),
        `mp_payment_id` VARCHAR(100),
        `observacoes` TEXT,
        `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `atualizado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`cliente_id`) REFERENCES `clientes`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`fatura_recorrente_id`) REFERENCES `faturas_recorrentes`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `contratos` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `cliente_id` INT NOT NULL,
        `numero` VARCHAR(20) NOT NULL UNIQUE,
        `titulo` VARCHAR(200) NOT NULL,
        `conteudo` TEXT NOT NULL,
        `data_inicio` DATE NOT NULL,
        `data_fim` DATE,
        `valor_mensal` DECIMAL(10,2),
        `status` ENUM('ativo', 'suspenso', 'encerrado') DEFAULT 'ativo',
        `arquivo_pdf` VARCHAR(255),
        `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`cliente_id`) REFERENCES `clientes`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `pagamentos_log` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `fatura_id` INT NOT NULL,
        `mp_payment_id` VARCHAR(100),
        `mp_status` VARCHAR(50),
        `mp_status_detail` VARCHAR(100),
        `valor_pago` DECIMAL(10,2),
        `tipo_pagamento` VARCHAR(50),
        `dados_raw` TEXT,
        `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`fatura_id`) REFERENCES `faturas`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_faturas_cliente ON `faturas`(`cliente_id`)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_faturas_status ON `faturas`(`status`)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_faturas_vencimento ON `faturas`(`data_vencimento`)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_clientes_cpf_cnpj ON `clientes`(`cpf_cnpj`)");

    $configInicial = [
        ['mp_access_token', ''],
        ['mp_public_key', ''],
        ['mp_webhook_url', ''],
        ['cor_primaria', '#0f7b5c'],
        ['cor_secundaria', '#6c757d'],
        ['cor_fundo', '#f8f9fa'],
        ['logo_empresa', ''],
        ['nome_sistema', 'Sistema de Cobrança'],
        ['smtp_host', ''],
        ['smtp_port', '587'],
        ['smtp_usuario', ''],
        ['smtp_senha', ''],
        ['smtp_from_email', ''],
        ['smtp_from_nome', 'Sistema de Cobrança'],
        ['smtp_ssl', 'tls'],
        ['envio_dias_antes', '3'],
        ['envio_dias_depois', '1'],
        ['envio_hora', '08:00'],
        ['cron_envio_ativo', '0'],
        ['template_email_assunto_antes', 'Lembrete: Fatura {numero} vence em {data_vencimento}'],
        ['template_email_assunto_depois', 'Cobrança: Fatura {numero} vencida'],
        ['template_email_corpo_antes', ''],
        ['template_email_corpo_depois', ''],
        ['api_pagamento_ativa', 'mercadopago'],
        ['inter_client_id', ''],
        ['inter_client_secret', ''],
        ['inter_conta', ''],
        ['inter_webhook_url', ''],
        ['inter_cert_crt', ''],
        ['inter_cert_key', ''],
        ['inter_cert_webhook', ''],
        ['bb_client_id', ''],
        ['bb_client_secret', ''],
        ['bb_conta', ''],
        ['bb_agencia', ''],
        ['bb_convenio', ''],
        ['bb_carteira', ''],
        ['bb_variacao', ''],
        ['bb_webhook_url', ''],
        ['bb_chave_pix', ''],
        ['bb_ambiente', 'producao'],
        ['cora_client_id', ''],
        ['cora_cert_path', ''],
        ['cora_key_path', ''],
        ['cora_webhook_url', ''],
        ['cora_ambiente', 'producao'],
        ['c6_client_id', ''],
        ['c6_client_secret', ''],
        ['c6_cert_path', ''],
        ['c6_cert_senha', ''],
        ['c6_agencia', ''],
        ['c6_conta', ''],
        ['c6_beneficiario', ''],
        ['c6_empresa', ''],
        ['c6_convenio', ''],
        ['c6_carteira', ''],
        ['c6_webhook_url', ''],
        ['c6_ambiente', 'homologacao'],
        ['pagbank_token', ''],
        ['pagbank_ambiente', 'sandbox'],
        ['pagbank_webhook_url', ''],
        ['nubank_chave_pix', ''],
        ['nubank_whatsapp', ''],
        ['whatsapp_ativo', '0'],
        ['whatsapp_api_url', ''],
        ['whatsapp_api_key', ''],
        ['whatsapp_instance', ''],
        ['cron_token', ''],
        ['site_url', ''],
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO `configuracoes` (`chave`, `valor`) VALUES (?, ?)");
    foreach ($configInicial as $cfg) {
        $stmt->execute($cfg);
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `administradores` WHERE `usuario` = 'admin'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $senhaAdmin = bin2hex(random_bytes(8));
        $hash = password_hash($senhaAdmin, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO `administradores` (`usuario`, `senha`, `nome`, `email`) VALUES (?, ?, 'Administrador', 'admin@sistema.com')");
        $stmt->execute(['admin', $hash]);
        $credFile = __DIR__ . '/../_initial_credentials.txt';
        file_put_contents($credFile, "Usuário: admin\nSenha: " . $senhaAdmin . "\n\nAltere esta senha após o primeiro login.\n");
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `superadmin` WHERE `usuario` = 'super'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $hash = password_hash('Wd#142536#', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO `superadmin` (`usuario`, `senha`, `nome`, `email`) VALUES (?, ?, 'Super Administrador', NULL)");
        $stmt->execute(['super', $hash]);
    }
}
