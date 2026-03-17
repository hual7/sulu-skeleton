<?php

declare(strict_types=1);

namespace App\Content\Normalizer;

use App\Entity\ArticleDimensionContent;
use Sulu\Content\Application\ContentNormalizer\Normalizer\NormalizerInterface;

final class ArticleCustomFieldsNormalizer implements NormalizerInterface
{
    public function enhance(object $object, array $normalizedData): array
    {
        if (!$object instanceof ArticleDimensionContent) {
            return $normalizedData;
        }

        $additionalData = $object->getAdditionalData();
        $normalizedData['notes'] = $additionalData['notes'] ?? null;
        $normalizedData['information'] = $additionalData['information'] ?? null;

        return $normalizedData;
    }

    public function getIgnoredAttributes(object $object): array
    {
        if (!$object instanceof ArticleDimensionContent) {
            return [];
        }

        return ['additionalData'];
    }
}
