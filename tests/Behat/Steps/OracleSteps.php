<?php

declare(strict_types=1);

namespace App\Tests\Behat\Steps;

use App\Entity\OracleCategory;
use App\Entity\OracleOption;

/**
 * Oracle steps: the public oracle tables and random-setting endpoints.
 *
 * In the test database the oracle tables are empty, so the API serves the
 * hardcoded fallback constants. A scenario can seed a category to prove the
 * DB-first path (the seeded value replaces the fallback for that category).
 */
trait OracleSteps
{
    /**
     * Seeds one oracle category with a single active, recognisable option so the
     * DB-first read can be distinguished from the hardcoded fallback constants.
     *
     * @Given the oracle category :name is seeded with value :value
     */
    public function theOracleCategoryIsSeededWithValue(string $name, string $value): void
    {
        $category = new OracleCategory();
        $category->setName($name);
        $category->setDisplayOrder(0);

        $option = new OracleOption();
        $option->setCategory($category);
        $option->setValue($value);
        $option->setHint('seeded');
        $option->setDisplayOrder(0);
        $option->setIsActive(true);
        $category->addOption($option);

        $this->entityManager->persist($category);
        $this->entityManager->persist($option);
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    /**
     * @When I request the oracle tables
     */
    public function iRequestTheOracleTables(): void
    {
        $this->actAs(null);
        $this->sendRequest('GET', '/api/oracle/tables', null);
    }

    /**
     * @When I request a random setting
     */
    public function iRequestARandomSetting(): void
    {
        $this->actAs(null);
        $this->sendRequest('GET', '/api/oracle/random-setting', null);
    }

    /**
     * @Then the oracle table :name has :count entries
     */
    public function theOracleTableHasEntries(string $name, int $count): void
    {
        $table = $this->getDecodedResponse()[$name] ?? null;
        if (!\is_array($table)) {
            throw new \RuntimeException(\sprintf('Oracle table "%s" missing from the response.', $name));
        }
        if (\count($table) !== $count) {
            throw new \RuntimeException(\sprintf('Expected %d entries in table "%s" but got %d.', $count, $name, \count($table)));
        }
    }

    /**
     * @Then the oracle table :name contains :value
     */
    public function theOracleTableContains(string $name, string $value): void
    {
        $table  = $this->getDecodedResponse()[$name] ?? [];
        $values = \array_column(\is_array($table) ? $table : [], 'value');
        if (!\in_array($value, $values, true)) {
            throw new \RuntimeException(\sprintf(
                'Expected oracle table "%s" to contain "%s". Found: %s',
                $name,
                $value,
                \implode(', ', $values),
            ));
        }
    }

    /**
     * @Then the random setting includes a genre and an epoch
     */
    public function theRandomSettingIncludesGenreAndEpoch(): void
    {
        $data = $this->getDecodedResponse();
        foreach (['genre', 'epoch'] as $key) {
            if (empty($data[$key]['value'])) {
                throw new \RuntimeException(\sprintf('Random setting missing a non-empty "%s.value".', $key));
            }
        }
    }
}
