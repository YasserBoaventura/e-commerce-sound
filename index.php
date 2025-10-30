<?php
session_start();
require_once 'config/database.php';


$database = new Database();
$db = $database->getConnection();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=0.0">
    <title>Som Automotivo Premium - Sua Loja de Som Profissional</title>
    <link rel="stylesheet" href="style.css">
    <style>
        
/* FOOTER SIMPLES - TESTE */
.footer {
    background: #2c3e50;
    color: white;
    padding: 3rem 0 1rem;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 2rem;
}

.footer-content {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
}

.footer-section h3 {
    margin-bottom: 1rem;
}

.footer-section p {
    color: #ccc;
    margin-bottom: 1rem;
}

.social-links {
    display: flex;
    gap: 1rem;
}

.social-links a {
    color: white;
    text-decoration: none;
    padding: 0.5rem 1rem;
    background: #007bff;
    border-radius: 5px;
}

.footer-section ul {
    list-style: none;
    padding: 0;
}

.footer-section ul li {
    margin-bottom: 0.5rem;
}

.footer-section ul li a {
    color: #ccc;
    text-decoration: none;
}

.footer-section ul li a:hover {
    color: white;
}

.footer-bottom {
    border-top: 1px solid #555;
    padding-top: 1rem;
    text-align: center;
    color: #999;
}

/* Modal do Carrinho */
.modal-carrinho {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-carrinho-content {
    background-color: white;
    margin: 10% auto;
    padding: 2rem;
    border-radius: 10px;
    width: 90%;
    max-width: 400px;
    position: relative;
}

.close-carrinho {
    position: absolute;
    right: 1rem;
    top: 1rem;
    font-size: 1.5rem;
    cursor: pointer;
    color: #666;
}

.close-carrinho:hover {
    color: #000;
}

.quantidade-group {
    margin: 1.5rem 0;
}

.quantidade-controls {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.btn-quantidade {
    background: #007bff;
    color: white;
    border: none;
    width: 40px;
    height: 40px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 1.2rem;
}

#quantidade {
    width: 60px;
    text-align: center;
    padding: 0.5rem;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 1rem;
}

.total-group {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 5px;
    margin: 1rem 0;
    text-align: center;
    font-size: 1.2rem;
    font-weight: bold;
}

.btn-success {
    background: #28a745;
    color: white;
    border: none;
    padding: 1rem 2rem;
    border-radius: 5px;
    cursor: pointer;
    width: 100%;
    font-size: 1.1rem;
    font-weight: 600;
    margin-top: 1rem;
}

.btn-success:hover {
    background: #218838;
}

/* Modal Finalizar Venda */
.modal-finalizar {
    display: none;
    position: fixed;
    z-index: 1001;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-finalizar-content {
    background-color: white;
    margin: 5% auto;
    padding: 2rem;
    border-radius: 10px;
    width: 90%;
    max-width: 600px;
    position: relative;
    max-height: 80vh;
    overflow-y: auto;
}

.close-finalizar {
    position: absolute;
    right: 1rem;
    top: 1rem;
    font-size: 1.5rem;
    cursor: pointer;
    color: #666;
}

.close-finalizar:hover {
    color: #000;
}

/* Carrinho Flutuante */
#carrinhoFlutuante {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    background: white;
    border: 2px solid #007bff;
    border-radius: 10px;
    padding: 1rem;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    z-index: 999;
    min-width: 200px;
    display: none;
}

.carrinho-header {
    display: flex;
    justify-content: space-between;
    font-weight: bold;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #ddd;
}

.carrinho-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
    padding: 0.5rem;
    background: #f8f9fa;
    border-radius: 5px;
    font-size: 0.9rem;
}

.carrinho-total {
    font-weight: bold;
    margin-top: 1rem;
    padding-top: 0.5rem;
    border-top: 1px solid #ddd;
}

.btn-finalizar {
    background: #28a745;
    color: white;
    border: none;
    padding: 0.8rem 1.5rem;
    border-radius: 5px;
    cursor: pointer;
    width: 100%;
    margin-top: 1rem;
    font-weight: 600;
}

.btn-finalizar:hover {
    background: #218838;
}

/* Itens do Carrinho no Modal Finalizar */
.item-carrinho {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    border-bottom: 1px solid #eee;
    margin-bottom: 0.5rem;
}

.item-info {
    flex: 1;
}

.item-preco {
    font-weight: bold;
    color: #28a745;
}

.total-final {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 8px;
    margin: 1.5rem 0;
    text-align: center;
    font-size: 1.3rem;
    font-weight: bold;
}

/* Formulário Cliente */
.form-cliente {
    margin-top: 1.5rem;
}

.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
}

.form-group input {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 1rem;
}

.btn-pagamento {
    background: #17a2b8;
    color: white;
    border: none;
    padding: 1rem 2rem;
    border-radius: 5px;
    cursor: pointer;
    width: 100%;
    font-size: 1.1rem;
    font-weight: 600;
    margin-top: 1rem;
}

.btn-pagamento:hover {
    background: #138496;
}
.produto-info{
    margin-top: 110px; /* Baixa o card 30px */
    /* Mantenha os outros estilos que já existem */
    background: white;
    border-radius: 15px;
 
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}
.produto-card{
     margin-top: 110px;
}
</style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <nav class="navbar">
            <div class="logo">🎵 SomAuto Premium</div>
            <ul class="nav-links">
                <li><a href="#produtos">Produtos</a></li>
                <li><a href="#categorias">Categorias</a></li>
                <li><a href="#sobre">Sobre</a></li>
                <li><a href="#contato">Contato</a></li>
            </ul>
            <a href="login.php" class="btn-admin">🔧 Área Admin</a>
        </nav>
    </header>

    <!-- Banner Hero -->
    <section class="hero">
        <h1>Som Automotivo de Alta Performance</h1>
        <p>Os melhores equipamentos para transformar seu carro em um estúdio musical</p>
        <button class="btn-primary" onclick="scrollToProdutos()">Ver Produtos</button>
    </section>

    <!-- Produtos em Destaque -->
 
<section id="produtos" class="produtos-section">
    <h2 class="section-title">🎯 Produtos em Destaque</h2>
    <div class="produtos-grid">
        <?php
      $query = "SELECT p.*, c.nome as categoria_nome, m.nome as marca_nome 
          FROM produtos p 
          LEFT JOIN categorias c ON p.categoria_id = c.id 
          LEFT JOIN marcas m ON p.marca_id = m.id 
          WHERE p.destaque = TRUE 
          ORDER BY p.data_cadastro DESC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        while ($produto = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $preco_formatado = number_format($produto['preco'], 2, ',', '.');
            $preco_original_formatado = $produto['preco_original'] ? number_format($produto['preco_original'], 2, ',', '.') : '';
            $desconto = $produto['preco_original'] ? round((($produto['preco_original'] - $produto['preco']) / $produto['preco_original']) * 100) : 0;
            
            
        ?>
            <div class="produto-card">
                <div class="produto-imagem">
    <?php
    // Verifica se existe imagem no banco de dados E se o arquivo existe
    if (!empty($produto['imagem']) && file_exists($produto['imagem'])) {
    echo '<img src="' . $produto['imagem'] . '" alt="' . $produto['nome'] . '" loading="lazy">';
} else {
    // Imagem padrão caso não exista
    echo '<div class="produto-sem-imagem">';
    echo '<span class="icone-produto">🎵</span>';
    
    // Verificar se marca_nome existe e não está vazia
    if (!empty($produto['marca_nome'])) {
        echo '<span class="marca-iniciais">' . substr($produto['marca_nome'], 0, 2) . '</span>';
    } else {
        // Fallback: usar iniciais do nome do produto
        $iniciais = !empty($produto['nome']) ? substr($produto['nome'], 0, 2) : 'PD';
        echo '<span class="marca-iniciais">' . $iniciais . '</span>';
    }
    
    echo '</div>';
}
    ?>
    
    <?php if($desconto > 0): ?>
    <div class="badge-desconto">-<?php echo $desconto; ?>%</div>
    <?php endif; ?>
</div>
                <div class="produto-info">
                    <h3 class="produto-nome"><?php echo $produto['nome']; ?></h3>
                    <div class="produto-preco">
                        R$ <?php echo $preco_formatado; ?>
                        <?php if($preco_original_formatado): ?>
                        <span class="produto-preco-original">De: R$ <?php echo $preco_original_formatado; ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="produto-descricao"><?php echo substr($produto['descricao'], 0, 100); ?>...</p>
                    <div class="produto-especificacoes">
                        <small><strong>Marca:</strong> <?php echo $produto['marca_nome']; ?></small><br>
                        <small><strong>Potência:</strong> <?php echo $produto['potencia']; ?></small><br>
                        <small>  <Strong>Estoque:</Strong> <?php echo $produto['estoque']; ?></small>
                    </div>
                            <button class="btn-primary btn-comprar" 
                style="width: 100%; margin-top: 1rem;"
                data-produto-id="<?php echo $produto['id']; ?>"
                data-produto-nome="<?php echo $produto['nome']; ?>"
                data-produto-preco="<?php echo $produto['preco']; ?>">
            🛒 Comprar Agora
            </button>
                </div>
            </div>
            <?php } ?>
        </div>
    </section>

    <!-- Seção Categorias -->
    <section id="categorias" class="categorias-section">
        <div class="container">
            <h2 class="section-title">📂 Nossas Categorias</h2>
            <div class="categorias-grid">
                <?php
                $query_categorias = "SELECT * FROM categorias WHERE ativo = TRUE ORDER BY nome";
                $stmt_categorias = $db->prepare($query_categorias);
                $stmt_categorias->execute();
                
                if ($stmt_categorias->rowCount() > 0) {
                    while ($categoria = $stmt_categorias->fetch(PDO::FETCH_ASSOC)) {
                        // Contar produtos na categoria
                        $query_count = "SELECT COUNT(*) as total FROM produtos WHERE categoria_id = :categoria_id AND ativo = TRUE";
                        $stmt_count = $db->prepare($query_count);
                        $stmt_count->bindParam(':categoria_id', $categoria['id']);
                        $stmt_count->execute();
                        $total_produtos = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
                ?>
                <div class="categoria-card">
                    <div class="categoria-icone">
                        <?php 
                        // Ícones diferentes para cada categoria
                        $icones = [
                            'Auto-Falante' => '🔊',
                            'Amplificador' => '⚡',
                            'Subwoofer' => '🎵',
                            'Tweeter' => '📢',
                            'Mid-range' => '🎶',
                            'Componente' => '🔧',
                            'Multimídia' => '📱',
                            'Acessórios' => '🔌'
                        ];
                        echo $icones[$categoria['nome']] ?? '🎵';
                        ?>
                    </div>
                    <h3><?php echo $categoria['nome']; ?></h3>
                    <p><?php echo $categoria['descricao'] ?? 'Produtos de alta qualidade'; ?></p>
                    <div class="categoria-info">
                       
                       
                    </div>
                </div>
                <?php 
                    }
                } else {
                    echo '<p class="sem-categorias">Nenhuma categoria cadastrada.</p>';
                }
                ?>
            </div>
        </div>
    </section>

      <!-- Seção Sobre -->
    <section id="sobre" class="sobre-section">
        <div class="container">
            <div class="sobre-content">
                <div class="sobre-texto">
                    <h2 class="section-title">🏢 Sobre a SomAuto Premium</h2>
                    <div class="sobre-descricao">
                        <p>Há mais de <strong>15 anos no mercado</strong>, a SomAuto Premium é referência em som automotivo de alta qualidade. Nossa missão é proporcionar a melhor experiência sonora para nossos clientes, com produtos de primeira linha e atendimento especializado.</p>
                        
                        <div class="sobre-vantagens">
                            <div class="vantagem-item">
                                <span class="vantagem-icone">✅</span>
                                <div>
                                    <h4>Qualidade Garantida</h4>
                                    <p>Produtos das melhores marcas com garantia</p>
                                </div>
                            </div>
                            <div class="vantagem-item">
                                <span class="vantagem-icone">🚚</span>
                                <div>
                                    <h4>Entrega Rápida</h4>
                                    <p>Entregamos em todo o Mocambique</p>
                                </div>
                            </div>
                            <div class="vantagem-item">
                                <span class="vantagem-icone">🔧</span>
                                <div>
                                    <h4>Instalação Profissional</h4>
                                    <p>Rede de instaladores credenciados</p>
                                </div>
                            </div>
                            <div class="vantagem-item">
                                <span class="vantagem-icone">💳</span>
                                <div>
                                    <h4>Parcele em até 12x</h4>
                                    <p>Pagamento facilitado</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="sobre-imagem">
                    <div class="imagem-placeholder">
                        🏪<br>Nossa Loja
                    </div>
                </div>
            </div>
            
            <!-- Estatísticas -->
            <div class="estatisticas">
                <div class="estatistica-item">
                    <div class="numero">15+</div>
                    <div class="label">Anos no Mercado</div>
                </div>
                <div class="estatistica-item">
                    <div class="numero">5000+</div>
                    <div class="label">Clientes Satisfeitos</div>
                </div>
                <div class="estatistica-item">
                    <div class="numero">100+</div>
                    <div class="label">Marcas Parceiras</div>
                </div>
                <div class="estatistica-item">
                    <div class="numero">24h</div>
                    <div class="label">Suporte Técnico</div>
                </div>
            </div>
        </div>
    </section>

  <!-- Seção Contato -->
    <section id="contato" class="contato-section">
        <div class="container">
            <h2 class="section-title">📞 Fale Conosco</h2>
            <div class="contato-content">
                <div class="contato-info">
                    <div class="info-item">
                        <div class="info-icone">📍</div>
                        <div class="info-texto">
                            <h4>Endereço</h4>
                            <p>Rua Carlos Cardoso, 123<br>,  - SP<br>CEP: 01234-567</p>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-icone">📞</div>
                        <div class="info-texto">
                            <h4>Telefone</h4>
                            <p>+258 + 870464693<br> (WhatsApp)</p>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-icone">✉️</div>
                        <div class="info-texto">
                            <h4>E-mail</h4>
                            <p>vendas@somautopremium.com.br<br>suporte@somautopremium.com.br</p>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-icone">🕒</div>
                        <div class="info-texto">
                            <h4>Horário de Atendimento</h4>
                            <p>Segunda a Sexta: 8h às 18h<br>Sábado: 8h às 12h</p>
                        </div>
                    </div>
                </div>
                
                <div class="contato-form">
                    <h3>Envie sua Mensagem</h3>
                    <form action="enviar-contato.php" method="POST">
                        <div class="form-group">
                            <input type="text" name="nome" placeholder="Seu nome completo" required>
                        </div>
                        <div class="form-group">
                            <input type="email" name="email" placeholder="Seu e-mail" required>
                        </div>
                        <div class="form-group">
                            <input type="tel" name="telefone" placeholder="Seu telefone">
                        </div>
                        <div class="form-group">
                            <select name="assunto" required>
                                <option value="">Selecione o assunto</option>
                                <option value="duvida">Dúvida sobre produto</option>
                                <option value="orcamento">Orçamento</option>
                                <option value="suporte">Suporte técnico</option>
                                <option value="reclamacao">Reclamação</option>
                                <option value="outro">Outro</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <textarea name="mensagem" placeholder="Sua mensagem..." rows="5" required></textarea>
                        </div>
                        <button type="submit" class="btn-primary">📤 Enviar Mensagem</button>
                    </form>
                </div>
            </div>
        </div>
    </section>


     <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>🎵 SomAuto Premium</h3>
                    <p>Sua loja de confiança em som automotivo há mais de 15 anos.</p>
                    <div class="social-links">
                        <a href="#">📘 Facebook</a>
                        <a href="#">📷 Instagram</a>
                        <a href="#">🐦 Twitter</a>
                    </div>
                </div>
                <div class="footer-section">
                    <h4>Links Rápidos</h4>
                    <ul>
                        <li><a href="#produtos">Produtos</a></li>
                        <li><a href="#categorias">Categorias</a></li>
                        <li><a href="#sobre">Sobre</a></li>
                        <li><a href="#contato">Contato</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Suporte</h4>
                    <ul>
                        <li><a href="#">Central de Ajuda</a></li>
                        <li><a href="#">Política de Trocas</a></li>
                        <li><a href="#">Garantia</a></li>
                        <li><a href="#">Instalação</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 SomAuto Premium. Todos os direitos reservados.</p>
            </div>
        </div>
    </footer>

    


    <!-- Modal do Carrinho -->
<div id="modalCarrinho" class="modal-carrinho">
    <div class="modal-carrinho-content">
        <span class="close-carrinho">&times;</span>
        <h3>🛒 Adicionar ao Carrinho</h3>
        
        <div id="modalProdutoInfo">
            <h4 id="modalProdutoNome"></h4>
            <p>Preço: R$ <span id="modalProdutoPreco"></span></p>
        </div>
        
        <div class="quantidade-group">
            <label><strong>Quantidade:</strong></label>
            <div class="quantidade-controls">
                <button class="btn-quantidade" id="decrementar">-</button>
                <input type="number" id="quantidade" value="1" min="1" max="99">
                <button class="btn-quantidade" id="incrementar">+</button>
            </div>
        </div>
        
        <div class="total-group">
            <strong>Total: R$ <span id="modalTotal">0.00</span></strong>
        </div>
        
        <button id="btnAdicionarCarrinho" class="btn-success">
            ✅ Adicionar ao Carrinho
        </button>
    </div>
</div>


<!-- Carrinho Flutuante -->
<div id="carrinhoFlutuante">
    <div class="carrinho-header">
        <span>🛒 Carrinho</span>
        <span id="carrinhoCount">0</span>
    </div>
    <div id="carrinhoItens"></div>
    <div class="carrinho-total" id="carrinhoTotal">Total: R$ 0.00</div>
    <button id="btnFinalizarCompra" class="btn-finalizar">💰 Finalizar Compra</button>
</div>

<!-- Modal Finalizar Venda -->
<div id="modalFinalizar" class="modal-finalizar">
    <div class="modal-finalizar-content">
        <span class="close-finalizar">&times;</span>
        <h3>💰 Finalizar Compra</h3>
        
        <div id="resumoCarrinho">
            <!-- Itens do carrinho aparecerão aqui -->
        </div>
        
        <div class="total-final">
            Total da Compra: R$ <span id="totalCompra">0.00</span>
        </div>
        
        <div class="form-cliente">
            <h4>📋 Seus Dados</h4>
            <form id="formCliente">
                <div class="form-group">
                    <label>Nome Completo:</label>
                    <input type="text" name="nome" placeholder="Seu nome completo" required>
                </div>
                <div class="form-group">
                    <label>E-mail:</label>
                    <input type="email" name="email" placeholder="seu@email.com" required>
                </div>
                <div class="form-group">
                    <label>Telefone:</label>
                    <input type="tel" name="telefone" placeholder="(11) 99999-9999" required>
                </div>
                <div class="form-group">
                    <label>Endereço:</label>
                    <input type="text" name="endereco" placeholder="Rua, número, bairro" required>
                </div>
            </form>
        </div>
        
        <button id="btnConfirmarVenda" class="btn-pagamento">
            💳 Confirmar e Finalizar Pedido
        </button>
    </div>
</div>

    <script src="Js/script.js"></script>
</body>
</html>