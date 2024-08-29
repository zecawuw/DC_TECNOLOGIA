<?php include 'header.html'; ?>
<?php
    include('conexao.php');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $produto = $_POST['produto'];
        $quantidade = $_POST['quantidade'];
        $preco = $_POST['preco'];
        
        $venda_id = $_POST['venda_id'] ?? null;

        if (empty($venda_id) || !is_numeric($venda_id) || !vendaExists($venda_id, $pdo)) {
            $stmt = $pdo->prepare("INSERT INTO vendas (cliente_id, usuario_id, forma_pagamento) VALUES (NULL, NULL, 'produto-cadastro')");
            $stmt->execute();
            $venda_id = $pdo->lastInsertId();
        }

        $stmt = $pdo->prepare("INSERT INTO itens_venda (venda_id, produto, quantidade, preco_unitario) VALUES (:venda_id, :produto, :quantidade, :preco)");
        $stmt->bindParam(':venda_id', $venda_id);
        $stmt->bindParam(':produto', $produto);
        $stmt->bindParam(':quantidade', $quantidade);
        $stmt->bindParam(':preco', $preco);

        if ($stmt->execute()) {
            echo "Produto cadastrado com sucesso!";
            header("Location: cadastrar_produtos.php");
            exit;
        } else {
            echo "Erro ao cadastrar o produto. Tente novamente.";
        }
    }

    function vendaExists($venda_id, $pdo) {
        $stmt = $pdo->prepare("SELECT id FROM vendas WHERE id = :venda_id");
        $stmt->bindParam(':venda_id', $venda_id);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }
?>

<?php
    include('conexao.php');

    $stmt = $pdo->prepare("SELECT * FROM itens_venda");
    $stmt->execute();
    $produtos = $stmt->fetchAll();
?>

<div class="container">
    <h2>Cadastro de Produto</h2>
    <form method="POST" action="cadastrar_produtos.php">
        <div class="form-group">
            <label for="venda_id">ID da Venda:</label>
            <input type="number" class="form-control" id="venda_id" name="venda_id">
        </div>
        <div class="form-group">
            <label for="produto">Nome do Produto:</label>
            <input type="text" class="form-control" id="produto" name="produto" required>
        </div>
        <div class="form-group">
            <label for="quantidade">Quantidade:</label>
            <input type="number" class="form-control" id="quantidade" name="quantidade" required>
        </div>
        <div class="form-group">
            <label for="preco">Preço Unitário:</label>
            <input type="number" step="0.01" class="form-control" id="preco" name="preco" required>
        </div>
        <button type="submit" class="btn btn-primary">Cadastrar Produto</button>
    </form>
</div>

<div class="container">
    <h2>Listagem de Produtos</h2>
    <table class="table table-striped">
        <thead>
            <tr>
                <th>ID da Venda</th>
                <th>Nome</th>
                <th>Quantidade</th>
                <th>Preço Unitário</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($produtos as $produto): ?>
                <tr>
                    <td><?= htmlspecialchars($produto['venda_id']) ?></td>
                    <td><?= htmlspecialchars($produto['produto']) ?></td>
                    <td><?= htmlspecialchars($produto['quantidade']) ?></td>
                    <td><?= htmlspecialchars($produto['preco_unitario']) ?></td>
                    <td>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
