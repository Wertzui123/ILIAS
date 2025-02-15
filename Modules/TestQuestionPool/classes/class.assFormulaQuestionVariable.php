<?php
/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

/**
 * Formula Question Variable
 * @author        Helmut Schottmüller <helmut.schottmueller@mac.com>
 * @version       $Id: class.assFormulaQuestionVariable.php 465 2009-06-29 08:27:36Z hschottm $
 * @ingroup       ModulesTestQuestionPool
 * */
class assFormulaQuestionVariable
{
    private $value = null;
    private float $range_min;
    private float $range_max;

    public function __construct(
        private string $variable,
        private string $range_min_txt,
        private string $range_max_txt,
        private ?assFormulaQuestionUnit $unit = null,
        private int $precision = 0,
        private int $intprecision = 1
    ) {
        $this->setRangeMin($range_min_txt);
        $this->setRangeMax($range_max_txt);
    }

    public function getRandomValue()
    {
        if ($this->getPrecision() === 0
            && !$this->isIntPrecisionValid(
                $this->getIntprecision(),
                $this->getRangeMin(),
                $this->getRangeMax()
            )
        ) {
            global $DIC;
            $lng = $DIC['lng'];
            $DIC->ui()->mainTemplate()->setOnScreenMessage(
                "failure",
                $lng->txt('err_divider_too_big')
            );
        }

        $mul = ilMath::_pow(10, $this->getPrecision());
        $r1 = round((float)ilMath::_mul($this->getRangeMin(), $mul));
        $r2 = round((float) ilMath::_mul($this->getRangeMax(), $mul));
        $calcval = $this->getRangeMin() - 1;
        //test

        $roundedRangeMIN = round($this->getRangeMin(), $this->getPrecision());
        $roundedRangeMAX = round($this->getRangeMax(), $this->getPrecision());
        while ($calcval < $roundedRangeMIN || $calcval > $roundedRangeMAX) {
            //		while($calcval < $this->getRangeMin() || $calcval > $this->getRangeMax())
            $rnd = mt_rand((int) $r1, (int) $r2);
            $calcval = ilMath::_div($rnd, $mul, $this->getPrecision());
            if (($this->getPrecision() == 0) && ($this->getIntprecision() != 0)) {
                if ($this->getIntprecision() > 0) {
                    $modulo = $calcval % $this->getIntprecision();
                    if ($modulo != 0) {
                        if ($modulo < ilMath::_div($this->getIntprecision(), 2)) {
                            $calcval = ilMath::_sub($calcval, $modulo, $this->getPrecision());
                        } else {
                            $calcval = ilMath::_add($calcval, ilMath::_sub($this->getIntprecision(), $modulo, $this->getPrecision()), $this->getPrecision());
                        }
                    }
                }
            }
        }
        return $calcval;
    }

    public function setRandomValue(): void
    {
        $this->setValue($this->getRandomValue());
    }

    public function isIntPrecisionValid(?int $int_precision, float $min_range, float $max_range)
    {
        if ($int_precision === null) {
            return false;
        }
        $min_abs = abs($min_range);
        $max_abs = abs($max_range);
        $bigger_abs = $max_abs > $min_abs ? $max_abs : $min_abs;
        if ($int_precision > $bigger_abs) {
            return false;
        }
        return true;
    }

    /************************************
     * Getter and Setter
     ************************************/

    public function setValue($value): void
    {
        $this->value = $value;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function getBaseValue()
    {
        if (!is_object($this->getUnit())) {
            return $this->value;
        } else {
            return ilMath::_mul($this->value, $this->getUnit()->getFactor());
        }
    }

    public function setPrecision(int $precision): void
    {
        $this->precision = $precision;
    }

    public function getPrecision(): int
    {
        return $this->precision;
    }

    public function setVariable($variable): void
    {
        $this->variable = $variable;
    }

    public function getVariable(): string
    {
        return $this->variable;
    }

    public function setRangeMin(string $range_min): void
    {
        $math = new EvalMath();
        $math->suppress_errors = true;
        $this->range_min = (float) $math->evaluate($range_min);
    }

    public function getRangeMin(): float
    {
        return $this->range_min;
    }

    public function setRangeMax(string $range_max): void
    {
        $math = new EvalMath();
        $math->suppress_errors = true;
        $this->range_max = (float) $math->evaluate($range_max);
    }

    public function getRangeMax(): float
    {
        return $this->range_max;
    }

    public function setUnit(?assFormulaQuestionUnit $unit): void
    {
        $this->unit = $unit;
    }

    public function getUnit(): ?assFormulaQuestionUnit
    {
        return $this->unit;
    }

    public function setIntprecision($intprecision): void
    {
        $this->intprecision = $intprecision;
    }

    public function getIntprecision(): int
    {
        return $this->intprecision;
    }

    public function setRangeMaxTxt(string $range_max_txt): void
    {
        $this->range_max_txt = $range_max_txt;
    }

    public function getRangeMaxTxt(): string
    {
        return $this->range_max_txt;
    }

    public function setRangeMinTxt(string $range_min_txt): void
    {
        $this->range_min_txt = $range_min_txt;
    }

    public function getRangeMinTxt(): string
    {
        return $this->range_min_txt;
    }
}
