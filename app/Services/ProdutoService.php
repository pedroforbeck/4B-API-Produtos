<?php

namespace App\Services;

use App\Models\Produto;
use App\Repositories\ProdutoRepositoryInterface;
use Illuminate\Validation\ValidationException;

class ProdutoService
{
    public function __construct(private ProdutoRepositoryInterface $produtos) {}

    public function create(array $dados)
    {
        $this->verificarMargem($dados['preco'], $dados['preco_custo']);

        return $this->produtos->create($dados);
    }

    public function listar(array $filtros)
    {
        return $this->produtos->search($filtros);
    }

    public function update(Produto $produto, array $dados): Produto
    {
        // Usa o valor enviado ou, se não veio, o que já está salvo no produto,
        // para a regra de margem valer mesmo quando só um dos preços é alterado
        $preco = $dados['preco'] ?? $produto->preco;
        $custo = $dados['preco_custo'] ?? $produto->preco_custo;

        $this->verificarMargem((float) $preco, (float) $custo);

        return $this->produtos->update($produto, $dados);
    }

    private function verificarMargem(float $preco, float $custo)
    {
        $valor = $custo + (0.1 * $custo);
        $preco_minimo = round($valor, 2);

        if ($preco < $preco_minimo) {
            throw ValidationException::withMessages([
                'preco' => sprintf('O preço deve ser mais do que 10 por cento do valor de custo: %.2f', $preco_minimo),
            ]);
        }
    }
}
