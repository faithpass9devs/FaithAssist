<?php

namespace App\Services\WhatsApp;

class PhoneNormalizer
{
    private const MEXICO_LADAS = ['52', '521', '1'];

    /**
     * Normaliza lada + teléfono al formato canónico de WhatsApp.
     *
     * México: la lada "1" se trata como captura errónea del formato 52/521
     * y un teléfono de 11 dígitos que inicia con "1" incluye el "1" del 521.
     *
     * @return array{number: ?string, country: ?string, error: ?string}
     */
    public static function normalize(?string $lada, ?string $phone): array
    {
        $lada = preg_replace('/\D/', '', (string) $lada) ?? '';
        $phone = preg_replace('/\D/', '', (string) $phone) ?? '';

        if ($lada === '' || $phone === '') {
            return [
                'number' => null,
                'country' => null,
                'error' => "Falta la lada o el telefono (lada: '{$lada}', telefono: '{$phone}').",
            ];
        }

        if (strlen($phone) === 11 && str_starts_with($phone, '1')) {
            $phone = substr($phone, 1);
        }

        if (in_array($lada, self::MEXICO_LADAS, true)) {
            if (strlen($phone) !== 10) {
                return [
                    'number' => null,
                    'country' => null,
                    'error' => sprintf(
                        'Telefono mexicano invalido (%d digitos, se requieren 10): lada %s + %s.',
                        strlen($phone),
                        $lada,
                        $phone,
                    ),
                ];
            }

            return ['number' => '521'.$phone, 'country' => '521', 'error' => null];
        }

        $number = $lada.$phone;

        if (strlen($number) < 11) {
            return [
                'number' => null,
                'country' => null,
                'error' => sprintf(
                    'Numero invalido (%d digitos en total): lada %s + %s.',
                    strlen($number),
                    $lada,
                    $phone,
                ),
            ];
        }

        return ['number' => $number, 'country' => $lada, 'error' => null];
    }
}
