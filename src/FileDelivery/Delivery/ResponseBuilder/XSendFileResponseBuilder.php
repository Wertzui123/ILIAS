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

namespace ILIAS\FileDelivery\Delivery\ResponseBuilder;

use Psr\Http\Message\ResponseInterface;
use ILIAS\Filesystem\Stream\FileStream;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @author Fabian Schmid <fabian@sr.solutions>
 */
class XSendFileResponseBuilder implements ResponseBuilder
{
    private const X_SENDFILE_HEADER = 'X-Sendfile';

    public function getName(): string
    {
        return 'xsendfile';
    }

    public function buildForStream(
        ServerRequestInterface $request,
        ResponseInterface $response,
        FileStream $stream,
    ): ResponseInterface {
        return $response->withHeader(
            self::X_SENDFILE_HEADER,
            $stream->getMetadata('uri')
        );
    }

    public function supportPartial(): bool
    {
        return true;
    }

    public function supportStreaming(): bool
    {
        return true;
    }

    public function supportFileDeletion(): bool
    {
        return false;
    }

    public function supportsInlineDelivery(): bool
    {
        return true;
    }

    public function supportsAttachmentDelivery(): bool
    {
        return true;
    }
}
