<?php

/*
 * This file is part of the SgDatatablesBundle package.
 *
 * (c) stwe <https://github.com/stwe/DatatablesBundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sg\DatatablesBundle\Tests\Column;

use DateTime;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use Sg\DatatablesBundle\Datatable\Column\ArrayColumn;

/**
 * Class ArrayColumnTest
 *
 * @internal
 * @coversNothing
 */
final class ArrayColumnTest extends TestCase
{
    /**
     * @throws ReflectionException
     */
    public function testIsAssociative(): void
    {
        $arrayColumn = new ArrayColumn();
        self::assertFalse(static::callMethod($arrayColumn, 'isAssociative', [['a', 'b']]));
        self::assertTrue(static::callMethod($arrayColumn, 'isAssociative', [['a' => 1, 'b' => 1]]));
    }

    /**
     * @throws ReflectionException
     */
    public function testArrayToString(): void
    {
        $arrayColumn = new ArrayColumn();
        $result      = static::callMethod($arrayColumn, 'arrayToString', [['a', 'b' => ['d' => new DateTime()]]]);
        self::assertNotEmpty($result);
        self::assertIsString($result);
    }

    /**
     * @param       $obj
     * @param       $name
     * @param array $args
     *
     * @return mixed
     * @throws ReflectionException
     */
    public static function callMethod($obj, $name, array $args)
    {
        $class  = new ReflectionClass($obj);
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method->invokeArgs($obj, $args);
    }
}
