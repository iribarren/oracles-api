<?php

declare(strict_types=1);

namespace App\ApiDoc;

use Nelmio\ApiDocBundle\Describer\DescriberInterface;
use Nelmio\ApiDocBundle\OpenApiPhp\Util;
use OpenApi\Annotations\OpenApi;
use OpenApi\Generator;

/**
 * Scans the ApiDoc directory to register OpenAPI schemas and global info
 * that are not attached to any controller/route.
 */
class SchemaDescriber implements DescriberInterface
{
    public function describe(OpenApi $api): void
    {
        $result = (new Generator())->generate([__DIR__], validate: false);

        if ($result !== null) {
            Util::merge($api, $result, false);
        }
    }
}
