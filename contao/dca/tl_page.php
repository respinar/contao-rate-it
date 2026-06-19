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

use Hofff\Contao\RateIt\EventListener\Dca\PageDcaListener;

/*
 * Extend tl_page
 */

$GLOBALS['TL_DCA']['tl_page']['config']['onload_callback'][] = [PageDcaListener::class, 'onLoad'];
$GLOBALS['TL_DCA']['tl_page']['config']['onundo_callback'][] = [PageDcaListener::class, 'onUndo'];

/*
 * Palettes
 */
$GLOBALS['TL_DCA']['tl_page']['palettes']['__selector__'][] = 'addRating';

/*
 * Add subpalettes to tl_page
 */
$GLOBALS['TL_DCA']['tl_page']['subpalettes']['addRating'] = 'rateit_position';

// Fields
$GLOBALS['TL_DCA']['tl_page']['fields']['addRating'] =
[
    'inputType' => 'checkbox',
    'sql' => "char(1) NOT NULL default ''",
    'eval' => ['tl_class' => 'w50 m12', 'submitOnChange' => true],
];

$GLOBALS['TL_DCA']['tl_page']['fields']['rateit_position'] =
[
    'default' => 'before',
    'inputType' => 'select',
    'options' => ['after', 'before', 'custom'],
    'reference' => &$GLOBALS['TL_LANG']['tl_page'],
    'sql' => "varchar(6) NOT NULL default ''",
    'eval' => ['mandatory' => true, 'tl_class' => 'w50'],
];
