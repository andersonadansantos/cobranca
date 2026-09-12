-- =====================================================================
-- SISTEMA DE COBRANCA - MULTI-TENANT
-- Dump sanitizado: SCHEMA COMPLETO + SEED MINIMO de dados nao confidenciais.
-- Nao inclui: clientes, faturas, recibos, pagamentos, administradores reais,
-- tokens/chaves de API, certificados nem configuracoes do ambiente de producao.
-- Contas criadas aqui: super (superadmin) e demo (admin) - as mesmas que o
-- config/database.php recria automaticamente se ausentes.
-- Gerado em: 12/09/2026 02:43:00
-- =====================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS cobranca CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cobranca;


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `admin_certificados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_certificados` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `subject_dn` text NOT NULL,
  `issuer_dn` text NOT NULL,
  `serial` varchar(100) DEFAULT NULL,
  `thumbprint` varchar(255) NOT NULL,
  `certificado_pem` text NOT NULL,
  `validade_inicio` datetime DEFAULT NULL,
  `validade_fim` datetime DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ultimo_uso` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `admin_evolution`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_evolution` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `url_api` varchar(255) NOT NULL DEFAULT '',
  `api_key` varchar(255) NOT NULL DEFAULT '',
  `instance` varchar(100) NOT NULL DEFAULT '',
  `ativo` tinyint(1) DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_evolution` (`admin_id`),
  CONSTRAINT `admin_evolution_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `administradores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `admin_planos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_planos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `plano_id` int(11) NOT NULL,
  `data_inicio` date DEFAULT NULL,
  `data_fim` date DEFAULT NULL,
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_plano` (`admin_id`),
  KEY `plano_id` (`plano_id`),
  CONSTRAINT `admin_planos_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `administradores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `admin_planos_ibfk_2` FOREIGN KEY (`plano_id`) REFERENCES `planos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `administradores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `administradores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(50) NOT NULL,
  `subdominio` varchar(50) DEFAULT NULL,
  `senha` varchar(255) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `google_id` varchar(100) DEFAULT NULL,
  `token_recuperacao` varchar(64) DEFAULT NULL,
  `token_recuperacao_expira` datetime DEFAULT NULL,
  `razao_social` varchar(200) DEFAULT NULL,
  `nome_fantasia` varchar(200) DEFAULT NULL,
  `cnpj` varchar(20) DEFAULT NULL,
  `cpf` varchar(20) DEFAULT NULL,
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
  `ativo` tinyint(1) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ultimo_login` timestamp NULL DEFAULT NULL,
  `origem` varchar(20) DEFAULT 'painel',
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario` (`usuario`),
  UNIQUE KEY `uq_admin_subdominio` (`subdominio`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clientes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `tipo_pessoa` enum('PF','PJ') NOT NULL DEFAULT 'PF',
  `nome_razao` varchar(200) NOT NULL,
  `cpf_cnpj` varchar(20) NOT NULL,
  `rg_ie` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `email2` varchar(150) DEFAULT NULL,
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
  `token_recuperacao_expira` datetime DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_cpf_cnpj` (`admin_id`,`cpf_cnpj`),
  KEY `idx_clientes_admin` (`admin_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `configuracoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `configuracoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `chave` varchar(100) NOT NULL,
  `valor` text DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_chave` (`admin_id`,`chave`),
  KEY `idx_config_admin` (`admin_id`)
) ENGINE=InnoDB AUTO_INCREMENT=390 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contratos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contratos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `cliente_id` int(11) NOT NULL,
  `numero` varchar(20) NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `conteudo` text NOT NULL,
  `data_inicio` date NOT NULL,
  `data_fim` date DEFAULT NULL,
  `valor_mensal` decimal(10,2) DEFAULT NULL,
  `status` enum('ativo','suspenso','encerrado') DEFAULT NULL,
  `arquivo_pdf` varchar(255) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero` (`numero`),
  KEY `cliente_id` (`cliente_id`),
  KEY `idx_contratos_admin` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `faturas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `faturas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `cliente_id` int(11) NOT NULL,
  `fatura_recorrente_id` int(11) DEFAULT NULL,
  `numero` varchar(20) NOT NULL,
  `descricao` varchar(200) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `desconto` decimal(10,2) DEFAULT NULL,
  `multa` decimal(10,2) DEFAULT NULL,
  `juros` decimal(5,2) DEFAULT NULL,
  `valor_final` decimal(10,2) NOT NULL,
  `data_emissao` date NOT NULL,
  `data_vencimento` date NOT NULL,
  `data_pagamento` date DEFAULT NULL,
  `status` enum('pendente','pago','atrasado','cancelado','vencido') DEFAULT NULL,
  `pix_qrcode` text DEFAULT NULL,
  `pix_copia_cola` varchar(500) DEFAULT NULL,
  `link_pagamento` varchar(500) DEFAULT NULL,
  `boleto_url` varchar(500) DEFAULT NULL,
  `mp_payment_id` varchar(100) DEFAULT NULL,
  `inter_codigo_solicitacao` varchar(100) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `ultimo_envio` date DEFAULT NULL,
  `ultimo_envio_tipo` varchar(30) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `acesso_token` varchar(64) DEFAULT NULL,
  `api_pagamento` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_numero` (`admin_id`,`numero`),
  KEY `fatura_recorrente_id` (`fatura_recorrente_id`),
  KEY `idx_faturas_cliente` (`cliente_id`),
  KEY `idx_faturas_status` (`status`),
  KEY `idx_faturas_vencimento` (`data_vencimento`),
  KEY `idx_faturas_admin` (`admin_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `faturas_recorrentes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `faturas_recorrentes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `cliente_id` int(11) NOT NULL,
  `descricao` varchar(200) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `frequencia` enum('unica','diaria','semanal','quinzenal','mensal','bimestral','trimestral','semestral','anual') NOT NULL DEFAULT 'mensal',
  `dia_vencimento` int(11) DEFAULT NULL,
  `data_inicio` date NOT NULL,
  `data_fim` date DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `status` varchar(20) NOT NULL DEFAULT 'ativa',
  `numero` varchar(20) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `cliente_id` (`cliente_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `livro_caixa_custos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `livro_caixa_custos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `descricao` varchar(200) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `data` date NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `pago_mes` int(11) DEFAULT NULL,
  `pago_ano` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_lc_custos_admin` (`admin_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `livro_caixa_entradas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `livro_caixa_entradas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `descricao` varchar(200) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `fatura_id` int(11) DEFAULT NULL,
  `data` date NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_lc_entradas_admin` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `livro_caixa_saidas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `livro_caixa_saidas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `descricao` varchar(200) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `data` date NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_lc_saidas_admin` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contexto` varchar(20) NOT NULL,
  `identificador` varchar(100) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `tentativas` int(11) DEFAULT NULL,
  `bloqueado_ate` datetime DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`),
  KEY `idx_contexto_id` (`identificador`,`contexto`),
  KEY `idx_ip` (`ip`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nfse_notas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nfse_notas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `fatura_id` int(11) DEFAULT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `ns_nrec` int(11) DEFAULT NULL,
  `chave_acesso` varchar(100) DEFAULT NULL,
  `numero_nfse` varchar(30) DEFAULT NULL,
  `serie` varchar(10) DEFAULT '900',
  `status` varchar(30) DEFAULT 'pendente',
  `motivo` varchar(500) DEFAULT NULL,
  `valor_servico` decimal(10,2) DEFAULT 0.00,
  `aliquota` decimal(5,2) DEFAULT 5.00,
  `valor_issqn` decimal(10,2) DEFAULT 0.00,
  `descricao_servico` varchar(500) DEFAULT NULL,
  `codigo_servico` varchar(20) DEFAULT NULL,
  `xml_dps` longtext DEFAULT NULL,
  `xml_nfse` longtext DEFAULT NULL,
  `danfse_pdf` longblob DEFAULT NULL,
  `data_emissao` datetime DEFAULT NULL,
  `data_processamento` datetime DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_nfse_admin` (`admin_id`),
  KEY `idx_nfse_fatura` (`fatura_id`),
  KEY `idx_nfse_cliente` (`cliente_id`),
  KEY `idx_nfse_status` (`status`),
  KEY `idx_nfse_nsNRec` (`ns_nrec`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pagamentos_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pagamentos_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `fatura_id` int(11) NOT NULL,
  `mp_payment_id` varchar(100) DEFAULT NULL,
  `mp_status` varchar(50) DEFAULT NULL,
  `mp_status_detail` varchar(100) DEFAULT NULL,
  `valor_pago` decimal(10,2) DEFAULT NULL,
  `tipo_pagamento` varchar(50) DEFAULT NULL,
  `dados_raw` text DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fatura_id` (`fatura_id`),
  KEY `idx_pagamentos_admin` (`admin_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `planos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `planos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(50) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `preco` decimal(10,2) DEFAULT 0.00,
  `descricao` varchar(500) DEFAULT NULL,
  `beneficios` text DEFAULT NULL,
  `max_clientes` int(11) DEFAULT NULL,
  `max_usuarios` int(11) DEFAULT NULL,
  `max_faturas_mensais` int(11) DEFAULT NULL,
  `whatsapp_cobranca` tinyint(1) DEFAULT 1,
  `email_cobranca` tinyint(1) DEFAULT 1,
  `cor` varchar(20) DEFAULT 'secondary',
  `icon` varchar(100) DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `ordem` int(11) DEFAULT 0,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=53873 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `planos_pagamentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `planos_pagamentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `plano_id` int(11) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `duracao_meses` int(11) DEFAULT 1,
  `descricao` varchar(200) DEFAULT NULL,
  `codigo_solicitacao` varchar(100) DEFAULT NULL,
  `qr_code` longtext DEFAULT NULL,
  `pix_copia_cola` text DEFAULT NULL,
  `status` varchar(30) DEFAULT 'pendente',
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `pago_em` timestamp NULL DEFAULT NULL,
  `metodo` varchar(20) DEFAULT NULL,
  `boleto_url` text DEFAULT NULL,
  `boleto_codigo_barras` text DEFAULT NULL,
  `boleto_linha_digitavel` text DEFAULT NULL,
  `mp_status` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_planos_pag_admin` (`admin_id`),
  KEY `idx_planos_pag_codigo` (`codigo_solicitacao`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `recibos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `recibos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `fatura_id` int(11) NOT NULL,
  `numero` varchar(20) NOT NULL,
  `descricao_servico` text DEFAULT NULL,
  `cidade_emissao` varchar(100) DEFAULT NULL,
  `data_emissao` date NOT NULL,
  `valor_recebido` decimal(10,2) NOT NULL,
  `valor_extenso` text DEFAULT NULL,
  `html_gerado` longtext DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_recibo_numero` (`admin_id`,`numero`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `superadmin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `superadmin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(50) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `ultimo_login` timestamp NULL DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario` (`usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `usuarios_admin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuarios_admin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `perfil` enum('admin','financeiro','atendimento') DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ultimo_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ua_admin_email` (`admin_id`,`email`),
  UNIQUE KEY `uq_ua_admin_usuario` (`admin_id`,`usuario`),
  KEY `idx_ua_admin` (`admin_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


-- ---------------------------------------------------------------------
-- SEED MINIMO (nao confidencial)
-- ---------------------------------------------------------------------

-- Planos padrao (Bronze/Prata/Ouro/Demo)
INSERT INTO `planos` (`id`,`nome`,`slug`,`preco`,`descricao`,`beneficios`,`max_clientes`,`max_usuarios`,`max_faturas_mensais`,`whatsapp_cobranca`,`email_cobranca`,`cor`,`icon`,`ativo`,`ordem`) VALUES
(1,'Bronze','bronze',49.00,'Autônomos/pequenos','até 100 clientes
1 usuário
500 faturas/mês
Cobranças por WhatsApp: Liberado
Cobranças por e-mail: Liberado
Gateways: Mercado Pago · Inter · Asaas · PIX Manual',100,1,500,1,1,'bronze','fa-medal',1,1),
(2,'Prata','prata',99.90,'PMEs/microempresas','até 500 clientes
3 usuários
Faturas ilimitadas
Cobranças por WhatsApp: Liberado
Cobranças por e-mail: Liberado
Gateways: Mercado Pago · Inter · Asaas · PIX Manual',500,3,NULL,1,1,'secondary','fa-circle-half-stroke',1,2),
(3,'Ouro','ouro',199.90,'Empresas / recuperadoras','Clientes ilimitados
10 usuários
Faturas ilimitadas
Cobranças por WhatsApp: Liberado
Cobranças por e-mail: Liberado
Gateways: Mercado Pago · Inter · Asaas · PIX Manual',NULL,10,NULL,1,1,'warning','fa-crown',1,3),
(4,'Demo','demo',0.00,'Conta demo para explorar o sistema','Acesso completo ao painel
Cobranças por WhatsApp: Bloqueado
Cobranças por e-mail: Bloqueado
Gateways: visualização apenas',100,1,1,0,0,'info','fa-flask',1,99);

-- Superadmin padrao (criado pelo database.php se ausente)
INSERT INTO `superadmin` (`id`,`usuario`,`senha`,`nome`,`email`) VALUES
(1,'super','$2y$10$..GHXsJc..h32Duo7hm4M.1.Vq/bFdMgEXV0ZA4.ufawoPgUVRRUq','Super Administrador','superadmin@sistema.com');

-- Conta demo (admin) - login: demo / demo1234
INSERT INTO `administradores` (`id`,`usuario`,`subdominio`,`senha`,`nome`,`email`,`ativo`,`origem`) VALUES
(1,'demo',NULL,'$2y$10$2lDx8CLI4IghV2lkQ63zKullc1qE0Y/DdeNA4WqmYRZVddiY9/saa','Conta Demo','demo@cobranca.local',1,'demo');

-- Admin demo vinculado ao plano demo
INSERT INTO `admin_planos` (`admin_id`,`plano_id`,`data_inicio`,`data_fim`) VALUES
(1,4,CURDATE(),DATE_ADD(CURDATE(), INTERVAL 999 MONTH));

COMMIT;
