<?php

namespace Tests\Feature;

use App\Models\Product;
use Tests\TestCase;

class BuilderCompatibilityTest extends TestCase
{
    /** @var array<int, string> */
    private array $productIds = [];

    private Product $cpu;

    private Product $motherboard;

    private Product $ram;

    private Product $ssd;

    private Product $gpu;

    private Product $case;

    private Product $psu750;

    private Product $psu1000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cpu = $this->makeProduct('CPU', [
            'socket' => 'LGA 1700',
            'tdp' => '125W',
        ]);

        $this->motherboard = $this->makeProduct('Motherboard', [
            'socket' => 'lga1700',
            'form_factor' => 'ATX',
            'memory_type' => 'DDR5',
        ]);

        $this->ram = $this->makeProduct('RAM', ['type' => 'DDR5']);
        $this->ssd = $this->makeProduct('SSD', ['capacity' => '1TB']);

        $this->gpu = $this->makeProduct('GPU', [
            'tdp' => '450W',
            'length' => 336,
        ]);

        $this->case = $this->makeProduct('Case', [
            'form_factor' => 'ATX',
            'max_gpu' => '365mm',
        ]);

        $this->psu750 = $this->makeProduct('PSU', ['wattage_w' => 750]);
        $this->psu1000 = $this->makeProduct('PSU', ['wattage_w' => 1000]);
    }

    protected function tearDown(): void
    {
        Product::whereIn('_id', $this->productIds)->delete();
        parent::tearDown();
    }

    public function test_socket_is_normalized_before_comparison(): void
    {
        $response = $this->postJson('/api/builder/validate', $this->payload($this->psu1000));

        $response->assertOk()
            ->assertJsonPath('data.is_compatible', true)
            ->assertJsonPath('data.missing_slots', []);
    }

    public function test_undersized_psu_is_an_error(): void
    {
        $response = $this->postJson('/api/builder/validate', $this->payload($this->psu750));

        $response->assertOk()
            ->assertJsonPath('data.is_compatible', false)
            ->assertJsonPath('data.power_analysis.psu_status', 'insufficient');

        $this->assertStringContainsString(
            'Daya PSU tidak cukup',
            implode(' ', $response->json('data.errors')),
        );
    }

    public function test_psu_error_can_be_overridden_with_ignore_flag(): void
    {
        $payload = $this->payload($this->psu750);
        $payload['ignore_psu_warning'] = true;

        $response = $this->postJson('/api/builder/validate', $payload);

        $response->assertOk()
            ->assertJsonPath('data.is_compatible', true);

        $this->assertNotEmpty($response->json('data.warnings'));
    }

    public function test_gpu_length_uses_numeric_values(): void
    {
        $longGpu = $this->makeProduct('GPU', [
            'tdp' => '450W',
            'length' => 420,
        ]);

        $payload = $this->payload($this->psu1000, $longGpu);

        $response = $this->postJson('/api/builder/validate', $payload);

        $response->assertOk()
            ->assertJsonPath('data.is_compatible', false);

        $this->assertStringContainsString(
            'Panjang VGA',
            implode(' ', $response->json('data.errors')),
        );
    }

    public function test_missing_slot_is_reported(): void
    {
        $payload = $this->payload($this->psu1000);
        unset($payload['gpu_id']);

        $response = $this->postJson('/api/builder/validate', $payload);

        $response->assertOk()
            ->assertJsonPath('data.is_compatible', false)
            ->assertJsonPath('data.missing_slots', ['GPU']);
    }

    /**
     * @param  array<string, mixed>  $specs
     */
    private function makeProduct(string $category, array $specs): Product
    {
        $product = Product::create([
            'category' => $category,
            'brand' => 'Test',
            'name' => 'Test '.$category.' '.uniqid(),
            'price' => 1_000_000,
            'discount_price' => 0,
            'stock' => 10,
            'specs' => $specs,
        ]);

        $this->productIds[] = (string) $product->_id;

        return $product;
    }

    /**
     * @return array<string, string>
     */
    private function payload(Product $psu, ?Product $gpu = null): array
    {
        return [
            'cpu_id' => (string) $this->cpu->_id,
            'motherboard_id' => (string) $this->motherboard->_id,
            'ram_id' => (string) $this->ram->_id,
            'storage_id' => (string) $this->ssd->_id,
            'gpu_id' => (string) ($gpu ?? $this->gpu)->_id,
            'case_id' => (string) $this->case->_id,
            'psu_id' => (string) $psu->_id,
        ];
    }
}
