<?php

namespace Nails\Common\Traits\Model;

use Nails\Factory;

/**
 * Trait Sortable
 *
 * @package Nails\Common\Traits\Model
 */
trait Sortable
{
    /**
     * Returns the column name for specific columns of interest
     *
     * @param string      $sColumn  The column to query
     * @param string|null $sDefault The default value if not defined
     *
     * @return string|null
     */
    abstract public function getColumn(string $sColumn, string $sDefault = null): ?string;

    // --------------------------------------------------------------------------

    /**
     * Returns the column on which to sort
     *
     * @return string
     * @deprecated Use getColumnSortable
     */
    public function getSortableColumn()
    {
        return $this->getColumnSortable();
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the column to use for sorting
     *
     * @return string
     */
    public function getColumnSortable(): string
    {
        return $this->getColumn('sortable', 'order');
    }
}
