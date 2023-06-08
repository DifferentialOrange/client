<?php

/**
 * This file is part of the tarantool/client package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

require __DIR__.'/../bootstrap.php';

$client = create_client();
ensure_server_version_at_least('2.3.1-68', $client);

$client->execute('DROP TABLE IF EXISTS users');
$client->execute('CREATE TABLE users ("id" INTEGER PRIMARY KEY AUTOINCREMENT, "name" VARCHAR(50))');

$stmt = $client->prepare('INSERT INTO users VALUES(null, ?)');
for ($i = 1; $i <= 100; ++$i) {
    $stmt->execute("name_$i");
    // You can also use executeSelect() and executeUpdate(), e.g.:
    // $lastInsertIds = $stmt->executeUpdate("name_$i")->getAutoincrementIds();
}
$stmt->close();

/**
 * SEQSCAN keyword is explicitly allowing to use seqscan:
 * https://github.com/tarantool/tarantool/commit/77648827326ad268ec0ffbcd620c2371b65ef2b4
 * It was introduced in Tarantool 2.11.0-rc1. If compat.sql_seq_scan_default set to "new"
 * (default value since 3.0), query returns error when trying to scan without keyword.
 */
$seqScan = server_version_at_least('2.11.0-rc1', $client) ? 'SEQSCAN' : '';
$result = $client->executeQuery("SELECT COUNT(\"id\") AS \"cnt\" FROM $seqScan users");

printf("Result: %s\n", json_encode($result[0]));

/* OUTPUT
Result: {"cnt":100}
*/
