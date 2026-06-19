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

use Contao\ArticleModel;
use Contao\BackendTemplate;
use Contao\Config;
use Contao\Environment;
use Contao\FrontendTemplate;
use Contao\Input;
use Contao\NewsModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;

/**
 * Class RateItTopRatingsModule.
 */
class RateItTopRatingsModule extends RateItFrontend
{
    private static array $arrUrlCache = [];

    /**
     * Initialize the controller.
     */
    public function __construct($objElement)
    {
        parent::__construct($objElement);

        $this->strKey = 'rateit_top_ratings';
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

            $objTemplate->wildcard = '### Rate IT Best/Most Ratings ###';
            $objTemplate->title = $this->name;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = $container->get('router')->generate('contao_backend', [
                'do' => 'themes',
                'table' => 'tl_module',
                'act' => 'edit',
                'id' => $this->id,
            ]);

            return $objTemplate->parse();
        }

        $this->strTemplate = $this->rateit_template;

        $this->arrTypes = StringUtil::deserialize($this->rateit_types);

        return parent::generate();
    }

    /**
     * Generate the module/content element.
     */
    protected function compile(): void
    {
        $this->Template = new FrontendTemplate($this->strTemplate);

        $this->Template->setData($this->arrData);

        $connection = System::getContainer()->get('database_connection');
        $sql = "SELECT i.id AS item_id,
            i.rkey AS rkey,
            i.title AS title,
            i.typ AS typ,
            i.createdat AS createdat,
            i.active AS active,
            IFNULL(AVG(r.rating),0) AS best,
            COUNT( r.rating ) AS most
        FROM tl_rateit_items i
            LEFT OUTER JOIN tl_rateit_ratings r
                ON (i.id = r.pid)
        WHERE
            typ IN ('".implode("', '", $this->arrTypes)."')
        GROUP BY rkey, title, item_id, typ, createdat, active
        ORDER BY ".$this->rateit_toptype.' DESC';

        $arrResult = $connection->fetchAllAssociative($sql.' LIMIT '.(int) $this->rateit_count);

        $objReturn = [];

        foreach ($arrResult as $result) {
            $return = new \stdClass();
            $return->title = $result['title'];
            $return->typ = $result['typ'];

            // ID ermitteln
            $stars = $this->percentToStars($result['best']);
            $configAdapter = System::getContainer()->get('contao.framework')->getAdapter(Config::class);
            $return->rateItID = 'rateItRating-'.$result['rkey'].'-'.$result['typ'].'-'.
                $stars.'_'.(int) $configAdapter->get('rating_count');
            $return->descriptionId = 'rateItRating-'.$result['rkey'].'-description';

            $return->rateit_class = 'rateItRating';

            $return->url = $this->getUrl($result);

            // Beschriftung ermitteln
            $rating = [];
            $rating['totalRatings'] = $result['most'];
            $rating['rating'] = $result['best'];
            $return->description = $this->getStarMessage($rating);

            $return->rating = $result['best'];
            $return->count = $result['most'];
            $return->rel = 'not-rateable';
            $objReturn[] = $return;
        }

        $this->Template->arrRatings = $objReturn;
    }

    private function getUrl(array $rating)
    {
        if ('page' === $rating['typ']) {
            return PageModel::findById($rating['rkey'])->getAbsoluteUrl();
        }
        if ('article' === $rating['typ']) {
            $objArticle = ArticleModel::findPublishedById($rating['rkey']);
            if (null !== $objArticle) {
                return PageModel::findById($objArticle->pid)->getAbsoluteUrl().'#'.$objArticle->alias;
            }
        }
        if ('news' === $rating['typ']) {
            $objNews = NewsModel::findById($rating['rkey']);
            $objArticle = NewsModel::findPublishedByPid($objNews->pid);

            // Internal link
            if ('external' !== $objArticle->source) {
                return $this->generateNewsUrl($objNews);
            }

            // Encode e-mail addresses
            if ('mailto:' === substr($objArticle->url, 0, 7)) {
                $strArticleUrl = StringUtil::encodeEmail($objArticle->url);
            } // Ampersand URIs
            else {
                $strArticleUrl = ampersand($objArticle->url);
            }

            /** @var \PageModel $objPage */
            global $objPage;

            // External link
            return $strArticleUrl;
        }

        return false;
    }

    private function generateNewsUrl($objItem)
    {
        $strCacheKey = 'id_'.$objItem->id;

        // Load the URL from cache
        if (isset(self::$arrUrlCache[$strCacheKey])) {
            return self::$arrUrlCache[$strCacheKey];
        }

        // Initialize the cache
        self::$arrUrlCache[$strCacheKey] = null;

        switch ($objItem->source) {
            // Link to an external page
            case 'external':
                if ('mailto:' === substr($objItem->url, 0, 7)) {
                    self::$arrUrlCache[$strCacheKey] = StringUtil::encodeEmail($objItem->url);
                } else {
                    self::$arrUrlCache[$strCacheKey] = ampersand($objItem->url);
                }
                break;

            // Link to an internal page
            case 'internal':
                if (($objTarget = $objItem->getRelated('jumpTo')) !== null) {
                    /** @var \PageModel $objTarget */
                    self::$arrUrlCache[$strCacheKey] = ampersand($objTarget->getFrontendUrl());
                }
                break;

            // Link to an article
            case 'article':
                if (
                    ($objArticle = ArticleModel::findById($objItem->articleId, [
                    'eager' => true,
                ])) !== null && ($objPid = $objArticle->getRelated('pid')) !== null
                ) {
                    /** @var \PageModel $objPid */
                    self::$arrUrlCache[$strCacheKey] = ampersand($objPid->getFrontendUrl('/articles/'.(!Config::get('disableAlias') && '' !== $objArticle->alias ? $objArticle->alias : $objArticle->id)));
                }
                break;
        }

        // Link to the default page
        if (null === self::$arrUrlCache[$strCacheKey]) {
            $objPage = PageModel::findWithDetails($objItem->getRelated('pid')->jumpTo);

            if (null === $objPage) {
                self::$arrUrlCache[$strCacheKey] = ampersand(Environment::get('request'), true);
            } else {
                self::$arrUrlCache[$strCacheKey] = ampersand($objPage->getFrontendUrl((Config::get('useAutoItem') && !Config::get('disableAlias') ? '/' : '/items/').(!Config::get('disableAlias') && '' !== $objItem->alias ? $objItem->alias : $objItem->id)));
            }

            // Add the current archive parameter (news archive)
            if ($blnAddArchive && '' !== Input::get('month')) {
                self::$arrUrlCache[$strCacheKey] .= (Config::get('disableAlias') ? '&amp;' : '?').'month='.Input::get('month');
            }
        }

        return self::$arrUrlCache[$strCacheKey];
    }
}
