<?php

declare(strict_types=1);

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

/*
    PHP port of ADL SeqRuleset.java
    @author Hendrik Holtmann <holtmann@mac.com>

    This .php file is GPL licensed (see above) but based on
    SeqRuleset.java by ADL Co-Lab, which is licensed as:

    Advanced Distributed Learning Co-Laboratory (ADL Co-Lab) Hub grants you
    ("Licensee") a non-exclusive, royalty free, license to use, modify and
    redistribute this software in source and binary code form, provided that
    i) this copyright notice and license appear on all copies of the software;
    and ii) Licensee does not utilize the software in a manner which is
    disparaging to ADL Co-Lab Hub.

    This software is provided "AS IS," without a warranty of any kind.  ALL
    EXPRESS OR IMPLIED CONDITIONS, REPRESENTATIONS AND WARRANTIES, INCLUDING
    ANY IMPLIED WARRANTY OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE
    OR NON-INFRINGEMENT, ARE HEREBY EXCLUDED.  ADL Co-Lab Hub AND ITS LICENSORS
    SHALL NOT BE LIABLE FOR ANY DAMAGES SUFFERED BY LICENSEE AS A RESULT OF
    USING, MODIFYING OR DISTRIBUTING THE SOFTWARE OR ITS DERIVATIVES.  IN NO
    EVENT WILL ADL Co-Lab Hub OR ITS LICENSORS BE LIABLE FOR ANY LOST REVENUE,
    PROFIT OR DATA, OR FOR DIRECT, INDIRECT, SPECIAL, CONSEQUENTIAL,
    INCIDENTAL OR PUNITIVE DAMAGES, HOWEVER CAUSED AND REGARDLESS OF THE
    THEORY OF LIABILITY, ARISING OUT OF THE USE OF OR INABILITY TO USE
    SOFTWARE, EVEN IF ADL Co-Lab Hub HAS BEEN ADVISED OF THE POSSIBILITY OF SUCH
    DAMAGES.
*/

define("RULE_TYPE_ANY", 1);
define("RULE_TYPE_EXIT", 2);
define("RULE_TYPE_POST", 3);
define("RULE_TYPE_SKIPPED", 4);
define("RULE_TYPE_DISABLED", 5);
define("RULE_TYPE_HIDDEN", 6);
define("RULE_TYPE_FORWARDBLOCK", 7);

class SeqRuleset
{
    public array $mRules;

    public function __construct(array $iRules)
    {
        $this->mRules = $iRules;
    }
}
