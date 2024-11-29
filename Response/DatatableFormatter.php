<?php

/*
 * This file is part of the SgDatatablesBundle package.
 *
 * (c) stwe <https://github.com/stwe/DatatablesBundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sg\DatatablesBundle\Response;

use Doctrine\ORM\Tools\Pagination\Paginator;
use Sg\DatatablesBundle\Datatable\Column\ColumnInterface;
use Sg\DatatablesBundle\Datatable\DatatableInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

use function array_key_exists;
use function call_user_func;
use function is_callable;

/**
 * Class DatatableFormatter
 */
class DatatableFormatter
{
    /**
     * The output array.
     *
     * @var array
     */
    private $output;

    /**
     * The PropertyAccessor.
     * Provides functions to read and write from/to an object or array using a simple string notation.
     *
     * @var PropertyAccessor
     */
    private $accessor;

    public function __construct()
    {
        $this->output = ['data' => []];

        $this->accessor = PropertyAccess::createPropertyAccessor();
    }

    //-------------------------------------------------
    // Formatter
    //-------------------------------------------------

    /**
     * Create the output array.
     */
    public function runFormatter(Paginator $paginator, DatatableInterface $datatable)
    {
        $lineFormatter = $datatable->getLineFormatter();
        $columns       = $datatable->getColumnBuilder()->getColumns();

        foreach ($paginator as $row) {
            // Adding custom DQL fields make PARTIAL columns stored in key 0
            if (isset($row[0])) {
                $row = array_merge($row, $row[0]);
                unset($row[0]);
            }

            // Format custom DQL fields output ('custom.dql.name' => $row['custom']['dql']['name'] = 'value')
            foreach ($columns as $column) {
                // @noinspection PhpUndefinedMethodInspection
                if ($column->isCustomDql() === true) {
                    $columnAlias = str_replace('.', '_', $column->getData());
                    $columnPath  = '[' . str_replace('.', '][', $column->getData()) . ']';
                    // @noinspection PhpUndefinedMethodInspection
                    if ($columnAlias !== $column->getData()) {
                        $this->accessor->setValue($row, $columnPath, $row[$columnAlias]);
                        unset($row[$columnAlias]);
                    }
                }
            }

            // 1. Set (if necessary) the custom data source for the Columns with a 'data' option
            foreach ($columns as $column) {
                $dql  = $column->getDql();
                $data = $column->getData();

                // @noinspection PhpUndefinedMethodInspection
                if ($dql !== null && $dql !== $data && array_key_exists($data, $row) === false && ($column->isAssociation() === false)) {
                    $row[$data] = $row[$dql];
                    unset($row[$dql]);
                }
            }

            // 2. Call the the lineFormatter to format row items
            if ($lineFormatter !== null && is_callable($lineFormatter)) {
                $row = call_user_func($datatable->getLineFormatter(), $row);
            }

            /** @var ColumnInterface $column */
            foreach ($columns as $column) {
                // 3. Add some special data to the output array. For example, the visibility of actions.
                $column->addDataToOutputArray($row);
                // 4. Call Columns renderContent method to format row items (e.g. for images or boolean values)
                $column->renderCellContent($row);
            }

            foreach ($columns as $column) {
                if (!$column->getSentInResponse()) {
                    unset($row[$column->getDql()]);
                }
            }

            $this->output['data'][] = $row;
        }
    }

    //-------------------------------------------------
    // Getters && Setters
    //-------------------------------------------------

    /**
     * @return array
     */
    public function getOutput()
    {
        return $this->output;
    }
}
