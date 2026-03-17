<?php

declare(strict_types=1);

namespace App\Content\Merger;

use App\Entity\PageDimensionContent;
use Sulu\Content\Application\ContentMerger\Merger\MergerInterface;

final class PageCustomFieldsMerger implements MergerInterface
{
    public function merge(object $targetObject, object $sourceObject): void
    {
        if (!$targetObject instanceof PageDimensionContent) {
            return;
        }

        if (!$sourceObject instanceof PageDimensionContent) {
            return;
        }

        $targetObject->setAdditionalData(\array_merge(
            $targetObject->getAdditionalData(),
            $sourceObject->getAdditionalData(),
        ));
    }
}
