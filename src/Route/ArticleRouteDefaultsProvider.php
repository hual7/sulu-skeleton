<?php

declare(strict_types=1);

namespace App\Route;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\Expr\Join;
use Sulu\Article\Domain\Model\ArticleDimensionContentInterface;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\CacheLifetimeMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TemplateMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TypedFormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderRegistry;
use Sulu\Bundle\HttpCacheBundle\CacheLifetime\CacheLifetimeRequestStore;
use Sulu\Bundle\HttpCacheBundle\CacheLifetime\CacheLifetimeResolverInterface;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Domain\Exception\ContentNotFoundException;
use Sulu\Content\Domain\Model\ContentRichEntityInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Domain\Model\TemplateInterface;
use Sulu\Route\Application\Routing\Matcher\RouteDefaultsProviderInterface;
use Sulu\Route\Domain\Model\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Workaround for Sulu bug: ContentRouteDefaultsProvider hardcodes Sulu\Article\Domain\Model\Article
 * (a MappedSuperclass) instead of using the configured sulu.model.article.class parameter.
 * Querying a MappedSuperclass directly in Doctrine DQL is invalid and causes:
 * "Class Sulu\Article\Domain\Model\Article has no association named dimensionContents"
 *
 * @see \Sulu\Content\Infrastructure\Sulu\Route\ContentRouteDefaultsProvider
 * @see \Sulu\Article\Infrastructure\Sulu\Route\ArticleRouteDefaultsProvider
 */
final class ArticleRouteDefaultsProvider implements RouteDefaultsProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ContentAggregatorInterface $contentAggregator,
        private readonly MetadataProviderRegistry $metadataProviderRegistry,
        private readonly CacheLifetimeResolverInterface $cacheLifetimeResolver,
        private readonly WebspaceManagerInterface $webspaceManager,
        private readonly string $environment,
        private readonly string $articleEntityClass,
    ) {
    }

    public function getDefaults(Route $route): array
    {
        $id = $route->getResourceId();
        $locale = $route->getLocale();

        $dimensionContent = $this->loadEntity($id, $locale);

        if (null === $dimensionContent) {
            throw new NotFoundHttpException(\sprintf('No content found for id "%s" and locale "%s"', $id, $locale));
        }

        $contentLocale = $dimensionContent->getLocale();
        if (!$contentLocale) {
            throw new NotFoundHttpException(\sprintf('No content found for id "%s" and locale "%s"', $id, $locale));
        }

        if (!$dimensionContent instanceof TemplateInterface) {
            throw new \RuntimeException(\sprintf('Expected "%s" but got "%s".', TemplateInterface::class, $dimensionContent::class));
        }

        $templateKey = $dimensionContent->getTemplateKey();
        if (!$templateKey) {
            throw new NotFoundHttpException(\sprintf('No template found for id "%s" and locale "%s"', $id, $locale));
        }

        $templateMetadata = $this->resolveTemplateMetadata($dimensionContent::getTemplateType(), $templateKey, $contentLocale);

        $defaults = [
            'object' => $dimensionContent,
            'view' => $templateMetadata->getView(),
            '_controller' => $templateMetadata->getController(),
        ];

        $cacheLifetime = $this->getCacheLifetime($templateMetadata);
        if ($cacheLifetime) {
            $defaults[CacheLifetimeRequestStore::ATTRIBUTE_KEY] = $cacheLifetime;
        }

        if ($dimensionContent instanceof ArticleDimensionContentInterface) {
            $seoData = $this->getSeoData($dimensionContent, $route);
            if ($seoData) {
                $defaults['_seo'] = $seoData;
            }
        }

        return $defaults;
    }

    private function loadEntity(string $id, string $locale): ?DimensionContentInterface
    {
        try {
            $queryBuilder = $this->entityManager->createQueryBuilder()
                ->select('entity')
                ->from($this->articleEntityClass, 'entity')
                ->leftJoin(
                    'entity.dimensionContents',
                    'dimensionContent',
                    Join::WITH,
                    'dimensionContent.stage = :stage AND dimensionContent.version = :version AND (dimensionContent.locale IS NULL OR dimensionContent.locale = :locale)'
                )
                ->addSelect('dimensionContent')
                ->where('entity = :id')
                ->setParameter('id', $id)
                ->setParameter('locale', $locale)
                ->setParameter('stage', DimensionContentInterface::STAGE_LIVE)
                ->setParameter('version', DimensionContentInterface::CURRENT_VERSION);

            $queryBuilder
                ->leftJoin('dimensionContent.excerptTags', 'excerptTag')
                ->addSelect('excerptTag')
                ->leftJoin('dimensionContent.excerptCategories', 'excerptCategory')
                ->addSelect('excerptCategory')
                ->leftJoin('excerptCategory.translations', 'excerptCategoryTranslation')
                ->addSelect('excerptCategoryTranslation');

            /** @var ContentRichEntityInterface $contentRichEntity */
            $contentRichEntity = $queryBuilder->getQuery()->getSingleResult();
        } catch (NoResultException) {
            return null;
        }

        try {
            $resolvedDimensionContent = $this->contentAggregator->aggregate(
                $contentRichEntity,
                [
                    'locale' => $locale,
                    'stage' => DimensionContentInterface::STAGE_LIVE,
                    'version' => DimensionContentInterface::CURRENT_VERSION,
                ]
            );

            if (!$resolvedDimensionContent instanceof TemplateInterface) {
                throw new \RuntimeException(\sprintf('Expected "%s" but got "%s".', TemplateInterface::class, $resolvedDimensionContent::class));
            }

            return $resolvedDimensionContent;
        } catch (ContentNotFoundException) {
            return null;
        }
    }

    private function getSeoData(ArticleDimensionContentInterface $dimensionContent, Route $route): ?array
    {
        $locale = $dimensionContent->getLocale();
        if (!$locale) {
            return null;
        }

        $mainWebspace = $dimensionContent->getMainWebspace();
        if (!$mainWebspace) {
            return null;
        }

        $canonicalUrl = $this->webspaceManager->findUrlByResourceLocator(
            $route->getSlug(),
            $this->environment,
            $locale,
            $mainWebspace,
        );

        if (!$canonicalUrl) {
            return null;
        }

        return ['canonicalUrl' => $canonicalUrl];
    }

    private function resolveTemplateMetadata(string $type, string $templateKey, string $locale): TemplateMetadata
    {
        $typedMetadata = $this->metadataProviderRegistry->getMetadataProvider('form')
            ->getMetadata($type, $locale, []);

        if (!$typedMetadata instanceof TypedFormMetadata) {
            throw new \RuntimeException(\sprintf('Could not find metadata "%s" of type "%s".', 'form', $type));
        }

        $metadata = $typedMetadata->getForms()[$templateKey] ?? null;

        if (!$metadata instanceof FormMetadata) {
            throw new \RuntimeException(\sprintf('Could not find form metadata "%s" of type "%s".', $templateKey, $type));
        }

        $templateMetadata = $metadata->getTemplate();

        if (!$templateMetadata instanceof TemplateMetadata) {
            throw new \RuntimeException(\sprintf('Could not find template metadata "%s" of type "%s".', $templateKey, $type));
        }

        return $templateMetadata;
    }

    private function getCacheLifetime(TemplateMetadata $templateMetadata): ?int
    {
        $cacheLifetime = $templateMetadata->getCacheLifetime();
        if (!$cacheLifetime instanceof CacheLifetimeMetadata) {
            return null;
        }

        $cacheLifeTimeType = $cacheLifetime->getType();
        $cacheLifeTimeValue = $cacheLifetime->getValue();

        if (!$this->cacheLifetimeResolver->supports($cacheLifeTimeType, $cacheLifeTimeValue)) {
            throw new \InvalidArgumentException(\sprintf('Invalid cacheLifeTime in route default provider: %s', \json_encode([
                'type' => $cacheLifeTimeType,
                'value' => $cacheLifeTimeValue,
            ], flags: \JSON_THROW_ON_ERROR)));
        }

        return $this->cacheLifetimeResolver->resolve($cacheLifeTimeType, $cacheLifeTimeValue);
    }
}
