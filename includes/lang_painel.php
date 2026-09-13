<?php
// =====================================================
// MULTILÍNGUE DOS PAINÉIS (admin, superadmin, usuario)
// Compartilha o idioma escolhido no site (cookie cobranca_site_idioma)
// e aceita o parâmetro ?idioma= (mesmo padrão do site).
// =====================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function painelIdiomas() {
    return [
        'pt-BR' => 'Português (Brasil)',
        'es-MX' => 'Español (México)',
        'es-AR' => 'Español (Argentina)',
        'es-CO' => 'Español (Colombia)',
        'es-CL' => 'Español (Chile)',
        'es-PE' => 'Español (Perú)',
    ];
}

function painelBandeira($cod) {
    $mapa = [
        'pt-BR' => 'br.svg',
        'es-MX' => 'mx.svg',
        'es-AR' => 'ar.svg',
        'es-CO' => 'co.svg',
        'es-CL' => 'cl.svg',
        'es-PE' => 'pe.svg',
    ];
    return $mapa[$cod] ?? 'br.svg';
}

function painelIdiomaValido($id) {
    return array_key_exists((string)$id, painelIdiomas());
}

function painelIdiomaAtual() {
    $id = $_GET['idioma'] ?? ($_COOKIE['cobranca_site_idioma'] ?? 'pt-BR');
    $id = preg_replace('/[^a-zA-Z0-9-]/', '', substr((string)$id, 0, 15));
    return painelIdiomaValido($id) ? $id : 'pt-BR';
}

// Ao escolher idioma via ?idioma=, salva o cookie e já renderiza nesse idioma
$painelNovoIdioma = null;
if (isset($_GET['idioma'])) {
    $painelNovoIdioma = painelIdiomaAtual();
    if ($painelNovoIdioma !== ($_COOKIE['cobranca_site_idioma'] ?? '')) {
        setcookie('cobranca_site_idioma', $painelNovoIdioma, time() + 31536000, '/');
        $_COOKIE['cobranca_site_idioma'] = $painelNovoIdioma;
    }
}

$GLOBALS['painel_pt'] = [
    // Layout
    'layout.admin_area' => 'Admin',
    'layout.area_cliente' => 'Área do Cliente',
    'layout.menu_label' => 'Menu Principal',
    'layout.menu' => 'Menu',
    'layout.conta' => 'Conta',
    'layout.super_admin' => 'Super Admin',
    'layout.conta_desativada' => 'Conta Desativada',
    'layout.plano_expirado' => 'Plano Expirado',
    'layout.conta_desativada_msg' => 'Sua conta foi desativada pelo administrador. Contate o suporte para reativar o acesso.',
    'layout.plano_vencido_msg' => 'Seu plano está vencido. Renove para continuar usando o painel.',
    'layout.sem_plano_titulo' => 'Escolha um plano',
    'layout.sem_plano_msg' => 'A emissão de faturas e as configurações são liberadas quando você escolhe um plano.',
    'layout.sem_plano_btn' => 'Escolher plano',

    // Navegação (painel do admin/usuário)
    'nav.painel_geral' => 'Painel Geral',
    'nav.cadastro' => 'Cadastro',
    'nav.emissao' => 'Emissão de Faturas',
    'nav.livro_caixa' => 'Livro Caixa',
    'nav.inadimplencia' => 'Inadimplência',
    'nav.configuracoes' => 'Configurações',
    'nav.api_pagamento' => 'API de Pagamento',
    'nav.personalizacao' => 'Personalização',
    'nav.banners' => 'Banners',
    'nav.config_envios' => 'Config. de Envios',
    'nav.template_email' => 'Template E-mail',
    'nav.template_whats' => 'Template Whats',
    'nav.template_recibo' => 'Template Recibo',
    'nav.contato_financeiro' => 'Contato/Financeiro',
    'nav.config_whatsapp' => 'Config. Whatsapp',
    'nav.meu_plano' => 'Meu Plano',
    'nav.minhas_faturas' => 'Minhas Faturas',
    'nav.usuarios_admin' => 'Usuários Admin',
    'nav.meu_perfil' => 'Meu Perfil',
    'nav.voltar_super' => 'Voltar ao Super Admin',
    'nav.sair' => 'Sair',

    // Navegação (super admin)
    'nav.dashboard' => 'Dashboard',
    'nav.api_admins' => 'API Admins',
    'nav.planos' => 'Planos',
    'nav.faturas' => 'Faturas',
    'nav.tutoriais' => 'Tutoriais',
    'nav.cron_job' => 'Cron Job',

    // Topbar comum
    'tb.suporte' => 'Suporte',
    'tb.editar_perfil' => 'Editar Perfil',
    'tb.sair' => 'Sair',
    'tb.renovar' => 'Renovar',
    'tb.plano_vencido' => 'Plano vencido',
    'tb.vence_hoje' => 'Vence hoje',
    'tb.dias_restantes' => '%d dia(s) restantes',
    'tb.plano' => 'Plano:',
    'tb.super_admin_tag' => 'Super Admin',
    'tb.meu_subdominio' => 'Seu subdomínio',
    'tb.copiar_subdominio' => 'Copiar endereço do seu subdomínio',
    'tb.area_usuario' => 'Área do usuário',
    'tb.sem_subdominio' => 'Sem subdomínio definido',

    // Login
    'login.titulo' => 'Login Admin',
    'login.acesse' => 'Acesse sua conta no painel %s',
    'login.entre' => 'Entre com suas credenciais e acesse seu painel %s.',
    'login.usuario' => 'Usuário',
    'login.ph_usuario' => 'Digite seu usuário',
    'login.senha' => 'Senha',
    'login.ph_senha' => 'Digite sua senha',
    'login.entrar' => 'Entrar',
    'login.lembrar' => 'Esqueceu sua senha?',
    'login.mostrar' => 'Mostrar ou ocultar senha',
    'login.voltar_cliente' => 'Voltar para Login do Cliente',
    'login.dev' => 'Desenvolvido por WD Soluções Digitais.',
    'login.erro_rate' => 'Muitas tentativas. Tente novamente em %d minuto(s).',
    'login.erro_turnstile' => 'Confirme que você não é um robô.',
    'login.erro_falha' => 'Falha na verificação. Tente novamente.',
    'login.erro_vazio' => 'Preencha todos os campos.',
    'login.erro_invalido' => 'Usuário ou senha inválidos.',

    // Dashboard (admin)
    'dash.titulo' => 'Painel Geral',
    'dash.clientes' => 'Clientes Cadastrados',
    'dash.faturas_aberto' => 'Faturas em Aberto',
    'dash.recebido_mes' => 'Recebido no Mês',
    'dash.total_pendente' => 'Total Pendente',
    'dash.receita_mensal' => 'Receita Mensal (últimos 6 meses)',
    'dash.status_faturas' => 'Status das Faturas',
    'dash.livro_caixa_mes' => 'Livro Caixa — Mês Atual (%s)',
    'dash.resumo_livro' => 'Resumo Livro Caixa',
    'dash.entradas' => 'Entradas',
    'dash.saidas' => 'Saídas',
    'dash.custos_fixos' => 'Custos Fixos',
    'dash.saldo' => 'Saldo',
    'dash.ver_livro' => 'Ver Livro Caixa Completo',
    'dash.faturas_recentes' => 'Faturas Recentes',
    'dash.alert_emp_incompletos' => 'Dados da empresa incompletos.',
    'dash.alert_campos_faltando' => 'Campos faltando: %s',
    'dash.preencher_agora' => 'Preencher agora →',
    'dash.alert_vencendo' => '%d fatura(s) vencendo em até 3 dias — Total: R$ %s',
    'dash.vence_em' => 'vence em %s',
    'dash.hoje' => 'hoje',
    'dash.quant_registros' => 'Mostrando %d de %d registros',
    'dash.pagina' => '%d/página',
    'dash.receita_r' => 'Receita (R$)',
    'dash.valor_r' => 'Valor (R$)',

    // Tabelas
    'th.numero' => 'Número',
    'th.cliente' => 'Cliente',
    'th.valor' => 'Valor',
    'th.vencimento' => 'Vencimento',
    'th.status' => 'Status',
    'th.acoes' => 'Ações',
    'th.descricao' => 'Descrição',
    'th.emissao' => 'Emissão',
    'th.nome' => 'Nome',
    'th.usuario' => 'Usuário',
    'th.email' => 'E-mail',
    'th.perfil' => 'Perfil',
    'th.plano' => 'Plano',
    'th.whatsapp' => 'WhatsApp',
    'th.ult_login' => 'Último Login',
    'th.n' => 'Nº',
    'th.admin' => 'Admin',
    'th.periodo' => 'Período',
    'th.gerada_em' => 'Gerada em',
    'th.paga_em' => 'Paga em',
    'txt.nenhuma_fatura' => 'Nenhuma fatura encontrada',
    'txt.nenhum_admin' => 'Nenhum admin cadastrado',
    'txt.nenhum_usuario' => 'Nenhum usuário cadastrado',

    // Status
    'st.pago' => 'Pago',
    'st.pendente' => 'Pendente',
    'st.vencido' => 'Vencido',
    'st.atrasado' => 'Atrasado',
    'st.cancelado' => 'Cancelado',
    'st.expirado' => 'Expirado',
    'st.livre' => 'Livre (Sem vencimento)',
    'st.parcelas' => 'Parcelas',

    // Filtros/botões comuns
    'filtro.status' => 'Status',
    'filtro.cliente' => 'Cliente',
    'filtro.mes' => 'Mês',
    'filtro.ano' => 'Ano',
    'filtro.todos' => 'Todos',
    'filtro.todos_status' => 'Todos os status',
    'filtro.titulo' => 'Filtros',
    'filtro.buscar' => 'Buscar admin, plano ou nº...',
    'btn.filtrar' => 'Filtrar',
    'btn.exportar_csv' => 'Exportar CSV',
    'btn.limpar_filtros' => 'Limpar filtros',
    'btn.novo_usuario' => 'Novo Usuário',
    'btn.novo_admin' => 'Novo Admin',
    'btn.cancelar' => 'Cancelar',
    'btn.salvar' => 'Salvar',
    'btn.criar_usuario' => 'Criar Usuário',
    'btn.ok' => 'OK',
    'btn.confirmar' => 'Confirmar',
    'btn.desativar' => 'Desativar',
    'btn.ativar' => 'Ativar',

    // Faturas
    'fat.titulo' => 'Faturas',
    'fat.lista' => 'Faturas (%d)',
    'fat.copiar_pix' => 'Copiar PIX',
    'fat.link_pagamento' => 'Link Pagamento',
    'fat.cancelar' => 'Cancelar',
    'fat.tit_cancelar' => 'Cancelar Fatura',
    'fat.confirm_cancelar' => 'Deseja cancelar a fatura %s?',
    'fat.super_admins' => 'Faturas Geradas pelos Admins',
    'fat.stats_filtro' => 'Faturas (filtro atual)',
    'fat.total_geral' => 'Total geral: %s',
    'fat.pagas' => 'Pagas',
    'fat.pendentes' => 'Pendentes',
    'fat.total_recebido' => 'Total Recebido',
    'fat.total_reembolsado' => 'Total Reembolsado (filtro)',

    // Usuários admin
    'uso.titulo' => 'Usuários Admin',
    'uso.cadastrados' => 'Usuários Cadastrados',
    'uso.lista' => 'Usuários Cadastrados',
    'uso.ativo' => 'Ativo',
    'uso.inativo' => 'Inativo',
    'uso.perfil_atendimento' => 'Atendimento',
    'uso.perfil_financeiro' => 'Financeiro',
    'uso.perfil_admin' => 'Administrador',
    'uso.senha' => 'Senha',
    'uso.nova_senha' => 'Nova senha (deixe vazio para manter)',
    'uso.editar' => 'Editar',
    'uso.excluir' => 'Excluir',
    'uso.remover' => 'Remover %s?',
    'uso.nao' => 'Não',
    'uso.sim_excluir' => 'Sim, excluir',

    // Super admin — dashboard
    'sup.titulo' => 'Dashboard Super Admin',
    'sup.admins_ativos' => 'Admins Ativos',
    'sup.com_whats' => 'Com WhatsApp',
    'sup.sem_whats' => 'Sem WhatsApp',
    'sup.planos' => 'Planos',
    'sup.plano_nome' => 'Plano %s',
    'sup.admins_neste_plano' => 'Admins neste plano',
    'sup.admins_cadastrados' => 'Admins Cadastrados (%d)',
    'sup.logar_como' => 'Logar como este admin',
    'sup.badge_site' => 'Site',
    'sup.badge_demo' => 'Demo',
    'sup.badge_sem_plano' => 'Sem plano',
    'sup.badge_configurado' => 'Configurado',
    'sup.badge_bloqueado' => 'Bloqueado',

    // Super admin — cadastros
    'cad.titulo' => 'Cadastros de Admins',
    'cad.novo_admin_tit' => 'Novo Admin',
    'cad.subdominio' => 'Subdomínio',
    'cad.subdominio_hint' => 'Acesso pelo subdomínio .seudominio.com (ex.: clientea.seudominio.com)',
    'cad.vencimento_plano' => 'Vencimento do plano',
    'cad.dados_empresa' => 'Dados da empresa (para emissão de boleto e PIX)',
    'cad.razao_social' => 'Razão Social',
    'cad.nome_fantasia' => 'Nome Fantasia',
    'cad.cnpj' => 'CNPJ',
    'cad.cpf' => 'CPF',
    'cad.insc_estadual' => 'Inscrição Estadual',
    'cad.insc_municipal' => 'Inscrição Municipal',
    'cad.tel_comercial' => 'Telefone Comercial',
    'cad.email_comercial' => 'E-mail Comercial',
    'cad.cep' => 'CEP',
    'cad.uf' => 'UF',
    'cad.logradouro' => 'Logradouro',
    'cad.numero' => 'Número',
    'cad.complemento' => 'Complemento',
    'cad.bairro' => 'Bairro',
    'cad.cidade' => 'Cidade',
    'cad.admin_ativo' => 'Admin ativo',
    'cad.btn_cadastrar' => 'Cadastrar Admin',
    'cad.admins_count' => 'Admins (%d)',
    'cad.tit_editar' => 'Editar Admin: %s',

    // Mobile (área do usuário)
    'usuario.faturas' => 'Faturas',
    'usuario.financeiro' => 'Financeiro',
    'usuario.perfil' => 'Perfil',
    'usuario.sair' => 'Sair',

    // Modais / JS
    'modal.pix_copiado' => 'Código PIX copiado!',
    'modal.excluir' => 'Excluir',
    'modal.confirm_excluir' => 'Deseja excluir <strong>%s</strong>? Esta ação não pode ser desfeita.',
    'modal.confirmar_exclusao' => 'Confirmar Exclusão',
    'modal.tem_certeza_excluir' => 'Tem certeza que deseja excluir o admin <strong>%s</strong>? Esta ação não pode ser desfeita.',
    'modal.digite_deletar' => 'Digite <strong>DELETAR</strong> para confirmar:',

    // Meses
    'mes_1' => 'Janeiro', 'mes_2' => 'Fevereiro', 'mes_3' => 'Março',
    'mes_4' => 'Abril', 'mes_5' => 'Maio', 'mes_6' => 'Junho',
    'mes_7' => 'Julho', 'mes_8' => 'Agosto', 'mes_9' => 'Setembro',
    'mes_10' => 'Outubro', 'mes_11' => 'Novembro', 'mes_12' => 'Dezembro',
    'mes_abbr_1' => 'Jan', 'mes_abbr_2' => 'Fev', 'mes_abbr_3' => 'Mar',
    'mes_abbr_4' => 'Abr', 'mes_abbr_5' => 'Mai', 'mes_abbr_6' => 'Jun',
    'mes_abbr_7' => 'Jul', 'mes_abbr_8' => 'Ago', 'mes_abbr_9' => 'Set',
    'mes_abbr_10' => 'Out', 'mes_abbr_11' => 'Nov', 'mes_abbr_12' => 'Dez',
];

$GLOBALS['painel_es'] = [
    // Layout
    'layout.admin_area' => 'Admin',
    'layout.area_cliente' => 'Área del Cliente',
    'layout.menu_label' => 'Menú Principal',
    'layout.menu' => 'Menú',
    'layout.conta' => 'Cuenta',
    'layout.super_admin' => 'Super Admin',
    'layout.conta_desativada' => 'Cuenta Desactivada',
    'layout.plano_expirado' => 'Plan Expirado',
    'layout.conta_desativada_msg' => 'Tu cuenta fue desactivada por el administrador. Contacta al soporte para reactivar el acceso.',
    'layout.plano_vencido_msg' => 'Tu plan está vencido. Renueva para seguir usando el panel.',
    'layout.sem_plano_titulo' => 'Elige un plan',
    'layout.sem_plano_msg' => 'La emisión de facturas y la configuración se liberan cuando eliges un plan.',
    'layout.sem_plano_btn' => 'Elegir plan',

    // Navegação
    'nav.painel_geral' => 'Panel General',
    'nav.cadastro' => 'Registro',
    'nav.emissao' => 'Emisión de Facturas',
    'nav.livro_caixa' => 'Libro Caja',
    'nav.inadimplencia' => 'Morosidad',
    'nav.configuracoes' => 'Configuración',
    'nav.api_pagamento' => 'API de Pago',
    'nav.personalizacao' => 'Personalización',
    'nav.banners' => 'Banners',
    'nav.config_envios' => 'Config. de Envíos',
    'nav.template_email' => 'Plantilla E-mail',
    'nav.template_whats' => 'Plantilla Whats',
    'nav.template_recibo' => 'Plantilla Recibo',
    'nav.contato_financeiro' => 'Contacto/Finanzas',
    'nav.config_whatsapp' => 'Config. WhatsApp',
    'nav.meu_plano' => 'Mi Plan',
    'nav.minhas_faturas' => 'Mis Facturas',
    'nav.usuarios_admin' => 'Usuarios Admin',
    'nav.meu_perfil' => 'Mi Perfil',
    'nav.voltar_super' => 'Volver al Super Admin',
    'nav.sair' => 'Salir',

    // Navegação (super admin)
    'nav.dashboard' => 'Dashboard',
    'nav.api_admins' => 'API Admins',
    'nav.planos' => 'Planes',
    'nav.faturas' => 'Facturas',
    'nav.tutoriais' => 'Tutoriales',
    'nav.cron_job' => 'Cron Job',

    // Topbar comum
    'tb.suporte' => 'Soporte',
    'tb.editar_perfil' => 'Editar Perfil',
    'tb.sair' => 'Salir',
    'tb.renovar' => 'Renovar',
    'tb.plano_vencido' => 'Plan vencido',
    'tb.vence_hoje' => 'Vence hoy',
    'tb.dias_restantes' => '%d día(s) restantes',
    'tb.plano' => 'Plan:',
    'tb.super_admin_tag' => 'Super Admin',
    'tb.meu_subdominio' => 'Tu subdominio',
    'tb.copiar_subdominio' => 'Copiar dirección de tu subdominio',
    'tb.area_usuario' => 'Área del usuario',
    'tb.sem_subdominio' => 'Sin subdominio definido',

    // Login
    'login.titulo' => 'Login Admin',
    'login.acesse' => 'Inicia sesión en tu panel de %s',
    'login.entre' => 'Ingresa con tus credenciales para acceder a tu panel %s.',
    'login.usuario' => 'Usuario',
    'login.ph_usuario' => 'Ingresa tu usuario',
    'login.senha' => 'Contraseña',
    'login.ph_senha' => 'Ingresa tu contraseña',
    'login.entrar' => 'Ingresar',
    'login.lembrar' => '¿Olvidaste tu contraseña?',
    'login.mostrar' => 'Mostrar u ocultar contraseña',
    'login.voltar_cliente' => 'Volver al Login del Cliente',
    'login.dev' => 'Desarrollado por WD Soluções Digitais.',
    'login.erro_rate' => 'Demasiados intentos. Inténtalo de nuevo en %d minuto(s).',
    'login.erro_turnstile' => 'Confirma que no eres un robot.',
    'login.erro_falha' => 'Fallo en la verificación. Inténtalo de nuevo.',
    'login.erro_vazio' => 'Completa todos los campos.',
    'login.erro_invalido' => 'Usuario o contraseña inválidos.',

    // Dashboard (admin)
    'dash.titulo' => 'Panel General',
    'dash.clientes' => 'Clientes Registrados',
    'dash.faturas_aberto' => 'Facturas Pendientes',
    'dash.recebido_mes' => 'Recibido en el Mes',
    'dash.total_pendente' => 'Total Pendiente',
    'dash.receita_mensal' => 'Ingresos Mensuales (últimos 6 meses)',
    'dash.status_faturas' => 'Estado de las Facturas',
    'dash.livro_caixa_mes' => 'Libro Caja — Mes Actual (%s)',
    'dash.resumo_livro' => 'Resumen Libro Caja',
    'dash.entradas' => 'Ingresos',
    'dash.saidas' => 'Egresos',
    'dash.custos_fixos' => 'Costos Fijos',
    'dash.saldo' => 'Saldo',
    'dash.ver_livro' => 'Ver Libro Caja Completo',
    'dash.faturas_recentes' => 'Facturas Recientes',
    'dash.alert_emp_incompletos' => 'Datos de la empresa incompletos.',
    'dash.alert_campos_faltando' => 'Campos faltantes: %s',
    'dash.preencher_agora' => 'Completar ahora →',
    'dash.alert_vencendo' => '%d factura(s) venciendo en hasta 3 días — Total: R$ %s',
    'dash.vence_em' => 'vence en %s',
    'dash.hoje' => 'hoy',
    'dash.quant_registros' => 'Mostrando %d de %d registros',
    'dash.pagina' => '%d/página',
    'dash.receita_r' => 'Ingresos (R$)',
    'dash.valor_r' => 'Valor (R$)',

    // Tabelas
    'th.numero' => 'Número',
    'th.cliente' => 'Cliente',
    'th.valor' => 'Valor',
    'th.vencimento' => 'Vencimiento',
    'th.status' => 'Estado',
    'th.acoes' => 'Acciones',
    'th.descricao' => 'Descripción',
    'th.emissao' => 'Emisión',
    'th.nome' => 'Nombre',
    'th.usuario' => 'Usuario',
    'th.email' => 'E-mail',
    'th.perfil' => 'Perfil',
    'th.plano' => 'Plan',
    'th.whatsapp' => 'WhatsApp',
    'th.ult_login' => 'Último Acceso',
    'th.n' => 'Nº',
    'th.admin' => 'Admin',
    'th.periodo' => 'Período',
    'th.gerada_em' => 'Generada el',
    'th.paga_em' => 'Pagada el',
    'txt.nenhuma_fatura' => 'Ninguna factura encontrada',
    'txt.nenhum_admin' => 'Ningún admin registrado',
    'txt.nenhum_usuario' => 'Ningún usuario registrado',

    // Status
    'st.pago' => 'Pagado',
    'st.pendente' => 'Pendiente',
    'st.vencido' => 'Vencida',
    'st.atrasado' => 'Atrasado',
    'st.cancelado' => 'Cancelado',
    'st.expirado' => 'Expirado',
    'st.livre' => 'Libre (Sin vencimiento)',
    'st.parcelas' => 'Cuotas',

    // Filtros/botões comuns
    'filtro.status' => 'Estado',
    'filtro.cliente' => 'Cliente',
    'filtro.mes' => 'Mes',
    'filtro.ano' => 'Año',
    'filtro.todos' => 'Todos',
    'filtro.todos_status' => 'Todos los estados',
    'filtro.titulo' => 'Filtros',
    'filtro.buscar' => 'Buscar admin, plan o nº...',
    'btn.filtrar' => 'Filtrar',
    'btn.exportar_csv' => 'Exportar CSV',
    'btn.limpar_filtros' => 'Limpiar filtros',
    'btn.novo_usuario' => 'Nuevo Usuario',
    'btn.novo_admin' => 'Nuevo Admin',
    'btn.cancelar' => 'Cancelar',
    'btn.salvar' => 'Guardar',
    'btn.criar_usuario' => 'Crear Usuario',
    'btn.ok' => 'OK',
    'btn.confirmar' => 'Confirmar',
    'btn.desativar' => 'Desactivar',
    'btn.ativar' => 'Activar',

    // Faturas
    'fat.titulo' => 'Facturas',
    'fat.lista' => 'Facturas (%d)',
    'fat.copiar_pix' => 'Copiar PIX',
    'fat.link_pagamento' => 'Link de Pago',
    'fat.cancelar' => 'Cancelar',
    'fat.tit_cancelar' => 'Cancelar Factura',
    'fat.confirm_cancelar' => '¿Deseas cancelar la factura %s?',
    'fat.super_admins' => 'Facturas Generadas por los Admins',
    'fat.stats_filtro' => 'Facturas (filtro actual)',
    'fat.total_geral' => 'Total general: %s',
    'fat.pagas' => 'Pagadas',
    'fat.pendentes' => 'Pendientes',
    'fat.total_recebido' => 'Total Recibido',
    'fat.total_reembolsado' => 'Total Reembolsado (filtro)',

    // Usuários admin
    'uso.titulo' => 'Usuarios Admin',
    'uso.cadastrados' => 'Usuarios Registrados',
    'uso.lista' => 'Usuarios Registrados',
    'uso.ativo' => 'Activo',
    'uso.inativo' => 'Inactivo',
    'uso.perfil_atendimento' => 'Atención',
    'uso.perfil_financeiro' => 'Finanzas',
    'uso.perfil_admin' => 'Administrador',
    'uso.senha' => 'Contraseña',
    'uso.nova_senha' => 'Nueva contraseña (déjala vacía para mantenerla)',
    'uso.editar' => 'Editar',
    'uso.excluir' => 'Eliminar',
    'uso.remover' => '¿Eliminar a %s?',
    'uso.nao' => 'No',
    'uso.sim_excluir' => 'Sí, eliminar',

    // Super admin — dashboard
    'sup.titulo' => 'Dashboard Super Admin',
    'sup.admins_ativos' => 'Admins Activos',
    'sup.com_whats' => 'Con WhatsApp',
    'sup.sem_whats' => 'Sin WhatsApp',
    'sup.planos' => 'Planes',
    'sup.plano_nome' => 'Plan %s',
    'sup.admins_neste_plano' => 'Admins en este plan',
    'sup.admins_cadastrados' => 'Admins Registrados (%d)',
    'sup.logar_como' => 'Iniciar sesión como este admin',
    'sup.badge_site' => 'Site',
    'sup.badge_demo' => 'Demo',
    'sup.badge_sem_plano' => 'Sin plan',
    'sup.badge_configurado' => 'Configurado',
    'sup.badge_bloqueado' => 'Bloqueado',

    // Super admin — cadastros
    'cad.titulo' => 'Registros de Admins',
    'cad.novo_admin_tit' => 'Nuevo Admin',
    'cad.subdominio' => 'Subdominio',
    'cad.subdominio_hint' => 'Acceso por el subdominio .sudominio.com (ej.: clientea.sudominio.com)',
    'cad.vencimento_plano' => 'Vencimiento del plan',
    'cad.dados_empresa' => 'Datos de la empresa (para emisión de boleto y PIX)',
    'cad.razao_social' => 'Razón Social',
    'cad.nome_fantasia' => 'Nombre Fantasía',
    'cad.cnpj' => 'CNPJ',
    'cad.cpf' => 'CPF',
    'cad.insc_estadual' => 'Inscripción Estadual',
    'cad.insc_municipal' => 'Inscripción Municipal',
    'cad.tel_comercial' => 'Teléfono Comercial',
    'cad.email_comercial' => 'E-mail Comercial',
    'cad.cep' => 'CEP',
    'cad.uf' => 'UF',
    'cad.logradouro' => 'Calle',
    'cad.numero' => 'Número',
    'cad.complemento' => 'Complemento',
    'cad.bairro' => 'Barrio',
    'cad.cidade' => 'Ciudad',
    'cad.admin_ativo' => 'Admin activo',
    'cad.btn_cadastrar' => 'Registrar Admin',
    'cad.admins_count' => 'Admins (%d)',
    'cad.tit_editar' => 'Editar Admin: %s',

    // Mobile (área do usuário)
    'usuario.faturas' => 'Facturas',
    'usuario.financeiro' => 'Finanzas',
    'usuario.perfil' => 'Perfil',
    'usuario.sair' => 'Salir',

    // Modais / JS
    'modal.pix_copiado' => '¡Código PIX copiado!',
    'modal.excluir' => 'Eliminar',
    'modal.confirm_excluir' => '¿Deseas eliminar a <strong>%s</strong>? Esta acción no se puede deshacer.',
    'modal.confirmar_exclusao' => 'Confirmar Eliminación',
    'modal.tem_certeza_excluir' => '¿Tienes seguro que deseas eliminar al admin <strong>%s</strong>? Esta acción no se puede deshacer.',
    'modal.digite_deletar' => 'Escribe <strong>DELETAR</strong> para confirmar:',

    // Meses
    'mes_1' => 'Enero', 'mes_2' => 'Febrero', 'mes_3' => 'Marzo',
    'mes_4' => 'Abril', 'mes_5' => 'Mayo', 'mes_6' => 'Junio',
    'mes_7' => 'Julio', 'mes_8' => 'Agosto', 'mes_9' => 'Septiembre',
    'mes_10' => 'Octubre', 'mes_11' => 'Noviembre', 'mes_12' => 'Diciembre',
    'mes_abbr_1' => 'Ene', 'mes_abbr_2' => 'Feb', 'mes_abbr_3' => 'Mar',
    'mes_abbr_4' => 'Abr', 'mes_abbr_5' => 'May', 'mes_abbr_6' => 'Jun',
    'mes_abbr_7' => 'Jul', 'mes_abbr_8' => 'Ago', 'mes_abbr_9' => 'Sep',
    'mes_abbr_10' => 'Oct', 'mes_abbr_11' => 'Nov', 'mes_abbr_12' => 'Dic',
];

// Variantes regionais do espanhol (voseo/formas locais)
$GLOBALS['painel_es_MX'] = [
    'modal.confirm_excluir' => '¿Deseas eliminar a <strong>%s</strong>? Esta acción no se puede deshacer.',
];
$GLOBALS['painel_es_AR'] = [
    'layout.plano_vencido_msg' => 'Tu plan está vencido. Renová para seguir usando el panel.',
    'fat.confirm_cancelar' => '¿Querés cancelar la factura %s?',
    'uso.remover' => '¿Eliminar a %s?',
    'modal.confirm_excluir' => '¿Querés eliminar a <strong>%s</strong>? Esta acción no se puede deshacer.',
];
$GLOBALS['painel_es_CO'] = [
    'filtro.buscar' => 'Buscar admin, plan o nro...',
    'modal.confirm_excluir' => '¿Desea eliminar a <strong>%s</strong>? Esta acción no se puede deshacer.',
];
$GLOBALS['painel_es_CL'] = [
    'modal.confirm_excluir' => '¿Desea eliminar a <strong>%s</strong>? Esta acción no se puede deshacer.',
    'layout.plano_vencido_msg' => 'Tu plan está vencido. Renueva para seguir usando el panel.',
];
$GLOBALS['painel_es_PE'] = [
    'modal.confirm_excluir' => '¿Desea eliminar a <strong>%s</strong>? Esta acción no se puede deshacer.',
];

// Helper de tradução
function t($chave, $params = []) {
    $id = painelIdiomaAtual();
    $base = strpos($id, 'es') === 0 ? 'es' : 'pt';
    $dicio = $GLOBALS['painel_' . $base];
    if (strpos($id, 'es') === 0) {
        $regional = str_replace('-', '_', $id);
        if (isset($GLOBALS['painel_' . $regional])) {
            $dicio = array_merge($dicio, $GLOBALS['painel_' . $regional]);
        }
    }
    $s = array_key_exists($chave, $dicio) ? $dicio[$chave] : $chave;
    if ($params) {
        $s = vsprintf($s, array_map('strval', $params));
    }
    return $s;
}

// Mês por extenso (1-12)
function tMes($m) {
    $m = (int)$m;
    return t('mes_' . $m);
}

// Mês abreviado (1-12)
function tMesAbbr($m) {
    $m = (int)$m;
    return t('mes_abbr_' . $m);
}

// Rótulo de status da fatura
function tStatus($s) {
    return t('st.' . strtolower((string)$s));
}