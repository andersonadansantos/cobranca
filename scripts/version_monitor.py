#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Monitor de versionamento automático (executado no HOST da VPS, via cron).

Detecta novas ações de deployment concluídas com sucesso (status='done') no
banco do Easypanel e incrementa a versão do sistema em +0.1. O valor é gravado
em um bind mount persistente, lido pelo PHP através de config/version.php.

Ações com status 'error' ou 'cancelled' são ignoradas. Cada deploy concluído
é processado uma única vez, registrando-se o createdAt da última action.

Uso:
    python3 /root/version_monitor.py            # processa novos deploys
    python3 /root/version_monitor.py --init     # inicializa sem incrementar

 Variáveis de ambiente opcionais (facilitam testes fora da VPS):
    APP_VERSION_DB, APP_VERSION_SERVICE,
    APP_VERSION_DIR, APP_VERSION_STATE
"""

import os
import sqlite3
import sys
from decimal import Decimal, InvalidOperation

DB_PATH = os.environ.get('APP_VERSION_DB', '/etc/easypanel/data/data.sdb')
SERVICE = os.environ.get('APP_VERSION_SERVICE', 'centraldefaturas_com_br')
PERSIST_DIR = os.environ.get(
    'APP_VERSION_DIR',
    '/root/appdata/centraldefaturas/marketing_imports/.system',
)
VERSION_FILE = os.path.join(PERSIST_DIR, 'version.txt')
STATE_FILE = os.environ.get(
    'APP_VERSION_STATE',
    '/root/appdata/centraldefaturas/.version_last_deploy',
)
VERSAO_INICIAL = '1.0'


def ler_versao():
    try:
        with open(VERSION_FILE, 'r', encoding='utf-8') as arquivo:
            valor = arquivo.read().strip()
        if Decimal(valor) >= Decimal('0'):
            return valor
    except (OSError, InvalidOperation, ValueError):
        pass
    return VERSAO_INICIAL


def gravar_versao(valor):
    os.makedirs(PERSIST_DIR, exist_ok=True)
    temporario = VERSION_FILE + '.tmp'
    with open(temporario, 'w', encoding='utf-8') as arquivo:
        arquivo.write(valor + '\n')
    os.replace(temporario, VERSION_FILE)


def ler_estado():
    try:
        with open(STATE_FILE, 'r', encoding='utf-8') as arquivo:
            return arquivo.read().strip()
    except OSError:
        return None


def gravar_estado(valor):
    diretorio = os.path.dirname(STATE_FILE)
    if diretorio:
        os.makedirs(diretorio, exist_ok=True)
    temporario = STATE_FILE + '.tmp'
    with open(temporario, 'w', encoding='utf-8') as arquivo:
        arquivo.write(valor + '\n')
    os.replace(temporario, STATE_FILE)


def deployments_concluidos():
    conexao = sqlite3.connect('file:{}?mode=ro'.format(DB_PATH), uri=True)
    try:
        cursor = conexao.cursor()
        cursor.execute(
            """SELECT createdAt FROM actions
               WHERE serviceName = ? AND type = 'deployment'
                 AND status = 'done'
               ORDER BY createdAt ASC, rowid ASC""",
            (SERVICE,),
        )
        return [linha[0] for linha in cursor.fetchall()]
    finally:
        conexao.close()


def main():
    concluidos = deployments_concluidos()
    if not concluidos:
        return 0

    ultimo = concluidos[-1]
    estado = ler_estado()

    # Na instalação, apenas registra o deploy atual como já processado,
    # sem incrementar: a versão inicial permanece 1.0.
    if '--init' in sys.argv or estado is None:
        gravar_versao(ler_versao())
        gravar_estado(ultimo)
        print('versionamento inicializado: versao={} estado={}'.format(ler_versao(), ultimo))
        return 0

    novos = [created for created in concluidos if created > estado]
    if not novos:
        return 0

    versao = ler_versao()
    for created in novos:
        atual = Decimal(versao)
        versao = '{:.1f}'.format(atual + Decimal('0.1'))
        gravar_versao(versao)
        gravar_estado(created)
        print('deploy {}: versao {} -> {}'.format(created, atual, versao))

    return 0


if __name__ == '__main__':
    sys.exit(main())
