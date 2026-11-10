<?php

namespace Tests\Unit;

use App\Support\SpecNormalizer;
use PHPUnit\Framework\TestCase;

class SpecNormalizerTest extends TestCase
{
    public function test_socket_is_normalized_before_comparison(): void
    {
        $this->assertSame('LGA1700', SpecNormalizer::socket('LGA 1700'));
        $this->assertSame('LGA1700', SpecNormalizer::socket('lga-1700'));
        $this->assertSame('LGA1700', SpecNormalizer::socket('LGA_1700'));
        $this->assertSame(
            SpecNormalizer::socket('LGA 1700'),
            SpecNormalizer::socket('lga1700'),
        );
    }

    public function test_form_factor_rank_follows_size_hierarchy(): void
    {
        $this->assertSame(1, SpecNormalizer::formFactorRank('Mini-ITX'));
        $this->assertSame(2, SpecNormalizer::formFactorRank('Micro-ATX'));
        $this->assertSame(2, SpecNormalizer::formFactorRank('mATX'));
        $this->assertSame(3, SpecNormalizer::formFactorRank('ATX'));
        $this->assertSame(4, SpecNormalizer::formFactorRank('E-ATX'));

        $this->assertNull(SpecNormalizer::formFactorRank('BTX'));
    }

    public function test_number_extracts_numeric_values_from_strings(): void
    {
        $this->assertSame(422.0, SpecNormalizer::number('422mm'));
        $this->assertSame(1000.0, SpecNormalizer::number('1000W'));
        $this->assertSame(1000.0, SpecNormalizer::number('1.000 W'));
        $this->assertSame(336.0, SpecNormalizer::number(336));
        $this->assertSame(2.5, SpecNormalizer::number('2,5 mm'));
        $this->assertNull(SpecNormalizer::number('tidak ada angka'));
        $this->assertNull(SpecNormalizer::number(null));
    }

    public function test_memory_type_is_normalized(): void
    {
        $this->assertSame('DDR5', SpecNormalizer::memoryType('DDR 5'));
        $this->assertSame('DDR5', SpecNormalizer::memoryType('ddr5'));
    }

    public function test_category_is_normalized_to_slug(): void
    {
        $this->assertSame('case_fan', SpecNormalizer::category('Case Fan'));
        $this->assertSame('cpu', SpecNormalizer::category('CPU'));
        $this->assertSame('operating_system', SpecNormalizer::category('Operating System'));
    }
}
