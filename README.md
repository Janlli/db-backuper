# db-backuper
for simtech

BackupController – контроллер для создания резервной копии базы данных приложений, написанных на Laravel.

Контроллер необходимо скопировать в app/Http/Controllers.

Для создания резервной копии таблицы необходимо создать роутер в файле routes/web.php: Route::get(“index”, “BackupController@index”);

После обращения к контроллеру будет создан файл, содержащий  SQL запросы.

В процессе создания резервной копии, таблицы базы данных блокируются для записи новых данных. 

По окончанию процесса происходит разблокировка. 


Файл доступен в папке: public/backups. 

Если в процессе работы скрипта будет риск превышения времени выполнения, за 2 секунды скрипт прервет выполнение, запишет промежуточный результат в файл и после редиректа продолжит выполнения с той записи на которой остановился.

Промежуточные результаты записываются в сессию:
$_SESSION['rcdCount'] = $rcdCount;
$_SESSION['tblCount'] = $tblCount;
.
После очередного перенаправления данные извлекаются и передаются скрипту. 

