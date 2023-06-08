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

namespace Tarantool\Client\Tests\Integration\Requests;

use Tarantool\Client\Exception\RequestFailed;
use Tarantool\Client\PreparedStatement;
use Tarantool\Client\Tests\Integration\TestCase;

/**
 * @requires Tarantool >=2.3.2
 */
final class PrepareTest extends TestCase
{
    private function provideSeqScan() : string
    {
        /**
         * SEQSCAN keyword is explicitly allowing to use seqscan:
         * https://github.com/tarantool/tarantool/commit/77648827326ad268ec0ffbcd620c2371b65ef2b4
         * It was introduced in Tarantool 2.11.0-rc1. If compat.sql_seq_scan_default set to "new"
         * (default value since 3.0), query returns error when trying to scan without keyword.
         */
        return $this->tarantoolVersionSatisfies('>=2.11.0-rc1') ? 'SEQSCAN' : '';
    }

    public function testPreparePreparesSqlStatement() : void
    {
        [$preparedCountBefore] = $this->client->evaluate('return box.info.sql().cache.stmt_count');
        $stmt = $this->client->prepare('SELECT ?');
        [$preparedCountAfter] = $this->client->evaluate('return box.info.sql().cache.stmt_count');

        try {
            self::assertSame($preparedCountBefore + 1, $preparedCountAfter);
            self::assertIsInt($stmt->getId());
            self::assertSame(1, $stmt->getBindCount());
            self::assertSame([['?', 'ANY']], $stmt->getBindMetadata());
            // If the data type of NULL cannot be determined from context, it is BOOLEAN.
            // @see https://www.tarantool.io/en/doc/2.2/reference/reference_sql/sql/#column-definition-data-type
            $metaColumnName = $this->tarantoolVersionSatisfies('<2.6.0') ? '?' : 'COLUMN_1';
            self::assertSame([[$metaColumnName, 'boolean']], $stmt->getMetadata());
        } finally {
            $stmt->close();
        }
    }

    public function testExecuteQueryReturnsResult() : void
    {
        $stmt = $this->client->prepare('SELECT :v1, :v2');

        $selectResult1 = $stmt->executeQuery([':v1' => 1], [':v2' => 2]);
        $selectResult2 = $stmt->executeQuery([':v1' => 3], [':v2' => 4]);

        try {
            if ($this->tarantoolVersionSatisfies('<2.6.0')) {
                self::assertSame([':v1' => 1, ':v2' => 2], $selectResult1[0]);
                self::assertSame([':v1' => 3, ':v2' => 4], $selectResult2[0]);
            } else {
                self::assertSame(['COLUMN_1' => 1, 'COLUMN_2' => 2], $selectResult1[0]);
                self::assertSame(['COLUMN_1' => 3, 'COLUMN_2' => 4], $selectResult2[0]);
            }
        } finally {
            $stmt->close();
        }
    }

    /**
     * @sql DROP TABLE IF EXISTS prepare_execute
     * @sql CREATE TABLE prepare_execute (id INTEGER PRIMARY KEY, name VARCHAR(50))
     */
    public function testExecuteUpdateUpdatesRows() : void
    {
        $stmt = $this->client->prepare('INSERT INTO prepare_execute VALUES(:id, :name)');

        $insertResult1 = $stmt->executeUpdate(1, 'foo');
        $insertResult2 = $stmt->executeUpdate([':name' => 'bar'], [':id' => 2]);

        $seqScan = self::provideSeqScan();
        $selectResult = $this->client->executeQuery("SELECT * FROM $seqScan prepare_execute ORDER BY id");

        try {
            self::assertSame(1, $insertResult1->count());
            self::assertSame(1, $insertResult2->count());
            self::assertSame([[1, 'foo'], [2, 'bar']], $selectResult->getData());
        } finally {
            $stmt->close();
        }
    }

    public function testCloseDeallocatesPreparedStatement() : void
    {
        $stmt = $this->client->prepare('SELECT ?');

        $this->expectPreparedStatementToBeDeallocatedOnce();
        $stmt->close();
    }

    public function testCloseDeallocatesPreparedInLuaSqlStatement() : void
    {
        [$data] = $this->client->evaluate("s = box.prepare('SELECT ?') return {
            id=s.stmt_id,
            bind_metadata=s.params,
            metadata=s.metadata,
            bind_count=s.param_count
        }");

        $stmt = new PreparedStatement(
            $this->client->getHandler(),
            $data['id'],
            $data['bind_count'],
            $data['bind_metadata'],
            $data['metadata']
        );

        $this->expectPreparedStatementToBeDeallocatedOnce();
        $stmt->close();
    }

    public function testCloseFailsOnNonexistentPreparedStatement() : void
    {
        $stmt = new PreparedStatement($this->client->getHandler(), 42, 0, [], []);

        $this->expectException(RequestFailed::class);
        $this->expectExceptionMessage('Prepared statement with id 42 does not exist');
        $stmt->close();
    }

    /**
     * @see https://github.com/tarantool/tarantool/issues/4825
     */
    public function testPrepareResetsPreviouslyBoundParameters() : void
    {
        $stmt = $this->client->prepare('SELECT :a, :b');

        // Bind parameters to the current statement
        $stmt->execute([':a' => 1], [':b' => 2]);

        $result = $stmt->executeQuery([':a' => 1]);
        self::assertSame([1, null], $result->getData()[0]);

        $result = $stmt->executeQuery();
        self::assertSame([null, null], $result->getData()[0]);
    }
}
