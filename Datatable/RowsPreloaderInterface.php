<?php

namespace Sg\DatatablesBundle\Datatable;

/**
 * Interface RowsPreloaderInterface
 *
 * Implement this on a Datatable class whose getLineFormatter() needs to load
 * additional data per row (e.g. re-fetch the entity by id). DatatableFormatter
 * calls preloadRows() once with all rows of the current page, before the
 * lineFormatter callable is applied row by row, so the implementation can
 * batch-load everything it needs (e.g. warm up the entity manager's identity
 * map with a single findBy(['id' => $ids])) instead of querying once per row.
 */
interface RowsPreloaderInterface
{
    /**
     * @param array $rows all rows of the current page, after DQL/custom-data normalization
     */
    public function preloadRows(array $rows);
}
