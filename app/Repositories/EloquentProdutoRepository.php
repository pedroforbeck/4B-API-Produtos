<?php

namespace App\Repositories;

use App\Models\Produto;
use Illuminate\Pagination\LengthAwarePaginator;

class EloquentProdutoRepository implements ProdutoRepositoryInterface
{
    public function create(array $dados): Produto
    {
        return Produto::create($dados);
    }

    public function update(Produto $produto, array $dados): Produto
    {
        $produto->update($dados);

        return $produto->refresh();
    }

    public function delete(Produto $produto): void
    {
        $produto->delete();
    }

    public function search(array $filtros = [], int $porPagina = 10): LengthAwarePaginator
    {
        return Produto::query()
            ->when(isset($filtros['search']) && $filtros['search'] !== '',
                fn ($query) => $query->where('nome', 'LIKE', "%{$filtros['search']}%"))
            ->when(isset($filtros['preco_min']),
                fn ($query) => $query->where('preco', '>=', $filtros['preco_min']))
            ->when(isset($filtros['preco_max']),
                fn ($query) => $query->where('preco', '<=', $filtros['preco_max']))
            ->paginate($porPagina);
    }
}
