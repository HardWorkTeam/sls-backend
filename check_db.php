<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

// Change host to direct (non-pooled) and nullify URL
$directHost = 'ep-twilight-breeze-azd60q4a.c-3.ap-southeast-1.aws.neon.tech';
echo "Setting DB host to: $directHost and disabling URL...\n";
config([
    'database.connections.pgsql.host' => $directHost,
    'database.connections.pgsql.url' => null,
]);
DB::purge('pgsql');

try {
    DB::transaction(function() {
        echo "Starting transaction on direct host...\n";
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
        echo "Users table created in transaction on direct host!\n";
    });
} catch (\Exception $e) {
    echo "Exception Class: " . get_class($e) . "\n";
    echo "Message: " . $e->getMessage() . "\n";
    
    $prev = $e->getPrevious();
    while ($prev) {
        echo "--- Previous Exception ---\n";
        echo "Exception Class: " . get_class($prev) . "\n";
        echo "Message: " . $prev->getMessage() . "\n";
        $prev = $prev->getPrevious();
    }
} finally {
    try {
        Schema::dropIfExists('users');
    } catch (\Exception $ex) {}
}
