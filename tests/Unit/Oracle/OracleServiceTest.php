<?php

declare(strict_types=1);

namespace App\Tests\Unit\Oracle;

use App\Oracle\OracleService;
use PHPUnit\Framework\TestCase;

class OracleServiceTest extends TestCase
{
    private OracleService $oracleService;

    /** @var list<string> */
    private array $tableNames = ['genre', 'epoch', 'binding', 'color', 'smell', 'interior'];

    protected function setUp(): void
    {
        $this->oracleService = new OracleService();
    }

    /** @return array<string, array{array<int, array{value: string, hint: string}>}> */
    private function getAllTables(): array
    {
        return [
            'genre'    => [$this->oracleService->getGenreTable()],
            'epoch'    => [$this->oracleService->getEpochTable()],
            'binding'  => [$this->oracleService->getBindingTable()],
            'color'    => [$this->oracleService->getColorTable()],
            'smell'    => [$this->oracleService->getSmellTable()],
            'interior' => [$this->oracleService->getInteriorTable()],
        ];
    }

    public function testGetGenreTableReturnsSixEntries(): void
    {
        $this->assertCount(6, $this->oracleService->getGenreTable());
    }

    public function testGetEpochTableReturnsSixEntries(): void
    {
        $this->assertCount(6, $this->oracleService->getEpochTable());
    }

    public function testGetBindingTableReturnsSixEntries(): void
    {
        $this->assertCount(6, $this->oracleService->getBindingTable());
    }

    public function testGetColorTableReturnsSixEntries(): void
    {
        $this->assertCount(6, $this->oracleService->getColorTable());
    }

    public function testGetSmellTableReturnsSixEntries(): void
    {
        $this->assertCount(6, $this->oracleService->getSmellTable());
    }

    public function testGetInteriorTableReturnsSixEntries(): void
    {
        $this->assertCount(6, $this->oracleService->getInteriorTable());
    }

    /**
     * @param array<int, array{value: string, hint: string}> $table
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('tableProvider')]
    public function testTableEntriesHaveValueKey(string $tableName, array $table): void
    {
        foreach ($table as $index => $entry) {
            $this->assertArrayHasKey(
                'value',
                $entry,
                "Entry {$index} in table '{$tableName}' must have a 'value' key"
            );
        }
    }

    /**
     * @param array<int, array{value: string, hint: string}> $table
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('tableProvider')]
    public function testTableEntriesHaveHintKey(string $tableName, array $table): void
    {
        foreach ($table as $index => $entry) {
            $this->assertArrayHasKey(
                'hint',
                $entry,
                "Entry {$index} in table '{$tableName}' must have a 'hint' key"
            );
        }
    }

    /**
     * @param array<int, array{value: string, hint: string}> $table
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('tableProvider')]
    public function testTableEntryValuesAreNonEmptyStrings(string $tableName, array $table): void
    {
        foreach ($table as $index => $entry) {
            $this->assertIsString($entry['value'], "value in '{$tableName}' entry {$index} must be a string");
            $this->assertNotEmpty($entry['value'], "value in '{$tableName}' entry {$index} must not be empty");
        }
    }

    /**
     * @param array<int, array{value: string, hint: string}> $table
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('tableProvider')]
    public function testTableEntryHintsAreStrings(string $tableName, array $table): void
    {
        foreach ($table as $index => $entry) {
            $this->assertIsString($entry['hint'], "hint in '{$tableName}' entry {$index} must be a string");
        }
    }

    /**
     * @return array<string, array{string, array<int, array{value: string, hint: string}>}>
     */
    public static function tableProvider(): array
    {
        $service = new OracleService();

        return [
            'genre'    => ['genre',    $service->getGenreTable()],
            'epoch'    => ['epoch',    $service->getEpochTable()],
            'binding'  => ['binding',  $service->getBindingTable()],
            'color'    => ['color',    $service->getColorTable()],
            'smell'    => ['smell',    $service->getSmellTable()],
            'interior' => ['interior', $service->getInteriorTable()],
        ];
    }

    public function testGetRandomFromTableReturnsEntryWithValueKey(): void
    {
        $table  = $this->oracleService->getColorTable();
        $result = $this->oracleService->getRandomFromTable($table);

        $this->assertArrayHasKey('value', $result);
    }

    public function testGetRandomFromTableReturnsEntryWithHintKey(): void
    {
        $table  = $this->oracleService->getColorTable();
        $result = $this->oracleService->getRandomFromTable($table);

        $this->assertArrayHasKey('hint', $result);
    }

    public function testGetRandomFromTableReturnedValueExistsInTable(): void
    {
        $table  = $this->oracleService->getSmellTable();
        $result = $this->oracleService->getRandomFromTable($table);

        $values = array_column($table, 'value');
        $this->assertContains(
            $result['value'],
            $values,
            'Returned value must be one of the table entries'
        );
    }

    public function testGetRandomFromTableCanReturnDifferentEntries(): void
    {
        // With 6 entries and enough rolls, we should see more than 1 distinct value
        $table      = $this->oracleService->getGenreTable();
        $seenValues = [];

        for ($i = 0; $i < 300; $i++) {
            $result       = $this->oracleService->getRandomFromTable($table);
            $seenValues[] = $result['value'];
        }

        $this->assertGreaterThan(
            1,
            count(array_unique($seenValues)),
            'getRandomFromTable should return different entries across many calls'
        );
    }

    public function testGetRandomFromTableEventuallyCoversAllEntries(): void
    {
        // With 6 entries, 500 draws should almost certainly cover all entries
        $table      = $this->oracleService->getBindingTable();
        $allValues  = array_column($table, 'value');
        $seenValues = [];

        for ($i = 0; $i < 500; $i++) {
            $seenValues[] = $this->oracleService->getRandomFromTable($table)['value'];
        }

        foreach ($allValues as $expectedValue) {
            $this->assertContains(
                $expectedValue,
                $seenValues,
                "Value '{$expectedValue}' was never returned in 500 draws"
            );
        }
    }

    public function testGetRandomFromSingleEntryTableAlwaysReturnsThatEntry(): void
    {
        $singleEntryTable = [['value' => 'OnlyOne', 'hint' => 'test hint']];
        $result           = $this->oracleService->getRandomFromTable($singleEntryTable);

        $this->assertSame('OnlyOne', $result['value']);
        $this->assertSame('test hint', $result['hint']);
    }
}
