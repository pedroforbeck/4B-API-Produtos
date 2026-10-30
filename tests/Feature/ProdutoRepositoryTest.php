<?php

namespace Tests\Feature;

use App\Models\Produto;
use App\Repositories\ProdutoRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProdutoRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_filtra_por_preco_minimo_incluindo_o_limite(): void
    {
        $this->criarProduto(['preco' => 10]);
        $limite = $this->criarProduto(['preco' => 20]);
        $acima = $this->criarProduto(['preco' => 30]);

        $resultado = app(ProdutoRepositoryInterface::class)->search(['preco_min' => 20]);

        $this->assertSame([$limite->id, $acima->id], $resultado->getCollection()->modelKeys());
    }

    public function test_api_aplica_e_valida_o_preco_minimo(): void
    {
        $this->criarProduto(['preco' => 10]);
        $produto = $this->criarProduto(['preco' => 20]);

        $this->getJson('/api/produtos?preco_min=20')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $produto->id);

        $this->getJson('/api/produtos?preco_min=-1')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('preco_min');

        $this->getJson('/api/produtos?preco_min=abc')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('preco_min');
    }

    private function criarProduto(array $dados = []): Produto
    {
        return Produto::create(array_merge([
            'nome' => 'Lajota',
            'preco_custo' => 5,
            'preco' => 10,
            'quantidade' => 10,
        ], $dados));
    }
}
