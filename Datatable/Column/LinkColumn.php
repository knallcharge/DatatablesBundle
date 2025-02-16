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

use Sg\DatatablesBundle\Datatable\Filter\TextFilter;
use Sg\DatatablesBundle\Datatable\Helper;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

use function call_user_func;
use function count;
use function in_array;
use function is_callable;

/**
 * Class LinkColumn
 */
class LinkColumn extends AbstractColumn
{
    // The LinkColumn is filterable.
    use FilterableTrait;

    /**
     * The route name
     * A required option.
     *
     * @var string
     */
    protected $route;

    /**
     * The route params.
     *
     * @var array|Closure
     */
    protected $routeParams;

    /**
     * The text rendered if data is null.
     *
     * @var string
     */
    protected $empty_value;

    /**
     * The text displayed for each item in the link.
     *
     * @var Closure|null
     */
    protected $text;

    /**
     * The separator for to-many fields.
     *
     * @var string
     */
    protected $separator;

    /**
     * Function to filter the toMany results.
     *
     * @var Closure|null
     */
    protected $filterFunction;

    /**
     * Boolean to indicate if it's an email link.
     */
    protected $email;

    /**
     * @var string
     */
    protected $emptyValue;

    //-------------------------------------------------
    // ColumnInterface
    //-------------------------------------------------

    /**
     * {@inheritdoc}
     */
    public function renderSingleField(array &$row)
    {
        $path = Helper::getDataPropertyPath($this->data);

        if ($this->accessor->isReadable($row, $path)) {
            if ($this->getEmail()) {
                $content = '<a href="mailto:';
                $content .= $this->accessor->getValue($row, $path);
                $content .= '">';

                if (is_callable($this->text)) {
                    $content .= call_user_func($this->text, $row);
                } else {
                    $content .= $this->accessor->getValue($row, $path);
                }

                $content .= '</a>';
            } else {
                if (is_callable($this->routeParams)) {
                    $renderRouteParams = call_user_func($this->routeParams, $row);
                } else {
                    $renderRouteParams = $this->routeParams;
                }

                if (in_array(null, $renderRouteParams, true)) {
                    $content = $this->getEmptyValue();
                } else {
                    $content = '<a href="';
                    $content .= $this->router->generate($this->getRoute(), $renderRouteParams);
                    $content .= '">';

                    if (is_callable($this->text)) {
                        $content .= call_user_func($this->text, $row);
                    } else {
                        $content .= $this->accessor->getValue($row, $path);
                    }

                    $content .= '</a>';
                }
            }
            $this->accessor->setValue($row, $path, $content);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @throws LoaderError|RuntimeError|SyntaxError
     */
    public function renderToMany(array &$row)
    {
        $value   = null;
        $path    = Helper::getDataPropertyPath($this->data, $value);
        $content = '';

        if ($this->accessor->isReadable($row, $path)) {
            $entries = $this->accessor->getValue($row, $path);

            if ($this->isEditableContentRequired($row)) {
                // e.g. comments[ ].createdBy.username
                //     => $path = [comments]
                //     => $value = [createdBy][username]

                if (count($entries) > 0) {
                    foreach ($entries as $key => $entry) {
                        $currentPath = $path . '[' . $key . ']' . $value;

                        $content = $this->renderTemplate($this->accessor->getValue($row, $currentPath));

                        $this->accessor->setValue($row, $currentPath, $content);
                    }
                }
                // no placeholder - leave this blank
            } else {
                if ($this->getFilterFunction() !== null) {
                    $entries = array_values(array_filter($entries, $this->getFilterFunction()));
                }

                if (count($entries) > 0) {
                    for ($i = 0; $i < count($entries); ++$i) {
                        if (is_callable($this->routeParams)) {
                            $renderRouteParams = call_user_func($this->routeParams, $entries[$i]);
                        } else {
                            $renderRouteParams = $this->routeParams;
                        }

                        $content .= '<a href="';
                        $content .= $this->router->generate($this->getRoute(), $renderRouteParams);
                        $content .= '">';

                        if (is_callable($this->text)) {
                            $content .= call_user_func($this->text, $entries[$i]);
                        } else {
                            $content .= $this->text;
                        }

                        $content .= '</a>';

                        if ($i < count($entries) - 1) {
                            $content .= $this->separator;
                        }
                    }

                    $this->accessor->setValue($row, $path, $content);
                } else {
                    $this->accessor->setValue($row, $path, $this->getEmptyValue());
                }
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getCellContentTemplate()
    {
        return '@SgDatatables/render/link.html.twig';
    }

    /**
     * {@inheritdoc}
     */
    public function getColumnType()
    {
        return parent::ACTION_COLUMN;
    }

    //-------------------------------------------------
    // Options
    //-------------------------------------------------

    /**
     * @return $this
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
                                   'filter'         => [TextFilter::class, []],
                                   'route'          => '',
                                   'route_params'   => [],
                                   'empty_value'    => '',
                                   'text'           => null,
                                   'separator'      => '',
                                   'filterFunction' => null,
                                   'email'          => false,
                               ]);

        $resolver->setAllowedTypes('filter', 'array');
        $resolver->setAllowedTypes('route', 'string');
        $resolver->setAllowedTypes('route_params', ['array', 'Closure']);
        $resolver->setAllowedTypes('empty_value', ['string']);
        $resolver->setAllowedTypes('text', ['Closure', 'null']);
        $resolver->setAllowedTypes('separator', ['string']);
        $resolver->setAllowedTypes('filterFunction', ['null', 'Closure']);
        $resolver->setAllowedTypes('email', ['bool']);

        return $this;
    }

    /**
     * @return string
     */
    public function getRoute()
    {
        return $this->route;
    }

    /**
     * @param string $route
     *
     * @return $this
     */
    public function setRoute($route)
    {
        $this->route = $route;

        return $this;
    }

    /**
     * Get route params.
     *
     * @return array|Closure
     */
    public function getRouteParams()
    {
        return $this->routeParams;
    }

    /**
     * Set route params.
     *
     * @param array|Closure $routeParams
     *
     * @return $this
     */
    public function setRouteParams($routeParams)
    {
        $this->routeParams = $routeParams;

        return $this;
    }

    /**
     * Get empty value.
     *
     * @return string
     */
    public function getEmptyValue()
    {
        return $this->emptyValue;
    }

    /**
     * Set empty value.
     *
     * @param array|Closure $emptyValue
     *
     * @return $this
     */
    public function setEmptyValue($emptyValue)
    {
        $this->emptyValue = $emptyValue;

        return $this;
    }

    /**
     * @return Closure|null
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * @param Closure|null $text
     *
     * @return $this
     */
    public function setText($text)
    {
        $this->text = $text;

        return $this;
    }

    /**
     * @return string
     */
    public function getSeparator()
    {
        return $this->separator;
    }

    /**
     * @param string $separator
     *
     * @return $this
     */
    public function setSeparator($separator)
    {
        $this->separator = $separator;

        return $this;
    }

    /**
     * Get filter function.
     *
     * @return Closure|null
     */
    public function getFilterFunction()
    {
        return $this->filterFunction;
    }

    /**
     * Set filter function.
     *
     * @param string $filterFunction
     *
     * @return $this
     */
    public function setFilterFunction($filterFunction)
    {
        $this->filterFunction = $filterFunction;

        return $this;
    }

    /**
     * Get email boolean.
     *
     * @return bool
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Set email boolean.
     *
     * @param bool $email
     *
     * @return $this
     */
    public function setEmail($email)
    {
        $this->email = $email;

        return $this;
    }

    //-------------------------------------------------
    // Helper
    //-------------------------------------------------

    /**
     * Render template.
     *
     * @param string|null $data
     *
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    private function renderTemplate($data)
    {
        return $this->twig->render(
            $this->getCellContentTemplate(),
            [
                'data' => $data,
            ],
        );
    }
}
