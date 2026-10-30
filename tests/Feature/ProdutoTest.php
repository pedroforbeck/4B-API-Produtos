<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ProdutoTest extends TestCase
{
    use RefreshDatabase; // Faltou essa trait na aula -> serve pra utilizar e dar refresh na memória temporária do sqlite


    public function test_cria_produto_com_sucesso(): void
    {
        $this->postJson('/api/produtos', [
            'nome' => 'Lajota', 
            'preco_custo' => 100,
            'preco' => 110,
        ])->assertCreated();

        $this->assertDatabaseHas('produtos', [
            'nome' => 'Lajota']);
    }

    public function test_nao_cria_produto_se_o_preco_nao_segue_a_regra_de_negocio(): void
    {
        // Custo 100 -> preço mínimo 110. Preço 105 viola a regra da margem
        $this->postJson('/api/produtos', [
            'nome' => 'Lajota',
            'preco_custo' => 100,
            'preco' => 105,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['preco']);

        $this->assertDatabaseMissing('produtos', [
            'nome' => 'Lajota']);
    }
}
