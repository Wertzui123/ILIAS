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
 * Class ilTestPasswordProtectionGUITest
 * @author Marvin Beym <mbeym@databay.de>
 */
class ilTestPasswordProtectionGUITest extends ilTestBaseTestCase
{
    private ilTestPasswordProtectionGUI $testObj;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testObj = new ilTestPasswordProtectionGUI(
            $this->createMock(ilCtrl::class),
            $this->createMock(ilGlobalPageTemplate::class),
            $this->createMock(ilLanguage::class),
            $this->createMock(ilTestPlayerAbstractGUI::class),
            $this->createMock(ilTestPasswordChecker::class),
            $this->createMock(ILIAS\Test\InternalRequestService::class),
            $this->createMock(ILIAS\GlobalScreen\Services::class)
        );
    }

    public function test_instantiateObject_shouldReturnInstance(): void
    {
        $this->assertInstanceOf(ilTestPasswordProtectionGUI::class, $this->testObj);
    }
}
