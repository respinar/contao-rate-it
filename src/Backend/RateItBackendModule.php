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

use Contao\BackendModule;
use Contao\BackendUser;
use Contao\Config;
use Contao\Database;
use Contao\Input;
use Contao\System;
use Hofff\Contao\RateIt\Rating\RatingTypes;

class RateItBackendModule extends BackendModule
{
    protected $strTemplate;
    protected $actions = [];

    protected $rateit;

    protected $tl_root;
    protected $tl_files;
    protected $languages;

    private $compiler;
    private $action = '';
    private $parameter = '';

    private $arrExportHeader;
    private $arrExportHeaderDetails;

    /**
     * Anzahl der Herzen/Sterne.
     *
     * @var int
     */
    protected $intStars = 5;

    protected $label;
    protected $labels;

    /**
     * @var Config
     */
    protected $config;

    /**
     * Initialize the controller.
     */
    public function __construct($objElement = [])
    {
        parent::__construct($objElement);

        // Fetch ContaoFramework and Config manually
        $framework = System::getContainer()->get('contao.framework');
        $this->config = $framework->getAdapter(Config::class);

        $this->label = $GLOBALS['TL_LANG']['rateit']['star'];
        $this->labels = $GLOBALS['TL_LANG']['rateit']['stars'];

        $this->actions = [
            //	  act[0]			strTemplate					compiler
            ['', 'rateitbe_ratinglist', 'listRatings'],
            ['reset_ratings', '', 'resetRatings'],
            ['view', 'rateitbe_ratingview', 'viewRating'],
        ];

        $this->loadLanguageFile('rateit_backend');
        $this->arrExportHeader = &$GLOBALS['TL_LANG']['tl_rateit']['xls_headers'];
        $this->arrExportHeaderDetails = &$GLOBALS['TL_LANG']['tl_rateit']['xls_headers_detail'];
    }

    /**
     * Generate module:
     * - Display a wildcard in the back end
     * - Select the template and compiler in the front end
     */
    public function generate(): string
    {
        $this->rateit = new \stdClass();
        $rateit = &$this->rateit;
        $backendUser = BackendUser::getInstance();
        $rateit->username = $backendUser->username;
        $rateit->isadmin = $backendUser->isAdmin;

        $this->strTemplate = $this->actions[0][1];
        $this->compiler = $this->actions[0][2];

        $act = Input::get('act');
        if (!$act) {
            $act = Input::post('act');
        }

        foreach ($this->actions as $action) {
            if ($act === $action[0]) {
                $this->parameter = $act;
                $this->action = $action[0];
                $this->strTemplate = $action[1];
                $this->compiler = $action[2];
                break;
            }
        }

        // Use injected config instead of static call
        $stars = (int) $this->config->get('rating_count');
        if ($stars > 0) {
            $this->intStars = $stars;
        }

        return str_replace(['{{', '}}'], ['[{]', '[}]'], parent::generate());
    } // generate

    /**
     * Compile module: common initializations and forwarding to distinct function compiler.
     */
    protected function compile(): void
    {
        // hide module?
        $compiler = $this->compiler;
        if ('hide' === $compiler) {
            return;
        }

        // load other helpers
        $this->tl_root = System::getContainer()->getParameter('kernel.project_dir').'/';
        $this->tl_files = str_replace('\\', '/', System::getContainer()->getParameter('contao.upload_path')).'/';
        $this->Template->rateit = $this->rateit;

        // complete rateit initialization
        $rateit = &$this->rateit;
        $rateit->f_link = $this->createUrl([$this->action => $this->parameter]);
        $rateit->f_action = $this->compiler;
        $rateit->f_mode = $this->action;
        $rateit->theme = new RateItBackend();
        $rateit->backLink = $this->getReferer(true);
        $rateit->homeLink = $this->createUrl();

        // execute compiler
        $this->$compiler($this->parameter);
    } // compile

    /**
     * List the ratings.
     */
    protected function listRatings(): void
    {
        $rateit = &$this->Template->rateit;
        $rateit->f_page = 0;
        $rateit->f_typ = '';
        $rateit->f_active = '';
        $rateit->f_parentstatus = '';
        $rateit->f_order = '';
        $rateit->f_find = '';
        $options = [];
        $types = [];

        $session = System::getContainer()->get('request_stack')->getSession();

        // returning from submit?
        if ($this->filterPost('rateit_action') === $rateit->f_action) {
            // get url parameters
            $rateit->f_typ = trim(Input::post('rateit_typ') ?? '');
            $rateit->f_active = trim(Input::post('rateit_active') ?? '');
            $rateit->f_parentstatus = trim(Input::post('rateit_parentstatus') ?? '');
            $rateit->f_order = trim(Input::post('rateit_order') ?? '');
            $rateit->f_page = trim(Input::post('rateit_page') ?? '');
            $rateit->f_find = trim(Input::post('rateit_find') ?? '');
            $session->set('rateit_settings', [
                'rateit_typ' => $rateit->f_typ,
                'rateit_parentstatus' => $rateit->f_parentstatus,
                'rateit_order' => $rateit->f_order,
                'rateit_page' => $rateit->f_page,
                'rateit_find' => $rateit->f_find,
            ]);
        } else {
            $stg = $session->get('rateit_settings');
            if (\is_array($stg)) {
                $rateit->f_typ = trim($stg['rateit_typ'] ?? '');
                $rateit->f_active = trim($stg['rateit_active'] ?? '');
                $rateit->f_parentstatus = trim($stg['rateit_parentstatus'] ?? '');
                $rateit->f_order = trim($stg['rateit_order'] ?? '');
                $rateit->f_page = trim($stg['rateit_page'] ?? '');
                $rateit->f_find = trim($stg['rateit_find'] ?? '');
            } // if
        } // if

        if ('' === $rateit->f_order) {
            $rateit->f_order = 'rating';
        }
        // if (!isset($rateit->f_active)) $rateit->f_active = '-1';

        $perpage = (int) ($this->config->get('rating_listsize') ?? 10);
        if ($perpage < 0) {
            $perpage = 10;
        }

        if ($rateit->f_page >= 0 && $perpage > 0) {
            $options['first'] = (int) $rateit->f_page * $perpage;
            $options['limit'] = $perpage;
        } // if
        if ('' !== $rateit->f_typ) {
            $options['typ'] = $rateit->f_typ;
        }
        if ('' !== $rateit->f_active) {
            $options['active'] = '0' === $rateit->f_active ? '' : $rateit->f_active;
        }
        if ('' !== $rateit->f_parentstatus) {
            $options['parentstatus'] = $rateit->f_parentstatus;
        }
        if ('' !== $rateit->f_find) {
            $options['find'] = $rateit->f_find;
        }

        switch ($rateit->f_order) {
            case 'title':
                $options['order'] = 'title';
                break;
            case 'typ':
                $options['order'] = 'typ';
                break;
            case 'createdat':
                $options['order'] = 'createdat';
                break;
            default:
                $options['order'] = 'rating desc';
        } // switch

        // query extensions
        $rateit->ratingitems = $this->getRatingItems($options);
        if ($rateit->f_page >= 0 && $perpage > 0 && 0 === \count($rateit->ratingitems)) {
            $rateit->f_page = 0;
            $options['first'] = 0;
            $rateit->ratingitems = $this->getRatingItems($options);
        } // if

        // add view links
        foreach ($rateit->ratingitems as &$ext) {
            $ext->viewLink = $this->createUrl(['act' => 'view', 'rkey' => $ext->rkey, 'typ' => $ext->typ]);
            $totrecs = $ext->totcount;
            $types[] = $ext->typ;
        } // foreach

        // create pages list
        $rateit->pages = [];
        if ($perpage > 0) {
            $first = 1;

            while ($totrecs > 0) {
                $cnt = $totrecs > $perpage ? $perpage : $totrecs;
                $rateit->pages[] = $first.' - '.($first + $cnt - 1);
                $first += $cnt;
                $totrecs -= $cnt;
            } // while
        } // if

        $this->Template->types = $this->getUsedTypes();
    } // listRatings

    /**
     * Detailed view of one rating.
     *
     * @param string
     */
    protected function viewRating(): void
    {
        $rateit = &$this->Template->rateit;

        $rateit->f_page = 0;

        $session = System::getContainer()->get('request_stack')->getSession();

        // returning from submit?
        if ($this->filterPost('rateit_action') === $rateit->f_action) {
            // get url parameters
            $rateit->f_page = trim(Input::post('rateit_details_page') ?? '');
            $session->set('rateit_settings', [
                'rateit_details_page' => $rateit->f_page,
            ]);
        } else {
            $stg = $session->get('rateit_settings');
            if (\is_array($stg)) {
                $rateit->f_page = trim($stg['rateit_details_page'] ?? '');
            } // if
        } // if

        $rkey = Input::get('rkey');
        if (strstr($rkey, '|')) {
            $arrRkey = explode('|', $rkey);

            foreach ($arrRkey as $key) {
                if (!is_numeric($key)) {
                    $this->redirect($rateit->homeLink);
                    exit;
                }
                $id = $rkey;
            }
        } else {
            if (is_numeric($rkey)) {
                $id = $rkey;
            } else {
                $this->redirect($rateit->homeLink);
                exit;
            }
        }

        $typ = Input::get('typ');

        // compose base options
        $options = [
            'rkey' => $rkey,
            'typ' => $typ,
        ];

        $this->rateit->f_link = $this->createUrl(['act' => 'view', 'rkey' => $rkey, 'typ' => $typ]);

        $perpage = (int) ($this->config->get('rating_listsize') ?? 10);
        if ($perpage < 0) {
            $perpage = 10;
        }

        if ($rateit->f_page >= 0 && $perpage > 0) {
            $options['first'] = ((int) $rateit->f_page) * $perpage;
            $options['limit'] = $perpage;
        } // if

        $rateit->ratingitems = $this->getRatingItems($options, true);
        if (\count($rateit->ratingitems) < 1) {
            $this->redirect($rateit->homeLink);
        }
        $ext = &$rateit->ratingitems[0];

        $ext->ratings = $this->getRatings($ext, $options);
        if ($rateit->f_page >= 0 && $perpage > 0 && 0 === \count($ext->ratings)) {
            $rateit->f_page = 0;
            $options['first'] = 0;
            $rateit->ratings = $this->getRatings($ext, $options);
        } // if

        if (\count($ext->ratings) > 0) {
            $totrecs = $ext->ratings[0]->totcount;
        } else {
            $totrecs = 0;
        }

        // create pages list
        $rateit->pages = [];
        if ($perpage > 0) {
            $first = 1;

            while ($totrecs > 0) {
                $cnt = $totrecs > $perpage ? $perpage : $totrecs;
                $rateit->pages[] = $first.' - '.($first + $cnt - 1);
                $first += $cnt;
                $totrecs -= $cnt;
            } // while
        } // if

        $ext->statistics = $this->getRatingStatistics($ext->item_id);
        $ext->ratingsChartData = $this->getRatingsChartData($ext->statistics);
        $ext->monthsChartData = $this->getMonthsChartData($ext->item_id);
    } // viewRating

    protected function resetRatings(): void
    {
        if ('updateinformation' === Input::post('rateit_action')) {
            $this->updateParentInformation();

            return;
        }

        $rateit = &$this->Template->rateit;

        // nothing checked?
        $ids0 = Input::post('selectedids');
        if (!\is_array($ids0)) {
            $this->redirect($rateit->homeLink);

            return;
        }

        $removeParent = 'removeratings' === Input::post('rateit_action');

        foreach ($ids0 as $id) {
            [$rkey, $typ] = explode('__', $id);
            Database::getInstance()->beginTransaction();

            $pid = Database::getInstance()->prepare('SELECT id FROM tl_rateit_items WHERE rkey=? and typ=?')
                ->execute($rkey, $typ)
                ->fetchRow()
            ;

            Database::getInstance()->prepare('DELETE FROM tl_rateit_ratings WHERE pid=?')
                ->execute($pid[0])
            ;

            if ($removeParent) {
                Database::getInstance()->prepare('DELETE FROM tl_rateit_items WHERE id=?')
                    ->execute($pid[0])
                ;
            }

            Database::getInstance()->commitTransaction();
        }

        $this->redirect($rateit->homeLink);
    } // resetRatings

    public function updateParentInformation(): void
    {
        $rateit = &$this->Template->rateit;

        // nothing checked?
        $ids0 = Input::post('selectedids');
        if (!\is_array($ids0)) {
            self::redirect($rateit->homeLink);

            return;
        }

        $pageTypes = self::getContainer()->get(RatingTypes::class);
        $result = Database::getInstance()->execute('SELECT id, rkey, typ FROM tl_rateit_items');

        while ($result->next()) {
            $information = $pageTypes->sourceInformation($result->typ, (int) $result->rkey);
            if (null === $information) {
                Database::getInstance()
                    ->prepare('UPDATE tl_rateit_items %s WHERE id=?')
                    ->set(['parentstatus' => 'r'])
                    ->execute($result->id)
                ;

                continue;
            }

            Database::getInstance()
                ->prepare('UPDATE tl_rateit_items %s WHERE id=?')
                ->set(['parentstatus' => $information->parentStatus(), 'title' => $information->title()])
                ->execute($result->id)
            ;
        }

        self::redirect($rateit->homeLink);
    }

    /**
     * Create url for hyperlink to the current page.
     *
     * @param array $aParams assiciative array with key/value pairs as parameters
     *
     * @return string the create link
     */
    protected function createUrl(array $aParams = [])
    {
        return $this->createPageUrl(Input::get('do'), $aParams);
    } // createUrl

    /**
     * Create url for hyperlink to an arbitrary page.
     *
     * @param string $aPage   the page ID
     * @param array  $aParams assiciative array with key/value pairs as parameters
     *
     * @return string the create link
     */
    protected function createPageUrl($aPage, array $aParams = [])
    {
        $aParams['do'] = $aPage;

        return System::getContainer()->get('router')->generate('contao_backend', $aParams);
    } // createPageUrl

    /**
     * Get post parameter and filter value.
     *
     * @param string $aKey  The post key. When filtering html, remove all attribs and
     *                      keep the plain tags.
     * @param string $aMode '': no filtering
     *                      'nohtml': strip all html
     *                      'text': Keep tags p br ul li em
     *
     * @return string the filtered input
     */
    protected function filterPost($aKey, $aMode = ''): array|string|null
    {
        $v = trim(Input::postRaw($aKey) ?? '');
        if ('' === $v || '' === $aMode) {
            return $v;
        }

        switch ($aMode) {
            case 'nohtml':
            case 'text':
                $v = strip_tags($v);
                break;
        } // switch

        return preg_replace('/<(\w+) .*>/U', '<$1>', $v);
    }

    // filterPost
    /**
     * @return array<\stdClass>
     */
    protected function getRatingItems(array $aOptions, $noLimit = false): array
    {
        $sql = 'SELECT i.id as item_id,
				i.rkey AS rkey,
				i.title as title,
				i.typ as typ,
				i.createdat as createdat,
				i.active as active,
                i.parentstatus as parentstatus,
				IFNULL(AVG(r.rating),0) AS rating,
				COUNT( r.rating ) AS totalRatings
				FROM tl_rateit_items i
				LEFT OUTER JOIN tl_rateit_ratings r
				ON (i.id = r.pid)
				%w
				GROUP BY rkey, title, item_id, typ, createdat, active, parentstatus
				%o
				%l';

        $cntSql = 'SELECT COUNT(*) FROM tl_rateit_items i %s';

        $where = '';
        $firstWhere = true;
        $limit = '';
        $order = '';

        foreach ($aOptions as $k => $v) {
            if ('find' === $k) {
                if (!$firstWhere) {
                    $where .= ' AND';
                }
                $where .= " title like '%$v%'";
                $firstWhere = false;
            } elseif (!\in_array($k, ['order', 'limit', 'first'], true)) {
                if (!$firstWhere) {
                    $where .= ' AND';
                }
                $where .= " $k='$v'";
                $firstWhere = false;
            } else {
                if ('limit' === $k && !$noLimit) {
                    $cntRows = $v;
                } elseif ('first' === $k && !$noLimit) {
                    $first = $v;
                }
            }
        }

        if (isset($cntRows, $first)) {
            $limit = "LIMIT $first, $cntRows";
        }

        if ('' !== $where) {
            $where = 'WHERE '.$where;
        }

        if (isset($aOptions['order']) && !empty($aOptions['order'])) {
            $order = 'ORDER BY '.$aOptions['order'];
        }

        $sql = str_replace('%o', $order, $sql);
        $sql = str_replace('%w', $where, $sql);
        $sql = str_replace('%l', $limit, $sql);

        $cntSql = str_replace('%s', $where, $cntSql);

        $count = Database::getInstance()->query($cntSql)->fetchRow();

        $arrRatingItems = Database::getInstance()->query($sql)->fetchAllAssoc();
        $arrReturn = [];

        foreach ($arrRatingItems as $rating) {
            if ('1' !== $rating['active']) {
                $rating['active'] = '0';
            }
            $rating['percent'] = $rating['rating'];
            $rating['rating'] = $this->percentToStars($rating['percent']);
            $rating['stars'] = $this->intStars;
            $rating['totcount'] = $count[0];
            $arrReturn[] = (object) $rating;
        }

        return $arrReturn;
    }

    // getRatingItems
    /**
     * @return array<\stdClass>
     */
    protected function getRatings($ext, $options = []): array
    {
        // Gesamtanzahl (für Paging wichtig) ermitteln
        $cntSql = "SELECT COUNT(*) FROM tl_rateit_ratings r WHERE r.pid=$ext->item_id";
        $count = Database::getInstance()->prepare($cntSql)
            ->execute()
            ->fetchRow()
        ;

        foreach ($options as $k => $v) {
            if ('limit' === $k) {
                $cntRows = $v;
            } elseif ('first' === $k) {
                $first = $v;
            }
        }

        if (isset($cntRows, $first)) {
            $limit = "LIMIT $first, $cntRows";
        }

        $sql = "SELECT id AS rating_id, session_id, memberid, rating, createdat
		FROM tl_rateit_ratings r
		WHERE r.pid=$ext->item_id
		ORDER BY createdat DESC
		%l";
        $sql = str_replace('%l', $limit, $sql);

        $arrRatings = Database::getInstance()->prepare($sql)
            ->execute()
            ->fetchAllAssoc()
        ;
        $arrReturn = [];

        foreach ($arrRatings as $rating) {
            $rating['percent'] = $rating['rating'];
            $rating['rating'] = $this->percentToStars($rating['percent']);
            $rating['stars'] = $this->intStars;
            $rating['totcount'] = $count[0];
            if (null !== $rating['memberid']) {
                $member = Database::getInstance()->prepare('SELECT firstname, lastname FROM tl_member WHERE id=?')
                    ->limit(1)
                    ->execute($rating['memberid'])
                    ->fetchAssoc()
                ;
                $rating['member'] = $member['firstname'].' '.$member['lastname'];
            }
            $arrReturn[] = (object) $rating;
        }

        return $arrReturn;
    }

    // getRatings
    /**
     * @return array<\stdClass>
     */
    protected function getRatingStatistics($item_id): array
    {
        $sql = "SELECT rating, count(*) as count
		FROM tl_rateit_ratings r
		WHERE r.pid=$item_id
		GROUP BY rating
		ORDER BY rating";

        $arrRatingStatistics = Database::getInstance()->prepare($sql)
            ->execute()
            ->fetchAllAssoc()
        ;
        $arrReturn = [];

        foreach ($arrRatingStatistics as $rating) {
            $rating['percent'] = $rating['rating'];
            $rating['rating'] = $this->percentToStars($rating['percent']);
            $arrReturn[$rating['percent']] = (object) $rating;
        }

        return $arrReturn;
    } // getRatings

    protected function getRatingsChartData($statistics): false|string
    {
        $arr = [];
        $arr['cols'] = [];
        $arr['rows'] = [];

        // Spalten anlegen
        $arr['cols'][] = ['id' => 'rating', 'label' => $GLOBALS['TL_LANG']['tl_rateit']['rating_chart_legend'][2], 'type' => 'string'];
        $arr['cols'][] = ['id' => 'count', 'label' => $GLOBALS['TL_LANG']['tl_rateit']['rating_chart_legend'][3], 'type' => 'number'];

        // Zeilen anlegen
        foreach ($statistics as $obj) {
            $arr['rows'][] = ['c' => [['v' => $obj->rating.' '.(1 === $obj->rating ? $this->label : $this->labels)], ['v' => (int) $obj->count, 'f' => $obj->count.' '.$GLOBALS['TL_LANG']['tl_rateit']['vote'][1 === $obj->count ? 0 : 1]]]];
        }

        return json_encode($arr);
    }

    protected function getMonthsChartData($item_id): false|string
    {
        $sql = "SELECT count(*) AS anzahl, avg(rating) AS bewertung, month(date(FROM_UNIXTIME(createdat))) AS monat, year(date(FROM_UNIXTIME(createdat))) AS jahr
		FROM tl_rateit_ratings r
		WHERE r.pid=$item_id
		GROUP BY monat, jahr
		ORDER BY jahr DESC , monat DESC
		LIMIT 0 , 12";

        $arrResult = Database::getInstance()->prepare($sql)
            ->execute()
            ->fetchAllAssoc()
        ;

        $arrResult = array_reverse($arrResult);

        $this->loadLanguageFile('default');

        $arr = [];
        $arr['cols'] = [];
        $arr['rows'] = [];

        // Spalten anlegen
        $arr['cols'][] = ['id' => 'month', 'label' => $GLOBALS['TL_LANG']['tl_rateit']['month_chart_legend'][3], 'type' => 'string'];
        $arr['cols'][] = ['id' => 'count', 'label' => $GLOBALS['TL_LANG']['tl_rateit']['month_chart_legend'][4], 'type' => 'number'];
        $arr['cols'][] = ['id' => 'avg', 'label' => $GLOBALS['TL_LANG']['tl_rateit']['month_chart_legend'][2], 'type' => 'number'];

        // Zeilen anlegen
        foreach ($arrResult as $result) {
            $month = $GLOBALS['TL_LANG']['MONTHS'][$result['monat'] - 1].' '.$result['jahr'];
            $avgValue = round((float) ($result['bewertung'] * $this->intStars / 100), 1);
            $arr['rows'][] = ['c' => [['v' => $month],
                ['v' => (int) $result['anzahl']],
                ['v' => $avgValue]]];
        }

        return json_encode($arr);
    }

    protected function percentToStars($percent): float
    {
        $modifier = 100 / $this->intStars;

        return round($percent / $modifier, 1);
    }

    /**
     * Convert encoding.
     *
     * @param        $strString String to convert
     * @param string $from      charset to convert from
     * @param string $to        charset to convert to
     *
     * @return string
     */
    public function convertEncoding($strString, $from, $to)
    {
        if (\function_exists('mb_strlen')) {
            @mb_substitute_character('none');

            return @mb_convert_encoding($strString, $to, $from);
        }
        if (\function_exists('iconv')) {
            if (\strlen($iconv = @iconv($from, $to.'//IGNORE', $strString))) {
                return $iconv;
            }

            return @iconv($from, $to, $strString);
        }

        return $strString;
    }

    private function getUsedTypes(): array
    {
        return Database::getInstance()->execute('SELECT typ FROM tl_rateit_items GROUP BY typ ORDER BY typ')->fetchEach('typ');
    }
} // class rateitBackendModule
