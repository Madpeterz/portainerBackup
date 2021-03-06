<?php

namespace YAPF\Framework\DbObjects\CollectionSet;

abstract class CollectionSet extends CollectionSetBulk
{
    /**
     * loadMatching
     * a very limited loading system
     * takes the keys as fields, and values as values
     * then passes that to loadWithConfig.
     * @return mixed[] [status =>  bool, count => integer, message =>  string]
     */
    public function loadMatching(array $input): array
    {
        $whereConfig = [
            "fields" => array_keys($input),
            "values" => array_values($input),
        ];
        return $this->loadWithConfig($whereConfig);
    }
    /**
     * loadIds
     * @deprecated please use loadIndexs
     * @return mixed[] [status =>  bool, count => integer, message =>  string]
     */
    public function loadIds(array $ids, string $field = "id"): array
    {
        return $this->loadIndexs($field, $ids);
    }

    /**
     * loadIds
     * @deprecated please use loadIndexs
     * @return mixed[] [status =>  bool, count => integer, message =>  string]
     */
    public function loadByValues(array $ids, string $field = "id"): array
    {
        return $this->loadIndexs($field, $ids);
    }
    /**
     * loadByField
     * alias of loadOnField
     * uses one field to load from the database with
     * for full control please use the method loadWithConfig
     * @deprecated public -> protected soon(TM)
     * @return mixed[] [status =>  bool, count => integer, message =>  string]
     */
    public function loadByField(
        string $field,
        $value,
        int $limit = 0,
        string $order = "id",
        string $order_dir = "DESC"
    ): array {
        return $this->loadOnField($field, $value, $limit, $order, $order_dir);
    }
    /**
     * loadOnField
     * uses one field to load from the database with
     * for full control please use the method loadWithConfig
     * @return mixed[] [status =>  bool, count => integer, message =>  string]
     */
    protected function loadOnField(
        string $field,
        $value,
        int $limit = 0,
        string $order_by = "id",
        string $by_direction = "DESC"
    ): array {
        if (is_object($value) == true) {
            $errormsg = "Attempted to pass value as a object!";
            $this->addError(__FILE__, __FUNCTION__, $errormsg);
            return ["status" => false,"message" => "Attempted to pass a value as a object!"];
        }
        $whereConfg = [
            "fields" => [$field],
            "values" => [$value],
        ];
        $orderConfig = ["ordering_enabled" => true,"order_field" => $order_by,"order_dir" => $by_direction];
        $optionsConfig = ["page_number" => 0,"max_entrys" => $limit];
        return $this->loadWithConfig($whereConfg, $orderConfig, $optionsConfig);
    }
    /**
     * loadLimited
     * alias of loadNewest
     * paged loading support with limiters
     * for full control please use the method loadWithConfig
     * @return mixed[] [status =>  bool, count => integer, message =>  string]
     */
    public function loadLimited(
        int $limit = 12,
        int $page = 0,
        string $order_by = "id",
        string $by_direction = "ASC",
        ?array $whereConfig = null
    ): array {
        return $this->loadNewest($limit, $page, $order_by, $by_direction, $whereConfg);
    }
    /**
     * loadNewest
     * default setup is to order by id newest first.
     * for full control please use the method loadWithConfig
     * @return mixed[] [status =>  bool, count => integer, message =>  string]
     */
    public function loadNewest(
        int $limit = 12,
        int $page = 0,
        string $order_by = "id",
        string $by_direction = "DESC",
        ?array $whereConfig = null
    ): array {
        return $this->loadWithConfig(
            $whereConfig,
            ["ordering_enabled" => true,"order_field" => $order_by,"order_dir" => $by_direction],
            ["page_number" => $page,"max_entrys" => $limit]
        );
    }
   /**
     * loadAll
     * Loads everything it can get its hands
     * ordered by id ASC by default
     * for full control please use the method loadWithConfig
     * @return mixed[] [status =>  bool, count => integer, message =>  string]
     */
    public function loadAll(string $order_by = "id", string $by_direction = "ASC"): array
    {
        return $this->loadWithConfig(
            null,
            ["ordering_enabled" => true,"order_field" => $order_by,"order_dir" => $by_direction]
        );
    }


   /**
     * loadWithConfig
     * Uses the select V2 system to load data
     * its magic!
     * see the v2 readme
     * @return mixed[] [status =>  bool, count => integer, message =>  string]
     */
    public function loadWithConfig(
        ?array $whereConfig = null,
        ?array $order_config = null,
        ?array $options_config = null,
        ?array $join_tables = null
    ): array {
        $this->makeWorker();
        $basic_config = ["table" => $this->worker->getTable()];
        if ($this->disableUpdates == true) {
            $basic_config["fields"] = $this->limitedFields;
        }
        $whereConfig = $this->worker->extendWhereConfig($whereConfig);
        // Cache support
        $hitCache = false;
        $hashme = "";
        if ($this->cache != null) {
            $mergeddata = $basic_config;
            if (is_array($join_tables) == true) {
                $mergeddata = array_merge($basic_config, $join_tables);
            }
            $hashme = $this->cache->getHash(
                $whereConfig,
                $order_config,
                $options_config,
                $mergeddata,
                $this->getTable(),
                count($this->worker->getFields())
            );
            $hitCache = $this->cache->cacheVaild($this->getTable(), $hashme);
        }
        if ($hitCache == true) {
            // wooo vaild data from cache!
            $loadme = $this->cache->readHash($this->getTable(), $hashme);
            if (is_array($loadme) == true) {
                return $this->processLoad($loadme);
            }
        }
        // Cache missed, read from the DB
        $load_data = $this->sql->selectV2(
            $basic_config,
            $order_config,
            $whereConfig,
            $options_config,
            $join_tables
        );
        if ($load_data["status"] == false) {
            $error_msg = get_class($this) . " Unable to load data: " . $load_data["message"];
            return $this->addError(__FILE__, __FUNCTION__, $error_msg, ["count" => 0]);
        }
        if ($this->cache != null) {
            // push data to cache so we can avoid reading from DB as much
            $this->cache->writeHash($this->worker->getTable(), $hashme, $load_data, $this->cacheAllowChanged);
        }
        return $this->processLoad($load_data);
    }



    /**
     * loadDataFromList
     * @deprecated
     * see class generated loadFrom{Fieldname}s function
     * @return mixed[] [status =>  bool, count => integer, message =>  string]
     */
    public function loadDataFromList(string $fieldname = "id", array $values = []): array
    {
        return $this->loadIndexs($fieldname, $values);
    }

    /**
     * loadIndexs
     * returns where fieldname value for the row is IN $values
     * @return mixed[] [status =>  bool, count => integer, message =>  string]
     */
    protected function loadIndexs(string $fieldname = "id", array $values = []): array
    {
        $this->makeWorker();
        $uids = [];
        foreach ($values as $id) {
            if (in_array($id, $uids) == false) {
                $uids[] = $id;
            }
        }
        if (count($uids) == 0) {
            return $this->addError(__FILE__, __FUNCTION__, "No ids sent!", ["count" => 0]);
        }
        $typecheck = $this->worker->getFieldType($fieldname, true);
        if ($typecheck == null) {
            $this->addError(__FILE__, __FUNCTION__, "Unable to find field: " .
            $fieldname . " in worker reported error: " . $this->worker->getLastError());
            return [
                "status" => false,
                "count" => 0,
                "message" => "Invaild field: " . $fieldname,
            ];
        }
        return $this->loadWithConfig([
            "fields" => [$fieldname],
            "matches" => ["IN"],
            "values" => [$uids],
            "types" => [$typecheck],
        ]);
    }
    /**
     * processLoad
     * takes the reply from mysqli and fills out objects and builds the collection
     * @return mixed[] [status =>  bool, count => integer, message =>  string]
     */
    protected function processLoad($load_data = []): array
    {
        $this->makeWorker();
        $use_field = $this->worker->use_id_field;
        if ($this->worker->bad_id == false) {
            $use_field = "id";
        }
        foreach ($load_data["dataset"] as $entry) {
            $new_object = new $this->worker_class($entry);
            if ($this->disableUpdates == true) {
                $new_object->noUpdates();
            }
            if ($new_object->isLoaded() == true) {
                $this->collected[$entry[$use_field]] = $new_object;
            }
        }
        $this->rebuildIndex();
        return ["status" => true, "count" => count($this->collected), "message" => "ok"];
    }
}
