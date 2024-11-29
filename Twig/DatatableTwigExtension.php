<?php

/*
 * This file is part of the SgDatatablesBundle package.
 *
 * (c) stwe <https://github.com/stwe/DatatablesBundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sg\DatatablesBundle\Twig;

use Closure;
use Sg\DatatablesBundle\Datatable\Action\Action;
use Sg\DatatablesBundle\Datatable\Column\ColumnInterface;
use Sg\DatatablesBundle\Datatable\DatatableInterface;
use Sg\DatatablesBundle\Datatable\Filter\FilterInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

use function is_array;
use function is_bool;

/**
 * Class DatatableTwigExtension
 */
class DatatableTwigExtension extends AbstractExtension
{
    /**
     * The PropertyAccessor.
     *
     * @var PropertyAccessor
     */
    protected $accessor;

    public function __construct()
    {
        $this->accessor = PropertyAccess::createPropertyAccessor();
    }

    //-------------------------------------------------
    // Twig_ExtensionInterface
    //-------------------------------------------------

    /**
     * {}
     */
    public function getName(): string
    {
        return 'sg_datatables_twig_extension';
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'sg_datatables_render',
                [$this, 'datatablesRender'],
                ['is_safe' => ['html'], 'needs_environment' => true],
            ),
            new TwigFunction(
                'sg_datatables_render_html',
                [$this, 'datatablesRenderHtml'],
                ['is_safe' => ['html'], 'needs_environment' => true],
            ),
            new TwigFunction(
                'sg_datatables_render_js',
                [$this, 'datatablesRenderJs'],
                ['is_safe' => ['html'], 'needs_environment' => true],
            ),
            new TwigFunction(
                'sg_datatables_render_filter',
                [$this, 'datatablesRenderFilter'],
                ['is_safe' => ['html'], 'needs_environment' => true],
            ),
            new TwigFunction(
                'sg_datatables_render_multiselect_actions',
                [$this, 'datatablesRenderMultiselectActions'],
                ['is_safe' => ['html'], 'needs_environment' => true],
            ),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('sg_datatables_bool_var', [$this, 'boolVar']),
        ];
    }

    //-------------------------------------------------
    // Functions
    //-------------------------------------------------

    /**
     * Renders the template.
     *
     * @param Environment        $twig
     * @param DatatableInterface $datatable
     *
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function datatablesRender(Environment $twig, DatatableInterface $datatable): string
    {
        return $twig->render(
            '@SgDatatables/datatable/datatable.html.twig',
            [
                'sg_datatables_view' => $datatable,
            ],
        );
    }

    /**
     * Renders the html template.
     *
     * @param Environment        $twig
     * @param DatatableInterface $datatable
     *
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function datatablesRenderHtml(Environment $twig, DatatableInterface $datatable): string
    {
        return $twig->render(
            '@SgDatatables/datatable/datatable_html.html.twig',
            [
                'sg_datatables_view' => $datatable,
            ],
        );
    }

    /**
     * Renders the js template.
     *
     * @param Environment        $twig
     * @param DatatableInterface $datatable
     *
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function datatablesRenderJs(Environment $twig, DatatableInterface $datatable): string
    {
        return $twig->render(
            '@SgDatatables/datatable/datatable_js.html.twig',
            [
                'sg_datatables_view' => $datatable,
            ],
        );
    }

    /**
     * Renders a Filter template.
     *
     * @param Environment        $twig
     * @param DatatableInterface $datatable
     * @param ColumnInterface    $column
     * @param string             $position
     *
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function datatablesRenderFilter(Environment $twig, DatatableInterface $datatable, ColumnInterface $column, $position): string
    {
        /** @var FilterInterface $filter */
        $filter       = $this->accessor->getValue($column, 'filter');
        $index        = $this->accessor->getValue($column, 'index');
        $searchColumn = $this->accessor->getValue($filter, 'searchColumn');

        if ($searchColumn !== null) {
            $columns           = $datatable->getColumnBuilder()->getColumnNames();
            $searchColumnIndex = $columns[$searchColumn];
        } else {
            $searchColumnIndex = $index;
        }

        return $twig->render(
            $filter->getTemplate(),
            [
                'column'              => $column,
                'search_column_index' => $searchColumnIndex,
                'datatable_name'      => $datatable->getName(),
                'position'            => $position,
            ],
        );
    }

    /**
     * Renders the MultiselectColumn Actions.
     *
     * @param Environment     $twig
     * @param ColumnInterface $multiselectColumn
     * @param int             $pipeline
     *
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function datatablesRenderMultiselectActions(Environment $twig, ColumnInterface $multiselectColumn, $pipeline): string
    {
        $parameters    = [];
        $values        = [];
        $actions       = $this->accessor->getValue($multiselectColumn, 'actions');
        $domId         = $this->accessor->getValue($multiselectColumn, 'renderActionsToId');
        $datatableName = $this->accessor->getValue($multiselectColumn, 'datatableName');

        /** @var Action $action */
        foreach ($actions as $actionKey => $action) {
            $routeParameters = $action->getRouteParameters();
            if (is_array($routeParameters)) {
                foreach ($routeParameters as $key => $value) {
                    $parameters[$actionKey][$key] = $value;
                }
            } elseif ($routeParameters instanceof Closure) {
                $parameters[$actionKey] = $routeParameters();
            } else {
                $parameters[$actionKey] = [];
            }

            if ($action->isButton()) {
                if ($action->getButtonValue() !== null) {
                    $values[$actionKey] = $action->getButtonValue();

                    if (is_bool($values[$actionKey])) {
                        $values[$actionKey] = (int)$values[$actionKey];
                    }

                    if ($action->isButtonValuePrefix() === true) {
                        $values[$actionKey] = 'sg-datatables-' . $datatableName . '-multiselect-button-' . $actionKey . '-' . $values[$actionKey];
                    }
                } else {
                    $values[$actionKey] = null;
                }
            }
        }

        return $twig->render(
            '@SgDatatables/datatable/multiselect_actions.html.twig',
            [
                'actions'          => $actions,
                'route_parameters' => $parameters,
                'values'           => $values,
                'datatable_name'   => $datatableName,
                'dom_id'           => $domId,
                'pipeline'         => $pipeline,
            ],
        );
    }

    //-------------------------------------------------
    // Filters
    //-------------------------------------------------

    /**
     * Renders: {{ var ? 'true' : 'false' }}.
     *
     * @param $value
     *
     * @return string
     */
    public function boolVar($value): string
    {
        if ($value) {
            return 'true';
        }

        return 'false';
    }
}
