<?php

/**
 * Essentially a copy of https://github.com/waza-ari/monolog-mysql by waza-ari
 * Terribly taped together to work with PostgreSQL
 * Will probably get fixed later
 * Please don't come after me :(
 */

use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;
use PDO;
use PDOStatement;

class PgSQLHandler extends AbstractProcessingHandler
{
    private $initialized = false;
    protected $pdo;
    private $statement;
    private $table = 'logs';
    private $defaultfields = array('id', 'channel', 'level', 'message', 'time');
    private $additionalFields = array();
    private $fields = array();

    public function __construct(PDO $pdo = null, $table, $additionalFields = array(), $level = Logger::DEBUG, $bubble = true) {
       if (!is_null($pdo)) {
            $this->pdo = $pdo;
        }
        $this->table = $table;
        $this->additionalFields = $additionalFields;
        parent::__construct($level, $bubble);
    }

    private function initialize() {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS "'.$this->table.'" (id BIGSERIAL PRIMARY KEY, channel VARCHAR(255), level INTEGER, message TEXT, time BIGINT)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS "'.$this->table.'_channel_idx" ON "'.$this->table.'" USING HASH (channel)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS "'.$this->table.'_level_idx" ON "'.$this->table.'" USING HASH (level)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS "'.$this->table.'_time_idx" ON "'.$this->table.'" USING BTREE (time)');
        $actualFields = array();
        $rs = $this->pdo->query('SELECT * FROM"'.$this->table.'" LIMIT 0');
        for ($i = 0; $i < $rs->columnCount(); $i++) {
            $col = $rs->getColumnMeta($i);
            $actualFields[] = $col['name'];
        }
        $removedColumns = array_diff($actualFields, $this->additionalFields, $this->defaultfields);
        $addedColumns = array_diff($this->additionalFields, $actualFields);
        if (!empty($removedColumns)) {
            foreach ($removedColumns as $c) {
                $this->pdo->exec('ALTER TABLE "'.$this->table.'" DROP "'.$c.'";');
            }
        }
        if (!empty($addedColumns)) {
            foreach ($addedColumns as $c) {
                $this->pdo->exec('ALTER TABLE "'.$this->table.'" add "'.$c.'" TEXT NULL DEFAULT NULL;');
            }
        }
        $this->defaultfields = array_merge($this->defaultfields, $this->additionalFields);
        $this->initialized = true;
    }

    private function prepareStatement()
    {
        $columns = "";
        $fields  = "";
        foreach ($this->fields as $key => $f) {
            if ($f == 'id') {
                continue;
            }
            if ($key == 1) {
                $columns .= "$f";
                $fields .= ":$f";
                continue;
            }
            $columns .= ", $f";
            $fields .= ", :$f";
        }
        $this->statement = $this->pdo->prepare('INSERT INTO "'.$this->table.'" ('.$columns.') VALUES ('.$fields.')');
    }

    protected function write(array $record)
    {
        if (!$this->initialized) {
            $this->initialize();
        }
        $this->fields = $this->defaultfields;
        if (isset($record['extra'])) {
            $record['context'] = array_merge($record['context'], $record['extra']);
        }
        $contentArray = array_merge(array('channel' => $record['channel'], 'level' => $record['level'], 'message' => $record['message'], 'time' => $record['datetime']->format('U')), $record['context']);
        foreach($contentArray as $key => $context) {
            if (!in_array($key, $this->fields)) {
                unset($contentArray[$key]);
                unset($this->fields[array_search($key, $this->fields)]);
                continue;
            }

            if ($context === null) {
                unset($contentArray[$key]);
                unset($this->fields[array_search($key, $this->fields)]);
            }
        }
        $this->prepareStatement();
        $contentArray = $contentArray + array_combine($this->additionalFields, array_fill(0, count($this->additionalFields), null));
        $this->statement->execute($contentArray);
    }
}
