<?php
session_start();
require_once 'config/database.php';

// Não usar header JSON para redirecionamento
// header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Usar FILTER_SANITIZE_FULL_SPECIAL_CHARS
        $nome = filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $telefone = filter_input(INPUT_POST, 'telefone', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $assunto = filter_input(INPUT_POST, 'assunto', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $mensagem = filter_input(INPUT_POST, 'mensagem', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        // Validações
        if (empty($nome) || empty($email) || empty($assunto) || empty($mensagem)) {
            $_SESSION['form_error'] = 'Todos os campos obrigatórios devem ser preenchidos.';
            header('Location: ' . $_SERVER['HTTP_REFERER']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['form_error'] = 'E-mail inválido.';
            header('Location: ' . $_SERVER['HTTP_REFERER']);
            exit;
        }

        // Determinar prioridade
        $prioridade = 'media';
        if ($assunto === 'reclamacao') $prioridade = 'alta';
        if ($assunto === 'suporte') $prioridade = 'alta';

        // Inserir no banco
        $stmt = $db->prepare("INSERT INTO contatos (nome, email, telefone, assunto, mensagem, prioridade) 
                               VALUES (:nome, :email, :telefone, :assunto, :mensagem, :prioridade)");
        
        $result = $stmt->execute([
            ':nome' => $nome,
            ':email' => $email,
            ':telefone' => $telefone,
            ':assunto' => $assunto,
            ':mensagem' => $mensagem,
            ':prioridade' => $prioridade
        ]);

        if ($result) {
            $_SESSION['form_success'] = 'Mensagem enviada com sucesso! Entraremos em contato em breve.';
            header('Location: ' . $_SERVER['HTTP_REFERER']);
            exit;
        } else {
            $_SESSION['form_error'] = 'Erro ao salvar no banco de dados.';
            header('Location: ' . $_SERVER['HTTP_REFERER']);
            exit;
        }

    } else {
        $_SESSION['form_error'] = 'Método não permitido.';
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    }

} catch (Exception $e) {
    $_SESSION['form_error'] = 'Erro: ' . $e->getMessage();
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}
?>