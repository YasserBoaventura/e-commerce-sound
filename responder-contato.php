<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, PUT, GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

try {
    $database = new Database();
    $db = $database->getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Adicionar resposta a um contato
        $input = json_decode(file_get_contents('php://input'), true);
        
        $contato_id = filter_var($input['contato_id'] ?? '', FILTER_VALIDATE_INT);
        $administrador_id = filter_var($input['administrador_id'] ?? 1, FILTER_VALIDATE_INT);
        $resposta = filter_var($input['resposta'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        if (!$contato_id || empty($resposta)) {
            throw new Exception('Contato ID e resposta são obrigatórios.');
        }

        // Inserir resposta usando administrador_id
        $stmt = $db->prepare("INSERT INTO respostas (contato_id, administrador_id, resposta) 
                              VALUES (:contato_id, :administrador_id, :resposta)");
        
        $result = $stmt->execute([
            ':contato_id' => $contato_id,
            ':administrador_id' => $administrador_id,
            ':resposta' => $resposta
        ]);

        if ($result) {
            // Atualizar status do contato para "respondido"
            $stmt = $db->prepare("UPDATE contatos SET status = 'respondido' WHERE id = :contato_id");
            $stmt->execute([':contato_id' => $contato_id]);

            echo json_encode([
                'success' => true,
                'message' => 'Resposta enviada com sucesso!',
                'resposta_id' => $db->lastInsertId()
            ]);
        } else {
            throw new Exception('Erro ao salvar resposta.');
        }

    } else if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // Atualizar status do contato (fechar)
        $input = json_decode(file_get_contents('php://input'), true);
        
        $contato_id = filter_var($input['contato_id'] ?? '', FILTER_VALIDATE_INT);
        $status = filter_var($input['status'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        if (!$contato_id || empty($status)) {
            throw new Exception('Contato ID e status são obrigatórios.');
        }

        $stmt = $db->prepare("UPDATE contatos SET status = :status WHERE id = :contato_id");
        $result = $stmt->execute([
            ':contato_id' => $contato_id,
            ':status' => $status
        ]);

        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Status atualizado com sucesso!'
            ]);
        } else {
            throw new Exception('Erro ao atualizar status.');
        }

    } else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Buscar respostas de um contato - AGORA usando administradores
        $contato_id = filter_var($_GET['contato_id'] ?? '', FILTER_VALIDATE_INT);

        if (!$contato_id) {
            throw new Exception('Contato ID é obrigatório.');
        }

        $stmt = $db->prepare("
            SELECT r.*, a.nome as administrador_nome, a.email as administrador_email
            FROM respostas r 
            LEFT JOIN administradores a ON r.administrador_id = a.id 
            WHERE r.contato_id = :contato_id 
            ORDER BY r.data_resposta ASC
        ");
        $stmt->execute([':contato_id' => $contato_id]);
        $respostas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $respostas
        ]);

    } else {
        throw new Exception('Método não permitido.');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Erro: ' . $e->getMessage()
    ]);
}
?>