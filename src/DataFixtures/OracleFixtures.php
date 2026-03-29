<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\OracleCategory;
use App\Entity\OracleOption;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Seeds the oracle_categories and oracle_options tables with the default values
 * that match the built-in constants in OracleService.
 *
 * Run with: docker compose exec backend-php php bin/console doctrine:fixtures:load
 *
 * Requires: composer require --dev doctrine/doctrine-fixtures-bundle
 */
class OracleFixtures extends Fixture
{
    /**
     * Category seed data: each entry is [name, display_order, options[]].
     * Options are [value, hint].
     *
     * @var array<int, array{name: string, display_order: int, options: array<int, array{value: string, hint: string}>}>
     */
    private const array SEED_DATA = [
        [
            'name'          => 'genre',
            'display_order' => 0,
            'options'       => [
                ['value' => 'Fantasía',         'hint' => ''],
                ['value' => 'XXXPunk',          'hint' => ''],
                ['value' => 'Mitos de Cthulhu', 'hint' => ''],
                ['value' => 'Sobrenatural',     'hint' => ''],
                ['value' => 'Romance',          'hint' => ''],
                ['value' => 'Investigación',    'hint' => ''],
            ],
        ],
        [
            'name'          => 'epoch',
            'display_order' => 1,
            'options'       => [
                ['value' => 'Antigüedad',    'hint' => ''],
                ['value' => 'Medieval',      'hint' => ''],
                ['value' => 'Renacimiento',  'hint' => ''],
                ['value' => 'Victoriana',    'hint' => ''],
                ['value' => 'Contemporanea', 'hint' => ''],
                ['value' => 'Futuro',        'hint' => ''],
            ],
        ],
        [
            'name'          => 'color',
            'display_order' => 2,
            'options'       => [
                ['value' => 'Negro',  'hint' => 'Darkness, night, hidden'],
                ['value' => 'Rojo',   'hint' => 'Passion, romance, warmth'],
                ['value' => 'Morado', 'hint' => 'Mysticism, wisdom'],
                ['value' => 'Verde',  'hint' => 'Nature, hope'],
                ['value' => 'Azul',   'hint' => 'Sky, sea, freedom'],
                ['value' => 'Blanco', 'hint' => 'Purity, light, day'],
            ],
        ],
        [
            'name'          => 'binding',
            'display_order' => 3,
            'options'       => [
                ['value' => 'Piel humana', 'hint' => 'Horror, cruelty, pain, feelings'],
                ['value' => 'Cuero',       'hint' => 'History, religion, countryside, animals'],
                ['value' => 'Hueso',       'hint' => 'Life, death, antiquity, ancestors'],
                ['value' => 'Cartulina',   'hint' => 'Simplicity, utility, practical, cheap'],
                ['value' => 'Madera',      'hint' => 'Rustic, hardness, craftsmanship'],
                ['value' => 'Terciopelo',  'hint' => 'Luxury, tenderness, softness'],
            ],
        ],
        [
            'name'          => 'smell',
            'display_order' => 4,
            'options'       => [
                ['value' => 'Flores silvestres',  'hint' => 'Countryside, nature, life'],
                ['value' => 'Mar',                'hint' => 'Open spaces, water, freedom'],
                ['value' => 'Tierra mojada',      'hint' => 'Rain, harvest, resolution'],
                ['value' => 'Especias de cocina', 'hint' => 'Home, comfort, exoticism'],
                ['value' => 'Fruta podrida',      'hint' => 'Unpleasant, death, deterioration'],
                ['value' => 'Madera quemada',     'hint' => 'Fire, destruction, rubble'],
            ],
        ],
        [
            'name'          => 'interior',
            'display_order' => 5,
            'options'       => [
                ['value' => 'Lomo y cubierta decorados de filigranas doradas y plateadas',        'hint' => ''],
                ['value' => 'Guarniciones de hierro en las esquinas y cerrado con un candado',    'hint' => ''],
                ['value' => 'Título y detalles en relieve en la parte frontal y trasera del libro', 'hint' => ''],
                ['value' => 'Tratado pictórico con dibujos de gran calidad',                      'hint' => ''],
                ['value' => 'Caligrafía exquisita con distintos dibujos',                         'hint' => ''],
                ['value' => 'Escrito en alfabeto desconocido y con muchas ilustraciones extrañas', 'hint' => ''],
            ],
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::SEED_DATA as $categoryData) {
            $category = new OracleCategory();
            $category->setName($categoryData['name']);
            $category->setDisplayOrder($categoryData['display_order']);
            $manager->persist($category);

            foreach ($categoryData['options'] as $i => $optionData) {
                $option = new OracleOption();
                $option->setCategory($category);
                $option->setValue($optionData['value']);
                $option->setHint($optionData['hint'] !== '' ? $optionData['hint'] : null);
                $option->setDisplayOrder($i);
                $option->setIsActive(true);
                $manager->persist($option);
            }
        }

        $manager->flush();
    }
}
