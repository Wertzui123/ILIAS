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

require_once './Modules/Test/classes/inc.AssessmentConstants.php';

/**
* Class for true/false or yes/no answers
*
* ASS_AnswerImagemap is a class for true/false or yes/no answers used for example in multiple choice tests.
*
* @author		Helmut Schottmüller <helmut.schottmueller@mac.com>
* @ingroup ModulesTestQuestionPool
*/
class ASS_AnswerImagemap extends ASS_AnswerBinaryState
{
    /**
     * @var mixed|string
     */
    protected $area;
    public string $coords;

    /**
     * The points given to the answer when the answer is not checked
     *
     * The points given to the answer when the answer is not checked
     *
     * @var double
     */
    protected $points_unchecked = 0.0;

    /**
    * ASS_AnswerImagemap constructor
    *
    * The constructor takes possible arguments an creates an instance of the ASS_AnswerImagemap object.
    *
    * @param string $answertext A string defining the answer text
    * @param double $points The number of points given for the selected answer
    * @param boolean $correctness A boolean value indicating the correctness of the answer
    * @param integer $order A nonnegative value representing a possible display or sort order
    * @access public
    */
    public function __construct(
        $answertext = "",
        $points = 0.0,
        $order = 0,
        $coords = "",
        $area = "",
        $id = -1,
        $points_unchecked = 0
    ) {
        parent::__construct($answertext, $points, 1, $id);
        $this->coords = $coords;
        $this->area = $area;
        $this->points_unchecked = $points_unchecked;
    }


    /**
    * Gets the coordinates of an image map
    *
    * @return string coords
    * @access public
    * @see $coords
    */
    public function getCoords(): string
    {
        $this->coords = preg_replace("/\s/", "", $this->coords);
        return $this->coords;
    }


    /**
    * Sets the coordinates of an image map
    * @param string $coords
    * @access public
    * @see $coords
    */
    public function setCoords(string $coords = ""): void
    {
        $coords = preg_replace("/\s/", "", $coords);
        $this->coords = $coords;
    }

    /**
    * Gets the area of an image map
    *
    * @return string area
    * @access public
    * @see $area
    */
    public function getArea(): string
    {
        return $this->area;
    }


    public function setArea(string $area = ""): void
    {
        $this->area = $area;
    }

    /**
     * Returns the points for an unchecked answer
     * Returns the points for an unchecked answer
     * @return double The points for an unchecked answer
     * @access public
     * @see    $points_unchecked
     */
    public function getPointsUnchecked()
    {
        return $this->points_unchecked;
    }

    /**
     * Sets the points for an unchecked answer
     *
     * @param int|string|null $points_unchecked The points for an unchecked answer
     * @see $points_unchecked
     *
     * @TODO Analyze usage and see if we can get rid of "magic nullification" here.
     */
    public function setPointsUnchecked($points_unchecked): void
    {
        $new_points = str_replace(",", ".", $points_unchecked);

        if ($this->checkPoints($new_points)) {
            $this->points_unchecked = $new_points;
        } else {
            $this->points_unchecked = 0.0;
        }
    }
}
