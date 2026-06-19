<?php

/**
 * This file is part of hofff/contao-rate-it.
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 *
 * @copyright  2019-2020 hofff.com.
 * @license    https://github.com/hofff/contao-rate-it/blob/master/LICENSE LGPL-3.0-or-later
 *
 * @filesource
 */

declare(strict_types=1);

namespace Hofff\Contao\RateIt\Rating\RatingType;

use Doctrine\DBAL\Connection;
use Hofff\Contao\RateIt\Rating\RatingType;
use Hofff\Contao\RateIt\Rating\SourceInformation;

abstract class BaseRatingType implements RatingType
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function sourceInformation(int $sourceId): SourceInformation|null
    {
        $record = $this->loadRecord($sourceId);
        if (null === $record) {
            return null;
        }

        return new SourceInformation(
            $this->generateTitle($record),
            $this->determineActiveState($record),
            $this->determineParentStatus($record),
        );
    }

    protected function determineParentStatus(array $record): string
    {
        $published = $this->determineParentPublishedState($record);

        switch ($published) {
            case true:
                return 'a';

            case false:
                return 'i';

            case null:
            default:
                return 'r';
        }
    }

    protected function loadRecord(int $sourceId): array|null
    {
        $record = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->tableName())
            ->where('id = :id')
            ->setParameter('id', $sourceId)
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative()
        ;

        return $record ?: null;
    }

    abstract protected function tableName(): string;

    abstract protected function generateTitle(array $record): string;

    abstract protected function determineActiveState(array $record): bool;

    abstract protected function determineParentPublishedState(array $record): bool;
}
