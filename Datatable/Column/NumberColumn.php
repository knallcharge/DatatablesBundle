<?php /** @noinspection DuplicatedCode */
/** @noinspection DuplicatedCode */

/*
 * This file is part of the SgDatatablesBundle package.
 *
 * (c) stwe <https://github.com/stwe/DatatablesBundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sg\DatatablesBundle\Datatable\Column;

use NumberFormatter;
use Sg\DatatablesBundle\Datatable\Helper;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

use function count;
use function is_float;

/**
 * Class NumberColumn
 */
class NumberColumn extends Column
{
    /**
     * A NumberFormatter instance.
     * A required option.
     *
     * @var NumberFormatter
     */
    protected $formatter;

    /**
     * Use NumberFormatter::formatCurrency instead NumberFormatter::format to format the value.
     * Default: false.
     *
     * @var bool
     */
    protected $useFormatCurrency;

    /**
     * The currency code.
     * Default: null => NumberFormatter::INTL_CURRENCY_SYMBOL is used.
     *
     * @var string|null
     */
    protected $currency;

    //-------------------------------------------------
    // ColumnInterface
    //-------------------------------------------------

    /**
     * {@inheritdoc}
     *
     * @throws LoaderError|RuntimeError|SyntaxError
     */
    public function renderSingleField(array &$row)
    {
        $path = Helper::getDataPropertyPath($this->data);

        if ($this->accessor->isReadable($row, $path)) {
            if ($this->isEditableContentRequired($row) === true) {
                $content = $this->renderTemplate($this->accessor->getValue($row, $path), $row[$this->editable->getPk()]);
            } else {
                $content = $this->renderTemplate($this->accessor->getValue($row, $path));
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
        $value = null;
        $path  = Helper::getDataPropertyPath($this->data, $value);

        $entries = $this->accessor->getValue($row, $path);

        if ($this->accessor->isReadable($row, $path) && count($entries) > 0) {
            foreach ($entries as $key => $entry) {
                $currentPath       = $path . '[' . $key . ']' . $value;
                $currentObjectPath = Helper::getPropertyPathObjectNotation($path, $key, $value);

                if ($this->isEditableContentRequired($row) === true) {
                    $content = $this->renderTemplate(
                        $this->accessor->getValue($row, $currentPath),
                        $row[$this->editable->getPk()],
                        $currentObjectPath,
                    );
                } else {
                    $content = $this->renderTemplate($this->accessor->getValue($row, $currentPath));
                }

                $this->accessor->setValue($row, $currentPath, $content);
            }
        }

        return $this;
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

        $resolver->setRequired('formatter');

        $resolver->setDefaults(
            [
                'use_format_currency' => false,
                'currency'            => null,
            ],
        );

        $resolver->setAllowedTypes('formatter', ['object']);
        $resolver->setAllowedTypes('use_format_currency', ['bool']);
        $resolver->setAllowedTypes('currency', ['null', 'string']);

        $resolver->setAllowedValues('formatter', function ($formatter) {
            if (!$formatter instanceof NumberFormatter) {
                return false;
            }

            return true;
        });

        return $this;
    }

    //-------------------------------------------------
    // Getters && Setters
    //-------------------------------------------------

    /**
     * @return NumberFormatter
     */
    public function getFormatter()
    {
        return $this->formatter;
    }

    /**
     * @return $this
     */
    public function setFormatter(NumberFormatter $formatter)
    {
        $this->formatter = $formatter;

        return $this;
    }

    /**
     * @return bool
     */
    public function isUseFormatCurrency()
    {
        return $this->useFormatCurrency;
    }

    /**
     * @param bool $useFormatCurrency
     *
     * @return $this
     */
    public function setUseFormatCurrency($useFormatCurrency)
    {
        $this->useFormatCurrency = $useFormatCurrency;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @param string|null $currency
     *
     * @return $this
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;

        return $this;
    }

    //-------------------------------------------------
    // Helper
    //-------------------------------------------------

    /**
     * Render template.
     *
     * @param string|float|null $data
     * @param null              $pk
     * @param null              $path
     *
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    private function renderTemplate($data, $pk = null, $path = null)
    {
        if ($this->useFormatCurrency === true) {
            if (is_float($data) === false) {
                $data = (float)$data;
            }

            if ($this->currency === null) {
                $this->currency = $this->formatter->getSymbol(NumberFormatter::INTL_CURRENCY_SYMBOL);
            }

            $data = $this->formatter->formatCurrency($data, $this->currency);
        } else {
            // expected number (int or float), other values will be converted to a numeric value
            $data = $this->formatter->format($data);
        }

        return $this->twig->render(
            $this->getCellContentTemplate(),
            [
                'data'                           => $data,
                'column_class_editable_selector' => $this->getColumnClassEditableSelector(),
                'pk'                             => $pk,
                'path'                           => $path,
            ],
        );
    }
}
