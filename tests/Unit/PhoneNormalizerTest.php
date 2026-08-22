<?php

namespace Tests\Unit;

use App\Services\WhatsApp\PhoneNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNormalizerTest extends TestCase
{
    public static function normalizesValidMexicanPhones(): array
    {
        return [
            'lada 52 con 10 digitos' => ['52', '7621556122', '5217621556122'],
            'lada 521 con 10 digitos' => ['521', '7220011122', '5217220011122'],
            'lada 1 tratada como Mexico' => ['1', '7224978399', '5217224978399'],
            'telefono con el 1 del 521' => ['52', '17621556122', '5217621556122'],
            'telefono con espacios y guiones' => ['52', '762 155 6122', '5217621556122'],
            'lada con simbolo +' => ['+52', '7621556122', '5217621556122'],
        ];
    }

    #[DataProvider('normalizesValidMexicanPhones')]
    public function test_normalizes_valid_mexican_phones(string $lada, string $phone, string $expected): void
    {
        $result = PhoneNormalizer::normalize($lada, $phone);

        $this->assertNull($result['error']);
        $this->assertSame($expected, $result['number']);
        $this->assertSame('521', $result['country']);
    }

    public function test_rejects_incomplete_mexican_phone(): void
    {
        $result = PhoneNormalizer::normalize('52', '722001122');

        $this->assertNull($result['number']);
        $this->assertStringContainsString('9 digitos', $result['error']);
    }

    public function test_rejects_missing_lada(): void
    {
        $result = PhoneNormalizer::normalize('', '7621556122');

        $this->assertNull($result['number']);
        $this->assertNotNull($result['error']);
    }

    public function test_accepts_foreign_lada(): void
    {
        $result = PhoneNormalizer::normalize('54', '1155551234');

        $this->assertNull($result['error']);
        $this->assertSame('541155551234', $result['number']);
        $this->assertSame('54', $result['country']);
    }

    public function test_rejects_short_foreign_number(): void
    {
        $result = PhoneNormalizer::normalize('49', '12345');

        $this->assertNull($result['number']);
        $this->assertNotNull($result['error']);
    }
}
