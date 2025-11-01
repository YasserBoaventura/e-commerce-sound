<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$erro_login = '';

// Processar login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['usuario'])) {
    $usuario = trim($_POST['usuario']);
    $senha = trim($_POST['senha']);
    
    try {
        $query = "SELECT * FROM administradores WHERE usuario = :usuario";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':usuario', $usuario);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (md5($senha) === $admin['senha']) {
                $_SESSION['admin_logado'] = true;
                $_SESSION['admin_usuario'] = $usuario;
                header("Location: admin.php");
                exit;
            } else {            
                $erro_login = "Senha incorreta!";
            }
        } else {
            $erro_login = "Usuário não encontrado!";
        }
    } catch (PDOException $e) {
        $erro_login = "Erro no banco: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Som Automotivo</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: rgba(255,255,255,0.1);
            padding: 3rem 2rem;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 450px;
            border: 1px solid rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
        }

        .login-container h2 {
            color: white;
            text-align: center;
            margin-bottom: 2rem;
            font-size: 1.8rem;
            font-weight: 600;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .admin-form {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 0.5rem;
            color: white;
            font-weight: 600;
            font-size: 1rem;
        }

        .form-group input {
            padding: 1rem;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 10px;
            font-size: 1rem;
            background: rgba(255,255,255,0.1);
            color: white;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #f39c12;
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(243, 156, 18, 0.3);
        }

        .btn-login {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(243, 156, 18, 0.4);
            margin-top: 1rem;
        }

        .btn-login:hover {
            background: linear-gradient(135deg, #e67e22, #f39c12);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(243, 156, 18, 0.6);
        }

        .credenciais {
            color: white;
            margin-top: 2rem;
            text-align: center;
            font-size: 0.9rem;
            background: rgba(255,255,255,0.1);
            padding: 1rem;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.2);
        }

        .credenciais code {
            background: rgba(255,255,255,0.2);
            padding: 0.3rem 0.6rem;
            border-radius: 6px;
            font-weight: bold;
            color: #f39c12;
        }

        .message {
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 10px;
            font-weight: 500;
            text-align: center;
        }

        .error {
            background: rgba(231, 76, 60, 0.9);
            color: white;
            border: 1px solid rgba(192, 57, 43, 0.5);
        }

        .back-link {
            color: white;
            text-align: center;
            margin-top: 1rem;
            display: block;
            text-decoration: none;
        }

        .back-link:hover {
            color: #f39c12;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>🔐 Login Administrativo</h2>
        
        <?php if(isset($erro_login) && $erro_login): ?>
        <div class="message error">❌ <?php echo $erro_login; ?></div>
        <?php endif; ?>
        
        <form method="POST" class="admin-form">
            <div class="form-group">
                <label>👤 Usuário:</label>
                <input type="text" name="usuario" value="" required 
                       placeholder="Digite seu usuário">
            </div>
            
            <div class="form-group">
                <label>🔒 Senha:</label>
                <input type="password" name="senha" value="" required 
                       placeholder="Digite sua senha">
            </div>
            
            <button type="submit" class="btn-login">🚀 Entrar no Sistema</button>
        </form>
         <a href="index.php" class="back-link">← Voltar para a Loja</a>
    </div>
</body>
</html>