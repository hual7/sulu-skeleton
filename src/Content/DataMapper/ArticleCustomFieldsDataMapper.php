<?php

declare(strict_types=1);

namespace App\Content\DataMapper;

use App\Entity\ArticleDimensionContent;
use Sulu\Content\Application\ContentDataMapper\DataMapper\DataMapperInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;

final class ArticleCustomFieldsDataMapper implements DataMapperInterface
{
    public function map(
        DimensionContentInterface $unlocalizedDimensionContent,
        DimensionContentInterface $localizedDimensionContent,
        array $data,
    ): void {
        if (!$localizedDimensionContent instanceof ArticleDimensionContent) {
            return;
        }

        $additionalData = $localizedDimensionContent->getAdditionalData();

        if (\array_key_exists('notes', $data)) {
            $additionalData['notes'] = $data['notes'];
        }

        if (\array_key_exists('information', $data)) {
            $additionalData['information'] = $data['information'];
        }

        $localizedDimensionContent->setAdditionalData($additionalData);
    }
}
