<?php

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

declare(strict_types=1);

namespace Hofff\Contao\RateIt\Rating;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\System;
use Doctrine\DBAL\Connection;

final class RatingService
{
    private const string SQL_QUERY = <<<'SQL'

        SELECT
            i.id AS id,
            i.rkey AS rkey,
            i.title AS title,
            IFNULL(AVG(r.rating),0) AS rating,
            COUNT( r.rating ) AS totalRatings
        FROM
            tl_rateit_items i
        LEFT OUTER JOIN
            tl_rateit_ratings r
            ON i.id = r.pid
        WHERE
            i.rkey=:rkey and typ=:type and active='1'
        GROUP BY i.rkey, i.id, i.title;
        SQL;

    private Connection $connection;

    /**
     * @var ContaoFrameworkInterface
     */
    private $framework;

    /**
     * @var IsUserAllowedToRate
     */
    private $isUserAllowedToRate;

    public function __construct(Connection $connection, ContaoFramework $framework, IsUserAllowedToRate $isUserAllowedToRate)
    {
        $this->connection = $connection;
        $this->framework = $framework;
        $this->isUserAllowedToRate = $isUserAllowedToRate;
    }

    public function getRating(string $type, int $ratingTypeId, int|null $userId): array|null
    {
        return $this->getRatingWithMessageTemplate($type, $ratingTypeId, $this->getConfig('rating_description') ?? '', $userId);
    }

    public function getRatingWithSuccessMessage(string $type, int $ratingTypeId, int|null $userId): array|null
    {
        return $this->getRatingWithMessageTemplate($type, $ratingTypeId, $this->getConfig('rating_success') ?? '' ?: ($this->getConfig('rating_description') ?? ''), $userId);
    }

    private function getRatingWithMessageTemplate(string $type, int $ratingTypeId, string $template, int|null $userId): array|null
    {
        $rating = $this->loadRating($ratingTypeId, $type);
        if (!$rating) {
            return null;
        }

        $stars = $this->percentToStars($rating['rating']);
        $maxStars = $this->maxStars();
        $sessionId = new CurrentUserId();

        return [
            'descriptionId' => \sprintf('rateItRating-%s-description', $ratingTypeId),
            'description' => $this->getStarMessage($template, $rating),
            'id' => \sprintf('rateItRating-%s-%s-%s_%s', (string) $ratingTypeId, $type, $stars, $maxStars),
            'class' => 'rateItRating',
            'itemreviewed' => $rating['title'],
            'actRating' => $this->percentToStars($rating['rating']),
            'maxRating' => $maxStars,
            'enabled' => ($this->isUserAllowedToRate)((int) $rating['id'], (string) $sessionId, $userId),
            'votes' => $rating['totalRatings'],
            'ratingId' => $ratingTypeId,
            'ratingType' => $type,
            'showBefore' => 'before' === $this->getConfig('rating_textposition'),
            'showAfter' => 'after' === $this->getConfig('rating_textposition'),
        ];
    }

    private function loadRating(int $rkey, string $typ): array|null
    {
        $statement = $this->connection->prepare(self::SQL_QUERY);
        $statement->bindValue('rkey', $rkey);
        $statement->bindValue('type', $typ);
        $result = $statement->executeQuery();

        if (0 === $result->rowCount()) {
            return null;
        }

        return $result->fetchAssociative();
    }

    private function maxStars(): int
    {
        return (int) $this->getConfig('rating_count') ?: 5;
    }

    private function getConfig(string $key)
    {
        $this->framework->initialize();

        return $this->framework->getAdapter(Config::class)->get($key);
    }

    // TODO: Rework
    private function getStarMessage(string $template, array|null $rating): string
    {
        $this->framework->initialize();
        $this->framework->getAdapter(System::class)->loadLanguageFile('default');

        $stars = $this->percentToStars($rating['rating']);

        $labels = [];

        if (preg_match('/^.*\[(.+)\|(.+)\].*$/i', $template, $labels)) {
            $label = $rating['totalRatings'] > 1 || 0 === $rating['totalRatings']
                ? $labels[2]
                : $labels[1];

            $description = $template;
        } else {
            $label = '';
            $description = $template;
        }

        $actValue = $rating['totalRatings'];
        $type = $GLOBALS['TL_LANG']['rateit']['stars'];

        $description = str_replace(
            '%current%',
            str_replace('.', ',', (string) $stars),
            $description,
        );

        $description = str_replace(
            '%max%',
            (string) $this->maxStars(),
            $description,
        );

        $description = str_replace('%type%', $type, $description);
        $description = str_replace('%count%', (string) $actValue, $description);

        if ('' !== $label) {
            return preg_replace(
                '/^(.*)(\[.*\])(.*)$/i',
                "\\1$label\\3",
                $description,
            );
        }

        return $description;
    }

    private function percentToStars($rating): float
    {
        $modifier = 100 / $this->maxStars();

        return round($rating / $modifier, 1);
    }
}
