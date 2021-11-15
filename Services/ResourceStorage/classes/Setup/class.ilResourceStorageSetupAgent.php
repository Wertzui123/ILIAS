<?php declare(strict_types=1);

use ILIAS\Refinery\Transformation;
use ILIAS\Setup\Agent;
use ILIAS\Setup\Config;
use ILIAS\Setup\Metrics;
use ILIAS\Setup\Objective;
use ILIAS\Setup\ObjectiveCollection;

/**
 * Class ilResourceStorageSetupAgent
 * @author Fabian Schmid <fs@studer-raimann.ch>
 */
class ilResourceStorageSetupAgent implements Agent
{
    use Agent\HasNoNamedObjective;

    public function hasConfig() : bool
    {
        return false;
    }

    public function getArrayToConfigTransformation() : Transformation
    {
        throw new \LogicException("Agent has no config.");
    }

    public function getInstallObjective(Config $config = null) : Objective
    {
        return new ObjectiveCollection(
            'IRSS Installation',
            false,
            new ilStorageContainersExistingObjective(),
            new ilDatabaseUpdateStepsExecutedObjective(
                new ilResourceStorageDB80()
            )
        );
    }

    public function getUpdateObjective(Config $config = null) : Objective
    {
        return new ObjectiveCollection(
            'IRSS Update',
            false,
            new ilStorageContainersExistingObjective(),
            new ilDatabaseUpdateStepsExecutedObjective(
                new ilResourceStorageDB80()
            )
        );
    }

    public function getBuildArtifactObjective() : Objective
    {
        return new Objective\NullObjective();
    }

    public function getStatusObjective(Metrics\Storage $storage) : Objective
    {
        return new Objective\NullObjective();
    }

    public function getMigrations() : array
    {
        return [new ilStorageHandlerV1Migration()];
    }

}
