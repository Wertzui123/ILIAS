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
 * Class ilMyTestResultsGUI
 *
 * @author    Björn Heyser <info@bjoernheyser.de>
 * @version    $Id$
 *
 * @package    Modules/Test
 *
 * @ilCtrl_Calls ilMyTestResultsGUI: ilTestEvaluationGUI
 * @ilCtrl_Calls ilMyTestResultsGUI: ilAssQuestionPageGUI
 * @ilCtrl_Calls ilMyTestResultsGUI: ilAssSpecFeedbackPageGUI
 * @ilCtrl_Calls ilMyTestResultsGUI: ilAssGenFeedbackPageGUI
 */
class ilMyTestResultsGUI
{
    public const EVALGUI_CMD_SHOW_PASS_OVERVIEW = 'outUserResultsOverview';

    protected ?ilObjTest $testObj = null;
    protected ?ilTestAccess $testAccess = null;
    protected ?ilTestSession $testSession = null;
    protected ?ilTestObjectiveOrientedContainer $objectiveParent = null;

    public function getTestObj(): ?ilObjTest
    {
        return $this->testObj;
    }

    public function setTestObj(ilObjTest $testObj): void
    {
        $this->testObj = $testObj;
    }

    public function getTestAccess(): ?ilTestAccess
    {
        return $this->testAccess;
    }

    public function setTestAccess(ilTestAccess $testAccess): void
    {
        $this->testAccess = $testAccess;
    }

    public function getTestSession(): ?ilTestSession
    {
        return $this->testSession;
    }

    public function setTestSession(ilTestSession $testSession): void
    {
        $this->testSession = $testSession;
    }

    public function getObjectiveParent(): ?ilTestObjectiveOrientedContainer
    {
        return $this->objectiveParent;
    }

    public function setObjectiveParent(ilTestObjectiveOrientedContainer $objectiveParent): void
    {
        $this->objectiveParent = $objectiveParent;
    }

    public function executeCommand(): void
    {
        /* @var ILIAS\DI\Container $DIC */
        global $DIC;

        if (!$DIC->ctrl()->getCmd()) {
            $DIC->ctrl()->setCmd(self::EVALGUI_CMD_SHOW_PASS_OVERVIEW);
        }

        switch ($DIC->ctrl()->getNextClass()) {
            case "iltestevaluationgui":
                $gui = new ilTestEvaluationGUI($this->getTestObj());
                $gui->setObjectiveOrientedContainer($this->getObjectiveParent());
                $gui->setTestAccess($this->getTestAccess());
                $DIC->ctrl()->forwardCommand($gui);
                break;

            case 'ilassquestionpagegui':
                $forwarder = new ilAssQuestionPageCommandForwarder();
                $forwarder->setTestObj($this->getTestObj());
                $forwarder->forward();
                break;
        }
    }
}
