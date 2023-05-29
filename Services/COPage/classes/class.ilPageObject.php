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

define("IL_INSERT_BEFORE", 0);
define("IL_INSERT_AFTER", 1);
define("IL_INSERT_CHILD", 2);

/*

    - move dom related code to PageDom class/interface
    - move ilDB dependency to ar object
    - move internal links related code to extra class
    - make factory available through DIC, opt allow decentralized factory parts
    - PC types
    -- internal links used/implemented?
    -- styles used/implemented?
    - application classes need
    -- page object
    --- page object should return php5 domdoc (getDom() vs getDomDoc()?)
        esp. plugins should use this
    -- remove content element hook, if content is not allowed
    - PC types could move to components (e.g. blog, login)
    - How to modularize xsl?
    -- read from db?
    -- xml entries say that xslt code is used -> read file and include in
       main xslt file

*/

/**
 * Class ilPageObject
 * Handles PageObjects of ILIAS Learning Modules (see ILIAS DTD)
 * @author Alexander Killing <killing@leifos.de>
 */
abstract class ilPageObject
{
    protected \ILIAS\COPage\Link\LinkManager $link;
    protected \ILIAS\COPage\PC\PCDefinition $pc_definition;
    protected int $create_user = 0;
    /**
     * @var string[]
     */
    protected array $id_elements;
    public int $old_nr;
    protected bool $page_not_found = false;
    protected bool $show_page_act_info = false;
    protected ilObjectDefinition $obj_definition;
    public static array $exists = array();
    protected ilDBInterface $db;
    protected ilObjUser $user;
    protected ilLanguage $lng;
    protected ilTree $tree;
    protected int $id;
    public ?php4DOMDocument $dom = null;
    public string $xml = "";
    public string $encoding = "";
    public php4DOMElement $node;
    public string $cur_dtd = "ilias_pg_8.dtd";
    public bool $contains_int_link = false;
    public bool $needs_parsing = false;
    public string $parent_type = "";
    public int $parent_id = 0;
    public array $update_listeners = [];
    public int $update_listener_cnt = 0;
    public ?object $offline_handler = null;     // see LMPresentation handleCodeParagraph
    public bool $dom_builded = false;
    public bool $history_saved = false;
    protected string $language = "-";
    protected static array $activation_data = array();
    protected bool $import_mode = false;
    protected ilLogger $log;
    protected ?array $page_record = array();
    protected bool $active = false;
    protected ilPageConfig $page_config;
    protected string $rendermd5 = "";
    protected string $renderedcontent = "";
    protected string $renderedtime = "";
    protected string $lastchange = "";
    public int $last_change_user = 0;
    protected bool $contains_question = false;
    protected array $hier_ids = [];
    protected ?string $activationstart = null;      // IL_CAL_DATETIME format
    protected ?string $activationend = null;        // IL_CAL_DATETIME format
    protected \ILIAS\COPage\ReadingTime\ReadingTimeManager $reading_time_manager;
    protected $concrete_lang = "";
    protected \ILIAS\COPage\ID\ContentIdManager $content_id_manager;
    protected \ILIAS\COPage\Page\PageManager $page_manager;
    protected \ILIAS\COPage\PC\DomainService $pc_service;

    final public function __construct(
        int $a_id = 0,
        int $a_old_nr = 0,
        string $a_lang = "-"
    ) {
        global $DIC;
        $this->obj_definition = $DIC["objDefinition"];
        $this->db = $DIC->database();
        $this->user = $DIC->user();
        $this->lng = $DIC->language();
        $this->tree = $DIC->repositoryTree();
        $this->log = ilLoggerFactory::getLogger('copg');

        $this->reading_time_manager = new ILIAS\COPage\ReadingTime\ReadingTimeManager();

        $this->parent_type = $this->getParentType();
        $this->id = $a_id;
        $this->setLanguage($a_lang);

        $this->contains_int_link = false;
        $this->needs_parsing = false;
        $this->update_listeners = array();
        $this->update_listener_cnt = 0;
        $this->dom_builded = false;
        $this->page_not_found = false;
        $this->old_nr = $a_old_nr;
        $this->encoding = "UTF-8";
        $this->id_elements =
            array("PageContent",
                  "TableRow",
                  "TableData",
                  "ListItem",
                  "FileItem",
                  "Section",
                  "Tab",
                  "ContentPopup",
                  "GridCell"
            );
        $this->setActive(true);
        $this->show_page_act_info = false;

        if ($a_id != 0) {
            $this->read();
        }

        $this->initPageConfig();
        $this->afterConstructor();
        $domain = $DIC->copage()
                      ->internal()
                      ->domain();
        $this->content_id_manager = $domain
            ->contentIds($this);
        $this->page_manager = $domain->page();
        $this->pc_service = $domain->pc();
        $this->pc_definition = $DIC
            ->copage()
            ->internal()
            ->domain()
            ->pc()
            ->definition();
        $this->link = $DIC->copage()
            ->internal()
            ->domain()
            ->link();
    }

    public function setContentIdManager(
        \ILIAS\COPage\ID\ContentIdManager $content_id_manager
    ): void {
        $this->content_id_manager = $content_id_manager;
    }

    public function afterConstructor(): void
    {
    }

    abstract public function getParentType(): string;

    public function initPageConfig(): void
    {
        $cfg = ilPageObjectFactory::getConfigInstance($this->getParentType());
        $this->setPageConfig($cfg);
    }

    /**
     * Set language
     * @param string $a_val language code or "-" for unknown / not set
     */
    public function setLanguage(string $a_val): void
    {
        $this->language = $a_val;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setPageConfig(ilPageConfig $a_val): void
    {
        $this->page_config = $a_val;
    }

    public function setConcreteLang(string $a_val)
    {
        $this->concrete_lang = $a_val;
    }

    public function getConcreteLang(): string
    {
        return $this->concrete_lang;
    }

    public function getPageConfig(): ilPageConfig
    {
        return $this->page_config;
    }

    public function setRenderMd5(string $a_rendermd5): void
    {
        $this->rendermd5 = $a_rendermd5;
    }

    public function getRenderMd5(): string
    {
        return $this->rendermd5;
    }

    public function setRenderedContent(string $a_renderedcontent): void
    {
        $this->renderedcontent = $a_renderedcontent;
    }

    public function getRenderedContent(): string
    {
        return $this->renderedcontent;
    }

    public function setRenderedTime(string $a_renderedtime): void
    {
        $this->renderedtime = $a_renderedtime;
    }

    public function getRenderedTime(): string
    {
        return $this->renderedtime;
    }

    public function setLastChange(string $a_lastchange): void
    {
        $this->lastchange = $a_lastchange;
    }

    public function getLastChange(): string
    {
        return $this->lastchange;
    }

    public function setLastChangeUser(int $a_val): void
    {
        $this->last_change_user = $a_val;
    }

    public function getLastChangeUser(): int
    {
        return $this->last_change_user;
    }

    public function setShowActivationInfo(bool $a_val): void
    {
        $this->show_page_act_info = $a_val;
    }

    public function getShowActivationInfo(): bool
    {
        return $this->show_page_act_info;
    }

    public function getCreationUserId(): int
    {
        return $this->create_user;
    }

    /**
     * Read page data
     */
    public function read(): void
    {
        $this->setActive(true);
        if ($this->old_nr == 0) {
            $query = "SELECT * FROM page_object" .
                " WHERE page_id = " . $this->db->quote($this->id, "integer") .
                " AND parent_type=" . $this->db->quote($this->getParentType(), "text") .
                " AND lang = " . $this->db->quote($this->getLanguage(), "text");
            $pg_set = $this->db->query($query);
            if (!$this->page_record = $this->db->fetchAssoc($pg_set)) {
                throw new ilCOPageNotFoundException("Error: Page " . $this->id . " is not in database" .
                    " (parent type " . $this->getParentType() . ", lang: " . $this->getLanguage() . ").");
            }
            $this->setActive($this->page_record["active"]);
            $this->setActivationStart($this->page_record["activation_start"]);
            $this->setActivationEnd($this->page_record["activation_end"]);
            $this->setShowActivationInfo($this->page_record["show_activation_info"]);
        } else {
            $query = "SELECT * FROM page_history" .
                " WHERE page_id = " . $this->db->quote($this->id, "integer") .
                " AND parent_type=" . $this->db->quote($this->getParentType(), "text") .
                " AND nr = " . $this->db->quote($this->old_nr, "integer") .
                " AND lang = " . $this->db->quote($this->getLanguage(), "text");
            $pg_set = $this->db->query($query);
            $this->page_record = $this->db->fetchAssoc($pg_set);
        }
        if (!$this->page_record) {
            throw new ilCOPageNotFoundException("Error: Page " . $this->id . " is not in database" .
                " (parent type " . $this->getParentType() . ", lang: " . $this->getLanguage() . ").");
        }
        $this->xml = $this->page_record["content"];
        $this->setParentId((int) $this->page_record["parent_id"]);
        $this->last_change_user = (int) ($this->page_record["last_change_user"] ?? 0);
        $this->create_user = (int) ($this->page_record["create_user"] ?? 0);
        $this->setRenderedContent((string) ($this->page_record["rendered_content"] ?? ""));
        $this->setRenderMd5((string) ($this->page_record["render_md5"] ?? ""));
        $this->setRenderedTime((string) ($this->page_record["rendered_time"] ?? ""));
        $this->setLastChange((string) ($this->page_record["last_change"] ?? ""));
    }

    /**
     * Checks whether page exists
     * @param string $a_lang language code, if empty language independent existence is checked
     */
    public static function _exists(
        string $a_parent_type,
        int $a_id,
        string $a_lang = "",
        bool $a_no_cache = false
    ): bool {
        global $DIC;

        $db = $DIC->database();

        if (!$a_no_cache && isset(self::$exists[$a_parent_type . ":" . $a_id . ":" . $a_lang])) {
            return self::$exists[$a_parent_type . ":" . $a_id . ":" . $a_lang];
        }

        $and_lang = "";
        if ($a_lang != "") {
            $and_lang = " AND lang = " . $db->quote($a_lang, "text");
        }

        $query = "SELECT page_id FROM page_object WHERE page_id = " . $db->quote($a_id, "integer") . " " .
            "AND parent_type = " . $db->quote($a_parent_type, "text") . $and_lang;
        $set = $db->query($query);
        if ($row = $db->fetchAssoc($set)) {
            self::$exists[$a_parent_type . ":" . $a_id . ":" . $a_lang] = true;
            return true;
        } else {
            self::$exists[$a_parent_type . ":" . $a_id . ":" . $a_lang] = false;
            return false;
        }
    }

    /**
     * Checks whether page exists and is not empty (may return true on some empty pages)
     */
    public static function _existsAndNotEmpty(
        string $a_parent_type,
        int $a_id,
        string $a_lang = "-"
    ): bool {
        return ilPageUtil::_existsAndNotEmpty($a_parent_type, $a_id, $a_lang);
    }

    /**
     * @return bool|array
     */
    public function buildDom(bool $a_force = false)
    {
        if ($this->dom_builded && !$a_force) {
            return true;
        }
        $options = 0;
        //$options = DOMXML_LOAD_VALIDATING;
        //$options = LIBXML_DTDLOAD;
        //$options = LIBXML_NOXMLDECL;
        $this->dom = domxml_open_mem($this->getXMLContent(true), $options, $error);
        $xpc = xpath_new_context($this->dom);
        $path = "//PageObject";
        $res = xpath_eval($xpc, $path);
        if (count($res->nodeset) == 1) {
            $this->node = $res->nodeset[0];
        }

        if (empty($error)) {
            $this->dom_builded = true;
            return true;
        } else {
            return $error;
        }
    }

    public function freeDom(): void
    {
        unset($this->dom);
    }

    /**
     * @depracated
     */
    public function getDom(): ?php4DOMDocument
    {
        return $this->dom;
    }

    /**
     * Get dom doc (DOMDocument)
     */
    public function getDomDoc(): DOMDocument
    {
        if ($this->dom instanceof php4DOMDocument) {
            return $this->dom->myDOMDocument;
        }
        /** @var DOMDocument $dom */
        $dom = $this->dom;
        return $dom;
    }

    public function setId(int $a_id): void
    {
        $this->id = $a_id;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setParentId(int $a_id): void
    {
        $this->parent_id = $a_id;
    }

    public function getParentId(): int
    {
        return $this->parent_id;
    }

    /**
     * @param mixed $a_parameters
     */
    public function addUpdateListener(
        object $a_object,
        string $a_method,
        $a_parameters = ""
    ): void {
        $cnt = $this->update_listener_cnt;
        $this->update_listeners[$cnt]["object"] = $a_object;
        $this->update_listeners[$cnt]["method"] = $a_method;
        $this->update_listeners[$cnt]["parameters"] = $a_parameters;
        $this->update_listener_cnt++;
    }

    public function callUpdateListeners(): void
    {
        for ($i = 0; $i < $this->update_listener_cnt; $i++) {
            $object = $this->update_listeners[$i]["object"];
            $method = $this->update_listeners[$i]["method"];
            $parameters = $this->update_listeners[$i]["parameters"];
            $object->$method($parameters);
        }
    }

    public function setActive(bool $a_active): void
    {
        $this->active = $a_active;
    }

    public function getActive(
        bool $a_check_scheduled_activation = false
    ): bool {
        if ($a_check_scheduled_activation && !$this->active) {
            $start = new ilDateTime($this->getActivationStart(), IL_CAL_DATETIME);
            $end = new ilDateTime($this->getActivationEnd(), IL_CAL_DATETIME);
            $now = new ilDateTime(time(), IL_CAL_UNIX);
            if (!ilDateTime::_before($now, $start) && !ilDateTime::_after($now, $end)) {
                return true;
            }
        }
        return $this->active;
    }

    /**
     * Preload activation data by Parent Id
     */
    public static function preloadActivationDataByParentId(int $a_parent_id): void
    {
        global $DIC;

        $db = $DIC->database();
        $set = $db->query(
            "SELECT page_id, parent_type, lang, active, activation_start, activation_end, show_activation_info FROM page_object " .
            " WHERE parent_id = " . $db->quote($a_parent_id, "integer")
        );
        while ($rec = $db->fetchAssoc($set)) {
            self::$activation_data[$rec["page_id"] . ":" . $rec["parent_type"] . ":" . $rec["lang"]] = $rec;
        }
    }

    /**
     * lookup activation status
     */
    public static function _lookupActive(
        int $a_id,
        string $a_parent_type,
        bool $a_check_scheduled_activation = false,
        string $a_lang = "-"
    ): bool {
        global $DIC;

        $db = $DIC->database();

        // language must be set at least to "-"
        if ($a_lang == "") {
            $a_lang = "-";
        }

        if (isset(self::$activation_data[$a_id . ":" . $a_parent_type . ":" . $a_lang])) {
            $rec = self::$activation_data[$a_id . ":" . $a_parent_type . ":" . $a_lang];
        } else {
            $set = $db->queryF(
                "SELECT active, activation_start, activation_end FROM page_object WHERE page_id = %s" .
                " AND parent_type = %s AND lang = %s",
                array("integer", "text", "text"),
                array($a_id, $a_parent_type, $a_lang)
            );
            $rec = $db->fetchAssoc($set);
            if (!$rec) {
                return true;
            }
        }

        $rec["n"] = ilUtil::now();
        if (!$rec["active"] && $a_check_scheduled_activation) {
            if ($rec["n"] >= $rec["activation_start"] &&
                $rec["n"] <= $rec["activation_end"]) {
                return true;
            }
        }

        return (bool) $rec["active"];
    }

    /**
     * Check whether page is activated by time schedule
     */
    public static function _isScheduledActivation(
        int $a_id,
        string $a_parent_type,
        string $a_lang = "-"
    ): bool {
        global $DIC;

        $db = $DIC->database();

        // language must be set at least to "-"
        if ($a_lang == "") {
            $a_lang = "-";
        }

        //echo "<br>";
        //var_dump(self::$activation_data); exit;
        if (isset(self::$activation_data[$a_id . ":" . $a_parent_type . ":" . $a_lang])) {
            $rec = self::$activation_data[$a_id . ":" . $a_parent_type . ":" . $a_lang];
        } else {
            $set = $db->queryF(
                "SELECT active, activation_start, activation_end FROM page_object WHERE page_id = %s" .
                " AND parent_type = %s AND lang = %s",
                array("integer", "text", "text"),
                array($a_id, $a_parent_type, $a_lang)
            );
            $rec = $db->fetchAssoc($set);
        }

        if (!$rec["active"] && $rec["activation_start"] != "") {
            return true;
        }

        return false;
    }

    /**
     * write activation status
     */
    public static function _writeActive(
        int $a_id,
        string $a_parent_type,
        bool $a_active
    ): void {
        global $DIC;

        $db = $DIC->database();

        // language must be set at least to "-"
        $a_lang = "-";

        $db->manipulateF(
            "UPDATE page_object SET active = %s, activation_start = %s, " .
            " activation_end = %s WHERE page_id = %s" .
            " AND parent_type = %s AND lang = %s",
            array("int", "timestamp", "timestamp", "integer", "text", "text"),
            array((int) $a_active, null, null, $a_id, $a_parent_type, $a_lang)
        );
    }

    /**
     * Lookup activation data
     */
    public static function _lookupActivationData(
        int $a_id,
        string $a_parent_type,
        string $a_lang = "-"
    ): array {
        global $DIC;

        $db = $DIC->database();

        // language must be set at least to "-"
        if ($a_lang == "") {
            $a_lang = "-";
        }

        if (isset(self::$activation_data[$a_id . ":" . $a_parent_type . ":" . $a_lang])) {
            $rec = self::$activation_data[$a_id . ":" . $a_parent_type . ":" . $a_lang];
        } else {
            $set = $db->queryF(
                "SELECT active, activation_start, activation_end, show_activation_info FROM page_object WHERE page_id = %s" .
                " AND parent_type = %s AND lang = %s",
                array("integer", "text", "text"),
                array($a_id, $a_parent_type, $a_lang)
            );
            $rec = $db->fetchAssoc($set);
        }

        return $rec;
    }

    public static function lookupParentId(int $a_id, string $a_type): int
    {
        global $DIC;

        $db = $DIC->database();

        $res = $db->query("SELECT parent_id FROM page_object WHERE page_id = " . $db->quote($a_id, "integer") . " " .
            "AND parent_type=" . $db->quote($a_type, "text"));
        $rec = $db->fetchAssoc($res);
        return (int) ($rec["parent_id"] ?? 0);
    }

    public static function _writeParentId(string $a_parent_type, int $a_pg_id, int $a_par_id): void
    {
        global $DIC;

        $db = $DIC->database();
        $db->manipulateF(
            "UPDATE page_object SET parent_id = %s WHERE page_id = %s" .
            " AND parent_type = %s",
            array("integer", "integer", "text"),
            array($a_par_id, $a_pg_id, $a_parent_type)
        );
    }

    /**
     * @param string $a_activationstart IL_CAL_DATETIME format
     */
    public function setActivationStart(?string $a_activationstart): void
    {
        if ($a_activationstart == "") {
            $a_activationstart = null;
        }
        $this->activationstart = $a_activationstart;
    }

    public function getActivationStart(): ?string
    {
        return $this->activationstart;
    }

    /**
     * Set Activation End.
     * @param string $a_activationend IL_CAL_DATETIME format
     */
    public function setActivationEnd(?string $a_activationend): void
    {
        if ($a_activationend == "") {
            $a_activationend = null;
        }
        $this->activationend = $a_activationend;
    }

    public function getActivationEnd(): ?string
    {
        return $this->activationend;
    }

    /**
     * Get a content object of the page
     */
    public function getContentObject(
        string $a_hier_id,
        string $a_pc_id = ""
    ): ?ilPageContent {
        $node = $this->page_manager->content($this->getDomDoc())->getContentDomNode(
            $a_hier_id,
            $a_pc_id
        );
        return $this->pc_service->getByNode($node, $this);
    }

    /**
     * Get content object for pc id
     */
    public function getContentObjectForPcId(string $pcid): ?ilPageContent
    {
        $hier_ids = $this->getHierIdsForPCIds([$pcid]);
        return $this->getContentObject($hier_ids[$pcid], $pcid);
    }

    /**
     * Get parent content object for pc id
     */
    public function getParentContentObjectForPcId(string $pcid): ?ilPageContent
    {
        $content_object = $this->getContentObjectForPcId($pcid);
        $node = $content_object->getNode();
        $node = $node->parent_node();
        while ($node) {
            if ($node->node_name() == "PageContent") {
                $pcid = $node->get_attribute("PCID");
                if ($pcid != "") {
                    return $this->getContentObjectForPcId($pcid);
                }
            }
            $node = $node->parent_node();
        }
        return null;
    }

    /**
     * @deprecated use getContentDomNode instead
     */
    public function getContentNode(string $a_hier_id, string $a_pc_id = ""): ?php4DOMElement
    {
        $xpc = xpath_new_context($this->dom);
        if ($a_hier_id == "pg") {
            return $this->node;
        } else {
            // get per pc id
            if ($a_pc_id != "") {
                $path = "//*[@PCID = '$a_pc_id']";
                $res = xpath_eval($xpc, $path);
                if (count($res->nodeset) == 1) {
                    $cont_node = $res->nodeset[0];
                    return $cont_node;
                }
            }

            // fall back to hier id
            $path = "//*[@HierId = '$a_hier_id']";
            $res = xpath_eval($xpc, $path);
            if (count($res->nodeset) == 1) {
                $cont_node = $res->nodeset[0];
                return $cont_node;
            }
        }
        return null;
    }

    public function getContentDomNode(string $a_hier_id, string $a_pc_id = ""): ?DOMNode
    {
        return $this->getContentNode($a_hier_id, $a_pc_id)?->myDOMNode;
    }

    public function getNode(): php4DOMElement
    {
        return $this->node;
    }

    /**
     * set xml content of page, start with <PageObject...>,
     * end with </PageObject>, comply with ILIAS DTD, omit MetaData, use utf-8!
     * @param string $a_encoding encoding of the content (here is no conversion done!
     *                           it must be already utf-8 encoded at the time)
     */
    public function setXMLContent(string $a_xml, string $a_encoding = "UTF-8"): void
    {
        $this->encoding = $a_encoding;
        $this->xml = $a_xml;
    }

    /**
     * append xml content to page
     * setXMLContent must be called before and the same encoding must be used
s     */
    public function appendXMLContent(string $a_xml): void
    {
        $this->xml .= $a_xml;
    }

    /**
     * get xml content of page
     */
    public function getXMLContent(bool $a_incl_head = false): string
    {
        // build full http path for XML DOCTYPE header.
        // Under windows a relative path doesn't work :-(
        if ($a_incl_head) {
            //echo "+".$this->encoding."+";
            $enc_str = (!empty($this->encoding))
                ? "encoding=\"" . $this->encoding . "\""
                : "";
            return "<?xml version=\"1.0\" $enc_str ?>" .
                "<!DOCTYPE PageObject SYSTEM \"" . $this->getIliasAbsolutePath() . "/xml/" . $this->cur_dtd . "\">" .
                $this->xml;
        } else {
            return $this->xml;
        }
    }

    protected function getIliasAbsolutePath(): string
    {
        return ILIAS_ABSOLUTE_PATH;
    }

    /**
     * Copy content of page; replace page components with copies
     * where necessary (e.g. questions)
     * @return string|string[]|null
     */
    public function copyXmlContent(
        bool $a_clone_mobs = false,
        int $a_new_parent_id = 0,
        int $obj_copy_id = 0
    ): string {
        $xml = $this->getXMLContent();
        $temp_dom = domxml_open_mem(
            '<?xml version="1.0" encoding="UTF-8"?>' . $xml,
            DOMXML_LOAD_PARSING,
            $error
        );
        if (empty($error)) {
            $this->handleCopiedContent($temp_dom, true, $a_clone_mobs, $a_new_parent_id, $obj_copy_id);
        }
        $xml = $temp_dom->dump_mem(0, $this->encoding);
        $xml = preg_replace('/<\?xml[^>]*>/i', "", $xml);
        $xml = preg_replace('/<!DOCTYPE[^>]*>/i', "", $xml);

        return $xml;
    }

    // @todo 1: begin: generalize, remove concrete dependencies

    /**
     * Handle copied content
     * This function copies items, that must be copied, if page
     * content is duplicated.
     * Currently called by
     * - copyXmlContent
     * - called by pasteContents
     * - called by ilPageEditorGUI->paste -> pasteContents
     */
    public function handleCopiedContent(
        php4DOMDocument $a_dom,
        bool $a_self_ass = true,
        bool $a_clone_mobs = false,
        int $new_parent_id = 0,
        int $obj_copy_id = 0
    ): void {
        $defs = $this->pc_definition->getPCDefinitions();

        // @todo 1: move all functions from above to the new domdoc
        $dom = $a_dom;
        if ($a_dom instanceof php4DOMDocument) {
            $dom = $a_dom->myDOMDocument;
        }
        foreach ($defs as $def) {
            $cl = $def["pc_class"];
            if ($cl == 'ilPCPlugged') {
                // the page object is provided for ilPageComponentPlugin
                ilPCPlugged::handleCopiedPluggedContent($this, $dom);
            } else {
                $cl::handleCopiedContent($dom, $a_self_ass, $a_clone_mobs, $new_parent_id, $obj_copy_id);
            }
        }
    }

    /**
     * Handle content before deletion
     * This currently treats only plugged content
     * If no node is given, then the whole dom will be scanned
     * @param php4DOMNode|DOMNode|null $a_node
     */
    public function handleDeleteContent($a_node = null, $move_operation = false): void
    {
        $pm = $this->page_manager->content($this->getDomDoc());
        $pm->handleDeleteContent($a_node, $move_operation);
    }

    /**
     * get xml content of page from dom
     * (use this, if any changes are made to the document)
     */
    public function getXMLFromDom(
        bool $a_incl_head = false,
        bool $a_append_mobs = false,
        bool $a_append_bib = false,
        string $a_append_str = "",
        bool $a_omit_pageobject_tag = false,
        int $style_id = 0
    ): string {
        if ($a_incl_head) {
            //echo "\n<br>#".$this->encoding."#";
            return $this->dom->dump_mem(0, $this->encoding);
        } else {
            // append multimedia object elements
            if ($a_append_mobs || $a_append_bib) {
                $mobs = "";
                $bibs = "";
                if ($a_append_mobs) {
                    $mobs = $this->getMultimediaXML();
                }
                if ($a_append_bib) {
                    // deprecated
                    //					$bibs = $this->getBibliographyXML();
                }
                $trans = $this->getLanguageVariablesXML($style_id);
                //echo htmlentities($this->dom->dump_node($this->node)); exit;
                return "<dummy>" . $this->dom->dump_node($this->node) . $mobs . $bibs . $trans . $a_append_str . "</dummy>";
            } else {
                if (is_object($this->dom)) {
                    if ($a_omit_pageobject_tag) {
                        $xml = "";
                        $childs = $this->node->child_nodes();
                        for ($i = 0, $iMax = count($childs); $i < $iMax; $i++) {
                            $xml .= $this->dom->dump_node($childs[$i]);
                        }
                    } else {
                        $xml = $this->dom->dump_mem(0, $this->encoding);
                        $xml = preg_replace('/<\?xml[^>]*>/i', "", $xml);
                        $xml = preg_replace('/<!DOCTYPE[^>]*>/i', "", $xml);

                        // don't use dump_node. This gives always entities.
                        //return $this->dom->dump_node($this->node);
                    }
                    return $xml;
                } else {
                    return "";
                }
            }
        }
    }

    /**
     * Get language variables as XML
     */
    public function getLanguageVariablesXML(int $style_id = 0): string
    {
        $xml = "<LVs>";
        $lang_vars = array(
            "ed_paste_clip",
            "ed_edit",
            "ed_edit_prop",
            "ed_delete",
            "ed_moveafter",
            "ed_movebefore",
            "ed_go",
            "ed_class",
            "ed_width",
            "ed_align_left",
            "ed_align_right",
            "ed_align_center",
            "ed_align_left_float",
            "ed_align_right_float",
            "ed_delete_item",
            "ed_new_item_before",
            "ed_new_item_after",
            "ed_copy_clip",
            "please_select",
            "ed_split_page",
            "ed_item_up",
            "ed_item_down",
            "ed_split_page_next",
            "ed_enable",
            "de_activate",
            "ed_paste",
            "ed_edit_multiple",
            "ed_cut",
            "ed_copy",
            "ed_insert_templ",
            "ed_click_to_add_pg",
            "download"
        );

        // collect lang vars from pc elements
        $defs = $this->pc_definition->getPCDefinitions();
        foreach ($defs as $def) {
            $lang_vars[] = "pc_" . $def["pc_type"];
            $lang_vars[] = "ed_insert_" . $def["pc_type"];

            $cl = $def["pc_class"];
            $lvs = call_user_func($def["pc_class"] . '::getLangVars');
            foreach ($lvs as $lv) {
                $lang_vars[] = $lv;
            }
        }

        // workaround for #30561, should go to characteristic manager
        $dummy_pc = new ilPCSectionGUI($this, null, "");
        $dummy_pc->setStyleId($style_id);
        foreach (["section", "table", "flist_li", "list_u", "list_o",
                  "table", "table_cell"] as $type) {
            $dummy_pc->getCharacteristicsOfCurrentStyle([$type]);
            foreach ($dummy_pc->getCharacteristics() as $char => $txt) {
                $xml .= "<LV name=\"char_" . $type . "_" . $char . "\" value=\"" . $txt . "\"/>";
            }
        }
        $type = "media_cont";
        $dummy_pc = new ilPCMediaObjectGUI($this, null, "");
        $dummy_pc->setStyleId($style_id);
        $dummy_pc->getCharacteristicsOfCurrentStyle([$type]);
        foreach ($dummy_pc->getCharacteristics() as $char => $txt) {
            $xml .= "<LV name=\"char_" . $type . "_" . $char . "\" value=\"" . $txt . "\"/>";
        }
        foreach (["text_block", "heading1", "heading2", "heading3"] as $type) {
            $dummy_pc = new ilPCParagraphGUI($this, null, "");
            $dummy_pc->setStyleId($style_id);
            $dummy_pc->getCharacteristicsOfCurrentStyle([$type]);
            foreach ($dummy_pc->getCharacteristics() as $char => $txt) {
                $xml .= "<LV name=\"char_" . $type . "_" . $char . "\" value=\"" . $txt . "\"/>";
            }
        }
        foreach ($lang_vars as $lang_var) {
            $xml .= $this->getLangVarXML($lang_var);
        }
        $xml .= "</LVs>";
        return $xml;
    }

    protected function getLangVarXML(string $var): string
    {
        $val = $this->lng->txt("cont_" . $var);
        $val = str_replace('"', "&quot;", $val);
        return "<LV name=\"$var\" value=\"" . $val . "\"/>";
    }

    public function getFirstParagraphText(): string
    {
        return $this->pc_service->paragraph()->getFirstParagraphText($this);
    }

    public function getParagraphForPCID(string $pcid): ?ilPCParagraph
    {
        return $this->pc_service->paragraph()->getParagraphForPCID($this, $pcid);
    }


    /**
     * lm parser set this flag to true, if the page contains intern links
     * (this method should only be called by the import parser)
     * todo: move to ilLMPageObject !?
     * @param bool $a_contains_link true, if page contains intern link tag(s)
     */
    // @todo: can we do this better
    public function setContainsIntLink(bool $a_contains_link): void
    {
        $this->contains_int_link = $a_contains_link;
    }

    /**
     * returns true, if page was marked as containing an intern link (via setContainsIntLink)
     * (this method should only be called by the import parser)
     */
    // @todo: can we do this better
    public function containsIntLink(): bool
    {
        return $this->contains_int_link;
    }

    public function setImportMode(bool $a_val): void
    {
        $this->import_mode = $a_val;
    }

    public function getImportMode(): bool
    {
        return $this->import_mode;
    }

    public function needsImportParsing(?bool $a_parse = null): bool
    {
        if ($a_parse === true) {
            $this->needs_parsing = true;
        }
        if ($a_parse === false) {
            $this->needs_parsing = false;
        }
        return $this->needs_parsing;
    }

    // @todo: can we do this better
    public function setContainsQuestion(bool $a_val): void
    {
        $this->contains_question = $a_val;
    }

    public function getContainsQuestion(): bool
    {
        return $this->contains_question;
    }


    /**
     * get all media objects, that are referenced and used within
     * the page
     */
    public function collectMediaObjects(bool $a_inline_only = true): array
    {
        $mob_manager = $this->pc_service->mediaObject();
        return $mob_manager->collectMediaObjects($this->getDomDoc(), $a_inline_only);
    }

    /**
     * get all internal links that are used within the page
     */
    public function getInternalLinks(): array
    {
        return $this->link->getInternalLinks($this->getDomDoc());
    }

    /**
     * get a xml string that contains all media object elements, that
     * are referenced by any media alias in the page
     */
    public function getMultimediaXML(): string
    {
        $mob_manager = $this->pc_service->mediaObject();
        return $mob_manager->getMultimediaXML($this->getDomDoc());
    }

    /**
     * get complete media object (alias) element
     */
    public function getMediaAliasElement(int $a_mob_id, int $a_nr = 1): string
    {
        $mob_manager = $this->pc_service->mediaObject();
        return $mob_manager->getMediaAliasElement(
            $this->getDomDoc(),
            $a_mob_id,
            $a_nr
        );
    }

    /**
     * Validate the page content agains page DTD
     */
    public function validateDom(): ?array
    {
        $this->stripHierIDs();

        // possible fix for #14820
        //libxml_disable_entity_loader(false);

        $error = null;
        $this->dom->validate($error);
        return $error;
    }

    /**
     * Add hierarchical ID (e.g. for editing) attributes "HierId" to current dom tree.
     */
    public function addHierIDs(): void
    {
        $this->content_id_manager->addHierIDsToDom();
    }

    public function getHierIds(): array
    {
        return $this->content_id_manager->getHierIds();
    }

    public function stripHierIDs(): void
    {
        $this->content_id_manager->stripHierIDsFromDom();
    }

    public function stripPCIDs(): void
    {
        $this->content_id_manager->stripPCIDs();
    }

    /**
     * Get hier ids for a set of pc ids
     */
    public function getHierIdsForPCIds(array $a_pc_ids): array
    {
        return $this->content_id_manager->getHierIdsForPCIds($a_pc_ids);
    }

    public function getHierIdForPcId(string $pcid): string
    {
        return $this->content_id_manager->getHierIdForPcId($pcid);
    }

    /**
     * Get hier ids for a set of pc ids
     */
    public function getPCIdsForHierIds(array $hier_ids): array
    {
        return $this->content_id_manager->getPCIdsForHierIds($hier_ids);
    }

    public function getPCIdForHierId(string $hier_id): string
    {
        return $this->content_id_manager->getPCIdForHierId($hier_id);
    }

    /**
     * add file sizes
     */
    public function addFileSizes(): void
    {
        $this->pc_service->fileList()->addFileSizes($this->getDomDoc());
    }

    /**
     * Resolves all internal link targets of the page, if targets are available
     * (after import)
     */
    public function resolveIntLinks(array $a_link_map = null): bool
    {
        $this->link->resolveIntLinks($this->getDomDoc(), $a_link_map);
    }

    /**
     * Resolve media aliases
     * (after import)
     */
    public function resolveMediaAliases(
        array $a_mapping,
        bool $a_reuse_existing_by_import = false
    ): bool {
        return $this->pc_service->mediaObject()->resolveMediaAliases(
            $this->getDomDoc(),
            $a_mapping,
            $a_reuse_existing_by_import
        );
    }

    /**
     * Resolve iim media aliases
     * (in ilContObjParse)
     */
    public function resolveIIMMediaAliases(array $a_mapping): bool
    {
        return $this->pc_service->interactiveImage()->resolveIIMMediaAliases(
            $this->getDomDoc(),
            $a_mapping
        );
    }

    /**
     * Resolve file items
     * (after import)
     */
    public function resolveFileItems(array $a_mapping): bool
    {
        return $this->pc_service->fileList()->resolveFileItems(
            $this->getDomDoc(),
            $a_mapping
        );
    }

    /**
     * Resolve all quesiont references
     * (after import)
     * @todo: move to question classes
     */
    public function resolveQuestionReferences(array $a_mapping): bool
    {
        // resolve normal internal links
        $xpc = xpath_new_context($this->dom);
        $path = "//Question";
        $res = xpath_eval($xpc, $path);
        $updated = false;
        for ($i = 0, $iMax = count($res->nodeset); $i < $iMax; $i++) {
            $qref = $res->nodeset[$i]->get_attribute("QRef");

            if (isset($a_mapping[$qref])) {
                $res->nodeset[$i]->set_attribute("QRef", "il__qst_" . $a_mapping[$qref]["pool"]);
                $updated = true;
            }
        }
        unset($xpc);

        return $updated;
    }


    /**
     * Move internal links from one destination to another. This is used
     * for pages and structure links. Just use IDs in "from" and "to".
     * @todo: generalize, internal links usage info
     */
    public function moveIntLinks(array $a_from_to): bool
    {
        $this->buildDom();

        $changed = false;

        // resolve normal internal links
        $xpc = xpath_new_context($this->dom);
        $path = "//IntLink";
        $res = xpath_eval($xpc, $path);
        for ($i = 0, $iMax = count($res->nodeset); $i < $iMax; $i++) {
            $target = $res->nodeset[$i]->get_attribute("Target");
            $type = $res->nodeset[$i]->get_attribute("Type");
            $obj_id = ilInternalLink::_extractObjIdOfTarget($target);
            if (($a_from_to[$obj_id] ?? 0) > 0 && is_int(strpos($target, "__"))) {
                if ($type == "PageObject" && ilLMObject::_lookupType($a_from_to[$obj_id]) == "pg") {
                    $res->nodeset[$i]->set_attribute("Target", "il__pg_" . $a_from_to[$obj_id]);
                    $changed = true;
                }
                if ($type == "StructureObject" && ilLMObject::_lookupType($a_from_to[$obj_id]) == "st") {
                    $res->nodeset[$i]->set_attribute("Target", "il__st_" . $a_from_to[$obj_id]);
                    $changed = true;
                }
                if ($type == "PortfolioPage") {
                    $res->nodeset[$i]->set_attribute("Target", "il__ppage_" . $a_from_to[$obj_id]);
                    $changed = true;
                }
            }
        }
        unset($xpc);

        // map areas
        $this->addHierIDs();
        $xpc = xpath_new_context($this->dom);
        $path = "//MediaAlias";
        $res = xpath_eval($xpc, $path);

        for ($i = 0, $iMax = count($res->nodeset); $i < $iMax; $i++) {
            $media_object_node = $res->nodeset[$i]->parent_node();
            $page_content_node = $media_object_node->parent_node();
            $c_hier_id = $page_content_node->get_attribute("HierId");

            // first check, wheter we got instance map areas -> take these
            $std_alias_item = new ilMediaAliasItem(
                $this->dom,
                $c_hier_id,
                "Standard"
            );
            $areas = $std_alias_item->getMapAreas();
            $correction_needed = false;
            if (count($areas) > 0) {
                // check if correction needed
                foreach ($areas as $area) {
                    if ($area["Type"] == "PageObject" ||
                        $area["Type"] == "StructureObject") {
                        $t = $area["Target"];
                        $tid = ilInternalLink::_extractObjIdOfTarget($t);
                        if ($a_from_to[$tid] > 0) {
                            $correction_needed = true;
                        }
                    }
                }
            } else {
                $areas = array();

                // get object map areas and check whether at least one must
                // be corrected
                $oid = $res->nodeset[$i]->get_attribute("OriginId");
                if (substr($oid, 0, 4) == "il__") {
                    $id_arr = explode("_", $oid);
                    $id = $id_arr[count($id_arr) - 1];

                    $mob = new ilObjMediaObject($id);
                    $med_item = $mob->getMediaItem("Standard");
                    $med_areas = $med_item->getMapAreas();

                    foreach ($med_areas as $area) {
                        $link_type = ($area->getLinkType() == "int")
                            ? "IntLink"
                            : "ExtLink";

                        $areas[] = array(
                            "Nr" => $area->getNr(),
                            "Shape" => $area->getShape(),
                            "Coords" => $area->getCoords(),
                            "Link" => array(
                                "LinkType" => $link_type,
                                "Href" => $area->getHref(),
                                "Title" => $area->getTitle(),
                                "Target" => $area->getTarget(),
                                "Type" => $area->getType(),
                                "TargetFrame" => $area->getTargetFrame()
                            )
                        );

                        if ($area->getType() == "PageObject" ||
                            $area->getType() == "StructureObject") {
                            $t = $area->getTarget();
                            $tid = ilInternalLink::_extractObjIdOfTarget($t);
                            if ($a_from_to[$tid] > 0) {
                                $correction_needed = true;
                            }
                            //var_dump($a_from_to);
                        }
                    }
                }
            }

            // correct map area links
            if ($correction_needed) {
                $changed = true;
                $std_alias_item->deleteAllMapAreas();
                foreach ($areas as $area) {
                    if ($area["Link"]["LinkType"] == "IntLink") {
                        $target = $area["Link"]["Target"];
                        $type = $area["Link"]["Type"];
                        $obj_id = ilInternalLink::_extractObjIdOfTarget($target);
                        if ($a_from_to[$obj_id] > 0) {
                            if ($type == "PageObject" && ilLMObject::_lookupType($a_from_to[$obj_id]) == "pg") {
                                $area["Link"]["Target"] = "il__pg_" . $a_from_to[$obj_id];
                            }
                            if ($type == "StructureObject" && ilLMObject::_lookupType($a_from_to[$obj_id]) == "st") {
                                $area["Link"]["Target"] = "il__st_" . $a_from_to[$obj_id];
                            }
                        }
                    }

                    $std_alias_item->addMapArea(
                        $area["Shape"],
                        $area["Coords"],
                        $area["Link"]["Title"],
                        array("Type" => $area["Link"]["Type"],
                              "TargetFrame" => $area["Link"]["TargetFrame"],
                              "Target" => $area["Link"]["Target"],
                              "Href" => $area["Link"]["Href"],
                              "LinkType" => $area["Link"]["LinkType"],
                        )
                    );
                }
            }
        }
        unset($xpc);

        return $changed;
    }

    /**
     * Change targest of repository links. Use full targets in "from" and "to"!!!
     * @todo: generalize, internal links usage info
     */
    public static function _handleImportRepositoryLinks(
        int $a_rep_import_id,
        string $a_rep_type,
        int $a_rep_ref_id
    ): void {
        //echo "-".$a_rep_import_id."-".$a_rep_ref_id."-";
        $sources = ilInternalLink::_getSourcesOfTarget(
            "obj",
            ilInternalLink::_extractObjIdOfTarget($a_rep_import_id),
            ilInternalLink::_extractInstOfTarget($a_rep_import_id)
        );
        //var_dump($sources);
        foreach ($sources as $source) {
            if ($source["type"] == "lm:pg") {
                if (self::_exists("lm", $source["id"], $source["lang"])) {
                    $page_obj = new ilLMPage($source["id"], 0, $source["lang"]);
                    if (!$page_obj->page_not_found) {
                        $page_obj->handleImportRepositoryLink(
                            $a_rep_import_id,
                            $a_rep_type,
                            $a_rep_ref_id
                        );
                    }
                    $page_obj->update();
                }
            }
        }
    }

    // @todo: generalize, internal links usage info
    public function handleImportRepositoryLink(
        string $a_rep_import_id,
        string $a_rep_type,
        int $a_rep_ref_id
    ): void {
        $this->buildDom();

        // resolve normal internal links
        $xpc = xpath_new_context($this->dom);
        $path = "//IntLink";
        $res = xpath_eval($xpc, $path);
        //echo "1";
        for ($i = 0, $iMax = count($res->nodeset); $i < $iMax; $i++) {
            //echo "2";
            $target = $res->nodeset[$i]->get_attribute("Target");
            $type = $res->nodeset[$i]->get_attribute("Type");
            if ($target == $a_rep_import_id && $type == "RepositoryItem") {
                //echo "setting:"."il__".$a_rep_type."_".$a_rep_ref_id;
                $res->nodeset[$i]->set_attribute(
                    "Target",
                    "il__" . $a_rep_type . "_" . $a_rep_ref_id
                );
            }
        }
        unset($xpc);
    }

    /**
     * Handle repository links on copy process
     */
    public function handleRepositoryLinksOnCopy(
        array $a_mapping,
        int $a_source_ref_id
    ): void {
        $type = "";
        $tree = $this->tree;
        $objDefinition = $this->obj_definition;

        $this->buildDom();
        $this->log->debug("Handle repository links...");

        // pc classes hook, @todo: move rest of function to this hook, too
        $defs = $this->pc_definition->getPCDefinitions();
        foreach ($defs as $def) {
            if (method_exists($def["pc_class"], 'afterRepositoryCopy')) {
                call_user_func($def["pc_class"] . '::afterRepositoryCopy', $this, $a_mapping, $a_source_ref_id);
            }
        }


        // resolve normal internal links
        $xpc = xpath_new_context($this->dom);
        $path = "//IntLink";
        $res = xpath_eval($xpc, $path);
        for ($i = 0, $iMax = count($res->nodeset); $i < $iMax; $i++) {
            $target = $res->nodeset[$i]->get_attribute("Target");
            $type = $res->nodeset[$i]->get_attribute("Type");
            $this->log->debug("Target: " . $target);
            $t = explode("_", $target);
            if ($type == "RepositoryItem" && ((int) $t[1] == 0 || (int) $t[1] == IL_INST_ID)) {
                if (isset($a_mapping[$t[3]])) {
                    // we have a mapping -> replace the ID
                    $this->log->debug("... replace " . $t[3] . " with " . $a_mapping[$t[3]] . ".");
                    $res->nodeset[$i]->set_attribute(
                        "Target",
                        "il__obj_" . $a_mapping[$t[3]]
                    );
                } elseif ($this->tree->isGrandChild($a_source_ref_id, $t[3])) {
                    // we have no mapping, but the linked object is child of the original node -> remove link
                    $this->log->debug("... remove links.");
                    if ($res->nodeset[$i]->parent_node()->node_name() == "MapArea") {    // simply remove map areas
                        $parent = $res->nodeset[$i]->parent_node();
                        $parent->unlink_node($parent);
                    } else {    // replace link by content of the link for other internal links
                        $source_node = $res->nodeset[$i];
                        $new_node = $source_node->clone_node(true);
                        $new_node->unlink_node($new_node);
                        $childs = $new_node->child_nodes();
                        for ($j = 0, $jMax = count($childs); $j < $jMax; $j++) {
                            $this->log->debug("... move node $j " . $childs[$j]->node_name() . " before " . $source_node->node_name());
                            $source_node->insert_before($childs[$j], $source_node);
                        }
                        $source_node->unlink_node($source_node);
                    }
                }
            }
        }
        unset($xpc);

        // resolve normal external links
        $ilias_url = parse_url(ILIAS_HTTP_PATH);
        $xpc = xpath_new_context($this->dom);
        $path = "//ExtLink";
        $res = xpath_eval($xpc, $path);
        for ($i = 0, $iMax = count($res->nodeset); $i < $iMax; $i++) {
            $href = $res->nodeset[$i]->get_attribute("Href");
            $this->log->debug("Href: " . $href);

            $url = parse_url($href);

            // only handle links on same host
            $this->log->debug("Host: " . ($url["host"] ?? ""));
            if (($url["host"] ?? "") !== "" && $url["host"] !== $ilias_url["host"]) {
                continue;
            }

            // get parameters
            $par = [];
            if (substr($href, strlen($href) - 5) === ".html") {
                $parts = explode(
                    "_",
                    basename(
                        substr($url["path"], 0, strlen($url["path"]) - 5)
                    )
                );
                if (array_shift($parts) !== "goto") {
                    continue;
                }
                $par["client_id"] = array_shift($parts);
                $par["target"] = implode("_", $parts);
            } else {
                foreach (explode("&", ($url["query"] ?? "")) as $p) {
                    $p = explode("=", $p);
                    if (isset($p[0]) && isset($p[1])) {
                        $par[$p[0]] = $p[1];
                    }
                }
            }

            $target_client_id = $par["client_id"] ?? "";
            if ($target_client_id != "" && $target_client_id != CLIENT_ID) {
                continue;
            }

            // get ref id
            $ref_id = 0;
            if (is_int(strpos($href, "ilias.php"))) {
                $ref_id = (int) ($par["ref_id"] ?? 0);
            } elseif (isset($par["target"]) && $par["target"] !== "") {
                $t = explode("_", $par["target"]);
                if ($objDefinition->isRBACObject($t[0] ?? "")) {
                    $ref_id = (int) ($t[1] ?? 0);
                    $type = $t[0] ?? "";
                }
            }
            if ($ref_id > 0) {
                if (isset($a_mapping[$ref_id])) {
                    $new_ref_id = $a_mapping[$ref_id];
                    // we have a mapping -> replace the ID
                    if (is_int(strpos($href, "ilias.php"))) {
                        $new_href = str_replace("ref_id=" . ($par["ref_id"] ?? ""), "ref_id=" . $new_ref_id, $href);
                    } else {
                        $nt = str_replace($type . "_" . $ref_id, $type . "_" . $new_ref_id, $par["target"]);
                        $new_href = str_replace($par["target"], $nt, $href);
                    }
                    if ($new_href != "") {
                        $this->log->debug("... ext link replace " . $href . " with " . $new_href . ".");
                        $res->nodeset[$i]->set_attribute("Href", $new_href);
                    }
                } elseif ($tree->isGrandChild($a_source_ref_id, $ref_id)) {
                    // we have no mapping, but the linked object is child of the original node -> remove link
                    $this->log->debug("... remove ext links.");
                    if ($res->nodeset[$i]->parent_node()->node_name() == "MapArea") {    // simply remove map areas
                        $parent = $res->nodeset[$i]->parent_node();
                        $parent->unlink_node($parent);
                    } else {    // replace link by content of the link for other internal links
                        $source_node = $res->nodeset[$i];
                        $new_node = $source_node->clone_node(true);
                        $new_node->unlink_node($new_node);
                        $childs = $new_node->child_nodes();
                        for ($j = 0, $jMax = count($childs); $j < $jMax; $j++) {
                            $this->log->debug("... move node $j " . $childs[$j]->node_name() . " before " . $source_node->node_name());
                            $source_node->insert_before($childs[$j], $source_node);
                        }
                        $source_node->unlink_node($source_node);
                    }
                }
            }
        }
        unset($xpc);
    }

    /**
     * Create new page object with current xml content
     */
    public function createFromXML(): void
    {
        $empty = false;
        if ($this->getXMLContent() == "") {
            $this->setXMLContent("<PageObject></PageObject>");
            $empty = true;
        }

        $content = $this->getXMLContent();
        $this->buildDom(true);
        $dom_doc = $this->getDomDoc();

        $iel = $this->containsDeactivatedElements($content);
        $inl = $this->containsIntLinks($content);

        // create object
        $this->db->insert("page_object", array(
            "page_id" => array("integer", $this->getId()),
            "parent_id" => array("integer", $this->getParentId()),
            "lang" => array("text", $this->getLanguage()),
            "content" => array("clob", $content),
            "parent_type" => array("text", $this->getParentType()),
            "create_user" => array("integer", $this->user->getId()),
            "last_change_user" => array("integer", $this->user->getId()),
            "active" => array("integer", (int) $this->getActive()),
            "activation_start" => array("timestamp", $this->getActivationStart()),
            "activation_end" => array("timestamp", $this->getActivationEnd()),
            "show_activation_info" => array("integer", (int) $this->getShowActivationInfo()),
            "inactive_elements" => array("integer", $iel),
            "int_links" => array("integer", $inl),
            "created" => array("timestamp", ilUtil::now()),
            "last_change" => array("timestamp", ilUtil::now()),
            "is_empty" => array("integer", $empty)
        ));

        // after update event
        $this->__afterUpdate($dom_doc, $content, true, $empty);
    }

    /**
     * Updates page object with current xml content
     * This function is currently (8 beta) called by:
     * - ilQuestionPageParser (Test and TestQuestionPool)
     * - ilSCORM13Package->dbImportSco (SCORM importer)
     * - assQuestion->copyPageOfQuestion
     */
    public function updateFromXML(): bool
    {
        $this->log->debug("ilPageObject, updateFromXML(): start, id: " . $this->getId());

        $content = $this->getXMLContent();

        $this->log->debug("ilPageObject, updateFromXML(): content: " . substr($content, 0, 100));

        $this->buildDom(true);
        $dom_doc = $this->getDomDoc();

        $iel = $this->containsDeactivatedElements($content);
        $inl = $this->containsIntLinks($content);

        $this->db->update("page_object", array(
            "content" => array("clob", $content),
            "parent_id" => array("integer", $this->getParentId()),
            "last_change_user" => array("integer", $this->user->getId()),
            "last_change" => array("timestamp", ilUtil::now()),
            "active" => array("integer", $this->getActive()),
            "activation_start" => array("timestamp", $this->getActivationStart()),
            "activation_end" => array("timestamp", $this->getActivationEnd()),
            "inactive_elements" => array("integer", $iel),
            "int_links" => array("integer", $inl),
        ), array(
            "page_id" => array("integer", $this->getId()),
            "parent_type" => array("text", $this->getParentType()),
            "lang" => array("text", $this->getLanguage())
        ));

        // after update event
        $this->__afterUpdate($dom_doc, $content);

        $this->log->debug("ilPageObject, updateFromXML(): end");

        return true;
    }

    /**
     * After update event handler (internal). The hooks are e.g. for
     * storing any dependent relations/references in the database.
     */
    final protected function __afterUpdate(
        DOMDocument $a_domdoc,
        string $a_xml,
        bool $a_creation = false,
        bool $a_empty = false
    ): void {
        // we do not need this if we are creating an empty page
        if (!$a_creation || !$a_empty) {
            // save internal link information
            // the page object is responsible to do this, since it "offers" the
            // internal link feature pc and page classes
            $this->saveInternalLinks($a_domdoc);

            // save style usage
            $this->saveStyleUsage($a_domdoc);

            // save estimated reading time
            $this->reading_time_manager->saveTime($this);

            // pc classes hook
            $defs = $this->pc_definition->getPCDefinitions();
            foreach ($defs as $def) {
                $cl = $def["pc_class"];
                call_user_func($def["pc_class"] . '::afterPageUpdate', $this, $a_domdoc, $a_xml, $a_creation);
            }
        }

        // call page hook
        $this->afterUpdate($a_domdoc, $a_xml);

        // call update listeners
        $this->callUpdateListeners();
    }

    /**
     * After update
     */
    public function afterUpdate(DOMDocument $domdoc, string $xml): void
    {
    }

    /**
     * update complete page content in db (dom xml content is used)
     * @return array|bool
     * @throws ilDateTimeException
     * @throws ilWACException
     */
    public function update(bool $a_validate = true, bool $a_no_history = false)
    {
        $this->log->debug("start..., id: " . $this->getId());
        $lm_set = new ilSetting("lm");

        // add missing pc ids
        if (!$this->checkPCIds()) {
            $this->insertPCIds();
        }

        // test validating
        if ($a_validate) {
            $errors = $this->validateDom();
        }
        //var_dump($errors); exit;
        if (empty($errors) && !$this->getEditLock()) {
            $lock = $this->getEditLockInfo();
            $errors[0] = array(0 => 0,
                               1 => $this->lng->txt("cont_not_saved_edit_lock_expired") . "<br />" .
                                   $this->lng->txt("obj_usr") . ": " .
                                   ilUserUtil::getNamePresentation($lock["edit_lock_user"]) . "<br />" .
                                   $this->lng->txt("content_until") . ": " .
                                   ilDatePresentation::formatDate(new ilDateTime($lock["edit_lock_until"], IL_CAL_UNIX))
            );
        }

        // check for duplicate pc ids
        $this->log->debug("checking duplicate ids");
        if ($this->hasDuplicatePCIds()) {
            $errors[0] = $this->lng->txt("cont_could_not_save_duplicate_pc_ids") .
                " (" . implode(", ", $this->getDuplicatePCIds()) . ")";
        }

        if (!empty($errors)) {
            $this->log->debug("ilPageObject, update(): errors: " . print_r($errors, true));
        }

        //echo "-".htmlentities($this->getXMLFromDom())."-"; exit;
        if (empty($errors)) {
            // @todo 1: is this page type or pc content type
            // related -> plugins should be able to hook in!?

            $this->log->debug("perform automatic modifications");
            $this->performAutomaticModifications();

            // get xml content
            $content = $this->getXMLFromDom();
            $dom_doc = $this->getDomDoc();

            // this needs to be locked

            // write history entry
            $old_set = $this->db->query("SELECT * FROM page_object WHERE " .
                "page_id = " . $this->db->quote($this->getId(), "integer") . " AND " .
                "parent_type = " . $this->db->quote($this->getParentType(), "text") . " AND " .
                "lang = " . $this->db->quote($this->getLanguage(), "text"));
            $last_nr_set = $this->db->query("SELECT max(nr) as mnr FROM page_history WHERE " .
                "page_id = " . $this->db->quote($this->getId(), "integer") . " AND " .
                "parent_type = " . $this->db->quote($this->getParentType(), "text") . " AND " .
                "lang = " . $this->db->quote($this->getLanguage(), "text"));
            $last_nr = $this->db->fetchAssoc($last_nr_set);
            if ($old_rec = $this->db->fetchAssoc($old_set)) {
                // only save, if something has changed
                // added user id to the check for ilias 5.0, 7.10.2014
                if (($content != $old_rec["content"] || $this->user->getId() != $old_rec["last_change_user"]) &&
                    !$a_no_history && !$this->history_saved && $lm_set->get("page_history", 1)) {
                    if ($old_rec["content"] != "<PageObject></PageObject>") {
                        $this->db->manipulateF(
                            "DELETE FROM page_history WHERE " .
                            "page_id = %s AND parent_type = %s AND hdate = %s AND lang = %s",
                            array("integer", "text", "timestamp", "text"),
                            array($old_rec["page_id"],
                                  $old_rec["parent_type"],
                                  $old_rec["last_change"],
                                  $old_rec["lang"]
                            )
                        );

                        // the following lines are a workaround for
                        // bug 6741
                        $last_c = $old_rec["last_change"];
                        if ($last_c == "") {
                            $last_c = ilUtil::now();
                        }

                        $this->db->insert("page_history", array(
                            "page_id" => array("integer", $old_rec["page_id"]),
                            "parent_type" => array("text", $old_rec["parent_type"]),
                            "lang" => array("text", $old_rec["lang"]),
                            "hdate" => array("timestamp", $last_c),
                            "parent_id" => array("integer", $old_rec["parent_id"]),
                            "content" => array("clob", $old_rec["content"]),
                            "user_id" => array("integer", $old_rec["last_change_user"]),
                            "ilias_version" => array("text", ILIAS_VERSION_NUMERIC),
                            "nr" => array("integer", (int) $last_nr["mnr"] + 1)
                        ));

                        $old_content = $old_rec["content"];
                        $old_domdoc = new DOMDocument();
                        $old_nr = $last_nr["mnr"] + 1;
                        $old_domdoc->loadXML('<?xml version="1.0" encoding="UTF-8"?>' . $old_content);

                        // after history entry creation event
                        $this->log->debug("calling __afterHistoryEntry");
                        $this->__afterHistoryEntry($old_domdoc, $old_content, $old_nr);

                        // only save one time
                    }
                    $this->history_saved = true;
                }
            }
            //echo htmlentities($content);
            $em = (trim($content) == "<PageObject/>")
                ? 1
                : 0;

            // @todo: pass dom instead?
            $this->log->debug("checking deactivated elements");
            $iel = $this->containsDeactivatedElements($content);
            $this->log->debug("checking internal links");
            $inl = $this->containsIntLinks($content);

            $this->db->update("page_object", array(
                "content" => array("clob", $content),
                "parent_id" => array("integer", $this->getParentId()),
                "last_change_user" => array("integer", $this->user->getId()),
                "last_change" => array("timestamp", ilUtil::now()),
                "is_empty" => array("integer", $em),
                "active" => array("integer", $this->getActive()),
                "activation_start" => array("timestamp", $this->getActivationStart()),
                "activation_end" => array("timestamp", $this->getActivationEnd()),
                "show_activation_info" => array("integer", $this->getShowActivationInfo()),
                "inactive_elements" => array("integer", $iel),
                "int_links" => array("integer", $inl),
            ), array(
                "page_id" => array("integer", $this->getId()),
                "parent_type" => array("text", $this->getParentType()),
                "lang" => array("text", $this->getLanguage())
            ));

            // after update event
            $this->log->debug("calling __afterUpdate()");
            $this->__afterUpdate($dom_doc, $content);

            $this->log->debug(
                "...ending, updated and returning true, content: " . substr(
                    $this->getXMLContent(),
                    0,
                    100
                )
            );

            //echo "<br>PageObject::update:".htmlentities($this->getXMLContent()).":";
            return true;
        } else {
            return $errors;
        }
    }

    public function delete(): void
    {
        $copg_logger = ilLoggerFactory::getLogger('copg');
        $copg_logger->debug(
            "ilPageObject: Delete called for ID '" . $this->getId() . "'," .
            " parent type: '" . $this->getParentType() . "', " .
            " hist nr: '" . $this->old_nr . "', " .
            " lang: '" . $this->getLanguage() . "', "
        );

        $mobs = array();
        if (!$this->page_not_found) {
            $this->buildDom();
            $mobs = $this->collectMediaObjects(false);
        }
        $mobs2 = ilObjMediaObject::_getMobsOfObject($this->getParentType() . ":pg", $this->getId(), false);
        foreach ($mobs2 as $m) {
            if (!in_array($m, $mobs)) {
                $mobs[] = $m;
            }
        }

        $copg_logger->debug("ilPageObject: ... found " . count($mobs) . " media objects.");

        $this->__beforeDelete();

        // treat plugged content
        $this->handleDeleteContent();

        // delete style usages
        $this->deleteStyleUsages(false);

        // delete internal links
        $this->deleteInternalLinks();

        // delete all mob usages
        ilObjMediaObject::_deleteAllUsages($this->getParentType() . ":pg", $this->getId());

        // delete news
        ilNewsItem::deleteNewsOfContext(
            $this->getParentId(),
            $this->getParentType(),
            $this->getId(),
            "pg"
        );

        // delete page_object entry
        $this->db->manipulate("DELETE FROM page_object " .
            "WHERE page_id = " . $this->db->quote($this->getId(), "integer") .
            " AND parent_type= " . $this->db->quote($this->getParentType(), "text"));

        // delete media objects
        foreach ($mobs as $mob_id) {
            $copg_logger->debug("ilPageObject: ... processing mob " . $mob_id . ".");

            if (ilObject::_lookupType($mob_id) != 'mob') {
                $copg_logger->debug("ilPageObject: ... type mismatch. Ignoring mob " . $mob_id . ".");
                continue;
            }

            if (ilObject::_exists($mob_id)) {
                $copg_logger->debug("ilPageObject: ... delete mob " . $mob_id . ".");

                $mob_obj = new ilObjMediaObject($mob_id);
                $mob_obj->delete();
            } else {
                $copg_logger->debug("ilPageObject: ... missing mob " . $mob_id . ".");
            }
        }

        $this->__afterDelete();
    }

    /**
     * Before deletion handler (internal).
     */
    final protected function __beforeDelete(): void
    {
        // pc classes hook
        $defs = $this->pc_definition->getPCDefinitions();
        foreach ($defs as $def) {
            $cl = $def["pc_class"];
            call_user_func($def["pc_class"] . '::beforePageDelete', $this);
        }
    }

    final protected function __afterDelete(): void
    {
        $this->afterDelete();
    }

    protected function afterDelete(): void
    {
    }

    final protected function __afterHistoryEntry(
        DOMDocument $a_old_domdoc,
        string $a_old_content,
        int $a_old_nr
    ): void {
        // save style usage
        $this->saveStyleUsage($a_old_domdoc, $a_old_nr);

        // pc classes hook
        $defs = $this->pc_definition->getPCDefinitions();
        foreach ($defs as $def) {
            $cl = $def["pc_class"];
            call_user_func(
                $def["pc_class"] . '::afterPageHistoryEntry',
                $this,
                $a_old_domdoc,
                $a_old_content,
                $a_old_nr
            );
        }
    }

    /**
     * Save all style class/template usages
     */
    public function saveStyleUsage(
        DOMDocument $a_domdoc,
        int $a_old_nr = 0
    ): void {
        $sname = "";
        $stype = "";
        $template = "";
        // media aliases
        $xpath = new DOMXPath($a_domdoc);
        $path = "//Paragraph | //Section | //MediaAlias | //FileItem" .
            " | //Table | //TableData | //Tabs | //List";
        $nodes = $xpath->query($path);
        $usages = array();
        foreach ($nodes as $node) {
            switch ($node->localName) {
                case "Paragraph":
                    $sname = $node->getAttribute("Characteristic");
                    $stype = "text_block";
                    $template = 0;
                    break;

                case "Section":
                    $sname = $node->getAttribute("Characteristic");
                    $stype = "section";
                    $template = 0;
                    break;

                case "MediaAlias":
                    $sname = $node->getAttribute("Class");
                    $stype = "media_cont";
                    $template = 0;
                    break;

                case "FileItem":
                    $sname = $node->getAttribute("Class");
                    $stype = "flist_li";
                    $template = 0;
                    break;

                case "Table":
                    $sname = $node->getAttribute("Template");
                    if ($sname == "") {
                        $sname = $node->getAttribute("Class");
                        $stype = "table";
                        $template = 0;
                    } else {
                        $stype = "table";
                        $template = 1;
                    }
                    break;

                case "TableData":
                    $sname = $node->getAttribute("Class");
                    $stype = "table_cell";
                    $template = 0;
                    break;

                case "Tabs":
                    $sname = $node->getAttribute("Template");
                    if ($sname != "") {
                        if ($node->getAttribute("Type") == "HorizontalAccordion") {
                            $stype = "haccordion";
                        }
                        if ($node->getAttribute("Type") == "VerticalAccordion") {
                            $stype = "vaccordion";
                        }
                    }
                    $template = 1;
                    break;

                case "List":
                    $sname = $node->getAttribute("Class");
                    if ($node->getAttribute("Type") == "Ordered") {
                        $stype = "list_o";
                    } else {
                        $stype = "list_u";
                    }
                    $template = 0;
                    break;
            }
            if ($sname != "" && $stype != "") {
                $usages[$sname . ":" . $stype . ":" . $template] = array("sname" => $sname,
                                                                         "stype" => $stype,
                                                                         "template" => $template
                );
            }
        }

        $this->deleteStyleUsages($a_old_nr);

        foreach ($usages as $u) {
            $id = $this->db->nextId('page_style_usage');
            $this->db->manipulate("INSERT INTO page_style_usage " .
                "(id, page_id, page_type, page_lang, page_nr, template, stype, sname) VALUES (" .
                $this->db->quote($id, "integer") . "," .
                $this->db->quote($this->getId(), "integer") . "," .
                $this->db->quote($this->getParentType(), "text") . "," .
                $this->db->quote($this->getLanguage(), "text") . "," .
                $this->db->quote($a_old_nr, "integer") . "," .
                $this->db->quote($u["template"], "integer") . "," .
                $this->db->quote($u["stype"], "text") . "," .
                $this->db->quote($u["sname"], "text") .
                ")");
        }
    }

    /**
     * Delete style usages
     */
    public function deleteStyleUsages(int $a_old_nr = 0): void
    {
        $and_old_nr = "";
        if ($a_old_nr !== 0) {
            $and_old_nr = " AND page_nr = " . $this->db->quote($a_old_nr, "integer");
        }

        $this->db->manipulate(
            "DELETE FROM page_style_usage WHERE " .
            " page_id = " . $this->db->quote($this->getId(), "integer") .
            " AND page_type = " . $this->db->quote($this->getParentType(), "text") .
            " AND page_lang = " . $this->db->quote($this->getLanguage(), "text") .
            $and_old_nr
        );
    }


    /**
     * Get last update of included elements (media objects and files).
     * This is needed for cache logic, cache must be reloaded if anything has changed.
     * @todo: move to content include class
     */
    public function getLastUpdateOfIncludedElements(): string
    {
        $mobs = ilObjMediaObject::_getMobsOfObject(
            $this->getParentType() . ":pg",
            $this->getId()
        );
        $files = ilObjFile::_getFilesOfObject(
            $this->getParentType() . ":pg",
            $this->getId()
        );
        $objs = array_merge($mobs, $files);
        return ilObject::_getLastUpdateOfObjects($objs);
    }

    /**
     * Delete internal links
     */
    public function deleteInternalLinks(): void
    {
        ilInternalLink::_deleteAllLinksOfSource(
            $this->getParentType() . ":pg",
            $this->getId(),
            $this->getLanguage()
        );
    }


    /**
     * save internal links of page
     * @todo: move to specific classes, internal link use info
     */
    public function saveInternalLinks(DOMDocument $a_domdoc): void
    {
        $this->deleteInternalLinks();
        $t_type = "";
        // query IntLink elements
        $xpath = new DOMXPath($a_domdoc);
        $nodes = $xpath->query('//IntLink');
        foreach ($nodes as $node) {
            $link_type = $node->getAttribute("Type");

            switch ($link_type) {
                case "StructureObject":
                    $t_type = "st";
                    break;

                case "PageObject":
                    $t_type = "pg";
                    break;

                case "GlossaryItem":
                    $t_type = "git";
                    break;

                case "MediaObject":
                    $t_type = "mob";
                    break;

                case "RepositoryItem":
                    $t_type = "obj";
                    break;

                case "File":
                    $t_type = "file";
                    break;

                case "WikiPage":
                    $t_type = "wpage";
                    break;

                case "PortfolioPage":
                    $t_type = "ppage";
                    break;

                case "User":
                    $t_type = "user";
                    break;
            }

            $target = $node->getAttribute("Target");
            $target_arr = explode("_", $target);
            $t_id = $target_arr[count($target_arr) - 1];

            // link to other internal object
            if (is_int(strpos($target, "__"))) {
                $t_inst = 0;
            } else {    // link to unresolved object in other installation
                $t_inst = (int) ($target_arr[1] ?? 0);
            }

            if ($t_id > 0) {
                ilInternalLink::_saveLink(
                    $this->getParentType() . ":pg",
                    $this->getId(),
                    $t_type,
                    $t_id,
                    $t_inst,
                    $this->getLanguage()
                );
            }
        }
    }

    /**
     * create new page (with current xml data)
     */
    public function create(bool $a_import = false): void
    {
        $this->createFromXML();
    }

    /**
     * delete content object with hierarchical id $a_hid
     * @return array|bool
     * @throws ilDateTimeException
     * @throws ilWACException
     */
    public function deleteContent(
        string $a_hid,
        bool $a_update = true,
        string $a_pcid = "",
        bool $move_operation = false
    ) {
        $pm = $this->page_manager->content($this->getDomDoc());
        $pm->deleteContent($a_hid, $a_pcid, $move_operation);
        if ($a_update) {
            return $this->update();
        }
        return true;
    }

    /**
     * Delete multiple content objects
     * @param bool $a_update    update page in db (note: update deletes all
     *                          hierarchical ids in DOM!)
     * @return array|bool
     * @throws ilDateTimeException
     */
    public function deleteContents(
        array $a_hids,
        bool $a_update = true,
        bool $a_self_ass = false,
        bool $move_operation = false
    ) {
        $pm = $this->page_manager->content($this->getDomDoc());
        $pm->deleteContents($a_hids, $a_self_ass, $move_operation);
        if ($a_update) {
            return $this->update();
        }
        return true;
    }

    /**
     * Copy contents to clipboard and cut them from the page
     * @return array|bool
     * @throws ilDateTimeException
     */
    public function cutContents(array $a_hids)
    {
        $this->copyContents($a_hids);
        return $this->deleteContents(
            $a_hids,
            true,
            $this->getPageConfig()->getEnableSelfAssessment(),
            true
        );
    }

    /**
     * Copy contents to clipboard
     */
    public function copyContents(array $a_hids): void
    {
        $user = $this->user;

        $pc_id = null;

        if (!is_array($a_hids)) {
            return;
        }

        $time = date("Y-m-d H:i:s", time());

        $hier_ids = array();
        $skip = array();
        foreach ($a_hids as $a_hid) {
            if ($a_hid == "") {
                continue;
            }
            $a_hid = explode(":", $a_hid);

            // check, whether new hid is child of existing one or vice versa
            reset($hier_ids);
            foreach ($hier_ids as $h) {
                if ($h . "_" == substr($a_hid[0], 0, strlen($h) + 1)) {
                    $skip[] = $a_hid[0];
                }
                if ($a_hid[0] . "_" == substr($h, 0, strlen($a_hid[0]) + 1)) {
                    $skip[] = $h;
                }
            }
            $pc_id[$a_hid[0]] = $a_hid[1];
            if ($a_hid[0] != "") {
                $hier_ids[$a_hid[0]] = $a_hid[0];
            }
        }
        foreach ($skip as $s) {
            unset($hier_ids[$s]);
        }
        $hier_ids = ilPageContent::sortHierIds($hier_ids);
        $nr = 1;
        foreach ($hier_ids as $hid) {
            $curr_node = $this->getContentNode($hid, $pc_id[$hid]);
            if (is_object($curr_node)) {
                if ($curr_node->node_name() == "PageContent") {
                    $content = $this->dom->dump_node($curr_node);
                    // remove pc and hier ids
                    $content = preg_replace('/PCID=\"[a-z0-9]*\"/i', "", $content);
                    $content = preg_replace('/HierId=\"[a-z0-9_]*\"/i', "", $content);

                    $user->addToPCClipboard($content, $time, $nr);
                    $nr++;
                }
            }
        }
        ilEditClipboard::setAction("copy");
    }

    /**
     * Paste contents from pc clipboard
     * @return array|bool
     * @throws ilDateTimeException
     */
    public function pasteContents(
        string $a_hier_id,
        bool $a_self_ass = false
    ) {
        $user = $this->user;

        $a_hid = explode(":", $a_hier_id);
        $content = $user->getPCClipboardContent();

        // we insert from last to first, because we insert all at the
        // same hier_id
        for ($i = count($content) - 1; $i >= 0; $i--) {
            $c = $content[$i];
            $temp_dom = domxml_open_mem(
                '<?xml version="1.0" encoding="UTF-8"?>' . $c,
                DOMXML_LOAD_PARSING,
                $error
            );
            if (empty($error)) {
                $this->handleCopiedContent($temp_dom, $a_self_ass);
                $xpc = xpath_new_context($temp_dom);
                $path = "//PageContent";
                $res = xpath_eval($xpc, $path);
                if (count($res->nodeset) > 0) {
                    $new_pc_node = $res->nodeset[0];
                    $cloned_pc_node = $new_pc_node->clone_node(true);
                    $cloned_pc_node->unlink_node($cloned_pc_node);
                    $this->insertContentNode(
                        $cloned_pc_node,
                        $a_hid[0],
                        IL_INSERT_AFTER,
                        $a_hid[1]
                    );
                }
            } else {
                //var_dump($error);
            }
        }
        return $this->update();
    }

    /**
     * (De-)activate elements
     * @return array|bool
     * @throws ilCOPageUnknownPCTypeException
     * @throws ilDateTimeException
     * @throws ilWACException
     */
    public function switchEnableMultiple(
        array $a_hids,
        bool $a_update = true,
        bool $a_self_ass = false
    ) {
        $cm = $this->page_manager->content($this->getDomDoc());
        $cm->switchEnableMultiple($this, $a_hids, $a_self_ass);
        if ($a_update) {
            return $this->update();
        }
        return true;
    }

    /**
     * delete content object with hierarchical id >= $a_hid
     * as part of a split page operation
     * @param string  $a_hid hierarchical id of content object
     * @param bool $a_update update page in db (note: update deletes all
     *                       hierarchical ids in DOM!)
     * @return array|bool
     * @throws ilDateTimeException
     */
    public function deleteContentFromHierId(
        string $a_hid,
        bool $a_update = true
    ) {
        $hier_ids = $this->getHierIds();

        // iterate all hierarchical ids
        foreach ($hier_ids as $hier_id) {
            // delete top level nodes only
            if (!is_int(strpos($hier_id, "_"))) {
                if ($hier_id != "pg" && $hier_id >= $a_hid) {
                    $curr_node = $this->getContentNode($hier_id);
                    $this->handleDeleteContent($curr_node, true);
                    $curr_node->unlink_node($curr_node);
                }
            }
        }
        if ($a_update) {
            return $this->update();
        }
        return true;
    }

    /**
     * delete content object with hierarchical id < $a_hid
     * as part of the split page operation
     * @param string  $a_hid              hierarchical id of content object
     * @param bool $a_update           update page in db (note: update deletes all
     *                                    hierarchical ids in DOM!)
     * @return array|bool
     * @throws ilDateTimeException
     */
    public function deleteContentBeforeHierId(
        string $a_hid,
        bool $a_update = true
    ) {
        $hier_ids = $this->getHierIds();

        // iterate all hierarchical ids
        foreach ($hier_ids as $hier_id) {
            // delete top level nodes only
            if (!is_int(strpos($hier_id, "_"))) {
                if ($hier_id != "pg" && $hier_id < $a_hid) {
                    $curr_node = $this->getContentNode($hier_id);
                    $this->handleDeleteContent($curr_node, true);
                    $curr_node->unlink_node($curr_node);
                }
            }
        }
        if ($a_update) {
            return $this->update();
        }
        return true;
    }

    /**
     * move content of hierarchical id >= $a_hid to other page
     * @throws ilDateTimeException
     */
    public static function _moveContentAfterHierId(
        ilPageObject $a_source_page,
        ilPageObject $a_target_page,
        string $a_hid
    ): void {
        $hier_ids = $a_source_page->getHierIds();

        $copy_ids = array();

        // iterate all hierarchical ids
        foreach ($hier_ids as $hier_id) {
            // move top level nodes only
            if (!is_int(strpos($hier_id, "_"))) {
                if ($hier_id != "pg" && $hier_id >= $a_hid) {
                    $copy_ids[] = $hier_id;
                }
            }
        }
        asort($copy_ids);

        $parent_node = $a_target_page->getContentNode("pg");
        $target_dom = $a_target_page->getDom();
        $parent_childs = $parent_node->child_nodes();
        $cnt_parent_childs = count($parent_childs);
        //echo "-$cnt_parent_childs-";
        $first_child = $parent_childs[0];
        foreach ($copy_ids as $copy_id) {
            $source_node = $a_source_page->getContentNode($copy_id);

            $new_node = $source_node->clone_node(true);
            $new_node->unlink_node($new_node);

            $source_node->unlink_node($source_node);

            if ($cnt_parent_childs == 0) {
                $new_node = $parent_node->append_child($new_node);
            } else {
                //$target_dom->import_node($new_node);
                $new_node = $first_child->insert_before($new_node, $first_child);
            }
            $parent_childs = $parent_node->child_nodes();

            //$cnt_parent_childs++;
        }

        $a_target_page->update();
        $a_source_page->update();
    }

    /**
     * insert a content node before/after a sibling or as first child of a parent
     */
    public function insertContent(
        ilPageContent $a_cont_obj,
        string $a_pos,
        int $a_mode = IL_INSERT_AFTER,
        string $a_pcid = "",
        bool $remove_placeholder = true
    ): void {
        if ($a_pcid == "" && $a_pos == "") {
            $a_pos = "pg";
        }
        // move mode into container elements is always INSERT_CHILD
        $curr_node = $this->getContentNode($a_pos, $a_pcid);
        $curr_name = $curr_node->node_name();

        // @todo: try to generalize this
        if (($curr_name == "TableData") || ($curr_name == "PageObject") ||
            ($curr_name == "ListItem") || ($curr_name == "Section")
            || ($curr_name == "Tab") || ($curr_name == "ContentPopup")
            || ($curr_name == "GridCell")) {
            $a_mode = IL_INSERT_CHILD;
        }

        $hid = $curr_node->get_attribute("HierId");
        if ($hid != "") {
            //echo "-".$a_pos."-".$hid."-";
            $a_pos = $hid;
        }

        if ($a_mode != IL_INSERT_CHILD) {            // determine parent hierarchical id
            // of sibling at $a_pos
            $pos = explode("_", $a_pos);
            $target_pos = array_pop($pos);
            $parent_pos = implode("_", $pos);
        } else {        // if we should insert a child, $a_pos is alreade the hierarchical id
            // of the parent node
            $parent_pos = $a_pos;
        }

        // get the parent node
        if ($parent_pos != "") {
            $parent_node = $this->getContentNode($parent_pos);
        } else {
            $parent_node = $this->getNode();
        }

        // count the parent children
        $parent_childs = $parent_node->child_nodes();
        $cnt_parent_childs = count($parent_childs);
        //echo "ZZ$a_mode";
        switch ($a_mode) {
            // insert new node after sibling at $a_pos
            case IL_INSERT_AFTER:
                $new_node = $a_cont_obj->getNode();
                //$a_pos = ilPageContent::incEdId($a_pos);
                //$curr_node = $this->getContentNode($a_pos);
                //echo "behind $a_pos:";
                if ($succ_node = $curr_node->next_sibling()) {
                    $new_node = $succ_node->insert_before($new_node, $succ_node);
                } else {
                    //echo "movin doin append_child";
                    $new_node = $parent_node->append_child($new_node);
                }
                $a_cont_obj->setNode($new_node);
                break;

            case IL_INSERT_BEFORE:
                //echo "INSERT_BEF";
                $new_node = $a_cont_obj->getNode();
                $succ_node = $this->getContentNode($a_pos);
                $new_node = $succ_node->insert_before($new_node, $succ_node);
                $a_cont_obj->setNode($new_node);
                break;

                // insert new node as first child of parent $a_pos (= $a_parent)
            case IL_INSERT_CHILD:
                //echo "insert as child:parent_childs:$cnt_parent_childs:<br>";
                $new_node = $a_cont_obj->getNode();
                if ($cnt_parent_childs == 0) {
                    $new_node = $parent_node->append_child($new_node);
                } else {
                    $new_node = $parent_childs[0]->insert_before($new_node, $parent_childs[0]);
                }
                $a_cont_obj->setNode($new_node);
                //echo "PP";
                break;
        }

        //check for PlaceHolder to remove in EditMode-keep in Layout Mode
        if ($remove_placeholder && !$this->getPageConfig()->getEnablePCType("PlaceHolder")) {
            $sub_nodes = $curr_node->child_nodes();
            foreach ($sub_nodes as $sub_node) {
                if ($sub_node->node_name() == "PlaceHolder") {
                    $curr_node->unlink_node();
                }
            }
        }
    }

    /**
     * insert a content node before/after a sibling or as first child of a parent
     */
    public function insertContentNode(
        php4DOMElement $a_cont_node,
        string $a_pos,
        int $a_mode = IL_INSERT_AFTER,
        string $a_pcid = ""
    ): void {
        // move mode into container elements is always INSERT_CHILD
        $curr_node = $this->getContentNode($a_pos, $a_pcid);
        $curr_name = $curr_node->node_name();
        // @todo: try to generalize
        if (($curr_name == "TableData") || ($curr_name == "PageObject") ||
            ($curr_name == "ListItem") || ($curr_name == "Section")
            || ($curr_name == "Tab") || ($curr_name == "ContentPopup")
            || ($curr_name == "GridCell")) {
            $a_mode = IL_INSERT_CHILD;
        }

        $hid = $curr_node->get_attribute("HierId");
        if ($hid != "") {
            $a_pos = $hid;
        }

        if ($a_mode != IL_INSERT_CHILD) {            // determine parent hierarchical id
            // of sibling at $a_pos
            $pos = explode("_", $a_pos);
            $target_pos = array_pop($pos);
            $parent_pos = implode("_", $pos);
        } else {        // if we should insert a child, $a_pos is alreade the hierarchical id
            // of the parent node
            $parent_pos = $a_pos;
        }

        // get the parent node
        if ($parent_pos != "") {
            $parent_node = $this->getContentNode($parent_pos);
        } else {
            $parent_node = $this->getNode();
        }

        // count the parent children
        $parent_childs = $parent_node->child_nodes();
        $cnt_parent_childs = count($parent_childs);
        switch ($a_mode) {
            // insert new node after sibling at $a_pos
            case IL_INSERT_AFTER:
                //$new_node = $a_cont_obj->getNode();
                if ($succ_node = $curr_node->next_sibling()) {
                    $a_cont_node = $succ_node->insert_before($a_cont_node, $succ_node);
                } else {
                    $a_cont_node = $parent_node->append_child($a_cont_node);
                }
                //$a_cont_obj->setNode($new_node);
                break;

            case IL_INSERT_BEFORE:
                //$new_node = $a_cont_obj->getNode();
                $succ_node = $this->getContentNode($a_pos);
                $a_cont_node = $succ_node->insert_before($a_cont_node, $succ_node);
                //$a_cont_obj->setNode($new_node);
                break;

                // insert new node as first child of parent $a_pos (= $a_parent)
            case IL_INSERT_CHILD:
                //$new_node = $a_cont_obj->getNode();
                if ($cnt_parent_childs == 0) {
                    $a_cont_node = $parent_node->append_child($a_cont_node);
                } else {
                    $a_cont_node = $parent_childs[0]->insert_before($a_cont_node, $parent_childs[0]);
                }
                //$a_cont_obj->setNode($new_node);
                break;
        }
    }

    /**
     * move content object from position $a_source before position $a_target
     * (both hierarchical content ids)
     * @param string $a_source source hier id
     * @param string $a_target target hier id
     * @param string $a_spcid source pcid
     * @param string $a_tpcid target pcid
     * @return array|bool
     * @throws ilCOPagePCEditException
     * @throws ilCOPageUnknownPCTypeException
     * @throws ilDateTimeException
     */
    public function moveContentBefore(
        string $a_source,
        string $a_target,
        string $a_spcid = "",
        string $a_tpcid = ""
    ) {
        if ($a_source == $a_target) {
            return false;
        }

        // clone the node
        $content = $this->getContentObject($a_source, $a_spcid);
        $source_node = $content->getNode();
        $clone_node = $source_node->clone_node(true);

        // delete source node
        $this->deleteContent($a_source, false, $a_spcid, true);

        // insert cloned node at target
        $content->setNode($clone_node);
        $this->insertContent($content, $a_target, IL_INSERT_BEFORE, $a_tpcid);
        return $this->update();
    }

    /**
     * move content object from position $a_source before position $a_target
     * (both hierarchical content ids)
     * @param string $a_source
     * @param string $a_target
     * @param string $a_spcid
     * @param string $a_tpcid
     * @return array|bool
     * @throws ilCOPagePCEditException
     * @throws ilCOPageUnknownPCTypeException
     * @throws ilDateTimeException
     */
    public function moveContentAfter(
        string $a_source,
        string $a_target,
        string $a_spcid = "",
        string $a_tpcid = ""
    ) {
        // nothing to do...
        if ($a_source === $a_target) {
            return true;
        }

        // clone the node
        $content = $this->getContentObject($a_source, $a_spcid);
        $source_node = $content->getNode();
        $clone_node = $source_node->clone_node(true);

        // delete source node
        $this->deleteContent($a_source, false, $a_spcid, true);

        // insert cloned node at target
        $content->setNode($clone_node);
        $this->insertContent($content, $a_target, IL_INSERT_AFTER, $a_tpcid);
        return $this->update();
    }

    /**
     * transforms bbCode to corresponding xml
     * @todo: move to paragraph
     */
    public function bbCode2XML(string &$a_content): void
    {
        $a_content = preg_replace('/\[com\]/i', "<Comment>", $a_content);
        $a_content = preg_replace('/\[\/com\]/i', "</Comment>", $a_content);
        $a_content = preg_replace('/\[emp]/i', "<Emph>", $a_content);
        $a_content = preg_replace('/\[\/emp\]/i', "</Emph>", $a_content);
        $a_content = preg_replace('/\[str]/i', "<Strong>", $a_content);
        $a_content = preg_replace('/\[\/str\]/i', "</Strong>", $a_content);
    }

    /**
     * inserts installation id into ids (e.g. il__pg_4 -> il_23_pg_4)
     * this is needed for xml export of page
     * @param string $a_inst installation id
     * @param bool $a_res_ref_to_obj_id convert repository links obj_<ref_id> to <type>_<obj_id>
     */
    public function insertInstIntoIDs(
        string $a_inst,
        bool $a_res_ref_to_obj_id = true
    ): void {
        // insert inst id into internal links
        $xpc = xpath_new_context($this->dom);
        $path = "//IntLink";
        $res = xpath_eval($xpc, $path);
        for ($i = 0, $iMax = count($res->nodeset); $i < $iMax; $i++) {
            $target = $res->nodeset[$i]->get_attribute("Target");
            $type = $res->nodeset[$i]->get_attribute("Type");

            if (substr($target, 0, 4) == "il__") {
                $id = substr($target, 4, strlen($target) - 4);

                // convert repository links obj_<ref_id> to <type>_<obj_id>
                // this leads to bug 6685.
                if ($a_res_ref_to_obj_id && $type == "RepositoryItem") {
                    $id_arr = explode("_", $id);

                    // changed due to bug 6685
                    $ref_id = $id_arr[1];
                    $obj_id = ilObject::_lookupObjId($id_arr[1]);

                    $otype = ilObject::_lookupType($obj_id);
                    if ($obj_id > 0) {
                        // changed due to bug 6685
                        // the ref_id should be used, if the content is
                        // imported on the same installation
                        // the obj_id should be used, if a different
                        // installation imports, but has an import_id for
                        // the object id.
                        $id = $otype . "_" . $obj_id . "_" . $ref_id;
                        //$id = $otype."_".$ref_id;
                    }
                }
                $new_target = "il_" . $a_inst . "_" . $id;
                $res->nodeset[$i]->set_attribute("Target", $new_target);
            }
        }
        unset($xpc);

        // @todo: move to media/fileitems/questions, ...

        // insert inst id into media aliases
        $xpc = xpath_new_context($this->dom);
        $path = "//MediaAlias";
        $res = xpath_eval($xpc, $path);
        for ($i = 0, $iMax = count($res->nodeset); $i < $iMax; $i++) {
            $origin_id = $res->nodeset[$i]->get_attribute("OriginId");
            if (substr($origin_id, 0, 4) == "il__") {
                $new_id = "il_" . $a_inst . "_" . substr($origin_id, 4, strlen($origin_id) - 4);
                $res->nodeset[$i]->set_attribute("OriginId", $new_id);
            }
        }
        unset($xpc);

        // insert inst id file item identifier entries
        $xpc = xpath_new_context($this->dom);
        $path = "//FileItem/Identifier";
        $res = xpath_eval($xpc, $path);
        for ($i = 0, $iMax = count($res->nodeset); $i < $iMax; $i++) {
            $origin_id = $res->nodeset[$i]->get_attribute("Entry");
            if (substr($origin_id, 0, 4) == "il__") {
                $new_id = "il_" . $a_inst . "_" . substr($origin_id, 4, strlen($origin_id) - 4);
                $res->nodeset[$i]->set_attribute("Entry", $new_id);
            }
        }
        unset($xpc);

        // insert inst id into question references
        $xpc = xpath_new_context($this->dom);
        $path = "//Question";
        $res = xpath_eval($xpc, $path);
        for ($i = 0, $iMax = count($res->nodeset); $i < $iMax; $i++) {
            $qref = $res->nodeset[$i]->get_attribute("QRef");
            //echo "<br>setted:".$qref;
            if (substr($qref, 0, 4) == "il__") {
                $new_id = "il_" . $a_inst . "_" . substr($qref, 4, strlen($qref) - 4);
                //echo "<br>setting:".$new_id;
                $res->nodeset[$i]->set_attribute("QRef", $new_id);
            }
        }
        unset($xpc);

        // insert inst id into content snippets
        $xpc = xpath_new_context($this->dom);
        $path = "//ContentInclude";
        $res = xpath_eval($xpc, $path);
        for ($i = 0, $iMax = count($res->nodeset); $i < $iMax; $i++) {
            $ci = $res->nodeset[$i]->get_attribute("InstId");
            if ($ci == "") {
                $res->nodeset[$i]->set_attribute("InstId", $a_inst);
            }
        }
        unset($xpc);
    }

    /**
     * Check, whether (all) page content hashes are set
     */
    public function checkPCIds(): bool
    {
        return $this->content_id_manager->checkPCIds();
    }

    /**
     * Get all pc ids
     */
    public function getAllPCIds(): array
    {
        return $this->content_id_manager->getAllPCIds();
    }

    public function hasDuplicatePCIds(): bool
    {
        return $this->content_id_manager->hasDuplicatePCIds();
    }

    /**
     * Get all duplicate PC Ids
     * @return int[]
     */
    public function getDuplicatePCIds(): array
    {
        return $this->content_id_manager->getDuplicatePCIds();
    }

    public function generatePCId(): string
    {
        return $this->content_id_manager->generatePCId();
    }

    /**
     * Insert Page Content IDs
     */
    public function insertPCIds(): void
    {
        $this->content_id_manager->insertPCIds();
    }

    public function sendParagraph(
        string $par_id,
        string $filename
    ): void {
        $this->pc_service->paragraph()->send(
            $this->getDomDoc(),
            $par_id,
            $filename
        );
    }

    public function registerOfflineHandler(object $handler): void
    {
        $this->offline_handler = $handler;
    }

    public function getOfflineHandler(): ?object
    {
        return $this->offline_handler;
    }

    /**
     * lookup whether page contains deactivated elements
     */
    public static function _lookupContainsDeactivatedElements(
        int $a_id,
        string $a_parent_type,
        string $a_lang = "-"
    ): bool {
        global $DIC;

        $db = $DIC->database();

        if ($a_lang == "") {
            $a_lang = "-";
        }

        $query = "SELECT * FROM page_object WHERE page_id = " .
            $db->quote($a_id, "integer") . " AND " .
            " parent_type = " . $db->quote($a_parent_type, "text") . " AND " .
            " lang = " . $db->quote($a_lang, "text") . " AND " .
            " inactive_elements = " . $db->quote(1, "integer");
        $obj_set = $db->query($query);

        if ($obj_rec = $obj_set->fetchRow(ilDBConstants::FETCHMODE_ASSOC)) {
            return true;
        }

        return false;
    }

    /**
     * Check whether content contains deactivated elements
     */
    public function containsDeactivatedElements(string $a_content): bool
    {
        if (strpos($a_content, " Enabled=\"False\"")) {
            return true;
        }
        return false;
    }

    /**
     * Get History Entries
     */
    public function getHistoryEntries(): array
    {
        $db = $this->db;

        $h_query = "SELECT * FROM page_history " .
            " WHERE page_id = " . $db->quote($this->getId(), "integer") .
            " AND parent_type = " . $db->quote($this->getParentType(), "text") .
            " AND lang = " . $db->quote($this->getLanguage(), "text") .
            " ORDER BY hdate DESC";

        $hset = $db->query($h_query);
        $hentries = array();

        while ($hrec = $db->fetchAssoc($hset)) {
            $hrec["sortkey"] = (int) $hrec["nr"];
            $hrec["user"] = (int) $hrec["user_id"];
            $hentries[] = $hrec;
        }
        //var_dump($hentries);
        return $hentries;
    }

    /**
     * Get History Entry
     */
    public function getHistoryEntry(int $a_old_nr): ?array
    {
        $db = $this->db;

        $res = $db->queryF(
            "SELECT * FROM page_history " .
            " WHERE page_id = %s " .
            " AND parent_type = %s " .
            " AND nr = %s" .
            " AND lang = %s",
            array("integer", "text", "integer", "text"),
            array($this->getId(), $this->getParentType(), $a_old_nr, $this->getLanguage())
        );
        if ($hrec = $db->fetchAssoc($res)) {
            return $hrec;
        }

        return null;
    }

    /**
     * Get information about a history entry, its predecessor and
     * its successor.
     * @param int $a_nr Nr of history entry
     */
    public function getHistoryInfo(int $a_nr): array
    {
        $db = $this->db;

        // determine previous entry
        $and_nr = ($a_nr > 0)
            ? " AND nr < " . $db->quote($a_nr, "integer")
            : "";
        $res = $db->query("SELECT MAX(nr) mnr FROM page_history " .
            " WHERE page_id = " . $db->quote($this->getId(), "integer") .
            " AND parent_type = " . $db->quote($this->getParentType(), "text") .
            " AND lang = " . $db->quote($this->getLanguage(), "text") .
            $and_nr);
        $row = $db->fetchAssoc($res);
        if ($row["mnr"] > 0) {
            $res = $db->query("SELECT * FROM page_history " .
                " WHERE page_id = " . $db->quote($this->getId(), "integer") .
                " AND parent_type = " . $db->quote($this->getParentType(), "text") .
                " AND lang = " . $db->quote($this->getLanguage(), "text") .
                " AND nr = " . $db->quote((int) $row["mnr"], "integer"));
            $row = $db->fetchAssoc($res);
            $ret["previous"] = $row;
        }

        // determine next entry
        $res = $db->query("SELECT MIN(nr) mnr FROM page_history " .
            " WHERE page_id = " . $db->quote($this->getId(), "integer") .
            " AND parent_type = " . $db->quote($this->getParentType(), "text") .
            " AND lang = " . $db->quote($this->getLanguage(), "text") .
            " AND nr > " . $db->quote($a_nr, "integer"));
        $row = $db->fetchAssoc($res);
        if ($row["mnr"] > 0) {
            $res = $db->query("SELECT * FROM page_history " .
                " WHERE page_id = " . $db->quote($this->getId(), "integer") .
                " AND parent_type = " . $db->quote($this->getParentType(), "text") .
                " AND lang = " . $db->quote($this->getLanguage(), "text") .
                " AND nr = " . $db->quote((int) $row["mnr"], "integer"));
            $row = $db->fetchAssoc($res);
            $ret["next"] = $row;
        }

        // current
        if ($a_nr > 0) {
            $res = $db->query("SELECT * FROM page_history " .
                " WHERE page_id = " . $db->quote($this->getId(), "integer") .
                " AND parent_type = " . $db->quote($this->getParentType(), "text") .
                " AND lang = " . $db->quote($this->getLanguage(), "text") .
                " AND nr = " . $db->quote($a_nr, "integer"));
        } else {
            $res = $db->query("SELECT page_id, last_change hdate, parent_type, parent_id, last_change_user user_id, content, lang FROM page_object " .
                " WHERE page_id = " . $db->quote($this->getId(), "integer") .
                " AND parent_type = " . $db->quote($this->getParentType(), "text") .
                " AND lang = " . $db->quote($this->getLanguage(), "text"));
        }
        $row = $db->fetchAssoc($res);
        $ret["current"] = $row;

        return $ret;
    }

    /**
     * Increase view cnt
     */
    public function increaseViewCnt(): void
    {
        $db = $this->db;

        $db->manipulate("UPDATE page_object " .
            " SET view_cnt = view_cnt + 1 " .
            " WHERE page_id = " . $db->quote($this->getId(), "integer") .
            " AND parent_type = " . $db->quote($this->getParentType(), "text") .
            " AND lang = " . $db->quote($this->getLanguage(), "text"));
    }

    /**
     * Get recent pages changes for parent object.
     * @param string $a_parent_type Parent Type
     * @param int    $a_parent_id   Parent ID
     * @param int    $a_period      Time Period
     */
    public static function getRecentChanges(
        string $a_parent_type,
        int $a_parent_id,
        int $a_period = 30,
        string $a_lang = ""
    ): array {
        global $DIC;

        $db = $DIC->database();

        $and_lang = "";
        if ($a_lang != "") {
            $and_lang = " AND lang = " . $db->quote($a_lang, "text");
        }

        $page_changes = array();
        $limit_ts = date('Y-m-d H:i:s', time() - ($a_period * 24 * 60 * 60));
        $q = "SELECT * FROM page_object " .
            " WHERE parent_id = " . $db->quote($a_parent_id, "integer") .
            " AND parent_type = " . $db->quote($a_parent_type, "text") .
            " AND last_change >= " . $db->quote($limit_ts, "timestamp") . $and_lang;
        //	" AND (TO_DAYS(now()) - TO_DAYS(last_change)) <= ".((int)$a_period);
        $set = $db->query($q);
        while ($page = $db->fetchAssoc($set)) {
            $page_changes[] = array(
                "date" => $page["last_change"],
                "id" => $page["page_id"],
                "lang" => $page["lang"],
                "type" => "page",
                "user" => $page["last_change_user"]
            );
        }

        $and_str = "";
        if ($a_period > 0) {
            $limit_ts = date('Y-m-d H:i:s', time() - ($a_period * 24 * 60 * 60));
            $and_str = " AND hdate >= " . $db->quote($limit_ts, "timestamp") . " ";
        }

        $q = "SELECT * FROM page_history " .
            " WHERE parent_id = " . $db->quote($a_parent_id, "integer") .
            " AND parent_type = " . $db->quote($a_parent_type, "text") .
            $and_str . $and_lang;
        $set = $db->query($q);
        while ($page = $db->fetchAssoc($set)) {
            $page_changes[] = array(
                "date" => $page["hdate"],
                "id" => $page["page_id"],
                "lang" => $page["lang"],
                "type" => "hist",
                "nr" => $page["nr"],
                "user" => $page["user_id"]
            );
        }

        $page_changes = ilArrayUtil::sortArray($page_changes, "date", "desc");

        return $page_changes;
    }

    /**
     * Get all pages for parent object
     */
    public static function getAllPages(
        string $a_parent_type,
        int $a_parent_id,
        string $a_lang = "-"
    ): array {
        global $DIC;

        $db = $DIC->database();

        $and_lang = "";
        if ($a_lang != "") {
            $and_lang = " AND lang = " . $db->quote($a_lang, "text");
        }

        $q = "SELECT * FROM page_object " .
            " WHERE parent_id = " . $db->quote($a_parent_id, "integer") .
            " AND parent_type = " . $db->quote($a_parent_type, "text") . $and_lang;
        $set = $db->query($q);
        $pages = array();
        while ($page = $db->fetchAssoc($set)) {
            $key_add = ($a_lang == "")
                ? ":" . $page["lang"]
                : "";
            $pages[$page["page_id"] . $key_add] = array(
                "date" => $page["last_change"],
                "id" => $page["page_id"],
                "lang" => $page["lang"],
                "user" => $page["last_change_user"]
            );
        }

        return $pages;
    }

    /**
     * Get new pages.
     */
    public static function getNewPages(
        string $a_parent_type,
        int $a_parent_id,
        string $a_lang = "-"
    ): array {
        global $DIC;

        $db = $DIC->database();

        $and_lang = "";
        if ($a_lang != "") {
            $and_lang = " AND lang = " . $db->quote($a_lang, "text");
        }

        $pages = array();

        $q = "SELECT * FROM page_object " .
            " WHERE parent_id = " . $db->quote($a_parent_id, "integer") .
            " AND parent_type = " . $db->quote($a_parent_type, "text") . $and_lang .
            " ORDER BY created DESC";
        $set = $db->query($q);
        while ($page = $db->fetchAssoc($set)) {
            if ($page["created"] != "") {
                $pages[] = array(
                    "created" => $page["created"],
                    "id" => $page["page_id"],
                    "lang" => $page["lang"],
                    "user" => $page["create_user"],
                );
            }
        }

        return $pages;
    }

    /**
     * Get all contributors for parent object
     * @param string $a_parent_type Parent Type
     * @param int    $a_parent_id   Parent ID
     */
    public static function getParentObjectContributors(
        string $a_parent_type,
        int $a_parent_id,
        string $a_lang = "-"
    ): array {
        global $DIC;

        $db = $DIC->database();

        $and_lang = "";
        if ($a_lang != "") {
            $and_lang = " AND lang = " . $db->quote($a_lang, "text");
        }

        $contributors = array();
        $set = $db->queryF(
            "SELECT last_change_user, lang, page_id FROM page_object " .
            " WHERE parent_id = %s AND parent_type = %s " .
            " AND last_change_user != %s" . $and_lang,
            array("integer", "text", "integer"),
            array($a_parent_id, $a_parent_type, 0)
        );

        while ($page = $db->fetchAssoc($set)) {
            if ($a_lang == "") {
                $contributors[$page["last_change_user"]][$page["page_id"]][$page["lang"]] = 1;
            } else {
                $contributors[$page["last_change_user"]][$page["page_id"]] = 1;
            }
        }

        $set = $db->queryF(
            "SELECT count(*) as cnt, lang, page_id, user_id FROM page_history " .
            " WHERE parent_id = %s AND parent_type = %s AND user_id != %s " . $and_lang .
            " GROUP BY page_id, user_id, lang ",
            array("integer", "text", "integer"),
            array($a_parent_id, $a_parent_type, 0)
        );
        while ($hpage = $db->fetchAssoc($set)) {
            if ($a_lang == "") {
                $contributors[$hpage["user_id"]][$hpage["page_id"]][$hpage["lang"]] =
                    ($contributors[$hpage["user_id"]][$hpage["page_id"]][$hpage["lang"]] ?? 0) + $hpage["cnt"];
            } else {
                $contributors[$hpage["user_id"]][$hpage["page_id"]] =
                    ($contributors[$hpage["user_id"]][$hpage["page_id"]] ?? 0) + $hpage["cnt"];
            }
        }

        $c = array();
        foreach ($contributors as $k => $co) {
            if (ilObject::_lookupType($k) == "usr") {
                $name = ilObjUser::_lookupName($k);
                $c[] = array("user_id" => $k,
                             "pages" => $co,
                             "lastname" => $name["lastname"],
                             "firstname" => $name["firstname"]
                );
            }
        }

        return $c;
    }

    /**
     * Get all contributors for parent object
     */
    public static function getPageContributors(
        string $a_parent_type,
        int $a_page_id,
        string $a_lang = "-"
    ): array {
        global $DIC;

        $db = $DIC->database();

        $and_lang = "";
        if ($a_lang != "") {
            $and_lang = " AND lang = " . $db->quote($a_lang, "text");
        }

        $contributors = array();
        $set = $db->queryF(
            "SELECT last_change_user, lang FROM page_object " .
            " WHERE page_id = %s AND parent_type = %s " .
            " AND last_change_user != %s" . $and_lang,
            array("integer", "text", "integer"),
            array($a_page_id, $a_parent_type, 0)
        );

        while ($page = $db->fetchAssoc($set)) {
            if ($a_lang == "") {
                $contributors[$page["last_change_user"]][$page["lang"]] = 1;
            } else {
                $contributors[$page["last_change_user"]] = 1;
            }
        }

        $set = $db->queryF(
            "SELECT count(*) as cnt, lang, page_id, user_id FROM page_history " .
            " WHERE page_id = %s AND parent_type = %s AND user_id != %s " . $and_lang .
            " GROUP BY user_id, page_id, lang ",
            array("integer", "text", "integer"),
            array($a_page_id, $a_parent_type, 0)
        );
        while ($hpage = $db->fetchAssoc($set)) {
            if ($a_lang === "") {
                $contributors[$hpage["user_id"]][$page["lang"]] =
                    ($contributors[$hpage["user_id"]][$page["lang"]] ?? 0) + $hpage["cnt"];
            } else {
                $contributors[$hpage["user_id"]] =
                    ($contributors[$hpage["user_id"]] ?? 0) + $hpage["cnt"];
            }
        }

        $c = array();
        foreach ($contributors as $k => $co) {
            $name = ilObjUser::_lookupName($k);
            $c[] = array("user_id" => $k,
                         "pages" => $co,
                         "lastname" => $name["lastname"],
                         "firstname" => $name["firstname"]
            );
        }

        return $c;
    }

    /**
     * Write rendered content
     */
    public function writeRenderedContent(
        string $a_content,
        string $a_md5
    ): void {
        global $DIC;

        $db = $DIC->database();

        $db->update("page_object", array(
            "rendered_content" => array("clob", $a_content),
            "render_md5" => array("text", $a_md5),
            "rendered_time" => array("timestamp", ilUtil::now())
        ), array(
            "page_id" => array("integer", $this->getId()),
            "lang" => array("text", $this->getLanguage()),
            "parent_type" => array("text", $this->getParentType())
        ));
    }

    /**
     * Get all pages for parent object that contain internal links
     */
    public static function getPagesWithLinks(
        string $a_parent_type,
        int $a_parent_id,
        string $a_lang = "-"
    ): array {
        global $DIC;

        $db = $DIC->database();

        $and_lang = "";
        if ($a_lang != "") {
            $and_lang = " AND lang = " . $db->quote($a_lang, "text");
        }

        $q = "SELECT * FROM page_object " .
            " WHERE parent_id = " . $db->quote($a_parent_id, "integer") .
            " AND parent_type = " . $db->quote($a_parent_type, "text") .
            " AND int_links = " . $db->quote(1, "integer") . $and_lang;
        $set = $db->query($q);
        $pages = array();
        while ($page = $db->fetchAssoc($set)) {
            $key_add = ($a_lang == "")
                ? ":" . $page["lang"]
                : "";
            $pages[$page["page_id"] . $key_add] = array(
                "date" => $page["last_change"],
                "id" => $page["page_id"],
                "lang" => $page["lang"],
                "user" => $page["last_change_user"]
            );
        }

        return $pages;
    }

    /**
     * Check whether content contains internal links
     */
    public function containsIntLinks(string $a_content): bool
    {
        if (strpos($a_content, "IntLink")) {
            return true;
        }
        return false;
    }

    /**
     * Perform automatic modifications (may be overwritten by sub classes)
     */
    public function performAutomaticModifications(): void
    {
    }

    protected function getContentManager(): \ILIAS\COPage\Page\PageContentManager
    {
        $this->buildDom();
        return $this->page_manager->content($this->getDomDoc());
    }

    /**
     * Save initial opened content
     */
    public function saveInitialOpenedContent(
        string $a_type,
        int $a_id,
        string $a_target
    ): void {
        $cm = $this->getContentManager();
        $cm->setInitialOpenedContent($a_type, $a_id, $a_target);
        $this->update();
    }

    /**
     * Get initial opened content
     */
    public function getInitialOpenedContent(): array
    {
        $cm = $this->getContentManager();
        return $cm->getInitialOpenedContent();
    }

    /**
     * Before page content update
     * Note: This one is "work in progress", currently only text paragraphs call this hook
     * It is called before the page content object invokes the update procedure of
     * ilPageObject
     */
    public function beforePageContentUpdate(ilPageContent $a_page_content): void
    {
    }

    /**
     * Copy page
     * @param int    $a_id              target page id (new page)
     * @param string $a_parent_type
     * @param int    $a_new_parent_id
     * @param false  $a_clone_mobs
     * @param int    $obj_copy_id       copy wizard id
     */
    public function copy(
        int $a_id,
        string $a_parent_type = "",
        int $a_new_parent_id = 0,
        bool $a_clone_mobs = false,
        int $obj_copy_id = 0
    ): void {
        if ($a_parent_type == "") {
            $a_parent_type = $this->getParentType();
            if ($a_new_parent_id == 0) {
                $a_new_parent_id = $this->getParentId();
            }
        }

        foreach (self::lookupTranslations($this->getParentType(), $this->getId()) as $l) {
            $existed = false;
            $orig_page = ilPageObjectFactory::getInstance($this->getParentType(), $this->getId(), 0, $l);
            if (ilPageObject::_exists($a_parent_type, $a_id, $l)) {
                $new_page_object = ilPageObjectFactory::getInstance($a_parent_type, $a_id, 0, $l);
                $existed = true;
            } else {
                $new_page_object = ilPageObjectFactory::getInstance($a_parent_type, 0, 0, $l);
                $new_page_object->setParentId($a_new_parent_id);
                $new_page_object->setId($a_id);
            }
            $new_page_object->setXMLContent($orig_page->copyXMLContent($a_clone_mobs, $a_new_parent_id, $obj_copy_id));
            $new_page_object->setActive($orig_page->getActive());
            $new_page_object->setActivationStart($orig_page->getActivationStart());
            $new_page_object->setActivationEnd($orig_page->getActivationEnd());
            if ($existed) {
                $new_page_object->buildDom();
                $new_page_object->update();
            } else {
                $new_page_object->create(false);
            }
        }
    }

    /**
     * Lookup translations
     */
    public static function lookupTranslations(
        string $a_parent_type,
        int $a_id
    ): array {
        global $DIC;

        $db = $DIC->database();

        $set = $db->query(
            "SELECT lang FROM page_object " .
            " WHERE page_id = " . $db->quote($a_id, "integer") .
            " AND parent_type = " . $db->quote($a_parent_type, "text")
        );
        $langs = array();
        while ($rec = $db->fetchAssoc($set)) {
            $langs[] = $rec["lang"];
        }
        return $langs;
    }

    /**
     * Copy page to translation
     */
    public function copyPageToTranslation(
        string $a_target_lang
    ): void {
        $transl_page = ilPageObjectFactory::getInstance(
            $this->getParentType(),
            0,
            0,
            $a_target_lang
        );
        $transl_page->setId($this->getId());
        $transl_page->setParentId($this->getParentId());
        $transl_page->setXMLContent($this->copyXmlContent());
        $transl_page->setActive($this->getActive());
        $transl_page->setActivationStart($this->getActivationStart());
        $transl_page->setActivationEnd($this->getActivationEnd());
        $transl_page->create(false);
    }

    ////
    //// Page locking
    ////

    /**
     * Get page lock
     */
    public function getEditLock(): bool
    {
        $db = $this->db;
        $user = $this->user;

        $min = $this->getEffectiveEditLockTime();
        if ($min > 0) {
            // try to set the lock for the user
            $ts = time();
            $db->manipulate(
                "UPDATE page_object SET " .
                " edit_lock_user = " . $db->quote($user->getId(), "integer") . "," .
                " edit_lock_ts = " . $db->quote($ts, "integer") .
                " WHERE (edit_lock_user = " . $db->quote($user->getId(), "integer") . " OR " .
                " edit_lock_ts < " . $db->quote(time() - ($min * 60), "integer") . ") " .
                " AND page_id = " . $db->quote($this->getId(), "integer") .
                " AND parent_type = " . $db->quote($this->getParentType(), "text")
            );

            $set = $db->query(
                "SELECT edit_lock_user FROM page_object " .
                " WHERE page_id = " . $db->quote($this->getId(), "integer") .
                " AND parent_type = " . $db->quote($this->getParentType(), "text")
            );
            $rec = $db->fetchAssoc($set);
            if ($rec["edit_lock_user"] != $user->getId()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Release page lock
     */
    public function releasePageLock(): bool
    {
        $db = $this->db;
        $user = $this->user;
        $aset = new ilSetting("adve");

        $min = (int) $aset->get("block_mode_minutes");
        if ($min > 0) {
            // try to set the lock for the user
            $ts = time();
            $db->manipulate(
                "UPDATE page_object SET " .
                " edit_lock_user = " . $db->quote($user->getId(), "integer") . "," .
                " edit_lock_ts = 0" .
                " WHERE edit_lock_user = " . $db->quote($user->getId(), "integer") .
                " AND page_id = " . $db->quote($this->getId(), "integer") .
                " AND parent_type = " . $db->quote($this->getParentType(), "text")
            );

            $set = $db->query(
                "SELECT edit_lock_user FROM page_object " .
                " WHERE page_id = " . $db->quote($this->getId(), "integer") .
                " AND parent_type = " . $db->quote($this->getParentType(), "text")
            );
            $rec = $db->fetchAssoc($set);
            if ($rec["edit_lock_user"] != $user->getId()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get edit lock info
     */
    public function getEditLockInfo(): array
    {
        $db = $this->db;

        $aset = new ilSetting("adve");
        $min = (int) $aset->get("block_mode_minutes");

        $set = $db->query(
            "SELECT edit_lock_user, edit_lock_ts FROM page_object " .
            " WHERE page_id = " . $db->quote($this->getId(), "integer") .
            " AND parent_type = " . $db->quote($this->getParentType(), "text")
        );
        $rec = $db->fetchAssoc($set);
        $rec["edit_lock_until"] = $rec["edit_lock_ts"] + $min * 60;

        return $rec;
    }

    /**
     * Truncate (html) string
     * @see http://dodona.wordpress.com/2009/04/05/how-do-i-truncate-an-html-string-without-breaking-the-html-code/
     */
    public static function truncateHTML(
        string $a_text,
        int $a_length = 100,
        string $a_ending = '...',
        bool $a_exact = false,
        bool $a_consider_html = true
    ): string {
        $open_tags = [];
        if ($a_consider_html) {
            // if the plain text is shorter than the maximum length, return the whole text
            if (strlen(preg_replace('/<.*?>/', '', $a_text)) <= $a_length) {
                return $a_text;
            }

            // splits all html-tags to scanable lines
            $total_length = strlen($a_ending);
            $open_tags = array();
            $truncate = '';
            preg_match_all('/(<.+?>)?([^<>]*)/s', $a_text, $lines, PREG_SET_ORDER);
            foreach ($lines as $line_matchings) {
                // if there is any html-tag in this line, handle it and add it (uncounted) to the output
                if (!empty($line_matchings[1])) {
                    // if it's an "empty element" with or without xhtml-conform closing slash
                    if (preg_match(
                        '/^<(\s*.+?\/\s*|\s*(img|br|input|hr|area|base|basefont|col|frame|isindex|link|meta|param)(\s.+?)?)>$/is',
                        $line_matchings[1]
                    )) {
                        // do nothing
                    } // if tag is a closing tag
                    elseif (preg_match('/^<\s*\/([^\s]+?)\s*>$/s', $line_matchings[1], $tag_matchings)) {
                        // delete tag from $open_tags list
                        $pos = array_search($tag_matchings[1], $open_tags);
                        if ($pos !== false) {
                            unset($open_tags[$pos]);
                        }
                    } // if tag is an opening tag
                    elseif (preg_match('/^<\s*([^\s>!]+).*?>$/s', $line_matchings[1], $tag_matchings)) {
                        // add tag to the beginning of $open_tags list
                        array_unshift($open_tags, strtolower($tag_matchings[1]));
                    }
                    // add html-tag to $truncate'd text
                    $truncate .= $line_matchings[1];
                }

                // calculate the length of the plain text part of the line; handle entities as one character
                $content_length = strlen(preg_replace(
                    '/&[0-9a-z]{2,8};|&#[0-9]{1,7};|[0-9a-f]{1,6};/i',
                    ' ',
                    $line_matchings[2]
                ));
                if ($total_length + $content_length > $a_length) {
                    // the number of characters which are left
                    $left = $a_length - $total_length;
                    $entities_length = 0;
                    // search for html entities
                    if (preg_match_all(
                        '/&[0-9a-z]{2,8};|&#[0-9]{1,7};|[0-9a-f]{1,6};/i',
                        $line_matchings[2],
                        $entities,
                        PREG_OFFSET_CAPTURE
                    )) {
                        // calculate the real length of all entities in the legal range
                        foreach ($entities[0] as $entity) {
                            if ($entity[1] + 1 - $entities_length <= $left) {
                                $left--;
                                $entities_length += strlen($entity[0]);
                            } else {
                                // no more characters left
                                break;
                            }
                        }
                    }

                    // $truncate .= substr($line_matchings[2], 0, $left+$entities_length);
                    $truncate .= ilStr::shortenText($line_matchings[2], 0, $left + $entities_length);

                    // maximum lenght is reached, so get off the loop
                    break;
                } else {
                    $truncate .= $line_matchings[2];
                    $total_length += $content_length;
                }

                // if the maximum length is reached, get off the loop
                if ($total_length >= $a_length) {
                    break;
                }
            }
        } else {
            if (strlen($a_text) <= $a_length) {
                return $a_text;
            } else {
                // $truncate = substr($a_text, 0, $a_length - strlen($a_ending));
                $truncate = ilStr::shortenText($a_text, 0, $a_length - strlen($a_ending));
            }
        }

        // THIS IS BUGGY AS IT MIGHT BREAK AN OPEN TAG AT THE END
        if (!count($open_tags)) {
            // if the words shouldn't be cut in the middle...
            if (!$a_exact) {
                // ...search the last occurance of a space...
                $spacepos = strrpos($truncate, ' ');
                if ($spacepos !== false) {
                    // ...and cut the text in this position
                    // $truncate = substr($truncate, 0, $spacepos);
                    $truncate = ilStr::shortenText($truncate, 0, $spacepos);
                }
            }
        }

        // add the defined ending to the text
        $truncate .= $a_ending;

        if ($a_consider_html) {
            // close all unclosed html-tags
            foreach ($open_tags as $tag) {
                $truncate .= '</' . $tag . '>';
            }
        }

        return $truncate;
    }

    /**
     * Get content templates
     * @return array array of arrays with "id" => page id (int), "parent_type" => parent type (string), "title" => title (string)
     */
    public function getContentTemplates(): array
    {
        return array();
    }

    /**
     * Get all pages for parent object
     */
    public static function getLastChangeByParent(
        string $a_parent_type,
        int $a_parent_id,
        string $a_lang = ""
    ): string {
        global $DIC;

        $db = $DIC->database();

        $and_lang = "";
        if ($a_lang != "") {
            $and_lang = " AND lang = " . $db->quote($a_lang, "text");
        }

        $db->setLimit(1, 0);
        $q = "SELECT last_change FROM page_object " .
            " WHERE parent_id = " . $db->quote($a_parent_id, "integer") .
            " AND parent_type = " . $db->quote($a_parent_type, "text") . $and_lang .
            " ORDER BY last_change DESC";

        $set = $db->query($q);
        $rec = $db->fetchAssoc($set);

        return $rec["last_change"];
    }

    public function getEffectiveEditLockTime(): int
    {
        if ($this->getPageConfig()->getEditLockSupport() == false) {
            return 0;
        }

        $aset = new ilSetting("adve");
        $min = (int) $aset->get("block_mode_minutes");

        return $min;
    }

    /**
     * Resolve resources
     * @todo: move this into proper "afterImport" routine that calls all PC components
     */
    public function resolveResources(array $ref_mapping): bool
    {
        return ilPCResources::resolveResources($this, $ref_mapping);
    }

    /**
     * Get object id of repository object that contains this page, return 0 if page does not belong to a repo object
     */
    public function getRepoObjId(): ?int
    {
        return $this->getParentId();
    }

    /**
     * Get page component model
     * @return array
     */
    public function getPCModel(): array
    {
        $model = [];
        foreach ($this->getAllPCIds() as $pc_id) {
            $co = $this->getContentObjectForPcId($pc_id);
            if ($co !== null) {
                $co_model = $co->getModel();
                if ($co_model !== null) {
                    $model[$pc_id] = $co_model;
                }
            }
        }
        return $model;
    }

    /**
     * Assign characteristic
     * @return array|bool
     * @throws ilCOPagePCEditException
     * @throws ilCOPageUnknownPCTypeException
     * @throws ilDateTimeException
     */
    public function assignCharacteristic(
        array $targets,
        string $char_par,
        string $char_sec,
        string $char_med
    ) {
        if (is_array($targets)) {
            foreach ($targets as $t) {
                $tarr = explode(":", $t);
                $cont_obj = $this->getContentObject($tarr[0], $tarr[1]);
                if (is_object($cont_obj) && $cont_obj->getType() == "par") {
                    $cont_obj->setCharacteristic($char_par);
                }
                if (is_object($cont_obj) && $cont_obj->getType() == "sec") {
                    $cont_obj->setCharacteristic($char_sec);
                }
                if (is_object($cont_obj) && $cont_obj->getType() == "media") {
                    $cont_obj->setClass($char_med);
                }
            }
            return $this->update();
        }
        return true;
    }
}
