<?php

namespace Bx\Xhprof\Listener;

use Bx\Portal\Connector\BaseConfigList;

class ConfigList extends BaseConfigList
{
    public const USE_PROFILING = 'USE_PROFILING';
    public const PROFILING_URLS = 'PROFILING_URLS';
    public const TIME_EXEC_LIMIT = 'TIME_EXEC_LIMIT';
    public const XHPROF_TOP_MODE = 'XHPROF_MODE';
    public const XHPROF_TOP_LIMIT = 'XHPROF_TOP_LIMIT';
    public const XHPROF_TOP_DIVERSITY = 'XHPROF_TOP_DIVERSITY';

    protected static function getModuleId(): string
    {
        return 'bx.xhprof.listener';
    }

    public static function get(string $key, $default = null)
    {
        $originalValue = parent::get($key, $default);
        if (static::isComplexValue($key) && !empty($originalValue)) {
            return json_decode($originalValue, true) ?: [];
        }

        return $originalValue;
    }

    public static function set(string $key, $value): void
    {
        if (static::isComplexValue($key)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        parent::set($key, $value);
    }

    private static function isComplexValue(string $key): bool
    {
        return in_array($key, [
            static::PROFILING_URLS,
        ]);
    }
}
