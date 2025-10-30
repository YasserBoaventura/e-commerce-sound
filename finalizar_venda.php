<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Lidar com preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Ler dados brutos
        $jsonInput = file_get_contents('php://input');
        $input = json_decode($jsonInput, true);
        
        if (!$input) {
            throw new Exception('Dados JSON inválidos ou vazios');
        }
        
        // Validar dados
        if (empty($input['cliente']['nome']) || 
            empty($input['cliente']['email']) ||  
            empty($input['cliente']['telefone'])) {
            throw new Exception('Nome, email e telefone são obrigatórios');
        }
        
        if (empty($input['itens']) || !is_array($input['itens'])) {
            throw new Exception('Nenhum item no carrinho');
        }
        
        $database = new Database();
        $db = $database->getConnection();
        
        // Iniciar transação
        $db->beginTransaction();
        
        // === 1. VERIFICAR ESTOQUE ANTES DE VENDER ===
        foreach ($input['itens'] as $item) {
            $queryEstoque = "SELECT estoque, nome FROM produtos WHERE id = :produto_id";
            $stmtEstoque = $db->prepare($queryEstoque);
            $stmtEstoque->bindValue(':produto_id', $item['id']);
            $stmtEstoque->execute();
            
            $produto = $stmtEstoque->fetch(PDO::FETCH_ASSOC);
            
            if (!$produto) {
                throw new Exception('Produto não encontrado: ID ' . $item['id']);
            }
            
            if ($produto['estoque'] < $item['quantidade']) {
                throw new Exception('Estoque insuficiente para ' . $produto['nome'] . 
                                  '. Disponível: ' . $produto['estoque'] . 
                                  ', Solicitado: ' . $item['quantidade']);
            }
        }
        
        // === 2. INSERIR VENDA ===
        $queryVenda = "INSERT INTO vendas (cliente_nome, cliente_email, cliente_telefone, cliente_endereco, total) 
                       VALUES (:nome, :email, :telefone, :endereco, :total)";
        $stmtVenda = $db->prepare($queryVenda);
        
        $stmtVenda->bindValue(':nome', $input['cliente']['nome']);
        $stmtVenda->bindValue(':email', $input['cliente']['email']);
        $stmtVenda->bindValue(':telefone', $input['cliente']['telefone']);
        $stmtVenda->bindValue(':endereco',$input['cliente']['endereco']);
        $stmtVenda->bindValue(':total', $input['total']);
        
        if (!$stmtVenda->execute()) {
            $errorInfo = $stmtVenda->errorInfo();
            throw new Exception('Erro ao inserir venda: ' . $errorInfo[2]);
        }
        
        $vendaId = $db->lastInsertId();
        
        // === 3. INSERIR ITENS DA VENDA ===
        $queryItem = "INSERT INTO venda_itens (venda_id, produto_id, produto_nome, quantidade, preco_unitario, subtotal) 
                      VALUES (:venda_id, :produto_id, :produto_nome, :quantidade, :preco_unitario, :subtotal)";
        $stmtItem = $db->prepare($queryItem);
        
        foreach ($input['itens'] as $item) {
            $stmtItem->bindValue(':venda_id', $vendaId);
            $stmtItem->bindValue(':produto_id', $item['id']);
            $stmtItem->bindValue(':produto_nome', $item['nome']);
            $stmtItem->bindValue(':quantidade', $item['quantidade']);
            $stmtItem->bindValue(':preco_unitario', $item['preco']);
            $stmtItem->bindValue(':subtotal', $item['subtotal']);
            
            if (!$stmtItem->execute()) {
                throw new Exception('Erro ao inserir item da venda');
            }
            
            // === 4. ATUALIZAR ESTOQUE DO PRODUTO ===
            $queryAtualizarEstoque = "UPDATE produtos SET estoque = estoque - :quantidade WHERE id = :produto_id";
            $stmtEstoque = $db->prepare($queryAtualizarEstoque);
            $stmtEstoque->bindValue(':quantidade', $item['quantidade']);
            $stmtEstoque->bindValue(':produto_id', $item['id']);
            
            if (!$stmtEstoque->execute()) {
                throw new Exception('Erro ao atualizar estoque do produto ID ' . $item['id']);
            }
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'venda_id' => $vendaId,
            'message' => 'Venda registrada com sucesso e estoque atualizado!'
        ]);
        
    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        
        echo json_encode([
            'success' => false,
            'message' => 'Erro: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Método não permitido. Use POST.'
    ]);
}
?>