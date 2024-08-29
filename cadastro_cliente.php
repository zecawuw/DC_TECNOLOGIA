<?php include 'header.html'; 
    include('conexao.php');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nome = $_POST['nome'];
        $email = $_POST['email'];

        $stmt = $pdo->prepare("SELECT * FROM clientes WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $clienteExistente = $stmt->fetch();

        if ($clienteExistente) {
            echo "Este email já está cadastrado. Por favor, use outro.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO clientes (nome, email) VALUES (:nome, :email)");
            $stmt->bindParam(':nome', $nome);
            $stmt->bindParam(':email', $email);
            if ($stmt->execute()) {
                echo "Cliente cadastrado com sucesso!";
                header("Location: cadastro_vendas.php");
            } else {
                echo "Erro ao cadastrar o cliente. Tente novamente.";
            }
        }
    }
?>

    <div class="container">
        <h2>Cadastro de Cliente</h2>
        <form method="POST" action="cadastro_cliente.php">
            <div class="form-group">
                <label for="nome">Nome:</label>
                <input type="text" class="form-control" id="nome" name="nome" required>
            </div>
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" class="form-control" id="email" name="email">
            </div>
            <button type="submit" class="btn btn-primary">Cadastrar</button>
        </form>
    </div>

        

