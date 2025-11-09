<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$erro_login = '';
$sucesso = '';
$produto_editando = null;
$marca_editando = null;

// 🔍 VERIFICAR SE ESTÁ EDITANDO UM PRODUTO (ESTRUTURA 3FN)
if (isset($_GET['editar']) && isset($_SESSION['admin_logado']) && $_SESSION['admin_logado']) {
    try {
        $query = "SELECT p.*, m.nome as marca_nome 
                 FROM produtos p 
                 LEFT JOIN marcas m ON p.marca_id = m.id 
                 WHERE p.id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $_GET['editar']);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $produto_editando = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Buscar especificações do produto
            $query_espec = "SELECT tipo_especificacao, valor, unidade 
                           FROM especificacoes_produto 
                           WHERE produto_id = :produto_id";
            $stmt_espec = $db->prepare($query_espec);
            $stmt_espec->bindParam(':produto_id', $produto_editando['id']);
            $stmt_espec->execute();
            $especificacoes = $stmt_espec->fetchAll(PDO::FETCH_ASSOC);
            
            // Adicionar especificações ao array do produto
            foreach ($especificacoes as $espec) {
                $produto_editando[$espec['tipo_especificacao']] = $espec['valor'];
            }
        } else {
            $erro_login = "Produto não encontrado!";
        }
    } catch (PDOException $e) {
        $erro_login = "Erro ao buscar produto: " . $e->getMessage();
    }
}

// 🔍 VERIFICAR SE ESTÁ EDITANDO UMA MARCA
if (isset($_GET['editar_marca']) && isset($_SESSION['admin_logado']) && $_SESSION['admin_logado']) {
    try {
        $query = "SELECT * FROM marcas WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $_GET['editar_marca']);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $marca_editando = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $erro_login = "Marca não encontrada!";
        }
    } catch (PDOException $e) {
        $erro_login = "Erro ao buscar marca: " . $e->getMessage();
    }
}

// ❌ CANCELAR EDIÇÃO
if (isset($_GET['cancelar_edicao'])) {
    $produto_editando = null;
    header("Location: admin.php");
    exit;
}

// ❌ CANCELAR EDIÇÃO DE MARCA
if (isset($_GET['cancelar_edicao_marca'])) {
    $marca_editando = null;
    header("Location: admin.php#gerenciarMarcas");
    exit;
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit;
}

// 📁 FUNÇÃO PARA UPLOAD DE IMAGEM
function fazerUploadImagem($file, $produto_id) {
    if ($file['error'] === UPLOAD_ERR_OK) {
        $extensao = pathinfo($file['name'], PATHINFO_EXTENSION);
        $extensoesPermitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array(strtolower($extensao), $extensoesPermitidas)) {
            // Criar pasta de uploads se não existir
            if (!is_dir('uploads')) {
                mkdir('uploads', 0777, true);
            }
            
            $nomeArquivo = 'produto_' . $produto_id . '_' . time() . '.' . $extensao;
            $caminhoCompleto = 'uploads/' . $nomeArquivo;
            
            if (move_uploaded_file($file['tmp_name'], $caminhoCompleto)) {
                return $caminhoCompleto;
            }
        }
    }
    return null;
}

// ✅ CRUD - CRIAR PRODUTO (ESTRUTURA 3FN)
if (isset($_POST['criar_produto']) && isset($_SESSION['admin_logado']) && $_SESSION['admin_logado']) {
    try {
        $imagem_path = '';
        
        // Processar upload da imagem
        if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
            // Criar pasta uploads se não existir
            if (!is_dir('uploads')) {
                mkdir('uploads', 0777, true);
            }
            
            $extensao = pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION);
            $extensoesPermitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array(strtolower($extensao), $extensoesPermitidas)) {
                $nomeArquivo = 'produto_' . time() . '_' . uniqid() . '.' . $extensao;
                $caminhoCompleto = 'uploads/' . $nomeArquivo;
                
                if (move_uploaded_file($_FILES['imagem']['tmp_name'], $caminhoCompleto)) {
                    $imagem_path = $caminhoCompleto;
                    $sucesso .= " 📸 Imagem salva com sucesso!";
                }
            }
        }

        // GESTÃO DA MARCA - Verificar se a marca já existe
        $marca_nome = $_POST['marca'];
        $query_marca = "SELECT id FROM marcas WHERE nome = :nome";
        $checkMarca = $db->prepare($query_marca);
        $checkMarca->execute([':nome' => $marca_nome]);
        $marcaExistente = $checkMarca->fetch();

        if ($marcaExistente) {
            $marca_id = $marcaExistente['id'];
        } else {
            // Inserir nova marca
            $insertMarca = $db->prepare("INSERT INTO marcas (nome) VALUES (:nome)");
            $insertMarca->execute([':nome' => $marca_nome]);
            $marca_id = $db->lastInsertId();
        }
        
        // Iniciar transação para garantir consistência
        $db->beginTransaction();
        
        try {
            // Dados do produto principal (sem especificações)
            $dados_produto = [
                ':nome' => $_POST['nome'],
                ':descricao' => $_POST['descricao'],
                ':preco' => $_POST['preco'],
                ':preco_original' => !empty($_POST['preco_original']) ? $_POST['preco_original'] : null,
                ':estoque' => $_POST['estoque'],
                ':categoria_id' => $_POST['categoria_id'],
                ':marca_id' => $marca_id,
                ':modelo' => $_POST['modelo'],
                ':imagem' => $imagem_path,
                ':destaque' => isset($_POST['destaque']) ? 1 : 0
            ];
            
            // Inserir produto principal
            $query_produto = "INSERT INTO produtos (nome, descricao, preco, preco_original, estoque, categoria_id, marca_id, modelo, imagem, destaque) 
                             VALUES (:nome, :descricao, :preco, :preco_original, :estoque, :categoria_id, :marca_id, :modelo, :imagem, :destaque)";
            
            $stmt_produto = $db->prepare($query_produto);
            $stmt_produto->execute($dados_produto);
            $produto_id = $db->lastInsertId();
            
            // Inserir especificações na tabela separada
            $especificacoes = [
                ['tipo' => 'potencia', 'valor' => $_POST['potencia'], 'unidade' => 'W'],
                ['tipo' => 'impedancia', 'valor' => $_POST['impedancia'], 'unidade' => 'Ω'],
                ['tipo' => 'frequencia', 'valor' => $_POST['frequencia'], 'unidade' => 'Hz']
            ];
            
            $query_espec = "INSERT INTO especificacoes_produto (produto_id, tipo_especificacao, valor, unidade) 
                           VALUES (:produto_id, :tipo, :valor, :unidade)";
            $stmt_espec = $db->prepare($query_espec);
            
            $especs_inseridas = 0;
            foreach ($especificacoes as $espec) {
                if (!empty(trim($espec['valor']))) {
                    $stmt_espec->execute([
                        ':produto_id' => $produto_id,
                        ':tipo' => $espec['tipo'],
                        ':valor' => trim($espec['valor']),
                        ':unidade' => $espec['unidade']
                    ]);
                    $especs_inseridas++;
                }
            }
            
            // Confirmar transação
            $db->commit();
            
            $sucesso = "✅ Produto cadastrado com sucesso!" . 
                      ($imagem_path ? " 📸 Imagem salva!" : "") . 
                      ($especs_inseridas > 0 ? " 📊 $especs_inseridas especificação(ões) adicionada(s)!" : "");
            
        } catch (Exception $e) {
            // Reverter transação em caso de erro
            $db->rollBack();
            throw $e;
        }
        
    } catch (PDOException $e) {
        $erro_login = "❌ Erro ao cadastrar produto: " . $e->getMessage();
    }
}

// ✏️ CRUD - EDITAR PRODUTO (ESTRUTURA 3FN)
if (isset($_POST['editar_produto']) && isset($_SESSION['admin_logado']) && $_SESSION['admin_logado']) {
    try {
        $produto_id = $_POST['id'];
        $imagem_atual = $_POST['imagem_atual'] ?? '';
        
        // Verificar se uma nova imagem foi enviada
        if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
            $caminhoImagem = fazerUploadImagem($_FILES['imagem'], $produto_id);
            if ($caminhoImagem) {
                $imagem_atual = $caminhoImagem;
                // Opcional: deletar imagem antiga
                if (!empty($_POST['imagem_atual']) && file_exists($_POST['imagem_atual'])) {
                    unlink($_POST['imagem_atual']);
                }
            }
        }

        // GESTÃO DA MARCA - Verificar se a marca já existe
        $marca_nome = $_POST['marca'];
        $query_marca = "SELECT id FROM marcas WHERE nome = :nome";
        $checkMarca = $db->prepare($query_marca);
        $checkMarca->execute([':nome' => $marca_nome]);
        $marcaExistente = $checkMarca->fetch();

        if ($marcaExistente) {
            $marca_id = $marcaExistente['id'];
        } else {
            // Inserir nova marca
            $insertMarca = $db->prepare("INSERT INTO marcas (nome) VALUES (:nome)");
            $insertMarca->execute([':nome' => $marca_nome]);
            $marca_id = $db->lastInsertId();
        }
        
        // Iniciar transação
        $db->beginTransaction();
        
        try {
            // Atualizar produto principal
            $dados_produto = [
                ':id' => $produto_id,
                ':nome' => $_POST['nome'],
                ':descricao' => $_POST['descricao'],
                ':preco' => $_POST['preco'],
                ':preco_original' => !empty($_POST['preco_original']) ? $_POST['preco_original'] : null,
                ':estoque' => $_POST['estoque'],
                ':categoria_id' => $_POST['categoria_id'],
                ':marca_id' => $marca_id,
                ':modelo' => $_POST['modelo'],
                ':imagem' => $imagem_atual,
                ':destaque' => isset($_POST['destaque']) ? 1 : 0
            ];
            
$query_produto = "UPDATE produtos SET
                    nome = :nome, 
                    descricao = :descricao, 
                    preco = :preco, 
                    preco_original = :preco_original, 
                    estoque = :estoque, 
                    categoria_id = :categoria_id, 
                    marca_id = :marca_id, 
                    modelo = :modelo, 
                    imagem = :imagem,
                    destaque = :destaque 
                    WHERE id = :id";
            
            $stmt_produto = $db->prepare($query_produto);
            $stmt_produto->execute($dados_produto);
            
            // Atualizar especificações - primeiro remover as existentes
            $query_delete_espec = "DELETE FROM especificacoes_produto WHERE produto_id = :produto_id";
            $stmt_delete = $db->prepare($query_delete_espec);
            $stmt_delete->execute([':produto_id' => $produto_id]);
            
            // Inserir novas especificações
            $especificacoes = [
                ['tipo' => 'potencia', 'valor' => $_POST['potencia'], 'unidade' => 'W'],
                ['tipo' => 'impedancia', 'valor' => $_POST['impedancia'], 'unidade' => 'Ω'],
                ['tipo' => 'frequencia', 'valor' => $_POST['frequencia'], 'unidade' => 'Hz']
            ];
            
            $query_espec = "INSERT INTO especificacoes_produto (produto_id, tipo_especificacao, valor, unidade) 
                           VALUES (:produto_id, :tipo, :valor, :unidade)";
            $stmt_espec = $db->prepare($query_espec);
            
            $especs_inseridas = 0;
            foreach ($especificacoes as $espec) {
                if (!empty(trim($espec['valor']))) {
                    $stmt_espec->execute([
                        ':produto_id' => $produto_id,
                        ':tipo' => $espec['tipo'],
                        ':valor' => trim($espec['valor']),
                        ':unidade' => $espec['unidade']
                    ]);
                    $especs_inseridas++;
                }
            }
            
            // Confirmar transação
            $db->commit();
            
            $sucesso = "✅ Produto atualizado com sucesso!" . 
                      ($especs_inseridas > 0 ? " 📊 $especs_inseridas especificação(ões) atualizada(s)!" : "");
            $produto_editando = null; // Limpa o modo edição
            
        } catch (Exception $e) {
            // Reverter transação em caso de erro
            $db->rollBack();
            throw $e;
        }
        
    } catch (PDOException $e) {
        $erro_login = "❌ Erro ao editar produto: " . $e->getMessage();
    }
}

// 🏷️ CRUD - GERENCIAR MARCAS
if (isset($_POST['criar_marca']) && isset($_SESSION['admin_logado']) && $_SESSION['admin_logado']) {
    try {
        $marca_nome = $_POST['nome_marca'];
        
        // Verificar se marca já existe
        $query_verificar = "SELECT id FROM marcas WHERE nome = :nome";
        $stmt_verificar = $db->prepare($query_verificar);
        $stmt_verificar->execute([':nome' => $marca_nome]);
        
        if ($stmt_verificar->rowCount() > 0) {
            $erro_login = "❌ Esta marca já existe!";
        } else {
            // Inserir nova marca
            $query_inserir = "INSERT INTO marcas (nome, descricao) VALUES (:nome, :descricao)";
            $stmt_inserir = $db->prepare($query_inserir);
            $stmt_inserir->execute([
                ':nome' => $marca_nome,
                ':descricao' => $_POST['descricao_marca'] ?? ''
            ]);
            
            $sucesso = "✅ Marca cadastrada com sucesso!";
        }
    } catch (PDOException $e) {
        $erro_login = "❌ Erro ao cadastrar marca: " . $e->getMessage();
    }
}

// ✏️ CRUD - EDITAR MARCA
if (isset($_POST['editar_marca']) && isset($_SESSION['admin_logado']) && $_SESSION['admin_logado']) {
    try {
        $marca_id = $_POST['id'];
        $marca_nome = $_POST['nome_marca'];
        
        // Verificar se a marca já existe (excluindo a própria marca que está sendo editada)
        $query_verificar = "SELECT id FROM marcas WHERE nome = :nome AND id != :id";
        $stmt_verificar = $db->prepare($query_verificar);
        $stmt_verificar->execute([
            ':nome' => $marca_nome,
            ':id' => $marca_id
        ]);
        
        if ($stmt_verificar->rowCount() > 0) {
            $erro_login = "❌ Esta marca já existe!";
        } else {
            // Atualizar marca
            $query_editar = "UPDATE marcas SET nome = :nome, descricao = :descricao WHERE id = :id";
            $stmt_editar = $db->prepare($query_editar);
            $stmt_editar->execute([
                ':nome' => $marca_nome,
                ':descricao' => $_POST['descricao_marca'] ?? '',
                ':id' => $marca_id
            ]);
            
            $sucesso = "✅ Marca atualizada com sucesso!";
            $marca_editando = null; // Limpa o modo edição
        }
    } catch (PDOException $e) {
        $erro_login = "❌ Erro ao editar marca: " . $e->getMessage();
    }
}

// 🗑️ CRUD - DELETAR MARCA
if (isset($_GET['deletar_marca']) && isset($_SESSION['admin_logado']) && $_SESSION['admin_logado']) {
    try {
        $marca_id = $_GET['deletar_marca'];
        
        // Verificar se há produtos usando esta marca
        $query_verificar_produtos = "SELECT COUNT(*) as total FROM produtos WHERE marca_id = :marca_id";
        $stmt_verificar = $db->prepare($query_verificar_produtos);
        $stmt_verificar->execute([':marca_id' => $marca_id]);
        $resultado = $stmt_verificar->fetch(PDO::FETCH_ASSOC);
        
        if ($resultado['total'] > 0) {
            $erro_login = "❌ Não é possível deletar esta marca pois existem produtos vinculados a ela!";
        } else {
            // Deletar marca
            $query_deletar = "DELETE FROM marcas WHERE id = :id";
            $stmt_deletar = $db->prepare($query_deletar);
            $stmt_deletar->execute([':id' => $marca_id]);
            
            $sucesso = "✅ Marca deletada com sucesso!";
        }
    } catch (PDOException $e) {
        $erro_login = "❌ Erro ao deletar marca: " . $e->getMessage();
    }
}

// CRUD - Deletar produto
if (isset($_GET['deletar']) && isset($_SESSION['admin_logado']) && $_SESSION['admin_logado']) {
    try {
        // Buscar produto para deletar imagem
        $queryBuscar = "SELECT imagem FROM produtos WHERE id = :id";
        $stmtBuscar = $db->prepare($queryBuscar);
        $stmtBuscar->bindParam(':id', $_GET['deletar']);
        $stmtBuscar->execute();
        $produto = $stmtBuscar->fetch(PDO::FETCH_ASSOC);
        
        // Deletar imagem do servidor
        if ($produto && !empty($produto['imagem']) && file_exists($produto['imagem'])) {
            unlink($produto['imagem']);
        }
        
        // Deletar especificações do produto
        $query_delete_espec = "DELETE FROM especificacoes_produto WHERE produto_id = :produto_id";
        $stmt_delete_espec = $db->prepare($query_delete_espec);
        $stmt_delete_espec->bindParam(':produto_id', $_GET['deletar']);
        $stmt_delete_espec->execute();
        
        // Deletar produto
        $query = "DELETE FROM produtos WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $_GET['deletar']);
        $stmt->execute();
        
        $sucesso = "🗑️ Produto deletado com sucesso!";
    } catch (PDOException $e) {
        $erro_login = "❌ Erro ao deletar produto: " . $e->getMessage();
    }
}

// 📊 PROCESSAR FILTRO DE VENDAS (COM TABELA CLIENTES SEPARADA)
$filtro_data_inicio = $_GET['data_inicio'] ?? '';
$filtro_data_fim = $_GET['data_fim'] ?? '';
$vendas_filtradas = [];

if (isset($_GET['filtrar_vendas']) && isset($_SESSION['admin_logado']) && $_SESSION['admin_logado']) {
    try {
        // QUERY PRINCIPAL COM JOIN NA TABELA CLIENTES
        $query_vendas = "SELECT 
            v.id,
            v.total,
            v.status,
            v.data_venda,
            v.metodo_pagamento_id,
            v.cliente_id,
            c.nome as cliente_nome,
            c.email as cliente_email, 
            c.telefone as cliente_telefone,
            c.endereco as cliente_endereco,
            mp.nome as metodo_pagamento_nome,
            COUNT(vi.id) as total_itens,
            SUM(vi.quantidade) as total_produtos
        FROM vendas v 
        LEFT JOIN clientes c ON v.cliente_id = c.id
        LEFT JOIN venda_itens vi ON v.id = vi.venda_id
        LEFT JOIN metodos_pagamento mp ON v.metodo_pagamento_id = mp.id";
        
        $where_conditions = [];
        $params = [];
        
        // Filtro por data de início
        if (!empty($filtro_data_inicio)) {
            $where_conditions[] = "DATE(v.data_venda) >= :data_inicio";
            $params[':data_inicio'] = $filtro_data_inicio;
        }
        
        // Filtro por data de fim
        if (!empty($filtro_data_fim)) {
            $where_conditions[] = "DATE(v.data_venda) <= :data_fim";
            $params[':data_fim'] = $filtro_data_fim;
        }
        
        // Adicionar WHERE se houver filtros
        if (!empty($where_conditions)) {
            $query_vendas .= " WHERE " . implode(" AND ", $where_conditions);
        }
        
        $query_vendas .= " GROUP BY v.id ORDER BY v.data_venda DESC";
        
        $stmt_vendas = $db->prepare($query_vendas);
        $stmt_vendas->execute($params);
        $vendas_filtradas = $stmt_vendas->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Filtro aplicado: " . count($vendas_filtradas) . " vendas encontradas");
        
    } catch (PDOException $e) {
        $erro_login = "❌ Erro ao buscar vendas: " . $e->getMessage();
        error_log("ERRO FILTRO VENDAS: " . $e->getMessage());
    }
}

// 📈 ESTATÍSTICAS GERAIS (ATUALIZADO)
try {
    // Total de vendas
    $query_total_vendas = "SELECT COUNT(*) as total FROM vendas";
    $stmt_total = $db->query($query_total_vendas);
    $total_vendas = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Valor total vendido
    $query_valor_total = "SELECT SUM(total) as valor_total FROM vendas";
    $stmt_valor = $db->query($query_valor_total);
    $valor_total = $stmt_valor->fetch(PDO::FETCH_ASSOC)['valor_total'] ?? 0;
    
    // Venda do dia
    $query_vendas_hoje = "SELECT COUNT(*) as hoje FROM vendas WHERE DATE(data_venda) = CURDATE()";
    $stmt_hoje = $db->query($query_vendas_hoje);
    $vendas_hoje = $stmt_hoje->fetch(PDO::FETCH_ASSOC)['hoje'];
    
    // Vendas do mês
    $query_vendas_mes = "SELECT COUNT(*) as mes FROM vendas WHERE MONTH(data_venda) = MONTH(CURDATE()) AND YEAR(data_venda) = YEAR(CURDATE())";
    $stmt_mes = $db->query($query_vendas_mes);
    $vendas_mes = $stmt_mes->fetch(PDO::FETCH_ASSOC)['mes'];
    
    // Clientes cadastrados
    $query_total_clientes = "SELECT COUNT(*) as total FROM clientes";
    $stmt_clientes = $db->query($query_total_clientes);
    $total_clientes = $stmt_clientes->fetch(PDO::FETCH_ASSOC)['total'];
    
} catch (PDOException $e) {
    // Não quebrar a página se houver erro nas estatísticas
    $total_vendas = 0;
    $valor_total = 0;
    $vendas_hoje = 0;
    $vendas_mes = 0;
    $total_clientes = 0;
    error_log("ERRO ESTATÍSTICAS: " . $e->getMessage());
}

// 📋 BUSCAR MARCAS CADASTRADAS
try {
    $query_marcas = "SELECT * FROM marcas ORDER BY nome";
    $stmt_marcas = $db->query($query_marcas);
    $marcas = $stmt_marcas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $marcas = [];
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Som Automotivo</title>
    <style>
    /* ===== ESTILOS GERAIS ===== */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        color: #333;
    }

    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 30px 20px;
    }

    /* ===== HEADER ===== */
    .header {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        padding: 1rem 0;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        position: sticky;
        top: 0;
        z-index: 1000;
    }

    .navbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 2rem;
    }

    .logo {
        font-size: 1.8rem;
        font-weight: bold;
        color: #2c3e50;
        background: linear-gradient(135deg, #667eea, #764ba2);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .nav-links {
        display: flex;
        list-style: none;
        gap: 2rem;
    }

    .nav-links a {
        color: #2c3e50;
        text-decoration: none;
        font-weight: 600;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .nav-links a:hover {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        transform: translateY(-2px);
    }

    /* ===== CARDS E SEÇÕES ===== */
    .admin-section {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        padding: 2.5rem;
        border-radius: 20px;
        margin: 2rem 0;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        border: 1px solid rgba(255,255,255,0.2);
    }

    .admin-section h1 {
        color: #2c3e50;
        margin-bottom: 1rem;
        font-size: 2.5rem;
        background: linear-gradient(135deg, #667eea, #764ba2);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .admin-section h2 {
        color: #2c3e50;
        margin-bottom: 1.5rem;
        font-size: 1.8rem;
        border-bottom: 3px solid #667eea;
        padding-bottom: 0.5rem;
    }

    .welcome-text {
        font-size: 1.2rem;
        color: #666;
        margin-bottom: 2rem;
        padding: 1rem;
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        border-radius: 10px;
        border-left: 4px solid #667eea;
    }

    
    /* ===== ESTILOS ESPECÍFICOS PARA FORMULÁRIOS ===== */
    .form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem; /* Reduzi de 1.5rem para 1rem */
    margin: 1.5rem 0; 
    }

    .full-width {
        grid-column: 1 / -1;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        position: relative;
    }

    .form-group label {
        margin-bottom: 0.8rem;
        color: #2c3e50;
        font-weight: 700;
        font-size: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        background: linear-gradient(135deg, #667eea, #764ba2);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
    padding: 0.5rem 1rem; /* Reduzi de 1.2rem 1.5rem para 0.8rem 1rem */
    border: 2px solid #e1e8ed;
    border-radius: 12px; /* Reduzi de 15px para 12px */
    font-size: 0.9rem; /* Reduzi de 1rem para 0.9rem */
    background: linear-gradient(135deg, #f8f9fa, #ffffff);
    transition: all 0.4s ease;
    font-family: inherit;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    position: relative;
    z-index: 1;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #667eea;
        background: linear-gradient(135deg, #ffffff, #f8f9fa);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.15);
        transform: translateY(-3px);
    }

    .form-group input::placeholder,
    .form-group textarea::placeholder {
        color: #a0a0a0;
        font-weight: 400;
    }

    .form-group textarea {
        resize: vertical;
        min-height: 120px;
        line-height: 1.6;
    }

    /* Efeito de brilho nos inputs */
    .form-group::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        border-radius: 15px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        z-index: 0;
        opacity: 0;
        transition: opacity 0.4s ease;
    }

    .form-group:focus-within::before {
        opacity: 0.1;
    }

    /* Estilo especial para select */
    .form-group select {
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23667eea' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 1.5rem center;
        background-size: 12px;
        padding-right: 3rem;
        cursor: pointer;
    }

    /* Checkbox moderno */
    .checkbox-group {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1.5rem;
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        border-radius: 15px;
        border-left: 4px solid #ffc107;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .checkbox-group:hover {
        background: linear-gradient(135deg, #e9ecef, #f8f9fa);
        transform: translateX(5px);
    }

    .checkbox-group input[type="checkbox"] {
        transform: scale(1.5);
        accent-color: #ffc107;
        cursor: pointer;
    }

    .checkbox-group label {
        margin: 0;
        font-weight: 700;
        color: #2c3e50;
        cursor: pointer;
        font-size: 1.1rem;
    }

    /* Botão de submit especial */
    .btn-submit {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        padding: 1.5rem 3rem;
        border: none;
        border-radius: 15px;
        font-size: 1.2rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.4s ease;
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        text-transform: uppercase;
        letter-spacing: 1px;
        position: relative;
        overflow: hidden;
    }

    .btn-submit::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
        transition: left 0.5s ease;
    }

    .btn-submit:hover {
        background: linear-gradient(135deg, #764ba2, #667eea);
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(102, 126, 234, 0.4);
    }

    .btn-submit:hover::before {
        left: 100%;
    }

    .btn-submit:active {
        transform: translateY(-2px);
    }

    /* Ícones nos labels */
    .form-group label[for*="nome"]::before { content: "📝 "; }
    .form-group label[for*="descricao"]::before { content: "📄 "; }
    .form-group label[for*="preco"]::before { content: "💰 "; }
    .form-group label[for*="estoque"]::before { content: "📦 "; }
    .form-group label[for*="categoria"]::before { content: "📁 "; }
    .form-group label[for*="marca"]::before { content: "🏷️ "; }
    .form-group label[for*="modelo"]::before { content: "🔧 "; }
    .form-group label[for*="potencia"]::before { content: "⚡ "; }
    .form-group label[for*="impedancia"]::before { content: "🔌 "; }
    .form-group label[for*="frequencia"]::before { content: "📊 "; }

    /* Animações nos inputs */
    @keyframes inputGlow {
        0% { box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        50% { box-shadow: 0 4px 20px rgba(102, 126, 234, 0.2); }
        100% { box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        animation: inputGlow 2s infinite;
    }

    /* Responsivo para formulários */
    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 1rem;
        }
        
        .btn-submit {
            padding: 1.2rem 2rem;
            font-size: 1.1rem;
        }
    }

    /* ===== BOTÕES ===== */
    .btn-primary {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        padding: 1.2rem 2.5rem;
        border: none;
        border-radius: 12px;
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #764ba2, #667eea);
        transform: translateY(-3px);
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
    }

    .btn-primary:active {
        transform: translateY(-1px);
    }

    /* ===== TABELAS ===== */
    table {
        width: 100%;
        border-collapse: collapse;
        margin: 2rem 0;
        background: white;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }

    th {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        padding: 1.2rem;
        text-align: left;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 0.9rem;
    }

    td {
        padding: 1.2rem;
        border-bottom: 1px solid #e9ecef;
        color: #555;
    }

    tr:hover {
        background: #f8f9fa;
        transform: scale(1.01);
        transition: all 0.2s ease;
    }

    /* ===== LINKS DE AÇÃO ===== */
    .action-link {
        color: #667eea;
        text-decoration: none;
        font-weight: 600;
        margin-right: 1rem;
        padding: 0.5rem 1rem;
        border-radius: 6px;
        transition: all 0.3s ease;
        display: inline-block;
    }

    .action-link.edit {
        background: rgba(255, 193, 7, 0.1);
        color: #ffc107;
    }

    .action-link.delete {
        background: rgba(220, 53, 69, 0.1);
        color: #dc3545;
    }

    .action-link:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .action-link.edit:hover {
        background: #ffc107;
        color: white;
    }

    .action-link.delete:hover {
        background: #dc3545;
        color: white;
    }

    /* ===== MENSAGENS ===== */
    .message {
        padding: 1.2rem 1.5rem;
        margin: 1.5rem 0;
        border-radius: 12px;
        font-weight: 500;
        text-align: center;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }

    .success {
        background: linear-gradient(135deg, #51cf66, #40c057);
        color: white;
        border: none;
    }

    .error {
        background: linear-gradient(135deg, #ff6b6b, #fa5252);
        color: white;
        border: none;
    }

    /* ===== CHECKBOX ESTILIZADO ===== */
    .checkbox-group {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        border-radius: 10px;
        border-left: 4px solid #ffc107;
    }

    .checkbox-group input[type="checkbox"] {
        transform: scale(1.3);
        accent-color: #ffc107;
    }

    .checkbox-group label {
        margin: 0;
        font-weight: 600;
        color: #2c3e50;
    }

    /* ===== ESTADOS VAZIOS ===== */
    .empty-state {
        text-align: center;
        padding: 3rem 2rem;
        color: #666;
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        border-radius: 15px;
        border: 2px dashed #dee2e6;
    }

    .empty-state p {
        font-size: 1.2rem;
        margin-bottom: 1rem;
    }

    /* ===== ANIMAÇÕES ===== */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .admin-section {
        animation: fadeInUp 0.6s ease-out;
    }

    /* ===== RESPONSIVO ===== */
    @media (max-width: 768px) {
        .navbar {
            flex-direction: column;
            gap: 1rem;
            padding: 1rem;
        }

        .nav-links {
            gap: 1rem;
        }

        .form-grid {
            grid-template-columns: 1fr;
        }

        .container {
            padding: 20px 15px;
        }

        .admin-section {
            padding: 1.5rem;
        }

        .admin-section h1 {
            font-size: 2rem;
        }

        .admin-section h2 {
            font-size: 1.5rem;
        }

        table {
            display: block;
            overflow-x: auto;
        }

        th, td {
            padding: 0.8rem;
        }
    }

    /* ===== SCROLL PERSONALIZADO ===== */
    ::-webkit-scrollbar {
        width: 8px;
    }

    ::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, #667eea, #764ba2);
        border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(135deg, #764ba2, #667eea);
    }

    /* ===== SEÇÃO DE VENDAS ===== */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin: 2rem 0;
}

.stat-card {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 2rem;
    border-radius: 15px;
    text-align: center;
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 35px rgba(102, 126, 234, 0.4);
}

.stat-number {
    font-size: 2.5rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
    display: block;
}

.stat-label {
    font-size: 1rem;
    opacity: 0.9;
}

.stat-card.total {
    background: linear-gradient(135deg, #51cf66, #40c057);
}

.stat-card.hoje {
    background: linear-gradient(135deg, #ffd43b, #fcc419);
}

/* Formulário de filtro */
.filter-form {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    padding: 2rem;
    border-radius: 15px;
    margin: 2rem 0;
    border-left: 4px solid #667eea;
}

.filter-grid {
    display: grid;
    grid-template-columns: 1fr 1fr auto auto;
    gap: 1rem;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
}

.filter-group label {
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #2c3e50;
}

.filter-group input {
    padding: 1rem;
    border: 2px solid #e1e8ed;
    border-radius: 10px;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.filter-group input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.btn-filter {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    padding: 1rem 2rem;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-filter:hover {
    background: linear-gradient(135deg, #764ba2, #667eea);
    transform: translateY(-2px);
}

.btn-clear {
    background: linear-gradient(135deg, #6c757d, #495057);
    color: white;
    border: none;
    padding: 1rem 2rem;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    text-align: center;
    transition: all 0.3s ease;
}

.btn-clear:hover {
    background: linear-gradient(135deg, #495057, #6c757d);
    transform: translateY(-2px);
}

/* Tabela de vendas */
.venda-item {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    border-left: 4px solid #667eea;
    transition: all 0.3s ease;
}

.venda-item:hover {
    transform: translateX(5px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
}

.venda-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e9ecef;
}

.venda-id {
    font-size: 1.2rem;
    font-weight: bold;
    color: #2c3e50;
}

.venda-data {
    color: #666;
    font-size: 0.9rem;
}

.venda-cliente {
    margin-bottom: 1rem;
}

.venda-cliente strong {
    color: #2c3e50;
}

.venda-detalhes {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1rem;
}

.venda-itens {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
}

.item-venda {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
    border-bottom: 1px solid #e9ecef;
}

.item-venda:last-child {
    border-bottom: none;
}

.venda-total {
    text-align: right;
    font-size: 1.3rem;
    font-weight: bold;
    color: #28a745;
    padding-top: 1rem;
    border-top: 2px solid #e9ecef;
}

.no-results {
    text-align: center;
    padding: 3rem 2rem;
    color: #666;
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border-radius: 15px;
    border: 2px dashed #dee2e6;
}

.export-buttons {
    display: flex;
    gap: 1rem;
    margin: 1rem 0;
    justify-content: flex-end;
}

.btn-export {
    background: linear-gradient(135deg, #51cf66, #40c057);
    color: white;
    border: none;
    padding: 0.8rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
}

.btn-export:hover {
    background: linear-gradient(135deg, #40c057, #51cf66);
    transform: translateY(-2px);
}

/* Responsivo */
@media (max-width: 768px) {
    .filter-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .venda-detalhes {
        grid-template-columns: 1fr;
    }
    
    .venda-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .export-buttons {
        justify-content: center;
    }
}

/* ===== SEÇÃO DE MARCAS ===== */
.marcas-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin: 2rem 0;
}

@media (max-width: 768px) {
    .marcas-grid {
        grid-template-columns: 1fr;
    }
}

.marca-card {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    padding: 1.5rem;
    border-radius: 12px;
    border-left: 4px solid #667eea;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
}

.marca-card:hover {
    transform: translateX(5px);
    background: linear-gradient(135deg, #e9ecef, #f8f9fa);
}

.marca-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
    gap: 1rem;
}

.marca-nome {
    font-size: 1.3rem;
    font-weight: bold;
    color: #2c3e50;
}

.marca-descricao {
    color: #666;
    margin-bottom: 1rem;
}

.marca-info {
    display: flex;
    justify-content: space-between;
    color: #888;
    font-size: 0.9rem;
}

.marca-form {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    padding: 2rem;
    border-radius: 15px;
    border-left: 4px solid #51cf66;
}

.marca-form .form-group {
    margin-bottom: 1.5rem;
}

.marca-actions {
    display: flex;
    gap: 0.5rem;
    flex-shrink: 0;
}
</style>
    
</head>
<body>
    <header class="header">
        <nav class="navbar">
            <div class="logo">🔧 Admin SomAuto</div>
            <?php if(isset($_SESSION['admin_logado']) && $_SESSION['admin_logado']): ?>
            <ul class="nav-links">
                <li><a href="#listaProdutos">Produtos</a></li>
                <li><a href="#gerenciarMarcas">Marcas</a></li>
                <li><a href="#vendas">Relatórios</a></li>
                <li><a href="index.php">Ver Loja</a></li>
                <li><a href="funcionario.php"> Atendimento</a></li>
                <li><a href="login.php">Sair (<?php echo $_SESSION['admin_usuario']; ?>)</a></li>
            </ul>
            <?php endif; ?>
        </nav>
    </header>

    <div class="container">
        <?php if(isset($sucesso) && $sucesso): ?>
            <div class="message success">✅ <?php echo $sucesso; ?></div>
        <?php endif; ?>

        <?php if(isset($erro_login) && $erro_login): ?>
            <div class="message error">❌ <?php echo $erro_login; ?></div>
        <?php endif; ?>

        <?php if(!isset($_SESSION['admin_logado']) || !$_SESSION['admin_logado']): ?>
        <!-- a logica ja foi implementada na ficheiro login.php-->

        <?php else: ?>
        <!-- ÁREA ADMINISTRATIVA -->
         <progress max="50"> </progress>
<div class="admin-section">
    <h1>🎯 Painel Administrativo</h1>
    <div class="welcome-text">
        Bem-vindo, <strong><?php echo $_SESSION['admin_usuario']; ?></strong>!
    </div>

   <!-- FORMULÁRIO DE CADASTRO/EDIÇÃO DE PRODUTO -->
<div class="admin-section">
    <h2>
        <?php if($produto_editando): ?>
            ✏️ Editando Produto: <span style="color: #667eea;"><?php echo $produto_editando['nome']; ?></span>
        <?php else: ?>
            ➕ Adicionar Novo Produto
        <?php endif; ?>
    </h2>
    
 <form method="POST" class="form-grid" enctype="multipart/form-data">
        <?php if($produto_editando): ?>
            <input type="hidden" name="editar_produto" value="true">
            <input type="hidden" name="id" value="<?php echo $produto_editando['id']; ?>">
            <input type="hidden" name="imagem_atual" value="<?php echo $produto_editando['imagem']; ?>">
        <?php else: ?>
            <input type="hidden" name="criar_produto" value="true">
        <?php endif; ?>
        
        <div class="form-group full-width">
            <label for="nome">Nome do Produto</label>
            <input type="text" name="nome" id="nome" 
                   value="<?php echo $produto_editando ? htmlspecialchars($produto_editando['nome']) : ''; ?>" 
                   placeholder="Ex: Auto-Falante Pioneer TS-A1670F" required>
        </div>
        
        <div class="form-group full-width">
            <label for="descricao">Descrição do Produto</label>
            <textarea name="descricao" id="descricao" 
                      placeholder="Descreva as características e benefícios do produto..." 
                      required><?php echo $produto_editando ? htmlspecialchars($produto_editando['descricao']) : ''; ?></textarea>
        </div>
        
        <div class="form-group">
            <label for="preco">Preço</label>
            <input type="number" name="preco" id="preco" step="0.01" 
                   value="<?php echo $produto_editando ? $produto_editando['preco'] : ''; ?>" 
                   placeholder="MT 299.90" required>
        </div>
        
        <div class="form-group">
            <label for="preco_original">Preço Original</label>
            <input type="number" name="preco_original" id="preco_original" step="0.01" 
                   value="<?php echo $produto_editando ? $produto_editando['preco_original'] : ''; ?>" 
                   placeholder="R$ 399.90 (opcional)">
        </div>
        
        <div class="form-group">
            <label for="estoque">Estoque</label>
            <input type="number" name="estoque" id="estoque" 
                   value="<?php echo $produto_editando ? $produto_editando['estoque'] : ''; ?>" 
                   placeholder="Quantidade disponível" required>
        </div>
        
        <div class="form-group">
            <label for="categoria_id">Categoria</label>
            <select name="categoria_id" id="categoria_id" required>
                <option value="">Selecione uma categoria</option>
                <option value="1" <?php echo ($produto_editando && $produto_editando['categoria_id'] == 1) ? 'selected' : ''; ?>>Auto-Falantes</option>
                <option value="2" <?php echo ($produto_editando && $produto_editando['categoria_id'] == 2) ? 'selected' : ''; ?>>Amplificadores</option>
                <option value="3" <?php echo ($produto_editando && $produto_editando['categoria_id'] == 3) ? 'selected' : ''; ?>>Subwoofers</option>
                <option value="4" <?php echo ($produto_editando && $produto_editando['categoria_id'] == 4) ? 'selected' : ''; ?>>Tweeters</option>
                <option value="5" <?php echo ($produto_editando && $produto_editando['categoria_id'] == 5) ? 'selected' : ''; ?>>Kits Completos</option>
            </select>
        </div>
        
        <div class="form-group">
            <label for="marca">Marca</label>
            <input type="text" name="marca" id="marca" 
                   value="<?php echo $produto_editando ? htmlspecialchars($produto_editando['marca_nome']) : ''; ?>" 
                   placeholder="Ex: Pioneer, JBL, Taramps..." required>
            <small style="color: #666; margin-top: 0.5rem; display: block;">
                💡 A marca será cadastrada automaticamente se não existir
            </small>
        </div>
        
        <div class="form-group">
            <label for="modelo">Modelo</label>
            <input type="text" name="modelo" id="modelo" 
                   value="<?php echo $produto_editando ? htmlspecialchars($produto_editando['modelo']) : ''; ?>" 
                   placeholder="Ex: TS-A1670F, GTO 19T..." required>
        </div>
        
        <div class="form-group">
            <label for="potencia">Potência</label>
            <input type="text" name="potencia" id="potencia" 
                   value="<?php echo $produto_editando ? htmlspecialchars($produto_editando['potencia']) : ''; ?>" 
                   placeholder="Ex: 400W RMS, 350W Max">
        </div>
        
        <div class="form-group">
            <label for="impedancia">Impedância</label>
            <input type="text" name="impedancia" id="impedancia" 
                   value="<?php echo $produto_editando ? htmlspecialchars($produto_editando['impedancia']) : ''; ?>" 
                   placeholder="Ex: 4Ω, 2Ω, 8Ω">
        </div>
        
        <div class="form-group">
            <label for="frequencia">Frequência</label>
            <input type="text" name="frequencia" id="frequencia" 
                   value="<?php echo $produto_editando ? htmlspecialchars($produto_editando['frequencia']) : ''; ?>" 
                   placeholder="Ex: 20-20.000 Hz, 35-30.000 Hz">
        </div>

        <div class="form-group">
            <label for="imagem">Imagem do Produto</label>
            <input type="file" name="imagem" id="imagem" accept="image/*">
            <?php if($produto_editando && !empty($produto_editando['imagem'])): ?>
                <small style="color: #666; margin-top: 0.5rem; display: block;">
                    📷 Imagem atual: <?php echo basename($produto_editando['imagem']); ?>
                </small>
            <?php endif; ?>
        </div>
        
        <div class="checkbox-group full-width">
            <input type="checkbox" name="destaque" value="1" id="destaque" 
                   <?php echo ($produto_editando && $produto_editando['destaque'] == 1) ? 'checked' : ''; ?>>
            <label for="destaque">Destacar este produto na loja principal</label>
        </div>
        
        <div class="form-group full-width" style="display: flex; gap: 1rem; margin-top: 2rem;">
            <?php if($produto_editando): ?>
                <button type="submit" class="btn-submit" style="flex: 1; background: linear-gradient(135deg, #51cf66, #40c057);">
                    💾 ATUALIZAR PRODUTO
                </button>
                <a href="admin.php?cancelar_edicao=true" class="btn-submit" style="flex: 1; background: linear-gradient(135deg, #6c757d, #495057); text-decoration: none; text-align: center; display: flex; align-items: center; justify-content: center;">
                    ❌ CANCELAR EDIÇÃO
                </a>
            <?php else: ?>
                <button type="submit" class="btn-submit full-width">
                    💾 CADASTRAR PRODUTO
                </button>
            <?php endif; ?>
        </div>
    </form>
</div>

    <!-- SEÇÃO DE GERENCIAMENTO DE MARCAS -->
    <div class="admin-section" id="gerenciarMarcas">
        <h2>🏷️ Gerenciar Marcas</h2>
        
        <div class="marcas-grid">
            <!-- FORMULÁRIO PARA CADASTRAR/EDITAR MARCA -->
            <div class="marca-form">
                <h3>
                    <?php if($marca_editando): ?>
                        ✏️ Editando Marca: <span style="color: #667eea;"><?php echo htmlspecialchars($marca_editando['nome']); ?></span>
                    <?php else: ?>
                        ➕ Cadastrar Nova Marca
                    <?php endif; ?>
                </h3>
                
                <form method="POST">
                    <?php if($marca_editando): ?>
                        <input type="hidden" name="editar_marca" value="true">
                        <input type="hidden" name="id" value="<?php echo $marca_editando['id']; ?>">
                    <?php else: ?>
                        <input type="hidden" name="criar_marca" value="true">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="nome_marca">Nome da Marca</label>
                        <input type="text" name="nome_marca" id="nome_marca" 
                               value="<?php echo $marca_editando ? htmlspecialchars($marca_editando['nome']) : ''; ?>" 
                               placeholder="Ex: Pioneer, JBL, Sony..." required>
                    </div>
                    
                    <div class="form-group">
                        <label for="descricao_marca">Descrição (Opcional)</label>
                        <textarea name="descricao_marca" id="descricao_marca" 
                                  placeholder="Breve descrição sobre a marca..."><?php echo $marca_editando ? htmlspecialchars($marca_editando['descricao']) : ''; ?></textarea>
                    </div>
                    
                    <div class="form-group" style="display: flex; gap: 1rem; margin-top: 1rem;">
                        <?php if($marca_editando): ?>
                            <button type="submit" class="btn-submit" style="flex: 1; background: linear-gradient(135deg, #51cf66, #40c057);">
                                💾 ATUALIZAR MARCA
                            </button>
                            <a href="admin.php?cancelar_edicao_marca=true#gerenciarMarcas" 
                               class="btn-submit" 
                               style="flex: 1; background: linear-gradient(135deg, #6c757d, #495057); text-decoration: none; text-align: center; display: flex; align-items: center; justify-content: center;">
                                ❌ CANCELAR
                            </a>
                        <?php else: ?>
                            <button type="submit" class="btn-submit" style="background: linear-gradient(135deg, #51cf66, #40c057);">
                                💾 CADASTRAR MARCA
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- LISTA DE MARCAS CADASTRADAS -->
            <div>
                <h3>📋 Marcas Cadastradas</h3>
                
                <?php if (count($marcas) > 0): ?>
                    <?php foreach ($marcas as $marca): ?>
                        <div class="marca-card">
                            <div class="marca-header">
                                <div class="marca-nome"><?php echo htmlspecialchars($marca['nome']); ?></div>
                                <div class="marca-actions">
                                    <a href="admin.php?editar_marca=<?php echo $marca['id']; ?>#gerenciarMarcas" 
                                       class="action-link edit">
                                        ✏️ Editar
                                    </a>
                                    <a href="admin.php?deletar_marca=<?php echo $marca['id']; ?>" 
                                       class="action-link delete" 
                                       onclick="return confirm('Tem certeza que deseja deletar esta marca?')">
                                        🗑️ Excluir
                                    </a>
                                </div>
                            </div>
                            
                            <?php if (!empty($marca['descricao'])): ?>
                                <div class="marca-descricao"><?php echo htmlspecialchars($marca['descricao']); ?></div>
                            <?php endif; ?>
                            
                            <div class="marca-info">
                                <span>ID: <?php echo $marca['id']; ?></span>
                                <?php if (isset($marca['created_at'])): ?>
                                    <span>Cadastro: <?php echo date('d/m/Y', strtotime($marca['created_at'])); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <p>📭 Nenhuma marca cadastrada ainda.</p>
                        <p>Use o formulário ao lado para cadastrar sua primeira marca!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- LISTA DE PRODUTOS -->
    <div class="admin-section" id="listaProdutos">
        <h2>📦 Produtos Cadastrados</h2>
        
        <?php
        try {
            $query = "SELECT p.*, c.nome as categoria_nome, m.nome as marca_nome 
                     FROM produtos p 
                     LEFT JOIN categorias c ON p.categoria_id = c.id 
                     LEFT JOIN marcas m ON p.marca_id = m.id 
                     ORDER BY p.id DESC";  
            $stmt = $db->query($query);
            $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($produtos) > 0): 
        ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Marca</th>
                    <th>Preço</th>
                    <th>Estoque</th>
                    <th>Categoria</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($produtos as $produto): ?>
                <tr>
                    <td><?php echo $produto['id']; ?></td>
                    <td><strong><?php echo $produto['nome']; ?></strong></td>
                    <td><?php echo $produto['marca_nome']; ?></td>
                    <td>MT <?php echo number_format($produto['preco'], 2, ',', '.'); ?></td>
                    <td><?php echo $produto['estoque']; ?></td>
                    <td><?php echo $produto['categoria_nome']; ?></td>
                    <td>
                        <a href="admin.php?editar=<?php echo $produto['id']; ?>" class="action-link edit">✏️ Editar</a>
                        <a href="admin.php?deletar=<?php echo $produto['id']; ?>" class="action-link delete" onclick="return confirm('Tem certeza que deseja excluir este produto?')">🗑️ Excluir</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <p>📭 Nenhum produto cadastrado ainda.</p>
            <p>Use o formulário acima para adicionar seu primeiro produto!</p>
        </div>
        <?php 
            endif;
        } catch (PDOException $e) {
            echo "<div class='message error'>Erro ao carregar produtos: " . $e->getMessage() . "</div>";
        }
        ?>
    </div>

   <!-- SEÇÃO DE VENDAS REALIZADAS -->
<div class="admin-section" id="vendas">
    <h2>💰 Relatório de Vendas</h2>
    
    <!-- ESTATÍSTICAS ATUALIZADAS -->
    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-number"><?php echo $total_vendas; ?></span>
            <span class="stat-label">Total de Vendas</span>
        </div>
        <div class="stat-card total">
            <span class="stat-number">MT <?php echo number_format($valor_total, 2, ',', '.'); ?></span>
            <span class="stat-label">Valor Total</span>
        </div>
        <div class="stat-card hoje">
            <span class="stat-number"><?php echo $vendas_hoje; ?></span>
            <span class="stat-label">Vendas Hoje</span>
        </div>
        <div class="stat-card" style="background: linear-gradient(135deg, #339af0, #228be6);">
            <span class="stat-number"><?php echo $total_clientes; ?></span>
            <span class="stat-label">Clientes Cadastrados</span>
        </div>
    </div>

    <!-- FORMULÁRIO DE FILTRO -->
    <div class="filter-form">
        <h3 style="margin-bottom: 1.5rem; color: #2c3e50;">📅 Filtrar Vendas por Período</h3>
        <form method="GET" class="filter-grid">
            <input type="hidden" name="filtrar_vendas" value="1">
            <div class="filter-group">
                <label for="data_inicio">Data Início</label>
                <input type="date" name="data_inicio" id="data_inicio" 
                       value="<?php echo htmlspecialchars($filtro_data_inicio); ?>">
            </div>
            <div class="filter-group">
                <label for="data_fim">Data Fim</label>
                <input type="date" name="data_fim" id="data_fim" 
                       value="<?php echo htmlspecialchars($filtro_data_fim); ?>">
            </div>
            <button type="submit" class="btn-filter">
                🔍 Filtrar Vendas
            </button>
            <a href="admin.php#vendas" class="btn-clear">
                🗑️ Limpar Filtro
            </a>
        </form>
        
        <?php if (!empty($filtro_data_inicio) || !empty($filtro_data_fim)): ?>
            <div style="margin-top: 1rem; padding: 1rem; background: rgba(102, 126, 234, 0.1); border-radius: 8px;">
                <strong>📊 Filtro Ativo:</strong>
                <?php if (!empty($filtro_data_inicio)): ?>
                    Desde <?php echo date('d/m/Y', strtotime($filtro_data_inicio)); ?>
                <?php endif; ?>
                <?php if (!empty($filtro_data_fim)): ?>
                    até <?php echo date('d/m/Y', strtotime($filtro_data_fim)); ?>
                <?php endif; ?>
                <?php if (!empty($vendas_filtradas)): ?>
                    - <strong><?php echo count($vendas_filtradas); ?> vendas encontradas</strong>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- LISTA DE VENDAS CORRIGIDA -->
    <div class="vendas-lista">
        <h3 style="margin: 2rem 0 1rem 0; color: #2c3e50;">
            <?php if (!empty($filtro_data_inicio) || !empty($filtro_data_fim)): ?>
                📋 Vendas Filtradas (<?php echo count($vendas_filtradas); ?>)
            <?php else: ?>
                📋 Últimas Vendas
            <?php endif; ?>
        </h3>

        <?php if (empty($vendas_filtradas) && (isset($_GET['filtrar_vendas']) || $total_vendas > 0)): ?>
            <div class="no-results">
                <p>📭 Nenhuma venda encontrada para o período selecionado.</p>
                <p>Tente ajustar as datas do filtro.</p>
            </div>
        <?php elseif (empty($vendas_filtradas) && $total_vendas == 0): ?>
            <div class="no-results">
                <p>📭 Nenhuma venda realizada ainda.</p>
                <p>As vendas aparecerão aqui automaticamente.</p>
            </div>
        <?php else: ?>
            <?php foreach ($vendas_filtradas as $venda): ?>
                <div class="venda-item">
                    <div class="venda-header">
                        <div class="venda-id">
                            Venda #<?php echo str_pad($venda['id'], 6, '0', STR_PAD_LEFT); ?>
                            <?php if (!empty($venda['metodo_pagamento_nome'])): ?>
                                <small style="color: #666; margin-left: 1rem;">
                                    💳 <?php echo $venda['metodo_pagamento_nome']; ?>
                                </small>
                            <?php endif; ?>
                        </div>
                        <div class="venda-data">
                            📅 <?php echo date('d/m/Y H:i', strtotime($venda['data_venda'])); ?>
                        </div>
                    </div>
                    
                    <div class="venda-cliente">
                        <strong>👤 Cliente:</strong> 
                        <?php echo htmlspecialchars($venda['cliente_nome']); ?> | 
                        <?php echo htmlspecialchars($venda['cliente_email']); ?> | 
                        📞 <?php echo htmlspecialchars($venda['cliente_telefone']); ?>
                        <?php if (!empty($venda['cliente_endereco'])): ?>
                            | 📍 <?php echo htmlspecialchars($venda['cliente_endereco']); ?>
                        <?php endif; ?>
                    </div>

                    <!-- ITENS DA VENDA -->
                    <div class="venda-detalhes">
                        <div style="grid-column: 1 / -1;">
                            <strong>📦 Itens da Venda:</strong>
                            <div class="venda-itens">
                                <?php
                                // Buscar itens desta venda específica
                                try {
                                    $query_itens = "SELECT * FROM venda_itens WHERE venda_id = :venda_id";
                                    $stmt_itens = $db->prepare($query_itens);
                                    $stmt_itens->bindValue(':venda_id', $venda['id']);
                                    $stmt_itens->execute();
                                    $itens = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    if (count($itens) > 0):
                                        foreach ($itens as $item):
                                ?>
                                <div class="item-venda">
                                    <span><?php echo htmlspecialchars($item['produto_nome']); ?></span>
                                    <span><?php echo $item['quantidade']; ?> x MT <?php echo number_format($item['preco_unitario'], 2, ',', '.'); ?></span>
                                    <span><strong>MT <?php echo number_format($item['subtotal'], 2, ',', '.'); ?></strong></span>
                                </div>
                                <?php 
                                        endforeach;
                                    else:
                                ?>
                                <div style="padding: 1rem; text-align: center; color: #666;">
                                    Nenhum item encontrado para esta venda
                                </div>
                                <?php
                                    endif;
                                } catch (PDOException $e) {
                                    echo "<div style='color: #dc3545; padding: 1rem;'>Erro ao carregar itens: " . $e->getMessage() . "</div>";
                                }
                                ?>
                            </div>
                        </div>
                        
                        <div>
                            <strong>📊 Resumo:</strong><br>
                            • Itens: <?php echo $venda['total_itens']; ?><br>
                            • Produtos: <?php echo $venda['total_produtos']; ?><br>
                            • Status: 
                            <span style="color: 
                                <?php echo $venda['status'] == 'paga' ? '#28a745' : 
                                      ($venda['status'] == 'pendente' ? '#ffc107' : '#dc3545'); ?>">
                                <?php echo ucfirst($venda['status']); ?>
                            </span>
                        </div>
                        
                        <div class="venda-total">
                            Total: MT <?php echo number_format($venda['total'], 2, ',', '.'); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</div>

 <?php endif; ?>
</body>
</html>