<?php include 'header.html'; ?>
<?php
    include('conexao.php');

    session_start();
   
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ERROR | E_PARSE);

    try {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $cliente_nome = $_POST['cliente_nome'];
            $quantidade_parcelas = isset($_POST['quantidade_parcelas']) ? $_POST['quantidade_parcelas'] : null;

            $pdo->beginTransaction();

            foreach ($_POST['produtos'] as $index => $produto) {
                $quantidade = $_POST['quantidades'][$index];
                $preco_unitario = $_POST['preco_unitarios'][$index];
                $preco_total = $quantidade * $preco_unitario;

                $stmt = $pdo->prepare("INSERT INTO planilha_produtos (cliente_nome, produto, quantidade, preco_unitario, preco_total, quantidade_parcelas) VALUES (:cliente_nome, :produto, :quantidade, :preco_unitario, :preco_total, :quantidade_parcelas)");
                $stmt->bindParam(':cliente_nome', $cliente_nome);
                $stmt->bindParam(':produto', $produto);
                $stmt->bindParam(':quantidade', $quantidade);
                $stmt->bindParam(':preco_unitario', $preco_unitario);
                $stmt->bindParam(':preco_total', $preco_total);
                $stmt->bindParam(':quantidade_parcelas', $quantidade_parcelas);
                $stmt->execute();

                $venda_id = $pdo->lastInsertId();

                $stmt = $pdo->prepare("DELETE FROM parcelas WHERE venda_id = :venda_id");
                $stmt->bindParam(':venda_id', $venda_id);
                $stmt->execute();

                if ($quantidade_parcelas > 0) {
                    $data_venda = date('Y-m-d');
                    $valor_parcela = $preco_total / $quantidade_parcelas;

                    for ($i = 1; $i <= $quantidade_parcelas; $i++) {
                        $data_parcela = date('Y-m-d', strtotime("$data_venda +".($i * 30)." days"));
                        $stmt = $pdo->prepare("INSERT INTO parcelas (venda_id, data_parcela, parcela_numero, valor_parcela) VALUES (:venda_id, :data_parcela, :parcela_numero, :valor_parcela)");
                        $stmt->bindParam(':venda_id', $venda_id);
                        $stmt->bindParam(':data_parcela', $data_parcela);
                        $stmt->bindParam(':parcela_numero', $i);
                        $stmt->bindParam(':valor_parcela', $valor_parcela); 
                        $stmt->execute();
                    }
                }

                $pdo->commit();

                echo "<h2>Produtos e parcelas atualizados com sucesso!</h2>";
            }
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo "Erro de banco de dados: " . $e->getMessage();
    } catch (Exception $e) {
        echo "Erro: " . $e->getMessage();
    }

    error_reporting(E_ERROR | E_PARSE);
?>

<div class="container">
    <h2>Cadastro de Venda</h2>
    <form method="POST" action="cadastro_vendas.php">
        <div class="form-group">
            <label for="cliente">Cliente:</label>
            <select id="cliente" name="cliente_nome" class="form-control" required>
                <option value="">Selecionar Cliente</option>
                <?php
                include('conexao.php');
                $stmt = $pdo->prepare("SELECT nome FROM clientes");
                $stmt->execute();
                $clientes = $stmt->fetchAll();
                foreach ($clientes as $cliente) {
                    echo '<option value="' . $cliente['nome'] . '">' . $cliente['nome'] . '</option>';
                }
                ?>
            </select>
        </div>

        <div id="itens_venda">
            <div class="item-venda">
                <div class="form-group">
                    <label for="produto">Produto:</label>
                    <select name="produtos[]" class="form-control produto" required>
                        <option value="">Selecionar Produto</option>
                        <?php
                        $stmt = $pdo->prepare("SELECT DISTINCT produto, preco_unitario FROM itens_venda");
                        $stmt->execute();
                        $produtos = $stmt->fetchAll();
                        foreach ($produtos as $produto) {
                            echo '<option value="' . $produto['produto'] . '" data-preco="' . $produto['preco_unitario'] . '">' . $produto['produto'] . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="quantidade">Quantidade:</label>
                    <input type="number" name="quantidades[]" class="form-control quantidade" required>
                </div>
                <div class="form-group">
                    <label for="preco_unitario">Preço Unitário:</label>
                    <input type="text" name="preco_unitarios[]" class="form-control preco_unitario" readonly>
                </div>
                <div class="form-group">
                    <label for="preco_total">Preço Total:</label>
                    <input type="text" class="form-control preco_total" readonly>
                </div>
            </div>
        </div>

        <button type="button" id="add-item" class="btn btn-secondary">Adicionar Outro Item</button>

        <div id="parcelamento" class="form-group">
            <label for="quantidade_parcelas">Quantidade de Parcelas:</label>
            <input type="number" id="quantidade_parcelas" name="quantidade_parcelas" class="form-control">
        </div>

        <div id="valores_parcelas" class="form-group" style="display: none;">
            <label>Valores das Parcelas:</label>
            <div id="valores_parcelas_list"></div>
        </div>

        <button type="submit" class="btn btn-primary">Cadastrar Venda</button>
    </form>
</div>

<script>
    function calcularPrecoTotal($elemento) {
        var quantidade = $elemento.find('.quantidade').val();
        var preco_unitario = $elemento.find('.preco_unitario').val();
        var preco_total = quantidade * preco_unitario;
        $elemento.find('.preco_total').val(preco_total.toFixed(2));
    }

    $(document).on('change', '.produto', function() {
        var preco = $(this).find('option:selected').data('preco');
        var $item = $(this).closest('.item-venda');
        $item.find('.preco_unitario').val(preco);
        calcularPrecoTotal($item);
    });

    $(document).on('input', '.quantidade', function() {
        var $item = $(this).closest('.item-venda');
        calcularPrecoTotal($item);
    });

    $('#add-item').click(function() {
        $('#itens_venda').append(`
            <div class="item-venda">
                <div class="form-group">
                    <label for="produto">Produto:</label>
                    <select name="produtos[]" class="form-control produto" required>
                        <option value="">Selecionar Produto</option>
                        <?php
                        foreach ($produtos as $produto) {
                            echo '<option value="' . $produto['produto'] . '" data-preco="' . $produto['preco_unitario'] . '">' . $produto['produto'] . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="quantidade">Quantidade:</label>
                    <input type="number" name="quantidades[]" class="form-control quantidade" required>
                </div>
                <div class="form-group">
                    <label for="preco_unitario">Preço Unitário:</label>
                    <input type="text" name="preco_unitarios[]" class="form-control preco_unitario" readonly>
                </div>
                <div class="form-group">
                    <label for="preco_total">Preço Total:</label>
                    <input type="text" class="form-control preco_total" readonly>
                </div>
            </div>
        `);
    });

    $('#quantidade_parcelas').on('input', function() {
        var quantidade = $(this).val();
        var valoresParcelaDiv = $('#valores_parcelas');
        var valoresParcelaList = $('#valores_parcelas_list');

        valoresParcelaList.empty();

        if (quantidade > 0) {
            valoresParcelaDiv.show();
            var precoTotal = 0;
            $('.preco_total').each(function() {
                precoTotal += parseFloat($(this).val()) || 0;
            });

            var valorParcela = precoTotal / quantidade;

            for (var i = 1; i <= quantidade; i++) {
                valoresParcelaList.append(`
                    <div class="form-group">
                        <label for="valor_parcela_${i}">Valor da Parcela ${i}:</label>
                        <input type="text" name="valor_parcela[]" id="valor_parcela_${i}" class="form-control" value="${valorParcela.toFixed(2)}" readonly>
                    </div>
                `);
            }
        } else {
            valoresParcelaDiv.hide();
        }
    });

    $('.produto').trigger('change');
</script>
