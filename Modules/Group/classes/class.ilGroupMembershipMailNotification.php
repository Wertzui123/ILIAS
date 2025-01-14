<?php

declare(strict_types=1);
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * @author Stefan Meyer <smeyer.ilias@gmx.de>
 *
 * @ingroup ServicesMembership
 */
class ilGroupMembershipMailNotification extends ilMailNotification
{
    // v Notifications affect members & co. v
    public const TYPE_ADMISSION_MEMBER = 20;
    public const TYPE_DISMISS_MEMBER = 21;

    public const TYPE_ACCEPTED_SUBSCRIPTION_MEMBER = 22;
    public const TYPE_REFUSED_SUBSCRIPTION_MEMBER = 23;

    public const TYPE_STATUS_CHANGED = 24;

    public const TYPE_BLOCKED_MEMBER = 25;
    public const TYPE_UNBLOCKED_MEMBER = 26;

    public const TYPE_UNSUBSCRIBE_MEMBER = 27;
    public const TYPE_SUBSCRIBE_MEMBER = 28;
    public const TYPE_WAITING_LIST_MEMBER = 29;

    // Notifications affect admins
    public const TYPE_NOTIFICATION_REGISTRATION = 30;
    public const TYPE_NOTIFICATION_REGISTRATION_REQUEST = 31;
    public const TYPE_NOTIFICATION_UNSUBSCRIBE = 32;

    /**
     * Notifications which are not affected by "mail_grp_member_notification" setting
     * because they addresses admins
     * @var int[]
     */
    protected array $permanent_enabled_notifications = array(
        self::TYPE_NOTIFICATION_REGISTRATION,
        self::TYPE_NOTIFICATION_REGISTRATION_REQUEST,
        self::TYPE_NOTIFICATION_UNSUBSCRIBE
    );

    private bool $force_sending_mail = false;

    private ilLogger $logger;
    private ilSetting $settings;


    public function __construct(bool $a_is_personal_workspace = false)
    {
        global $DIC;

        $this->logger = $DIC->logger()->grp();
        $this->settings = $DIC->settings();
        parent::__construct($a_is_personal_workspace);
    }

    /**
     * @inheritDoc
     */
    protected function initMail(): ilMail
    {
        parent::initMail();
        $this->mail = $this->mail->withContextParameters([
            ilMail::PROP_CONTEXT_SUBJECT_PREFIX => ilContainer::_lookupContainerSetting(
                ilObject::_lookupObjId($this->getRefId()),
                ilObjectServiceSettingsGUI::EXTERNAL_MAIL_PREFIX,
                ''
            ),
        ]);

        return $this->mail;
    }

    /**
     * Force sending mail independent from global setting
     */
    public function forceSendingMail(bool $a_status): void
    {
        $this->force_sending_mail = $a_status;
    }



    public function send(): bool
    {
        if (!$this->isNotificationTypeEnabled($this->getType())) {
            $this->logger->info('Membership mail disabled globally.');
            return false;
        }

        if (
            $this->getType() == self::TYPE_ADMISSION_MEMBER
        ) {
            $obj = \ilObjectFactory::getInstanceByRefId($this->getRefId());
            if (!$obj instanceof \ilObjGroup) {
                $this->logger->warning('Refid: ' . $this->getRefId() . ' is not of type grp.');
                return false;
            }
            if (!$obj->getAutoNotification()) {
                if (!$this->force_sending_mail) {
                    $this->logger->info('Sending welcome mail disabled locally.');
                    return false;
                }
            }
        }

        // parent::send();

        switch ($this->getType()) {
            case self::TYPE_ADMISSION_MEMBER:

                foreach ($this->getRecipients() as $rcp) {
                    $this->initLanguage($rcp);
                    $this->initMail();
                    $this->setSubject(
                        sprintf($this->getLanguageText('grp_mail_admission_new_sub'), $this->getObjectTitle(true))
                    );
                    $this->setBody(ilMail::getSalutation($rcp, $this->getLanguage()));
                    $this->appendBody("\n\n");
                    $this->appendBody(
                        sprintf($this->getLanguageText('grp_mail_admission_new_bod'), $this->getObjectTitle())
                    );
                    $this->appendBody("\n\n");
                    $this->appendBody($this->getLanguageText('grp_mail_permanent_link'));
                    $this->appendBody("\n\n");
                    $this->appendBody($this->createPermanentLink());
                    $this->getMail()->appendInstallationSignature(true);

                    $this->sendMail(array($rcp));
                }
                break;

            case self::TYPE_DISMISS_MEMBER:

                foreach ($this->getRecipients() as $rcp) {
                    $this->initLanguage($rcp);
                    $this->initMail();
                    $this->setSubject(
                        sprintf($this->getLanguageText('grp_mail_dismiss_sub'), $this->getObjectTitle(true))
                    );
                    $this->setBody(ilMail::getSalutation($rcp, $this->getLanguage()));
                    $this->appendBody("\n\n");
                    $this->appendBody(
                        sprintf($this->getLanguageText('grp_mail_dismiss_bod'), $this->getObjectTitle())
                    );
                    $this->getMail()->appendInstallationSignature(true);
                    $this->sendMail(array($rcp));
                }
                break;


            case self::TYPE_NOTIFICATION_REGISTRATION:

                foreach ($this->getRecipients() as $rcp) {
                    $this->initLanguage($rcp);
                    $this->initMail();
                    $this->setSubject(
                        sprintf($this->getLanguageText('grp_mail_notification_reg_sub'), $this->getObjectTitle(true))
                    );
                    $this->setBody(ilMail::getSalutation($rcp, $this->getLanguage()));
                    $this->appendBody("\n\n");

                    $info = $this->getAdditionalInformation();
                    $this->appendBody(
                        sprintf(
                            $this->getLanguageText('grp_mail_notification_reg_bod'),
                            $this->userToString($info['usr_id']),
                            $this->getObjectTitle()
                        )
                    );
                    $this->appendBody("\n\n");
                    $this->appendBody($this->getLanguageText('grp_mail_permanent_link'));
                    $this->appendBody("\n\n");
                    $this->appendBody($this->createPermanentLink(array(), 'mem'));

                    $this->appendBody("\n\n");
                    $this->appendBody($this->getLanguageText('grp_notification_explanation_admin'));

                    $this->getMail()->appendInstallationSignature(true);
                    $this->sendMail(array($rcp));
                }
                break;

            case self::TYPE_UNSUBSCRIBE_MEMBER:

                foreach ($this->getRecipients() as $rcp) {
                    $this->initLanguage($rcp);
                    $this->initMail();
                    $this->setSubject(
                        sprintf($this->getLanguageText('grp_mail_unsubscribe_member_sub'), $this->getObjectTitle(true))
                    );
                    $this->setBody(ilMail::getSalutation($rcp, $this->getLanguage()));
                    $this->appendBody("\n\n");
                    $this->appendBody(
                        sprintf($this->getLanguageText('grp_mail_unsubscribe_member_bod'), $this->getObjectTitle())
                    );
                    $this->getMail()->appendInstallationSignature(true);
                    $this->sendMail(array($rcp));
                }
                break;

            case self::TYPE_NOTIFICATION_UNSUBSCRIBE:

                foreach ($this->getRecipients() as $rcp) {
                    $this->initLanguage($rcp);
                    $this->initMail();
                    $this->setSubject(
                        sprintf($this->getLanguageText('grp_mail_notification_unsub_sub'), $this->getObjectTitle(true))
                    );
                    $this->setBody(ilMail::getSalutation($rcp, $this->getLanguage()));
                    $this->appendBody("\n\n");

                    $info = $this->getAdditionalInformation();
                    $this->appendBody(
                        sprintf(
                            $this->getLanguageText('grp_mail_notification_unsub_bod'),
                            $this->userToString($info['usr_id']),
                            $this->getObjectTitle()
                        )
                    );
                    $this->appendBody("\n\n");
                    $this->appendBody($this->getLanguageText('grp_mail_notification_unsub_bod2'));
                    $this->appendBody("\n\n");
                    $this->appendBody($this->createPermanentLink(array(), 'mem'));

                    $this->appendBody("\n\n");
                    $this->appendBody($this->getLanguageText('grp_notification_explanation_admin'));

                    $this->getMail()->appendInstallationSignature(true);
                    $this->sendMail(array($rcp));
                }
                break;


            case self::TYPE_SUBSCRIBE_MEMBER:

                foreach ($this->getRecipients() as $rcp) {
                    $this->initLanguage($rcp);
                    $this->initMail();
                    $this->setSubject(
                        sprintf($this->getLanguageText('grp_mail_subscribe_member_sub'), $this->getObjectTitle(true))
                    );
                    $this->setBody(ilMail::getSalutation($rcp, $this->getLanguage()));
                    $this->appendBody("\n\n");
                    $this->appendBody(
                        sprintf($this->getLanguageText('grp_mail_subscribe_member_bod'), $this->getObjectTitle())
                    );

                    $this->appendBody("\n\n");
                    $this->appendBody($this->getLanguageText('grp_mail_permanent_link'));
                    $this->appendBody("\n\n");
                    $this->appendBody($this->createPermanentLink());
                    $this->getMail()->appendInstallationSignature(true);

                    $this->sendMail(array($rcp));
                }
                break;


            case self::TYPE_NOTIFICATION_REGISTRATION_REQUEST:

                foreach ($this->getRecipients() as $rcp) {
                    $this->initLanguage($rcp);
                    $this->initMail();
                    $this->setSubject(
                        sprintf($this->getLanguageText('grp_mail_notification_reg_req_sub'), $this->getObjectTitle(true))
                    );
                    $this->setBody(ilMail::getSalutation($rcp, $this->getLanguage()));
                    $this->appendBody("\n\n");

                    $info = $this->getAdditionalInformation();
                    $this->appendBody(
                        sprintf(
                            $this->getLanguageText('grp_mail_notification_reg_req_bod'),
                            $this->userToString($info['usr_id']),
                            $this->getObjectTitle()
                        )
                    );
                    $this->appendBody("\n\n");
                    $this->appendBody($this->getLanguageText('grp_mail_notification_reg_req_bod2'));
                    $this->appendBody("\n");
                    $this->appendBody($this->createPermanentLink(array(), 'mem'));

                    $this->appendBody("\n\n");
                    $this->appendBody($this->getLanguageText('grp_notification_explanation_admin'));

                    $this->getMail()->appendInstallationSignature(true);
                    $this->sendMail(array($rcp));
                }
                break;

            case self::TYPE_REFUSED_SUBSCRIPTION_MEMBER:

                foreach ($this->getRecipients() as $rcp) {
                    $this->initLanguage($rcp);
                    $this->initMail();
                    $this->setSubject(
                        sprintf($this->getLanguageText('grp_mail_sub_dec_sub'), $this->getObjectTitle(true))
                    );
                    $this->setBody(ilMail::getSalutation($rcp, $this->getLanguage()));
                    $this->appendBody("\n\n");
                    $this->appendBody(
                        sprintf($this->getLanguageText('grp_mail_sub_dec_bod'), $this->getObjectTitle())
                    );

                    $this->getMail()->appendInstallationSignature(true);

                    $this->sendMail(array($rcp));
                }
                break;

            case self::TYPE_ACCEPTED_SUBSCRIPTION_MEMBER:

                foreach ($this->getRecipients() as $rcp) {
                    $this->initLanguage($rcp);
                    $this->initMail();
                    $this->setSubject(
                        sprintf($this->getLanguageText('grp_mail_sub_acc_sub'), $this->getObjectTitle(true))
                    );
                    $this->setBody(ilMail::getSalutation($rcp, $this->getLanguage()));
                    $this->appendBody("\n\n");
                    $this->appendBody(
                        sprintf($this->getLanguageText('grp_mail_sub_acc_bod'), $this->getObjectTitle())
                    );
                    $this->appendBody("\n\n");
                    $this->appendBody($this->getLanguageText('grp_mail_permanent_link'));
                    $this->appendBody("\n\n");
                    $this->appendBody($this->createPermanentLink());
                    $this->getMail()->appendInstallationSignature(true);

                    $this->sendMail(array($rcp));
                }
                break;

            case self::TYPE_WAITING_LIST_MEMBER:
                foreach ($this->getRecipients() as $rcp) {
                    $this->initLanguage($rcp);
                    $this->initMail();
                    $this->setSubject(
                        sprintf($this->getLanguageText('grp_mail_wl_sub'), $this->getObjectTitle(true))
                    );

                    $this->setBody(ilMail::getSalutation($rcp, $this->getLanguage()));

                    $info = $this->getAdditionalInformation();
                    $this->appendBody("\n\n");
                    $this->appendBody(
                        sprintf(
                            $this->getLanguageText('grp_mail_wl_bod'),
                            $this->getObjectTitle(),
                            $info['position']
                        )
                    );
                    $this->getMail()->appendInstallationSignature(true);
                    $this->sendMail(array($rcp));
                }
                break;


            case self::TYPE_STATUS_CHANGED:
                foreach ($this->getRecipients() as $rcp) {
                    $this->initLanguage($rcp);
                    $this->initMail();
                    $this->setSubject(
                        sprintf($this->getLanguageText('grp_mail_status_sub'), $this->getObjectTitle(true))
                    );
                    $this->setBody(ilMail::getSalutation($rcp, $this->getLanguage()));
                    $this->appendBody("\n\n");
                    $this->appendBody(
                        sprintf($this->getLanguageText('grp_mail_status_bod'), $this->getObjectTitle())
                    );

                    $this->appendBody("\n\n");
                    $this->appendBody($this->createGroupStatus($rcp));

                    $this->appendBody("\n\n");
                    $this->appendBody($this->getLanguageText('grp_mail_permanent_link'));
                    $this->appendBody("\n\n");
                    $this->appendBody($this->createPermanentLink());

                    $this->getMail()->appendInstallationSignature(true);

                    $this->sendMail(array($rcp));
                }
                break;
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    protected function initLanguage(int $a_usr_id): void
    {
        parent::initLanguage($a_usr_id);
        $this->getLanguage()->loadLanguageModule('grp');
    }

    protected function createGroupStatus(int $a_usr_id): string
    {
        $part = ilGroupParticipants::_getInstanceByObjId($this->getObjId());

        $body = $this->getLanguageText('grp_new_status') . "\n";
        $body .= $this->getLanguageText('role') . ': ';


        if ($part->isAdmin($a_usr_id)) {
            $body .= $this->getLanguageText('il_grp_admin') . "\n";
        } else {
            $body .= $this->getLanguageText('il_grp_member') . "\n";
        }

        if ($part->isAdmin($a_usr_id)) {
            $body .= $this->getLanguageText('grp_notification') . ': ';

            if ($part->isNotificationEnabled($a_usr_id)) {
                $body .= $this->getLanguageText('grp_notify_on') . "\n";
            } else {
                $body .= $this->getLanguageText('grp_notify_off') . "\n";
            }
        }
        return $body;
    }

    /**
     * get setting "mail_grp_member_notification" and excludes types which are not affected by this setting
     * See description of $this->permanent_enabled_notifications
     */
    protected function isNotificationTypeEnabled(int $a_type): bool
    {
        return
            $this->force_sending_mail ||
            $this->settings->get('mail_grp_member_notification', "1") ||
            in_array($a_type, $this->permanent_enabled_notifications);
    }
}
