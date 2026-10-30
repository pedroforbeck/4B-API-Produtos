<?php

namespace Tests\Feature;

use App\Models\Produto;
use App\Repositories\ProdutoRepositoryInterface;
use App\Services\ProdutoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProdutoServiceTest extends TestCase
{
    use RefreshDatabase;

    private function criarProduto(): Produto
    {
        return Produto::create([
            'nome' => 'Lajota',
            'preco_custo' => 100,
            'preco' => 110,
        ]);
    }

    public function test_update_atualiza_o_produto_com_sucesso(): void
    {
        $produto = $this->criarProduto();

        $atualizado = app(ProdutoService::class)->update($produto, [
            'nome' => 'Lajota Premium',
            'preco' => 150,
        ]);

        $this->assertSame('Lajota Premium', $atualizado->nome);
        $this->assertDatabaseHas('produtos', [
            'id' => $produto->id,
            'nome' => 'Lajota Premium',
            'preco' => 150,
        ]);
    }

    public function test_update_nao_altera_o_produto_se_o_preco_nao_segue_a_regra_de_negocio(): void
    {
        $produto = $this->criarProduto();

        // Custo continua 100 -> preço mínimo 110. Preço 105 viola a regra
        $this->assertThrows(
            fn () => app(ProdutoService::class)->update($produto, ['preco' => 105]),
            ValidationException::class
        );

        $this->assertDatabaseHas('produtos', [
            'id' => $produto->id,
            'preco' => 110,
        ]);
    }

    public function test_update_delega_a_persistencia_ao_repository(): void
    {
        $produto = $this->criarProduto();
        $dados = ['preco' => 150];

        $this->mock(ProdutoRepositoryInterface::class)
            ->shouldReceive('update')
            ->once()
            ->with($produto, $dados)
            ->andReturn($produto);

        $this->assertSame($produto, app(ProdutoService::class)->update($produto, $dados));
    }
}
