<?php

declare(strict_types=1);

namespace App\Oracle;

use App\Repository\OracleCategoryRepository;

/**
 * Provides oracle tables and random selection for La Biblioteca.
 *
 * Each getXxxTable() method tries to load active options from the database first,
 * and falls back to the built-in constants if the database returns an empty set.
 *
 * All tables are normalized to an array of ['value' => string, 'hint' => string].
 * Values are kept in Spanish (game content); hints are in English.
 */
class OracleService
{
    /** @var array<int, array{value: string, hint: string}> */
    private const GENRE_TABLE = [
        ['value' => 'Fantasía',          'hint' => ''],
        ['value' => 'XXXPunk',           'hint' => ''],
        ['value' => 'Mitos de Cthulhu',  'hint' => ''],
        ['value' => 'Sobrenatural',      'hint' => ''],
        ['value' => 'Romance',           'hint' => ''],
        ['value' => 'Investigación',     'hint' => ''],
    ];

    /** @var array<int, array{value: string, hint: string}> */
    private const EPOCH_TABLE = [
        ['value' => 'Antigüedad',    'hint' => ''],
        ['value' => 'Medieval',      'hint' => ''],
        ['value' => 'Renacimiento',  'hint' => ''],
        ['value' => 'Victoriana',    'hint' => ''],
        ['value' => 'Contemporanea', 'hint' => ''],
        ['value' => 'Futuro',        'hint' => ''],
    ];

    /** @var array<int, array{value: string, hint: string}> */
    private const BINDING_TABLE = [
        ['value' => 'Piel humana', 'hint' => 'Horror, cruelty, pain, feelings'],
        ['value' => 'Cuero',       'hint' => 'History, religion, countryside, animals'],
        ['value' => 'Hueso',       'hint' => 'Life, death, antiquity, ancestors'],
        ['value' => 'Cartulina',   'hint' => 'Simplicity, utility, practical, cheap'],
        ['value' => 'Madera',      'hint' => 'Rustic, hardness, craftsmanship'],
        ['value' => 'Terciopelo',  'hint' => 'Luxury, tenderness, softness'],
    ];

    /** @var array<int, array{value: string, hint: string}> */
    private const COLOR_TABLE = [
        ['value' => 'Negro',  'hint' => 'Darkness, night, hidden'],
        ['value' => 'Rojo',   'hint' => 'Passion, romance, warmth'],
        ['value' => 'Morado', 'hint' => 'Mysticism, wisdom'],
        ['value' => 'Verde',  'hint' => 'Nature, hope'],
        ['value' => 'Azul',   'hint' => 'Sky, sea, freedom'],
        ['value' => 'Blanco', 'hint' => 'Purity, light, day'],
    ];

    /** @var array<int, array{value: string, hint: string}> */
    private const SMELL_TABLE = [
        ['value' => 'Flores silvestres',   'hint' => 'Countryside, nature, life'],
        ['value' => 'Mar',                 'hint' => 'Open spaces, water, freedom'],
        ['value' => 'Tierra mojada',       'hint' => 'Rain, harvest, resolution'],
        ['value' => 'Especias de cocina',  'hint' => 'Home, comfort, exoticism'],
        ['value' => 'Fruta podrida',       'hint' => 'Unpleasant, death, deterioration'],
        ['value' => 'Madera quemada',      'hint' => 'Fire, destruction, rubble'],
    ];

    /** @var array<int, array{value: string, hint: string}> */
    private const INTERIOR_TABLE = [
        ['value' => 'Lomo y cubierta decorados de filigranas doradas y plateadas', 'hint' => ''],
        ['value' => 'Guarniciones de hierro en las esquinas y cerrado con un candado', 'hint' => ''],
        ['value' => 'Título y detalles en relieve en la parte frontal y trasera del libro', 'hint' => ''],
        ['value' => 'Tratado pictórico con dibujos de gran calidad', 'hint' => ''],
        ['value' => 'Caligrafía exquisita con distintos dibujos', 'hint' => ''],
        ['value' => 'Escrito en alfabeto desconocido y con muchas ilustraciones extrañas', 'hint' => ''],
    ];

    /** Maps each category name to its constant fallback table. */
    private const array FALLBACK_TABLES = [
        'genre'    => self::GENRE_TABLE,
        'epoch'    => self::EPOCH_TABLE,
        'binding'  => self::BINDING_TABLE,
        'color'    => self::COLOR_TABLE,
        'smell'    => self::SMELL_TABLE,
        'interior' => self::INTERIOR_TABLE,
    ];

    public function __construct(
        private readonly OracleCategoryRepository $categoryRepository,
    ) {}

    /**
     * Returns a random entry from the given table.
     *
     * @param array<int, array{value: string, hint: string}> $table
     * @return array{value: string, hint: string}
     */
    public function getRandomFromTable(array $table): array
    {
        $index = random_int(0, count($table) - 1);
        return $table[$index];
    }

    /** @return array<int, array{value: string, hint: string}> */
    public function getGenreTable(): array
    {
        return $this->loadFromDb('genre') ?? self::GENRE_TABLE;
    }

    /** @return array<int, array{value: string, hint: string}> */
    public function getEpochTable(): array
    {
        return $this->loadFromDb('epoch') ?? self::EPOCH_TABLE;
    }

    /** @return array<int, array{value: string, hint: string}> */
    public function getBindingTable(): array
    {
        return $this->loadFromDb('binding') ?? self::BINDING_TABLE;
    }

    /** @return array<int, array{value: string, hint: string}> */
    public function getColorTable(): array
    {
        return $this->loadFromDb('color') ?? self::COLOR_TABLE;
    }

    /** @return array<int, array{value: string, hint: string}> */
    public function getSmellTable(): array
    {
        return $this->loadFromDb('smell') ?? self::SMELL_TABLE;
    }

    /** @return array<int, array{value: string, hint: string}> */
    public function getInteriorTable(): array
    {
        return $this->loadFromDb('interior') ?? self::INTERIOR_TABLE;
    }

    /**
     * Returns all categories with their active options (for admin use).
     * Falls back to constants for any category not found in the database.
     *
     * @return array<string, array{id: int|null, options: array<int, array{id: int|null, value: string, hint: string, is_active: bool, display_order: int}>}>
     */
    public function getAllTables(): array
    {
        $categories = $this->categoryRepository->findAllWithAllOptions();

        $result = [];

        foreach ($categories as $category) {
            $options = [];
            foreach ($category->getOptions() as $option) {
                $options[] = [
                    'id'            => $option->getId(),
                    'value'         => $option->getValue(),
                    'hint'          => $option->getHint() ?? '',
                    'is_active'     => $option->isActive(),
                    'display_order' => $option->getDisplayOrder(),
                ];
            }
            $result[$category->getName()] = [
                'id'      => $category->getId(),
                'options' => $options,
            ];
        }

        // Fill in any category not yet seeded in the DB with constant fallbacks
        foreach (self::FALLBACK_TABLES as $name => $rows) {
            if (!\array_key_exists($name, $result)) {
                $fallbackOptions = [];
                foreach ($rows as $i => $row) {
                    $fallbackOptions[] = [
                        'id'            => null,
                        'value'         => $row['value'],
                        'hint'          => $row['hint'],
                        'is_active'     => true,
                        'display_order' => $i,
                    ];
                }
                $result[$name] = [
                    'id'      => null,
                    'options' => $fallbackOptions,
                ];
            }
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Loads active options for a category from the database.
     * Returns null if the category does not exist or has no active options,
     * so the caller can fall back to the built-in constant.
     *
     * @return array<int, array{value: string, hint: string}>|null
     */
    private function loadFromDb(string $categoryName): ?array
    {
        $category = $this->categoryRepository->findOneBy(['name' => $categoryName]);

        if ($category === null) {
            return null;
        }

        $rows = [];
        foreach ($category->getOptions() as $option) {
            if ($option->isActive()) {
                $rows[] = [
                    'value' => $option->getValue(),
                    'hint'  => $option->getHint() ?? '',
                ];
            }
        }

        return $rows !== [] ? $rows : null;
    }
}
