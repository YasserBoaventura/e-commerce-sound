// Funções da loja
function scrollToProdutos() {
    document.getElementById('produtos').scrollIntoView({ 
        behavior: 'smooth' 
    });
}

// Animações
document.addEventListener('DOMContentLoaded', function() {
    // Animação de entrada dos produtos
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    });

    document.querySelectorAll('.produto-card').forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'all 0.6s ease';
        observer.observe(card);
    });

    // INICIALIZAR SISTEMA DE CARRINHO
    inicializarSistemaCarrinho();
});

// ========== SISTEMA COMPLETO DE CARRINHO ==========

// Variáveis globais do carrinho
let produtoAtual = null;
let carrinho = JSON.parse(localStorage.getItem('carrinho')) || [];

function inicializarSistemaCarrinho() {
    console.log('🛒 Inicializando sistema de carrinho...');
    
    // Botões "Comprar Agora"
    document.querySelectorAll('.btn-comprar').forEach(btn => {
        btn.addEventListener('click', function() {
            const produtoId = this.getAttribute('data-produto-id');
            const produtoNome = this.getAttribute('data-produto-nome');
            const produtoPreco = parseFloat(this.getAttribute('data-produto-preco'));
            
            abrirModalCarrinho({
                id: produtoId,
                nome: produtoNome,
                preco: produtoPreco
            });
        });
    });

    // Controles de quantidade
    const incrementar = document.getElementById('incrementar');
    const decrementar = document.getElementById('decrementar');
    const quantidadeInput = document.getElementById('quantidade');
    
    if (incrementar) incrementar.addEventListener('click', incrementarQuantidade);
    if (decrementar) decrementar.addEventListener('click', decrementarQuantidade);
    if (quantidadeInput) quantidadeInput.addEventListener('input', atualizarTotal);

    // Botões modais
    const btnAdicionar = document.getElementById('btnAdicionarCarrinho');
    const btnFinalizar = document.getElementById('btnFinalizarCompra');
    const btnConfirmar = document.getElementById('btnConfirmarVenda');
    
    if (btnAdicionar) btnAdicionar.addEventListener('click', adicionarAoCarrinhoComQuantidade);
    if (btnFinalizar) btnFinalizar.addEventListener('click', abrirModalFinalizar);
    if (btnConfirmar) btnConfirmar.addEventListener('click', finalizarVenda);

    // Fechar modais
    document.querySelectorAll('.close-carrinho, .close-finalizar').forEach(close => {
        close.addEventListener('click', function() {
            const modal = this.closest('.modal-carrinho, .modal-finalizar');
            if (modal) modal.style.display = 'none';
        });
    });

    // Fechar modal clicando fora
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal-carrinho') || 
            event.target.classList.contains('modal-finalizar')) {
            event.target.style.display = 'none';
        }
    });

    // Atualizar carrinho flutuante
    atualizarCarrinhoFlutuante();
}

// FUNÇÕES DO MODAL CARRINHO
function abrirModalCarrinho(produto) {
    produtoAtual = produto;
    
    document.getElementById('modalProdutoNome').textContent = produto.nome;
    document.getElementById('modalProdutoPreco').textContent = produto.preco.toFixed(2);
    document.getElementById('quantidade').value = 1;
    atualizarTotal();
    
    document.getElementById('modalCarrinho').style.display = 'block';
}

function fecharModalCarrinho() {
    document.getElementById('modalCarrinho').style.display = 'none';
}

function incrementarQuantidade() {
    const input = document.getElementById('quantidade');
    let valor = parseInt(input.value);
    if (valor < 99) {
        input.value = valor + 1;
        atualizarTotal();
    }
}

function decrementarQuantidade() {
    const input = document.getElementById('quantidade');
    let valor = parseInt(input.value);
    if (valor > 1) {
        input.value = valor - 1;
        atualizarTotal();
    }
}

function atualizarTotal() {
    if (!produtoAtual) return;
    
    const quantidade = parseInt(document.getElementById('quantidade').value);
    const preco = produtoAtual.preco;
    const total = quantidade * preco;
    document.getElementById('modalTotal').textContent = total.toFixed(2);
}

function adicionarAoCarrinhoComQuantidade() {
    const quantidade = parseInt(document.getElementById('quantidade').value);
    
    const item = {
        id: produtoAtual.id,
        nome: produtoAtual.nome,
        preco: produtoAtual.preco,
        quantidade: quantidade,
        subtotal: (produtoAtual.preco * quantidade).toFixed(2)
    };

    // Verificar se produto já está no carrinho
    const index = carrinho.findIndex(p => p.id === item.id);
    if (index > -1) {
        carrinho[index].quantidade += quantidade;
        carrinho[index].subtotal = (carrinho[index].preco * carrinho[index].quantidade).toFixed(2);
    } else {
        carrinho.push(item);
    }

    salvarCarrinho();
    atualizarCarrinhoFlutuante();
    fecharModalCarrinho();
    
    alert(`✅ ${quantidade}x "${produtoAtual.nome}" adicionado ao carrinho!\nTotal: R$ ${item.subtotal}`);
}

// FUNÇÕES DO CARRINHO FLUTUANTE
function atualizarCarrinhoFlutuante() {
    const carrinhoCount = document.getElementById('carrinhoCount');
    const carrinhoTotal = document.getElementById('carrinhoTotal');
    const carrinhoItens = document.getElementById('carrinhoItens');
    const carrinhoFlutuante = document.getElementById('carrinhoFlutuante');
    
    if (!carrinhoCount || !carrinhoTotal || !carrinhoItens || !carrinhoFlutuante) {
        console.warn('❌ Elementos do carrinho flutuante não encontrados');
        return;
    }
    
    const count = carrinho.reduce((total, item) => total + item.quantidade, 0);
    const total = carrinho.reduce((sum, item) => sum + parseFloat(item.subtotal), 0);
    
    carrinhoCount.textContent = count;
    carrinhoTotal.textContent = `Total: R$ ${total.toFixed(2)}`;
    
    carrinhoItens.innerHTML = '';
    
    carrinho.forEach(item => {
        const div = document.createElement('div');
        div.className = 'carrinho-item';
        div.innerHTML = `
            <span>${item.nome}</span>
            <span>${item.quantidade}x</span>
        `;
        carrinhoItens.appendChild(div);
    });
    
    // Mostrar/ocultar carrinho flutuante
    carrinhoFlutuante.style.display = count > 0 ? 'block' : 'none';
}

// FUNÇÕES FINALIZAR VENDA
function abrirModalFinalizar() {
    if (carrinho.length === 0) {
        alert('🛒 Carrinho vazio! Adicione produtos primeiro.');
        return;
    }
    
    const resumo = document.getElementById('resumoCarrinho');
    const totalCompra = document.getElementById('totalCompra');
    const modalFinalizar = document.getElementById('modalFinalizar');
    
    if (!resumo || !totalCompra || !modalFinalizar) {
        console.error('❌ Elementos do modal finalizar não encontrados');
        return;
    }
    
    let html = '<h4>📦 Resumo do Pedido:</h4>';
    let total = 0;
    
    carrinho.forEach(item => {
        html += `
            <div class="item-carrinho">
                <div class="item-info">
                    <strong>${item.nome}</strong><br>
                    <small>${item.quantidade} x R$ ${parseFloat(item.preco).toFixed(2)}</small>
                </div>
                <div class="item-preco">R$ ${parseFloat(item.subtotal).toFixed(2)}</div>
            </div>
        `;
        total += parseFloat(item.subtotal);
    });
    
    resumo.innerHTML = html;
    totalCompra.textContent = total.toFixed(2);
    modalFinalizar.style.display = 'block';
}

function finalizarVenda() {
    console.log('🔍 INICIANDO finalizarVenda()');
    
    const form = document.getElementById('formCliente');
    if (!form) {
        console.error('❌ Formulário formCliente não encontrado');
        alert('❌ Formulário não encontrado');
        return;
    }
    
    const formData = new FormData(form);
    
    // Validar método de pagamento
    const metodoPagamentoSelecionado = document.querySelector('input[name="metodo_pagamento"]:checked');
    if (!metodoPagamentoSelecionado) {
        alert('❌ Selecione um método de pagamento!');
        return;
    }
    
    console.log('📝 Dados do formulário:', {
        nome: formData.get('nome'),
        email: formData.get('email'),
        telefone: formData.get('telefone'),
        endereco: formData.get('endereco'),
        metodo_pagamento: metodoPagamentoSelecionado.value
    });
    
    // Validar formulário
    if (!formData.get('nome') || !formData.get('email') || !formData.get('telefone') || !formData.get('endereco')) {
        console.error('❌ Campos obrigatórios não preenchidos');
        alert('❌ Preencha todos os dados obrigatórios!');
        return;
    }

    if (carrinho.length === 0) {
        console.error('❌ Carrinho vazio');
        alert('❌ Carrinho vazio!');
        return;
    }

    // ESTRUTURA CORRETA para o PHP
    const dadosVenda = {
        cliente: {
            nome: formData.get('nome'),
            email: formData.get('email'),
            telefone: formData.get('telefone'),
            endereco: formData.get('endereco')
        },
        metodo_pagamento: metodoPagamentoSelecionado.value,
        carrinho: carrinho.map(item => ({
            id: item.id,
            nome: item.nome,
            preco: parseFloat(item.preco),
            quantidade: parseInt(item.quantidade),
            subtotal: parseFloat(item.subtotal)
        }))
    };

    console.log('📤 Dados que serão enviados:', dadosVenda);
    console.log('🔄 Convertendo para JSON...');

    // Verificar se consegue converter para JSON
    try {
        const jsonData = JSON.stringify(dadosVenda);
        console.log('✅ JSON gerado com sucesso:', jsonData);
    } catch (e) {
        console.error('❌ Erro ao converter para JSON:', e);
        alert('Erro nos dados do carrinho');
        return;
    }

    // Mostrar loading
    const btnConfirmar = document.getElementById('btnConfirmarVenda');
    const textoOriginal = btnConfirmar.textContent;
    btnConfirmar.textContent = '⏳ Processando...';
    btnConfirmar.disabled = true;

    console.log('🚀 Enviando requisição para finalizar_venda.php...');

    // Enviar para o servidor PHP
    fetch('finalizar_venda.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(dadosVenda)
    })
    .then(response => {
        console.log('📨 Resposta recebida. Status:', response.status);
        
        // Primeiro ler como texto para debug
        return response.text().then(text => {
            console.log('📨 Resposta completa (texto):', text);
            
            // Tentar parsear como JSON
            try {
                const data = JSON.parse(text);
                return { success: true, data: data };
            } catch (e) {
                console.error('❌ Resposta não é JSON válido:', text);
                throw new Error('Resposta do servidor não é JSON válido');
            }
        });
    })
    .then(result => {
        if (result.success) {
            console.log('✅ Resposta do servidor:', result.data);
            
            if (result.data.success) {
                alert(`🎉 Pedido finalizado com sucesso!\n\n📋 Nº do Pedido: #${result.data.venda_id}\n💰 Total: R$ ${result.data.total}\n💳 Método: ${result.data.metodo_pagamento}\n\nObrigado pela compra!`);
                
                // Limpar carrinho
                carrinho = [];
                salvarCarrinho();
                atualizarCarrinhoFlutuante();
                
                const modalFinalizar = document.getElementById('modalFinalizar');
                if (modalFinalizar) {
                    modalFinalizar.style.display = 'none';
                }
                
                form.reset();
            } else {
                alert('❌ Erro ao finalizar pedido: ' + result.data.message);
            }
        }
    })
    .catch(error => {
        console.error('❌ Erro na requisição:', error);
        console.error('❌ Tipo do erro:', error.name);
        console.error('❌ Mensagem:', error.message);
        alert('❌ Erro ao processar pedido: ' + error.message);
    })
    .finally(() => {
        // Restaurar botão
        btnConfirmar.textContent = textoOriginal;
        btnConfirmar.disabled = false;
        console.log('🏁 Finalizando processo finalizarVenda()');
    });
}

// LOCAL STORAGE
function salvarCarrinho() {
    localStorage.setItem('carrinho', JSON.stringify(carrinho));
}

// Função antiga mantida para compatibilidade
function adicionarAoCarrinho(produtoId) {
    // Esta função é mantida para compatibilidade com código antigo
    console.log('Função antiga - usar modal instead');
}