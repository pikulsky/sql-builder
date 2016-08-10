<?php
namespace Aura\SqlQuery\Mysql;

class Util
{
    /**
     * Correct php types to mysql types
     *
     * @param mixed $value
     *
     * @return int|string
     */
    public static function correctBindValue($value)
    {
        // cast date time
        if ($value instanceof \DateTime) {
            $value = $value->format('Y-m-d H:i:s');
        }

        // cast boolean
        if (is_bool($value)) {
            $value = (int) $value;
        }

        return $value;
    }
}