-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 16/08/2026 às 21:00
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `cobranca`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `administradores`
--

CREATE TABLE `administradores` (
  `id` int(11) NOT NULL,
  `usuario` varchar(50) NOT NULL,
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
  `ultimo_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `administradores`
--

INSERT INTO `administradores` (`id`, `usuario`, `senha`, `nome`, `email`, `avatar`, `google_id`, `token_recuperacao`, `token_recuperacao_expira`, `razao_social`, `nome_fantasia`, `cnpj`, `inscricao_estadual`, `inscricao_municipal`, `telefone_comercial`, `email_comercial`, `cep`, `logradouro`, `numero`, `complemento`, `bairro`, `cidade`, `estado`, `ativo`, `criado_em`, `ultimo_login`) VALUES
(1, 'admin', '$2y$10$WrT7RBnyCfLSnr13mu6QDOO2ErqPG7DwGLJLiWk6sRdqumlT6FCOe', 'Administrador', 'admin@sistema.com', '/cobranca/assets/img/avatars/admin_1.png', NULL, NULL, NULL, 'WD Comunicações Agência Digital LTDA', 'WD Soluções Digitais LTDA', '29243448000118', '', '', '(91) 98267-5573', 'contato@agenciawd.com.br', '', '', '', '', '', '', '', 1, '2026-07-19 18:19:10', '2026-08-16 18:58:33');

-- --------------------------------------------------------

--
-- Estrutura para tabela `admin_certificados`
--

CREATE TABLE `admin_certificados` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `subject_dn` text NOT NULL,
  `issuer_dn` text NOT NULL,
  `serial` varchar(100) DEFAULT NULL,
  `thumbprint` varchar(255) NOT NULL,
  `certificado_pem` text NOT NULL,
  `validade_inicio` datetime DEFAULT NULL,
  `validade_fim` datetime DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `ultimo_uso` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `admin_certificados`
--

INSERT INTO `admin_certificados` (`id`, `admin_id`, `nome`, `subject_dn`, `issuer_dn`, `serial`, `thumbprint`, `certificado_pem`, `validade_inicio`, `validade_fim`, `ativo`, `criado_em`, `ultimo_uso`) VALUES
(3, 1, 'WD COMUNICACOES AGENCIA DIGITAL', 'WD COMUNICACOES AGENCIA DIGITAL LTDA:29243448000118', 'AC Certisign RFB G5', '24ADFF71C74E42AADC206497ABAC786F', 'F67B022205FF606DA0DC7B49EF807CE5EDD7BADC', '-----BEGIN CERTIFICATE-----\nMIIIDjCCBfagAwIBAgIQJK3/ccdOQqrcIGSXq6x4bzANBgkqhkiG9w0BAQsFADB4\nMQswCQYDVQQGEwJCUjETMBEGA1UEChMKSUNQLUJyYXNpbDE2MDQGA1UECxMtU2Vj\ncmV0YXJpYSBkYSBSZWNlaXRhIEZlZGVyYWwgZG8gQnJhc2lsIC0gUkZCMRwwGgYD\nVQQDExNBQyBDZXJ0aXNpZ24gUkZCIEc1MB4XDTI1MTIyNzEyMzEyMVoXDTI2MTIy\nNzEyMzEyMVowgf8xCzAJBgNVBAYTAkJSMRMwEQYDVQQKDApJQ1AtQnJhc2lsMQsw\nCQYDVQQIDAJQQTESMBAGA1UEBwwJQmVuZXZpZGVzMRMwEQYDVQQLDApQcmVzZW5j\naWFsMRcwFQYDVQQLDA4zMTM4MTMyNTAwMDE5NTE2MDQGA1UECwwtU2VjcmV0YXJp\nYSBkYSBSZWNlaXRhIEZlZGVyYWwgZG8gQnJhc2lsIC0gUkZCMRYwFAYDVQQLDA1S\nRkIgZS1DTlBKIEExMTwwOgYDVQQDDDNXRCBDT01VTklDQUNPRVMgQUdFTkNJQSBE\nSUdJVEFMIExUREE6MjkyNDM0NDgwMDAxMTgwggEiMA0GCSqGSIb3DQEBAQUAA4IB\nDwAwggEKAoIBAQCWuWSEriTxK2GAfFjnbIEla43Ixl796AFyZqenSSddX9pw7zsn\nlal3u9cMS09HldN/txXOlL4TmSqyqlPzob1L5HrdnUNaZBYLoGDO01/NY+9UvBME\nxdDRvq+ZsJuKhQE09UT0HnpnopzfBArYxrCxwc9Q7oXk9bqZ3i8Bgwog6+6FEkIQ\neksYHxsyHeIYqPtwhtHzYKuimlyBPkuP2DQ6kon3pI/kNoF0fwWQkykai0BfzP6S\nKiimGaG/UAv+4BjK04sAbzfHvDKkPD05+5qTHky2fCIuRTrB6LAk9wkCziEOrWgu\nH+auHVU5+QsNQdynC2Nb8weSiWJAV7+h0jfNAgMBAAGjggMKMIIDBjCBuQYDVR0R\nBIGxMIGuoDwGBWBMAQMEoDMEMTE2MTIxOTkwMDEzNTQwOTUyNzgwMDAwMDAwMDAw\nMDAwMDAwMDAwNjAzMTg0NlBDUEGgIAYFYEwBAwKgFwQVTElESUFORSBDT1NUQSBC\nQVJSRVRPoBkGBWBMAQMDoBAEDjI5MjQzNDQ4MDAwMTE4oBcGBWBMAQMHoA4EDDAw\nMDAwMDAwMDAwMIEYd2Rjb211bmljYWNhbzdAZ21haWwuY29tMAkGA1UdEwQCMAAw\nHwYDVR0jBBgwFoAUU31/nb7RYdAgutqf44mnE3NYzUIwfwYDVR0gBHgwdjB0BgZg\nTAECAQwwajBoBggrBgEFBQcCARZcaHR0cDovL2ljcC1icmFzaWwuY2VydGlzaWdu\nLmNvbS5ici9yZXBvc2l0b3Jpby9kcGMvQUNfQ2VydGlzaWduX1JGQi9EUENfQUNf\nQ2VydGlzaWduX1JGQi5wZGYwgbwGA1UdHwSBtDCBsTBXoFWgU4ZRaHR0cDovL2lj\ncC1icmFzaWwuY2VydGlzaWduLmNvbS5ici9yZXBvc2l0b3Jpby9sY3IvQUNDZXJ0\naXNpZ25SRkJHNS9MYXRlc3RDUkwuY3JsMFagVKBShlBodHRwOi8vaWNwLWJyYXNp\nbC5vdXRyYWxjci5jb20uYnIvcmVwb3NpdG9yaW8vbGNyL0FDQ2VydGlzaWduUkZC\nRzUvTGF0ZXN0Q1JMLmNybDAOBgNVHQ8BAf8EBAMCBeAwHQYDVR0lBBYwFAYIKwYB\nBQUHAwIGCCsGAQUFBwMEMIGsBggrBgEFBQcBAQSBnzCBnDBfBggrBgEFBQcwAoZT\naHR0cDovL2ljcC1icmFzaWwuY2VydGlzaWduLmNvbS5ici9yZXBvc2l0b3Jpby9j\nZXJ0aWZpY2Fkb3MvQUNfQ2VydGlzaWduX1JGQl9HNS5wN2MwOQYIKwYBBQUHMAGG\nLWh0dHA6Ly9vY3NwLWFjLWNlcnRpc2lnbi1yZmIuY2VydGlzaWduLmNvbS5icjAN\nBgkqhkiG9w0BAQsFAAOCAgEAfhs+NPTSsFwXB3z4U824ojD75hXCUTK/Rh/G8yNJ\n70OjY9jPkOa+iqVealNZYqIoQ1bTzILZCsg9zXc6G7AHcwyfoTOFvef9+MKDKaD5\nb7fS+cvyzsrG4Dm4ZtXa69cAORwmzOdlmsY7RBnNL4Dn/czEC6NiKjDNROECAKHy\nGDH8pW48rXtoVhSTbPTYKKYRe1aXt5FhQOnyiEO/KNubk1FmVwzf/sc0ujvPqgxk\nXN6pwe90bB1tnSHQOJ3BUH2Y+IQet1F5Mn2d9ZI2V/QD766JYsKc9cnx5UIxrW7k\neGfMr5Tn9Y8Yrf4kXQ5wZdhDgK2StaxlBQd4G0idp+aWONhRp4k3kpxIIJVkUIU2\nP7T+7rwxMt7cDjykt+BUEEuIRm9XNOqTDhwwhrBEGSDw8fSTyY5aYXOLq+8GDK9Q\nUR5VhK8tqSeq8SGkoO+byfcIo4euuKTEhaNWPiZcvOeKEcQ2AP73Gf83UHuq63ZS\n+YuZ2lhM1/lXICW4HVhdKYWNdCLsZEFq+B5ZTKtZk4uztTBCRNLvOAcPc6o+QkHX\nxa529MzY1ksQGkaLpbRD27oJ1HOCmJPUhlpWfy3+zw1nQnhGXbUnu/oCXGXIs/Fu\nkZLULVPr3l7FU5Nxwyj+cCFi/mCR10foaNhIFUi9KDk0nXrECwCnL1gM/FemtM6p\nep4=\n-----END CERTIFICATE-----\n', '2025-12-27 13:31:21', '2026-12-27 13:31:21', 1, '2026-07-27 14:21:19', '2026-07-29 19:15:34');

-- --------------------------------------------------------

--
-- Estrutura para tabela `clientes`
--

CREATE TABLE `clientes` (
  `id` int(11) NOT NULL,
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
  `token_recuperacao_expira` datetime DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `clientes`
--

INSERT INTO `clientes` (`id`, `tipo_pessoa`, `nome_razao`, `cpf_cnpj`, `rg_ie`, `email`, `telefone`, `celular`, `cep`, `logradouro`, `numero`, `complemento`, `bairro`, `cidade`, `estado`, `avatar`, `google_id`, `senha`, `token_recuperacao`, `token_recuperacao_expira`, `ativo`, `criado_em`, `atualizado_em`) VALUES
(1, 'PJ', 'WD Soluções Digitais', '29243448000118', '', 'wdcomunicacao7@gmail.com', '(91) 98267-5573', '(91) 98267-5573', '6681630', 'Moura Carvalho', '', 'frente cohab', 'Campina', 'Belém', 'PA', '/cobranca/assets/img/avatars/user_1.png', NULL, '$2y$10$8OV5PCx08fsHh6qAYjclaeEjeW4O33lXpS1Hxzlmb.S9nunH5/8cm', NULL, NULL, 1, '2026-07-19 18:22:07', '2026-08-03 18:16:16'),
(2, 'PF', 'Anderson Adan', '84996374268', '', 'adansantoswd@gmail.com', '(91) 99942-6049', '(91) 99942-6049', '6681630', 'Moura Carvalho', '134', 'frente cohab', 'Campina', 'Belém', 'PA', '/cobranca/assets/img/avatars/user_2.jpg', NULL, '$2y$10$wpjAleoUOJYrKa1qmPHnQ.4ij8pbk1wL7GZVhs0ksdPCgRwOBONyy', 'fcd815f32febdf8647016be7cc09dfaaa3a7d57dc6babf337bda0b40edbca490', '2026-08-03 16:18:47', 1, '2026-07-21 19:31:41', '2026-08-03 18:18:47');

-- --------------------------------------------------------

--
-- Estrutura para tabela `configuracoes`
--

CREATE TABLE `configuracoes` (
  `id` int(11) NOT NULL,
  `chave` varchar(100) NOT NULL,
  `valor` text DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `configuracoes`
--

INSERT INTO `configuracoes` (`id`, `chave`, `valor`, `criado_em`) VALUES
(1, 'mp_access_token', 'APP_USR-881644701953872-052609-fd9c330856a662737bc4e70340d335b8-1443680567', '2026-07-19 18:19:10'),
(2, 'mp_public_key', 'APP_USR-e0075b9b-2453-4a09-be58-5c53f0690df4', '2026-07-19 18:19:10'),
(3, 'mp_webhook_url', 'http://localhost/cobranca/api/webhook.php', '2026-07-19 18:19:10'),
(4, 'cor_primaria', '#bc1515', '2026-07-19 18:19:10'),
(5, 'cor_secundaria', '#6c757d', '2026-07-19 18:19:10'),
(6, 'cor_fundo', '#f8f9fa', '2026-07-19 18:19:10'),
(7, 'logo_empresa', '/cobranca/assets/img/logo_1784559250.png', '2026-07-19 18:19:10'),
(8, 'nome_sistema', 'WD_payments', '2026-07-19 18:19:10'),
(9, 'logo_login', '/cobranca/assets/img/logo_login_1784559397.png', '2026-07-20 14:56:37'),
(10, 'smtp_host', 'smtp.emailarray.com', '2026-07-20 19:19:46'),
(11, 'smtp_port', '465', '2026-07-20 19:19:46'),
(12, 'smtp_usuario', 'contato@agenciawd.com.br', '2026-07-20 19:19:46'),
(13, 'smtp_senha', 'Wd#142536#', '2026-07-20 19:19:46'),
(14, 'smtp_from_email', 'contato@agenciawd.com.br', '2026-07-20 19:19:46'),
(15, 'smtp_from_nome', 'WD Soluções Digitais', '2026-07-20 19:19:46'),
(16, 'smtp_ssl', 'ssl', '2026-07-20 19:19:46'),
(24, 'envio_dias_antes', '3', '2026-07-20 19:38:38'),
(25, 'envio_dias_depois', '1', '2026-07-20 19:38:38'),
(26, 'envio_hora', '08:00', '2026-07-20 19:38:38'),
(27, 'cron_envio_ativo', '1', '2026-07-20 19:38:38'),
(28, 'inter_client_id', '7dd3fd9f-d422-4eb7-b305-71ed4fe07ec7', '2026-07-20 21:19:05'),
(29, 'inter_client_secret', '9534137d-34d4-4808-a75a-0b310b3e5638', '2026-07-20 21:19:05'),
(30, 'inter_conta', '0001-3855031-8', '2026-07-20 21:19:05'),
(31, 'inter_webhook_url', 'http://localhost/cobranca/api/webhook_inter.php', '2026-07-20 21:19:05'),
(32, 'api_pagamento_ativa', 'inter', '2026-07-20 21:19:13'),
(40, 'inter_cert_crt', 'C:\\xampp\\htdocs\\cobranca\\admin/../config/inter_certs/certificado.crt', '2026-07-20 22:40:35'),
(41, 'inter_cert_key', 'C:\\xampp\\htdocs\\cobranca\\admin/../config/inter_certs/certificado.key', '2026-07-20 22:40:35'),
(55, 'inter_cert_webhook', 'C:\\xampp\\htdocs\\cobranca\\admin/../config/inter_certs/certificado_webhook.pem', '2026-07-21 15:08:54'),
(85, 'bb_client_id', 'eyJpZCI6ImI3M2I5NTMtNjljZS00YWRjLThlZmYiLCJjb2RpZ29QdWJsaWNhZG9yIjowLCJjb2RpZ29Tb2Z0d2FyZSI6MTY2OTk4LCJzZXF1ZW5jaWFsSW5zdGFsYWNhbyI6MX0', '2026-07-21 21:23:06'),
(86, 'bb_client_secret', 'eyJpZCI6ImEwMjc1NWUtZDk0OC00ZDViLTkwZTAtM2IxNDFjODc2YzUiLCJjb2RpZ29QdWJsaWNhZG9yIjowLCJjb2RpZ29Tb2Z0d2FyZSI6MTY2OTk4LCJzZXF1ZW5jaWFsSW5zdGFsYWNhbyI6MSwic2VxdWVuY2lhbENyZWRlbmNpYWwiOjEsImFtYmllbnRlIjoicHJvZHVjYW8iLCJpYXQiOjE3ODQ2NjgzOTg4MDN9', '2026-07-21 21:23:06'),
(87, 'bb_conta', '168360', '2026-07-21 21:23:06'),
(88, 'bb_agencia', '2605', '2026-07-21 21:23:06'),
(89, 'bb_convenio', '', '2026-07-21 21:23:06'),
(90, 'bb_carteira', '', '2026-07-21 21:23:06'),
(91, 'bb_variacao', '', '2026-07-21 21:23:06'),
(92, 'bb_webhook_url', '', '2026-07-21 21:23:06'),
(93, 'bb_chave_pix', '', '2026-07-21 21:23:06'),
(94, 'bb_ambiente', 'producao', '2026-07-21 21:23:06'),
(95, 'logo_mobile', '/cobranca/assets/img/logo_mobile_1784733364.png', '2026-07-22 15:16:04'),
(96, 'banner_desktop', '/cobranca/assets/img/banners/banner_desktop.png', '2026-07-22 15:39:11'),
(97, 'banner_mobile', '/cobranca/assets/img/banners/banner_mobile.png', '2026-07-22 15:39:17'),
(98, 'banner_desktop_1', '/cobranca/assets/img/banners/desktop_1.png', '2026-07-22 15:55:55'),
(99, 'banner_desktop_2', '/cobranca/assets/img/banners/desktop_2.png', '2026-07-22 15:56:02'),
(100, 'banner_desktop_3', '/cobranca/assets/img/banners/desktop_3.png', '2026-07-22 15:56:09'),
(101, 'banner_mobile_1', '/cobranca/assets/img/banners/mobile_1.png', '2026-07-22 16:04:04'),
(102, 'banner_mobile_2', '/cobranca/assets/img/banners/mobile_2.png', '2026-07-22 16:04:11'),
(103, 'banner_mobile_3', '/cobranca/assets/img/banners/mobile_3.png', '2026-07-22 16:04:18'),
(136, 'financeiro_whatsapp', '91982675573', '2026-07-23 14:34:20'),
(137, 'financeiro_email', 'contato@agenciawd.com.br', '2026-07-23 14:34:20'),
(138, 'financeiro_fone', '91982675573', '2026-07-23 14:34:20'),
(164, 'regua_1_enviar_geracao', '1', '2026-07-24 01:07:25'),
(165, 'regua_2_dias_antes', '5', '2026-07-24 01:07:25'),
(166, 'regua_3_dias_antes', '3', '2026-07-24 01:07:25'),
(167, 'regua_4_no_vencimento', '1', '2026-07-24 01:07:25'),
(168, 'regua_5_dias_depois', '2', '2026-07-24 01:07:25'),
(212, 'pagbank_token', 'a15851aa-76cd-45e9-a6fe-4aef633006d015de635742e9b8605311bef9008475f1f9ad-c700-4ab8-89b6-16957742eded', '2026-07-27 14:48:37'),
(213, 'pagbank_ambiente', 'producao', '2026-07-27 14:48:37'),
(214, 'pagbank_webhook_url', '', '2026-07-27 14:48:37'),
(215, 'nubank_chave_pix', '29243448000118', '2026-07-27 15:29:06'),
(216, 'nubank_whatsapp', '5591982675573', '2026-07-27 15:29:06'),
(232, 'pix_manual_chave', '29243448000118', '2026-07-27 19:07:41'),
(233, 'pix_manual_banco', 'BTG Pactual', '2026-07-27 19:07:41'),
(234, 'pix_manual_favorecido', 'WD Comunicações Digitais LTDA', '2026-07-27 19:07:41'),
(235, 'pix_manual_cnpj', '29243448000118', '2026-07-27 19:07:41'),
(236, 'pix_manual_whatsapp', '5591982675573', '2026-07-27 19:07:41'),
(246, 'whatsapp_api_url', 'https://sites-evolution-api.4lktmy.easypanel.host', '2026-07-29 12:16:01'),
(247, 'whatsapp_api_key', 'EEC416A9B0A4-419B-889B-523E646D9885', '2026-07-29 12:16:01'),
(248, 'whatsapp_instance', 'WD_Payment', '2026-07-29 12:16:01'),
(249, 'whatsapp_ativo', '1', '2026-07-29 12:16:01'),
(250, 'template_whats_antes', '{nomeEmpresa}\r\nOlá {cliente} esse é um lembrete de sua fatura {numero} de valor {valor} com vencimento: {data_vencimento}\r\n\r\nSegue seu código PIX para pagamento:\r\n{pix}\r\n\r\n==========CENTRAL DE CLIENTE===============\r\n\r\nPara 2ª via de sua fatura acesse sua área de cliente:\r\n{link_fatura}\r\n\r\n===========ACESSO:=========================\r\n{cpf_cnpj}', '2026-07-30 14:05:53'),
(252, 'template_whats_depois', '{nomeEmpresa}\r\nOlá {cliente} SUA FATURA ESTÁ EM ATRASO. {numero} de valor {valor} com vencimento: {data_vencimento}\r\n\r\nSegue seu código PIX para pagamento:\r\n{pix}\r\n\r\n==========CENTRAL DE CLIENTE===============\r\n\r\nPara 2ª via de sua fatura acesse sua área de cliente:\r\n{link_fatura}\r\n\r\n===========ACESSO:=========================\r\n{cpf_cnpj}', '2026-07-30 14:06:47'),
(253, 'template_whats_pagamento', '{nomeEmpresa}\r\nOlá {cliente} RECEBEMOS SEU PAGAMENTO. Fatura {numero} de valor {valor} com vencimento: {data_vencimento}.\r\n\r\nOBRIGADO!\r\n\r\n\r\n', '2026-07-30 14:07:50'),
(277, 'cron_token', '41eacd86933aa74d9c4e61cb2539830a', '2026-08-12 12:13:02');

-- --------------------------------------------------------

--
-- Estrutura para tabela `contratos`
--

CREATE TABLE `contratos` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `numero` varchar(20) NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `conteudo` text NOT NULL,
  `data_inicio` date NOT NULL,
  `data_fim` date DEFAULT NULL,
  `valor_mensal` decimal(10,2) DEFAULT NULL,
  `status` enum('ativo','suspenso','encerrado') DEFAULT 'ativo',
  `arquivo_pdf` varchar(255) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `faturas`
--

CREATE TABLE `faturas` (
  `id` int(11) NOT NULL,
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
  `api_pagamento` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `faturas`
--

INSERT INTO `faturas` (`id`, `cliente_id`, `fatura_recorrente_id`, `numero`, `descricao`, `valor`, `desconto`, `multa`, `juros`, `valor_final`, `data_emissao`, `data_vencimento`, `data_pagamento`, `status`, `pix_qrcode`, `pix_copia_cola`, `link_pagamento`, `boleto_url`, `mp_payment_id`, `inter_codigo_solicitacao`, `observacoes`, `ultimo_envio`, `ultimo_envio_tipo`, `criado_em`, `atualizado_em`, `acesso_token`, `api_pagamento`) VALUES
(13, 1, 12, 'FAT-202607-0001', 'aluguel da casa', 10.00, 0.00, 0.00, 0.00, 10.00, '2026-07-20', '2026-07-31', NULL, 'cancelado', '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', '00020126580014br.gov.bcb.pix0136098e4d43-6777-411d-959f-2e0e8dad5ffc520400005303986540510.005802BR5909AGENCIAWD6010Ananindeua62250521mpqrinter16887092500363041537', 'https://www.mercadopago.com.br/payments/168870925003/ticket?caller_id=1206853381&hash=49f96ec3-3e27-4273-bb1a-3f49abf2be79', 'https://www.mercadopago.com.br/payments/169749258128/ticket?caller_id=1206853381&payment_method_id=bolbradesco&payment_id=169749258128&payment_method_reference_id=10596392741&hash=89128fb8-35c7-49f7-a639-4d401ba7aa80', '169749258128', NULL, NULL, NULL, NULL, '2026-07-20 21:25:58', '2026-07-24 01:39:08', '391c95875c050978c4c363abc7b309eeeb4db940a94dd5e0c969e3955fce78e5', NULL),
(14, 1, 13, 'FAT-202607-0002', 'inter', 5.00, 0.00, 0.00, 0.00, 5.00, '2026-07-20', '2026-07-30', NULL, 'cancelado', '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', '00020126580014br.gov.bcb.pix0136098e4d43-6777-411d-959f-2e0e8dad5ffc52040000530398654045.005802BR5909AGENCIAWD6010Ananindeua62250521mpqrinter1697661950186304821E', 'https://www.mercadopago.com.br/payments/169766195018/ticket?caller_id=1206853381&hash=09e708ab-32a7-4134-81ff-19b822a4c62c', 'https://www.mercadopago.com.br/payments/169843515970/ticket?caller_id=1206853381&payment_method_id=bolbradesco&payment_id=169843515970&payment_method_reference_id=10599026498&hash=9b2b1f5e-1502-4d37-8870-c7288c57697e', '169843515970', NULL, NULL, NULL, NULL, '2026-07-20 22:47:42', '2026-07-24 01:39:08', '6194c5b9a4651d810a3510477c021ed4fcc16baf9e713d8d78cff2c57fcd1953', NULL),
(15, 1, 14, 'FAT-202607-0003', 'teste 3', 5.00, 0.00, 0.00, 0.00, 5.00, '2026-07-21', '2026-07-31', NULL, 'cancelado', '', '000201010212261010014BR.GOV.BCB.PIX2579cdpj-sandbox.partners.uatinter.co/pj-s/v2/cobv/54abeecba27447dc8f732e983572c1ad52040000530398654045.005802BR5901*6013Belo_Horizont61089999999962070503***6304D75D', '', '/cobranca/assets/boletos_inter/5e9c10f7-0888-4628-955e-8c30de0bffc9.pdf', NULL, '5e9c10f7-0888-4628-955e-8c30de0bffc9', NULL, NULL, NULL, '2026-07-21 15:27:52', '2026-07-24 01:39:08', '2352bd1afc6acbfcec43ba211bf47b4ce65ce53353a52400be3e6c53889bb097', NULL),
(22, 2, 21, 'FAT-202607-0004', 'testar pagamento', 5.00, 0.00, 0.00, 0.00, 5.00, '2026-07-23', '2026-07-30', '2026-07-23', 'pago', '', '00020101021226980014BR.GOV.BCB.PIX2576spi-qrcode.bancointer.com.br/spi/pj/v2/cobv/6f16e434de2e4209901c486f7254be6052040000530398654045.005802BR5901*6009BENEVIDES61086879500062070503***63040FA5', '', NULL, NULL, '6b6269d2-7edc-4834-9cea-8e6392c3c394', NULL, NULL, NULL, '2026-07-23 12:44:24', '2026-07-24 01:39:08', '3cfef41cefe366b400ab873a31bad009d2be4dd722426e42a5b0339fb659a3a0', NULL),
(23, 2, 22, 'FAT-202607-0005', 'teste 2', 5.00, 0.00, 0.00, 0.00, 5.00, '2026-07-23', '2026-07-30', '2026-07-23', 'pago', '', '00020101021226980014BR.GOV.BCB.PIX2576spi-qrcode.bancointer.com.br/spi/pj/v2/cobv/528a703da4e140b991101c9ff95207f652040000530398654045.005802BR5901*6009BENEVIDES61086879500062070503***6304237B', '', NULL, NULL, 'af6f16d7-bb97-4383-a4d6-84e36fc78d11', NULL, NULL, NULL, '2026-07-23 13:16:27', '2026-07-24 01:39:08', 'e2ebf9905d4ee86e98e05963ec88e3317c4b11a7714f58d7b8633f76f3fd4204', NULL),
(25, 1, 24, 'FAT-202607-0006', 'teste 4', 5.00, 0.00, 0.00, 0.00, 5.00, '2026-07-23', '2026-07-30', NULL, 'cancelado', '', '00020101021226980014BR.GOV.BCB.PIX2576spi-qrcode.bancointer.com.br/spi/pj/v2/cobv/26a7437cf94e4df0aa48416070694c2252040000530398654045.005802BR5901*6009BENEVIDES61086879500062070503***6304FDB0', '', NULL, NULL, '4157c330-b69f-4549-94c7-8cde67cc0d37', NULL, '2026-07-25', 'geracao', '2026-07-23 19:27:22', '2026-07-25 13:05:59', 'e71d47db1c5c867eafb48e1b49f800d8fa605fbc15b9db9852ad8ff0ba261bb8', NULL),
(26, 1, 25, 'FAT-202607-0007', 'Localhost', 5.00, 0.00, 0.00, 0.00, 5.00, '2026-07-25', '2026-07-30', NULL, 'cancelado', '', '00020101021226980014BR.GOV.BCB.PIX2576spi-qrcode.bancointer.com.br/spi/pj/v2/cobv/8eb3446ad3d04ef6a1f04afd97b65c3252040000530398654045.005802BR5901*6009BENEVIDES61086879500062070503***6304F5C3', '', NULL, NULL, '5c12e4d4-6cf2-4e24-93c9-d63d955be3ba', NULL, '2026-07-27', 'lembrete2', '2026-07-25 13:51:36', '2026-08-03 18:19:55', '942fde3f5456f03601d389094119200edd029d6bdeec7bef5a93275d764fdb05', NULL),
(27, 1, 26, 'FAT-202607-0008', 'teste Nu Bank', 5.00, 0.00, 0.00, 0.00, 5.00, '2026-07-27', '2026-08-02', NULL, 'cancelado', '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', '00020126580014br.gov.bcb.pix0136098e4d43-6777-411d-959f-2e0e8dad5ffc52040000530398654045.005802BR5909AGENCIAWD6010Ananindeua62250521mpqrinter1698989319956304AE9F', 'https://www.mercadopago.com.br/payments/169898931995/ticket?caller_id=1206853381&hash=67d8d8a4-d93b-4522-8c04-369eace48df0', 'https://www.mercadopago.com.br/payments/169899021893/ticket?caller_id=1206853381&payment_method_id=bolbradesco&payment_id=169899021893&payment_method_reference_id=10600747894&hash=fa3d5886-9fd6-4b50-99c4-cb4021d5d234', '169899021893', NULL, NULL, NULL, NULL, '2026-07-27 18:06:28', '2026-07-27 18:08:01', '852c87a5589ba0d5704b3a7e85ecee7ec33bb69e5641a8ffb2748316e863e446', NULL),
(28, 1, 27, 'FAT-202607-0009', 'teste Nu Bank2', 5.00, 0.00, 0.00, 0.00, 5.00, '2026-07-27', '2026-08-02', NULL, 'cancelado', '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', '00020126580014br.gov.bcb.pix0136098e4d43-6777-411d-959f-2e0e8dad5ffc52040000530398654045.005802BR5909AGENCIAWD6010Ananindeua62250521mpqrinter1707916005946304EFE0', 'https://www.mercadopago.com.br/payments/170791600594/ticket?caller_id=1206853381&hash=442d1334-9cf6-4b3c-99b4-9b5be623f068', 'https://www.mercadopago.com.br/payments/170791588682/ticket?caller_id=1206853381&payment_method_id=bolbradesco&payment_id=170791588682&payment_method_reference_id=10598305905&hash=9f75373d-0edb-4cef-97ab-1588a8706d51', '170791588682', NULL, NULL, NULL, NULL, '2026-07-27 18:11:43', '2026-07-27 18:12:59', '3796fcf14392263ccf6f0145ec0b957e9c4263164dff67af6f558c7055e27c83', NULL),
(29, 1, 28, 'FAT-202607-0010', 'teste inter', 5.00, 0.00, 0.00, 0.00, 5.00, '2026-07-27', '2026-08-01', NULL, 'cancelado', '', '00020101021226980014BR.GOV.BCB.PIX2576spi-qrcode.bancointer.com.br/spi/pj/v2/cobv/7418227043524bc68722b04c262f8e3c52040000530398654045.005802BR5901*6009BENEVIDES61086879500062070503***63042224', '', '/cobranca/assets/boletos_inter/eec86239-934d-4c07-a4ac-a3fa9fd773d7.pdf', NULL, 'eec86239-934d-4c07-a4ac-a3fa9fd773d7', NULL, NULL, NULL, '2026-07-27 18:13:23', '2026-07-27 18:34:01', 'dd4c9d870a18f8284e7daedd96ce17acf35acf15d9ed2663f4b6451a0981e8fd', NULL),
(30, 1, 29, 'FAT-202607-0011', 'teste Pagbank', 5.00, 0.00, 0.00, 0.00, 5.00, '2026-07-27', '2026-08-02', NULL, 'cancelado', '', '00020101021426360014br.gov.bcb.pix01142924344800011852040000530398654045.005802BR5927WD COMUNICAÇÕES DIGITAIS 6009SAO PAULO62070503***63BFDF', '', NULL, 'pix_manual_1785179315', NULL, NULL, NULL, NULL, '2026-07-27 18:32:11', '2026-07-27 19:46:10', '42570a192823f31b0e67ee6f33abfa3090305603a7836a719c25084c81fd21db', NULL),
(31, 1, 30, 'FAT-202607-0012', 'pix manual', 5.00, 0.00, 0.00, 0.00, 5.00, '2026-07-27', '2026-08-02', NULL, 'cancelado', '', '00020101021426360014br.gov.bcb.pix01142924344800011852040000530398654045.005802BR5927WD COMUNICAÇÕES DIGITAIS 6009SAO PAULO62070503***63BFDF', '', 'https://www.mercadopago.com.br/payments/170799358932/ticket?caller_id=1206853381&payment_method_id=bolbradesco&payment_id=170799358932&payment_method_reference_id=10600773868&hash=90a31b09-a28a-4140-aea7-99dbe65884ab', '170799358932', NULL, NULL, NULL, NULL, '2026-07-27 19:08:08', '2026-07-27 19:46:07', 'd84ae9b3b85a0b013a56cc14185e38988d902d66a97b0e4a694053d8b5bdb276', NULL),
(32, 1, 31, 'FAT-202607-0013', 'teste inter', 5.00, 0.00, 0.00, 0.00, 5.00, '2026-07-27', '2026-08-01', NULL, 'cancelado', '', '00020101021226980014BR.GOV.BCB.PIX2576spi-qrcode.bancointer.com.br/spi/pj/v2/cobv/aa632b5eed4e4fe480168c481800c3b152040000530398654045.005802BR5901*6009BENEVIDES61086879500062070503***6304D509', '', '/cobranca/assets/boletos_inter/2eb655be-a0de-44bf-8ce5-3edb721d1829.pdf', NULL, '2eb655be-a0de-44bf-8ce5-3edb721d1829', NULL, NULL, NULL, '2026-07-27 19:46:35', '2026-07-27 20:21:17', '5ec82a26b65de8573ad7e905fc38f2806b9f111f2694ba36943a16d37c7a3476', NULL),
(33, 1, 32, 'FAT-202607-0014', 'pix manual', 10.00, 0.00, 0.00, 0.00, 10.00, '2026-07-27', '2026-08-01', NULL, 'cancelado', '', '00020101021426360014br.gov.bcb.pix011429243448000118520400005303986540510.005802BR5927WD COMUNICAÇÕES DIGITAIS 6009SAO PAULO62070503***63EB27', '', NULL, 'pix_manual_1785181656', NULL, NULL, NULL, NULL, '2026-07-27 19:47:35', '2026-07-27 20:21:14', 'a87db8fb5b915d3232b1fd4d9b53f328581aee3b8611bd9a68c16b86763d3b40', NULL),
(34, 1, 34, 'FAT-202607-0015', 'teste Whats', 5.00, 0.00, 0.00, 0.00, 5.00, '2026-07-29', '2026-08-02', NULL, 'cancelado', '', '00020101021226980014BR.GOV.BCB.PIX2576spi-qrcode.bancointer.com.br/spi/pj/v2/cobv/0fe688b32ae94b7e8cef9d3772a7eef052040000530398654045.005802BR5901*6009BENEVIDES61086879500062070503***63048A55', '', NULL, NULL, '283f2158-0372-4332-afbe-c080d8ee6be1', NULL, NULL, NULL, '2026-07-29 12:35:19', '2026-07-29 12:42:31', '68db7da021350a8c56bd937a56c7608753865336b452fa010361c921ba3ea1f5', 'inter'),
(35, 1, 35, 'FAT-202607-0016', 'teste Whats 2', 5.00, 0.00, 0.00, 0.00, 5.00, '2026-07-29', '2026-08-03', NULL, 'cancelado', '', '00020101021226980014BR.GOV.BCB.PIX2576spi-qrcode.bancointer.com.br/spi/pj/v2/cobv/14e72ce989fe4b84860bafaea2f70ac052040000530398654045.005802BR5901*6009BENEVIDES61086879500062070503***630483C5', '', NULL, NULL, '26d7b774-7e33-4c9e-950f-9eca51b0c421', NULL, NULL, NULL, '2026-07-29 12:35:55', '2026-07-29 12:42:28', 'a18e31ee3dc8af113e8383845e95bb542f2b05afb162311ca7f94a5d982fd886', 'inter'),
(36, 2, 36, 'FAT-202607-0017', 'teste Whats PF', 5.00, 0.00, 0.00, 0.00, 5.00, '2026-07-29', '2026-08-02', NULL, 'cancelado', '', '00020101021226980014BR.GOV.BCB.PIX2576spi-qrcode.bancointer.com.br/spi/pj/v2/cobv/3d7bea605b7a4524b5d81d53ee1c779152040000530398654045.005802BR5901*6009BENEVIDES61086879500062070503***6304CD24', '', NULL, NULL, '13e68c2c-af97-45b9-843c-fb056fc3d112', NULL, NULL, NULL, '2026-07-29 12:44:30', '2026-07-29 12:48:34', 'a43f385f3cafcf92f0b3e346d1b7c5ed23f576eb281b5c41591b3d103fea0047', 'inter'),
(37, 2, 37, 'FAT-202607-0018', 'teste Whats cod', 5.00, 0.00, 0.00, 0.00, 5.00, '2026-07-29', '2026-08-03', NULL, 'cancelado', '', '00020101021226980014BR.GOV.BCB.PIX2576spi-qrcode.bancointer.com.br/spi/pj/v2/cobv/969dcd118257417e9b599bdb3d668e5552040000530398654045.005802BR5901*6009BENEVIDES61086879500062070503***630492AB', '', NULL, NULL, '7de73130-644e-4c18-bfff-4e7df2f926be', NULL, NULL, NULL, '2026-07-29 12:48:55', '2026-07-29 12:50:34', '23dc5a7c15a4994c89be51754b03eaaa84e98e4489f7197cd1f022b2f1c2b359', 'inter'),
(38, 2, 38, 'FAT-202607-0019', 'teste Whats cod', 5.00, 0.00, 0.00, 0.00, 5.00, '2026-07-29', '2026-08-02', NULL, 'cancelado', '', '00020101021226980014BR.GOV.BCB.PIX2576spi-qrcode.bancointer.com.br/spi/pj/v2/cobv/24948e66d8d74c87b521af37ac3b8eb652040000530398654045.005802BR5901*6009BENEVIDES61086879500062070503***63045C53', '', NULL, NULL, '2030bb59-d939-41f3-9431-1a300f2c25fa', NULL, NULL, NULL, '2026-07-29 12:51:55', '2026-07-29 14:33:29', '0729f948edc73fffef17e9512250fda034d20d2f1fba11a25a566f6dad1d8563', 'inter'),
(39, 2, 39, 'FAT-202607-0020', 'teste Whats img', 5.00, 0.00, 0.00, 0.00, 5.00, '2026-07-29', '2026-08-03', NULL, 'cancelado', '', '00020101021226980014BR.GOV.BCB.PIX2576spi-qrcode.bancointer.com.br/spi/pj/v2/cobv/dc3f5243be734260b06365ff333b7f5e52040000530398654045.005802BR5901*6009BENEVIDES61086879500062070503***6304B85A', '', NULL, NULL, 'bb785142-31b5-47f1-8097-acd9341d8b7c', NULL, NULL, NULL, '2026-07-29 13:05:31', '2026-07-29 14:33:27', 'fed6e3b27be6d5fe493cdbf3d88c6beaba4c93bc31bcbddd85867360d3cea17e', 'inter'),
(40, 2, 40, 'FAT-202607-0021', 'teste pix', 5.00, 0.00, 0.00, 0.00, 5.00, '2026-07-29', '2026-08-03', NULL, 'cancelado', '', '00020101021226980014BR.GOV.BCB.PIX2576spi-qrcode.bancointer.com.br/spi/pj/v2/cobv/698c9483eaba49b2a11c8683e3636cf952040000530398654045.005802BR5901*6009BENEVIDES61086879500062070503***6304F1F7', '', NULL, NULL, '4830332a-6c06-424d-b12f-b3c1002374cb', NULL, NULL, NULL, '2026-07-29 14:18:21', '2026-07-29 14:33:24', '2e649b4ca6e66b71e2dfe2be6d03294c610baea47983d3eedf09250fb5b6d8ce', 'inter'),
(41, 2, 41, 'FAT-202607-0022', 'TESTE', 5.00, 0.00, 0.00, 0.00, 5.00, '2026-07-29', '2026-08-04', NULL, 'cancelado', '', '00020101021226980014BR.GOV.BCB.PIX2576spi-qrcode.bancointer.com.br/spi/pj/v2/cobv/bd4b3fa8a7184890985b011d511b957c52040000530398654045.005802BR5901*6009BENEVIDES61086879500062070503***63042279', '', NULL, NULL, '0064f97e-689b-418b-af89-ed27c6801cd5', NULL, '2026-07-29', 'geracao', '2026-07-29 14:47:17', '2026-08-03 18:19:51', '2f552970ce4349136fe91d5bd3f54cc1f0ba97a43f8bbbff35818da417d6ed04', 'inter'),
(42, 2, 42, 'FAT-202607-0023', 'teste', 5.00, 0.00, 0.00, 0.00, 5.00, '2026-07-29', '2026-08-02', NULL, 'cancelado', '', '00020101021226980014BR.GOV.BCB.PIX2576spi-qrcode.bancointer.com.br/spi/pj/v2/cobv/852f2c9abef745e9b247b81a3fffe0a652040000530398654045.005802BR5901*6009BENEVIDES61086879500062070503***6304AC66', '', NULL, NULL, '83f54e83-8caa-4112-8e9d-d00c1ebca98f', NULL, '2026-07-29', 'geracao', '2026-07-29 14:54:30', '2026-08-03 18:19:48', 'f5933ae7d86489aabe739240532349c319da41d32995d68c36cfea2a6b54b280', 'inter'),
(43, 2, 43, 'FAT-202607-0024', 'teste de imagem', 5.00, 0.00, 0.00, 0.00, 5.00, '2026-07-30', '2026-08-01', NULL, 'cancelado', '', '00020101021226980014BR.GOV.BCB.PIX2576spi-qrcode.bancointer.com.br/spi/pj/v2/cobv/12f2855683ae4dabb9183fed64f0d11752040000530398654045.005802BR5901*6009BENEVIDES61086879500062070503***63043D96', '', NULL, NULL, 'c8e81dc4-5f76-4f49-837c-cd3082698a4f', NULL, '2026-07-30', 'geracao', '2026-07-30 14:22:40', '2026-08-03 18:19:44', 'e28a1568a72b175e1fb306ff23031060309563a6a36b14e0c2f59bcad34baaf0', 'inter'),
(44, 2, 44, 'FAT-202607-0025', 'teste qrcode', 10.00, 0.00, 0.00, 0.00, 10.00, '2026-07-30', '2026-08-01', NULL, 'cancelado', '', '00020101021226980014BR.GOV.BCB.PIX2576spi-qrcode.bancointer.com.br/spi/pj/v2/cobv/c67961f81fb64b399111ce4a141a10fa520400005303986540510.005802BR5901*6009BENEVIDES61086879500062070503***6304DACE', '', NULL, NULL, '3c71dceb-3bbb-49fe-8ac9-03f3245c962d', NULL, '2026-07-30', 'geracao', '2026-07-30 16:25:54', '2026-07-30 16:38:47', '0090641251c6591c4777a1d0a2fa55568cb614398e93b68bca751a364be42156', 'inter'),
(45, 2, 45, 'FAT-202607-0026', 'teste', 5.00, 0.00, 0.00, 0.00, 5.00, '2026-07-30', '2026-08-01', NULL, 'cancelado', '', '00020101021226980014BR.GOV.BCB.PIX2576spi-qrcode.bancointer.com.br/spi/pj/v2/cobv/b9316d85dd35471b85d2fee926a1b1f652040000530398654045.005802BR5901*6009BENEVIDES61086879500062070503***6304F928', '', NULL, NULL, '4eb7cfa2-ddff-4d37-895e-a853afc80f36', NULL, '2026-07-30', 'geracao', '2026-07-30 16:39:06', '2026-08-03 18:19:40', '5c52a6879c8aba3c9906c5db4ed4958476807e8b665b1cd9eec2c6915d985a41', 'inter'),
(46, 2, 46, 'FAT-202608-0027', 'teste', 12.00, 0.00, 0.00, 0.00, 12.00, '2026-08-03', '2026-08-10', NULL, 'cancelado', '', '00020101021226980014BR.GOV.BCB.PIX2576spi-qrcode.bancointer.com.br/spi/pj/v2/cobv/5c0c8722f3074c8f9ddb13eb121704fe520400005303986540512.005802BR5901*6009BENEVIDES61086879500062070503***63041A9B', '', NULL, NULL, '408397f4-ebce-4dc0-9148-53f7bd7d8f26', NULL, '2026-08-03', 'geracao', '2026-08-03 18:29:12', '2026-08-03 18:30:40', '22fe0b4ec459332174946d02d18267d6c2a290d15fd28fa9988f8ee1b34cc01d', 'inter'),
(48, 1, 48, 'FAT-202608-0028', 'teste localhost', 2.50, 0.00, 0.00, 0.00, 2.50, '2026-08-11', '2026-08-12', NULL, 'pendente', '', '00020101021226980014BR.GOV.BCB.PIX2576spi-qrcode.bancointer.com.br/spi/pj/v2/cobv/fbbeb0a469e7483aafd0f8db8f52e36652040000530398654042.505802BR5901*6009BENEVIDES61086879500062070503***63048606', '', NULL, NULL, '1e45c483-8166-4c3c-889d-37f0df9bd08e', NULL, '2026-08-11', 'geracao', '2026-08-11 13:42:59', '2026-08-11 13:43:08', 'b193a48a46d3737018986e00172621a01cd568a79eee4930319a430e7fcf5bed', 'inter');

-- --------------------------------------------------------

--
-- Estrutura para tabela `faturas_recorrentes`
--

CREATE TABLE `faturas_recorrentes` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `descricao` varchar(200) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `frequencia` enum('unica','diaria','semanal','quinzenal','mensal','bimestral','trimestral','semestral','anual') NOT NULL DEFAULT 'mensal',
  `dia_vencimento` int(11) DEFAULT 1,
  `data_inicio` date NOT NULL,
  `data_fim` date DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `status` varchar(20) DEFAULT 'ativa',
  `numero` varchar(20) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `faturas_recorrentes`
--

INSERT INTO `faturas_recorrentes` (`id`, `cliente_id`, `descricao`, `valor`, `frequencia`, `dia_vencimento`, `data_inicio`, `data_fim`, `ativo`, `status`, `numero`, `criado_em`) VALUES
(12, 1, 'aluguel da casa', 10.00, 'mensal', 31, '2026-07-20', NULL, 0, 'cancelado', 'FAT-202607-0001', '2026-07-20 21:25:58'),
(13, 1, 'inter', 5.00, 'mensal', 30, '2026-07-21', NULL, 0, 'cancelado', 'FAT-202607-0002', '2026-07-20 22:47:42'),
(14, 1, 'teste 3', 5.00, 'mensal', 31, '2026-07-21', NULL, 0, 'cancelado', 'FAT-202607-0003', '2026-07-21 15:27:52'),
(21, 2, 'testar pagamento', 5.00, 'mensal', 30, '2026-07-23', NULL, 1, 'ativa', 'FAT-202607-0004', '2026-07-23 12:44:24'),
(22, 2, 'teste 2', 5.00, '', 30, '2026-07-24', NULL, 1, 'ativa', 'FAT-202607-0005', '2026-07-23 13:16:27'),
(24, 1, 'teste 4', 5.00, '', 30, '2026-07-23', NULL, 1, 'ativa', 'FAT-202607-0006', '2026-07-23 19:27:22'),
(25, 1, 'Localhost', 5.00, '', 30, '2026-07-25', NULL, 1, 'cancelado', 'FAT-202607-0007', '2026-07-25 13:51:36'),
(26, 1, 'teste Nu Bank', 5.00, '', 2, '2026-07-27', NULL, 1, 'cancelado', 'FAT-202607-0008', '2026-07-27 18:06:28'),
(27, 1, 'teste Nu Bank2', 5.00, '', 2, '2026-07-27', NULL, 1, 'cancelado', 'FAT-202607-0009', '2026-07-27 18:11:43'),
(28, 1, 'teste inter', 5.00, '', 1, '2026-07-27', NULL, 1, 'cancelado', 'FAT-202607-0010', '2026-07-27 18:13:23'),
(29, 1, 'teste Pagbank', 5.00, '', 2, '2026-07-27', NULL, 1, 'cancelado', 'FAT-202607-0011', '2026-07-27 18:32:11'),
(30, 1, 'pix manual', 5.00, '', 2, '2026-07-29', NULL, 1, 'cancelado', 'FAT-202607-0012', '2026-07-27 19:08:08'),
(31, 1, 'teste inter', 5.00, '', 1, '2026-07-27', NULL, 1, 'cancelado', 'FAT-202607-0013', '2026-07-27 19:46:35'),
(32, 1, 'pix manual', 10.00, '', 1, '2026-07-27', NULL, 1, 'cancelado', 'FAT-202607-0014', '2026-07-27 19:47:35'),
(33, 1, 'teste Whats', 5.00, '', 2, '2026-07-31', NULL, 1, 'cancelado', 'FAT-202607-0033', '2026-07-29 12:33:40'),
(34, 1, 'teste Whats', 5.00, '', 2, '2026-07-31', NULL, 1, 'cancelado', 'FAT-202607-0015', '2026-07-29 12:35:19'),
(35, 1, 'teste Whats 2', 5.00, '', 3, '2026-07-30', NULL, 1, 'cancelado', 'FAT-202607-0016', '2026-07-29 12:35:55'),
(36, 2, 'teste Whats PF', 5.00, '', 2, '2026-07-30', NULL, 1, 'cancelado', 'FAT-202607-0017', '2026-07-29 12:44:30'),
(37, 2, 'teste Whats cod', 5.00, '', 3, '2026-07-29', NULL, 1, 'cancelado', 'FAT-202607-0018', '2026-07-29 12:48:55'),
(38, 2, 'teste Whats cod', 5.00, '', 2, '2026-07-30', NULL, 1, 'cancelado', 'FAT-202607-0019', '2026-07-29 12:51:55'),
(39, 2, 'teste Whats img', 5.00, '', 3, '2026-07-31', NULL, 1, 'cancelado', 'FAT-202607-0020', '2026-07-29 13:05:31'),
(40, 2, 'teste pix', 5.00, '', 3, '2026-07-31', NULL, 1, 'cancelado', 'FAT-202607-0021', '2026-07-29 14:18:21'),
(41, 2, 'TESTE', 5.00, '', 4, '2026-07-31', NULL, 1, 'cancelado', 'FAT-202607-0022', '2026-07-29 14:47:17'),
(42, 2, 'teste', 5.00, '', 2, '2026-07-31', NULL, 1, 'cancelado', 'FAT-202607-0023', '2026-07-29 14:54:30'),
(43, 2, 'teste de imagem', 5.00, '', 1, '2026-07-30', NULL, 1, 'cancelado', 'FAT-202607-0024', '2026-07-30 14:22:40'),
(44, 2, 'teste qrcode', 10.00, '', 1, '2026-07-30', NULL, 1, 'cancelado', 'FAT-202607-0025', '2026-07-30 16:25:54'),
(45, 2, 'teste', 5.00, '', 1, '2026-07-30', NULL, 1, 'cancelado', 'FAT-202607-0026', '2026-07-30 16:39:06'),
(46, 2, 'teste', 12.00, '', 10, '2026-08-03', NULL, 1, 'cancelado', 'FAT-202608-0027', '2026-08-03 18:29:12'),
(48, 1, 'teste localhost', 2.50, 'unica', 12, '2026-08-11', NULL, 1, 'ativa', 'FAT-202608-0028', '2026-08-11 13:42:59');

-- --------------------------------------------------------

--
-- Estrutura para tabela `livro_caixa_custos`
--

CREATE TABLE `livro_caixa_custos` (
  `id` int(11) NOT NULL,
  `descricao` varchar(200) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `data` date NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `pago_mes` int(11) DEFAULT NULL,
  `pago_ano` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `livro_caixa_custos`
--

INSERT INTO `livro_caixa_custos` (`id`, `descricao`, `valor`, `data`, `criado_em`, `pago_mes`, `pago_ano`) VALUES
(2, 'aluguel da casa', 1000.00, '2026-08-15', '2026-07-20 20:48:15', 7, 2026),
(7, 'Vivo', 41.00, '2026-07-23', '2026-07-23 13:51:56', NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `livro_caixa_entradas`
--

CREATE TABLE `livro_caixa_entradas` (
  `id` int(11) NOT NULL,
  `descricao` varchar(200) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `fatura_id` int(11) DEFAULT NULL,
  `data` date NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `livro_caixa_saidas`
--

CREATE TABLE `livro_caixa_saidas` (
  `id` int(11) NOT NULL,
  `descricao` varchar(200) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `data` date NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `contexto` varchar(20) NOT NULL,
  `identificador` varchar(100) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `tentativas` int(11) DEFAULT 1,
  `bloqueado_ate` datetime DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `contexto`, `identificador`, `ip`, `tentativas`, `bloqueado_ate`, `criado_em`, `atualizado_em`) VALUES
(1, 'admin', 'all', '::1', 1, NULL, '2026-07-24 14:20:19', '2026-07-24 14:20:19'),
(2, 'user', 'all', '::1', 1, NULL, '2026-07-30 16:05:02', '2026-07-30 16:05:02');

-- --------------------------------------------------------

--
-- Estrutura para tabela `pagamentos_log`
--

CREATE TABLE `pagamentos_log` (
  `id` int(11) NOT NULL,
  `fatura_id` int(11) NOT NULL,
  `mp_payment_id` varchar(100) DEFAULT NULL,
  `mp_status` varchar(50) DEFAULT NULL,
  `mp_status_detail` varchar(100) DEFAULT NULL,
  `valor_pago` decimal(10,2) DEFAULT NULL,
  `tipo_pagamento` varchar(50) DEFAULT NULL,
  `dados_raw` text DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `pagamentos_log`
--

INSERT INTO `pagamentos_log` (`id`, `fatura_id`, `mp_payment_id`, `mp_status`, `mp_status_detail`, `valor_pago`, `tipo_pagamento`, `dados_raw`, `criado_em`) VALUES
(1, 22, '6b6269d2-7edc-4834-9cea-8e6392c3c394', 'approved', 'Baixa automatica via cron', 5.00, 'inter', '{\"source\":\"cron\"}', '2026-07-23 13:11:41'),
(2, 23, 'af6f16d7-bb97-4383-a4d6-84e36fc78d11', 'approved', 'Verificado via polling', 5.00, 'inter', '{\"source\":\"verificar_status\"}', '2026-07-23 13:21:03');

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios_admin`
--

CREATE TABLE `usuarios_admin` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `perfil` enum('admin','financeiro','atendimento') DEFAULT 'atendimento',
  `ativo` tinyint(1) DEFAULT 1,
  `avatar` varchar(255) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `ultimo_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `administradores`
--
ALTER TABLE `administradores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario` (`usuario`);

--
-- Índices de tabela `admin_certificados`
--
ALTER TABLE `admin_certificados`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Índices de tabela `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cpf_cnpj` (`cpf_cnpj`),
  ADD KEY `idx_clientes_cpf_cnpj` (`cpf_cnpj`);

--
-- Índices de tabela `configuracoes`
--
ALTER TABLE `configuracoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `chave` (`chave`);

--
-- Índices de tabela `contratos`
--
ALTER TABLE `contratos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero` (`numero`),
  ADD KEY `cliente_id` (`cliente_id`);

--
-- Índices de tabela `faturas`
--
ALTER TABLE `faturas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero` (`numero`),
  ADD KEY `fatura_recorrente_id` (`fatura_recorrente_id`),
  ADD KEY `idx_faturas_cliente` (`cliente_id`),
  ADD KEY `idx_faturas_status` (`status`),
  ADD KEY `idx_faturas_vencimento` (`data_vencimento`);

--
-- Índices de tabela `faturas_recorrentes`
--
ALTER TABLE `faturas_recorrentes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cliente_id` (`cliente_id`);

--
-- Índices de tabela `livro_caixa_custos`
--
ALTER TABLE `livro_caixa_custos`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `livro_caixa_entradas`
--
ALTER TABLE `livro_caixa_entradas`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `livro_caixa_saidas`
--
ALTER TABLE `livro_caixa_saidas`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_contexto_id` (`contexto`,`identificador`),
  ADD KEY `idx_ip` (`ip`);

--
-- Índices de tabela `pagamentos_log`
--
ALTER TABLE `pagamentos_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fatura_id` (`fatura_id`);

--
-- Índices de tabela `usuarios_admin`
--
ALTER TABLE `usuarios_admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `usuario` (`usuario`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `administradores`
--
ALTER TABLE `administradores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `admin_certificados`
--
ALTER TABLE `admin_certificados`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `configuracoes`
--
ALTER TABLE `configuracoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=278;

--
-- AUTO_INCREMENT de tabela `contratos`
--
ALTER TABLE `contratos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `faturas`
--
ALTER TABLE `faturas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT de tabela `faturas_recorrentes`
--
ALTER TABLE `faturas_recorrentes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT de tabela `livro_caixa_custos`
--
ALTER TABLE `livro_caixa_custos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de tabela `livro_caixa_entradas`
--
ALTER TABLE `livro_caixa_entradas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `livro_caixa_saidas`
--
ALTER TABLE `livro_caixa_saidas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `pagamentos_log`
--
ALTER TABLE `pagamentos_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `usuarios_admin`
--
ALTER TABLE `usuarios_admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `admin_certificados`
--
ALTER TABLE `admin_certificados`
  ADD CONSTRAINT `admin_certificados_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `administradores` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `contratos`
--
ALTER TABLE `contratos`
  ADD CONSTRAINT `contratos_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `faturas`
--
ALTER TABLE `faturas`
  ADD CONSTRAINT `faturas_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `faturas_ibfk_2` FOREIGN KEY (`fatura_recorrente_id`) REFERENCES `faturas_recorrentes` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `faturas_recorrentes`
--
ALTER TABLE `faturas_recorrentes`
  ADD CONSTRAINT `faturas_recorrentes_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `pagamentos_log`
--
ALTER TABLE `pagamentos_log`
  ADD CONSTRAINT `pagamentos_log_ibfk_1` FOREIGN KEY (`fatura_id`) REFERENCES `faturas` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
