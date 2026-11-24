<?php
// =====================================================
// CONTROLE CENTRAL DE VERSÃO DO SISTEMA
// =====================================================
//
// A versão é lida de config/version.txt (versionada no repositório e
// incrementada automaticamente pelo hook .githooks/pre-commit antes de cada
// commit). Em produção, se existir uma versão gravada pelo monitor no volume
// persistente, ela tem prioridade por ser a fonte operacional da VPS.

if (!function_exists('sistemaVersao')) {
    function sistemaVersao() {
        static $versao = null;

        if ($versao !== null) {
            return $versao;
        }

        $raiz = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__);

        // Prioridade 1: versão versionada no repositório (atualizada no pre-commit).
        // É a fonte que acompanha cada commit, garantindo que o rodapé mude
        // automaticamente após commit/deploy.
        $valorRepo = _sistemaVersaoLerArquivo($raiz . '/config/version.txt');
        if ($valorRepo !== null) {
            return $versao = $valorRepo;
        }

        // Prioridade 2: fallback operacional legado no volume persistente da VPS.
        $arquivosPersistentes = [
            $raiz . '/config/marketing_imports/.system/version.txt',
            $raiz . '/config/marketing_imports/.system_version.txt',
        ];
        foreach ($arquivosPersistentes as $arquivo) {
            $valor = _sistemaVersaoLerArquivo($arquivo);
            if ($valor !== null) {
                return $versao = $valor;
            }
        }

        return $versao = '1.0';
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
