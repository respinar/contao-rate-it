<?php

declare(strict_types=1);

/**
 * This file is part of hofff/contao-rate-it.
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 *
 * @copyright  2019 hofff.com.
 * @copyright  2013-2018 cgo IT.
 * @license    https://github.com/hofff/contao-rate-it/blob/master/LICENSE LGPL-3.0-or-later
 *
 * @filesource
 */

namespace Hofff\Contao\RateIt\Frontend;

use Contao\BackendTemplate;
use Contao\Config;
use Contao\FrontendUser;
use Contao\System;
use Hofff\Contao\RateIt\Rating\RatingService;

/**
 * Class RateItHybrid.
 */
abstract class RateItHybrid extends RateItFrontend
{
    // protected $intStars = 5;

    /**
     * Initialize the controller.
     */
    /**
     * Initialize the controller.
     */
    public function __construct($objElement)
    {
        parent::__construct($objElement);
    }

    /**
     * Display a wildcard in the back end.
     *
     * @return string
     */
    public function generate()
    {
        $container = System::getContainer();
        $request = $container->get('request_stack')->getCurrentRequest();
        $scopeMatcher = $container->get('contao.routing.scope_matcher');

        if ($request && $scopeMatcher->isBackendRequest($request)) {
            $objTemplate = new BackendTemplate('be_wildcard');

            $objTemplate->wildcard = '### Rate IT ###';
            $objTemplate->title = $this->rateit_title;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = $this->generateBackendUrl();

            return $objTemplate->parse();
        }

        $configAdapter = $container->get('contao.framework')->getAdapter(Config::class);
        $this->strTemplate = $configAdapter->get('rating_template') ?: 'rateit_default';
        $this->strTextPosition = $configAdapter->get('rating_textposition') ?: 'after';

        return parent::generate();
    }

    private function generateBackendUrl(): string
    {
        $container = System::getContainer();
        $router = $container->get('router');

        if ('ce' === $this->getType()) {
            return $router->generate('contao_backend', [
                'do' => 'article',
                'table' => 'tl_content',
                'act' => 'edit',
                'id' => $this->id,
            ]);
        }

        return $router->generate('contao_backend', [
            'do' => 'themes',
            'table' => 'tl_module',
            'act' => 'edit',
            'id' => $this->id,
        ]);
    }

    /**
     * Generate the module/content element.
     */
    protected function compile(): void
    {
        $rating = self::getContainer()
            ->get(RatingService::class)
            ->getRating($this->getType(), (int) $this->getParent()->id, $this->getUserId())
        ;

        $this->Template->setData(array_merge($this->Template->getData(), (array) $rating));

        $this->Template->showBefore = 'before' === $this->strTextPosition;
        $this->Template->showAfter = 'after' === $this->strTextPosition;

        parent::compile();
    }

    abstract protected function getType(): string;

    private function getUserId(): int|null
    {
        $user = FrontendUser::getInstance();

        if ($user->id) {
            return (int) $user->id;
        }

        return null;
    }
}
