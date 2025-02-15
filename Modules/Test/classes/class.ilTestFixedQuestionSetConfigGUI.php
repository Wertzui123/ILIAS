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
 * GUI class that manages the question set configuration for tests
 * requireing a once defined question set
 *
 * @author		Björn Heyser <bheyser@databay.de>
 * @version		$Id$
 *
 * @package		Modules/Test
 *
 * @ilCtrl_Calls ilTestFixedQuestionSetConfigGUI: ilTestExpressPageObjectGUI
 * @ilCtrl_Calls ilTestFixedQuestionSetConfigGUI: ilPageEditorGUI
 * @ilCtrl_Calls ilTestFixedQuestionSetConfigGUI: ilAssQuestionPageGUI
 */
class ilTestFixedQuestionSetConfigGUI
{
    /**
     * @var ilCtrl
     */
    public $ctrl = null;

    /**
     * @var ilAccess
     */
    public $access = null;

    /**
     * @var ilTabsGUI
     */
    public $tabs = null;

    /**
     * @var ilLanguage
     */
    public $lng = null;

    /**
     * @var ilTemplate
     */
    public $tpl = null;

    /**
     * @var ilDBInterface
     */
    public $db = null;

    /**
     * @var ilTree
     */
    public $tree = null;

    public ?ilComponentRepository $component_repository;

    /**
     * @var ilObjectDefinition
     */
    public $objDefinition = null;

    /**
     * @var ilObjTest
     */
    public $testOBJ = null;

    /**
     * ilTestFixedQuestionSetConfigGUI constructor.
     */
    public function __construct()
    {
    }
    /**
     * Control Flow Entrance
     */
    public function executeCommand()
    {
    }
}
