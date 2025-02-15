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

namespace ILIAS\ResourceStorage\Flavour\Machine\DefaultMachines\Extract;

use ILIAS\ResourceStorage\Flavour\Definition\FlavourDefinition;
use ILIAS\ResourceStorage\Flavour\Definition\PagesToExtract;
use ILIAS\Filesystem\Stream\Stream;

/**
 * @author Fabian Schmid <fabian@sr.solutions>
 */
interface Extractor
{
    public function readImage(\Imagick $img, Stream $stream, PagesToExtract $definition): \Imagick;

    public function getResolution(): int;

    public function getTargetFormat(): string;

    public function getBackground(): \ImagickPixel;

    public function getRemoveColor(): ?\ImagickPixel;

    public function getAlphaChannel(): int;
}
