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
 * Importer class for question pools
 *
 * @author Helmut Schottmüller <ilias@aurealis.de>
 * @version $Id$
 * @ingroup ModulesLearningModule
 */

class ilTestQuestionPoolImporter extends ilXmlImporter
{
    /**
     * @var ilObjQuestionPool
     */
    private $poolOBJ;

    /**
     * Import XML
     * @param
     * @return void
     */
    public function importXmlRepresentation(string $a_entity, string $a_id, string $a_xml, ilImportMapping $a_mapping): void
    {
        ilObjQuestionPool::_setImportDirectory($this->getImportDirectoryContainer());

        // Container import => pool object already created
        if (($new_id = $a_mapping->getMapping('Services/Container', 'objs', $a_id)) !== null) {
            $newObj = ilObjectFactory::getInstanceByObjId($new_id, false);
            $newObj->getObjectProperties()->storePropertyIsOnline($newObj->getObjectProperties()->getPropertyIsOnline()->withOffline()); // sets Question pools to always online

            ilSession::set('qpl_import_subdir', $this->getImportPackageName());
        } elseif (($new_id = $a_mapping->getMapping('Modules/TestQuestionPool', 'qpl', "new_id")) !== null) {
            $newObj = ilObjectFactory::getInstanceByObjId($new_id, false);
        } else {
            // Shouldn't happen
            global $DIC; /* @var ILIAS\DI\Container $DIC */
            $DIC['ilLog']->write(__METHOD__ . ': non container and no tax mapping, perhaps old qpl export');
            return;
        }

        list($xml_file, $qti_file) = $this->parseXmlFileNames();

        global $DIC; /* @var ILIAS\DI\Container $DIC */
        if (!@file_exists($xml_file)) {
            $DIC['ilLog']->write(__METHOD__ . ': Cannot find xml definition: ' . $xml_file);
            return;
        }
        if (!@file_exists($qti_file)) {
            $DIC['ilLog']->write(__METHOD__ . ': Cannot find qti definition: ' . $qti_file);
            return;
        }

        $this->poolOBJ = $newObj;

        $newObj->fromXML($xml_file);

        // set another question pool name (if possible)
        if (isset($_POST["qpl_new"]) && strlen($_POST["qpl_new"])) {
            $newObj->setTitle($_POST["qpl_new"]);
        }

        $newObj->update();
        $newObj->saveToDb();

        // FIXME: Copied from ilObjQuestionPoolGUI::importVerifiedFileObject
        // TODO: move all logic to ilObjQuestionPoolGUI::importVerifiedFile and call
        // this method from ilObjQuestionPoolGUI and ilTestImporter

        global $DIC; /* @var ILIAS\DI\Container $DIC */
        $DIC['ilLog']->write(__METHOD__ . ': xml file: ' . $xml_file . ", qti file:" . $qti_file);

        if (ilSession::get("qpl_import_idents") !== null) {
            $idents = ilSession::get("qpl_import_idents");
            ilSession::clear("qpl_import_idents");
        } else {
            $idents = null;
        }

        $qtiParser = new ilQTIParser($qti_file, ilQTIParser::IL_MO_PARSE_QTI, $newObj->getId(), $idents);
        $qtiParser->startParsing();

        if (strlen($xml_file)) {
            $questionPageParser = new ilQuestionPageParser($newObj, $xml_file, basename($this->getImportDirectory()));
            $questionPageParser->setQuestionMapping($qtiParser->getImportMapping());
            $questionPageParser->startParsing();

            foreach ($qtiParser->getImportMapping() as $k => $v) {
                $oldQuestionId = substr($k, strpos($k, 'qst_') + strlen('qst_'));
                $newQuestionId = $v['pool']; // yes, this is the new question id ^^

                $a_mapping->addMapping(
                    "Services/Taxonomy",
                    "tax_item",
                    "qpl:quest:$oldQuestionId",
                    $newQuestionId
                );

                $a_mapping->addMapping(
                    "Services/Taxonomy",
                    "tax_item_obj_id",
                    "qpl:quest:$oldQuestionId",
                    $newObj->getId()
                );

                $a_mapping->addMapping(
                    "Modules/TestQuestionPool",
                    "quest",
                    $oldQuestionId,
                    $newQuestionId
                );
            }
        }

        $this->importQuestionSkillAssignments($xml_file, $a_mapping, $newObj->getId());

        $a_mapping->addMapping("Modules/TestQuestionPool", "qpl", $a_id, $newObj->getId());

        ilObjQuestionPool::_setImportDirectory(null);

        $newObj->saveToDb();
    }

    /**
     * Final processing
     * @param ilImportMapping $a_mapping
     * @return void
     */
    public function finalProcessing(ilImportMapping $a_mapping): void
    {
        $maps = $a_mapping->getMappingsOfEntity("Modules/TestQuestionPool", "qpl");
        foreach ($maps as $old => $new) {
            if ($old != "new_id" && (int) $old > 0) {
                // get all new taxonomys of this object
                $new_tax_ids = $a_mapping->getMapping("Services/Taxonomy", "tax_usage_of_obj", $old);
                if ($new_tax_ids !== null) {
                    $tax_ids = explode(":", $new_tax_ids);
                    foreach ($tax_ids as $tid) {
                        ilObjTaxonomy::saveUsage((int) $tid, $new);
                    }
                }
            }
        }
    }

    /**
     * Create qti and xml file name
     * @return array
     */
    protected function parseXmlFileNames(): array
    {
        global $DIC; /* @var ILIAS\DI\Container $DIC */
        $DIC['ilLog']->write(__METHOD__ . ': ' . $this->getImportDirectory());

        $basename = basename($this->getImportDirectory());

        $xml = $this->getImportDirectory() . '/' . $basename . '.xml';
        $qti = $this->getImportDirectory() . '/' . preg_replace('/qpl/', 'qti', $basename) . '.xml';

        return array($xml,$qti);
    }

    private function getImportDirectoryContainer(): string
    {
        $dir = $this->getImportDirectory();
        $dir = dirname($dir);
        return $dir;
    }

    private function getImportPackageName(): string
    {
        $dir = $this->getImportDirectory();
        $name = basename($dir);
        return $name;
    }

    protected function importQuestionSkillAssignments($xmlFile, ilImportMapping $mappingRegistry, $targetParentObjId): void
    {
        $parser = new ilAssQuestionSkillAssignmentXmlParser($xmlFile);
        $parser->startParsing();

        $importer = new ilAssQuestionSkillAssignmentImporter();
        $importer->setTargetParentObjId($targetParentObjId);
        $importer->setImportInstallationId($this->getInstallId());
        $importer->setImportMappingRegistry($mappingRegistry);
        $importer->setImportMappingComponent('Modules/TestQuestionPool');
        $importer->setImportAssignmentList($parser->getAssignmentList());

        $importer->import();

        if ($importer->getFailedImportAssignmentList()->assignmentsExist()) {
            $qsaImportFails = new ilAssQuestionSkillAssignmentImportFails($targetParentObjId);
            $qsaImportFails->registerFailedImports($importer->getFailedImportAssignmentList());

            $this->poolOBJ->getObjectProperties()->storePropertyIsOnline($this->poolOBJ->getObjectProperties()->getPropertyIsOnline()->withOffline());
        }
    }
}
