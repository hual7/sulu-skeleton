<?php

declare(strict_types=1);

namespace App\Content\Merger;

use App\Entity\ArticleDimensionContent;
use Sulu\Content\Application\ContentMerger\Merger\MergerInterface;

final class ArticleCustomFieldsMerger implements MergerInterface
{
    public function merge(object $targetObject, object $sourceObject): void
    {
        if (!$targetObject instanceof ArticleDimensionContent) {
            return;
        }

        if (!$sourceObject instanceof ArticleDimensionContent) {
            return;
        }

        $targetObject->setAdditionalData(\array_merge(
            $targetObject->getAdditionalData(),
            $sourceObject->getAdditionalData(),
        ));
    }
}
