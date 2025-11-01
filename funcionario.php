<?php
require_once 'config/database.php';

// Inicializar variáveis
$contatos = [];
$filtro_status = $_GET['status'] ?? '';
$filtro_prioridade = $_GET['prioridade'] ?? '';
$filtro_assunto = $_GET['assunto'] ?? '';
$filtro_data = $_GET['data'] ?? '';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Construir query com filtros
    $sql = "SELECT * FROM contatos WHERE 1=1";
    $params = [];
    
    if (!empty($filtro_status)) {
        $sql .= " AND status = :status";
        $params[':status'] = $filtro_status;
    }
    
    if (!empty($filtro_prioridade)) {
        $sql .= " AND prioridade = :prioridade";
        $params[':prioridade'] = $filtro_prioridade;
    }
    
    if (!empty($filtro_assunto)) {
        $sql .= " AND assunto = :assunto";
        $params[':assunto'] = $filtro_assunto;
    }
    
    if (!empty($filtro_data)) {
        $sql .= " AND DATE(data_criacao) = :data";
        $params[':data'] = $filtro_data;
    }
    
    $sql .= " ORDER BY 
        CASE prioridade 
            WHEN 'urgente' THEN 1
            WHEN 'alta' THEN 2
            WHEN 'media' THEN 3
            WHEN 'baixa' THEN 4
        END,
        data_criacao DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $contatos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $erro = "Erro ao carregar contatos: " . $e->getMessage();
}

// Processar ações (responder/fechar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['acao'])) {
        try {
            $contato_id = $_POST['contato_id'] ?? '';
            
            if (empty($contato_id)) {
                throw new Exception('Contato ID não especificado.');
            }
            
            if ($_POST['acao'] === 'responder') {
                $resposta = $_POST['resposta'] ?? '';
                
                if (empty($resposta)) {
                    throw new Exception('A resposta não pode estar vazia.');
                }
                
                // Inserir resposta usando administrador_id
                $stmt = $db->prepare("INSERT INTO respostas (contato_id, administrador_id, resposta) VALUES (:contato_id, :administrador_id, :resposta)");
                $result = $stmt->execute([
                    ':contato_id' => $contato_id,
                    ':administrador_id' => 1, // ID do administrador logado
                    ':resposta' => $resposta
                ]);
                
                if (!$result) {
                    throw new Exception('Erro ao inserir resposta no banco.');
                }
                
                // Atualizar status para respondido
                $stmt = $db->prepare("UPDATE contatos SET status = 'respondido' WHERE id = :contato_id");
                $result = $stmt->execute([':contato_id' => $contato_id]);
                
                if (!$result) {
                    throw new Exception('Erro ao atualizar status do contato.');
                }
                
                $sucesso = "Resposta enviada com sucesso!";
                
            } elseif ($_POST['acao'] === 'fechar') {
                // Fechar contato
                $stmt = $db->prepare("UPDATE contatos SET status = 'fechado' WHERE id = :contato_id");
                $result = $stmt->execute([':contato_id' => $contato_id]);
                
                if (!$result) {
                    throw new Exception('Erro ao fechar contato.');
                }
                
                $sucesso = "Contato fechado com sucesso!";
            }
            
            // Recarregar a página para atualizar os dados
            header("Location: funcionario.php?" . $_SERVER['QUERY_STRING']);
            exit;
            
        } catch (Exception $e) {
            $erro = "Erro ao processar ação: " . $e->getMessage();
            // Log detalhado para debug
            error_log("ERRO DETALHADO: " . $e->getMessage());
            error_log("POST data: " . print_r($_POST, true));
        }
    }
}

// Funções de formatação
function formatarAssunto($assunto) {
    $assuntos = [
        'duvida' => '❓ Dúvida',
        'orcamento' => '💰 Orçamento',
        'suporte' => '🔧 Suporte',
        'reclamacao' => '⚠️ Reclamação',
        'outro' => '📄 Outro'
    ];
    return $assuntos[$assunto] ?? $assunto;
}

function formatarPrioridade($prioridade) {
    $prioridades = [
        'baixa' => '⬇️ Baixa',
        'media' => '🔸 Média',
        'alta' => '⬆️ Alta',
        'urgente' => '🚨 Urgente'
    ];
    return $prioridades[$prioridade] ?? $prioridade;
}

function formatarStatus($status) {
    $statusMap = [
        'pendente' => '⏳ Pendente',
        'em_andamento' => '🔄 Em Andamento',
        'respondido' => '✅ Respondido',
        'fechado' => '📁 Fechado'
    ];
    return $statusMap[$status] ?? $status;
}

 function formatarData($data) {
    return date('d/m/Y', strtotime($data));
}

// Calcular estatísticas
$estatisticas = [
    'pendente' => 0,
    'em_andamento' => 0,
    'respondido' => 0,
    'fechado' => 0
];

foreach ($contatos as $contato) {
    if (isset($estatisticas[$contato['status']])) {
        $estatisticas[$contato['status']]++;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Contatos - Funcionários</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
         background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .filters {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .filter-group label {
            font-weight: 600;
            font-size: 14px;
        }

        select, input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .btn-filter {
            background: #3498db;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            align-self: flex-end;
        }

        .btn-filter:hover {
            background: #2980b9;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .stat-number {
            font-size: 2em;
            font-weight: bold;
            margin: 10px 0;
        }

        .pendente { border-left: 4px solid #e74c3c; }
        .andamento { border-left: 4px solid #f39c12; }
        .respondido { border-left: 4px solid #3498db; }
        .fechado { border-left: 4px solid #27ae60; }

        .contatos-table {
            width: 100%;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
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

        tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-pendente { background: #ffeaa7; color: #e17055; }
        .status-em_andamento { background: #fab1a0; color: #d63031; }
        .status-respondido { background: #74b9ff; color: #0984e3; }
        .status-fechado { background: #55efc4; color: #00b894; }

        .prioridade-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .prioridade-baixa { background: #55efc4; color: #00b894; }
        .prioridade-media { background: #74b9ff; color: #0984e3; }
        .prioridade-alta { background: #ffeaa7; color: #e17055; }
        .prioridade-urgente { background: #fab1a0; color: #d63031; }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
      

        .btn-responder {
            background: #3498db;
            color: white;
        }

        .btn-responder:hover {
            background: #2980b9;
        }

        .btn-fechar {
            background: #27ae60;
            color: white;
        }

        .btn-fechar:hover {
            background: #219a52;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }

        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #7f8c8d;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .mensagem-original {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #3498db;
            margin-bottom: 15px;
        }

        .historico-respostas {
            margin-top: 20px;
        }

        .resposta-item {
            background: #ecf0f1;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 10px;
            border-left: 4px solid #95a5a6;
        }

        .resposta-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 12px;
            color: #7f8c8d;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>📨 Sistema de Contatos</h1>
            <div class="user-info">
                <span>  </span>
            </div>
        </div>
          
      <!--botao voltar ao painel -->
      <div style="display: flex; justify-content: flex-end; margin-top: 10px;">
    <button class="btn btn-filter" style="background: #95a5a6;">
        <a href="admin.php" style="color: white; text-decoration: none;">⬅️ Voltar painel</a>
    </button>
</div>


        <!-- Mensagens de sucesso/erro -->
        <?php if (isset($sucesso)): ?>
            <div class="alert alert-success">
                ✅ <?php echo htmlspecialchars($sucesso); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($erro)): ?>
            <div class="alert alert-error">
      ❌ <?php echo htmlspecialchars($erro); ?>
  </div>
<?php endif; ?>

<!-- Filtros -->
<form method="GET" class="filters">
  <div class="filter-group">
      <label>Status</label>
      <select name="status">
          <option value="">Todos os status</option>
          <option value="pendente" <?php echo $filtro_status === 'pendente' ? 'selected' : ''; ?>>Pendente</option>
          <option value="em_andamento" <?php echo $filtro_status === 'em_andamento' ? 'selected' : ''; ?>>Em Andamento</option>
          <option value="respondido" <?php echo $filtro_status === 'respondido' ? 'selected' : ''; ?>>Respondido</option>
          <option value="fechado" <?php echo $filtro_status === 'fechado' ? 'selected' : ''; ?>>Fechado</option>
      </select>
  </div>
  <div class="filter-group">
      <label>Prioridade</label>
      <select name="prioridade">
          <option value="">Todas as prioridades</option>
          <option value="baixa" <?php echo $filtro_prioridade === 'baixa' ? 'selected' : ''; ?>>Baixa</option>
          <option value="media" <?php echo $filtro_prioridade === 'media' ? 'selected' : ''; ?>>Média</option>
          <option value="alta" <?php echo $filtro_prioridade === 'alta' ? 'selected' : ''; ?>>Alta</option>
          <option value="urgente" <?php echo $filtro_prioridade === 'urgente' ? 'selected' : ''; ?>>Urgente</option>
      </select>
  </div>
  <div class="filter-group">
      <label>Assunto</label>
      <select name="assunto">
          <option value="">Todos os assuntos</option>
          <option value="duvida" <?php echo $filtro_assunto === 'duvida' ? 'selected' : ''; ?>>Dúvida</option>
          <option value="orcamento" <?php echo $filtro_assunto === 'orcamento' ? 'selected' : ''; ?>>Orçamento</option>
          <option value="suporte" <?php echo $filtro_assunto === 'suporte' ? 'selected' : ''; ?>>Suporte</option>
          <option value="reclamacao" <?php echo $filtro_assunto === 'reclamacao' ? 'selected' : ''; ?>>Reclamação</option>
          <option value="outro" <?php echo $filtro_assunto === 'outro' ? 'selected' : ''; ?>>Outro</option>
      </select>
  </div>
  <div class="filter-group">
      <label>Data</label>
      <input type="date" name="data" value="<?php echo htmlspecialchars($filtro_data); ?>">
  </div>
  <button type="submit" class="btn btn-filter">🔍 Filtrar</button >

  <a href="funcionario.php" class="btn" style="background: #95a5a6;">🔄 Limpar</a>

</form>

<!-- Estatísticas -->
<div class="stats">
  <div class="stat-card pendente">
      <div>📥 Pendentes</div>
      <div class="stat-number"><?php echo $estatisticas['pendente']; ?></div>
  </div>
  <div class="stat-card andamento">
      <div>🔄 Em Andamento</div>
      <div class="stat-number"><?php echo $estatisticas['em_andamento']; ?></div>
  </div>
  <div class="stat-card respondido">
      <div>✅ Respondidos</div>
      <div class="stat-number"><?php echo $estatisticas['respondido']; ?></div>
  </div>
  <div class="stat-card fechado">
      <div>📁 Fechados</div>
      <div class="stat-number"><?php echo $estatisticas['fechado']; ?></div>
  </div>
</div>

<!-- Tabela de Contatos -->
<div class="contatos-table">
<table>
<thead>
  <tr>
      <th>ID</th>
      <th>Nome</th>
      <th>Contato</th>
      <th>Assunto</th>
      <th>Prioridade</th>
      <th>Status</th>
      <th>Data</th>
      <th>Ações</th>
  </tr>
</thead>
<tbody>
  <?php if (empty($contatos)): ?>
      <tr>
          <td colspan="8" style="text-align: center; padding: 20px; color: #666;">
              📭 Nenhum contato encontrado.
          </td>
      </tr>
  <?php else: ?>
      <?php foreach ($contatos as $contato): ?>
          <tr>
              <td>#<?php echo $contato['id']; ?></td>
              <td><strong><?php echo htmlspecialchars($contato['nome']); ?></strong></td>
              <td>
                  <div><?php echo htmlspecialchars($contato['email']); ?></div>
                  <small><?php echo $contato['telefone'] ? htmlspecialchars($contato['telefone']) : 'Não informado'; ?></small>
              </td>
              <td><?php echo formatarAssunto($contato['assunto']); ?></td>
              <td><span class="prioridade-badge prioridade-<?php echo $contato['prioridade']; ?>"><?php echo formatarPrioridade($contato['prioridade']); ?></span></td>
              <td><span class="status-badge status-<?php echo $contato['status']; ?>"><?php echo formatarStatus($contato['status']); ?></span></td>
              <td><?php echo formatarData($contato['data_criacao']); ?></td>
              <td>
                  <a href="?ver=<?php echo $contato['id']; ?>&<?php echo http_build_query($_GET); ?>" class="btn btn-responder">
                      👁️ Ver/Responder
                  </a>
<?php if ($contato['status'] !== 'fechado'): ?>
    <form method="POST" style="display: inline;">
        <input type="hidden" name="contato_id" value="<?php echo $contato['id']; ?>">
        <input type="hidden" name="acao" value="fechar">
        <button type="submit" class="btn btn-fechar" onclick="return confirm('Tem certeza que deseja fechar este contato?')">
            ✅ Fechar
                          </button>
                      </form>
                          <?php endif; ?>
                      </td>
                  </tr>
              <?php endforeach; ?>
          <?php endif; ?>
      </tbody>
  </table>
        </div>
    </div>

    <!-- Modal para Ver/Responder -->
    <?php if (isset($_GET['ver'])): ?>
        <?php
        $contato_id = $_GET['ver'];
        $contato_detalhes = null;
        $respostas = [];
        
        try {
            // Buscar detalhes do contato
            $stmt = $db->prepare("SELECT * FROM contatos WHERE id = :id");
            $stmt->execute([':id' => $contato_id]);
            $contato_detalhes = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Buscar respostas
              $stmt = $db->prepare("
      SELECT r.*, a.nome as administrador_nome 
      FROM respostas r 
      LEFT JOIN administradores a ON r.administrador_id = a.id 
      WHERE r.contato_id = :contato_id 
      ORDER BY r.data_resposta ASC
    ");
            $stmt->execute([':contato_id' => $contato_id]);
            $respostas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $erro = "Erro ao carregar detalhes: " . $e->getMessage();
        }
        ?>
        
    <?php if ($contato_detalhes): ?>
<div class="modal" style="display: block;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>📝 Responder Contato</h3>
            <a href="?" class="close">&times;</a>
        </div>
        <div>
            <div class="mensagem-original">
                <h4>Mensagem Original:</h4>
                <p><strong>De:</strong> <?php echo htmlspecialchars($contato_detalhes['nome']); ?> (<?php echo htmlspecialchars($contato_detalhes['email']); ?>)</p>
                <p><strong>Telefone:</strong> <?php echo $contato_detalhes['telefone'] ? htmlspecialchars($contato_detalhes['telefone']) : 'Não informado'; ?></p>
                <p><strong>Assunto:</strong> <?php echo formatarAssunto($contato_detalhes['assunto']); ?></p>
                <p><strong>Prioridade:</strong> <?php echo formatarPrioridade($contato_detalhes['prioridade']); ?></p>
                <p><strong>Mensagem:</strong></p>
                <p style="white-space: pre-wrap;"><?php echo htmlspecialchars($contato_detalhes['mensagem']); ?></p>
                <p><small><strong>Recebido em:</strong> <?php echo formatarData($contato_detalhes['data_criacao']); ?></small></p>
            </div>

            <?php if (!empty($respostas)): ?>
            <div class="historico-respostas">
                <h4>📋 Histórico de Respostas:</h4>
                <?php foreach ($respostas as $resposta): ?>
                    <div class="resposta-item">
                        <div class="resposta-header">
                            <span><?php echo $resposta['administrador_nome'] ? htmlspecialchars($resposta['administrador_nome']) : 'Funcionário ID: ' . $resposta['administrador_id']; ?></span>
                            <span><?php echo formatarData($resposta['data_resposta']) . ' ' . date('H:i', strtotime($resposta['data_resposta'])); ?></span>
                        </div>
                                <p style="white-space: pre-wrap;"><?php echo htmlspecialchars($resposta['resposta']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                        <p style="color: #666; text-align: center;">📭 Nenhuma resposta enviada ainda.</p>
                    <?php endif; ?>

                    <form method="POST" >
                        <input type="hidden" name="contato_id" value="<?php echo $contato_detalhes['id']; ?>">
                        <input type="hidden" name="acao" value="responder">
                        
                        <div class="form-group">
                            <label for="respostaTexto">✏️ Sua Resposta:</label>
                            <textarea id="respostaTexto" name="resposta" placeholder="Digite sua resposta aqui..." rows="6" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-responder" style="width: 100%;">
                            📤 Enviar Resposta
                        </button>
                    </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</body>
</html>