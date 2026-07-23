// =====================================================
// SISTEMA DE COBRANÇA - JAVASCRIPT PRINCIPAL
// =====================================================

document.addEventListener('DOMContentLoaded', function() {
    initTooltips();
    initMask();
    initSidebar();
});

// Inicializar Bootstrap Tooltips
function initTooltips() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(el) {
        return new bootstrap.Tooltip(el);
    });
}

// Máscaras de input
function initMask() {
    document.querySelectorAll('.mask-cpf').forEach(function(el) {
        el.addEventListener('input', function(e) {
            let v = e.target.value.replace(/\D/g, '');
            if (v.length > 14) v = v.substring(0, 14);
            if (v.length > 11) {
                v = v.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{1,2})$/, '$1.$2.$3/$4-$5');
            } else {
                v = v.replace(/^(\d{3})(\d{3})(\d{3})(\d{1,2})$/, '$1.$2.$3-$4');
                v = v.replace(/^(\d{3})(\d{3})(\d{3})$/, '$1.$2.$3');
                v = v.replace(/^(\d{3})(\d{3})$/, '$1.$2');
                v = v.replace(/^(\d{3})$/, '$1');
            }
            e.target.value = v;
        });
    });

    document.querySelectorAll('.mask-phone').forEach(function(el) {
        el.addEventListener('input', function(e) {
            let v = e.target.value.replace(/\D/g, '');
            if (v.length > 11) v = v.substring(0, 11);
            if (v.length > 10) {
                v = v.replace(/^(\d{2})(\d{5})(\d{4})$/, '($1) $2-$3');
            } else if (v.length > 6) {
                v = v.replace(/^(\d{2})(\d{4})(\d{0,4})$/, '($1) $2-$3');
            } else if (v.length > 2) {
                v = v.replace(/^(\d{2})(\d{0,5})$/, '($1) $2');
            }
            e.target.value = v;
        });
    });

    document.querySelectorAll('.mask-cep').forEach(function(el) {
        el.addEventListener('input', function(e) {
            let v = e.target.value.replace(/\D/g, '');
            if (v.length > 8) v = v.substring(0, 8);
            if (v.length > 5) {
                v = v.replace(/^(\d{5})(\d{1,3})$/, '$1-$2');
            }
            e.target.value = v;
        });
    });

    document.querySelectorAll('.mask-money').forEach(function(el) {
        el.addEventListener('input', function(e) {
            let v = e.target.value.replace(/\D/g, '');
            if (v.length === 0) { e.target.value = ''; return; }
            v = (parseInt(v) / 100).toFixed(2);
            v = v.replace('.', ',');
            v = v.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            e.target.value = 'R$ ' + v;
        });
    });
}

// Sidebar toggle
function initSidebar() {
    var toggler = document.getElementById('sidebarToggle');
    var sidebar = document.querySelector('.sidebar');
    if (toggler && sidebar) {
        toggler.addEventListener('click', function() {
            sidebar.classList.toggle('show');
            var overlay = document.getElementById('sidebarOverlay');
            if (overlay) overlay.classList.toggle('show');
        });
        var overlay = document.getElementById('sidebarOverlay');
        if (overlay) {
            overlay.addEventListener('click', function() {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
            });
        }
    }
}

// Formatar valor para moeda
function formatMoney(value) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(value);
}

// Confirmar exclusão
function confirmarExclusao(nome) {
    return confirm('Tem certeza que deseja excluir ' + nome + '?');
}

// Copiar para área de transferência
function copiarPix(codigo) {
    navigator.clipboard.writeText(codigo).then(function() {
        showToast('Código PIX copiado!');
    }).catch(function() {
        var textarea = document.createElement('textarea');
        textarea.value = codigo;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        showToast('Código PIX copiado!');
    });
}

// Toast notification
function showToast(mensagem, tipo) {
    tipo = tipo || 'success';
    var toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;';
        document.body.appendChild(toastContainer);
    }

    var toast = document.createElement('div');
    toast.className = 'toast align-items-center text-white bg-' + tipo + ' border-0 show';
    toast.setAttribute('role', 'alert');
    toast.innerHTML = '<div class="d-flex"><div class="toast-body">' + mensagem + '</div>' +
        '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
    toastContainer.appendChild(toast);

    setTimeout(function() {
        toast.remove();
    }, 3000);
}

// Buscar CEP
function buscarCEP(cep, campos) {
    cep = cep.replace(/\D/g, '');
    if (cep.length !== 8) return;

    fetch('https://viacep.com.br/ws/' + cep + '/json/')
        .then(function(r) { return r.json(); })
        .then(function(dados) {
            if (!dados.erro) {
                if (campos.logradouro) document.getElementById(campos.logradouro).value = dados.logradouro || '';
                if (campos.bairro) document.getElementById(campos.bairro).value = dados.bairro || '';
                if (campos.cidade) document.getElementById(campos.cidade).value = dados.localidade || '';
                if (campos.estado) document.getElementById(campos.estado).value = dados.uf || '';
            }
        })
        .catch(function() {});
}

// Gerar número de fatura
function gerarNumeroFatura() {
    var now = new Date();
    var y = now.getFullYear();
    var m = String(now.getMonth() + 1).padStart(2, '0');
    var seq = String(Math.floor(Math.random() * 9999) + 1).padStart(4, '0');
    return 'FAT-' + y + m + '-' + seq;
}

// Validar CPF/CNPJ
function validarCPFCNPJ(valor) {
    valor = valor.replace(/\D/g, '');
    if (valor.length === 11) return validarCPF(valor);
    if (valor.length === 14) return validarCNPJ(valor);
    return false;
}

function validarCPF(cpf) {
    if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) return false;
    var sum = 0;
    for (var i = 0; i < 9; i++) sum += parseInt(cpf[i]) * (10 - i);
    var rest = (sum * 10) % 11;
    if (rest === 10) rest = 0;
    if (rest !== parseInt(cpf[9])) return false;
    sum = 0;
    for (var i = 0; i < 10; i++) sum += parseInt(cpf[i]) * (11 - i);
    rest = (sum * 10) % 11;
    if (rest === 10) rest = 0;
    return rest === parseInt(cpf[10]);
}

function validarCNPJ(cnpj) {
    if (cnpj.length !== 14 || /^(\d)\1{13}$/.test(cnpj)) return false;
    var weights1 = [5,4,3,2,9,8,7,6,5,4,3,2];
    var weights2 = [6,5,4,3,2,9,8,7,6,5,4,3,2];
    var sum = 0;
    for (var i = 0; i < 12; i++) sum += parseInt(cnpj[i]) * weights1[i];
    var rest = sum % 11;
    var d1 = rest < 2 ? 0 : 11 - rest;
    if (parseInt(cnpj[12]) !== d1) return false;
    sum = 0;
    for (var i = 0; i < 13; i++) sum += parseInt(cnpj[i]) * weights2[i];
    rest = sum % 11;
    var d2 = rest < 2 ? 0 : 11 - rest;
    return parseInt(cnpj[13]) === d2;
}
