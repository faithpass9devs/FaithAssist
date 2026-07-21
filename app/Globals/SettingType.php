<?php

namespace App\Globals;

final class SettingType
{
    public const BOOLEAN = 'boolean';

    public const STRING = 'string';

    public const NUMBER = 'number';

    public const ARRAY = 'array';

    public const JSON = 'json';

    public const FILE = 'file';

    public static function values(): array
    {
        return [
            self::BOOLEAN,
            self::STRING,
            self::NUMBER,
            self::ARRAY,
            self::JSON,
            self::FILE,
        ];
    }
}
