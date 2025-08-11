Anwendungsbeispiel
So würde ein Entwickler die neuen Hooks verwenden:

Eine invokable-Klasse erstellen:

PHP

// app/Actions/LogBackupStart.php
namespace App\Actions;

use Illuminate\Support\Facades\Log;

class LogBackupStart
{
public function __invoke()
{
Log::info('Ein spezifischer Backup-Job wird jetzt gestartet.');
}
}
Die Klasse im Backup-Prozess verwenden:

PHP

use App\Actions\LogBackupStart;

Backup::create()
->includeDatabases(['mysql'])
->before(LogBackupStart::class)
->run();
