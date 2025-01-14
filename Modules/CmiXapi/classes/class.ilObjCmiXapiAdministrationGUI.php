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

/**
 * Class ilObjCmiXapiAdministrationGUI
 *
 * @author      Uwe Kohnle <kohnle@internetlehrer-gmbh.de>
 * @author      Björn Heyser <info@bjoernheyser.de>
 * @author      Stefan Schneider <info@eqsoft.de>
 *
 * @package     Module/CmiXapi
 *
 * @ilCtrl_Calls ilObjCmiXapiAdministrationGUI: ilPermissionGUI
 */
class ilObjCmiXapiAdministrationGUI extends ilObjectGUI
{
    public const TAB_ID_LRS_TYPES = 'tab_lrs_types';
    public const TAB_ID_PERMISSIONS = 'perm_settings';

    public const CMD_SHOW_LRS_TYPES_LIST = 'showLrsTypesList';
    public const CMD_SHOW_LRS_TYPE_FORM = 'showLrsTypeForm';
    public const CMD_SAVE_LRS_TYPE_FORM = 'saveLrsTypeForm';

    public const DEFAULT_CMD = self::CMD_SHOW_LRS_TYPES_LIST;

    public function getAdminTabs(): void
    {
        // lrs types tab

        $this->tabs_gui->addTab(
            self::TAB_ID_LRS_TYPES,
            $this->lng->txt(self::TAB_ID_LRS_TYPES),
            $this->ctrl->getLinkTargetByClass(self::class)
        );

        // permissions tab
        if ($this->rbac_system->checkAccess('edit_permission', $this->object->getRefId())) {
            $this->tabs_gui->addTab(
                self::TAB_ID_PERMISSIONS,
                $this->lng->txt(self::TAB_ID_PERMISSIONS),
                $this->ctrl->getLinkTargetByClass(ilPermissionGUI::class, 'perm')
            );
        }
    }

    public function executeCommand(): void
    {
        $this->lng->loadLanguageModule('cmix');

        $this->prepareOutput();

        switch ($this->ctrl->getNextClass()) {
            case 'ilpermissiongui':

                $this->tabs_gui->activateTab(self::TAB_ID_PERMISSIONS);
                $gui = new ilPermissionGUI($this);
                $this->ctrl->forwardCommand($gui);
                break;

            default:

                $command = $this->ctrl->getCmd(self::DEFAULT_CMD) . 'Cmd';
                $this->{$command}();
        }
    }

    protected function viewCmd(): void
    {
        $this->showLrsTypesListCmd();
    }

    protected function showLrsTypesListCmd(): void
    {
        $this->tabs_gui->activateTab(self::TAB_ID_LRS_TYPES);

        $toolbarHtml = "";
        if ($this->rbac_system->checkAccess('write', $this->getRefId())) {
            $toolbarHtml = $this->buildLrsTypesToolbarGUI()->getHTML();
        }

        $table = $this->buildLrsTypesTableGUI();

        $table->setData(ilCmiXapiLrsTypeList::getTypesData(true));
        $this->tpl->setContent($toolbarHtml . $table->getHTML());
    }

    protected function buildLrsTypesTableGUI(): \ilCmiXapiLrsTypesTableGUI
    {
        return new ilCmiXapiLrsTypesTableGUI($this, self::CMD_SHOW_LRS_TYPES_LIST);
    }

    protected function buildLrsTypesToolbarGUI(): \ilToolbarGUI
    {
        global $DIC;
        $button = $DIC->ui()->factory()->button()->primary(
            $this->lng->txt("btn_create_lrs_type"),
            $this->ctrl->getLinkTarget($this, self::CMD_SHOW_LRS_TYPE_FORM)
        );

        $toolbar = new ilToolbarGUI();
        $toolbar->addComponent($button);

        return $toolbar;
    }

    protected function showLrsTypeFormCmd(ilPropertyFormGUI $form = null): void
    {
        $this->tabs_gui->activateTab(self::TAB_ID_LRS_TYPES);

        if ($form === null) {
            $lrsType = $this->initLrsType();

            $form = $this->buildLrsTypeForm($lrsType);
        }

        $this->tpl->setContent($form->getHTML());
    }

    protected function initLrsType(): \ilCmiXapiLrsType
    {
        if ($this->post_wrapper->has('lrs_type_id')) {
            if (is_int($this->post_wrapper->retrieve('lrs_type_id', $this->refinery->kindlyTo()->int()))) {
                return new ilCmiXapiLrsType($this->post_wrapper->retrieve('lrs_type_id', $this->refinery->kindlyTo()->int()));
            }
        }

        if ($this->request_wrapper->has('lrs_type_id')) {
            if (is_int($this->request_wrapper->retrieve('lrs_type_id', $this->refinery->kindlyTo()->int()))) {
                return new ilCmiXapiLrsType($this->request_wrapper->retrieve('lrs_type_id', $this->refinery->kindlyTo()->int()));
            }
        }

        return new ilCmiXapiLrsType();
    }

    protected function buildLrsTypeForm(ilCmiXapiLrsType $lrsType): \ilPropertyFormGUI
    {
        global $DIC; /* @var \ILIAS\DI\Container $DIC */

        $form = new ilPropertyFormGUI();
        $form->setFormAction($DIC->ctrl()->getFormAction($this));

        if ($lrsType->getTypeId()) {
            $form->setTitle($DIC->language()->txt('edit_lrs_type_form'));
        } else {
            $form->setTitle($DIC->language()->txt('create_lrs_type_form'));
            //            $form->addCommandButton(self::CMD_SAVE_LRS_TYPE_FORM, $DIC->language()->txt('create'));
        }
        $form->addCommandButton(self::CMD_SAVE_LRS_TYPE_FORM, $DIC->language()->txt('save'));
        $form->addCommandButton(self::CMD_SHOW_LRS_TYPES_LIST, $DIC->language()->txt('cancel'));

        $hiddenId = new ilHiddenInputGUI('lrs_type_id');
        $hiddenId->setValue((string) $lrsType->getTypeId());
        $form->addItem($hiddenId);


        $item = new ilTextInputGUI($DIC->language()->txt('conf_title'), 'title');
        $item->setValue($lrsType->getTitle());
        $item->setInfo($DIC->language()->txt('info_title'));
        $item->setRequired(true);
        $item->setMaxLength(255);
        $form->addItem($item);

        $item = new ilTextInputGUI($DIC->language()->txt('conf_description'), 'description');
        $item->setValue($lrsType->getDescription());
        $item->setInfo($DIC->language()->txt('info_description'));
        $form->addItem($item);

        $item = new ilRadioGroupInputGUI($DIC->language()->txt('conf_availability'), 'availability');
        $optionCreate = new ilRadioOption(
            $DIC->language()->txt('conf_availability_' . ilCmiXapiLrsType::AVAILABILITY_CREATE),
            (string) ilCmiXapiLrsType::AVAILABILITY_CREATE
        );
        $optionCreate->setInfo('conf_availability_' . ilCmiXapiLrsType::AVAILABILITY_CREATE . '_info');
        $item->addOption($optionCreate);
        $optionCreate = new ilRadioOption(
            $DIC->language()->txt('conf_availability_' . ilCmiXapiLrsType::AVAILABILITY_EXISTING),
            (string) ilCmiXapiLrsType::AVAILABILITY_EXISTING
        );
        $optionCreate->setInfo('conf_availability_' . ilCmiXapiLrsType::AVAILABILITY_EXISTING . '_info');
        $item->addOption($optionCreate);
        $optionCreate = new ilRadioOption(
            $DIC->language()->txt('conf_availability_' . ilCmiXapiLrsType::AVAILABILITY_NONE),
            (string) ilCmiXapiLrsType::AVAILABILITY_NONE
        );
        $optionCreate->setInfo('conf_availability_' . ilCmiXapiLrsType::AVAILABILITY_NONE . '_info');
        $item->addOption($optionCreate);
        $item->setValue((string) $lrsType->getAvailability());
        $item->setRequired(true);
        $form->addItem($item);

        $sectionHeader = new ilFormSectionHeaderGUI();
        $sectionHeader->setTitle($DIC->language()->txt('lrs_authentication'));
        $form->addItem($sectionHeader);

        $item = new ilTextInputGUI($DIC->language()->txt('conf_lrs_endpoint'), 'lrs_endpoint');
        $item->setValue($lrsType->getLrsEndpoint());
        $item->setInfo($DIC->language()->txt('info_lrs_endpoint'));
        $item->setRequired(true);
        $item->setMaxLength(255);
        $form->addItem($item);

        $item = new ilTextInputGUI($DIC->language()->txt('conf_lrs_key'), 'lrs_key');
        $item->setValue($lrsType->getLrsKey());
        $item->setInfo($DIC->language()->txt('info_lrs_key'));
        $item->setRequired(true);
        $item->setMaxLength(128);
        $form->addItem($item);

        $item = new ilTextInputGUI($DIC->language()->txt('conf_lrs_secret'), 'lrs_secret');
        $item->setValue($lrsType->getLrsSecret());
        $item->setInfo($DIC->language()->txt('info_lrs_secret'));
        $item->setRequired(true);
        $item->setMaxLength(128);
        $form->addItem($item);

        $sectionHeader = new ilFormSectionHeaderGUI();
        $sectionHeader->setTitle($DIC->language()->txt('privacy_options'));
        $form->addItem($sectionHeader);

        $useProxy = new ilCheckboxInputGUI($DIC->language()->txt('conf_use_proxy'), 'use_proxy');
        $useProxy->setInfo($DIC->language()->txt('conf_use_proxy_info'));
        if($lrsType->isBypassProxyEnabled() == false) {
            $useProxy->setChecked(true);
        }

        $options = array(
            "achieved" => $DIC->language()->txt('achieved_label'),
            "answered" => $DIC->language()->txt('answered_label'),
            "completed" => $DIC->language()->txt('completed_label'),
            "failed" => $DIC->language()->txt('failed_label'),
            "initialized" => $DIC->language()->txt('initialized_label'),
            "passed" => $DIC->language()->txt('passed_label'),
            "progressed" => $DIC->language()->txt('progressed_label'),
            "satisfied" => $DIC->language()->txt('satisfied_label'),
            "terminated" => $DIC->language()->txt('terminated_label'),
        );
        $multi = $DIC->ui()->factory()->input()->field()->multiselect($DIC->language()->txt('conf_store_only_verbs'), $options, $DIC->language()->txt('conf_store_only_verbs_info'))
                    ->withRequired(true);

        //        $form =($DIC->ui()->factory()->input()->container()->form()->standard('#', ['multi' => $multi]);

        $item = new ilCheckboxInputGUI($DIC->language()->txt('only_moveon_label'), 'only_moveon');
        $item->setInfo($DIC->language()->txt('only_moveon_info'));
        $item->setChecked($lrsType->getOnlyMoveon());

        $subitem = new ilCheckboxInputGUI($DIC->language()->txt('achieved_label'), 'achieved');
        $subitem->setInfo($DIC->language()->txt('achieved_info'));
        $subitem->setChecked($lrsType->getAchieved());
        $item->addSubItem($subitem);

        $subitem = new ilCheckboxInputGUI($DIC->language()->txt('answered_label'), 'answered');
        $subitem->setInfo($DIC->language()->txt('answered_info'));
        $subitem->setChecked($lrsType->getAnswered());
        $item->addSubItem($subitem);

        $subitem = new ilCheckboxInputGUI($DIC->language()->txt('completed_label'), 'completed');
        $subitem->setInfo($DIC->language()->txt('completed_info'));
        $subitem->setChecked($lrsType->getCompleted());
        $item->addSubItem($subitem);

        $subitem = new ilCheckboxInputGUI($DIC->language()->txt('failed_label'), 'failed');
        $subitem->setInfo($DIC->language()->txt('failed_info'));
        $subitem->setChecked($lrsType->getFailed());
        $item->addSubItem($subitem);

        $subitem = new ilCheckboxInputGUI($DIC->language()->txt('initialized_label'), 'initialized');
        $subitem->setInfo($DIC->language()->txt('initialized_info'));
        $subitem->setChecked($lrsType->getInitialized());
        $item->addSubItem($subitem);

        $subitem = new ilCheckboxInputGUI($DIC->language()->txt('passed_label'), 'passed');
        $subitem->setInfo($DIC->language()->txt('passed_info'));
        $subitem->setChecked($lrsType->getPassed());
        $item->addSubItem($subitem);

        $subitem = new ilCheckboxInputGUI($DIC->language()->txt('progressed_label'), 'progressed');
        $subitem->setInfo($DIC->language()->txt('progressed_info'));
        $subitem->setChecked($lrsType->getProgressed());
        $item->addSubItem($subitem);

        $subitem = new ilCheckboxInputGUI($DIC->language()->txt('satisfied_label'), 'satisfied');
        $subitem->setInfo($DIC->language()->txt('satisfied_info'));
        $subitem->setChecked($lrsType->getSatisfied());
        $item->addSubItem($subitem);

        $subitem = new ilCheckboxInputGUI($DIC->language()->txt('terminated_label'), 'terminated');
        $subitem->setInfo($DIC->language()->txt('terminated_info'));
        $subitem->setChecked($lrsType->getTerminated());
        $item->addSubItem($subitem);

        $useProxy->addSubItem($item);

        $item = new ilCheckboxInputGUI($DIC->language()->txt('hide_data_label'), 'hide_data');
        $item->setInfo($DIC->language()->txt('hide_data_info'));
        $item->setChecked($lrsType->getHideData());

        $subitem = new ilCheckboxInputGUI($DIC->language()->txt('timestamp_label'), 'timestamp');
        $subitem->setInfo($DIC->language()->txt('timestamp_info'));
        $subitem->setChecked($lrsType->getTimestamp());
        $item->addSubItem($subitem);

        $subitem = new ilCheckboxInputGUI($DIC->language()->txt('duration_label'), 'duration');
        $subitem->setInfo($DIC->language()->txt('duration_info'));
        $subitem->setChecked($lrsType->getDuration());
        $item->addSubItem($subitem);

        $useProxy->addSubItem($item);

        $item = new ilCheckboxInputGUI($DIC->language()->txt('no_substatements_label'), 'no_substatements');
        $item->setInfo($DIC->language()->txt('no_substatements_info'));
        $item->setChecked($lrsType->getNoSubstatements());
        $useProxy->addSubItem($item);



        $form->addItem($useProxy);

        //        $sectionHeader = new ilFormSectionHeaderGUI();
        //        $sectionHeader->setTitle('Privacy Settings');
        //        $form->addItem($sectionHeader);

        $item = new ilRadioGroupInputGUI($DIC->language()->txt('conf_privacy_ident'), 'privacy_ident');
        $op = new ilRadioOption(
            $DIC->language()->txt('conf_privacy_ident_il_uuid_user_id'),
            (string) ilCmiXapiLrsType::PRIVACY_IDENT_IL_UUID_USER_ID
        );
        $op->setInfo($DIC->language()->txt('conf_privacy_ident_il_uuid_user_id_info'));
        $item->addOption($op);
        $op = new ilRadioOption(
            $DIC->language()->txt('conf_privacy_ident_il_uuid_login'),
            (string) ilCmiXapiLrsType::PRIVACY_IDENT_IL_UUID_LOGIN
        );
        $op->setInfo($DIC->language()->txt('conf_privacy_ident_il_uuid_login_info'));
        $item->addOption($op);
        $op = new ilRadioOption(
            $DIC->language()->txt('conf_privacy_ident_il_uuid_ext_account'),
            (string) ilCmiXapiLrsType::PRIVACY_IDENT_IL_UUID_EXT_ACCOUNT
        );
        $op->setInfo($DIC->language()->txt('conf_privacy_ident_il_uuid_ext_account_info'));
        $item->addOption($op);
        $op = new ilRadioOption(
            $DIC->language()->txt('conf_privacy_ident_il_uuid_sha256'),
            (string) ilCmiXapiLrsType::PRIVACY_IDENT_IL_UUID_SHA256
        );
        $op->setInfo($DIC->language()->txt('conf_privacy_ident_il_uuid_sha256_info'));
        $item->addOption($op);
        $op = new ilRadioOption(
            $DIC->language()->txt('conf_privacy_ident_il_uuid_sha256url'),
            (string) ilCmiXapiLrsType::PRIVACY_IDENT_IL_UUID_SHA256URL
        );
        $op->setInfo($DIC->language()->txt('conf_privacy_ident_il_uuid_sha256url_info'));
        $item->addOption($op);

        $op = new ilRadioOption(
            $DIC->language()->txt('conf_privacy_ident_il_uuid_random'),
            (string) ilCmiXapiLrsType::PRIVACY_IDENT_IL_UUID_RANDOM
        );
        $op->setInfo($DIC->language()->txt('conf_privacy_ident_il_uuid_random_info'));
        $item->addOption($op);
        $op = new ilRadioOption(
            $DIC->language()->txt('conf_privacy_ident_real_email'),
            (string) ilCmiXapiLrsType::PRIVACY_IDENT_REAL_EMAIL
        );
        $op->setInfo($DIC->language()->txt('conf_privacy_ident_real_email_info'));
        $item->addOption($op);
        $item->setValue((string) $lrsType->getPrivacyIdent());
        $item->setInfo(
            $DIC->language()->txt('conf_privacy_ident_info') . ' ' . ilCmiXapiUser::getIliasUuid()
        );
        $item->setRequired(false);
        $form->addItem($item);

        $item = new ilRadioGroupInputGUI($DIC->language()->txt('conf_privacy_name'), 'privacy_name');
        $op = new ilRadioOption(
            $DIC->language()->txt('conf_privacy_name_none'),
            (string) ilCmiXapiLrsType::PRIVACY_NAME_NONE
        );
        $op->setInfo($DIC->language()->txt('conf_privacy_name_none_info'));
        $item->addOption($op);
        $op = new ilRadioOption(
            $DIC->language()->txt('conf_privacy_name_firstname'),
            (string) ilCmiXapiLrsType::PRIVACY_NAME_FIRSTNAME
        );
        $item->addOption($op);
        $op = new ilRadioOption(
            $DIC->language()->txt('conf_privacy_name_lastname'),
            (string) ilCmiXapiLrsType::PRIVACY_NAME_LASTNAME
        );
        $item->addOption($op);
        $op = new ilRadioOption(
            $DIC->language()->txt('conf_privacy_name_fullname'),
            (string) ilCmiXapiLrsType::PRIVACY_NAME_FULLNAME
        );
        $item->addOption($op);
        $item->setValue((string) $lrsType->getPrivacyName());
        $item->setInfo($DIC->language()->txt('conf_privacy_name_info'));
        $item->setRequired(false);
        $form->addItem($item);

        //        $item = new ilCheckboxInputGUI($DIC->language()->txt('only_moveon_label'), 'only_moveon');
        //        $item->setInfo($DIC->language()->txt('only_moveon_info'));
        //        $item->setChecked($lrsType->getOnlyMoveon());
        //
        //        $subitem = new ilCheckboxInputGUI($DIC->language()->txt('achieved_label'), 'achieved');
        //        $subitem->setInfo($DIC->language()->txt('achieved_info'));
        //        $subitem->setChecked($lrsType->getAchieved());
        //        $item->addSubItem($subitem);
        //
        //        $subitem = new ilCheckboxInputGUI($DIC->language()->txt('answered_label'), 'answered');
        //        $subitem->setInfo($DIC->language()->txt('answered_info'));
        //        $subitem->setChecked($lrsType->getAnswered());
        //        $item->addSubItem($subitem);
        //
        //        $subitem = new ilCheckboxInputGUI($DIC->language()->txt('completed_label'), 'completed');
        //        $subitem->setInfo($DIC->language()->txt('completed_info'));
        //        $subitem->setChecked($lrsType->getCompleted());
        //        $item->addSubItem($subitem);
        //
        //        $subitem = new ilCheckboxInputGUI($DIC->language()->txt('failed_label'), 'failed');
        //        $subitem->setInfo($DIC->language()->txt('failed_info'));
        //        $subitem->setChecked($lrsType->getFailed());
        //        $item->addSubItem($subitem);
        //
        //        $subitem = new ilCheckboxInputGUI($DIC->language()->txt('initialized_label'), 'initialized');
        //        $subitem->setInfo($DIC->language()->txt('initialized_info'));
        //        $subitem->setChecked($lrsType->getInitialized());
        //        $item->addSubItem($subitem);
        //
        //        $subitem = new ilCheckboxInputGUI($DIC->language()->txt('passed_label'), 'passed');
        //        $subitem->setInfo($DIC->language()->txt('passed_info'));
        //        $subitem->setChecked($lrsType->getPassed());
        //        $item->addSubItem($subitem);
        //
        //        $subitem = new ilCheckboxInputGUI($DIC->language()->txt('progressed_label'), 'progressed');
        //        $subitem->setInfo($DIC->language()->txt('progressed_info'));
        //        $subitem->setChecked($lrsType->getProgressed());
        //        $item->addSubItem($subitem);
        //
        //        $subitem = new ilCheckboxInputGUI($DIC->language()->txt('satisfied_label'), 'satisfied');
        //        $subitem->setInfo($DIC->language()->txt('satisfied_info'));
        //        $subitem->setChecked($lrsType->getSatisfied());
        //        $item->addSubItem($subitem);
        //
        //        $subitem = new ilCheckboxInputGUI($DIC->language()->txt('terminated_label'), 'terminated');
        //        $subitem->setInfo($DIC->language()->txt('terminated_info'));
        //        $subitem->setChecked($lrsType->getTerminated());
        //        $item->addSubItem($subitem);
        //
        //        $form->addItem($item);

        //        $item = new ilCheckboxInputGUI($DIC->language()->txt('hide_data_label'), 'hide_data');
        //        $item->setInfo($DIC->language()->txt('hide_data_info'));
        //        $item->setChecked($lrsType->getHideData());
        //
        //        $subitem = new ilCheckboxInputGUI($DIC->language()->txt('timestamp_label'), 'timestamp');
        //        $subitem->setInfo($DIC->language()->txt('timestamp_info'));
        //        $subitem->setChecked($lrsType->getTimestamp());
        //        $item->addSubItem($subitem);
        //
        //        $subitem = new ilCheckboxInputGUI($DIC->language()->txt('duration_label'), 'duration');
        //        $subitem->setInfo($DIC->language()->txt('duration_info'));
        //        $subitem->setChecked($lrsType->getDuration());
        //        $item->addSubItem($subitem);
        //
        //        $form->addItem($item);
        //
        //        $item = new ilCheckboxInputGUI($DIC->language()->txt('no_substatements_label'), 'no_substatements');
        //        $item->setInfo($DIC->language()->txt('no_substatements_info'));
        //        $item->setChecked($lrsType->getNoSubstatements());
        //        $form->addItem($item);

        $item = new ilRadioGroupInputGUI($DIC->language()->txt('conf_delete_data'), 'delete_data');
        $options = ["0","1","2","11","12"];
        for ((int) $i = 0; $i < count($options); $i++) {
            $op = new ilRadioOption($DIC->language()->txt('conf_delete_data_opt' . $options[$i]), $options[$i]);
            $item->addOption($op);
        }
        $item->setValue((string) $lrsType->getDeleteData());
        $item->setInfo($DIC->language()->txt('conf_delete_data_info'));
        $form->addItem($item);

        $item = new ilRadioGroupInputGUI($DIC->language()->txt('conf_privacy_setting_conf'), 'force_privacy_setting');
        $op = new ilRadioOption($DIC->language()->txt('conf_privacy_setting_default'), "0");
        $item->addOption($op);
        $op = new ilRadioOption($DIC->language()->txt('conf_privacy_setting_force'), "1");
        $item->addOption($op);
        $item->setValue((string) ((int) $lrsType->getForcePrivacySettings()));
        $form->addItem($item);

        $sectionHeader = new ilFormSectionHeaderGUI();
        $sectionHeader->setTitle('Hints');
        $form->addItem($sectionHeader);

        $item = new ilCheckboxInputGUI($DIC->language()->txt('conf_external_lrs'), 'external_lrs');
        $item->setChecked($lrsType->getExternalLrs());
        $item->setInfo($DIC->language()->txt('info_external_lrs'));
        $form->addItem($item);

        $item = new ilTextAreaInputGUI($DIC->language()->txt('conf_privacy_comment_default'), 'privacy_comment_default');
        $item->setInfo($DIC->language()->txt('info_privacy_comment_default'));
        $item->setValue($lrsType->getPrivacyCommentDefault());
        $item->setRows(5);
        $form->addItem($item);

        $item = new ilTextAreaInputGUI($DIC->language()->txt('conf_remarks'), 'remarks');
        $item->setInfo($DIC->language()->txt('info_remarks'));
        $item->setValue($lrsType->getRemarks());
        $item->setRows(5);
        $form->addItem($item);

        return $form;
    }

    protected function saveLrsTypeFormCmd(): void
    {
        global $DIC; /* @var \ILIAS\DI\Container $DIC */

        $lrsType = $this->initLrsType();

        $form = $this->buildLrsTypeForm($lrsType);

        if (!$form->checkInput()) {
            $this->showLrsTypeFormCmd($form);
            return;
        }

        $lrsType->setTitle($form->getInput("title"));
        $lrsType->setDescription($form->getInput("description"));
        $lrsType->setAvailability((int) $form->getInput("availability"));

        $lrsType->setLrsEndpoint(
            ilFileUtils::removeTrailingPathSeparators($form->getInput("lrs_endpoint"))
        );

        $lrsType->setLrsKey($form->getInput("lrs_key"));
        $lrsType->setLrsSecret($form->getInput("lrs_secret"));
        $lrsType->setExternalLrs((bool) $form->getInput("external_lrs"));
        $lrsType->setPrivacyIdent((int) $form->getInput("privacy_ident"));
        $lrsType->setPrivacyName((int) $form->getInput("privacy_name"));
        $lrsType->setPrivacyCommentDefault($form->getInput("privacy_comment_default"));
        $lrsType->setRemarks($form->getInput("remarks"));

        $oldBypassProxyEnabled = $lrsType->isBypassProxyEnabled();
        $newBypassProxyEnabled = false;
        if ((bool) $form->getInput("use_proxy") == false) {
            $newBypassProxyEnabled = true;
        }
        $lrsType->setBypassProxyEnabled($newBypassProxyEnabled);
        if ($newBypassProxyEnabled && $newBypassProxyEnabled != $oldBypassProxyEnabled) {
            ilObjCmiXapi::updateByPassProxyFromLrsType($lrsType);
        }

        $lrsType->setOnlyMoveon((bool) $form->getInput("only_moveon"));
        $lrsType->setAchieved((bool) $form->getInput("achieved"));
        $lrsType->setAnswered((bool) $form->getInput("answered"));
        $lrsType->setCompleted((bool) $form->getInput("completed"));
        $lrsType->setFailed((bool) $form->getInput("failed"));
        $lrsType->setInitialized((bool) $form->getInput("initialized"));
        $lrsType->setPassed((bool) $form->getInput("passed"));
        $lrsType->setProgressed((bool) $form->getInput("progressed"));
        $lrsType->setSatisfied((bool) $form->getInput("satisfied"));
        $lrsType->setTerminated((bool) $form->getInput("terminated"));
        $lrsType->setHideData((bool) $form->getInput("hide_data"));
        $lrsType->setTimestamp((bool) $form->getInput("timestamp"));
        $lrsType->setDuration((bool) $form->getInput("duration"));
        $lrsType->setNoSubstatements((bool) $form->getInput("no_substatements"));
        $lrsType->setDeleteData((int) $form->getInput("delete_data"));

        $lrsType->setForcePrivacySettings((bool) $form->getInput("force_privacy_setting"));
        if ($lrsType->getForcePrivacySettings()) {
            ilObjCmiXapi::updatePrivacySettingsFromLrsType($lrsType);
        }

        $lrsType->save();

        $DIC->ctrl()->redirect($this, self::CMD_SHOW_LRS_TYPES_LIST);
    }
}
