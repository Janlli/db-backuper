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

    public function index()
    {
        session_start();
        $this->startTime = time();
        $execTime = ini_get('max_execution_time');
        $this->redirTime = $execTime - 2;
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
        for ($i = 0; $i < count($this->tblNames); ++$i) {
            $this->createTable = \DB::select('SHOW CREATE TABLE '.$this->tblNames[$this->tblCount].'');
            $tblRowCount = \DB::table($this->tblNames[$this->tblCount])->count();
            $str = (array) $this->createTable[0];
            if (0 === $this->rcdCount) {
                $this->result .= $str['Create Table'].";\n";
            }
            $allColumns = \DB::getSchemaBuilder()->getColumnListing($this->tblNames[$this->tblCount]);
            if (0 != $tblRowCount) {
                $this->result .= " \n\n\n INSERT INTO ".'`'.$this->tblNames[$this->tblCount].'` '.'(';
                foreach ($allColumns as $column) {
                    $this->result .= '`'.$column.'`, ';
                }
                $this->result = substr_replace($this->result, '', -2);
                $this->result .= ')'.' VALUES ';
                for ($this->rcdCount; $this->rcdCount <= $tblRowCount; ++$this->rcdCount) {
                    $inserts = \DB::table($this->tblNames[$this->tblCount])->skip($this->rcdCount)->limit(1)->get()->toArray();
                    foreach ($inserts as $insert) {
                        $this->result .= "\n ( ";
                        foreach ($insert as $value) {
                            $value = addslashes($value);
                            $this->result .= "'".$value."', ";
                            $this->currentTime = time();
                            if ($this->redirTime < $this->currentTime - $this->startTime) {
                                $this->lockTables(false);
                                $this->redirect();
                            }
                        }
                        $this->result = substr_replace($this->result, '', -2);
                        $this->result .= '),';
                    }
                }
                $this->result = substr_replace($this->result, ';', -1);
            }
            $this->result .= "\n\n\n";
            ++$this->tblCount;
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
    {   if(!file_exists('backups')) {
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
          <title>Loading...</title>
          <meta charset=\"utf-8\">
          <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">
          <meta http-equiv=\"refresh\" content=\"2\">
        </head>
        <body>
        <p>Loading...</p>
        </body>
        </html>";
        exit();
    }
}
