<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sulu\Page\Domain\Model\PageDimensionContent as SuluPageDimensionContent;
use Sulu\Page\Domain\Model\PageInterface;

#[ORM\Entity]
#[ORM\Table(name: 'pa_page_dimension_contents')]
class PageDimensionContent extends SuluPageDimensionContent
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
