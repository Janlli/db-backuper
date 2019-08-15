<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BackupController extends Controller
{

    private $execTime;
    private $redirTime;
    private $startTime;
    private $currentTime;
    private $lockString = "";
    private $tables = [];
    private $result = "";
    private $alldb = [];
    private $attrTable;
    private $filename;


public function index()
{
  $this->filename = "backup-" . date("Y_m_d_H_i_s") . ".sql";  // имя файла
  $this->startTime = time();
  $execTime = ini_get('max_execution_time');            // максимальное время работы скрипта
  $redirTime = $execTime - 2;
  $tab = \DB::select('SHOW TABLES');                    // получаем имена таблиц
  foreach($tab as $k => $v){
      $v = (array) $v;
      $v = array_values($v);
    array_push($this->alldb, $v[0]);
  }
  $this->lockTables(true);                                // блокируем базу данных
  $this->backup();
  $this->lockTables(false);                                // разблокировка
}

private function backup()
    {
      (isset($_SESSION['redCount'])) ? $redCount = $_SESSION['redCount'] : $redCount = 0;                   // сохроняем состояние в сессию
      (isset($_SESSION['tblCount'])) ? $tblCount = $_SESSION['tblCount'] : $tblCount = 0;

      $this->currentTime = time();        // сохраняем текущее время
        for ($i = 0; $i < count($this->alldb); $i++){
          $this->attrTable = \DB::select("SHOW CREATE TABLE " . $this->alldb[$tblCount] . "");        // показывает запрос создания таблиц
          $tblRowCount = \DB::table($this->alldb[$tblCount])->count();            // общее количество записей в таблице
          $str = (array) $this->attrTable[0];

          if ($redCount === 0){
          $this->result .= $str['Create Table'] . ";\n";
          }

          $allColumns = \DB::getSchemaBuilder()->getColumnListing($this->alldb[$tblCount]);       // получает все коллоны


          if($tblRowCount != 0){
            $this->result .= " \n\n\n INSERT INTO " . "'" . $this->alldb[$tblCount] . "' " . "("; // вставка значений таблицы в файл
          foreach ($allColumns as $column){
            $this->result .= "'" . $column . "', ";
              }
            $this->result .= ")" . " VALUES ";
            for ($redCount; $redCount <= $tblRowCount; $redCount++){
            $inserts = \DB::table($this->alldb[$tblCount])->where("id", "=", $redCount)->get()->toArray();
            foreach ($inserts as $insert){
            $this->result .= "\n ( ";
            foreach ($insert as $value){

              $this->result .= "'" . $value . "', ";
                if ($this->redirTime < $this->currentTime - $this->startTime){          // перенаправлениею
                    $this->redirect();
              }
            }
            $this->result = substr_replace($this->result ,"", -2);
            $this->result .= '),';
          }
        }
          $this->result = substr_replace($this->result ,";", -1);
    }

            $this->result .= "\n\n\n";
            $tblCount++;
            $redCount = 0;
       }
       $this->writeResult();        // сохранение результата


    }

private function lockTables(bool $lock)
    {
      ($lock === true) ? $this->lockString = "LOCK TABLES " : $this->lockString = "UNLOCK TABLES ";

      $showTables = \DB::select('SHOW TABLES');
      foreach ($showTables as $table){
         foreach ($table as $key => $value){
           $this->lockString .= "$value WRITE, ";
           array_push($this->tables, $value);
         }
       };
       $this->lockString = rtrim($this->lockString, ", ");
       \DB::raw($this->lockString);
    }

private function writeResult()
{
    $resultFile = fopen("backups/" . $this->filename, "a");
    fwrite($resultFile, $this->result);
    fclose($resultFile);
}

private function redirect()
{
    $this->writeResult();
    $_SESSION['redCount'] = $redCount;
    $_SESSION['tblCount'] = $tblCount;
    header("Location: " . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit();
}

     };








