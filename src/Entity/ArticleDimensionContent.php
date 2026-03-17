<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sulu\Article\Domain\Model\ArticleDimensionContent as SuluArticleDimensionContent;
use Sulu\Article\Domain\Model\ArticleInterface;

#[ORM\Entity]
#[ORM\Table(name: 'ar_article_dimension_contents')]
class ArticleDimensionContent extends SuluArticleDimensionContent
{
    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(name: 'additionalData', type: Types::JSON, options: ['default' => '{}'])]
    private array $additionalData = [];

    /**
     * @return array<string, mixed>
     */
    public function getAdditionalData(): array
    {
        return $this->additionalData ?? [];
    }

    /**
     * @param array<string, mixed> $additionalData
     */
    public function setAdditionalData(array $additionalData): static
    {
        $this->additionalData = $additionalData;

        return $this;
    }
}
