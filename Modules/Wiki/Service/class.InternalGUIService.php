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

namespace ILIAS\Wiki;

use ILIAS\DI\Container;
use ILIAS\Repository\GlobalDICGUIServices;
use ILIAS\Wiki\Content;
use ILIAS\Wiki\Page;
use ILIAS\Wiki\Notification\NotificationGUI;

/**
 * @author Alexander Killing <killing@leifos.de>
 */
class InternalGUIService
{
    use GlobalDICGUIServices;

    protected InternalDataService $data_service;
    protected InternalDomainService $domain_service;

    public function __construct(
        Container $DIC,
        InternalDataService $data_service,
        InternalDomainService $domain_service
    ) {
        $this->data_service = $data_service;
        $this->domain_service = $domain_service;
        $this->initGUIServices($DIC);
    }

    public function request(
        ?array $passed_query_params = null,
        ?array $passed_post_data = null
    ): WikiGUIRequest {
        return new WikiGUIRequest(
            $this->http(),
            $this->domain_service->refinery(),
            $passed_query_params,
            $passed_post_data
        );
    }

    public function content(): Content\GUIService
    {
        return new Content\GUIService(
            $this->domain_service,
            $this
        );
    }

    public function page(): Page\GUIService
    {
        return new Page\GUIService(
            $this->domain_service,
            $this
        );
    }

    public function notification(): NotificationGUI
    {
        return new NotificationGUI(
            $this->domain_service,
            $this
        );
    }

    public function wiki(): Wiki\GUIService
    {
        return new Wiki\GUIService(
            $this->domain_service,
            $this
        );
    }

}
