<?php 

        include 'header.html';
        include('conexao.php');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nome = $_POST['nome'];
            $email = $_POST['email'];
            $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $usuarioExistente = $stmt->fetch();

            if ($usuarioExistente) {
                echo "Este email já está cadastrado. Por favor, use outro.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha) VALUES (:nome, :email, :senha)");
                $stmt->bindParam(':nome', $nome);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':senha', $senha);
                if ($stmt->execute()) {
                    echo "Usuário cadastrado com sucesso!";
                    header("Location: index.php");
                } else {
                    echo "Erro ao cadastrar o usuário. Tente novamente.";
                }
            }
        }
?>
    <div class="container">
        <h2>Cadastro de Usuário</h2>
        <form method="POST" action="cadastrar.php">
            <div class="form-group">
                <label for="nome">Nome:</label>
                <input type="text" class="form-control" id="nome" name="nome" required>
            </div>
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="senha">Senha:</label>
                <input type="password" class="form-control" id="senha" name="senha" required>
            </div>
            <button type="submit" class="btn btn-primary">Cadastrar</button>
        </form>
    </div>
