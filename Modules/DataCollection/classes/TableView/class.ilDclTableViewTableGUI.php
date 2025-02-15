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

class ilDclTableViewTableGUI extends ilTable2GUI
{
    protected ilDclTable $table;

    protected \ILIAS\UI\Renderer $renderer;
    protected \ILIAS\UI\Factory $ui_factory;

    public function __construct(object $a_parent_obj, string $a_parent_cmd, ilDclTable $table, int $ref_id)
    {
        global $DIC;

        $this->ui_factory = $DIC->ui()->factory();
        $this->renderer = $DIC->ui()->renderer();

        parent::__construct($a_parent_obj, $a_parent_cmd);

        $this->parent_obj = $a_parent_obj;
        $this->table = $table;
        $this->ctrl = $DIC->ctrl();
        $this->lng = $DIC->language();

        $this->setExternalSegmentation(true);
        $this->setExternalSorting(true);

        if ($this->parent_obj instanceof ilDclTableViewGUI) {
            $this->ctrl->setParameterByClass(ilDclTableViewGUI::class, 'table_id', $table->getId());
            $this->setFormAction($this->ctrl->getFormActionByClass(ilDclTableViewGUI::class));
            $this->addMultiCommand('confirmDeleteTableviews', $this->lng->txt('dcl_delete_views'));
            $this->addCommandButton('saveTableViewOrder', $this->lng->txt('dcl_save_order'));

            $this->setFormAction($this->ctrl->getFormAction($a_parent_obj));
            $this->setFormName('tableview_list');

            $this->addColumn('', '', '1', true);
            $this->addColumn($this->lng->txt('dcl_order'), '', '30px');

            $this->setRowTemplate('tpl.tableview_list_row.html', 'Modules/DataCollection');
            $this->setData($this->table->getTableViews());
        } elseif ($this->parent_obj instanceof ilDclDetailedViewGUI) {
            $this->setRowTemplate('tpl.detailview_list_row.html', 'Modules/DataCollection');
            $this->setData($this->table->getVisibleTableViews($ref_id, true));
        }

        $this->addColumn($this->lng->txt('title'), '', 'auto');
        $this->addColumn($this->lng->txt('description'), '', 'auto');
        $this->addColumn($this->lng->txt('actions'), '', '30px');

        $this->setTopCommands(true);
        $this->setEnableHeader(true);
        $this->setShowRowsSelector(false);
        $this->setShowTemplates(false);
        $this->setEnableHeader(true);
        $this->setEnableTitle(true);
        $this->setDefaultOrderDirection('asc');
        $this->setLimit();

        $this->setId('dcl_tableviews');
        $this->setTitle($this->table->getTitle());
        $this->setStyle('table', $this->getStyle('table') . ' ' . 'dcl_record_list');
    }

    /**
     * Get HTML
     */
    public function getHTML(): string
    {
        if ($this->getExportMode()) {
            $this->exportData($this->getExportMode(), true);
        }

        $this->prepareOutput();

        if (is_object($this->getParentObject()) && $this->getId() == "") {
            $this->ctrl->saveParameter($this->getParentObject(), $this->getNavParameter());
        }

        if (!$this->getPrintMode()) {
            // set form action
            if ($this->form_action != "" && $this->getOpenFormTag()) {
                $hash = "";

                if ($this->form_multipart) {
                    $this->tpl->touchBlock("form_multipart_bl");
                }

                if ($this->getPreventDoubleSubmission()) {
                    $this->tpl->touchBlock("pdfs");
                }

                $this->tpl->setCurrentBlock("tbl_form_header");
                $this->tpl->setVariable("FORMACTION", $this->getFormAction() . $hash);
                $this->tpl->setVariable("FORMNAME", $this->getFormName());
                $this->tpl->parseCurrentBlock();
            }

            if ($this->form_action != "" && $this->getCloseFormTag()) {
                $this->tpl->touchBlock("tbl_form_footer");
            }
        }

        if (!$this->enabled['content']) {
            return $this->render();
        }

        if (!$this->getExternalSegmentation()) {
            $this->setMaxCount(count($this->row_data));
        }

        $this->determineOffsetAndOrder();

        $this->setFooter("tblfooter", $this->lng->txt("previous"), $this->lng->txt("next"));

        $data = $this->getData();
        if ($this->dataExists()) {
            // sort
            if (!$this->getExternalSorting() && $this->enabled["sort"]) {
                $data = ilArrayUtil::sortArray(
                    $data,
                    $this->getOrderField(),
                    $this->getOrderDirection(),
                    $this->numericOrdering($this->getOrderField())
                );
            }

            // slice
            if (!$this->getExternalSegmentation()) {
                $data = array_slice($data, $this->getOffset(), $this->getLimit());
            }
        }

        // fill rows
        if ($this->dataExists()) {
            if ($this->getPrintMode()) {
                ilDatePresentation::setUseRelativeDates(false);
            }

            $this->tpl->addBlockFile(
                "TBL_CONTENT",
                "tbl_content",
                $this->row_template,
                $this->row_template_dir
            );

            foreach ($data as $set) {
                $this->tpl->setCurrentBlock("tbl_content");
                $this->css_row = ($this->css_row !== "tblrow1")
                    ? "tblrow1"
                    : "tblrow2";
                $this->tpl->setVariable("CSS_ROW", $this->css_row);

                $this->fillRowFromObject($set);
                $this->tpl->setCurrentBlock("tbl_content");
                $this->tpl->parseCurrentBlock();
            }
        } else {
            // add standard no items text (please tell me, if it messes something up, alex, 29.8.2008)
            $no_items_text = (trim($this->getNoEntriesText()) != '')
                ? $this->getNoEntriesText()
                : $this->lng->txt("no_items");

            $this->css_row = ($this->css_row !== "tblrow1")
                ? "tblrow1"
                : "tblrow2";

            $this->tpl->setCurrentBlock("tbl_no_entries");
            $this->tpl->setVariable('TBL_NO_ENTRY_CSS_ROW', $this->css_row);
            $this->tpl->setVariable('TBL_NO_ENTRY_COLUMN_COUNT', $this->column_count);
            $this->tpl->setVariable('TBL_NO_ENTRY_TEXT', trim($no_items_text));
            $this->tpl->parseCurrentBlock();
        }

        if (!$this->getPrintMode()) {
            $this->fillFooter();

            $this->fillHiddenRow();

            $this->fillActionRow();

            $this->storeNavParameter();
        }

        return $this->render();
    }

    /**
     * @param ilDclTableView $a_set
     */
    public function fillRowFromObject(ilDclTableView $a_set): void
    {
        if ($this->parent_obj instanceof ilDclTableViewGUI) {
            $this->tpl->setVariable("ID", $a_set->getId());
            $this->tpl->setVariable("ORDER_NAME", "order[{$a_set->getId()}]");
            $this->tpl->setVariable("ORDER_VALUE", $a_set->getOrder());
        }
        $this->tpl->setVariable("TITLE", $a_set->getTitle());
        $this->ctrl->setParameterByClass(ilDclTableViewEditGUI::class, 'tableview_id', $a_set->getId());
        $this->tpl->setVariable("TITLE_LINK", $this->ctrl->getLinkTargetByClass('ildcltablevieweditgui'));
        $this->tpl->setVariable("DESCRIPTION", $a_set->getDescription());
        $this->tpl->setVariable('ACTIONS', $this->buildAction($a_set->getId()));
    }

    /**
     * build either actions menu or view button
     */
    protected function buildAction(int $id): string
    {
        if ($this->parent_obj instanceof ilDclTableViewGUI) {
            $dropdown_items = [];

            $this->ctrl->setParameterByClass(ilDclTableViewGUI::class, 'tableview_id', $id);
            $this->ctrl->setParameterByClass(ilDclDetailedViewDefinitionGUI::class, 'tableview_id', $id);

            $dropdown_items[] = $this->ui_factory->link()->standard(
                $this->lng->txt('edit'),
                $this->ctrl->getLinkTargetByClass(ilDclTableViewEditGUI::class, 'editGeneralSettings')
            );
            $dropdown_items[] = $this->ui_factory->link()->standard(
                $this->lng->txt('copy'),
                $this->ctrl->getLinkTargetByClass(ilDclTableViewEditGUI::class, 'copy')
            );
            $dropdown_items[] = $this->ui_factory->link()->standard(
                $this->lng->txt('delete'),
                $this->ctrl->getLinkTargetByClass(ilDclTableViewEditGUI::class, 'confirmDelete')
            );
            $dropdown = $this->ui_factory->dropdown()->standard($dropdown_items)->withLabel($this->lng->txt('actions'));

            return $this->renderer->render($dropdown);
        } elseif ($this->parent_obj instanceof ilDclDetailedViewGUI) {
            $this->ctrl->setParameterByClass(ilDclDetailedViewGUI::class, 'tableview_id', $id);
            $this->ctrl->saveParameterByClass(ilDclDetailedViewGUI::class, 'record_id');
            $link = $this->ui_factory->link()->standard(
                $this->lng->txt('view'),
                $this->ctrl->getLinkTargetByClass(ilDclDetailedViewGUI::class, 'renderRecord')
            );
            return $this->renderer->render($link);
        }
        return "";
    }
}
