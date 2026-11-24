<?php
// =====================================================
// CONTROLE CENTRAL DE VERSÃO DO SISTEMA
// =====================================================
//
// Versão automática no rodapé de todo o sistema.
// - Commit Git: .githooks/pre-commit incrementa +0.1 em config/version.txt.
// - Deploy VPS: scripts/version_monitor.py (cron no host) incrementa +0.1
//   no arquivo persistente; na leitura, o sistema usa a maior versão válida
//   entre repo e persistente, garantindo atualização em ambos os fluxos.

if (!function_exists('sistemaVersao')) {
    function sistemaVersao() {
        static $versao = null;

        if ($versao !== null) {
            return $versao;
        }

        $raiz = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__);
        $candidatas = [];

        // Fonte 1: versão versionada no repositório (atualizada no pre-commit).
        $valorRepo = _sistemaVersaoLerArquivo($raiz . '/config/version.txt');
        if ($valorRepo !== null) {
            $candidatas[] = $valorRepo;
        }

        // Fonte 2: arquivos persistentes da VPS (atualizados pelo monitor de deploy).
        $arquivosPersistentes = [
            $raiz . '/config/marketing_imports/.system/version.txt',
            $raiz . '/config/marketing_imports/.system_version.txt',
        ];
        foreach ($arquivosPersistentes as $arquivo) {
            $valor = _sistemaVersaoLerArquivo($arquivo);
            if ($valor !== null) {
                $candidatas[] = $valor;
            }
        }

        if (empty($candidatas)) {
            return $versao = '1.0';
        }

        usort($candidatas, function ($a, $b) {
            return version_compare($a, $b);
        });
        return $versao = (string) end($candidatas);
    }
}

if (!function_exists('_sistemaVersaoLerArquivo')) {
    function _sistemaVersaoLerArquivo($arquivo) {
        if (!is_file($arquivo) || !is_readable($arquivo)) {
            return null;
        }
        $conteudo = trim((string)file_get_contents($arquivo));
        $conteudo = preg_replace('/^\xEF\xBB\xBF/', '', $conteudo);
        if (preg_match('/^\d+\.\d+$/', $conteudo)) {
            return $conteudo;
        }
        return null;
    }
}
