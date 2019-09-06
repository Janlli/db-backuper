<?php

namespace App\Http\Controllers;

class BackupController extends Controller
{
    private $execTime;
    private $redirTime;
    private $startTime;
    private $currentTime;
    private $lockString = '';
    private $tables = [];
    private $result = '';
    private $tblNames = [];
    private $createTable;
    private $rcdCount;
    private $tblCount;
    private $maxMemoryScript;
    private $currentUsedMemory;

    const MEMORY_SPAN = 10 * 1024 * 1024;
    const TIME_SPAN = 4;

    public function index()
    {
        ini_set('max_execution_time', 6);

        session_start();
        $this->startTime = time();
        $execTime = ini_get('max_execution_time');
        $memoryLimit = ini_get('memory_limit');
        $this->maxMemoryScript = $this->getMemoryLimit($memoryLimit);
        $this->redirTime = $execTime - self::TIME_SPAN;
        $tab = \DB::select('SHOW TABLES');
        foreach ($tab as $k => $v) {
            $v = (array) $v;
            $v = array_values($v);
            $this->tblNames[] = $v[0];
        }
        $this->lockTables(true);
        $this->backup();
        $this->lockTables(false);
        unset($_SESSION['fileName']);
        unset($_SESSION['tblCount']);
        unset($_SESSION['rcdCount']);
    }
    
    private function backup()
    {
        if (!isset($_SESSION['fileName'])) {
            $_SESSION['fileName'] = 'backup-'.date('Y-m-d H-i-s').'.sql';
        }
        (isset($_SESSION['rcdCount'])) ? $this->rcdCount = $_SESSION['rcdCount'] : $this->rcdCount = 0;
        (isset($_SESSION['tblCount'])) ? $this->tblCount = $_SESSION['tblCount'] : $this->tblCount = 0;
        for ($this->tblCount; $this->tblCount < count($this->tblNames); $this->tblCount++) {
            $this->createTable = \DB::select('SHOW CREATE TABLE '.$this->tblNames[$this->tblCount].'');
            $tblRowCount = \DB::table($this->tblNames[$this->tblCount])->count();
            $str = (array) $this->createTable[0];
            if ($this->rcdCount === 0) {
                $this->result .= $str['Create Table'].";\n";
            }
            $allColumns = \DB::getSchemaBuilder()->getColumnListing($this->tblNames[$this->tblCount]);
            if ($tblRowCount != 0) {
                $this->result .= " \n\n\n INSERT INTO ".'`'.$this->tblNames[$this->tblCount].'` '.'(';
                
                $quotedColumns = [];
                foreach ($allColumns as $column) {
                    $quotedColumns[] = "`{$column}`";
                }
                $this->result .= implode(', ', $quotedColumns);
                
                $this->result .= ')  VALUES ';
                for ($this->rcdCount; $this->rcdCount <= $tblRowCount; $this->rcdCount++) {
                    $inserts = \DB::table($this->tblNames[$this->tblCount])->skip($this->rcdCount)->limit(1)->get()->toArray();
                    $insert = reset($inserts);
                    
                    $quotedValues = [];
                    foreach ($insert as $value) {
                        $quotedValues[] = \DB::connection()->getPdo()->quote($value);
                    }
                    $this->result .= "\n(" . implode(', ', $quotedValues) . "),";

                    $this->currentTime = time();
                    $this->currentUsedMemory = memory_get_usage(false);
                    if ($this->isTimeLimitExceeded()
                        || $this->isMemoryLimitExceeded()
                    ) {
                        $this->result = rtrim($this->result, ',') . ';';
                        $this->lockTables(false);
                        $this->redirect();
                    }
                }
                $this->result = rtrim($this->result, ',') . ';';
            }
            $this->result .= "\n\n\n";
            $this->rcdCount = 0;
        }
        $this->writeResult();
    }

    private function lockTables(bool $lock)
    {
        (true === $lock) ? $this->lockString = 'LOCK TABLES ' : $this->lockString = 'UNLOCK TABLES ';
        $showTables = \DB::select('SHOW TABLES');
        foreach ($showTables as $table) {
            foreach ($table as $key => $value) {
                $this->lockString .= "$value WRITE, ";
                $this->tables[] = $value;
            }
        }
        $this->lockString = rtrim($this->lockString, ', ');
        \DB::raw($this->lockString);
    }

    private function writeResult()
    {   if (!file_exists('backups')) {
            mkdir('backups');
        }
        $resultFile = fopen('backups/'.$_SESSION['fileName'], 'a');
        fwrite($resultFile, $this->result);
        fclose($resultFile);
    }

    private function redirect()
    {
        $this->writeResult();
        $_SESSION['rcdCount'] = $this->rcdCount;
        $_SESSION['tblCount'] = $this->tblCount;
        echo
        "<!DOCTYPE html>
        <html lang=\"en\">
        <head>
          <title>TEST</title>
          <meta charset=\"utf-8\">
          <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">
          <meta http-equiv=\"refresh\" content=\"0\">
        </head>
        <body>
          <p>Loading...</p>
        </body>
        </html>";
        exit();
    }

    /**
     * @return int
     */
    protected function getMemoryLimit(string $memoryLimit)
    {
        
        $suffix = strtolower(substr($memoryLimit, -1));
        
        if (is_numeric($memoryLimit)) {
            return (int) $memoryLimit;
        } elseif ($suffix === 'm') {
            return (int) $memoryLimit * 1024 * 1024;
        } elseif ($suffix === 'g') {
            return (int) $memoryLimit * 1024 * 1024 * 1024;
        } elseif ($suffix === 'k') {
            return (int) $memoryLimit * 1024;
        }
    }

    protected function isTimeLimitExceeded()
    {
        if ($this->redirTime < 0) {
            return false;
        }
        
        return $this->redirTime < $this->currentTime - $this->startTime;
    }

    protected function isMemoryLimitExceeded() 
    {
        if ($this->maxMemoryScript == 0) {
            return false;
        }

        return ($this->currentUsedMemory > $this->maxMemoryScript - self::MEMORY_SPAN);
    }

    

}
