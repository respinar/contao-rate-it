<?php

declare(strict_types=1);

namespace Hofff\Contao\RateIt\DependencyInjection\Compiler;

use Hofff\Contao\RateIt\EventListener\Hook\RateItArticleListener;
use Hofff\Contao\RateIt\EventListener\Hook\RateItCommentsListener;
use Hofff\Contao\RateIt\EventListener\Hook\RateItNewsListener;
use Hofff\Contao\RateIt\EventListener\Hook\RateItPageListener;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class RemoveInactiveListenersPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (! $container->hasParameter('hofff.contao_rate_it.types')) {
            return;
        }

        $types   = $container->getParameter('hofff.contao_rate_it.types');
        $bundles = $container->getParameter('kernel.bundles');

        if (! \in_array('page', $types, true)) {
            $container->removeDefinition(RateItPageListener::class);
        }

        if (! \in_array('article', $types, true)) {
            $container->removeDefinition(RateItArticleListener::class);
        }

        if (! isset($bundles['ContaoNewsBundle']) || ! \in_array('news', $types, true)) {
            $container->removeDefinition(RateItNewsListener::class);
        }

        if (! isset($bundles['ContaoCommentsBundle']) || ! \in_array('comments', $types, true)) {
            $container->removeDefinition(RateItCommentsListener::class);
        }
    }
}
