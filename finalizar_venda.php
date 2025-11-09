<?php
// finalizar_venda.php
session_start();

// HEADERS PARA JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Ativar logs mas não mostrar erros na resposta
error_reporting(E_ALL);
ini_set('display_errors', 0);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido. Use POST.']);
    exit();
}

// LOG para debug
error_log('=== FINALIZAR_VENDA.PHP INICIADO ===');

try {
    // Ler dados JSON
    $json_input = file_get_contents('php://input');
    
    if (empty($json_input)) {
        throw new Exception('Nenhum dado JSON recebido');
    }
    
    $dados = json_decode($json_input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON inválido: ' . json_last_error_msg());
    }
    
    error_log('Dados recebidos: ' . print_r($dados, true));
    
    // VALIDAÇÃO DOS DADOS
    if (!isset($dados['cliente'])) {
        throw new Exception("Campo obrigatório faltando: cliente");
    }
    
    if (!isset($dados['metodo_pagamento'])) {
        throw new Exception("Campo obrigatório faltando: metodo_pagamento");
    }
    
    if (!isset($dados['carrinho'])) {
        throw new Exception("Campo obrigatório faltando: carrinho");
    }
    
    $metodo_pagamento_codigo = $dados['metodo_pagamento'];
    $carrinho = $dados['carrinho'];
    
    error_log("Método pagamento: " . $metodo_pagamento_codigo);
    error_log("Itens no carrinho: " . count($carrinho));
    
    // Validar dados do cliente
    $campos_cliente_obrigatorios = ['nome', 'email', 'telefone', 'endereco'];
    foreach ($campos_cliente_obrigatorios as $campo) {
        if (!isset($dados['cliente'][$campo]) || empty(trim($dados['cliente'][$campo]))) {
            throw new Exception("Campo do cliente faltando: {$campo}");
        }
    }
    
    // Validar email
    if (!filter_var($dados['cliente']['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Email inválido: " . $dados['cliente']['email']);
    }
    
    // Validar carrinho
    if (empty($carrinho)) {
        throw new Exception('Carrinho vazio');
    }
    
    // Incluir conexão com banco
    require_once 'config/database.php';
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Iniciar transação
    $db->beginTransaction();
    
    // 1. VERIFICAR OU CRIAR CLIENTE
    $cliente_email = $dados['cliente']['email'];
    $query_cliente = "SELECT id FROM clientes WHERE email = :email";
    $stmt_cliente = $db->prepare($query_cliente);
    $stmt_cliente->execute([':email' => $cliente_email]);
    $cliente_existente = $stmt_cliente->fetch(PDO::FETCH_ASSOC);
    
    if ($cliente_existente) {
        $cliente_id = $cliente_existente['id'];
        error_log("✅ Cliente existente: ID {$cliente_id}");
    } else {
        // Criar novo cliente
        $query_inserir_cliente = "INSERT INTO clientes (nome, email, telefone, endereco) 
                                 VALUES (:nome, :email, :telefone, :endereco)";
        $stmt_inserir_cliente = $db->prepare($query_inserir_cliente);
        $resultado = $stmt_inserir_cliente->execute([
            ':nome' => $dados['cliente']['nome'],
            ':email' => $dados['cliente']['email'],
            ':telefone' => $dados['cliente']['telefone'],
            ':endereco' => $dados['cliente']['endereco']
        ]);
        
        if (!$resultado) {
            $errorInfo = $stmt_inserir_cliente->errorInfo();
            throw new Exception("Erro ao inserir cliente: " . $errorInfo[2]);
        }
        
        $cliente_id = $db->lastInsertId();
        error_log("✅ Novo cliente criado: ID {$cliente_id}");
    }
    
    // 2. BUSCAR ID DO MÉTODO DE PAGAMENTO
    $query_metodo = "SELECT id, nome FROM metodos_pagamento WHERE codigo = :codigo AND ativo = TRUE";
    $stmt_metodo = $db->prepare($query_metodo);
    $stmt_metodo->execute([':codigo' => $metodo_pagamento_codigo]);
    $metodo_pagamento = $stmt_metodo->fetch(PDO::FETCH_ASSOC);
    
    if (!$metodo_pagamento) {
        throw new Exception('Método de pagamento não encontrado: ' . $metodo_pagamento_codigo);
    }
    
    $metodo_pagamento_id = $metodo_pagamento['id'];
    $metodo_pagamento_nome = $metodo_pagamento['nome'];
    error_log("✅ Método de pagamento: {$metodo_pagamento_nome} (ID: {$metodo_pagamento_id})");
    
    // 3. CALCULAR TOTAL DA VENDA
    $total_venda = 0;
    foreach ($carrinho as $item) {
        $preco = floatval($item['preco']);
        $quantidade = intval($item['quantidade']);
        $subtotal = $preco * $quantidade;
        $total_venda += $subtotal;
    }
    error_log("💰 Total calculado: R$ {$total_venda}");
    
    // 4. INSERIR VENDA
    $query_venda = "INSERT INTO vendas (cliente_id, metodo_pagamento_id, total, status) 
                    VALUES (:cliente_id, :metodo_id, :total, 'pendente')";
    
    $stmt_venda = $db->prepare($query_venda);
    $resultado_venda = $stmt_venda->execute([
        ':cliente_id' => $cliente_id,
        ':metodo_id' => $metodo_pagamento_id,
        ':total' => $total_venda
    ]);
    
    if (!$resultado_venda) {
        $errorInfo = $stmt_venda->errorInfo();
        throw new Exception("Erro ao inserir venda: " . $errorInfo[2]);
    }
    
    $venda_id = $db->lastInsertId();
    error_log("✅ Venda criada: ID {$venda_id}");
    
    // 5. INSERIR ITENS DA VENDA
    $query_item = "INSERT INTO venda_itens 
                   (venda_id, produto_id, produto_nome, quantidade, preco_unitario, subtotal) 
                   VALUES (:venda_id, :produto_id, :produto_nome, :quantidade, :preco, :subtotal)";
    
    $stmt_item = $db->prepare($query_item);
    
    $itens_inseridos = 0;
    
    foreach ($carrinho as $item) {
        $preco = floatval($item['preco']);
        $quantidade = intval($item['quantidade']);
        $subtotal = $preco * $quantidade;
        
        $resultado_item = $stmt_item->execute([
            ':venda_id' => $venda_id,
            ':produto_id' => $item['id'],
            ':produto_nome' => $item['nome'],
            ':quantidade' => $quantidade,
            ':preco' => $preco,
            ':subtotal' => $subtotal
        ]);
        
        if (!$resultado_item) {
            $errorInfo = $stmt_item->errorInfo();
            throw new Exception("Erro ao inserir item: " . $errorInfo[2]);
        }
        
        $itens_inseridos++;
    }
    
    error_log("✅ {$itens_inseridos} itens inseridos");
    
    // Confirmar transação
    $db->commit();
    error_log("🎉 Venda finalizada com sucesso!");
    
    // Resposta de sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Venda processada com sucesso!',
        'venda_id' => $venda_id,
        'cliente_id' => $cliente_id,
        'total' => $total_venda,
        'metodo_pagamento' => $metodo_pagamento_nome,
        'itens_processados' => $itens_inseridos
    ]);
    
} catch (Exception $e) {
    // Reverter em caso de erro
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    error_log('❌ ERRO: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao processar venda: ' . $e->getMessage()
    ]);
}
?>