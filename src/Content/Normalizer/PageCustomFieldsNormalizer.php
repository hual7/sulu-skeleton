<?php

declare(strict_types=1);

namespace App\Content\Normalizer;

use App\Entity\PageDimensionContent;
use Sulu\Content\Application\ContentNormalizer\Normalizer\NormalizerInterface;

final class PageCustomFieldsNormalizer implements NormalizerInterface
{
    public function enhance(object $object, array $normalizedData): array
    {
        if (!$object instanceof PageDimensionContent) {
            return $normalizedData;
        }

        $additionalData = $object->getAdditionalData();
        $normalizedData['notes'] = $additionalData['notes'] ?? null;
        $normalizedData['information'] = $additionalData['information'] ?? null;

        return $normalizedData;
    }

    public function getIgnoredAttributes(object $object): array
    {
        if (!$object instanceof PageDimensionContent) {
            return [];
        }

        return ['additionalData'];
    }
}
