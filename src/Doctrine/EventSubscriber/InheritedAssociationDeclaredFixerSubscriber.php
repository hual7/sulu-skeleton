<?php

declare(strict_types=1);

namespace App\Doctrine\EventSubscriber;

use App\Entity\Page;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Gedmo\Mapping\MappedEventSubscriber;
use Gedmo\Tree\TreeListener;
use Sulu\Page\Domain\Model\Page as SuluPage;

class InheritedAssociationDeclaredFixerSubscriber
{
    public function loadClassMetadata(LoadClassMetadataEventArgs $event): void
    {
        $metadata = $event->getClassMetadata();

        if (!$metadata->isMappedSuperclass) {
            $this->fixAssociationDeclared($metadata->getName(), $metadata->associationMappings);
        }

        if (Page::class === $metadata->getName()) {
            $this->aliasGedmoTreeConfig($event, Page::class, SuluPage::class);
        }
    }

    private function fixAssociationDeclared(string $className, iterable $associationMappings): void
    {
        foreach ($associationMappings as $fieldName => $mapping) {
            if (null !== $mapping->declared) {
                continue;
            }

            $rc = new \ReflectionClass($className);

            if ($rc->hasProperty($fieldName)) {
                $mapping->declared = $rc->getProperty($fieldName)->getDeclaringClass()->getName();
                continue;
            }

            foreach (\class_parents($className) as $parentClass) {
                $parentRc = new \ReflectionClass($parentClass);
                foreach ($parentRc->getProperties(\ReflectionProperty::IS_PRIVATE) as $rp) {
                    if ($rp->getName() === $fieldName) {
                        $mapping->declared = $parentClass;
                        continue 3;
                    }
                }
            }
        }
    }

    private function aliasGedmoTreeConfig(LoadClassMetadataEventArgs $event, string $sourceClass, string $aliasClass): void
    {
        $em = $event->getEntityManager();

        foreach ($em->getEventManager()->getAllListeners() as $listeners) {
            foreach ($listeners as $listener) {
                if (!$listener instanceof TreeListener) {
                    continue;
                }

                $rpConfigs = new \ReflectionProperty(MappedEventSubscriber::class, 'configurations');
                $configurations = $rpConfigs->getValue();

                $rpName = new \ReflectionProperty(MappedEventSubscriber::class, 'name');
                $name = $rpName->getValue($listener);

                $sourceConfig = $configurations[$name][$sourceClass] ?? null;

                if (null === $sourceConfig || [] === $sourceConfig) {
                    $listener->getConfiguration($em, $sourceClass);
                    $configurations = $rpConfigs->getValue();
                    $sourceConfig = $configurations[$name][$sourceClass] ?? null;
                }

                if (null !== $sourceConfig && [] !== $sourceConfig && !isset($configurations[$name][$aliasClass])) {
                    $configurations[$name][$aliasClass] = $sourceConfig;
                    $rpConfigs->setValue(null, $configurations);
                }

                return;
            }
        }
    }
}
