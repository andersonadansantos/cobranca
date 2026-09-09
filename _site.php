<?php
// =====================================================
// SITE PÚBLICO - HELPER COMPARTILHADO
// Conteúdo em Tailwind (CDN), limpo e persuasivo.
// =====================================================

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/settings.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('SITE_NOME', 'CobrançaPRO');
define('SITE_TAGLINE', 'Cobranças que chegam e são pagas');

mb_internal_encoding('UTF-8');
ini_set('default_charset', 'UTF-8');

// Página dinâmica (idioma/conteúdo): impede cache que serviria idioma errado
if (!headers_sent()) {
    header('Cache-Control: private, no-cache, must-revalidate');
}

// =====================================================
// IDIOMAS DO SITE
// =====================================================
function siteIdiomas() {
    return [
        'pt-BR' => 'Português (Brasil)',
        'es-MX' => 'Español (México)',
        'es-AR' => 'Español (Argentina)',
        'es-CO' => 'Español (Colombia)',
        'es-CL' => 'Español (Chile)',
        'es-PE' => 'Español (Perú)',
    ];
}

function siteBandeira($cod) {
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

function siteIdiomaValido($id) {
    return array_key_exists((string)$id, siteIdiomas());
}

function siteIdiomaAtual() {
    $id = $_GET['idioma'] ?? ($_COOKIE['cobranca_site_idioma'] ?? 'pt-BR');
    $id = preg_replace('/[^a-zA-Z0-9-]/', '', (string)$id);
    return siteIdiomaValido($id) ? $id : 'pt-BR';
}

function siteIdiomaBase() {
    return strpos(siteIdiomaAtual(), 'es') === 0 ? 'es' : 'pt-BR';
}

// País-alvo do visitante, derivado do idioma escolhido no seletor do site.
function sitePaises() {
    return [
        'pt-BR' => 'BR',
        'es-MX' => 'MX',
        'es-AR' => 'AR',
        'es-CO' => 'CO',
        'es-CL' => 'CL',
        'es-PE' => 'PE',
    ];
}

function sitePais() {
    $mapa = sitePaises();
    return $mapa[siteIdiomaAtual()] ?? 'BR';
}

function siteEhBrasil() {
    return sitePais() === 'BR';
}

// Formas de pagamento de PLANO liberadas por país:
// Brasil -> PIX + boleto + cartão. Fora do Brasil -> somente cartão (internacional).
// O Super Admin pode desativar o cartão e/ou os métodos brasileiros (config global).
function sitePlanosMetodos() {
    $metodos = siteEhBrasil() ? ['pix', 'boleto', 'cartao'] : ['cartao'];
    if (getConfig('super_plano_cartao', '1') !== '1') {
        $metodos = array_values(array_diff($metodos, ['cartao']));
    }
    if (getConfig('super_plano_pix_boleto', '1') !== '1') {
        $metodos = array_values(array_diff($metodos, ['pix', 'boleto']));
    }
    return $metodos;
}

function siteNoMetodoPlano($metodo) {
    return in_array($metodo, sitePlanosMetodos(), true);
}

// Máximo de parcelas no cartão de PLANO (0 = cartão desativado).
function siteCartaoParcelasMax() {
    if (getConfig('super_plano_cartao', '1') !== '1') {
        return 0;
    }
    $max = (int) getConfig('super_mp_max_parcelas', '12');
    return max(1, min($max, 12));
}

function siteT($chave) {
    $t = siteTrad();
    $atual = siteIdiomaAtual();
    return $t[$atual][$chave] ?? $t[siteIdiomaBase()][$chave] ?? $t['pt-BR'][$chave] ?? $chave;
}

function siteTrad() {
    $pt = [
        'site_tagline' => 'Cobranças que chegam e são pagas',
        'meta_desc' => 'Emissão e cobrança de faturas com PIX, boleto, WhatsApp e e-mail. Cadastre-se em minutos e receba mais rápido.',
        'seo_title' => 'CobrançaPRO — Cobranças que chegam e são pagas',
        'seo_keys' => 'cobrança online, emissão de faturas, cobrança por WhatsApp, cobrança por e-mail, boleto, PIX, receber pagamentos, gestão de cobranças',
        // NAV
        'nav_como' => 'Como funciona',
        'nav_recursos' => 'Recursos',
        'nav_planos' => 'Planos',
        'nav_duvidas' => 'Dúvidas',
        'nav_demo' => 'Ver demo',
        'nav_painel' => 'Abrir painel',
        'nav_criar' => 'Criar conta grátis',
        // HERO
        'hero_badge' => 'Cobrança automática via WhatsApp e e-mail',
        'hero_t1' => 'Cobre e receba direto',
        'hero_t2' => 'no seu banco, sem intermediários.',
        'hero_sub' => 'Emita faturas, receba por PIX e boleto e dispare lembretes automáticos de cobrança que se adaptam a cada cliente. Menos planilha, mais dinheiro no caixa.',
        'hero_btn1' => 'Começar agora',
        'hero_btn2' => 'Ver planos',
        'hero_btn3' => 'Testar demo grátis',
        'hero_p1' => 'Sem cartão de crédito',
        'hero_p2' => 'PIX e boleto',
        'hero_p3' => 'Cancelamento quando quiser',
        // MOCKUP
        'mock_titulo' => 'Resumo de cobranças',
        'mock_recebidos' => 'R$ 12.840,00 recebidos',
        'mock_fatura' => 'Fatura #',
        'mock_pago' => 'PAGO · PIX',
        'mock_lembrete' => 'LEMBRETE ENVIADO',
        'mock_venc' => ' · vence amanhã',
        'mock_cobranca' => 'EM COBRANÇA',
        'mock_auto' => 'Lembrete automático disparado',
        'mock_wa' => 'via WhatsApp',
        // BANCOS
        'bancos_titulo' => 'Seus clientes pagam pelos canais que já usam no dia a dia',
        'bancos_apps' => 'e todos os apps de PIX',
        // STATS
        'st1' => 'empresas cobrando com CobrançaPRO',
        'st2' => 'recebidos via plataforma',
        'st3' => 'menos faturas em atraso',
        'st4' => 'para começar a cobrar',
        // COMO FUNCIONA
        'cf_badge' => 'Como funciona',
        'cf_titulo' => 'Do cadastro ao recebimento em 3 passos',
        'cf_n1' => 'Crie sua conta',
        'cf_d1' => 'Cadastro em menos de 2 minutos, sem cartão de crédito. Escolha o plano ideal e pague por PIX.',
        'cf_n2' => 'Cadastre clientes e faturas',
        'cf_d2' => 'Importe sua base, emita faturas com PIX e boleto em segundos e deixe tudo pronto para receber.',
        'cf_n3' => 'Receba e automatize',
        'cf_d3' => 'Os lembretes de cobrança saem sozinhos por WhatsApp e e-mail na hora certa. Você só acompanha o caixa.',
        // RECURSOS
        'rec_badge' => 'Recursos',
        'rec_titulo' => 'Tudo que você precisa para cobrar profissionalmente',
        'r1_t' => 'PIX e boleto',
        'r1_d' => 'Faturas com QR Code PIX copia e cola e boletos registrados para pagamento imediato ou agendado.',
        'r2_t' => 'Cobrança por WhatsApp',
        'r2_d' => 'Disparos automáticos com avatar profissional para cada etapa: envio, vencimento, atraso e confirmação de pagamento.',
        'r3_t' => 'E-mails profissionais',
        'r3_d' => 'Templates de cobrança personalizados com seus dados e marca, disparados por SMTP próprio.',
        'r4_t' => 'Lembretes automáticos',
        'r4_d' => 'Regras de cobrança por e-mail/WhatsApp que você define: 5 dias antes, no dia, e depois do vencimento.',
        'r5_t' => 'Relatórios claros',
        'r5_d' => 'Painel com situação de cada fatura, taxa de recebimento e valores em aberto com um clique.',
        'r6_t' => 'Segurança e privacidade',
        'r6_d' => 'Seus dados e os dos seus clientes protegidos, com rastreamento de cada envio de cobrança.',
        // PLANOS VITRINE
        'plan_badge' => 'Planos',
        'plan_titulo' => 'Preços simples, sem surpresa',
        'plan_sub' => 'Escolha o plano certo, pague por PIX e comece hoje a cobrar do jeito certo.',
        'plan_mes' => '/mês',
        'plan_assinar' => 'Assinar agora',
        'plan_demo_link' => 'Antes de assinar, conheça a versão demo',
        // DEPOIMENTOS
        'dep_badge' => 'Quem usa recomenda',
        'dep_titulo' => 'Resultados reais, gente de verdade',
        'dep1_q' => 'Recuperei mais de R$ 30 mil só com os lembretes de WhatsApp. O que eu fazia por telefone agora acontece sozinho.',
        'dep1_c' => 'Clínica odontológica',
        'dep2_q' => 'Nossa inadimplência caiu pela metade em dois meses. O painel é limpo e o cliente paga pelo PIX sem perguntar nada.',
        'dep2_c' => 'Distribuidora de bebidas',
        'dep3_q' => 'Configuramos em uma tarde e no dia seguinte as primeiras faturas já saíram cobrando. Simples assim.',
        'dep3_c' => 'Contabilidade',
        // FAQ
        'faq_badge' => 'Dúvidas',
        'faq_titulo' => 'Perguntas frequentes',
        'faq1_q' => 'Como funciona o período de teste?',
        'faq1_a' => 'Você pode conhecer a versão demo gratuitamente antes de assinar. Na demo, os envios de WhatsApp e e-mail estão desativados por segurança, mas você vê todo o fluxo de cobrança na prática.',
        'faq2_q' => 'Como faço o pagamento do plano?',
        'faq2_a' => 'Faça o cadastro no site, escolha o plano e finalize com PIX na hora, sem cartão de crédito. O acesso ao painel é liberado automaticamente assim que o pagamento é confirmado.',
        'faq3_q' => 'Como funcionam os lembretes de WhatsApp?',
        'faq3_a' => 'Você define as regras (ex.: 5 dias antes do vencimento, no dia e após atraso). A plataforma dispara as cobranças automaticamente com sua marca e você acompanha cada envio.',
        'faq4_q' => 'Posso cancelar quando quiser?',
        'faq4_a' => 'Sim. Você escolhe a duração e pode cancelar ou trocar de plano a qualquer momento. Sem fidelidade, sem multa.',
        'faq5_q' => 'Meus dados ficam seguros?',
        'faq5_a' => 'Sim. Suas informações e as dos seus clientes são protegidas, e todo envio de cobrança fica rastreado para sua conferência.',
        // CTA FINAL
        'cta_titulo' => 'Pare de cobrar na mão. Comece hoje.',
        'cta_sub' => 'Monte sua conta em minutos, teste a demo à vontade e receba seu primeiro pagamento ainda esta semana.',
        'cta_btn1' => 'Criar conta',
        'cta_btn2' => 'Ver demonstração',
        // RODAPÉ
        'ft_desc' => 'Plataforma de cobrança para você cobrar menos a mão e receber mais rápido: faturas, PIX e boletos com lembretes automáticos por WhatsApp e e-mail.',
        'ft_produto' => 'Produto',
        'ft_acesso' => 'Acesso',
        'ft_painel' => 'Painel do cliente',
        'ft_pagador' => 'Área do pagador',
        'ft_criar' => 'Criar conta',
        'ft_direitos' => 'Todos os direitos reservados.',
        'ft_pix' => 'Pagamento seguro via PIX.',
        // PÁGINA PLANOS
        'pl_titulo' => 'Escolha o plano que cabe no seu caixa',
        'pl_sub' => 'Sem multa, sem fidelidade. Pagamento único por PIX e acesso liberado na hora.',
        'pl_sem_envios' => 'Sem envios',
        'pl_wa' => 'WhatsApp',
        'pl_email' => 'E-mail',
        'pl_gateways' => 'Você recebe com',
        'pl_lib' => 'Liberado',
        'pl_bloq' => 'Bloqueado',
        'pl_popular' => 'MAIS POPULAR',
        'pl_demo_t' => 'Quer ver antes de pagar?',
        'pl_demo_d' => 'Entre na versão demo: acesse o painel completo com uma fatura já cadastrada. Envios de WhatsApp e e-mail ficam desativados por segurança.',
        'pl_ver_demo' => 'Ver demonstração',
        // PÁGINA CADASTRO
        'cad_voltar' => 'Voltar aos planos',
        'cad_titulo' => 'Criar minha conta',
        'cad_sub1' => 'Preencha em menos de 2 minutos. Ao finalizar, você segue direto para o pagamento por PIX do plano',
        'cad_erros_t' => 'Revise os dados abaixo:',
        'cad_passo1' => '1. Dados de acesso',
        'cad_nome' => 'Nome completo *',
        'cad_nome_ph' => 'Como você quer ser chamado',
        'cad_email' => 'E-mail *',
        'cad_email_ph' => 'voce@empresa.com.br',
        'cad_usuario' => 'Usuário de acesso *',
        'cad_usuario_ph' => 'ex.: carla.mendes',
        'cad_senha' => 'Senha *',
        'cad_senha_ph' => 'Mínimo 6 caracteres',
        'cad_confirma' => 'Confirmar senha *',
        'cad_confirma_ph' => 'Repita a senha',
        'cad_passo2' => '2. Dados de pagamento',
        'cad_cpfcnpj' => 'CPF ou CNPJ *',
        'cad_cpfcnpj_ph' => '000.000.000-00 ou CNPJ',
        'cad_cep' => 'CEP *',
        'cad_cep_ph' => '00000-000',
        'cad_end' => 'Endereço (rua/avenida)',
        'cad_num' => 'Número',
        'cad_fantasia' => 'Nome fantasia',
        'cad_fantasia_ph' => 'Sua empresa',
        'cad_tel' => 'Telefone / WhatsApp',
        'cad_tel_ph' => '(00) 00000-0000',
        'cad_cidade' => 'Cidade',
        'cad_uf' => 'UF',
        'cad_uf_sel' => '— Selecione —',
        'cad_btn' => 'Criar conta e ir para o pagamento',
        'cad_termos' => 'Ao criar a conta você concorda com os termos de uso. Seus dados são tratados com sigilo.',
        'cad_resumo' => 'Resumo',
        'cad_pix_nota' => 'Pagamento rápido e seguro via PIX. Acesso liberado assim que confirmado.',
        'cad_conta' => 'Já tem conta?',
        'cad_login' => 'Fazer login',
        'cad_e_nome' => 'Informe seu nome.',
        'cad_e_email' => 'E-mail inválido.',
        'cad_e_usuario' => 'Usuário deve ter 3 a 30 caracteres (letras, números, ponto, hífen).',
        'cad_e_senha' => 'A senha deve ter no mínimo 6 caracteres.',
        'cad_e_confirma' => 'A confirmação da senha não confere.',
        'cad_e_doc' => 'Informe um CPF (11 dígitos) ou CNPJ (14 dígitos) válido.',
        'cad_e_cep' => 'Informe um CEP válido (8 dígitos).',
        'cad_e_usuario_uso' => 'Esse usuário já está em uso. Escolha outro.',
        'cad_e_email_uso' => 'Já existe uma conta cadastrada com esse e-mail.',
        'cad_e_falha' => 'Não foi possível concluir o cadastro: ',
        // PÁGINA PAGAMENTO
        'pag_plano' => 'Pagamento do plano',
        'pag_plano_rot' => 'Plano',
        'pag_seu_plano' => 'Seu plano',
        'pag_quase' => 'Quase lá',
        'pag_sub' => 'Escaneie o QR Code ou copie o código PIX para pagar. Assim que o pagamento for confirmado, seu painel é liberado automaticamente.',
        'pag_mensal' => 'Mensal',
        'pag_unico' => 'Pagamento único · sem fidelidade',
        'pag_gerando' => 'Gerando seu PIX...',
        'pag_escan' => 'Escaneie com o app do seu banco',
        'pag_copia' => 'Ou pague por copia e cola:',
        'pag_btn_copiar' => 'Copiar código PIX',
        'pag_btn_paguei' => 'Já paguei - confirmar',
        'pag_auto' => 'Estamos verificando o pagamento automaticamente a cada 8 segundos.',
        'pag_pend' => 'O acesso liberado fica pendente enquanto o pagamento não for confirmado junto ao banco.',
        'pag_tentar' => 'Tentar novamente',
        'pag_confirmado' => 'Pagamento confirmado!',
        'pag_ativo' => 'está ativo. Já pode começar a cobrar.',
        'pag_abrir' => 'Abrir meu painel',
        'pag_problemas' => 'Problemas para pagar?',
        'pag_outro' => 'Escolher outro plano',
        'pag_ou' => 'ou',
        'pag_entrar' => 'entrar no painel',
        'pag_qr_indisp' => 'QR indisponível no momento',
        'pag_e_pix' => 'Não foi possível gerar o PIX. Tente novamente.',
        'pag_e_no_code' => 'PIX ainda sem código. Aguarde um instante e tente de novo.',
        'pag_e_conexao' => 'Falha de conexão. Tente novamente.',
        'pag_copiado' => 'Copiado!',
        'pag_consultando' => 'Consultando...',
        // MÉTODOS DE PAGAMENTO (pagamento.php)
        'pag_metodo' => 'Escolha como deseja pagar',
        'pag_pix' => 'PIX',
        'pag_pix_d' => 'QR Code para pagar na hora com a câmera ou o app do seu banco.',
        'pag_boleto' => 'Boleto bancário',
        'pag_boleto_d' => 'Gere um boleto para pagar em qualquer banco ou lotérica.',
        'pag_cartao' => 'Cartão de crédito / débito',
        'pag_cartao_d' => 'Pague com seu cartão — inclusive internacional — à vista ou parcelado.',
        'pag_note_int' => 'Pagamento processado pelo Mercado Pago em reais (R$). Fora do Brasil, a conversão é feita pelo seu banco emissor.',
        'pag_b_abrir' => 'Abrir boleto bancário',
        'pag_b_linha' => 'Linha digitável',
        'pag_btn_baixar' => 'Baixar boleto',
        'pag_b_paguei' => 'Já paguei',
        'pag_cc_note' => 'Seus dados de cartão são processados com segurança pelo Mercado Pago.',
        'pag_cc_num' => 'Número do cartão',
        'pag_cc_nome' => 'Nome impresso no cartão',
        'pag_cc_val' => 'Validade (MM/AA)',
        'pag_cc_cvv' => 'CVV',
        'pag_cc_parc' => 'Parcelas',
        'pag_cc_credito' => 'Crédito',
        'pag_cc_debito' => 'Débito',
        'pag_cc_avista' => 'Pagar à vista',
        'pag_cc_parcelado' => 'Pagar em %sx de %s',
        // PÁGINA DEMO
        'demo_badge' => 'Versão demo gratuita',
        'demo_t1' => 'Conheça o painel',
        'demo_t2' => 'sem pagar nada',
        'demo_sub' => 'Entre na demonstração e explore o painel completo com faturas, clientes e relatórios já cadastrados.',
        'demo_nota_t' => 'Neste modo de demonstração:',
        'demo_li1' => 'Todo o painel de cobrança fica disponível para exploração.',
        'demo_li2' => 'Os envios de WhatsApp e e-mail ficam bloqueados localmente e no painel.',
        'demo_li3' => 'As configurações de integração ficam travadas.',
        'demo_btn' => 'Entrar na demonstração',
        'demo_pe' => 'Sem cadastro, sem e-mail. Um clique e pronto.',
        'demo_e_falha' => 'A conta de demonstração ainda não está disponível. Contate o administrador.',
    ];

    $es = [
        'site_tagline' => 'Cobranzas que llegan y se pagan',
        'meta_desc' => 'Emisión y cobranza de facturas con PIX, boleto, WhatsApp y correo. Regístrate en minutos y recibe más rápido.',
        'seo_title' => 'CobrançaPRO — Cobranzas que llegan y se pagan',
        'seo_keys' => 'cobranza online, emisión de facturas, cobranza por WhatsApp, cobranza por correo, boleto, PIX, recibir pagos, gestión de cobranza',
        // NAV
        'nav_como' => 'Cómo funciona',
        'nav_recursos' => 'Recursos',
        'nav_planos' => 'Planes',
        'nav_duvidas' => 'Dudas',
        'nav_demo' => 'Ver demo',
        'nav_painel' => 'Abrir panel',
        'nav_criar' => 'Crear cuenta gratis',
        // HERO
        'hero_badge' => 'Cobranza automática por WhatsApp y correo',
        'hero_t1' => 'Cobra y recibe directo',
        'hero_t2' => 'en tu banco, sin intermediarios.',
        'hero_sub' => 'Emita facturas, reciba por PIX y boleto y dispare recordatorios automáticos de cobranza que se adaptan a cada cliente. Menos planilla, más dinero en caja.',
        'hero_btn1' => 'Comenzar ahora',
        'hero_btn2' => 'Ver planes',
        'hero_btn3' => 'Probar demo gratis',
        'hero_p1' => 'Sin tarjeta de crédito',
        'hero_p2' => 'PIX y boleto',
        'hero_p3' => 'Cancelación cuando quieras',
        // MOCKUP
        'mock_titulo' => 'Resumen de cobranzas',
        'mock_recebidos' => 'R$ 12.840,00 recibidos',
        'mock_fatura' => 'Factura #',
        'mock_pago' => 'PAGO · PIX',
        'mock_lembrete' => 'RECORDATORIO ENVIADO',
        'mock_venc' => ' · vence mañana',
        'mock_cobranca' => 'EN COBRANZA',
        'mock_auto' => 'Recordatorio automático enviado',
        'mock_wa' => 'por WhatsApp',
        // BANCOS
        'bancos_titulo' => 'Tus clientes pagan por los canales que ya usan a diario',
        'bancos_apps' => 'y todas las apps de PIX',
        // STATS
        'st1' => 'empresas cobrando con CobrançaPRO',
        'st2' => 'recibidos por la plataforma',
        'st3' => 'menos facturas vencidas',
        'st4' => 'para empezar a cobrar',
        // COMO FUNCIONA
        'cf_badge' => 'Cómo funciona',
        'cf_titulo' => 'Del registro al cobro en 3 pasos',
        'cf_n1' => 'Crea tu cuenta',
        'cf_d1' => 'Registro en menos de 2 minutos, sin tarjeta de crédito. Elige el plan ideal y paga por PIX.',
        'cf_n2' => 'Registra clientes y facturas',
        'cf_d2' => 'Importa tu base, emite facturas con PIX y boleto en segundos y deja todo listo para recibir.',
        'cf_n3' => 'Recibe y automatiza',
        'cf_d3' => 'Los recordatorios de cobranza salen solos por WhatsApp y correo en el momento justo. Tú solo acompañas la caja.',
        // RECURSOS
        'rec_badge' => 'Recursos',
        'rec_titulo' => 'Todo lo que necesitas para cobrar profesionalmente',
        'r1_t' => 'PIX y boleto',
        'r1_d' => 'Facturas con QR PIX copia y pega y boletos registrados para pago inmediato o programado.',
        'r2_t' => 'Cobranza por WhatsApp',
        'r2_d' => 'Disparos automáticos con avatar profesional para cada etapa: envío, vencimiento, atraso y confirmación de pago.',
        'r3_t' => 'Correos profesionales',
        'r3_d' => 'Plantillas de cobranza personalizadas con tus datos y marca, enviadas por SMTP propio.',
        'r4_t' => 'Recordatorios automáticos',
        'r4_d' => 'Reglas de cobranza por correo/WhatsApp que defines tú: 5 días antes, el mismo día y después del vencimiento.',
        'r5_t' => 'Reportes claros',
        'r5_d' => 'Panel con el estado de cada factura, tasa de cobro y valores pendientes con un clic.',
        'r6_t' => 'Seguridad y privacidad',
        'r6_d' => 'Tus datos y los de tus clientes protegidos, con seguimiento de cada envío de cobranza.',
        // PLANOS VITRINE
        'plan_badge' => 'Planes',
        'plan_titulo' => 'Precios simples, sin sorpresas',
        'plan_sub' => 'Elige el plan correcto, paga por PIX y empieza hoy a cobrar de la manera correcta.',
        'plan_mes' => '/mes',
        'plan_assinar' => 'Contratar ahora',
        'plan_demo_link' => 'Antes de contratar, conoce la versión demo',
        // DEPOIMENTOS
        'dep_badge' => 'Quienes lo usan lo recomiendan',
        'dep_titulo' => 'Resultados reales, gente de verdad',
        'dep1_q' => 'Recuperé más de R$ 30 mil solo con los recordatorios de WhatsApp. Lo que hacía por teléfono ahora ocurre solo.',
        'dep1_c' => 'Clínica odontológica',
        'dep2_q' => 'Nuestra morosidad cayó a la mitad en dos meses. El panel es limpio y el cliente paga por PIX sin preguntar nada.',
        'dep2_c' => 'Distribuidora de bebidas',
        'dep3_q' => 'Configuramos en una tarde y al día siguiente las primeras facturas ya salieron cobrando. Así de simple.',
        'dep3_c' => 'Contabilidad',
        // FAQ
        'faq_badge' => 'Dudas',
        'faq_titulo' => 'Preguntas frecuentes',
        'faq1_q' => '¿Cómo funciona el periodo de prueba?',
        'faq1_a' => 'Puedes conocer la versión demo gratis antes de contratar. En la demo, los envíos de WhatsApp y correo están desactivados por seguridad, pero ves todo el flujo de cobranza en la práctica.',
        'faq2_q' => '¿Cómo hago el pago del plan?',
        'faq2_a' => 'Haz el registro en el sitio, elige el plan y finaliza con PIX en el momento, sin tarjeta de crédito. El acceso al panel se libera automáticamente al confirmarse el pago.',
        'faq3_q' => '¿Cómo funcionan los recordatorios de WhatsApp?',
        'faq3_a' => 'Defines las reglas (ej.: 5 días antes del vencimiento, el mismo día y tras el atraso). La plataforma envía las cobranzas automáticamente con tu marca y sigues cada envío.',
        'faq4_q' => '¿Puedo cancelar cuando quiera?',
        'faq4_a' => 'Sí. Eliges la duración y puedes cancelar o cambiar de plan en cualquier momento. Sin fidelidad, sin multas.',
        'faq5_q' => '¿Mis datos están seguros?',
        'faq5_a' => 'Sí. Tus datos y los de tus clientes están protegidos, y cada envío de cobranza queda registrado para tu revisión.',
        // CTA FINAL
        'cta_titulo' => 'Deja de cobrar a mano. Empieza hoy.',
        'cta_sub' => 'Crea tu cuenta en minutos, prueba la demo a gusto y recibe tu primer pago esta misma semana.',
        'cta_btn1' => 'Crear cuenta',
        'cta_btn2' => 'Ver demostración',
        // RODAPÉ
        'ft_desc' => 'Plataforma de cobranza para que cobres menos a mano y recibas más rápido: facturas, PIX y boletos con recordatorios automáticos por WhatsApp y correo.',
        'ft_produto' => 'Producto',
        'ft_acesso' => 'Acceso',
        'ft_painel' => 'Panel del cliente',
        'ft_pagador' => 'Área del pagador',
        'ft_criar' => 'Crear cuenta',
        'ft_direitos' => 'Todos los derechos reservados.',
        'ft_pix' => 'Pago seguro por PIX.',
        // PÁGINA PLANOS
        'pl_titulo' => 'Elige el plan que cabe en tu caja',
        'pl_sub' => 'Sin multas, sin fidelidad. Pago único por PIX y acceso liberado al instante.',
        'pl_sem_envios' => 'Sin envíos',
        'pl_wa' => 'WhatsApp',
        'pl_email' => 'Correo',
        'pl_gateways' => 'Puedes cobrar con',
        'pl_lib' => 'Habilitado',
        'pl_bloq' => 'Bloqueado',
        'pl_popular' => 'MÁS POPULAR',
        'pl_demo_t' => '¿Quieres ver antes de pagar?',
        'pl_demo_d' => 'Entra a la versión demo: accede al panel completo con una factura ya registrada. Los envíos de WhatsApp y correo quedan desactivados por seguridad.',
        'pl_ver_demo' => 'Ver demostración',
        // PÁGINA REGISTRO
        'cad_voltar' => 'Volver a los planes',
        'cad_titulo' => 'Crear mi cuenta',
        'cad_sub1' => 'Completa en menos de 2 minutos. Al finalizar, sigues directo al pago por PIX del plan',
        'cad_erros_t' => 'Revisa los datos a continuación:',
        'cad_passo1' => '1. Datos de acceso',
        'cad_nome' => 'Nombre completo *',
        'cad_nome_ph' => 'Como quieres ser llamado',
        'cad_email' => 'Correo electrónico *',
        'cad_email_ph' => 'tu@empresa.com.br',
        'cad_usuario' => 'Usuario de acceso *',
        'cad_usuario_ph' => 'ej.: carla.mendes',
        'cad_senha' => 'Contraseña *',
        'cad_senha_ph' => 'Mínimo 6 caracteres',
        'cad_confirma' => 'Confirmar contraseña *',
        'cad_confirma_ph' => 'Repite la contraseña',
        'cad_passo2' => '2. Datos de pago',
        'cad_cpfcnpj' => 'CPF o CNPJ *',
        'cad_cpfcnpj_ph' => '000.000.000-00 o CNPJ',
        'cad_cep' => 'CEP *',
        'cad_cep_ph' => '00000-000',
        'cad_end' => 'Dirección (calle/avenida)',
        'cad_num' => 'Número',
        'cad_fantasia' => 'Nombre de fantasía',
        'cad_fantasia_ph' => 'Tu empresa',
        'cad_tel' => 'Teléfono / WhatsApp',
        'cad_tel_ph' => '(00) 00000-0000',
        'cad_cidade' => 'Ciudad',
        'cad_uf' => 'UF',
        'cad_uf_sel' => '— Selecciona —',
        'cad_btn' => 'Crear cuenta e ir al pago',
        'cad_termos' => 'Al crear la cuenta aceptas los términos de uso. Tus datos se tratan con confidencialidad.',
        'cad_resumo' => 'Resumen',
        'cad_pix_nota' => 'Pago rápido y seguro por PIX. El acceso se libera apenas se confirma.',
        'cad_conta' => '¿Ya tienes cuenta?',
        'cad_login' => 'Iniciar sesión',
        'cad_e_nome' => 'Ingresa tu nombre.',
        'cad_e_email' => 'Correo inválido.',
        'cad_e_usuario' => 'El usuario debe tener de 3 a 30 caracteres (letras, números, punto, guion).',
        'cad_e_senha' => 'La contraseña debe tener al menos 6 caracteres.',
        'cad_e_confirma' => 'La confirmación de la contraseña no coincide.',
        'cad_e_doc' => 'Ingresa un CPF (11 dígitos) o CNPJ (14 dígitos) válido.',
        'cad_e_cep' => 'Ingresa un CEP válido (8 dígitos).',
        'cad_e_usuario_uso' => 'Ese usuario ya está en uso. Elige otro.',
        'cad_e_email_uso' => 'Ya existe una cuenta registrada con ese correo.',
        'cad_e_falha' => 'No fue posible completar el registro: ',
        // PÁGINA PAGO
        'pag_plano' => 'Pago del plan',
        'pag_plano_rot' => 'Plan',
        'pag_seu_plano' => 'Tu plan',
        'pag_quase' => '¡Casi listo',
        'pag_sub' => 'Escanea el código QR o copia el código PIX para pagar. Apenas se confirme el pago, tu panel se libera automáticamente.',
        'pag_mensal' => 'Mensual',
        'pag_unico' => 'Pago único · sin permanencia',
        'pag_gerando' => 'Generando tu PIX...',
        'pag_escan' => 'Escanea con la app de tu banco',
        'pag_copia' => 'O paga por copia y pega:',
        'pag_btn_copiar' => 'Copiar código PIX',
        'pag_btn_paguei' => 'Ya pagué - confirmar',
        'pag_auto' => 'Estamos verificando el pago automáticamente cada 8 segundos.',
        'pag_pend' => 'El acceso liberado queda pendiente hasta que el pago se confirme con el banco.',
        'pag_tentar' => 'Intentar de nuevo',
        'pag_confirmado' => '¡Pago confirmado!',
        'pag_ativo' => 'está activo. Ya puedes empezar a cobrar.',
        'pag_abrir' => 'Abrir mi panel',
        'pag_problemas' => '¿Problemas para pagar?',
        'pag_outro' => 'Elegir otro plan',
        'pag_ou' => 'o',
        'pag_entrar' => 'entrar en el panel',
        'pag_qr_indisp' => 'QR no disponible por el momento',
        'pag_e_pix' => 'No fue posible generar el PIX. Inténtalo de nuevo.',
        'pag_e_no_code' => 'El PIX aún no tiene código. Espera un momento y vuelve a intentarlo.',
        'pag_e_conexao' => 'Fallo de conexión. Inténtalo de nuevo.',
        'pag_copiado' => '¡Copiado!',
        'pag_consultando' => 'Consultando...',
        // MÉTODOS DE PAGO (pagamento.php)
        'pag_metodo' => 'Elige cómo quieres pagar',
        'pag_pix' => 'PIX',
        'pag_pix_d' => 'Código QR para pagar al instante con la cámara o la app de tu banco.',
        'pag_boleto' => 'Boleto bancario',
        'pag_boleto_d' => 'Genera un boleto para pagar en cualquier banco.',
        'pag_cartao' => 'Tarjeta de crédito / débito',
        'pag_cartao_d' => 'Paga con tu tarjeta —incluso internacional— al contado o en cuotas.',
        'pag_note_int' => 'El pago se procesa con Mercado Pago en reales (R$). Fuera de Brasil, la conversión la hace tu banco emisor.',
        'pag_b_abrir' => 'Abrir boleto bancario',
        'pag_b_linha' => 'Línea de pago',
        'pag_btn_baixar' => 'Descargar boleto',
        'pag_b_paguei' => 'Ya pagué',
        'pag_cc_note' => 'Tus datos de tarjeta se procesan de forma segura con Mercado Pago.',
        'pag_cc_num' => 'Número de la tarjeta',
        'pag_cc_nome' => 'Nombre en la tarjeta',
        'pag_cc_val' => 'Vencimiento (MM/AA)',
        'pag_cc_cvv' => 'CVV',
        'pag_cc_parc' => 'Cuotas',
        'pag_cc_credito' => 'Crédito',
        'pag_cc_debito' => 'Débito',
        'pag_cc_avista' => 'Pagar de una vez',
        'pag_cc_parcelado' => 'Pagar en %s cuotas de %s',
        // PÁGINA DEMO
        'demo_badge' => 'Versión demo gratuita',
        'demo_t1' => 'Conoce el panel',
        'demo_t2' => 'sin pagar nada',
        'demo_sub' => 'Entra a la demostración y explora el panel completo con facturas, clientes e informes ya registrados.',
        'demo_nota_t' => 'En este modo de demostración:',
        'demo_li1' => 'Todo el panel de cobranza queda disponible para explorar.',
        'demo_li2' => 'Los envíos de WhatsApp y correo quedan bloqueados localmente y en el panel.',
        'demo_li3' => 'Las configuraciones de integración quedan bloqueadas.',
        'demo_btn' => 'Entrar en la demostración',
        'demo_pe' => 'Sin registro, sin correo. Un clic y listo.',
        'demo_e_falha' => 'La cuenta de demostración aún no está disponible. Contacta al administrador.',
    ];
    $esMX = array_merge($es, [
        // MÉXICO
        'seo_title' => 'CobrançaPRO México — Cobranza profesional por PIX, WhatsApp y correo',
        'seo_keys' => 'cobranza en México, emisión de facturas, cobranza por WhatsApp, cobranza por correo, PIX, boleto, recibir pagos, cobrar online',
        'nav_duvidas' => 'Preguntas frecuentes',
        'hero_btn1' => 'Empieza ahora',
        'hero_btn2' => 'Ver planes',
        'hero_sub' => 'Emita facturas, reciba por PIX y boleto y dispare recordatorios automáticos de cobranza que se adaptan a cada cliente. Menos papeleo, más dinero en caja.',
        'cf_titulo' => 'Del registro al cobro en 3 pasos',
        'plan_assinar' => 'Contratar ahora',
        'plan_titulo' => 'Precios simples, sin sorpresas',
        'dep2_q' => 'Nuestro índice de impago se redujo a la mitad en dos meses. El panel es limpio y el cliente paga por PIX sin preguntar nada.',
        'faq1_q' => '¿Cómo funciona el periodo de prueba?',
        'faq1_a' => 'Puedes conocer la versión demo gratis antes de contratar. En la demo, los envíos de WhatsApp y correo están desactivados por seguridad, pero ves todo el flujo de cobranza en la práctica.',
        'cta_titulo' => 'Deja de cobrar a mano. Empieza hoy.',
        'cta_btn1' => 'Crear cuenta',
        'cta_sub' => 'Crea tu cuenta en minutos, prueba la demo a gusto y recibe tu primer pago esta misma semana.',
        'ft_pix' => 'Pago seguro con PIX.',
    ]);

    $esAR = array_merge($es, [
        // ARGENTINA (voseo)
        'seo_title' => 'CobrançaPRO Argentina — Cobranzas que llegan y se pagan',
        'seo_keys' => 'cobranza online Argentina, emisión de facturas, recordatorios de cobro, cobranza por WhatsApp, PIX, recibir pagos, cobrar por correo',
        'nav_duvidas' => 'Dudas frecuentes',
        'hero_t2' => 'en tu banco, sin intermediarios.',
        'hero_btn1' => 'Empezá ahora',
        'hero_btn2' => 'Ver planes',
        'hero_sub' => 'Emití facturas, recibí por PIX y boleto y dispará recordatorios automáticos de cobranza que se adaptan a cada cliente. Menos planillas, más plata en la caja.',
        'cf_n1' => 'Creá tu cuenta',
        'cf_d1' => 'El registro te lleva menos de 2 minutos y no te pedimos tarjeta de crédito. Elegí el plan ideal y pagá con PIX.',
        'cf_n3' => 'Recibí y automatizá',
        'cf_d3' => 'Los recordatorios de cobranza salen solos por WhatsApp y correo en el momento justo. Vos solo acompañás la caja.',
        'plan_titulo' => 'Elegí el plan que cabe en tu caja',
        'plan_assinar' => 'Contratá ahora',
        'faq2_a' => 'Hacé el registro en el sitio, elegí el plan y finalizá con PIX en el momento, sin tarjeta de crédito. El acceso al panel se libera automáticamente al confirmarse el pago.',
        'faq3_a' => 'Definís las reglas (ej.: 5 días antes del vencimiento, el mismo día y tras el atraso). La plataforma envía las cobranzas automáticamente con tu marca y seguís cada envío.',
        'faq4_a' => 'Sí. Elegís la duración y podés cancelar o cambiar de plan en cualquier momento. Sin permanencia, sin multas.',
        'cta_titulo' => 'Dejá de cobrar a mano. Empezá hoy.',
        'cta_btn1' => 'Creá tu cuenta',
        'cta_btn2' => 'Ver demostración',
        'cta_sub' => 'Creá tu cuenta en minutos, probá la demo a gusto y recibí tu primer pago esta misma semana.',
        'ft_pix' => 'Pago seguro con PIX.',
        'cad_voltar' => 'Volver a los planes',
        'cad_titulo' => 'Creá tu cuenta',
        'cad_sub1' => 'Completá en menos de 2 minutos. Al finalizar, seguís directo al pago por PIX del plan',
        'cad_nome_ph' => 'Cómo querés ser llamado',
        'cad_confirma_ph' => 'Repetí la contraseña',
        'cad_uf_sel' => '— Seleccioná —',
        'cad_btn' => 'Creá tu cuenta e ir al pago',
        'cad_conta' => '¿Ya tenés cuenta?',
        'cad_login' => 'Iniciar sesión',
        'cad_e_usuario' => 'El usuario debe tener de 3 a 30 caracteres (letras, números, punto, guion).',
        'cad_e_usuario_uso' => 'Ese usuario ya está en uso. Elegí otro.',
        'cad_e_falha' => 'No fue posible completar el registro: ',
        'pag_quase' => '¡Casi listo',
        'pag_sub' => 'Escaneá el código QR o copiá el código PIX para pagar. Apenas se confirme el pago, tu panel se libera automáticamente.',
        'pag_unico' => 'Pago único · sin permanencia',
        'pag_gerando' => 'Generando tu PIX...',
        'pag_escan' => 'Escaneá con la app de tu banco',
        'pag_copia' => 'O pagá por copia y pega:',
        'pag_btn_copiar' => 'Copiar código PIX',
        'pag_btn_paguei' => 'Ya pagué - confirmar',
        'pag_tentar' => 'Intentar de nuevo',
        'pag_confirmado' => '¡Pago confirmado!',
        'pag_ativo' => 'está activo. Ya podés empezar a cobrar.',
        'pag_abrir' => 'Abrir mi panel',
        'pag_outro' => 'Elegir otro plan',
        'pag_ou' => 'o',
        'pag_entrar' => 'entrar en el panel',
        'pag_e_pix' => 'No fue posible generar el PIX. Intentá de nuevo.',
        'pag_e_no_code' => 'El PIX todavía no tiene código. Esperá un momento y volvé a intentarlo.',
        'pag_e_conexao' => 'Fallo de conexión. Intentá de nuevo.',
        'cad_passo1' => '1. Datos de acceso',
        'cad_passo2' => '2. Datos de pago',
        'demo_sub' => 'Entrá a la demostración y explorá el panel completo con facturas, clientes e informes ya registrados.',
        'demo_btn' => 'Entrá a la demo',
        'demo_e_falha' => 'La cuenta de demostración todavía no está disponible. Contactá al administrador.',
    ]);

    $esCO = array_merge($es, [
        // COLÔMBIA
        'seo_title' => 'CobrançaPRO Colombia — Cobranza profesional por WhatsApp y correo',
        'seo_keys' => 'cobranza en Colombia, emisión de facturas, cartera vencida, cobranza por WhatsApp, PIX, recibir pagos, cobrar por correo',
        'nav_duvidas' => 'Preguntas frecuentes',
        'hero_sub' => 'Emita facturas, reciba por PIX y boleto y dispare recordatorios automáticos de cobranza que se adaptan a cada cliente. Menos planillas, más dinero en caja.',
        'plan_assinar' => 'Contratar ahora',
        'r5_t' => 'Informes claros',
        'dep2_q' => 'Nuestra cartera vencida se redujo a la mitad en dos meses. El panel es limpio y el cliente paga por PIX sin preguntar nada.',
        'faq1_a' => 'Puedes conocer la versión demo gratis antes de contratar. En la demo, los envíos de WhatsApp y correo están desactivados por seguridad, pero ves todo el flujo de cobranza en la práctica.',
        'cta_sub' => 'Crea tu cuenta en minutos, prueba la demo a gusto y recibe tu primer pago esta misma semana.',
        'ft_pix' => 'Pago seguro con PIX.',
    ]);

    $esCL = array_merge($es, [
        // CHILE
        'seo_title' => 'CobrançaPRO Chile — Cobranzas que llegan y se pagan',
        'seo_keys' => 'cobranza en Chile, emisión de facturas, cobranza por WhatsApp, recordatorios de cobro, PIX, recibir pagos, cobrar online',
        'nav_duvidas' => 'Preguntas frecuentes',
        'hero_sub' => 'Emita facturas, reciba por PIX y boleto y dispare recordatorios automáticos de cobranza que se adaptan a cada cliente. Menos planillas, más dinero en la caja.',
        'plan_assinar' => 'Contratar ahora',
        'dep2_q' => 'Nuestra morosidad se redujo a la mitad en dos meses. El panel es limpio y el cliente paga por PIX sin preguntar nada.',
        'faq1_a' => 'Puedes conocer la versión demo gratis antes de contratar. En la demo, los envíos de WhatsApp y correo están desactivados por seguridad, pero ves todo el flujo de cobranza en la práctica.',
        'cta_sub' => 'Crea tu cuenta en minutos, prueba la demo a gusto y recibe tu primer pago esta misma semana.',
        'ft_pix' => 'Pago seguro con PIX.',
    ]);

    $esPE = array_merge($es, [
        // PERU
        'seo_title' => 'CobrançaPRO Perú — Cobranza profesional por PIX y boleto',
        'seo_keys' => 'cobranza en Perú, emisión de facturas, cobranza por WhatsApp, cobranza por correo, PIX, boleto, recibir pagos, cobrar online',
        'nav_duvidas' => 'Preguntas frecuentes',
        'hero_sub' => 'Emita facturas, reciba por PIX y boleto y dispare recordatorios automáticos de cobranza que se adaptan a cada cliente. Menos planillas, más dinero en caja.',
        'plan_assinar' => 'Contratar ahora',
        'faq1_a' => 'Puedes conocer la versión demo gratis antes de contratar. En la demo, los envíos de WhatsApp y correo están desactivados por seguridad, pero ves todo el flujo de cobranza en la práctica.',
        'cta_sub' => 'Crea tu cuenta en minutos, prueba la demo a gusto y recibe tu primer pago esta misma semana.',
        'ft_pix' => 'Pago seguro con PIX.',
    ]);

    return [
        'pt-BR' => $pt,
        'es' => $es,
        'es-MX' => $esMX,
        'es-AR' => $esAR,
        'es-CO' => $esCO,
        'es-CL' => $esCL,
        'es-PE' => $esPE,
    ];
}

// Ao escolher idioma via ?idioma=, salva o cookie e já renderiza nesse idioma
if (isset($_GET['idioma'])) {
    $novo = siteIdiomaAtual();
    if (siteIdiomaValido($novo)) {
        setcookie('cobranca_site_idioma', $novo, time() + 31536000, '/');
    }
}

// Planos pagos exibidos no site (o plano demo fica fora da vitrine)
function sitePlanos() {
    $pdo = getConnection();
    if (!$pdo) return [];
    try {
        $stmt = $pdo->query("SELECT * FROM planos WHERE ativo = 1 AND slug <> 'demo' ORDER BY preco ASC");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

// Linhas de benefício normalizadas (corrige '??' de importações antigas)
function siteParseBeneficios($texto) {
    $texto = str_replace('??', '·', (string)$texto);
    $linhas = preg_split('/\R/', $texto);
    $out = [];
    foreach ($linhas as $l) {
        $l = trim($l);
        if ($l === '') continue;
        $out[] = $l;
    }
    return $out;
}

// Classifica a linha de benefício para ícone correto
function siteBeneficioStatus($linha) {
    $l = mb_strtolower($linha);
    if (strpos($l, 'gateway') !== false) return 'gw';
    if (strpos($l, 'bloqueado') !== false) return 'no';
    return 'ok';
}

function siteGateways() {
    return [
        ['log' => '/cobranca/assets/img/pix-logo.svg',             'alt' => 'PIX',          'cls' => 'h-6 w-auto'],
        ['log' => '/cobranca/assets/img/mercado-pago-logo.png',    'alt' => 'Mercado Pago', 'cls' => 'h-6 w-auto'],
        ['log' => '/cobranca/assets/img/banco-inter-logo-0-1.png', 'alt' => 'Banco Inter',  'cls' => 'h-6 w-auto'],
        ['log' => '/cobranca/assets/img/asaas-logo.svg',           'alt' => 'Asaas',        'cls' => 'h-5 w-auto'],
    ];
}

function siteCorHex($cor) {
    $mapa = [
        'bronze' => '#b87333',
        'secondary' => '#64748b',
        'warning' => '#f59e0b',
        'primary' => '#0d6efd',
        'success' => '#0f7b5c',
        'info' => '#0891b2',
        'danger' => '#dc2626',
        'dark' => '#1f2937',
    ];
    return $mapa[strtolower(trim((string)$cor))] ?? '#0f7b5c';
}

// Classe Tailwind (valor arbitrário) a partir da cor do plano
function siteCorCls($cor) {
    return 'bg-[' . siteCorHex($cor) . ']';
}

function sitePreco($v) {
    return number_format((float)$v, 2, ',', '.');
}

function siteLogado() {
    return isset($_SESSION['admin_id']) && (int)$_SESSION['admin_id'] > 0;
}

function siteHeader($secao = '') {
    $secao = htmlspecialchars((string)$secao);
    ?>
<!DOCTYPE html>
<html lang="<?= siteIdiomaAtual() ?>" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= siteT('seo_title') ?></title>
    <meta name="description" content="<?= siteT('meta_desc') ?>">
    <meta name="keywords" content="<?= siteT('seo_keys') ?>">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <meta name="author" content="<?= SITE_NOME ?>">
    <meta name="theme-color" content="#059669">
    <link rel="icon" type="image/png" href="/cobranca/assets/img/pix-logo.svg">
    <?php
    // ===================== SEO =====================
    $proto   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host    = $_SERVER['HTTP_HOST'] ?? '';
    $reqPath = strtok($_SERVER['REQUEST_URI'] ?? '', '?');

    $params   = $_GET;
    unset($params['idioma']);
    $baseQuery = http_build_query($params);

    $urlBase = $proto . '://' . $host . $reqPath . ($baseQuery !== '' ? '?' . $baseQuery : '');
    if (isset($_SERVER['SERVER_PORT']) && !in_array((int)$_SERVER['SERVER_PORT'], [80, 443], true)) {
        $urlBase = $proto . '://' . $host . ':' . (int)$_SERVER['SERVER_PORT'] . $reqPath . ($baseQuery !== '' ? '?' . $baseQuery : '');
    }

    $idiomaAtual = siteIdiomaAtual();
    $canonical   = $urlBase;
    if ($idiomaAtual !== 'pt-BR') {
        $canonical .= (strpos($urlBase, '?') !== false ? '&' : '?') . 'idioma=' . $idiomaAtual;
    }

    $ogImagem = '';
    require_once __DIR__ . '/config/settings.php';
    $logo = function_exists('getLogo') ? trim((string)getLogo()) : '';
    if ($logo !== '') {
        if (strpos($logo, 'http') === 0) {
            $ogImagem = $logo;
        } elseif (strpos($logo, '/') === 0) {
            $ogImagem = $proto . '://' . $host . $logo;
        } else {
            $ogImagem = $proto . '://' . $host . '/cobranca/assets/img/' . $logo;
        }
    }
    if (empty($ogImagem)) {
        $logos = glob(__DIR__ . '/assets/img/logo_*.png');
        $ogImagem = $logos ? ($proto . '://' . $host . '/cobranca/assets/img/' . basename($logos[0])) : '';
    }

    $ogLocale = str_replace('-', '_', $idiomaAtual);
    $seoTitle = siteT('seo_title');
    $seoDesc  = siteT('meta_desc');
    ?>
    <link rel="canonical" href="<?= htmlspecialchars($canonical) ?>">

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= SITE_NOME ?>">
    <meta property="og:title" content="<?= htmlspecialchars($seoTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($seoDesc) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($canonical) ?>">
    <?php if ($ogImagem !== ''): ?><meta property="og:image" content="<?= htmlspecialchars($ogImagem) ?>"><?php endif; ?>
    <meta property="og:locale" content="<?= htmlspecialchars($ogLocale) ?>">
    <?php foreach (siteIdiomas() as $cod => $rot): if ($cod === $idiomaAtual) continue; ?>
    <meta property="og:locale:alternate" content="<?= str_replace('-', '_', $cod) ?>">
    <?php endforeach; ?>

    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($seoTitle) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($seoDesc) ?>">
    <?php if ($ogImagem !== ''): ?><meta name="twitter:image" content="<?= htmlspecialchars($ogImagem) ?>"><?php endif; ?>

    <!-- hreflang (idioma por país) -->
    <?php foreach (siteIdiomas() as $cod => $rot):
        $href = $urlBase;
        if ($cod !== 'pt-BR') {
            $href .= (strpos($urlBase, '?') !== false ? '&' : '?') . 'idioma=' . $cod;
        }
    ?>
    <link rel="alternate" hreflang="<?= $cod ?>" href="<?= htmlspecialchars($href) ?>">
    <?php endforeach; ?>
    <link rel="alternate" hreflang="x-default" href="<?= htmlspecialchars($urlBase) ?>">

    <!-- JSON-LD -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebSite",
        "name": <?= json_encode(SITE_NOME, JSON_UNESCAPED_UNICODE) ?>,
        "alternateName": <?= json_encode(siteT('site_tagline'), JSON_UNESCAPED_UNICODE) ?>,
        "url": <?= json_encode($urlBase, JSON_UNESCAPED_UNICODE) ?>,
        "inLanguage": <?= json_encode($idiomaAtual) ?>,
        "description": <?= json_encode($seoDesc, JSON_UNESCAPED_UNICODE) ?>,
        "publisher": {
            "@type": "Organization",
            "name": <?= json_encode(SITE_NOME, JSON_UNESCAPED_UNICODE) ?>,
            <?php if ($ogImagem !== ''): ?>"logo": {
                "@type": "ImageObject",
                "url": <?= json_encode($ogImagem) ?>,
                "width": 512,
                "height": 512
            }<?php endif; ?>
        }
    }
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        brand: {
                            50: '#ecfdf5', 100: '#d1fae5', 200: '#a7f3d0', 300: '#6ee7b7',
                            400: '#34d399', 500: '#10b981', 600: '#059669', 700: '#047857',
                            800: '#065f46', 900: '#064e3b',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        @keyframes surgir {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .anim-entrada { animation: surgir .8s ease backwards; }
        .anim-entrada-d { animation: surgir .8s ease backwards; animation-delay: .15s; }

        @keyframes shimmer-texto {
            0%   { background-position: 0% 50%; }
            100% { background-position: 220% 50%; }
        }
        .anim-shimmer {
            background: linear-gradient(90deg, #059669 0%, #34d399 25%, #f59e0b 50%, #34d399 75%, #059669 100%);
            background-size: 220% auto;
            -webkit-background-clip: text;
                    background-clip: text;
            color: transparent;
            animation: shimmer-texto 6s linear infinite;
        }

        .mock-feed { overflow: hidden; }
        .mock-track { display: flex; flex-direction: column; gap: 12px; will-change: transform; }
        .mock-slide { flex-shrink: 0; }

        @keyframes flutua {
            0%, 100% { transform: translateY(0); }
            50%      { transform: translateY(-10px); }
        }
        .anim-flutua { animation: flutua 6s ease-in-out infinite; }

        /* Elementos observados por scroll: visíveis por padrão.
           A animação só acontece quando entram na viewport. */
        .reveal-init { opacity: 1; }

        @media (prefers-reduced-motion: reduce) {
            .anim-entrada, .anim-entrada-d, .anim-shimmer, .anim-flutua { animation: none; }
            .anim-entrada-d, .reveal-init { opacity: 1; transform: none; }
        }
    </style>
</head>
<body class="bg-white text-slate-800 antialiased font-sans">
<!-- Navegação -->
<header class="sticky top-0 z-50 bg-white/80 backdrop-blur-lg border-b border-slate-100">
    <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        <a href="/cobranca/index.php" class="flex items-center gap-2 font-extrabold text-lg tracking-tight">
            <span class="w-9 h-9 rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 text-white grid place-items-center shadow-lg shadow-brand-200">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </span>
            <span><?= SITE_NOME ?></span>
        </a>
        <div class="hidden md:flex items-center gap-8 text-sm font-semibold text-slate-600">
            <a href="/cobranca/index.php#como-funciona" class="hover:text-brand-700 transition"><?= siteT('nav_como') ?></a>
            <a href="/cobranca/index.php#recursos" class="hover:text-brand-700 transition"><?= siteT('nav_recursos') ?></a>
            <a href="/cobranca/planos.php" class="hover:text-brand-700 transition"><?= siteT('nav_planos') ?></a>
            <a href="/cobranca/index.php#faq" class="hover:text-brand-700 transition"><?= siteT('nav_duvidas') ?></a>
        </div>
        <div class="flex items-center gap-2">
            <div class="relative" id="idioma-selector">
            <button type="button" id="idioma-btn" aria-haspopup="listbox" aria-expanded="false"
                    class="flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-600 focus:outline-none focus:border-brand-400 cursor-pointer">
                <img id="idioma-lbl-flag" src="/cobranca/assets/img/flags/<?= siteBandeira(siteIdiomaAtual()) ?>" width="20" height="14"
                     class="w-5 h-3.5 object-cover rounded-[2px]" alt="">
                <span id="idioma-lbl"><?= htmlspecialchars(siteIdiomas()[siteIdiomaAtual()] ?? siteIdiomaAtual()) ?></span>
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div id="idioma-menu" class="hidden absolute right-0 mt-2 w-60 rounded-xl border border-slate-100 bg-white p-1.5 shadow-xl z-50">
                <?php foreach (siteIdiomas() as $cod => $rot): ?>
                <button type="button" data-idioma="<?= $cod ?>" role="option" aria-selected="<?= siteIdiomaAtual() === $cod ? 'true' : 'false' ?>"
                        class="idioma-item flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-brand-50 hover:text-brand-700 cursor-pointer">
                    <img src="/cobranca/assets/img/flags/<?= siteBandeira($cod) ?>" width="20" height="14" class="w-5 h-3.5 object-cover rounded-[2px]" alt="">
                    <span class="flex-1 text-left"><?= htmlspecialchars($rot) ?></span>
                    <?php if (siteIdiomaAtual() === $cod): ?>
                    <svg class="w-4 h-4 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    <?php endif; ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
        <script>
        (function () {
            var btn = document.getElementById('idioma-btn');
            var menu = document.getElementById('idioma-menu');
            if (!btn || !menu) return;
            var selecionar = function (cod) {
                var u = new URL(window.location.href);
                u.searchParams.set('idioma', cod);
                window.location = u.href;
            };
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var escondido = menu.classList.toggle('hidden');
                btn.setAttribute('aria-expanded', escondido ? 'false' : 'true');
            });
            document.addEventListener('click', function () {
                menu.classList.add('hidden');
                btn.setAttribute('aria-expanded', 'false');
            });
            menu.addEventListener('click', function (e) {
                var alvo = e.target.closest('[data-idioma]');
                if (!alvo) return;
                e.stopPropagation();
                selecionar(alvo.getAttribute('data-idioma'));
            });
        })();
        </script>
            <a href="/cobranca/demo.php" class="hidden sm:inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-sm font-bold text-brand-700 bg-brand-50 hover:bg-brand-100 transition">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0zM9 9.563c0-.82.745-1.46 1.55-1.33.85.14 1.25.63 1.25 1.02 0 .78-2.25 1.24-2.25 2.94a.873.873 0 001.66.39M12 14.5v.01"/></svg>
                <?= siteT('nav_demo') ?>
            </a>
            <a href="/cobranca/cadastro.php<?= $secao ? '?plano=' . $secao : '' ?>" class="rounded-full px-5 py-2 text-sm font-bold text-white bg-gradient-to-r from-brand-600 to-brand-700 hover:from-brand-700 hover:to-brand-800 shadow-lg shadow-brand-200 transition"><?= siteT('nav_criar') ?></a>
        </div>
    </nav>
</header>
    <?php
}

function siteFooter() {
    ?>
<footer class="bg-slate-950 text-slate-300 mt-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14 grid grid-cols-1 md:grid-cols-4 gap-10">
        <div class="md:col-span-2">
            <div class="flex items-center gap-2 font-extrabold text-white text-lg">
                <span class="w-9 h-9 rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 text-white grid place-items-center">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
                <?= SITE_NOME ?>
            </div>
            <p class="mt-4 text-sm leading-relaxed text-slate-400 max-w-md"><?= siteT('ft_desc') ?></p>
        </div>
        <div>
            <h4 class="font-bold text-white text-sm uppercase tracking-wider"><?= siteT('ft_produto') ?></h4>
            <ul class="mt-4 space-y-2 text-sm">
                <li><a href="/cobranca/planos.php" class="hover:text-brand-400 transition"><?= siteT('nav_planos') ?></a></li>
                <li><a href="/cobranca/demo.php" class="hover:text-brand-400 transition"><?= siteT('nav_demo') ?></a></li>
                <li><a href="/cobranca/index.php#como-funciona" class="hover:text-brand-400 transition"><?= siteT('nav_como') ?></a></li>
                <li><a href="/cobranca/index.php#faq" class="hover:text-brand-400 transition"><?= siteT('nav_duvidas') ?></a></li>
            </ul>
        </div>
        <div>
            <h4 class="font-bold text-white text-sm uppercase tracking-wider"><?= siteT('ft_acesso') ?></h4>
            <ul class="mt-4 space-y-2 text-sm">
                <li><a href="/cobranca/admin/login.php" class="hover:text-brand-400 transition"><?= siteT('ft_painel') ?></a></li>
                <li><a href="/cobranca/usuario/login.php" class="hover:text-brand-400 transition"><?= siteT('ft_pagador') ?></a></li>
                <li><a href="/cobranca/cadastro.php" class="hover:text-brand-400 transition"><?= siteT('ft_criar') ?></a></li>
            </ul>
        </div>
    </div>
    <div class="border-t border-white/10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-slate-500">
            <span>© <?= date('Y') ?> <?= SITE_NOME ?>. <?= siteT('ft_direitos') ?></span>
            <span><?= siteT('ft_pix') ?></span>
        </div>
    </div>
</footer>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var els = document.querySelectorAll('.reveal-init:not(.anim-entrada)');
    if (!('IntersectionObserver' in window)) {
        els.forEach(function (e) { e.classList.add('anim-entrada'); });
        return;
    }
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('anim-entrada');
                io.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12 });
    els.forEach(function (e) { io.observe(e); });
});
</script>
    <?php
}

// Notificação de novo cadastro para o superadmin (best-effort por e-mail)
function notificarSuperCadastro($_nome, $_usuario, $_email, $_planoNome) {
    try {
        $pdo = getConnection();
        if (!$pdo) return;
        $stmt = $pdo->query("SELECT * FROM superadmin WHERE email IS NOT NULL AND email <> '' ORDER BY id LIMIT 1");
        $super = $stmt->fetch();
        $superNome = $super['nome'] ?? 'Super Administrador';
        $superEmail = $super['email'] ?? '';
        if (empty($superEmail)) return;

        $g = function ($k) use ($pdo) {
            $s = $pdo->prepare("SELECT valor FROM configuracoes WHERE admin_id IS NULL AND chave = ?");
            $s->execute([$k]);
            return (string)$s->fetchColumn();
        };

        $host = $g('smtp_host');
        $port = $g('smtp_port') ?: '587';
        $user = $g('smtp_usuario');
        $pass = $g('smtp_senha');
        $fromEmail = $g('smtp_from_email');
        $fromNome = trim($g('smtp_from_nome')) ?: SITE_NOME;
        $ssl = $g('smtp_ssl') ?: 'tls';
        if (empty($host) || empty($user)) return;

        if (!function_exists('enviarEmail')) {
            require_once __DIR__ . '/config/email_helpers.php';
        }
        $assunto = 'Novo cadastro no site: ' . $_nome;
        $html = '<h2>Novo cliente cadastrado no site ' . SITE_NOME . '</h2>'
            . '<p><strong>Nome:</strong> ' . htmlspecialchars($_nome) . '<br>'
            . '<strong>Usuário:</strong> ' . htmlspecialchars($_usuario) . '<br>'
            . '<strong>E-mail:</strong> ' . htmlspecialchars($_email) . '<br>'
            . '<strong>Plano escolhido:</strong> ' . htmlspecialchars($_planoNome) . '</p>'
            . '<p>O cliente aguarda pagamento do plano. Acesse o painel do Super Admin para acompanhar.</p>';
        $txt = "Novo cadastro no site " . SITE_NOME . "\n"
            . "Nome: {$_nome}\nUsuário: {$_usuario}\nE-mail: {$_email}\nPlano escolhido: {$_planoNome}\n";
        @enviarEmail($host, $port, $user, $pass, $fromEmail, $fromNome, $ssl, $superEmail, $superNome, $assunto, $html, $txt);
    } catch (Throwable $e) {
        // nunca derruba o cadastro
    }
}