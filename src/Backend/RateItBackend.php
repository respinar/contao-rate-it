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

namespace Hofff\Contao\RateIt\Backend;

use Contao\StringUtil;
use Contao\System;

class RateItBackend
{
    public const path = 'bundles/hofffcontaorateit/';

    /**
     * Get a css file.
     *
     * @param string $file the basename if the file (without extension)
     *
     * @return string the file path
     */
    public static function css($file): string
    {
        return self::path.'css/'.$file.'.css';
    } // file

    /**
     * Get a js file.
     *
     * @param string $file the basename if the file (without extension)
     *
     * @return string the file path
     */
    public static function js($file): string
    {
        return self::path.'js/'.$file.'.js';
    } // file

    /**
     * Get image url from the theme.
     *
     * @param string $file the basename if the image (without extension)
     *
     * @return string the image path
     */
    public static function image($file): string
    {
        $webDirectory = System::getContainer()->getParameter('contao.web_dir');
        $url = self::path.'images/';

        if (is_file($webDirectory.'/'.$url.$file.'.png')) {
            return $url.$file.'.png';
        }
        if (is_file($webDirectory.'/'.$url.$file.'.gif')) {
            return $url.$file.'.gif';
        }

        return $url.'star.gif';
    } // image

    /**
     * Create a 'img' tag from theme icons.
     *
     * @param string $file       the basename if the image (without extension)
     * @param string $alt        the 'alt' text
     * @param string $attributes additional tag attributes
     *
     * @return string the html code
     */
    public static function createImage($file, $alt = '', $attributes = ''): string
    {
        if ('' === $alt) {
            $alt = 'icon';
        }
        $img = self::image($file);
        $size = getimagesize($img);

        return '<img'.('.png' === substr($img, -4) ? ' class="pngfix"' : '').' src="'.$img.'" '.$size[3].' alt="'.StringUtil::specialchars($alt).'"'.('' !== $attributes ? ' '.$attributes : '').'>';
    } // createImage

    /**
     * Create a list button (link button).
     *
     * @param string $file    the basename if the image (without extension)
     * @param string $link    the URL of the link to create
     * @param string $text    the alt/title text
     * @param string $confirm optional confirmation text before redirecting to the link
     * @param bool   $popup   open the target in a new window
     *
     * @return string the html code
     */
    public function createListButton($file, $link, $text, $confirm = '', $popup = false): string
    {
        $target = $popup ? ' target="_blank"' : '';
        $onclick = '' !== $confirm ? ' onclick="if(!confirm(\''.$confirm.'\'))return false"' : '';

        return '<a href="'.$link.'" title="'.$text.'"'.$target.$onclick.'>'.$this->createImage($file, $text).'</a>';
    } // createListButton

    public function createMainButton($file, $link, $text, $confirm = ''): string
    {
        $onclick = '' === $confirm
            ? ''
            : ' onclick="if(!confirm(\''.$confirm.'\'))return false"';

        return '<a href="'.$link.'" title="'.$text.'"'.$onclick.'>'.$this->createImage($file, $text).' '.$text.'</a>';
    } // createMainButton
} // class RateItBackend
