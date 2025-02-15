<?php

declare(strict_types=0);
/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Class ilChangeEvent tracks change events on repository objects.
 * The following events are considered to be a 'write event':
 *  - The creation of a new repository object
 *  - A change of the data or meta-data of an object
 *  - A move, link, copy, deletion or undeletion of the object
 * UI objects, which cause a 'write event', must call _recordWriteEvent(...)
 * In most cases, UI objects let the user catch up with write events on the
 * object, when doing this call.
 * The following events are considered to be a 'read event':
 *  - Opening a container object in the browser*
 *  - Opening / downloading / reading an object
 * UI objects, which cause a 'read event', must call _recordReadEvent(...).
 * In most cases, UI objects let the user catch up with write events on the
 * object, when doing this call.
 * *reading the content of a container using WebDAV is not counted, because WebDAV
 *  clients can't see all objects in a container.
 * A user can catch up with write events, by calling __catchupWriteEvents(...).
 * A user can query, if an object has changed, since the last time he has caught
 * up with write events, by calling _lookupUncaughtWriteEvents(...).
 * @author        Werner Randelshofer <werner.randelshofer@hslu.ch>
 * @version       $Id: class.ilChangeEvent.php,v 1.02 2007/05/07 19:25:34 wrandels Exp $
 */
class ilChangeEvent
{
    private static array $has_accessed = [];

    /**
     * Records a write event.
     * The parent object should be specified for the 'delete', 'undelete' and
     * 'add' and 'remove' events.
     * @param int      $obj_id        The object which was written to.
     * @param int      $usr_id        The user who performed a write action.
     * @param string   $action        The name of the write action.
     *                                'create', 'update', 'delete', 'add', 'remove', 'undelete'.
     * @param int|null $parent_obj_id The object id of the parent object.
     *                                If this is null, then the event is recorded for all parents
     *                                of the object. If this is not null, then the event is only
     *                                recorded for the specified parent.
     * @return void
     */
    public static function _recordWriteEvent(
        int $obj_id,
        int $usr_id,
        string $action,
        ?int $parent_obj_id = null
    ): void {
        global $DIC;

        $ilDB = $DIC['ilDB'];

        /* see _recordReadEvent
        if (!ilChangeEvent::_isActive())
        {
            return;
        }
        */

        if ($parent_obj_id == null) {
            $pset = $ilDB->query(
                'SELECT r2.obj_id par_obj_id FROM object_reference r1 ' .
                'JOIN tree t ON t.child = r1.ref_id ' .
                'JOIN object_reference r2 ON r2.ref_id = t.parent ' .
                'WHERE r1.obj_id = ' . $ilDB->quote($obj_id, 'integer')
            );

            while ($prec = $ilDB->fetchAssoc($pset)) {
                $nid = $ilDB->nextId("write_event");
                $query = sprintf(
                    'INSERT INTO write_event ' .
                    '(write_id, obj_id, parent_obj_id, usr_id, action, ts) VALUES ' .
                    '(%s, %s, %s, %s, %s, ' . $ilDB->now() . ')',
                    $ilDB->quote($nid, 'integer'),
                    $ilDB->quote($obj_id, 'integer'),
                    $ilDB->quote($prec["par_obj_id"], 'integer'),
                    $ilDB->quote($usr_id, 'integer'),
                    $ilDB->quote($action, 'text')
                );

                $aff = $ilDB->manipulate($query);
            }
        } else {
            $nid = $ilDB->nextId("write_event");
            $query = sprintf(
                'INSERT INTO write_event ' .
                '(write_id, obj_id, parent_obj_id, usr_id, action, ts) ' .
                'VALUES (%s,%s,%s,%s,%s,' . $ilDB->now() . ')',
                $ilDB->quote($nid, 'integer'),
                $ilDB->quote($obj_id, 'integer'),
                $ilDB->quote($parent_obj_id, 'integer'),
                $ilDB->quote($usr_id, 'integer'),
                $ilDB->quote($action, 'text')
            );
            $aff = $ilDB->manipulate($query);
        }
    }

    public static function _recordReadEvent(
        string $a_type,
        int $a_ref_id,
        int $obj_id,
        int $usr_id,
        bool $isCatchupWriteEvents = true,
        $a_ext_rc = null,
        $a_ext_time = null
    ): void {
        global $DIC;

        $ilDB = $DIC['ilDB'];
        $tree = $DIC['tree'];

        $validTimeSpan = ilObjUserTracking::_getValidTimeSpan();

        $query = sprintf(
            'SELECT * FROM read_event ' .
            'WHERE obj_id = %s ' .
            'AND usr_id = %s ',
            $ilDB->quote($obj_id, 'integer'),
            $ilDB->quote($usr_id, 'integer')
        );
        $res = $ilDB->query($query);
        $row = $ilDB->fetchObject($res);

        // read counter
        if ($a_ext_rc !== null) {
            $read_count = 'read_count = ' . $ilDB->quote(
                $a_ext_rc,
                "integer"
            ) . ", ";
            $read_count_init = max(1, (int) $a_ext_rc);
            $read_count_diff = max(1, (int) $a_ext_rc) - $row->read_count;
        } else {
            $read_count = 'read_count = read_count + 1, ';
            $read_count_init = 1;
            $read_count_diff = 1;
        }

        if ($row) {
            if ($a_ext_time !== null) {
                $time = (int) $a_ext_time;
            } else {
                $time = $ilDB->quote(
                    (time() - $row->last_access) <= $validTimeSpan
                        ? $row->spent_seconds + time() - $row->last_access
                        : $row->spent_seconds,
                    'integer'
                );

                // if we are in the valid interval, we do not
                // add anything to the read_count, since this is the
                // same access for us
                if ((time() - $row->last_access) <= $validTimeSpan) {
                    $read_count = '';
                    $read_count_init = 1;
                    $read_count_diff = 0;
                }
            }
            $time_diff = $time - (int) ($row->spent_seconds ?? 0);

            // Update
            $query = sprintf(
                'UPDATE read_event SET ' .
                $read_count .
                'spent_seconds = %s, ' .
                'last_access = %s ' .
                'WHERE obj_id = %s ' .
                'AND usr_id = %s ',
                $time,
                $ilDB->quote(time(), 'integer'),
                $ilDB->quote($obj_id, 'integer'),
                $ilDB->quote($usr_id, 'integer')
            );
            $aff = $ilDB->manipulate($query);

            self::_recordObjStats($obj_id, $time_diff, $read_count_diff);
        } else {
            if ($a_ext_time !== false) {
                $time = (int) $a_ext_time;
            } else {
                $time = 0;
            }

            $time_diff = $time - (int) ($row->spent_seconds ?? 0);

            // #10407
            $ilDB->replace(
                'read_event',
                array(
                    'obj_id' => array('integer', $obj_id),
                    'usr_id' => array('integer', $usr_id)
                ),
                array(
                    'read_count' => array('integer', $read_count_init),
                    'spent_seconds' => array('integer', $time),
                    'first_access' => array('timestamp', date("Y-m-d H:i:s")),
                    // was $ilDB->now()
                    'last_access' => array('integer', time())
                )
            );

            self::$has_accessed[$obj_id][$usr_id] = true;

            self::_recordObjStats($obj_id, $time_diff, $read_count_diff);
        }

        if ($isCatchupWriteEvents) {
            ilChangeEvent::_catchupWriteEvents($obj_id, $usr_id);
        }

        // update parents (no categories or root)
        if (!in_array($a_type, array("cat", "root", "crs"))) {
            if ($tree->isInTree($a_ref_id)) {
                $path = $tree->getPathId($a_ref_id);

                foreach ($path as $p) {
                    $obj2_id = ilObject::_lookupObjId($p);
                    $obj2_type = ilObject::_lookupType($obj2_id);
                    //echo "<br>1-$obj2_type-$p-$obj2_id-";
                    if (($p != $a_ref_id) && (in_array(
                        $obj2_type,
                        array("crs",
                                              "fold",
                                              "grp",
                                              "lso"
                        )
                    ))) {
                        $query = sprintf(
                            'SELECT * FROM read_event ' .
                            'WHERE obj_id = %s ' .
                            'AND usr_id = %s ',
                            $ilDB->quote($obj2_id, 'integer'),
                            $ilDB->quote($usr_id, 'integer')
                        );
                        $res2 = $ilDB->query($query);
                        if ($row2 = $ilDB->fetchAssoc($res2)) {
                            //echo "<br>2";
                            // update read count and spent seconds
                            $query = sprintf(
                                'UPDATE read_event SET ' .
                                'childs_read_count = childs_read_count + %s ,' .
                                'childs_spent_seconds = childs_spent_seconds + %s ' .
                                'WHERE obj_id = %s ' .
                                'AND usr_id = %s ',
                                $ilDB->quote((int) $read_count_diff, 'integer'),
                                $ilDB->quote((int) $time_diff, 'integer'),
                                $ilDB->quote($obj2_id, 'integer'),
                                $ilDB->quote($usr_id, 'integer')
                            );
                            $aff = $ilDB->manipulate($query);

                            self::_recordObjStats(
                                $obj2_id,
                                null,
                                null,
                                (int) $time_diff,
                                (int) $read_count_diff
                            );
                        } else {
                            // #10407
                            $ilDB->replace(
                                'read_event',
                                array(
                                    'obj_id' => array('integer', $obj2_id),
                                    'usr_id' => array('integer', $usr_id)
                                ),
                                array(
                                    'read_count' => array('integer', 1),
                                    'spent_seconds' => array('integer', $time),
                                    'first_access' => array('timestamp',
                                                            date("Y-m-d H:i:s")
                                    ), // was $ilDB->now()
                                    'last_access' => array('integer', time()),
                                    'childs_read_count' => array('integer',
                                                                 (int) $read_count_diff
                                    ),
                                    'childs_spent_seconds' => array('integer',
                                                                    (int) $time_diff
                                    )
                                )
                            );

                            self::$has_accessed[$obj2_id][$usr_id] = true;

                            self::_recordObjStats(
                                $obj2_id,
                                $time,
                                1,
                                (int) $time_diff,
                                (int) $read_count_diff
                            );
                        }
                    }
                }
            }
        }

        // @todo:
        // - calculate diff of spent_seconds and read_count
        // - use ref id to get parents of types grp, crs, fold
        // - add diffs to childs_spent_seconds and childs_read_count
    }

    public static function _recordObjStats(
        int $a_obj_id,
        ?int $a_spent_seconds,
        ?int $a_read_count,
        ?int $a_childs_spent_seconds = null,
        ?int $a_child_read_count = null
    ): void {
        global $DIC;

        $ilDB = $DIC['ilDB'];

        if (!ilObjUserTracking::_enabledObjectStatistics() ||
            $a_obj_id <= 0) { // #12706
            return;
        }

        $now = time();

        $fields = array();
        $fields['log_id'] = array("integer", $ilDB->nextId('obj_stat_log'));
        $fields["obj_id"] = array("integer", $a_obj_id);
        $fields["obj_type"] = array("text", ilObject::_lookupType($a_obj_id));
        $fields["tstamp"] = array("timestamp", $now);
        $fields["yyyy"] = array("integer", date("Y"));
        $fields["mm"] = array("integer", date("m"));
        $fields["dd"] = array("integer", date("d"));
        $fields["hh"] = array("integer", date("H"));
        if ($a_spent_seconds > 0) {
            $fields["spent_seconds"] = array("integer", $a_spent_seconds);
        }
        if ($a_read_count > 0) {
            $fields["read_count"] = array("integer", $a_read_count);
        }
        if ($a_childs_spent_seconds > 0) {
            $fields["childs_spent_seconds"] = array("integer",
                                                    $a_childs_spent_seconds
            );
        }
        if ($a_child_read_count > 0) {
            $fields["childs_read_count"] = array("integer",
                                                 $a_child_read_count
            );
        }
        $ilDB->insert("obj_stat_log", $fields);

        // 0.01% probability
        if (mt_rand(1, 100) == 1) {
            self::_syncObjectStats($now);
        }
    }

    public static function _syncObjectStats(
        ?int $a_now = null,
        int $a_minimum = 20000
    ) {
        global $DIC;

        $ilDB = $DIC['ilDB'];

        if (!$a_now) {
            $a_now = time();
        }

        set_time_limit(0);

        // has source table enough entries?
        $set = $ilDB->query("SELECT COUNT(*) AS counter FROM obj_stat_log");
        $row = $ilDB->fetchAssoc($set);
        if ($row["counter"] >= $a_minimum) {
            $ilAtomQuery = $ilDB->buildAtomQuery();
            $ilAtomQuery->addTableLock('obj_stat_log');
            $ilAtomQuery->addTableLock('obj_stat_tmp');

            $ilAtomQuery->addQueryCallable(
                function (ilDBInterface $ilDB) use ($a_now, $a_minimum, &$ret) {
                    // if other process was transferring, we had to wait for the lock and
                    // the source table should now have less than minimum/needed entries
                    $set = $ilDB->query(
                        "SELECT COUNT(*) AS counter FROM obj_stat_log"
                    );
                    $row = $ilDB->fetchAssoc($set);
                    if ($row["counter"] >= $a_minimum) {
                        // use only "full" seconds to have a clear cut
                        $ilDB->query(
                            "INSERT INTO obj_stat_tmp" .
                            " SELECT * FROM obj_stat_log" .
                            " WHERE tstamp < " . $ilDB->quote(
                                $a_now,
                                "timestamp"
                            )
                        );

                        // remove transferred entries from source table
                        $ilDB->query(
                            "DELETE FROM obj_stat_log" .
                            " WHERE tstamp < " . $ilDB->quote(
                                $a_now,
                                "timestamp"
                            )
                        );

                        $ret = true;
                    } else {
                        $ret = false;
                    }
                }
            );

            $ilAtomQuery->run();

            //continue only if obj_stat_log counter >= $a_minimum
            if ($ret) {
                $ilAtomQuery = $ilDB->buildAtomQuery();
                $ilAtomQuery->addTableLock('obj_stat_tmp');
                $ilAtomQuery->addTableLock('obj_stat');

                $ilAtomQuery->addQueryCallable(
                    function (ilDBInterface $ilDB) use ($a_now, $a_minimum) {
                        // process log data (timestamp is not needed anymore)
                        $sql = "SELECT obj_id, obj_type, yyyy, mm, dd, hh, SUM(read_count) AS read_count," .
                            " SUM(childs_read_count) AS childs_read_count, SUM(spent_seconds) AS spent_seconds," .
                            " SUM(childs_spent_seconds) AS childs_spent_seconds" .
                            " FROM obj_stat_tmp" .
                            " GROUP BY obj_id, obj_type, yyyy, mm, dd, hh";
                        $set = $ilDB->query($sql);
                        while ($row = $ilDB->fetchAssoc($set)) {
                            // "primary key"
                            $where = array("obj_id" => array("integer",
                                                             $row["obj_id"]
                            ),
                                           "obj_type" => array("text",
                                                               $row["obj_type"]
                                           ),
                                           "yyyy" => array("integer",
                                                           $row["yyyy"]
                                           ),
                                           "mm" => array("integer", $row["mm"]),
                                           "dd" => array("integer", $row["dd"]),
                                           "hh" => array("integer", $row["hh"])
                            );

                            $where_sql = array();
                            foreach ($where as $field => $def) {
                                $where_sql[] = $field . " = " . $ilDB->quote(
                                    $def[1],
                                    $def[0]
                                );
                            }
                            $where_sql = implode(" AND ", $where_sql);

                            // existing entry?
                            $check = $ilDB->query(
                                "SELECT read_count, childs_read_count, spent_seconds," .
                                "childs_spent_seconds" .
                                " FROM obj_stat" .
                                " WHERE " . $where_sql
                            );
                            if ($ilDB->numRows($check)) {
                                $old = $ilDB->fetchAssoc($check);

                                // add existing values
                                $fields = array("read_count" => array("integer",
                                                                      $old["read_count"] + $row["read_count"]
                                ),
                                                "childs_read_count" => array("integer",
                                                                             $old["childs_read_count"] + $row["childs_read_count"]
                                                ),
                                                "spent_seconds" => array("integer",
                                                                         $old["spent_seconds"] + $row["spent_seconds"]
                                                ),
                                                "childs_spent_seconds" => array("integer",
                                                                                $old["childs_spent_seconds"] + $row["childs_spent_seconds"]
                                                )
                                );

                                $ilDB->update("obj_stat", $fields, $where);
                            } else {
                                // new entry
                                $fields = $where;
                                $fields["read_count"] = array("integer",
                                                              $row["read_count"]
                                );
                                $fields["childs_read_count"] = array("integer",
                                                                     $row["childs_read_count"]
                                );
                                $fields["spent_seconds"] = array("integer",
                                                                 $row["spent_seconds"]
                                );
                                $fields["childs_spent_seconds"] = array("integer",
                                                                        $row["childs_spent_seconds"]
                                );

                                $ilDB->insert("obj_stat", $fields);
                            }
                        }

                        // clean up transfer table
                        $ilDB->query("DELETE FROM obj_stat_tmp");
                    }
                );

                $ilAtomQuery->run();
            }
        }
    }

    /**
     * Catches up with all write events which occured before the specified
     * timestamp.
     * @param $obj_id    int The object.
     * @param $usr_id    int The user.
     * @param $timestamp string|null timestamp.
     * @return void
     */
    public static function _catchupWriteEvents(
        int $obj_id,
        int $usr_id,
        ?string $timestamp = null
    ): void {
        global $DIC;

        $ilDB = $DIC['ilDB'];

        $query = "SELECT obj_id FROM catch_write_events " .
            "WHERE obj_id = " . $ilDB->quote($obj_id, 'integer') . " " .
            "AND usr_id  = " . $ilDB->quote($usr_id, 'integer');
        $res = $ilDB->query($query);
        if ($res->numRows()) {
            $ts = ($timestamp == null)
                ? ilUtil::now()
                : $timestamp;
        } else {
            $ts = ilUtil::now();
        }

        // alex, use replace due to bug #10406
        $ilDB->replace(
            "catch_write_events",
            array(
                "obj_id" => array("integer", $obj_id),
                "usr_id" => array("integer", $usr_id)
            ),
            array(
                "ts" => array("timestamp", $ts)
            )
        );
    }

    /**
     * Reads all write events which occured on the object
     * which happened after the last time the user caught up with them.
     * @param $obj_id int The object
     * @param $usr_id int The user who is interested into these events.
     * @return array with rows from table write_event
     */
    public static function _lookupUncaughtWriteEvents(
        int $obj_id,
        int $usr_id
    ): array {
        global $DIC;

        $ilDB = $DIC['ilDB'];
        $q = "SELECT ts " .
            "FROM catch_write_events " .
            "WHERE obj_id=" . $ilDB->quote($obj_id, 'integer') . " " .
            "AND usr_id=" . $ilDB->quote($usr_id, 'integer');
        $r = $ilDB->query($q);
        $catchup = null;
        while ($row = $r->fetchRow(ilDBConstants::FETCHMODE_ASSOC)) {
            $catchup = $row['ts'];
        }

        if ($catchup == null) {
            $query = sprintf(
                'SELECT * FROM write_event ' .
                'WHERE obj_id = %s ' .
                'AND usr_id <> %s ' .
                'ORDER BY ts DESC',
                $ilDB->quote($obj_id, 'integer'),
                $ilDB->quote($usr_id, 'integer')
            );
            $res = $ilDB->query($query);
        } else {
            $query = sprintf(
                'SELECT * FROM write_event ' .
                'WHERE obj_id = %s ' .
                'AND usr_id <> %s ' .
                'AND ts >= %s ' .
                'ORDER BY ts DESC',
                $ilDB->quote($obj_id, 'integer'),
                $ilDB->quote($usr_id, 'integer'),
                $ilDB->quote($catchup, 'timestamp')
            );
            $res = $ilDB->query($query);
        }
        $events = array();
        while ($row = $ilDB->fetchAssoc($res)) {
            $events[] = $row;
        }
        return $events;
    }

    /**
     * Returns the change state of the object for the specified user.
     * which happened after the last time the user caught up with them.
     * @param $obj_id int The object
     * @param $usr_id int The user who is interested into these events.
     * @return int 0 = object is unchanged,
     *                1 = object is new,
     *                2 = object has changed
     */
    public static function _lookupChangeState(int $obj_id, int $usr_id): int
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];

        $q = "SELECT ts " .
            "FROM catch_write_events " .
            "WHERE obj_id=" . $ilDB->quote($obj_id, 'integer') . " " .
            "AND usr_id=" . $ilDB->quote($usr_id, 'integer');
        $r = $ilDB->query($q);
        $catchup = null;
        while ($row = $r->fetchRow(ilDBConstants::FETCHMODE_ASSOC)) {
            $catchup = $row['ts'];
        }

        if ($catchup == null) {
            $ilDB->setLimit(1);
            $query = sprintf(
                'SELECT * FROM write_event ' .
                'WHERE obj_id = %s ' .
                'AND usr_id <> %s ',
                $ilDB->quote($obj_id, 'integer'),
                $ilDB->quote($usr_id, 'integer')
            );
            $res = $ilDB->query($query);
        } else {
            $ilDB->setLimit(1);
            $query = sprintf(
                'SELECT * FROM write_event ' .
                'WHERE obj_id = %s ' .
                'AND usr_id <> %s ' .
                'AND ts > %s ',
                $ilDB->quote($obj_id, 'integer'),
                $ilDB->quote($usr_id, 'integer'),
                $ilDB->quote($catchup, 'timestamp')
            );
            $res = $ilDB->query($query);
        }

        $numRows = $res->numRows();
        if ($numRows > 0) {
            $row = $ilDB->fetchAssoc($res);
            // if we have write events, and user never catched one, report as new (1)
            // if we have write events, and user catched an old write event, report as changed (2)
            return ($catchup == null) ? 1 : 2;
        } else {
            return 0; // user catched all write events, report as unchanged (0)
        }
    }

    /**
     * Reads all read events which occured on the object.
     * @param $obj_id int The object
     * @param $usr_id int Optional, the user who performed these events.
     */
    public static function _lookupReadEvents($obj_id, $usr_id = null)
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];

        if ($usr_id == null) {
            $query = sprintf(
                'SELECT * FROM read_event ' .
                'WHERE obj_id = %s ' .
                'ORDER BY last_access DESC',
                $ilDB->quote($obj_id, 'integer')
            );
            $res = $ilDB->query($query);
        } else {
            $query = sprintf(
                'SELECT * FROM read_event ' .
                'WHERE obj_id = %s ' .
                'AND usr_id = %s ' .
                'ORDER BY last_access DESC',
                $ilDB->quote($obj_id, 'integer'),
                $ilDB->quote($usr_id, 'integer')
            );
            $res = $ilDB->query($query);
        }

        $counter = 0;
        $events = [];
        while ($row = $ilDB->fetchAssoc($res)) {
            $events[$counter]['obj_id'] = $row['obj_id'];
            $events[$counter]['usr_id'] = $row['usr_id'];
            $events[$counter]['last_access'] = $row['last_access'];
            $events[$counter]['read_count'] = $row['read_count'];
            $events[$counter]['spent_seconds'] = $row['spent_seconds'];
            $events[$counter]['first_access'] = $row['first_access'];

            $counter++;
        }
        return $events;
    }

    public static function lookupUsersInProgress(int $a_obj_id): array
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];

        $query = sprintf(
            'SELECT DISTINCT(usr_id) usr FROM read_event ' .
            'WHERE obj_id = %s ',
            $ilDB->quote($a_obj_id, 'integer')
        );
        $res = $ilDB->query($query);
        $users = [];
        while ($row = $ilDB->fetchObject($res)) {
            $users[] = (int) $row->usr;
        }
        return $users;
    }

    /**
     * Has accessed
     */
    public static function hasAccessed(int $a_obj_id, int $a_usr_id): bool
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];

        if (isset(self::$has_accessed[$a_obj_id][$a_usr_id])) {
            return self::$has_accessed[$a_obj_id][$a_usr_id];
        }

        $set = $ilDB->query(
            "SELECT usr_id FROM read_event WHERE " .
            "obj_id = " . $ilDB->quote($a_obj_id, "integer") . " AND " .
            "usr_id = " . $ilDB->quote($a_usr_id, "integer")
        );
        if ($rec = $ilDB->fetchAssoc($set)) {
            return self::$has_accessed[$a_obj_id][$a_usr_id] = true;
        }
        return self::$has_accessed[$a_obj_id][$a_usr_id] = false;
    }

    /**
     * Activates change event tracking.
     */
    public static function _activate(): bool
    {
        if (ilChangeEvent::_isActive()) {
            return false;
        } else {
            global $DIC;

            $ilDB = $DIC['ilDB'];

            // Insert initial data into table write_event
            // We need to do this here, because we need
            // to catch up write events that occured while the change event tracking was
            // deactivated.

            // IGNORE isn't supported in oracle
            $set = $ilDB->query(
                sprintf(
                    'SELECT r1.obj_id,r2.obj_id p,d.owner,%s,d.create_date ' .
                    'FROM object_data d ' .
                    'LEFT JOIN write_event w ON d.obj_id = w.obj_id ' .
                    'JOIN object_reference r1 ON d.obj_id=r1.obj_id ' .
                    'JOIN tree t ON t.child=r1.ref_id ' .
                    'JOIN object_reference r2 on r2.ref_id=t.parent ' .
                    'WHERE w.obj_id IS NULL',
                    $ilDB->quote('create', 'text')
                )
            );
            $res = null;
            while ($rec = $ilDB->fetchAssoc($set)) {
                $nid = $ilDB->nextId("write_event");
                $query = 'INSERT INTO write_event ' .
                    '(write_id, obj_id,parent_obj_id,usr_id,action,ts) VALUES (' .
                    $ilDB->quote($nid, "integer") . "," .
                    $ilDB->quote($rec["obj_id"], "integer") . "," .
                    $ilDB->quote($rec["p"], "integer") . "," .
                    $ilDB->quote($rec["owner"], "integer") . "," .
                    $ilDB->quote("create", "text") . "," .
                    $ilDB->quote($rec["create_date"], "timestamp") .
                    ')';
                $res = $ilDB->query($query);
            }

            global $DIC;

            $ilSetting = $DIC['ilSetting'];
            $ilSetting->set('enable_change_event_tracking', '1');

            return $res !== null;
        }
    }

    /**
     * Deactivates change event tracking.
     */
    public static function _deactivate(): bool
    {
        global $DIC;

        $ilSetting = $DIC['ilSetting'];
        $ilSetting->set('enable_change_event_tracking', '0');
        return true;
    }

    /**
     * Returns true, if change event tracking is active.
     */
    public static function _isActive(): bool
    {
        global $DIC;

        $ilSetting = $DIC['ilSetting'];
        return $ilSetting->get('enable_change_event_tracking', '0') == '1';
    }

    /**
     * Delete object entries
     */
    public static function _delete(int $a_obj_id): bool
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];
        $query = sprintf(
            'DELETE FROM write_event WHERE obj_id = %s ',
            $ilDB->quote($a_obj_id, 'integer')
        );
        $aff = $ilDB->manipulate($query);

        $query = sprintf(
            'DELETE FROM read_event WHERE obj_id = %s ',
            $ilDB->quote($a_obj_id, 'integer')
        );
        $aff = $ilDB->manipulate($query);
        return true;
    }

    public static function _deleteReadEvents(int $a_obj_id): void
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];

        $ilDB->manipulate(
            "DELETE FROM read_event" .
            " WHERE obj_id = " . $ilDB->quote($a_obj_id, "integer")
        );
    }

    public static function _deleteReadEventsForUsers(
        int $a_obj_id,
        array $a_user_ids
    ): void {
        global $DIC;

        $ilDB = $DIC['ilDB'];

        $ilDB->manipulate(
            "DELETE FROM read_event" .
            " WHERE obj_id = " . $ilDB->quote($a_obj_id, "integer") .
            " AND " . $ilDB->in("usr_id", $a_user_ids, "", "integer")
        );
    }

    public static function _getAllUserIds(int $a_obj_id): array
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];
        $res = array();
        $set = $ilDB->query(
            "SELECT usr_id FROM read_event" .
            " WHERE obj_id = " . $ilDB->quote($a_obj_id, "integer")
        );
        while ($row = $ilDB->fetchAssoc($set)) {
            $res[] = (int) $row["usr_id"];
        }
        return $res;
    }

    /**
     * _updateAccessForScormOfflinePlayer
     * needed to synchronize last_access and first_access when learning modul is used offline
     * called in ./Modules/ScormAicc/classes/class.ilSCORMOfflineMode.php
     */
    public static function _updateAccessForScormOfflinePlayer(
        int $obj_id,
        int $usr_id,
        int $i_last_access,
        string $t_first_access
    ): bool {
        global $DIC;

        $ilDB = $DIC->database();
        $res = $ilDB->queryF(
            'UPDATE read_event SET first_access=%s, last_access = %s WHERE obj_id=%s AND usr_id=%s',
            array('timestamp', 'integer', 'integer', 'integer'),
            array($t_first_access, $i_last_access, $obj_id, $usr_id)
        );
        return true;
    }
}
