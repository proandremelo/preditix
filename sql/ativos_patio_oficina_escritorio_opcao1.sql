-- =============================================================================
-- Opção 1: três tabelas separadas (pátio, oficina, escritório)
-- + extensão do ENUM tipo_equipamento em ordens_servico (e manutencoes, se existir)
-- Executar em ambiente de homologação antes de produção.
-- =============================================================================

-- -----------------------------------------------------------------------------
-- Tabelas de ativos (estrutura alinhada a tanques / cadastro fixo)
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `patios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tag` varchar(50) DEFAULT NULL,
  `nome` varchar(150) NOT NULL,
  `localizacao` varchar(255) DEFAULT NULL,
  `area_m2` decimal(12,2) DEFAULT NULL,
  `observacoes` text,
  `foto` varchar(255) DEFAULT NULL,
  `status` enum('ativo','inativo','manutencao') DEFAULT 'ativo',
  `data_criacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `data_atualizacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tag` (`tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `oficinas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tag` varchar(50) DEFAULT NULL,
  `nome` varchar(150) NOT NULL,
  `localizacao` varchar(255) DEFAULT NULL,
  `area_m2` decimal(12,2) DEFAULT NULL,
  `observacoes` text,
  `foto` varchar(255) DEFAULT NULL,
  `status` enum('ativo','inativo','manutencao') DEFAULT 'ativo',
  `data_criacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `data_atualizacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tag` (`tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `escritorios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tag` varchar(50) DEFAULT NULL,
  `nome` varchar(150) NOT NULL,
  `localizacao` varchar(255) DEFAULT NULL,
  `area_m2` decimal(12,2) DEFAULT NULL,
  `observacoes` text,
  `foto` varchar(255) DEFAULT NULL,
  `status` enum('ativo','inativo','manutencao') DEFAULT 'ativo',
  `data_criacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `data_atualizacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tag` (`tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- ordens_servico: incluir patio, oficina, escritorio no ENUM
-- (ajuste a lista se o seu ENUM atual for diferente)
-- -----------------------------------------------------------------------------

ALTER TABLE `ordens_servico`
  MODIFY COLUMN `tipo_equipamento` enum(
    'embarcacao',
    'veiculo',
    'implemento',
    'tanque',
    'patio',
    'oficina',
    'escritorio'
  ) COLLATE utf8mb4_unicode_ci NOT NULL;

-- -----------------------------------------------------------------------------
-- Opcional: tabela manutencoes (se existir no seu banco com o mesmo ENUM)
-- Descomente se aplicável.
-- -----------------------------------------------------------------------------

-- ALTER TABLE `manutencoes`
--   MODIFY COLUMN `tipo_equipamento` enum(
--     'embarcacao',
--     'implemento',
--     'tanque',
--     'veiculo',
--     'patio',
--     'oficina',
--     'escritorio'
--   ) NOT NULL;
