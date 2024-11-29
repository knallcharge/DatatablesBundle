<?php

/*
 * This file is part of the SgDatatablesBundle package.
 *
 * (c) stwe <https://github.com/stwe/DatatablesBundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sg\DatatablesBundle\Datatable;

use Random\RandomException;

use function is_int;

/**
 * Class Helper
 */
class Helper
{
    /**
     * Generate a unique ID.
     *
     * @param string $prefix
     *
     * @return string
     * @throws RandomException
     */
    public static function generateUniqueID($prefix = '')
    {
        $id = sha1(microtime(true) . random_int(10000, 90000));

        return $prefix ? $prefix . '-' . $id : $id;
    }

    /**
     * Returns a array notated property path for the Accessor.
     *
     * @param string      $data
     * @param string|null $value
     *
     * @return string
     */
    public static function getDataPropertyPath($data, &$value = null)
    {
        // handle nested array case
        if (is_int(strpos($data, '[')) === true) {
            $before = strstr($data, '[', true);
            $value  = strstr($data, ']');

            // remove needle
            $value = str_replace('].', '', $value);
            // format value
            $value = '[' . str_replace('.', '][', $value) . ']';

            $data = $before;
        }

        // e.g. 'createdBy.allowed' => [createdBy][allowed]
        return '[' . str_replace('.', '][', $data) . ']';
    }

    /**
     * Returns object notated property path.
     *
     * @param string $path
     * @param int    $key
     * @param string $value
     *
     * @return string
     */
    public static function getPropertyPathObjectNotation($path, $key, $value): string
    {
        $objectValue = str_replace(['][', '[', ']'], ['.', '', ''], $value);

        return str_replace(['[', ']'], '', $path) . '[' . $key . '].' . $objectValue;
    }
}
