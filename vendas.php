<?php 
    include 'header.html';

    session_start();
    include('conexao.php');



    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['venda_id']) && isset($_POST['quantidade_parcelas'])) {
        $venda_id = $_POST['venda_id'];
        $quantidade_parcelas = $_POST['quantidade_parcelas'];

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE planilha_produtos SET quantidade_parcelas = :quantidade_parcelas WHERE id = :id");
            $stmt->bindParam(':quantidade_parcelas', $quantidade_parcelas, PDO::PARAM_INT);
            $stmt->bindParam(':id', $venda_id, PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $pdo->prepare("SELECT preco_total FROM planilha_produtos WHERE id = :id");
            $stmt->bindParam(':id', $venda_id, PDO::PARAM_INT);
            $stmt->execute();
            $venda = $stmt->fetch(PDO::FETCH_ASSOC);

            $preco_total = $venda['preco_total'];
            $valor_parcela = $quantidade_parcelas > 0 ? $preco_total / $quantidade_parcelas : $preco_total;

            $stmt = $pdo->prepare("DELETE FROM parcelas WHERE venda_id = :venda_id");
            $stmt->bindParam(':venda_id', $venda_id, PDO::PARAM_INT);
            $stmt->execute();

            $data_venda = date('Y-m-d');
            for ($i = 1; $i <= $quantidade_parcelas; $i++) {
                $data_parcela = date('Y-m-d', strtotime("$data_venda +".($i * 30)." days"));
                $stmt = $pdo->prepare("INSERT INTO parcelas (venda_id, data_parcela, parcela_numero, valor_parcela) VALUES (:venda_id, :data_parcela, :parcela_numero, :valor_parcela)");
                $stmt->bindParam(':venda_id', $venda_id);
                $stmt->bindParam(':data_parcela', $data_parcela);
                $stmt->bindParam(':parcela_numero', $i);
                $stmt->bindParam(':valor_parcela', $valor_parcela);
                $stmt->execute();
            }

            $pdo->commit();

            echo "<script>alert('Quantidade de parcelas atualizada com sucesso!'); window.location.href = window.location.href;</script>";
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<script>alert('Erro ao atualizar: " . $e->getMessage() . "');</script>";
        }
    }


    $cliente_nome = '';
    $produto = '';
    $data_inicial = '';
    $data_final = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['venda_id'])) {
        $cliente_nome = isset($_POST['cliente_nome']) ? $_POST['cliente_nome'] : '';
        $produto = isset($_POST['produto']) ? $_POST['produto'] : '';
        $data_inicial = isset($_POST['data_inicial']) ? $_POST['data_inicial'] : '';
        $data_final = isset($_POST['data_final']) ? $_POST['data_final'] : '';
        $data_proxima_parcela = isset($_POST['data_proxima_parcela']) ? $_POST['data_proxima_parcela'] : '';

    }


    $cliente_nome = '';
    $produto = '';
    $data_proxima_parcela = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $cliente_nome = isset($_POST['cliente_nome']) ? $_POST['cliente_nome'] : '';
        $produto = isset($_POST['produto']) ? $_POST['produto'] : '';
        $data_proxima_parcela = isset($_POST['data_proxima_parcela']) ? $_POST['data_proxima_parcela'] : '';
    }

    try {
        $sql = "
            SELECT pp.id, pp.cliente_nome, pp.produto, pp.quantidade, pp.preco_unitario, pp.preco_total, 
                pp.quantidade_parcelas, 
                (CASE WHEN pp.quantidade_parcelas > 0 THEN pp.preco_total / pp.quantidade_parcelas ELSE pp.preco_total END) AS valor_parcela, 
                pp.data_venda, 
                IFNULL(
                    (SELECT MIN(data_parcela) 
                        FROM parcelas 
                        WHERE venda_id = pp.id AND data_parcela > CURDATE()), 
                    'Sem Parcelas Futuras'
                ) AS proxima_parcela
            FROM planilha_produtos pp
            LEFT JOIN parcelas p ON pp.id = p.venda_id
            WHERE 1=1
        ";

        if ($cliente_nome) {
            $sql .= " AND pp.cliente_nome LIKE :cliente_nome";
        }
        if ($produto) {
            $sql .= " AND pp.produto LIKE :produto";
        }
        if ($data_proxima_parcela) {
            $sql .= " AND IFNULL(
                        (SELECT MIN(data_parcela) 
                        FROM parcelas 
                        WHERE venda_id = pp.id AND data_parcela > CURDATE()), 
                        'Sem Parcelas Futuras'
                    ) = :data_proxima_parcela";
        }

        $sql .= " GROUP BY pp.id
                ORDER BY pp.data_venda DESC";
        $stmt = $pdo->prepare($sql);

        if ($cliente_nome) {
            $stmt->bindValue(':cliente_nome', "%$cliente_nome%");
        }
        if ($produto) {
            $stmt->bindValue(':produto', "%$produto%");
        }
        if ($data_proxima_parcela) {
            $stmt->bindValue(':data_proxima_parcela', $data_proxima_parcela);
        }

        $stmt->execute();
        $vendas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo "Erro de banco de dados: " . $e->getMessage();
    }





    $parcelas = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ver_mais_id'])) {
        $venda_id = $_POST['ver_mais_id'];

        $stmt = $pdo->prepare("SELECT * FROM parcelas WHERE venda_id = :venda_id");
        $stmt->bindParam(':venda_id', $venda_id, PDO::PARAM_INT);
        $stmt->execute();
        $parcelas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }



    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_parcelas'])) {
        $venda_id = $_POST['venda_id'];
        $parcelas = $_POST['parcelas'];

        try {
            $pdo->beginTransaction();

            $soma_parcelas = array_sum(array_column($parcelas, 'valor'));

            $stmt = $pdo->prepare("SELECT preco_total FROM planilha_produtos WHERE id = :id");
            $stmt->bindParam(':id', $venda_id, PDO::PARAM_INT);
            $stmt->execute();
            $venda = $stmt->fetch(PDO::FETCH_ASSOC);
            $preco_total = $venda['preco_total'];

            $ajuste = $preco_total - $soma_parcelas;
            $parcela_count = count($parcelas);

            foreach ($parcelas as $index => $parcela) {
                $valor_parcela = $parcela['valor'] + ($ajuste / $parcela_count);
                $stmt = $pdo->prepare("UPDATE parcelas SET valor_parcela = :valor WHERE venda_id = :venda_id AND data_parcela = :data");
                $stmt->bindParam(':valor', $valor_parcela, PDO::PARAM_STR);
                $stmt->bindParam(':venda_id', $venda_id, PDO::PARAM_INT);
                $stmt->bindParam(':data', $parcela['data'], PDO::PARAM_STR);
                $stmt->execute();
            }

            $pdo->commit();
            echo "<script>alert('Datas e valores das parcelas atualizados com sucesso!'); window.location.href = window.location.href;</script>";
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<script>alert('Erro ao atualizar as datas e valores das parcelas: " . $e->getMessage() . "');</script>";
        }
    }
?>



    <style>
       #parcelas{
        position: relative;
        width: 300px;
        text-align: center;
        top: -1200px;
        justify-content: center;
        margin: 0 auto;
       }
    </style>
    <div class="container">
        <h2>Vendas Cadastradas</h2>
        <form method="POST" action="" class="form-inline mb-4">
            <div class="form-group mr-2">
                <label for="cliente_nome" class="mr-2">Cliente:</label>
                <input type="text" id="cliente_nome" name="cliente_nome" class="form-control" value="<?php echo htmlspecialchars($cliente_nome); ?>">
            </div>
            <div class="form-group mr-2">
                <label for="produto" class="mr-2">Produto:</label>
                <input type="text" id="produto" name="produto" class="form-control" value="<?php echo htmlspecialchars($produto); ?>">
            </div>
            <div class="form-group mr-2">
                <label for="data_proxima_parcela" class="mr-2">Data da Próxima Parcela:</label>
                <input type="date" id="data_proxima_parcela" name="data_proxima_parcela" class="form-control" value="<?php echo htmlspecialchars($data_proxima_parcela); ?>">
            </div>
            <button type="submit" class="btn btn-primary">Filtrar</button>
        </form>


        <table class="table table-bordered">
    <thead>
        <tr>
            <th>Cliente</th>
            <th>Produto</th>
            <th>Quantidade</th>
            <th>Preço Unitário</th>
            <th>Preço Total</th>
            <th>Quantidade de Parcelas</th>
            <th>Valor da Parcela</th>
            <th>Data da Venda</th>
            <th>Próxima Parcela</th>
            <th>Ações</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($vendas)): ?>
            <?php foreach ($vendas as $venda): ?>
                <tr>
                    <td><?php echo htmlspecialchars($venda['cliente_nome']); ?></td>
                    <td><?php echo htmlspecialchars($venda['produto']); ?></td>
                    <td><?php echo $venda['quantidade']; ?></td>
                    <td>R$ <?php echo number_format($venda['preco_unitario'], 2, ',', '.'); ?></td>
                    <td>R$ <?php echo number_format($venda['preco_total'], 2, ',', '.'); ?></td>
                    <td>
                        <form method="POST" action="">
                            <input type="hidden" name="venda_id" value="<?php echo $venda['id']; ?>">
                            <input type="number" name="quantidade_parcelas" value="<?php echo $venda['quantidade_parcelas']; ?>" required class="form-control">
                            <button type="submit" class="btn btn-warning">Atualizar</button>
                        </form>
                    </td>
                    <td>R$ <?php echo number_format($venda['valor_parcela'], 2, ',', '.'); ?></td>
                    <td><?php echo date('d/m/Y H:i:s', strtotime($venda['data_venda'])); ?></td>
                    <td><?php echo $venda['proxima_parcela'] !== 'Sem Parcelas Futuras' ? date('d/m/Y', strtotime($venda['proxima_parcela'])) : $venda['proxima_parcela']; ?></td>
                    <td>
                        <form method="POST" action="">
                            <input type="hidden" name="ver_mais_id" value="<?php echo $venda['id']; ?>">
                            <a href="#parcelas"><button type="submit" class="btn btn-info">Ver Mais</button></a>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="11">Nenhuma venda encontrada para os critérios selecionados.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<?php if (!empty($parcelas)): ?>
    <div id="parcelas" class="card mt-4">
        <div class="card-header d-flex justify-content-between">
            <p>Datas das Parcelas</p>
            <p id="fecharParcelas" style="cursor: pointer; margin: 0;">X</p>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="venda_id" value="<?php echo htmlspecialchars($venda_id); ?>">
                <ul class="list-group">
                    <?php foreach ($parcelas as $index => $parcela): ?>
                        <li class="list-group-item">
                            <input type="date" name="parcelas[<?php echo $index; ?>][data]" value="<?php echo date('Y-m-d', strtotime($parcela['data_parcela'])); ?>" class="form-control parcela-data">
                            <input type="number" name="parcelas[<?php echo $index; ?>][valor]" value="<?php echo number_format($parcela['valor_parcela'], 2, '.', ''); ?>" class="form-control parcela-valor">
                        </li>
                    <?php endforeach; ?>
                </ul>
                <button type="submit" name="atualizar_parcelas" class="btn btn-success mt-2">Salvar Alterações</button>
            </form>
        </div>
    </div>
<?php endif; ?>






        

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
    $(document).ready(function() {
        $('#form-parcelas').on('submit', function(e) {
            e.preventDefault();

            $.ajax({
                type: 'POST',
                url: 'vendas.php',
                data: $(this).serialize(), 
                success: function(response) {
                    $('#form-parcelas').hide();
                },
                error: function() {
                    alert('Erro ao salvar');
                }
            });
        });
    });
</script>
    <script>
        document.getElementById('fecharParcelas').addEventListener('click', function() {
            document.getElementById('parcelas').style.display = 'none';
        });
    </script>

<script>
 document.addEventListener('DOMContentLoaded', function() {
    const parcelaValores = document.querySelectorAll('.parcela-valor');
    const precoTotal = <?php echo json_encode($preco_total); ?>;

    function atualizarValoresParcela() {
        parcelaValores.forEach((input) => {
            input.addEventListener('input', function() {
                let valores = Array.from(parcelaValores).map(el => parseFloat(el.value) || 0);
                let soma = valores.reduce((a, b) => a + b, 0);

                if (soma !== precoTotal) {
                    if (soma > precoTotal) {
                        const excesso = soma - precoTotal;
                        let novoValor = parseFloat(this.value) - excesso;
                        this.value = Math.max(0, novoValor);

                        soma = Array.from(parcelaValores).map(el => parseFloat(el.value) || 0).reduce((a, b) => a + b, 0);
                    }

                    if (soma < precoTotal) {
                        const restante = precoTotal - soma;
                        const parcelasRestantes = Array.from(parcelaValores).filter(el => el !== this);

                        parcelasRestantes.forEach(el => {
                            let valorAtual = parseFloat(el.value) || 0;
                            el.value = Math.max(0, valorAtual + restante / parcelasRestantes.length);
                        });
                    }
                }
            });
        });
    }

    atualizarValoresParcela();
});



</script>
<script>
    var preco_total = <?php echo json_encode($preco_total); ?>;
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('form-parcelas');
        if (form) {
            form.addEventListener('submit', function() {
                setTimeout(function() {
                    form.style.display = 'none';
                }, 500);
            });
        }
    });
</script>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        const btnSalvar = document.getElementById('btn-salvar');
        if (btnSalvar) {
            btnSalvar.addEventListener('click', function() {
                const form = btnSalvar.closest('form');
                if (form) {
                    form.addEventListener('submit', function() {
                        document.getElementById('parcelas').style.display = 'none';
                    });
                }
            });
        }
    });
</script>