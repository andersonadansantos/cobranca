-- =====================================================
-- BANCO DE DADOS: cobranca
-- Sistema: WD_payments
-- Compatível com: MySQL 5.7+, MySQL 8.x, MariaDB 10.x
-- Importar via: Painel Hostinger > Bancos de Dados > phpMyAdmin > Importar
-- =====================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- TABELA: administradores
-- =====================================================
DROP TABLE IF EXISTS `administradores`;
CREATE TABLE `administradores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(50) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `google_id` varchar(100) DEFAULT NULL,
  `razao_social` varchar(200) DEFAULT NULL,
  `nome_fantasia` varchar(200) DEFAULT NULL,
  `cnpj` varchar(20) DEFAULT NULL,
  `inscricao_estadual` varchar(30) DEFAULT NULL,
  `inscricao_municipal` varchar(30) DEFAULT NULL,
  `telefone_comercial` varchar(20) DEFAULT NULL,
  `email_comercial` varchar(100) DEFAULT NULL,
  `cep` varchar(10) DEFAULT NULL,
  `logradouro` varchar(200) DEFAULT NULL,
  `numero` varchar(10) DEFAULT NULL,
  `complemento` varchar(100) DEFAULT NULL,
  `bairro` varchar(100) DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `estado` char(2) DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `ultimo_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario` (`usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- TABELA: clientes
-- =====================================================
DROP TABLE IF EXISTS `clientes`;
CREATE TABLE `clientes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo_pessoa` enum('PF','PJ') NOT NULL DEFAULT 'PF',
  `nome_razao` varchar(200) NOT NULL,
  `cpf_cnpj` varchar(20) NOT NULL,
  `rg_ie` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `celular` varchar(20) DEFAULT NULL,
  `cep` varchar(10) DEFAULT NULL,
  `logradouro` varchar(200) DEFAULT NULL,
  `numero` varchar(10) DEFAULT NULL,
  `complemento` varchar(100) DEFAULT NULL,
  `bairro` varchar(100) DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `estado` char(2) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `google_id` varchar(100) DEFAULT NULL,
  `senha` varchar(255) DEFAULT NULL,
  `token_recuperacao` varchar(64) DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `cpf_cnpj` (`cpf_cnpj`),
  KEY `idx_clientes_cpf_cnpj` (`cpf_cnpj`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- TABELA: configuracoes
-- =====================================================
DROP TABLE IF EXISTS `configuracoes`;
CREATE TABLE `configuracoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chave` varchar(100) NOT NULL,
  `valor` text DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `chave` (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- TABELA: contratos
-- =====================================================
DROP TABLE IF EXISTS `contratos`;
CREATE TABLE `contratos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` int(11) NOT NULL,
  `numero` varchar(20) NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `conteudo` text NOT NULL,
  `data_inicio` date NOT NULL,
  `data_fim` date DEFAULT NULL,
  `valor_mensal` decimal(10,2) DEFAULT NULL,
  `status` enum('ativo','suspenso','encerrado') DEFAULT 'ativo',
  `arquivo_pdf` varchar(255) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero` (`numero`),
  KEY `cliente_id` (`cliente_id`),
  CONSTRAINT `contratos_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- TABELA: faturas_recorrentes (antes de faturas)
-- =====================================================
DROP TABLE IF EXISTS `faturas_recorrentes`;
CREATE TABLE `faturas_recorrentes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` int(11) NOT NULL,
  `descricao` varchar(200) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `frequencia` enum('mensal','bimestral','trimestral','semestral','anual') DEFAULT 'mensal',
  `dia_vencimento` int(11) DEFAULT 1,
  `data_inicio` date NOT NULL,
  `data_fim` date DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `status` varchar(20) DEFAULT 'ativa',
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `cliente_id` (`cliente_id`),
  CONSTRAINT `faturas_recorrentes_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- TABELA: faturas
-- =====================================================
DROP TABLE IF EXISTS `faturas`;
CREATE TABLE `faturas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` int(11) NOT NULL,
  `fatura_recorrente_id` int(11) DEFAULT NULL,
  `numero` varchar(20) NOT NULL,
  `descricao` varchar(200) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `desconto` decimal(10,2) DEFAULT 0.00,
  `multa` decimal(10,2) DEFAULT 0.00,
  `juros` decimal(5,2) DEFAULT 0.00,
  `valor_final` decimal(10,2) NOT NULL,
  `data_emissao` date NOT NULL,
  `data_vencimento` date NOT NULL,
  `data_pagamento` date DEFAULT NULL,
  `status` enum('pendente','pago','atrasado','cancelado','vencido') DEFAULT 'pendente',
  `pix_qrcode` text DEFAULT NULL,
  `pix_copia_cola` varchar(500) DEFAULT NULL,
  `link_pagamento` varchar(500) DEFAULT NULL,
  `boleto_url` varchar(500) DEFAULT NULL,
  `mp_payment_id` varchar(100) DEFAULT NULL,
  `inter_codigo_solicitacao` varchar(100) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `ultimo_envio` date DEFAULT NULL,
  `ultimo_envio_tipo` varchar(30) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `acesso_token` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero` (`numero`),
  KEY `fatura_recorrente_id` (`fatura_recorrente_id`),
  KEY `idx_faturas_cliente` (`cliente_id`),
  KEY `idx_faturas_status` (`status`),
  KEY `idx_faturas_vencimento` (`data_vencimento`),
  CONSTRAINT `faturas_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `faturas_ibfk_2` FOREIGN KEY (`fatura_recorrente_id`) REFERENCES `faturas_recorrentes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- TABELA: livro_caixa_custos
-- =====================================================
DROP TABLE IF EXISTS `livro_caixa_custos`;
CREATE TABLE `livro_caixa_custos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `descricao` varchar(200) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `data` date NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `pago_mes` int(11) DEFAULT NULL,
  `pago_ano` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- TABELA: livro_caixa_entradas
-- =====================================================
DROP TABLE IF EXISTS `livro_caixa_entradas`;
CREATE TABLE `livro_caixa_entradas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `descricao` varchar(200) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `fatura_id` int(11) DEFAULT NULL,
  `data` date NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- TABELA: livro_caixa_saidas
-- =====================================================
DROP TABLE IF EXISTS `livro_caixa_saidas`;
CREATE TABLE `livro_caixa_saidas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `descricao` varchar(200) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `data` date NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- TABELA: login_attempts
-- =====================================================
DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contexto` varchar(20) NOT NULL,
  `identificador` varchar(100) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `tentativas` int(11) DEFAULT 1,
  `bloqueado_ate` datetime DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_contexto_id` (`contexto`,`identificador`),
  KEY `idx_ip` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- TABELA: pagamentos_log
-- =====================================================
DROP TABLE IF EXISTS `pagamentos_log`;
CREATE TABLE `pagamentos_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fatura_id` int(11) NOT NULL,
  `mp_payment_id` varchar(100) DEFAULT NULL,
  `mp_status` varchar(50) DEFAULT NULL,
  `mp_status_detail` varchar(100) DEFAULT NULL,
  `valor_pago` decimal(10,2) DEFAULT NULL,
  `tipo_pagamento` varchar(50) DEFAULT NULL,
  `dados_raw` text DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fatura_id` (`fatura_id`),
  CONSTRAINT `pagamentos_log_ibfk_1` FOREIGN KEY (`fatura_id`) REFERENCES `faturas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- TABELA: usuarios_admin
-- =====================================================
DROP TABLE IF EXISTS `usuarios_admin`;
CREATE TABLE `usuarios_admin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `perfil` enum('admin','financeiro','atendimento') DEFAULT 'atendimento',
  `ativo` tinyint(1) DEFAULT 1,
  `avatar` varchar(255) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `ultimo_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `usuario` (`usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- DADOS INICIAIS
-- =====================================================

-- Admin padrão: admin / Admin@2026
INSERT INTO `administradores` (`usuario`, `senha`, `nome`, `email`, `ativo`)
VALUES ('admin', '$2y$10$WrT7RBnyCfLSnr13mu6QDOO2ErqPG7DwGLJLiWk6sRdqumlT6FCOe', 'Administrador', 'admin@sistema.com', 1);

-- Configurações padrão
INSERT INTO `configuracoes` (`chave`, `valor`) VALUES
('cor_primaria', '#ce4b4b'),
('cor_secundaria', '#6c757d'),
('cor_fundo', '#f8f9fa'),
('nome_sistema', 'WD_payments'),
('logo_empresa', '/cobranca/assets/img/logo_empresa.png'),
('logo_login', '/cobranca/assets/img/logo_login.png'),
('logo_mobile', '/cobranca/assets/img/logo_mobile.png'),
('smtp_host', ''),
('smtp_port', '465'),
('smtp_usuario', ''),
('smtp_senha', ''),
('smtp_from_email', ''),
('smtp_from_nome', ''),
('smtp_ssl', 'ssl'),
('mp_access_token', ''),
('mp_public_key', ''),
('mp_webhook_url', ''),
('api_pagamento_ativa', 'mp'),
('inter_client_id', ''),
('inter_client_secret', ''),
('inter_conta', ''),
('inter_webhook_url', ''),
('bb_client_id', ''),
('bb_client_secret', ''),
('bb_conta', ''),
('bb_agencia', ''),
('bb_ambiente', 'sandbox'),
('envio_dias_antes', '3'),
('envio_dias_depois', '1'),
('envio_hora', '08:00'),
('cron_envio_ativo', '0'),
('regua_1_enviar_geracao', '1'),
('regua_2_dias_antes', '7'),
('regua_3_dias_antes', '3'),
('regua_4_no_vencimento', '1'),
('regua_5_dias_depois', '2'),
('financeiro_whatsapp', ''),
('financeiro_email', ''),
('financeiro_fone', '');
