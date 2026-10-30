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

    public function test_search_filtra_por_preco_maximo_incluindo_o_limite(): void
    {
        $abaixo = $this->criarProduto(['preco' => 10]);
        $limite = $this->criarProduto(['preco' => 20]);
        $this->criarProduto(['preco' => 30]);

        $resultado = app(ProdutoRepositoryInterface::class)->search(['preco_max' => 20]);

        $this->assertSame([$abaixo->id, $limite->id], $resultado->getCollection()->modelKeys());
    }

    public function test_search_combina_nome_e_intervalo_de_precos(): void
    {
        $this->criarProduto(['nome' => 'Lajota simples', 'preco' => 10]);
        $produto = $this->criarProduto(['nome' => 'Lajota premium', 'preco' => 20]);
        $this->criarProduto(['nome' => 'Lajota especial', 'preco' => 30]);
        $this->criarProduto(['nome' => 'Cimento', 'preco' => 20]);

        $resultado = app(ProdutoRepositoryInterface::class)->search([
            'search' => 'Lajota', 'preco_min' => 15, 'preco_max' => 25,
        ]);

        $this->assertSame([$produto->id], $resultado->getCollection()->modelKeys());
    }

    public function test_search_aceita_zero_nos_filtros(): void
    {
        $produto = $this->criarProduto(['nome' => 'Produto 0', 'preco' => 0, 'preco_custo' => 0]);
        $this->criarProduto(['nome' => 'Produto 0 pago']);
        $this->criarProduto(['nome' => 'Brinde', 'preco' => 0, 'preco_custo' => 0]);

        $resultado = app(ProdutoRepositoryInterface::class)->search([
            'search' => '0', 'preco_min' => 0, 'preco_max' => 0,
        ]);

        $this->assertSame([$produto->id], $resultado->getCollection()->modelKeys());
    }

    public function test_search_sem_filtros_mantem_a_paginacao(): void
    {
        $this->criarProduto();
        $this->criarProduto();
        $this->criarProduto();

        $resultado = app(ProdutoRepositoryInterface::class)->search([], 2);

        $this->assertSame(3, $resultado->total());
        $this->assertCount(2, $resultado->items());
    }

    public function test_api_aplica_preco_maximo_isolado_e_os_filtros_combinados(): void
    {
        $produto = $this->criarProduto(['preco' => 20]);
        $this->criarProduto(['preco' => 30]);

        $this->getJson('/api/produtos?preco_max=20')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $produto->id);

        $this->getJson('/api/produtos?search=Lajota&preco_min=20&preco_max=20')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $produto->id);
    }

    public function test_api_rejeita_preco_maximo_invalido_e_intervalo_invertido(): void
    {
        foreach (['preco_max=-1', 'preco_max=abc', 'preco_min=30&preco_max=20'] as $filtros) {
            $this->getJson('/api/produtos?'.$filtros)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('preco_max');
        }
    }

    public function test_api_preserva_filtros_nos_links_de_paginacao(): void
    {
        for ($i = 0; $i < 11; $i++) {
            $this->criarProduto();
        }

        $resposta = $this->getJson('/api/produtos?search=Lajota&preco_min=5&preco_max=20')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 11);

        parse_str(parse_url($resposta->json('links.next'), PHP_URL_QUERY), $parametros);

        $this->assertSame([
            'search' => 'Lajota', 'preco_min' => '5', 'preco_max' => '20', 'page' => '2',
        ], $parametros);
    }

    public function test_update_persiste_alteracoes_e_retorna_o_produto_atualizado(): void
    {
        $produto = $this->criarProduto(['descricao' => 'Original']);

        $atualizado = app(ProdutoRepositoryInterface::class)->update($produto, [
            'nome' => 'Lajota premium', 'preco' => 15, 'quantidade' => 0, 'descricao' => null,
        ]);

        $this->assertSame($produto->id, $atualizado->id);
        $this->assertSame('Lajota premium', $atualizado->nome);
        $this->assertNull($atualizado->descricao);
        $this->assertDatabaseHas('produtos', [
            'id' => $produto->id, 'nome' => 'Lajota premium', 'preco' => 15,
            'quantidade' => 0, 'descricao' => null, 'preco_custo' => 5,
        ]);
    }

    public function test_api_atualiza_produto_e_preserva_campos_nao_enviados(): void
    {
        $produto = $this->criarProduto();

        $this->patchJson('/api/produtos/'.$produto->id, ['nome' => 'Lajota premium'])
            ->assertOk()
            ->assertJsonPath('data.id', $produto->id)
            ->assertJsonPath('data.nome', 'Lajota premium');

        $this->assertDatabaseHas('produtos', [
            'id' => $produto->id, 'nome' => 'Lajota premium', 'preco' => 10,
            'preco_custo' => 5, 'quantidade' => 10,
        ]);
    }

    public function test_api_nao_atualiza_quando_apenas_o_custo_viola_a_margem(): void
    {
        $produto = $this->criarProduto();

        $this->putJson('/api/produtos/'.$produto->id, ['preco_custo' => 10])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('preco');

        $this->assertDatabaseHas('produtos', [
            'id' => $produto->id, 'preco' => 10, 'preco_custo' => 5,
        ]);
    }

    public function test_api_update_retorna_404_para_produto_inexistente(): void
    {
        $this->putJson('/api/produtos/999', ['nome' => 'Inexistente'])->assertNotFound();
    }

    public function test_delete_remove_somente_o_produto_informado(): void
    {
        $produto = $this->criarProduto();
        $outro = $this->criarProduto();

        app(ProdutoRepositoryInterface::class)->delete($produto);

        $this->assertDatabaseMissing('produtos', ['id' => $produto->id]);
        $this->assertDatabaseHas('produtos', ['id' => $outro->id]);
    }

    public function test_api_exclui_produto_e_retorna_204_sem_corpo(): void
    {
        $produto = $this->criarProduto();
        $outro = $this->criarProduto();

        $this->deleteJson('/api/produtos/'.$produto->id)->assertNoContent();

        $this->assertDatabaseMissing('produtos', ['id' => $produto->id]);
        $this->assertDatabaseHas('produtos', ['id' => $outro->id]);
        $this->getJson('/api/produtos/'.$produto->id)->assertNotFound();
        $this->deleteJson('/api/produtos/'.$produto->id)->assertNotFound();
    }

    public function test_api_delete_retorna_404_para_produto_inexistente(): void
    {
        $this->deleteJson('/api/produtos/999')->assertNotFound();
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
