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

/**
 * Important pages table
 *
 * @author Alexander Killing <killing@leifos.de>
 */
class ilImportantPagesTableGUI extends ilTable2GUI
{
    protected \ILIAS\Wiki\Navigation\ImportantPageManager $imp_page_manager;
    protected \ILIAS\Wiki\InternalGUIService $gui;
    protected \ILIAS\Wiki\InternalDomainService $domain;
    protected ilAccessHandler $access;
    protected ilWikiPageTemplate $templates;

    public function __construct(
        object $a_parent_obj,
        string $a_parent_cmd
    ) {
        global $DIC;

        $service = $DIC->wiki()->internal();
        $this->domain = $service->domain();
        $this->gui = $service->gui();

        $this->ctrl = $this->gui->ctrl();
        $this->lng = $this->domain->lng();
        $this->access = $this->domain->access();
        $this->imp_page_manager = $this->domain->importantPage($a_parent_obj->getRefId());

        $this->templates = new ilWikiPageTemplate($a_parent_obj->getObject()->getId());
        parent::__construct($a_parent_obj, $a_parent_cmd);
        $data = array_merge(
            [array("page_id" => 0)],
            $this->imp_page_manager->getListAsArray()
        );
        $this->setData($data);
        $this->setTitle($this->lng->txt(""));
        $this->setLimit(9999);

        $this->addColumn("", "", "1", true);
        $this->addColumn($this->lng->txt("wiki_ordering"), "order");
        $this->addColumn($this->lng->txt("wiki_indentation"));
        $this->addColumn($this->lng->txt("wiki_page"));
        $this->addColumn($this->lng->txt("wiki_purpose"));

        $this->setEnableHeader(true);
        $this->setFormAction($this->ctrl->getFormAction($a_parent_obj));
        $this->setRowTemplate("tpl.imp_pages_row.html", "Modules/Wiki");
        //$this->disable("footer");
        $this->setEnableTitle(true);

        $this->addMultiCommand("confirmRemoveImportantPages", $this->lng->txt("remove"));
        $this->addMultiCommand("setAsStartPage", $this->lng->txt("wiki_set_as_start_page"));
        $this->addCommandButton("saveOrderingAndIndent", $this->lng->txt("wiki_save_ordering_and_indent"));
    }

    protected function fillRow(array $a_set): void
    {
        $lng = $this->lng;

        if ($a_set["page_id"] > 0) {
            $this->tpl->setCurrentBlock("cb");
            $this->tpl->setVariable("PAGE_ID", $a_set["page_id"]);
            $this->tpl->parseCurrentBlock();

            $this->tpl->setCurrentBlock("ord");
            $this->tpl->setVariable("PAGE_ID_ORD", $a_set["page_id"]);
            $this->tpl->setVariable("VAL_ORD", $a_set["ord"]);
            $this->tpl->parseCurrentBlock();

            $this->tpl->setVariable(
                "PAGE_TITLE",
                ilWikiPage::lookupTitle($a_set["page_id"])
            );
            $this->tpl->setVariable(
                "SEL_INDENT",
                ilLegacyFormElementsUtil::formSelect(
                    $a_set["indent"],
                    "indent[" . $a_set["page_id"] . "]",
                    [0 => "0", 1 => "1", 2 => "2"],
                    false,
                    true
                )
            );

            if ($this->templates->isPageTemplate((int) $a_set["page_id"])) {
                $this->tpl->setVariable(
                    "PURPOSE",
                    $lng->txt("wiki_page_template")
                );
            }
        } else {
            $this->tpl->setVariable(
                "PAGE_TITLE",
                ($this->getParentObject()->getObject()->getStartPage())
            );

            $this->tpl->setVariable(
                "PURPOSE",
                $lng->txt("wiki_start_page")
            );
        }
    }
}
