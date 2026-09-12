<?php
// =====================================================
// TEMPLATE PADRÃO DE RECIBO + helpers
// =====================================================

function reciboTemplatePadrao(): string
{
    return '<div style="max-width:794px;margin:0 auto;padding:40px 50px;font-family:\'Inter\',\'Segoe UI\',Arial,sans-serif;color:#1a1a2e;line-height:1.5;background:#fff;">
    <div style="text-align:center;margin-bottom:24px;">
        {{empresa_logo}}
    </div>
    <h1 style="text-align:center;font-size:16px;font-weight:bold;text-transform:uppercase;letter-spacing:2px;margin:0 0 8px 0;color:#1a1a2e;">RECIBO DE PRESTAÇÃO DE SERVIÇOS</h1>
    <div style="text-align:center;margin-bottom:20px;font-size:12px;color:#555;">
        <strong>Recibo nº {{recibo_numero}}</strong> &nbsp;&nbsp;|&nbsp;&nbsp; <strong>Fatura nº {{fatura_numero}}</strong>
    </div>
    <hr style="border:none;border-top:2px solid #1a1a2e;margin:0 0 20px 0;">
    <div style="display:flex;gap:20px;margin-bottom:20px;">
        <div style="flex:1;border:1px solid #ddd;padding:14px;border-radius:4px;">
            <div style="font-size:11px;font-weight:bold;text-transform:uppercase;letter-spacing:1px;color:#555;margin-bottom:8px;">Emitente</div>
            <div style="font-size:12px;line-height:1.6;">
                <strong>{{empresa_nome}}</strong><br>
                CNPJ: {{empresa_cnpj}}<br>
                Ins. Mun.: {{empresa_inscricao_municipal}}<br>
                {{empresa_endereco}}, {{empresa_cidade}} - {{empresa_estado}}<br>
                CEP: {{empresa_cep}} | Tel: {{empresa_telefone}}<br>
                E-mail: {{empresa_email}}
            </div>
        </div>
        <div style="flex:1;border:1px solid #ddd;padding:14px;border-radius:4px;">
            <div style="font-size:11px;font-weight:bold;text-transform:uppercase;letter-spacing:1px;color:#555;margin-bottom:8px;">Tomador</div>
            <div style="font-size:12px;line-height:1.6;">
                <strong>{{cliente_nome}}</strong><br>
                CPF/CNPJ: {{cliente_cnpj_cpf}}<br>
                {{cliente_endereco}}<br>
                {{cliente_cidade}} - {{cliente_estado}}<br>
                CEP: {{cliente_cep}} | Tel: {{cliente_telefone}}<br>
                E-mail: {{cliente_email}}
            </div>
        </div>
    </div>
    <div style="margin-bottom:20px;font-size:13px;text-align:justify;line-height:1.7;">
        Pelo presente, a empresa <strong>{{empresa_nome}}</strong>, inscrita no CNPJ sob o nº <strong>{{empresa_cnpj}}</strong>, declara, para os devidos fins, que recebeu de <strong>{{cliente_nome}}</strong>, inscrita no CPF/CNPJ sob o nº <strong>{{cliente_cnpj_cpf}}</strong>, a quantia de <strong>{{valor_recebido}}</strong> (<em>{{valor_extenso}}</em>), referente à <strong>{{descricao_servico}}</strong>.
    </div>
    <div style="margin-bottom:20px;padding:16px 20px;border:2px solid #1a1a2e;border-radius:4px;text-align:center;background:#f9f9f9;">
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#555;margin-bottom:4px;">Valor Recebido</div>
        <div style="font-size:22px;font-weight:bold;color:#1a1a2e;">{{valor_recebido}}</div>
        <div style="font-size:12px;font-style:italic;margin-top:4px;">{{valor_extenso}}</div>
    </div>
    <div style="font-size:12px;text-align:justify;line-height:1.7;margin-bottom:20px;">
        O presente recibo é firmado para os devidos efeitos fiscais e legais, como comprovação do efetivo pagamento pelos serviços prestados.
    </div>
    <div style="font-size:12px;text-align:justify;line-height:1.7;margin-bottom:30px;">
        Por ser verdade, firmamos o presente.
    </div>
    <div style="text-align:right;font-size:12px;margin-bottom:40px;">
        {{cidade_emissao}}, {{data_emissao}}
    </div>
    {{assinatura_bloco}}
    <hr style="border:none;border-top:1px solid #ccc;margin:30px 0 10px 0;">
    <div style="text-align:center;font-size:10px;color:#999;">
        Documento gerado automaticamente pelo sistema.
    </div>
</div>';
}

function getTemplateReciboHtml($adminId = null): string
{
    $config = ($adminId !== null) ? getAllConfigForAdmin($adminId) : getAllConfig();
    $html = $config['template_recibo_html'] ?? '';
    if (empty($html)) $html = reciboTemplatePadrao();
    return $html;
}