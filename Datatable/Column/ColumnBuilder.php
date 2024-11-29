<?php

/*
 * This file is part of the SgDatatablesBundle package.
 *
 * (c) stwe <https://github.com/stwe/DatatablesBundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sg\DatatablesBundle\Datatable\Column;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\MappingException;
use Exception;
use Psr\Cache\InvalidArgumentException;
use ReflectionException;
use Sg\DatatablesBundle\Datatable\Factory;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

use function array_key_exists;
use function count;

/**
 * Class ColumnBuilder
 */
class ColumnBuilder
{
    /**
     * The class metadata.
     *
     * @var ClassMetadata
     */
    private $metadata;

    /**
     * The Twig Environment.
     *
     * @var Environment
     */
    private $twig;

    /**
     * The router.
     *
     * @var RouterInterface
     */
    private $router;

    /**
     * The name of the associated Datatable.
     *
     * @var string
     */
    private $datatableName;

    /**
     * The doctrine orm entity manager service.
     *
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * The generated Columns.
     *
     * @var array
     */
    private $columns;

    /**
     * This variable stores the array of column names as keys and column ids as values
     * in order to perform search column id by name.
     *
     * @var array
     */
    private $columnNames;

    /**
     * Unique Columns.
     *
     * @var array
     */
    private $uniqueColumns;

    /**
     * The fully-qualified class name of the entity (e.g. AppBundle\Entity\Post).
     *
     * @var string
     */
    private $entityClassName;

    /**
     * @param string $datatableName
     */
    public function __construct(ClassMetadata $metadata, Environment $twig, RouterInterface $router, $datatableName, EntityManagerInterface $em)
    {
        $this->metadata      = $metadata;
        $this->twig          = $twig;
        $this->router        = $router;
        $this->datatableName = $datatableName;
        $this->em            = $em;

        $this->columns         = [];
        $this->columnNames     = [];
        $this->uniqueColumns   = [];
        $this->entityClassName = $metadata->getName();
    }

    //-------------------------------------------------
    // Builder
    //-------------------------------------------------

    /**
     * Add Column.
     *
     * @param string|null            $dql
     * @param ColumnInterface|string $class
     *
     * @return $this
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function add($dql, $class, array $options = [])
    {
        $column = Factory::create($class, ColumnInterface::class);
        $column->initOptions();

        $this->handleDqlProperties($dql, $options, $column);
        $this->setEnvironmentProperties($column);
        $column->set($options);

        $this->setTypeProperties($dql, $column);
        $this->addColumn($dql, $column);

        $this->checkUnique();

        return $this;
    }

    /**
     * Remove Column.
     *
     * @param string|null $dql
     *
     * @return $this
     */
    public function remove($dql)
    {
        foreach ($this->columns as $column) {
            if ($column->getDql() === $dql) {
                $this->removeColumn($dql, $column);

                break;
            }
        }

        return $this;
    }

    //-------------------------------------------------
    // Getters && Setters
    //-------------------------------------------------

    /**
     * @return array
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * @return array
     */
    public function getColumnNames()
    {
        return $this->columnNames;
    }

    /**
     * Get a unique Column by his type.
     *
     * @param string $columnType
     *
     * @return mixed|null
     */
    public function getUniqueColumn($columnType)
    {
        return $this->uniqueColumns[$columnType] ?? null;
    }

    //-------------------------------------------------
    // Helper
    //-------------------------------------------------

    /**
     * @param string $entityName
     *
     * @return ClassMetadata
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws Exception
     */
    private function getMetadata($entityName)
    {
        try {
            $metadata = $this->em->getMetadataFactory()->getMetadataFor($entityName);
        } catch (MappingException) {
            throw new Exception('DatatableQueryBuilder::getMetadata(): Given object ' . $entityName . ' is not a Doctrine Entity.');
        }

        return $metadata;
    }

    /**
     * Get metadata from association.
     *
     * @param string        $association
     * @param ClassMetadata $metadata
     *
     * @return ClassMetadata
     * @throws Exception
     * @throws InvalidArgumentException
     */
    private function getMetadataFromAssociation($association, ClassMetadata $metadata)
    {
        $targetClass = $metadata->getAssociationTargetClass($association);

        return $this->getMetadata($targetClass);
    }

    /**
     * @param ClassMetadata  $metadata
     * @param AbstractColumn $column
     * @param string         $field
     *
     * @return void
     */
    private function setTypeOfField(ClassMetadata $metadata, AbstractColumn $column, $field): void
    {
        if ($column->getTypeOfField() === null) {
            $column->setTypeOfField($metadata->getTypeOfField($field));
        }

        $column->setOriginalTypeOfField($metadata->getTypeOfField($field));
    }

    /**
     * Handle dql properties.
     *
     * @param string         $dql
     * @param array          $options
     * @param AbstractColumn $column
     *
     * @return void
     * @throws Exception
     */
    private function handleDqlProperties($dql, array $options, AbstractColumn $column): void
    {
        // the Column 'data' property has normally the same value as 'dql'
        $column->setData($dql);

        if (!isset($options['dql'])) {
            $column->setCustomDql(false);
            $column->setDql($dql);
        } else {
            $column->setCustomDql(true);
        }
    }

    /**
     * Set environment properties.
     *
     * @param AbstractColumn $column
     *
     * @return void
     */
    private function setEnvironmentProperties(AbstractColumn $column): void
    {
        $column->setDatatableName($this->datatableName);
        $column->setEntityClassName($this->entityClassName);
        $column->setTwig($this->twig);
        $column->setRouter($this->router);
    }

    /**
     * Sets some types.
     *
     * @param string         $dql
     * @param AbstractColumn $column
     *
     * @return void
     * @throws Exception|InvalidArgumentException
     */
    private function setTypeProperties($dql, AbstractColumn $column): void
    {
        if ($column->isSelectColumn() === true && $column->isCustomDql() === false) {
            $metadata = $this->metadata;
            $parts    = explode('.', $dql);
            // add associations types
            if ($column->isAssociation() === true) {
                while (count($parts) > 1) {
                    $currentPart = array_shift($parts);

                    // @noinspection PhpUndefinedMethodInspection
                    $column->addTypeOfAssociation($metadata->getAssociationMapping($currentPart)['type']);
                    $metadata = $this->getMetadataFromAssociation($currentPart, $metadata);
                }
            } else {
                $column->setTypeOfAssociation(null);
            }

            // set the type of the field
            $this->setTypeOfField($metadata, $column, $parts[0]);
        } else {
            $column->setTypeOfAssociation(null);
            $column->setOriginalTypeOfField(null);
        }
    }

    /**
     * Adds a Column.
     *
     * @param string         $dql
     * @param AbstractColumn $column
     *
     * @return void
     */
    private function addColumn($dql, AbstractColumn $column): void
    {
        if ($column->callAddIfClosure() === true) {
            $this->columns[]         = $column;
            $index                   = count($this->columns) - 1;
            $this->columnNames[$dql] = $index;
            $column->setIndex($index);

            // Use the Column-Index as data source for Columns with 'dql' === null
            if ($column->getDql() === null && $column->getData() === null) {
                $column->setData($index);
            }

            if ($column->isUnique() === true) {
                $this->uniqueColumns[$column->getColumnType()] = $column;
            }
        }
    }

    /**
     * Removes a Column.
     *
     * @param string         $dql
     * @param AbstractColumn $column
     *
     * @return void
     */
    private function removeColumn($dql, AbstractColumn $column): void
    {
        // Remove column from columns
        foreach ($this->columns as $k => $c) {
            if ($c === $column) {
                unset($this->columns[$k]);
                $this->columns = array_values($this->columns);

                break;
            }
        }

        // Remove column from columnNames
        if (array_key_exists($dql, $this->columnNames)) {
            unset($this->columnNames[$dql]);
        }

        // Reindex columnNames
        foreach ($this->columns as $k => $c) {
            $this->columnNames[$c->getDql()] = $k;
        }

        // Remove column from uniqueColumns
        foreach ($this->uniqueColumns as $k => $c) {
            if ($c === $column) {
                unset($this->uniqueColumns[$k]);
                $this->uniqueColumns = array_values($this->uniqueColumns);

                break;
            }
        }
    }

    /**
     * Check unique.
     *
     * @return void
     * @throws Exception
     *
     */
    private function checkUnique(): void
    {
        $unique = $this->uniqueColumns;

        if (count(array_unique($unique)) < count($unique)) {
            throw new Exception('ColumnBuilder::checkUnique(): Unique columns are only allowed once.');
        }
    }
}
